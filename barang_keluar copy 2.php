<?php
// barang_keluar.php
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// require_once 'auth.php'; // Aktifkan jika ada auth

$currentUser = $_SESSION['user']['username'] ?? 'System';
$pageTitle   = 'Barang Keluar (Outbound)';

// ==========================
// 1. Daftar gudang
// ==========================
$stmt = $pdo->query("SELECT id, name, code FROM warehouses ORDER BY name ASC");
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// 1b. Daftar PO dari barang aktif
// ==========================
$poStmt = $pdo->query("
    SELECT DISTINCT po_number
    FROM rfid_registrations
    WHERE is_active = 1
      AND po_number IS NOT NULL
      AND po_number <> ''
    ORDER BY po_number ASC
");
$activePos = $poStmt->fetchAll(PDO::FETCH_COLUMN);

$error = '';
$successMsg = '';

// ==========================
// 2. Handle Form Submit
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouseId     = (int)($_POST['warehouse_id'] ?? 0);
    $rfidRaw         = trim($_POST['rfid_tags'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');
    $customerName    = trim($_POST['customer_name'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $poNumber        = trim($_POST['po_number'] ?? '');

    if ($warehouseId <= 0) {
        $error = 'Silakan pilih gudang asal barang.';
    } elseif ($customerName === '' || $customerAddress === '') {
        $error = 'Nama dan Alamat Customer wajib diisi untuk Surat Jalan.';
    } elseif ($rfidRaw === '') {
        $error = 'RFID Tag kosong. Silakan scan barang terlebih dahulu.';
    } else {
        $tags = preg_split('/\r\n|\r|\n/', $rfidRaw);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags, fn($t) => $t !== '');

        if (empty($tags)) {
            $error = 'Format RFID tidak valid.';
        } else {
            // Cek duplikasi di form
            $counts = array_count_values($tags);
            $dupInForm = [];
            foreach ($counts as $tag => $cnt) {
                if ($cnt > 1) $dupInForm[] = $tag;
            }

            if (!empty($dupInForm)) {
                $error = 'Duplikasi tag di input (batalkan): ' . implode(', ', $dupInForm);
            } else {
                $tags = array_values(array_unique($tags));

                // Prepare Statements
                $getReg = $pdo->prepare("
                    SELECT *
                    FROM rfid_registrations
                    WHERE rfid_tag = :tag
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $movementInsert = $pdo->prepare("
                    INSERT INTO stock_movements
                        (rfid_tag, registration_id, warehouse_id, movement_type, movement_time, created_by, notes)
                    VALUES
                        (:rfid_tag, :registration_id, :warehouse_id, 'OUT', :movement_time, :created_by, :notes)
                ");
                $updateInactive = $pdo->prepare("
                    UPDATE rfid_registrations
                    SET is_active = 0
                    WHERE id = :id
                ");

                $notRegistered = [];
                $insertedTags  = [];
                $now = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))
                    ->format('Y-m-d H:i:s');

                // Mulai Transaksi Database
                $pdo->beginTransaction();

                try {
                    // A. Proses Stock Movement (OUT)
                    foreach ($tags as $tag) {
                        $getReg->execute([':tag' => $tag]);
                        $reg = $getReg->fetch(PDO::FETCH_ASSOC);

                        if (!$reg) {
                            $notRegistered[] = $tag;
                            continue;
                        }

                        // Insert Log OUT
                        $movementInsert->execute([
                            ':rfid_tag'        => $tag,
                            ':registration_id' => $reg['id'],
                            ':warehouse_id'    => $warehouseId,
                            ':movement_time'   => $now,
                            ':created_by'      => $currentUser,
                            ':notes'           => $notes,
                        ]);

                        // Nonaktifkan Tag
                        $updateInactive->execute([':id' => $reg['id']]);
                        $insertedTags[] = $tag;
                    }

                    if (empty($insertedTags)) {
                        throw new Exception('Tidak ada tag valid yang diproses. Pastikan tag sudah terdaftar.');
                    }

                    // B. Buat Header Surat Jalan
                    $tanggalSj = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))
                        ->format('Y-m-d');
                    $insertSj = $pdo->prepare("
                        INSERT INTO surat_jalan
                            (no_sj, tanggal_sj, customer_name, customer_address, po_number, warehouse_id, notes, created_by)
                        VALUES
                            ('', :tanggal_sj, :customer_name, :customer_address, :po_number, :warehouse_id, :notes, :created_by)
                    ");
                    $insertSj->execute([
                        ':tanggal_sj'      => $tanggalSj,
                        ':customer_name'   => $customerName,
                        ':customer_address'=> $customerAddress,
                        ':po_number'       => $poNumber,
                        ':warehouse_id'    => $warehouseId,
                        ':notes'           => $notes,
                        ':created_by'      => $currentUser,
                    ]);

                    $sjId = (int)$pdo->lastInsertId();
                    $noSj = 'SJ/' . date('ym') . '/' . str_pad($sjId, 4, '0', STR_PAD_LEFT);

                    // Update No SJ
                    $pdo->prepare("UPDATE surat_jalan SET no_sj = :no_sj WHERE id = :id")
                        ->execute([':no_sj' => $noSj, ':id' => $sjId]);

                    // C. Buat Detail Item Surat Jalan
                    $detailInsert = $pdo->prepare("
                        INSERT INTO surat_jalan_items
                            (surat_jalan_id, rfid_tag, product_name, batch_number, qty, unit)
                        VALUES
                            (:sj_id, :rfid_tag, :product_name, :batch_number, :qty, :unit)
                    ");

                    foreach ($insertedTags as $tag) {
                        $getReg->execute([':tag' => $tag]);
                        $reg = $getReg->fetch(PDO::FETCH_ASSOC);
                        if (!$reg) continue;

                        $detailInsert->execute([
                            ':sj_id'        => $sjId,
                            ':rfid_tag'     => $tag,
                            ':product_name' => $reg['product_name'] ?? 'Unknown',
                            ':batch_number' => $reg['batch_number'] ?? '-',
                            ':qty'          => (int)($reg['pcs'] ?? 1),
                            ':unit'         => 'PCS',
                        ]);
                    }

                    // Commit Transaksi
                    $pdo->commit();

                    if (!empty($notRegistered)) {
                        $_SESSION['flash_error'] =
                            'Peringatan: Sebagian tag tidak terdaftar dan dilewati: ' .
                            implode(', ', $notRegistered);
                    }

                    // Redirect ke Cetak
                    header('Location: surat_jalan_cetak.php?id=' . $sjId);
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Gagal memproses: ' . $e->getMessage();
                }
            }
        }
    }
}

// ==========================
// 3. Log Barang Keluar
// ==========================
$logStmt = $pdo->query("
    SELECT sm.*, w.name AS warehouse_name, rr.product_name, rr.po_number, rr.so_number
    FROM stock_movements sm
    LEFT JOIN warehouses w ON sm.warehouse_id = w.id
    LEFT JOIN rfid_registrations rr ON sm.registration_id = rr.id
    WHERE sm.movement_type = 'OUT'
    ORDER BY sm.movement_time DESC, sm.id DESC
    LIMIT 20
");
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<style>
    .rfid-console {
        background-color: #212529;
        color: #ffc107; /* Kuning untuk Outbound */
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 0.95rem;
        border: 2px solid #343a40;
    }
    .rfid-console:focus {
        background-color: #212529;
        color: #ffc107;
        border-color: #dc3545;
        box-shadow: none;
    }
    .scanner-box {
        background: #fff5f5;
        border: 1px dashed #feb2b2;
        border-radius: 8px;
        padding: 15px;
    }
    @keyframes pulse-red {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    .status-scanning {
        animation: pulse-red 2s infinite;
        background-color: #dc3545 !important;
        border-color: #dc3545 !important;
        color: white !important;
    }
</style>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-danger text-white py-3">
                <h6 class="m-0 fw-bold"><i class="bi bi-truck me-2"></i>Form Barang Keluar & Surat Jalan</h6>
            </div>
            <div class="card-body">

                <?php if (!empty($_SESSION['flash_error'])): ?>
                    <div class="alert alert-warning small py-2 mb-3">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION['flash_error']); ?>
                        <?php unset($_SESSION['flash_error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger small py-2 mb-3">
                        <i class="bi bi-x-circle me-1"></i>
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">
                            1. Tujuan Pengiriman
                        </label>
                        <div class="card bg-light border-0 p-3">
                            <div class="mb-2">
                                <label class="small text-muted">Nama Customer</label>
                                <input type="text" name="customer_name"
                                       class="form-control form-control-sm fw-bold"
                                       placeholder="PT. Pelanggan Setia"
                                       value="<?= htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                                       required>
                            </div>
                            <div class="mb-2">
                                <label class="small text-muted">Alamat Lengkap</label>
                                <textarea name="customer_address"
                                          class="form-control form-control-sm"
                                          rows="2"
                                          placeholder="Jalan Raya No. 123..."
                                          required><?= htmlspecialchars($_POST['customer_address'] ?? ''); ?></textarea>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="small text-muted">No. PO Customer</label>
                                    <!-- Input dengan datalist PO aktif -->
                                    <input type="text"
                                           name="po_number"
                                           list="poOptions"
                                           class="form-control form-control-sm"
                                           placeholder="Pilih / ketik PO"
                                           value="<?= htmlspecialchars($_POST['po_number'] ?? ''); ?>">
                                    <datalist id="poOptions">
                                        <?php foreach ($activePos as $po): ?>
                                            <option value="<?= htmlspecialchars($po); ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="form-text small">
                                        Saran otomatis diambil dari barang yang masih aktif.
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Gudang Asal</label>
                                    <select name="warehouse_id" class="form-select form-select-sm" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach ($warehouses as $g): ?>
                                            <option value="<?= (int)$g['id']; ?>"
                                                <?= (isset($_POST['warehouse_id']) && $_POST['warehouse_id'] == $g['id']) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($g['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">
                            2. Scan Barang Keluar
                        </label>

                        <div class="scanner-box">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold text-danger">
                                    <i class="bi bi-upc-scan me-1"></i> SCANNER
                                </div>
                                <span id="scanBadge" class="badge bg-secondary rounded-pill px-3">IDLE</span>
                            </div>

                            <div class="btn-group w-100 mb-2">
                                <button type="button" id="btnStartScan"
                                        class="btn btn-outline-danger fw-bold">
                                    <i class="bi bi-play-fill"></i> START
                                </button>
                                <button type="button" id="btnStopScan"
                                        class="btn btn-outline-secondary fw-bold" disabled>
                                    <i class="bi bi-stop-fill"></i> STOP
                                </button>
                            </div>

                            <textarea name="rfid_tags" id="rfid_tags"
                                      class="form-control rfid-console"
                                      rows="5"
                                      placeholder="> Siap scan barang keluar..."
                                      required><?= htmlspecialchars($_POST['rfid_tags'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold text-muted">Preview Item</label>
                            <small class="text-muted" style="font-size: 0.7rem;">Otomatis update saat scan</small>
                        </div>
                        <div id="previewContainer"
                             class="border rounded bg-white p-0"
                             style="max-height: 150px; overflow-y: auto;">
                            <div class="text-center text-muted py-3 small">
                                Belum ada item di-scan.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <input type="text" name="notes"
                               class="form-control form-control-sm"
                               placeholder="Catatan Tambahan (Opsional)"
                               value="<?= htmlspecialchars($_POST['notes'] ?? ''); ?>">
                    </div>

                    <button type="submit"
                            class="btn btn-danger w-100 py-2 fw-bold shadow-sm">
                        <i class="bi bi-printer me-2"></i>
                        PROSES & CETAK SURAT JALAN
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-danger">
                    <i class="bi bi-clock-history me-2"></i>Riwayat Pengeluaran
                </h6>
                <button class="btn btn-sm btn-light border"
                        onclick="location.reload()" title="Refresh">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0"
                           style="font-size: 0.9rem;">
                        <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-3">Waktu</th>
                            <th>Gudang</th>
                            <th>Info Produk</th>
                            <th>Tag ID</th>
                            <th class="pe-3">User</th>
                        </tr>
                        </thead>
                        <tbody class="border-top-0">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-box-seam fs-1 d-block mb-2 opacity-25"></i>
                                    Belum ada data barang keluar.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $row): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark">
                                            <?= date('H:i', strtotime($row['movement_time'])); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?= date('d/m/y', strtotime($row['movement_time'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($row['warehouse_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-truncate" style="max-width: 150px;">
                                            <?= htmlspecialchars($row['product_name']); ?>
                                        </div>
                                        <div class="small text-muted">
                                            Ref: <?= htmlspecialchars($row['so_number'] ?: '-'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-monospace small bg-white border px-1 rounded text-danger">
                                            <?= htmlspecialchars($row['rfid_tag']); ?>
                                        </span>
                                    </td>
                                    <td class="pe-3 small text-muted">
                                        <?= htmlspecialchars($row['created_by'] ?? '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const btnStart   = document.getElementById('btnStartScan');
const btnStop    = document.getElementById('btnStopScan');
const rfidArea   = document.getElementById('rfid_tags');
const scanBadge  = document.getElementById('scanBadge');
const previewContainer = document.getElementById('previewContainer');

let scanTimer = null;
let previewTimer = null;

function setScanningState(isScanning) {
    if (isScanning) {
        scanBadge.textContent = 'SCANNING...';
        scanBadge.className = 'badge rounded-pill px-3 status-scanning';
        btnStart.disabled = true;
        btnStop.disabled = false;
        btnStart.classList.replace('btn-outline-danger', 'btn-danger');
    } else {
        scanBadge.textContent = 'IDLE';
        scanBadge.className = 'badge bg-secondary rounded-pill px-3';
        btnStart.disabled = false;
        btnStop.disabled = true;
        btnStart.classList.replace('btn-danger', 'btn-outline-danger');
    }
}

function getCurrentTags() {
    if (!rfidArea) return [];
    return rfidArea.value.split(/\r?\n/)
        .map(s => s.trim())
        .filter(s => s !== '');
}

function refreshPreview() {
    if (!previewContainer) return;
    const tags = getCurrentTags();

    if (tags.length === 0) {
        previewContainer.innerHTML =
            '<div class="text-center text-muted py-3 small">Belum ada item di-scan.</div>';
        return;
    }

    fetch('preview_rfid_tags.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tags: tags })
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !Array.isArray(data.items)) {
                previewContainer.innerHTML =
                    '<div class="p-2 text-danger small">Gagal memuat preview.</div>';
                return;
            }

            let html =
                '<table class="table table-sm table-bordered mb-0 small">' +
                '<thead class="table-light text-muted"><tr>' +
                '<th>Tag</th><th>Produk</th><th>Status</th></tr></thead><tbody>';

            data.items.forEach(row => {
                const registered = !!row.registered;
                const statusBadge = registered
                    ? '<span class="badge bg-success">OK</span>'
                    : '<span class="badge bg-danger">Not Found</span>';

                const productName = registered
                    ? row.product_name
                    : '<span class="text-danger fst-italic">Tidak terdaftar</span>';

                html += `<tr>
                    <td class="font-monospace">${row.tag}</td>
                    <td>${productName}</td>
                    <td class="text-center">${statusBadge}</td>
                </tr>`;
            });

            html += '</tbody></table>';
            previewContainer.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            previewContainer.innerHTML =
                '<div class="p-2 text-danger small">Error koneksi preview.</div>';
        });
}

function schedulePreviewUpdate() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshPreview, 500);
}

function startPolling() {
    if (scanTimer) return;
    setScanningState(true);

    scanTimer = setInterval(() => {
        fetch('get_latest_rfid.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const tags = Array.isArray(data.tags)
                    ? data.tags
                    : (data.epc ? [data.epc] : []);
                if (!tags.length) return;

                let lines = getCurrentTags();
                let isAdded = false;

                tags.forEach(epc => {
                    if (epc && !lines.includes(epc)) {
                        lines.push(epc);
                        isAdded = true;
                    }
                });

                if (isAdded) {
                    rfidArea.value = lines.join("\n");
                    rfidArea.scrollTop = rfidArea.scrollHeight;
                    schedulePreviewUpdate();
                }
            })
            .catch(err => console.error('Polling error:', err));
    }, 500);
}

function stopPolling() {
    if (scanTimer) {
        clearInterval(scanTimer);
        scanTimer = null;
    }
    setScanningState(false);
}

btnStart.addEventListener('click', () => {
    fetch('rfid_control.php?action=start')
        .then(r => r.json())
        .then(() => startPolling())
        .catch(() => alert('Gagal memulai scanner'));
});

btnStop.addEventListener('click', () => {
    fetch('rfid_control.php?action=stop')
        .then(r => r.json())
        .then(() => stopPolling())
        .catch(err => console.error(err));
});

rfidArea.addEventListener('input', schedulePreviewUpdate);

setScanningState(false);
schedulePreviewUpdate();
</script>

<?php
include 'layout/footer.php';
