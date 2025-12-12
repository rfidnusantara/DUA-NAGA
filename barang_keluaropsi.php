<?php
// barang_keluar.php
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Wajib login kalau pakai auth
require_once 'auth.php';

$currentUser = $_SESSION['user']['username'] ?? null;
$pageTitle   = 'Barang Keluar (RFID)';

// ==========================
// 1. Daftar gudang
// ==========================
$stmt = $pdo->query("SELECT id, name, code FROM warehouses ORDER BY name ASC");
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouseId = (int)($_POST['warehouse_id'] ?? 0);
    $rfidRaw     = trim($_POST['rfid_tags'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    if ($warehouseId <= 0) {
        $error = 'Silakan pilih gudang asal barang keluar.';
    } elseif ($rfidRaw === '') {
        $error = 'RFID Tag tidak boleh kosong (klik Start lalu scan).';
    } else {
        $tags = preg_split('/\r\n|\r|\n/', $rfidRaw);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags, fn($t) => $t !== '');

        if (empty($tags)) {
            $error = 'RFID Tag tidak valid. Pastikan ada minimal 1 tag.';
        } else {
            // Cek double di form
            $counts = array_count_values($tags);
            $dupInForm = [];
            foreach ($counts as $tag => $cnt) {
                if ($cnt > 1) $dupInForm[] = $tag;
            }

            if (!empty($dupInForm)) {
                $error = 'Terdapat RFID Tag double di form (tidak disimpan): ' . implode(', ', $dupInForm);
            } else {
                $tags = array_values(array_unique($tags));

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

                // UPDATE status aktif (OUT => nonaktif)
                $updateInactive = $pdo->prepare("
                    UPDATE rfid_registrations
                    SET is_active = 0
                    WHERE id = :id
                ");

                $notRegistered = [];
                // WIB
                $now = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))
                    ->format('Y-m-d H:i:s');

                foreach ($tags as $tag) {
                    $getReg->execute([':tag' => $tag]);
                    $reg = $getReg->fetch(PDO::FETCH_ASSOC);

                    if (!$reg) {
                        $notRegistered[] = $tag;
                        continue;
                    }

                    $movementInsert->execute([
                        ':rfid_tag'        => $tag,
                        ':registration_id' => $reg['id'],
                        ':warehouse_id'    => $warehouseId,
                        ':movement_time'   => $now,
                        ':created_by'      => $currentUser,
                        ':notes'           => $notes,
                    ]);

                    // Set status NON AKTIF
                    $updateInactive->execute([
                        ':id' => $reg['id'],
                    ]);
                }

                if (!empty($notRegistered)) {
                    $error = 'Sebagian tag tidak memiliki data registrasi dan tidak disimpan: ' . implode(', ', $notRegistered);
                } else {
                    $successMsg = 'Barang keluar berhasil disimpan untuk ' . count($tags) . ' tag, dan status tag dinonaktifkan.';
                    $_POST['rfid_tags'] = '';
                }
            }
        }
    }
}

// ==========================
// 2. Log barang keluar
// ==========================
$logStmt = $pdo->query("
    SELECT sm.*, w.name AS warehouse_name, rr.product_name, rr.po_number, rr.so_number
    FROM stock_movements sm
    LEFT JOIN warehouses w ON sm.warehouse_id = w.id
    LEFT JOIN rfid_registrations rr ON sm.registration_id = rr.id
    WHERE sm.movement_type = 'OUT'
    ORDER BY sm.movement_time DESC, sm.id DESC
    LIMIT 50
");
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<div class="row g-3">
    <!-- FORM BARANG KELUAR -->
    <div class="col-md-5">
        <div class="card card-elevated">
            <div class="card-body">
                <h5 class="mb-3">Barang Keluar (RFID)</h5>

                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success py-2">
                        <?= htmlspecialchars($successMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2">
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Gudang Asal</label>
                        <select name="warehouse_id" class="form-select" required>
                            <option value="">-- Pilih Gudang --</option>
                            <?php foreach ($warehouses as $g): ?>
                                <option value="<?= (int)$g['id']; ?>"
                                    <?= (isset($_POST['warehouse_id']) && (int)$_POST['warehouse_id'] === (int)$g['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($g['name'] . ' (' . $g['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>RFID Tag (barang keluar, 1 baris 1 tag)</span>
                            <span class="small">
                                <button type="button" id="btnStartScan" class="btn btn-sm btn-success">
                                    Start
                                </button>
                                <button type="button" id="btnStopScan" class="btn btn-sm btn-outline-secondary">
                                    Stop
                                </button>
                            </span>
                        </label>
                        <textarea name="rfid_tags" id="rfid_tags"
                                  class="form-control"
                                  rows="6"
                                  placeholder="Klik Start lalu scan beberapa tag. Setiap tag akan muncul di baris baru."
                                  required><?= htmlspecialchars($_POST['rfid_tags'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Status: <span id="scanStatus">Idle</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan (opsional)</label>
                        <input type="text" name="notes" class="form-control"
                               value="<?= htmlspecialchars($_POST['notes'] ?? ''); ?>"
                               placeholder="Contoh: pengeluaran ke customer X.">
                    </div>

                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-box-arrow-up"></i> Simpan Barang Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- LOG BARANG KELUAR TERBARU -->
    <div class="col-md-7">
        <div class="card card-elevated">
            <div class="card-body">
                <h5 class="mb-3">Log Barang Keluar Terbaru</h5>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Waktu</th>
                            <th>Gudang</th>
                            <th>RFID Tag</th>
                            <th>Product</th>
                            <th>PO</th>
                            <th>SO</th>
                            <th>User</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">
                                    Belum ada data barang keluar.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($logs as $row): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['movement_time']); ?></td>
                                    <td><?= htmlspecialchars($row['warehouse_name']); ?></td>
                                    <td><?= htmlspecialchars($row['rfid_tag']); ?></td>
                                    <td><?= htmlspecialchars($row['product_name']); ?></td>
                                    <td><?= htmlspecialchars($row['po_number']); ?></td>
                                    <td><?= htmlspecialchars($row['so_number']); ?></td>
                                    <td><?= htmlspecialchars($row['created_by'] ?? '-'); ?></td>
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
const scanStatus = document.getElementById('scanStatus');

let scanTimer = null;

function updateStatus(text) {
    if (scanStatus) scanStatus.textContent = text;
}

function startPolling() {
    if (scanTimer) return;

    updateStatus('Scanning...');
    if (btnStart) btnStart.disabled = true;
    if (btnStop)  btnStop.disabled  = false;

    scanTimer = setInterval(() => {
        fetch('get_latest_rfid.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                const tags = Array.isArray(data.tags)
                    ? data.tags
                    : (data.epc ? [data.epc] : []);

                if (!tags.length || !rfidArea) return;

                let lines = rfidArea.value
                    .split(/\r?\n/)
                    .map(s => s.trim())
                    .filter(s => s !== '');

                tags.forEach(epc => {
                    if (epc && !lines.includes(epc)) {
                        lines.push(epc);
                    }
                });

                rfidArea.value = lines.join("\n");
            })
            .catch(err => console.error('get_latest_rfid error:', err));
    }, 500);
}

function stopPolling() {
    if (scanTimer) {
        clearInterval(scanTimer);
        scanTimer = null;
    }
    updateStatus('Stopped');
    if (btnStart) btnStart.disabled = false;
    if (btnStop)  btnStop.disabled  = true;
}

if (btnStart) {
    btnStart.addEventListener('click', () => {
        fetch('rfid_control.php?action=start')
            .then(r => r.json())
            .then(() => { startPolling(); })
            .catch(err => console.error('rfid_control start error:', err));
    });
}

if (btnStop) {
    btnStop.addEventListener('click', () => {
        fetch('rfid_control.php?action=stop')
            .then(r => r.json())
            .then(() => { stopPolling(); })
            .catch(err => console.error('rfid_control stop error:', err));
    });
}

if (btnStop) btnStop.disabled = true;
updateStatus('Idle');
</script>

<?php
include 'layout/footer.php';
