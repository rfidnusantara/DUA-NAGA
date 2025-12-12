<?php
// barang_masuk.php
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// require_once 'auth.php'; // Aktifkan jika ada auth

$currentUser = $_SESSION['user']['username'] ?? 'System';
$pageTitle = 'Barang Masuk (Inbound)';

// ==========================
// 1. Ambil daftar gudang
// ==========================
$stmt = $pdo->query("SELECT id, name, code FROM warehouses ORDER BY name ASC");
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// 2. Handle Form Submit (LOGIKA PHP TETAP SAMA)
// ==========================
$error = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouseId = (int)($_POST['warehouse_id'] ?? 0);
    $rfidRaw     = trim($_POST['rfid_tags'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    if ($warehouseId <= 0) {
        $error = 'Silakan pilih gudang tujuan terlebih dahulu.';
    } elseif ($rfidRaw === '') {
        $error = 'RFID Tag kosong. Silakan scan tag terlebih dahulu.';
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
                $tags = array_values(array_unique($tags)); // unikkan

                // Prepare statement
                $getReg = $pdo->prepare("SELECT * FROM rfid_registrations WHERE rfid_tag = :tag ORDER BY id DESC LIMIT 1");
                $movementInsert = $pdo->prepare("
                    INSERT INTO stock_movements (rfid_tag, registration_id, warehouse_id, movement_type, movement_time, created_by, notes)
                    VALUES (:rfid_tag, :registration_id, :warehouse_id, 'IN', :movement_time, :created_by, :notes)
                ");
                $updateActive = $pdo->prepare("UPDATE rfid_registrations SET is_active = 1 WHERE id = :id");

                $notRegistered = [];
                $now = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');

                $pdo->beginTransaction(); // Pakai transaksi biar aman
                try {
                    foreach ($tags as $tag) {
                        $getReg->execute([':tag' => $tag]);
                        $reg = $getReg->fetch(PDO::FETCH_ASSOC);

                        if (!$reg) {
                            $notRegistered[] = $tag;
                            continue; // Skip tag tak dikenal
                        }

                        $movementInsert->execute([
                            ':rfid_tag'      => $tag,
                            ':registration_id' => $reg['id'],
                            ':warehouse_id'  => $warehouseId,
                            ':movement_time' => $now,
                            ':created_by'    => $currentUser,
                            ':notes'         => $notes,
                        ]);

                        $updateActive->execute([':id' => $reg['id']]);
                    }

                    if (!empty($notRegistered)) {
                        $pdo->rollBack();
                        $error = 'Gagal! Ada tag yang belum terdaftar: ' . implode(', ', $notRegistered) . '. Harap registrasi dahulu.';
                    } else {
                        $pdo->commit();
                        $successMsg = 'Sukses! ' . count($tags) . ' item berhasil masuk ke stok.';
                        $_POST['rfid_tags'] = ''; // Reset form
                        $_POST['notes'] = '';
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Database Error: ' . $e->getMessage();
                }
            }
        }
    }
}

// ==========================
// 3. Log barang masuk terbaru
// ==========================
$logStmt = $pdo->query("
    SELECT sm.*, w.name AS warehouse_name, rr.product_name, rr.po_number, rr.so_number
    FROM stock_movements sm
    LEFT JOIN warehouses w ON sm.warehouse_id = w.id
    LEFT JOIN rfid_registrations rr ON sm.registration_id = rr.id
    WHERE sm.movement_type = 'IN'
    ORDER BY sm.movement_time DESC, sm.id DESC
    LIMIT 20
");
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<style>
    .rfid-console {
        background-color: #212529; /* Dark background */
        color: #00ff00; /* Green terminal text */
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 0.95rem;
        border: 2px solid #343a40;
    }
    .rfid-console:focus {
        background-color: #212529;
        color: #00ff00;
        border-color: #0d6efd;
        box-shadow: none;
    }
    .scanner-box {
        background: #f8f9fa;
        border: 1px dashed #dee2e6;
        border-radius: 8px;
        padding: 15px;
    }
    /* Animasi Berkedip saat Scanning */
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
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="bi bi-box-arrow-in-down me-2"></i>Form Barang Masuk</h6>
            </div>
            <div class="card-body">
                
                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2 fs-4"></i>
                        <div><?= htmlspecialchars($successMsg); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
                        <div><?= htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    
                    <div class="form-floating mb-3">
                        <select name="warehouse_id" id="warehouse_id" class="form-select" required>
                            <option value="">-- Pilih Lokasi Gudang --</option>
                            <?php foreach ($warehouses as $g): ?>
                                <option value="<?= (int)$g['id']; ?>" <?= (isset($_POST['warehouse_id']) && $_POST['warehouse_id'] == $g['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($g['name']); ?> (<?= htmlspecialchars($g['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="warehouse_id">Gudang Tujuan</label>
                    </div>

                    <div class="scanner-box mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="fw-bold small text-uppercase text-muted">RFID Scanner Control</label>
                            <span id="scanBadge" class="badge bg-secondary rounded-pill px-3">IDLE</span>
                        </div>
                        
                        <div class="btn-group w-100 mb-2">
                            <button type="button" id="btnStartScan" class="btn btn-outline-success fw-bold">
                                <i class="bi bi-play-fill"></i> START
                            </button>
                            <button type="button" id="btnStopScan" class="btn btn-outline-danger fw-bold" disabled>
                                <i class="bi bi-stop-fill"></i> STOP
                            </button>
                        </div>

                        <textarea name="rfid_tags" id="rfid_tags" 
                                  class="form-control rfid-console" 
                                  rows="5" 
                                  placeholder="> Menunggu input scanner..."
                                  required><?= htmlspecialchars($_POST['rfid_tags'] ?? ''); ?></textarea>
                        <div class="text-end mt-1">
                            <small class="text-muted fst-italic">Pastikan kursor aktif di area hitam saat scanning.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Live Preview Data</label>
                        <div id="previewContainer" class="border rounded bg-light p-0" style="min-height: 50px; max-height: 200px; overflow-y: auto;">
                            <div class="text-center text-muted py-3 small">
                                <i class="bi bi-upc-scan fs-4 d-block mb-1"></i>
                                Belum ada tag terbaca
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" name="notes" class="form-control" id="notes" placeholder="Catatan" value="<?= htmlspecialchars($_POST['notes'] ?? ''); ?>">
                        <label for="notes">Catatan / No. Surat Jalan (Opsional)</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="bi bi-save me-2"></i> SIMPAN BARANG MASUK
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary"><i class="bi bi-clock-history me-2"></i>Riwayat Barang Masuk</h6>
                <button class="btn btn-sm btn-light border" onclick="location.reload()" title="Refresh Data">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th class="ps-3" width="5%">No</th>
                                <th width="20%">Waktu & Gudang</th>
                                <th width="30%">Info Produk</th>
                                <th width="15%">RFID</th>
                                <th class="pe-3">User</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                    Belum ada data barang masuk hari ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($logs as $row): ?>
                                <tr>
                                    <td class="ps-3 text-center text-muted"><?= $no++; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= date('H:i', strtotime($row['movement_time'])); ?></div>
                                        <div class="small text-muted"><?= date('d/m/y', strtotime($row['movement_time'])); ?></div>
                                        <span class="badge bg-light text-dark border mt-1">
                                            <?= htmlspecialchars($row['warehouse_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary text-truncate" style="max-width: 180px;">
                                            <?= htmlspecialchars($row['product_name']); ?>
                                        </div>
                                        <div class="small text-muted">
                                            PO: <?= htmlspecialchars($row['po_number']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-monospace small bg-white border px-1 rounded">
                                            <?= htmlspecialchars($row['rfid_tag']); ?>
                                        </span>
                                    </td>
                                    <td class="pe-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width:28px; height:28px; font-size:0.8rem;">
                                                <?= strtoupper(substr($row['created_by'] ?? 'S', 0, 1)); ?>
                                            </div>
                                            <span class="small"><?= htmlspecialchars($row['created_by'] ?? '-'); ?></span>
                                        </div>
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

// Helper: Ubah Status UI
function setScanningState(isScanning) {
    if (isScanning) {
        scanBadge.textContent = 'SCANNING...';
        scanBadge.className = 'badge rounded-pill px-3 status-scanning'; // Add pulse class
        btnStart.disabled = true;
        btnStop.disabled = false;
        btnStart.classList.replace('btn-outline-success', 'btn-success');
    } else {
        scanBadge.textContent = 'IDLE';
        scanBadge.className = 'badge bg-secondary rounded-pill px-3';
        btnStart.disabled = false;
        btnStop.disabled = true;
        btnStart.classList.replace('btn-success', 'btn-outline-success');
    }
}

// Logic: Ambil isi textarea jadi array
function getCurrentTags() {
    if (!rfidArea) return [];
    return rfidArea.value
        .split(/\r?\n/)
        .map(s => s.trim())
        .filter(s => s !== '');
}

// Logic: Refresh Preview Table via AJAX
function refreshPreview() {
    if (!previewContainer) return;
    const tags = getCurrentTags();

    if (tags.length === 0) {
        previewContainer.innerHTML = `
            <div class="text-center text-muted py-3 small">
                <i class="bi bi-upc-scan fs-4 d-block mb-1"></i>
                Belum ada tag terbaca
            </div>`;
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
            previewContainer.innerHTML = '<div class="p-2 text-danger small">Gagal memuat preview.</div>';
            return;
        }

        let html = '<table class="table table-sm table-bordered mb-0 small">';
        html += '<thead class="table-light text-muted"><tr><th>Tag</th><th>Produk</th><th>Status</th></tr></thead><tbody>';

        data.items.forEach(row => {
            const registered = !!row.registered;
            const statusBadge = registered 
                ? '<span class="badge bg-success">Valid</span>' 
                : '<span class="badge bg-danger">Unknown</span>';
            const productName = registered ? row.product_name : '<span class="text-danger fst-italic">Tidak terdaftar</span>';
            
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
        previewContainer.innerHTML = '<div class="p-2 text-danger small">Error koneksi preview.</div>';
    });
}

function schedulePreviewUpdate() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshPreview, 500); // Delay 0.5s agar tidak spam request
}

// Logic: Polling Hardware
function startPolling() {
    if (scanTimer) return;
    setScanningState(true);

    scanTimer = setInterval(() => {
        fetch('get_latest_rfid.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                const tags = Array.isArray(data.tags) ? data.tags : (data.epc ? [data.epc] : []);
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
                    rfidArea.scrollTop = rfidArea.scrollHeight; // Auto scroll ke bawah
                    schedulePreviewUpdate(); // Trigger preview update
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

// Event Listeners
btnStart.addEventListener('click', () => {
    fetch('rfid_control.php?action=start')
        .then(r => r.json())
        .then(() => startPolling())
        .catch(err => alert('Gagal memulai hardware scanner'));
});

btnStop.addEventListener('click', () => {
    fetch('rfid_control.php?action=stop')
        .then(r => r.json())
        .then(() => stopPolling())
        .catch(err => console.error(err));
});

rfidArea.addEventListener('input', schedulePreviewUpdate);

// Init
setScanningState(false);
schedulePreviewUpdate();
</script>

<?php
include 'layout/footer.php';
?>