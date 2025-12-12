<?php
// ==========================================
// FILE: barang_keluar.php (STABLE VERSION + FILTER)
// ==========================================

require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUser = $_SESSION['user']['username'] ?? 'System';
$pageTitle   = 'Barang Keluar (Outbound)';

// =========================================================================
// 0. FUNGSI BANTUAN (UTILITY)
// =========================================================================
function parseSplitData($rawString) {
    $rawString = trim($rawString ?? '');
    if (empty($rawString)) return [];
    // Regex memisahkan (1), (2), 1. 2.
    $parts = preg_split('/(?:\(\d+\)|\d+\.)/', $rawString, -1, PREG_SPLIT_NO_EMPTY);
    $cleanParts = [];
    foreach ($parts as $p) {
        $clean = trim($p, " ,.-:\t\n\r\0\x0B"); 
        if (!empty($clean)) $cleanParts[] = $clean;
    }
    if (empty($cleanParts)) return [$rawString];
    return $cleanParts;
}

// =========================================================================
// 1. DATA GUDANG (UNTUK FILTER & FORM)
// =========================================================================
$stmt = $pdo->query("SELECT id, name, code FROM warehouses ORDER BY name ASC");
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil Filter ID dari URL (jika user memilih filter di atas)
$filterWarehouseId = (int)($_GET['filter_warehouse'] ?? 0);

// =========================================================================
// 2. AMBIL DATA DARI API EXTERNAL
// =========================================================================
// Kita ambil data API di sini agar bisa difilter
$apiUrl    = "https://core.db.nagaverse.id/api/client/sales-orders";
$apiParams = "?paginate=false&state=ready_for_delivery"; 
$fullUrl   = $apiUrl . $apiParams;

$apiHeaders = [
    'X-App-Id: fee8c492-acec-4058-a7c1-e540ee6e6eef',
    'X-Api-Key: 7f5b370fb4ecfc552cd4be8bb5ada61a6c69260262ef2029a422a78b517cefc8',
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $apiHeaders);
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$apiResponse = curl_exec($ch);
$apiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$salesOrdersApi = [];

if ($apiHttpCode === 200) {
    $decoded = json_decode($apiResponse, true);
    $rawData = [];
    
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        $rawData = $decoded['data'];
    } elseif (is_array($decoded)) {
        $rawData = $decoded;
    }

    // Filter Data API berdasarkan Perusahaan
    foreach ($rawData as $row) {
        $apiCompanyId = $row['company']['id'] ?? $row['company_id'] ?? 0;
        
        // Jika filter aktif DAN ID perusahaan tidak cocok, lewati
        if ($filterWarehouseId > 0 && $apiCompanyId != $filterWarehouseId) {
            continue; 
        }
        $salesOrdersApi[] = $row;
    }
}

// =========================================================================
// 3. HANDLE FORM SUBMIT (LOGIKA ASLI ANDA - DIPERTAHANKAN)
// =========================================================================
$error = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouseId     = (int)($_POST['warehouse_id'] ?? 0);
    $rfidRaw         = trim($_POST['rfid_tags'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');
    $customerName    = trim($_POST['customer_name'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $poNumber        = trim($_POST['po_number'] ?? '');

    if ($warehouseId <= 0) {
        $error = 'Silakan pilih Perushaan asal barang.';
    } elseif ($customerName === '' || $customerAddress === '') {
        $error = 'Nama dan Alamat Customer wajib diisi.';
    } elseif ($rfidRaw === '') {
        $error = 'RFID Tag kosong. Silakan scan barang terlebih dahulu.';
    } else {
        $tags = preg_split('/\r\n|\r|\n/', $rfidRaw);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags, fn($t) => $t !== '');

        if (empty($tags)) {
            $error = 'Format RFID tidak valid.';
        } else {
            $counts = array_count_values($tags);
            $dupInForm = [];
            foreach ($counts as $tag => $cnt) {
                if ($cnt > 1) $dupInForm[] = $tag;
            }

            if (!empty($dupInForm)) {
                $error = 'Duplikasi tag di input: ' . implode(', ', $dupInForm);
            } else {
                $tags = array_values(array_unique($tags));

                // Query Prepare (Asli)
                $getReg = $pdo->prepare("SELECT * FROM rfid_registrations WHERE rfid_tag = :tag ORDER BY id DESC LIMIT 1");
                $movementInsert = $pdo->prepare("INSERT INTO stock_movements (rfid_tag, registration_id, warehouse_id, movement_type, movement_time, created_by, notes) VALUES (:rfid_tag, :registration_id, :warehouse_id, 'OUT', :movement_time, :created_by, :notes)");
                $updateInactive = $pdo->prepare("UPDATE rfid_registrations SET is_active = 0 WHERE id = :id");

                $notRegistered = [];
                $insertedTags  = [];
                $now = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');

                $pdo->beginTransaction();

                try {
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

                        $updateInactive->execute([':id' => $reg['id']]);
                        $insertedTags[] = $tag;
                    }

                    if (empty($insertedTags)) {
                        throw new Exception('Tidak ada tag valid yang diproses.');
                    }

                    // Header Surat Jalan
                    $tanggalSj = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
                    $insertSj = $pdo->prepare("INSERT INTO surat_jalan (no_sj, tanggal_sj, customer_name, customer_address, po_number, warehouse_id, notes, created_by) VALUES ('', :tanggal_sj, :customer_name, :customer_address, :po_number, :warehouse_id, :notes, :created_by)");
                    
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

                    $pdo->prepare("UPDATE surat_jalan SET no_sj = :no_sj WHERE id = :id")->execute([':no_sj' => $noSj, ':id' => $sjId]);

                    // Detail Surat Jalan
                    $detailInsert = $pdo->prepare("INSERT INTO surat_jalan_items (surat_jalan_id, rfid_tag, product_name, batch_number, qty, unit) VALUES (:sj_id, :rfid_tag, :product_name, :batch_number, :qty, :unit)");

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

                    $pdo->commit();

                    if (!empty($notRegistered)) {
                        $_SESSION['flash_error'] = 'Peringatan: Sebagian tag tidak terdaftar: ' . implode(', ', $notRegistered);
                    }

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
// 4. Log Barang Keluar
// ==========================
$logStmt = $pdo->query("SELECT sm.*, w.name AS warehouse_name, rr.product_name, rr.po_number, rr.so_number FROM stock_movements sm LEFT JOIN warehouses w ON sm.warehouse_id = w.id LEFT JOIN rfid_registrations rr ON sm.registration_id = rr.id WHERE sm.movement_type = 'OUT' ORDER BY sm.movement_time DESC, sm.id DESC LIMIT 10");
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    /* Styling Scanner */
    .rfid-console { background-color: #212529; color: #ffc107; font-family: 'Consolas', monospace; font-size: 0.95rem; border: 2px solid #343a40; }
    .rfid-console:focus { background-color: #212529; color: #ffc107; border-color: #dc3545; box-shadow: none; }
    .scanner-box { background: #fff5f5; border: 1px dashed #feb2b2; border-radius: 8px; padding: 15px; }
    
    /* Animasi Scanning */
    @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
    .status-scanning { animation: pulse-red 2s infinite; background-color: #dc3545 !important; border-color: #dc3545 !important; color: white !important; }
    
    /* Select2 Height Fix */
    .select2-container--bootstrap-5 .select2-selection { border-color: #ced4da; }
    .select2-container .select2-selection--single { height: 38px !important; }
    
    /* Kartu Filter */
    .bg-api-filter { background-color: #eef2ff; border: 1px dashed #c7d2fe; }
</style>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-danger text-white py-3">
                <h6 class="m-0 fw-bold"><i class="bi bi-truck me-2"></i>Form Barang Keluar</h6>
            </div>
            <div class="card-body">

                <?php if (!empty($_SESSION['flash_error'])): ?>
                    <div class="alert alert-warning small py-2 mb-3"><?= htmlspecialchars($_SESSION['flash_error']); ?><?php unset($_SESSION['flash_error']); ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger small py-2 mb-3"><?= htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="get" class="mb-3">
                    <label class="form-label small fw-bold text-muted">FILTER PERUSAHAAN </label>
                    <div class="input-group input-group-sm">
                        <select name="filter_warehouse" class="form-select select2-filter" onchange="this.form.submit()">
                            <option value="">-- Tampilkan Semua --</option>
                            <?php foreach ($warehouses as $g): ?>
                                <option value="<?= $g['id']; ?>" <?= ($filterWarehouseId == $g['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($g['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if($filterWarehouseId > 0): ?>
                            <a href="barang_keluar.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="form-text small mb-2 text-end fst-italic">Filter data API di bawah berdasarkan perusahaan.</div>
                </form>

                <hr>

                <form method="post" autocomplete="off">

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">1. Tujuan Pengiriman</label>
                        
                        <div class="card p-2 mb-3 bg-api-filter">
                            <label class="small fw-bold text-primary mb-1">Cari Data (PO / Customer):</label>
                            <select id="api_po_selector" class="form-select select2-api">
                                <option value="">-- Ketik untuk Mencari --</option>
                                <?php foreach ($salesOrdersApi as $row): ?>
                                    <?php 
                                        $po   = $row['customer_order']['number'] ?? ($row['customer_order_id'] ?? '-'); 
                                        $comp = $row['company']['name'] ?? 'Umum';
                                        
                                        $rawName = $row['customer']['name'] ?? 'No Name';
                                        $names   = parseSplitData($rawName);
                                        $dispName = $names[0] ?? $rawName;
                                        if(strlen($dispName) > 30) $dispName = substr($dispName, 0, 30).'...';

                                        // Data Lengkap untuk JS
                                        $fullRawName = $row['customer']['name'] ?? '';
                                        $fullRawAddr = !empty($row['delivery_address']) ? $row['delivery_address'] : ($row['customer']['address'] ?? '');
                                    ?>
                                    <option value="<?= htmlspecialchars($po); ?>" 
                                        data-raw-name="<?= htmlspecialchars($fullRawName); ?>"
                                        data-raw-addr="<?= htmlspecialchars($fullRawAddr); ?>"
                                        data-company-id="<?= htmlspecialchars($row['company']['id'] ?? ''); ?>">
                                        [<?= htmlspecialchars($comp); ?>] PO: <?= htmlspecialchars($po); ?> | <?= htmlspecialchars($dispName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="card bg-light border-0 p-3">
                            <div class="mb-2">
                                <label class="small text-muted">Nama Customer</label>
                                <select name="customer_name" id="customer_name" class="form-select form-select-sm fw-bold" required onchange="syncAddress()">
                                    <option value="">-- Pilih dari API di atas --</option>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="small text-muted">Alamat Lengkap</label>
                                <textarea name="customer_address" id="customer_address" class="form-control form-control-sm" rows="2" placeholder="Otomatis terisi..." readonly required></textarea>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="small text-muted">No. PO Customer</label>
                                    <input type="text" name="po_number" id="po_number" class="form-control form-control-sm" readonly value="<?= htmlspecialchars($_POST['po_number']??'') ?>">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Perusahaan Asal</label>
                                    <select name="warehouse_id" id="warehouse_id" class="form-select form-select-sm" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach ($warehouses as $g): ?>
                                            <option value="<?= (int)$g['id']; ?>" <?= ($filterWarehouseId == $g['id']) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($g['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">2. Scan Barang Keluar</label>
                        <div class="scanner-box">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold text-danger"><i class="bi bi-upc-scan me-1"></i> SCANNER</div>
                                <span id="scanBadge" class="badge bg-secondary rounded-pill px-3">IDLE</span>
                            </div>

                            <div class="btn-group w-100 mb-2">
                                <button type="button" id="btnStartScan" class="btn btn-outline-danger fw-bold"><i class="bi bi-play-fill"></i> START</button>
                                <button type="button" id="btnStopScan" class="btn btn-outline-secondary fw-bold" disabled><i class="bi bi-stop-fill"></i> STOP</button>
                            </div>

                            <textarea name="rfid_tags" id="rfid_tags" class="form-control rfid-console" rows="5" placeholder="> Siap scan..." required><?= htmlspecialchars($_POST['rfid_tags'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold text-muted">Preview Item</label>
                            <small class="text-muted" style="font-size: 0.7rem;">Otomatis update</small>
                        </div>
                        <div id="previewContainer" class="border rounded bg-white p-0" style="max-height: 150px; overflow-y: auto;">
                            <div class="text-center text-muted py-3 small">Belum ada item di-scan.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Catatan Tambahan (Opsional)" value="<?= htmlspecialchars($_POST['notes'] ?? ''); ?>">
                    </div>

                    <button type="submit" class="btn btn-danger w-100 py-2 fw-bold shadow-sm"><i class="bi bi-printer me-2"></i> PROSES & CETAK SURAT JALAN</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-danger"><i class="bi bi-clock-history me-2"></i>Riwayat Pengeluaran</h6>
                <button class="btn btn-sm btn-light border" onclick="location.reload()" title="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="bg-light text-secondary"><tr><th class="ps-3" style="width:5%;">No</th><th>Waktu</th><th>Perusahaan</th><th>Info Produk</th><th>Tag ID</th><th class="pe-3">User</th></tr></thead>
                        <tbody class="border-top-0">
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-box-seam fs-1 d-block mb-2 opacity-25"></i>Belum ada data barang keluar.</td></tr>
                        <?php else: $no=1; foreach ($logs as $row): ?>
                            <tr>
                                <td class="ps-3 text-center text-muted"><?= $no++; ?></td>
                                <td><div class="fw-bold text-dark"><?= date('H:i', strtotime($row['movement_time'])); ?></div><div class="small text-muted"><?= date('d/m/y', strtotime($row['movement_time'])); ?></div></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['warehouse_name']); ?></span></td>
                                <td><div class="fw-bold text-truncate" style="max-width: 150px;"><?= htmlspecialchars($row['product_name']); ?></div><div class="small text-muted">Ref: <?= htmlspecialchars($row['so_number'] ?: '-'); ?></div></td>
                                <td><span class="font-monospace small bg-white border px-1 rounded text-danger"><?= htmlspecialchars($row['rfid_tag']); ?></span></td>
                                <td class="pe-3 small text-muted"><?= htmlspecialchars($row['created_by'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- 1. INISIALISASI SELECT2 ---
    $('.select2-filter').select2({ theme: "bootstrap-5", width: '100%', placeholder: "Cari Perusahaan..." });
    $('.select2-api').select2({ theme: "bootstrap-5", width: '100%', placeholder: "Ketik PO / Customer...", allowClear: true });
    $('.select2-simple').select2({ theme: "bootstrap-5", width: '100%' });

    // --- 2. LOGIC DROPDOWN API ---
    // Helper string splitter
    function parseStringData(str) {
        if (!str) return [];
        return str.split(/(?:\(\d+\)|\d+\.)/).filter(s => s.trim() !== '').map(s => s.trim().replace(/^[,.\s]+|[,.\s]+$/g, ''));
    }

    // Event change pada Dropdown API
    $('#api_po_selector').on('change', function() {
        const opt = $(this).find(':selected');
        const po  = opt.val();
        
        $('#po_number').val(po);
        
        const companyId = opt.attr('data-company-id');
        if (companyId) {
            $('#warehouse_id').val(companyId).trigger('change');
        }

        // Proses Data Nama & Alamat
        const rawName = opt.attr('data-raw-name') || '';
        const rawAddr = opt.attr('data-raw-addr') || '';
        
        const names = parseStringData(rawName);
        const addrs = parseStringData(rawAddr);

        const custSelect = document.getElementById('customer_name');
        custSelect.innerHTML = '<option value="">-- Pilih Customer --</option>';

        names.forEach((nm, index) => {
            const el = document.createElement('option');
            el.value = nm; 
            el.textContent = nm;
            // Pairing logic: nama index-i dengan alamat index-i
            el.setAttribute('data-addr', addrs[index] ? addrs[index] : (addrs[0] || '-'));
            custSelect.appendChild(el);
        });

        if (names.length > 0) { 
            custSelect.selectedIndex = 1; 
            syncAddress(); 
        } else { 
            document.getElementById('customer_address').value = ''; 
        }
    });

    // --- 3. SCANNER LOGIC (ROBUST MODE) ---
    // Gunakan Polling langsung, tidak blocking UI jika hardware lambat
    const bStart = document.getElementById('btnStartScan');
    const bStop = document.getElementById('btnStopScan');
    const txtArea = document.getElementById('rfid_tags');
    const badge = document.getElementById('scanBadge');
    const previewContainer = document.getElementById('previewContainer');
    let scanTimer = null;
    let previewTimer = null;

    function setScanningState(isScanning) {
        if (isScanning) {
            badge.textContent = 'SCANNING...';
            badge.className = 'badge rounded-pill px-3 status-scanning';
            bStart.disabled = true;
            bStop.disabled = false;
            bStart.classList.replace('btn-outline-danger', 'btn-danger');
        } else {
            badge.textContent = 'IDLE';
            badge.className = 'badge bg-secondary rounded-pill px-3';
            bStart.disabled = false;
            bStop.disabled = true;
            bStart.classList.replace('btn-danger', 'btn-outline-danger');
        }
    }

    function startPolling() {
        if (scanTimer) return;
        setScanningState(true);
        // Focus sekali saja, jangan loop agar bisa input manual
        if(txtArea) txtArea.focus();

        scanTimer = setInterval(() => {
            fetch('get_latest_rfid.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const tags = Array.isArray(data.tags) ? data.tags : (data.epc ? [data.epc] : []);
                    if (!tags.length) return;

                    let lines = txtArea.value.split(/\r?\n/).map(s => s.trim()).filter(s => s !== '');
                    let isAdded = false;

                    tags.forEach(epc => {
                        if (epc && !lines.includes(epc)) {
                            lines.push(epc);
                            isAdded = true;
                        }
                    });

                    if (isAdded) {
                        txtArea.value = lines.join("\n");
                        txtArea.scrollTop = txtArea.scrollHeight;
                        schedulePreviewUpdate();
                    }
                })
                .catch(err => { /* Silent fail agar UI tidak error */ });
        }, 800);
    }

    function stopPolling() {
        if (scanTimer) {
            clearInterval(scanTimer);
            scanTimer = null;
        }
        setScanningState(false);
    }

    // Attach Event Listeners
    bStart.addEventListener('click', () => {
        // Langsung nyalakan UI Polling (agar tidak macet menunggu hardware)
        startPolling();
        
        // Kirim perintah start ke hardware (Fire & Forget)
        fetch('rfid_control.php?action=start').catch(e => console.log('Hardware warning'));
    });

    bStop.addEventListener('click', () => {
        // Matikan Hardware
        fetch('rfid_control.php?action=stop').finally(() => stopPolling());
    });

    // Preview Logic (Debounced)
    if(txtArea) {
        txtArea.addEventListener('input', schedulePreviewUpdate);
    }

    function schedulePreviewUpdate() {
        if (previewTimer) clearTimeout(previewTimer);
        previewTimer = setTimeout(refreshPreview, 500);
    }

    function refreshPreview() {
        if (!previewContainer || !txtArea) return;
        const tags = txtArea.value.split(/\r?\n/).map(s => s.trim()).filter(s => s !== '');

        if (tags.length === 0) {
            previewContainer.innerHTML = '<div class="text-center text-muted py-3 small">Belum ada item di-scan.</div>';
            return;
        }

        fetch('preview_rfid_tags.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tags: tags })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !Array.isArray(data.items)) return;
            let html = '<table class="table table-sm table-bordered mb-0 small"><thead class="table-light text-muted"><tr><th>Tag</th><th>Produk</th><th>Status</th></tr></thead><tbody>';
            data.items.forEach(row => {
                const statusBadge = row.registered ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Not Found</span>';
                html += `<tr><td class="font-monospace">${row.tag}</td><td>${row.registered ? row.product_name : '<span class="text-danger">Tidak terdaftar</span>'}</td><td class="text-center">${statusBadge}</td></tr>`;
            });
            html += '</tbody></table>';
            previewContainer.innerHTML = html;
        });
    }
});

// Fungsi Global Sync Alamat
function syncAddress() {
    const select = document.getElementById('customer_name');
    const addrBox = document.getElementById('customer_address');
    if (select.selectedIndex > 0) {
        const opt = select.options[select.selectedIndex];
        addrBox.value = opt.getAttribute('data-addr') || '';
        addrBox.style.backgroundColor = '#fff3cd';
        setTimeout(() => addrBox.style.backgroundColor = '#e9ecef', 500);
    } else {
        addrBox.value = '';
    }
}
</script>

<?php include 'layout/footer.php'; ?>