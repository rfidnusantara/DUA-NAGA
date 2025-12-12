<?php
// ==========================================
// FILE: barang_keluar.php (REVISI SPLIT DATA)
// ==========================================

require_once 'functions.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUser = $_SESSION['user']['username'] ?? 'System';
$pageTitle   = 'Barang Keluar (Outbound)';

// =========================================================================
// 0. FUNGSI BANTUAN: MEMECAH STRING (1)... (2)...
// =========================================================================
function pecahData($rawString) {
    $rawString = trim($rawString ?? '');
    if (empty($rawString)) return [];

    $hasil = [];

    // 1. Cek Pola "(1) Tekst (2) Teks" atau "1. Teks 2. Teks"
    // Regex ini mencari angka dalam kurung (1) atau angka dengan titik 1.
    if (preg_match('/(?:\(\d+\)|\d+\.)/', $rawString)) {
        // Pisahkan berdasarkan angka tersebut
        $parts = preg_split('/(?:\(\d+\)|\d+\.)/', $rawString);
        foreach ($parts as $p) {
            $bersih = trim($p);
            // Hapus karakter aneh di awal/akhir
            $bersih = trim($bersih, ",.- "); 
            if (!empty($bersih)) {
                $hasil[] = $bersih;
            }
        }
    } 
    // 2. Cek Pola Pemisah Garis Miring " / " (jika tidak ada nomor)
    elseif (strpos($rawString, '/') !== false) {
        $parts = explode('/', $rawString);
        foreach ($parts as $p) {
            $hasil[] = trim($p);
        }
    } 
    // 3. Jika tidak ada pola, kembalikan string asli
    else {
        $hasil[] = $rawString;
    }

    return $hasil; // Mengembalikan Array ['Nama 1', 'Nama 2']
}

// =========================================================================
// 1. AMBIL DATA DARI API EXTERNAL
// =========================================================================
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

$apiResponse = curl_exec($ch);
$apiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$salesOrdersApi = [];
$uniqueCustomers = []; // Array untuk menampung Nama Customer Unik
$uniqueAddresses = []; // Array untuk menampung Alamat Unik

if ($apiHttpCode === 200) {
    $decoded = json_decode($apiResponse, true);
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        $salesOrdersApi = $decoded['data'];
    } elseif (is_array($decoded)) {
        $salesOrdersApi = $decoded;
    }

    // --- LOGIKA PARSING DAN SPLIT DATA ---
    foreach ($salesOrdersApi as $row) {
        // 1. Ambil Nama (Raw)
        $rawName = $row['customer']['name'] ?? '';
        // Pecah Nama jika formatnya (1) A (2) B
        $names = pecahData($rawName);
        foreach ($names as $nm) {
            $uniqueCustomers[$nm] = $nm; // Simpan ke array unik
        }

        // 2. Ambil Alamat (Raw)
        $rawAddr1 = $row['customer']['address'] ?? '';
        $rawAddr2 = $row['delivery_address'] ?? '';

        // Pecah Alamat 1
        $addrs1 = pecahData($rawAddr1);
        foreach ($addrs1 as $ad) {
            $uniqueAddresses[$ad] = $ad;
        }

        // Pecah Alamat 2 (Delivery Address)
        if (!empty($rawAddr2)) {
            $addrs2 = pecahData($rawAddr2);
            foreach ($addrs2 as $ad) {
                // Tandai ini alamat kirim jika berbeda
                if (!isset($uniqueAddresses[$ad])) {
                    $uniqueAddresses[$ad] = $ad;
                }
            }
        }
    }
}

// =========================================================================
// 2. AMBIL DATA GUDANG INTERNAL
// =========================================================================
$stmt = $pdo->query("SELECT id, name, code FROM warehouses ORDER BY name ASC");
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$successMsg = '';

// =========================================================================
// 3. HANDLE FORM SUBMIT
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouseId     = (int)($_POST['warehouse_id'] ?? 0);
    $rfidRaw         = trim($_POST['rfid_tags'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');
    
    // Ambil data dari dropdown/input
    $customerName    = trim($_POST['customer_name'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $poNumber        = trim($_POST['po_number'] ?? '');

    if ($warehouseId <= 0) {
        $error = 'Silakan pilih Gudang asal barang.';
    } elseif ($customerName === '' || $customerAddress === '') {
        $error = 'Nama dan Alamat Customer wajib dipilih.';
    } elseif ($rfidRaw === '') {
        $error = 'RFID Tag kosong. Silakan scan barang terlebih dahulu.';
    } else {
        $tags = preg_split('/\r\n|\r|\n/', $rfidRaw);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags, fn($t) => $t !== '');

        if (empty($tags)) {
            $error = 'Format RFID tidak valid.';
        } else {
            // Cek Duplikasi
            $counts = array_count_values($tags);
            $dupInForm = [];
            foreach ($counts as $tag => $cnt) {
                if ($cnt > 1) $dupInForm[] = $tag;
            }

            if (!empty($dupInForm)) {
                $error = 'Duplikasi tag di input: ' . implode(', ', $dupInForm);
            } else {
                $tags = array_values(array_unique($tags));

                $getReg = $pdo->prepare("SELECT * FROM rfid_registrations WHERE rfid_tag = :tag ORDER BY id DESC LIMIT 1");
                $movementInsert = $pdo->prepare("INSERT INTO stock_movements (rfid_tag, registration_id, warehouse_id, movement_type, movement_time, created_by, notes) VALUES (:rfid_tag, :registration_id, :warehouse_id, 'OUT', :movement_time, :created_by, :notes)");
                $updateInactive = $pdo->prepare("UPDATE rfid_registrations SET is_active = 0 WHERE id = :id");

                $notRegistered = [];
                $insertedTags  = [];
                $now = date('Y-m-d H:i:s');

                $pdo->beginTransaction();
                try {
                    foreach ($tags as $tag) {
                        $getReg->execute([':tag' => $tag]);
                        $reg = $getReg->fetch(PDO::FETCH_ASSOC);
                        if (!$reg) { $notRegistered[] = $tag; continue; }

                        $movementInsert->execute([
                            ':rfid_tag' => $tag, ':registration_id' => $reg['id'], ':warehouse_id' => $warehouseId,
                            ':movement_time' => $now, ':created_by' => $currentUser, ':notes' => $notes
                        ]);
                        $updateInactive->execute([':id' => $reg['id']]);
                        $insertedTags[] = $tag;
                    }

                    if (empty($insertedTags)) throw new Exception('Tidak ada tag valid.');

                    $tanggalSj = date('Y-m-d');
                    $insertSj = $pdo->prepare("INSERT INTO surat_jalan (no_sj, tanggal_sj, customer_name, customer_address, po_number, warehouse_id, notes, created_by) VALUES ('', :tanggal_sj, :customer_name, :customer_address, :po_number, :warehouse_id, :notes, :created_by)");
                    $insertSj->execute([
                        ':tanggal_sj' => $tanggalSj, ':customer_name' => $customerName,
                        ':customer_address'=> $customerAddress, ':po_number' => $poNumber,
                        ':warehouse_id' => $warehouseId, ':notes' => $notes, ':created_by' => $currentUser
                    ]);

                    $sjId = (int)$pdo->lastInsertId();
                    $noSj = 'SJ/' . date('ym') . '/' . str_pad($sjId, 4, '0', STR_PAD_LEFT);
                    $pdo->prepare("UPDATE surat_jalan SET no_sj = :no_sj WHERE id = :id")->execute([':no_sj' => $noSj, ':id' => $sjId]);

                    $detailInsert = $pdo->prepare("INSERT INTO surat_jalan_items (surat_jalan_id, rfid_tag, product_name, batch_number, qty, unit) VALUES (:sj_id, :rfid_tag, :product_name, :batch_number, :qty, :unit)");
                    foreach ($insertedTags as $tag) {
                        $getReg->execute([':tag' => $tag]);
                        $reg = $getReg->fetch(PDO::FETCH_ASSOC);
                        if (!$reg) continue;
                        $detailInsert->execute([
                            ':sj_id' => $sjId, ':rfid_tag' => $tag, 
                            ':product_name' => $reg['product_name'] ?? 'Unknown',
                            ':batch_number' => $reg['batch_number'] ?? '-',
                            ':qty' => (int)($reg['pcs'] ?? 1), ':unit' => 'PCS'
                        ]);
                    }

                    $pdo->commit();
                    if (!empty($notRegistered)) $_SESSION['flash_error'] = 'Peringatan: Tag tidak terdaftar dilewati: ' . implode(', ', $notRegistered);
                    header('Location: surat_jalan_cetak.php?id=' . $sjId);
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Gagal: ' . $e->getMessage();
                }
            }
        }
    }
}

// Log Logic
$logStmt = $pdo->query("SELECT sm.*, w.name AS warehouse_name, rr.product_name, rr.po_number, rr.so_number FROM stock_movements sm LEFT JOIN warehouses w ON sm.warehouse_id = w.id LEFT JOIN rfid_registrations rr ON sm.registration_id = rr.id WHERE sm.movement_type = 'OUT' ORDER BY sm.movement_time DESC, sm.id DESC LIMIT 10");
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<style>
    .rfid-console { background-color: #212529; color: #ffc107; font-family: monospace; font-size: 0.95rem; border: 2px solid #343a40; }
    .scanner-box { background: #fff5f5; border: 1px dashed #feb2b2; border-radius: 8px; padding: 15px; }
    .status-scanning { animation: pulse-red 2s infinite; background-color: #dc3545 !important; color: white !important; }
    @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
    .api-card { background-color: #e3f2fd; border: 1px solid #90caf9; }
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

                <form method="post" autocomplete="off">
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">1. Referensi Data (API)</label>
                        <div class="card p-3 mb-2 api-card">
                            <label class="small text-primary fw-bold mb-1"><i class="bi bi-cloud-download me-1"></i> Pilih SO / PO (Untuk Filter):</label>
                            
                            <select id="api_selector" class="form-select form-select-sm mb-2" onchange="fillFormData(this)">
                                <option value="">-- Lihat Referensi API --</option>
                                <?php if(empty($salesOrdersApi)): ?>
                                    <option disabled>Data API Kosong</option>
                                <?php else: ?>
                                    <?php foreach ($salesOrdersApi as $order): ?>
                                        <?php 
                                            // Kita tampilkan data mentah disini agar user tahu ini order yg mana
                                            $soNum = $order['number'] ?? '-';
                                            $poNum = $order['customer_order']['number'] ?? ($order['customer_order_id'] ?? '-');
                                            $rawName = $order['customer']['name'] ?? 'No Name';
                                            
                                            // Potong nama jika terlalu panjang untuk dropdown
                                            $displayName = (strlen($rawName) > 30) ? substr($rawName, 0, 30) . '...' : $rawName;

                                            $label = "SO: $soNum | PO: $poNum | $displayName";
                                        ?>
                                        <option 
                                            value="<?= htmlspecialchars($poNum); ?>" 
                                            data-po="<?= htmlspecialchars($poNum); ?>"
                                        >
                                            <?= htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text small text-muted">
                                *Pilih data di atas untuk mengisi Nomor PO otomatis. <br>
                                *Nama & Alamat harus dipilih manual dari opsi di bawah (sudah dipisah).
                            </div>
                        </div>

                        <div class="card bg-light border-0 p-3">
                            <div class="mb-2">
                                <label class="small text-muted fw-bold">Nama Customer (Pilih Satu)</label>
                                <select name="customer_name" id="customer_name" class="form-select form-select-sm fw-bold" required>
                                    <option value="">-- Pilih Nama (Hasil Split) --</option>
                                    <?php foreach ($uniqueCustomers as $name): ?>
                                        <option value="<?= htmlspecialchars($name); ?>"
                                            <?= (isset($_POST['customer_name']) && $_POST['customer_name'] === $name) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="small text-muted fw-bold">Alamat Lengkap (Pilih Satu)</label>
                                <select name="customer_address" id="customer_address" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih Alamat (Hasil Split) --</option>
                                    <?php foreach ($uniqueAddresses as $addr): ?>
                                        <option value="<?= htmlspecialchars($addr); ?>"
                                            <?= (isset($_POST['customer_address']) && $_POST['customer_address'] === $addr) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars(substr($addr, 0, 80) . (strlen($addr)>80 ? '...' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="small text-muted">No. PO Customer</label>
                                    <input type="text" name="po_number" id="po_number"
                                           class="form-control form-control-sm"
                                           placeholder="Otomatis..."
                                           value="<?= htmlspecialchars($_POST['po_number'] ?? ''); ?>"
                                           readonly>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Gudang Asal</label>
                                    <select name="warehouse_id" class="form-select form-select-sm" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach ($warehouses as $g): ?>
                                            <option value="<?= (int)$g['id']; ?>" <?= (isset($_POST['warehouse_id']) && $_POST['warehouse_id'] == $g['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($g['name']); ?></option>
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
                                <button type="button" id="btnStartScan" class="btn btn-outline-danger fw-bold">START</button>
                                <button type="button" id="btnStopScan" class="btn btn-outline-secondary fw-bold" disabled>STOP</button>
                            </div>
                            <textarea name="rfid_tags" id="rfid_tags" class="form-control rfid-console" rows="5" placeholder="> Siap scan..." required><?= htmlspecialchars($_POST['rfid_tags'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div id="previewContainer" class="border rounded bg-white p-0" style="max-height: 150px; overflow-y: auto;">
                            <div class="text-center text-muted py-3 small">Belum ada item di-scan.</div>
                        </div>
                    </div>
                    <div class="mb-3"><input type="text" name="notes" class="form-control form-control-sm" placeholder="Catatan Tambahan" value="<?= htmlspecialchars($_POST['notes'] ?? ''); ?>"></div>
                    <button type="submit" class="btn btn-danger w-100 py-2 fw-bold shadow-sm"><i class="bi bi-printer me-2"></i> PROSES & CETAK SURAT JALAN</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-danger"><i class="bi bi-clock-history me-2"></i>Riwayat Pengeluaran</h6>
                <button class="btn btn-sm btn-light border" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="bg-light text-secondary"><tr><th class="ps-3">No</th><th>Waktu</th><th>Gudang</th><th>Info Produk</th><th>Tag ID</th><th class="pe-3">User</th></tr></thead>
                        <tbody class="border-top-0">
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada data.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($logs as $row): ?>
                                <tr>
                                    <td class="ps-3"><?= $no++; ?></td>
                                    <td><?= date('H:i d/m', strtotime($row['movement_time'])); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['warehouse_name']); ?></span></td>
                                    <td><?= htmlspecialchars($row['product_name']); ?><br><small class="text-muted"><?= $row['so_number']; ?></small></td>
                                    <td><span class="font-monospace small text-danger"><?= htmlspecialchars($row['rfid_tag']); ?></span></td>
                                    <td><?= htmlspecialchars($row['created_by']); ?></td>
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
// JS LOGIC UNTUK MENGISI PO (NAMA & ALAMAT DIPILIH MANUAL KARENA HASIL SPLIT)
function fillFormData(masterSelect) {
    const selectedOption = masterSelect.options[masterSelect.selectedIndex];
    const poInput = document.getElementById('po_number');

    if (!selectedOption.value) {
        poInput.value = "";
        return;
    }

    // Ambil Data PO dari dropdown Master
    const targetPo = selectedOption.getAttribute('data-po');
    poInput.value = targetPo;

    // Highlight input PO agar user sadar sudah terisi
    poInput.style.backgroundColor = '#e8f5e9';
    setTimeout(() => poInput.style.backgroundColor = '', 600);
}

// SCRIPT SCANNER DEFAULT
const btnStart=document.getElementById('btnStartScan');const btnStop=document.getElementById('btnStopScan');const rfidArea=document.getElementById('rfid_tags');const scanBadge=document.getElementById('scanBadge');const previewContainer=document.getElementById('previewContainer');let scanTimer=null;let previewTimer=null;
function setScanningState(a){if(a){scanBadge.textContent='SCANNING...';scanBadge.className='badge rounded-pill px-3 status-scanning';btnStart.disabled=!0;btnStop.disabled=!1;btnStart.classList.replace('btn-outline-danger','btn-danger')}else{scanBadge.textContent='IDLE';scanBadge.className='badge bg-secondary rounded-pill px-3';btnStart.disabled=!1;btnStop.disabled=!0;btnStart.classList.replace('btn-danger','btn-outline-danger')}}
function getCurrentTags(){if(!rfidArea)return[];return rfidArea.value.split(/\r?\n/).map(s=>s.trim()).filter(s=>s!=='')}
function refreshPreview(){if(!previewContainer)return;const a=getCurrentTags();if(a.length===0){previewContainer.innerHTML='<div class="text-center text-muted py-3 small">Belum ada item di-scan.</div>';return}fetch('preview_rfid_tags.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({tags:a})}).then(r=>r.json()).then(d=>{if(!d.success||!Array.isArray(d.items)){previewContainer.innerHTML='<div class="p-2 text-danger small">Gagal preview.</div>';return}let h='<table class="table table-sm table-bordered mb-0 small"><thead class="table-light text-muted"><tr><th>Tag</th><th>Produk</th></tr></thead><tbody>';d.items.forEach(r=>{h+=`<tr><td class="font-monospace">${r.tag}</td><td>${r.registered?r.product_name:'<span class="text-danger">N/A</span>'}</td></tr>`});h+='</tbody></table>';previewContainer.innerHTML=h})}
function schedulePreviewUpdate(){if(previewTimer)clearTimeout(previewTimer);previewTimer=setTimeout(refreshPreview,500)}
function startPolling(){if(scanTimer)return;setScanningState(!0);scanTimer=setInterval(()=>{fetch('get_latest_rfid.php').then(r=>r.json()).then(d=>{if(!d.success)return;const t=Array.isArray(d.tags)?d.tags:(d.epc?[d.epc]:[]);if(!t.length)return;let l=getCurrentTags();let add=!1;t.forEach(e=>{if(e&&!l.includes(e)){l.push(e);add=!0}});if(add){rfidArea.value=l.join("\n");rfidArea.scrollTop=rfidArea.scrollHeight;schedulePreviewUpdate()}})},500)}
function stopPolling(){if(scanTimer){clearInterval(scanTimer);scanTimer=null}setScanningState(!1)}
btnStart.addEventListener('click',()=>{fetch('rfid_control.php?action=start').then(()=>startPolling()).catch(()=>startPolling())});btnStop.addEventListener('click',()=>{fetch('rfid_control.php?action=stop').then(()=>stopPolling())});rfidArea.addEventListener('input',schedulePreviewUpdate);setScanningState(!1);schedulePreviewUpdate();
</script>

<?php include 'layout/footer.php'; ?>