<?php
// registrasi_item.php
require_once 'functions.php'; // include config & fungsi API

$pageTitle = 'Registrasi Item (RFID Backend Python)';

// ==========================
// 1. Ambil data dari API
// ==========================
$salesOrders = fetch_sales_orders_from_api(1, 50); // page 1, 50 data

// ==========================
// 2. Handle Form Submit
// ==========================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiProductId = (int)($_POST['api_product_id'] ?? 0);
    $productName  = trim($_POST['product_name'] ?? '');
    $poNumber     = trim($_POST['po_number'] ?? '');
    $soNumber     = trim($_POST['so_number'] ?? '');
    $nameLabel    = trim($_POST['name_label'] ?? '');
    $batchNumber  = trim($_POST['batch_number'] ?? '');
    $pcs          = (int)($_POST['pcs'] ?? 0);
    $rfidRaw      = trim($_POST['rfid_tag'] ?? '');

    if ($apiProductId <= 0 || $productName === '') {
        $error = 'Silakan pilih data dari API (PO/SO/Product) terlebih dahulu.';
    } elseif ($pcs <= 0) {
        $error = 'Pcs harus lebih dari 0.';
    } elseif ($rfidRaw === '') {
        $error = 'RFID Tag tidak boleh kosong (klik Start lalu scan).';
    } else {
        // --- Pisah banyak tag: tiap baris = 1 tag ---
        $tags = preg_split('/\r\n|\r|\n/', $rfidRaw);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags, function($t){ return $t !== ''; });
        $tags = array_values(array_unique($tags)); // buang duplikat di textarea

        if (empty($tags)) {
            $error = 'RFID Tag tidak valid. Pastikan ada minimal 1 tag.';
        } else {
            $newTags = [];

            // cek satu-satu ke DB, hanya simpan yang belum pernah terdaftar
            $cek = $pdo->prepare("SELECT COUNT(*) FROM rfid_registrations WHERE rfid_tag = :tag");

            foreach ($tags as $tag) {
                $cek->execute([':tag' => $tag]);
                $jumlah = (int)$cek->fetchColumn();
                if ($jumlah === 0) {
                    $newTags[] = $tag;
                }
            }

            if (empty($newTags)) {
                $error = 'Semua RFID Tag di daftar ini sudah pernah diregistrasi. Silakan scan tag yang belum digunakan.';
            } else {
                // Simpan ke DB: 1 baris per tag
                $stmt = $pdo->prepare("
                    INSERT INTO rfid_registrations
                        (api_product_id, product_name, po_number, so_number, name_label, batch_number, pcs, rfid_tag)
                    VALUES
                        (:api_product_id, :product_name, :po, :so, :name_label, :batch, :pcs, :rfid)
                ");

                foreach ($newTags as $rfidTag) {
                    $stmt->execute([
                        ':api_product_id' => $apiProductId,
                        ':product_name'   => $productName,
                        ':po'             => $poNumber,
                        ':so'             => $soNumber,
                        ':name_label'     => $nameLabel,
                        ':batch'          => $batchNumber,
                        ':pcs'            => $pcs,
                        ':rfid'           => $rfidTag,
                    ]);
                }

                header('Location: registrasi_item.php?success=1');
                exit;
            }
        }
    }
}

// ==========================
// 3. Ambil data registrasi yang sudah tersimpan
// ==========================
$stmt = $pdo->query("
    SELECT *
    FROM rfid_registrations
    ORDER BY created_at DESC, id DESC
");
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// 4. Tampilkan halaman
// ==========================
include 'layout/header.php';
?>

<div class="row g-3">
    <!-- FORM REGISTRASI -->
    <div class="col-md-5">
        <div class="card card-elevated">
            <div class="card-body">
                <h5 class="mb-3">Form Registrasi Item (RFID)</h5>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success py-2">
                        Registrasi berhasil disimpan.
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2">
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($salesOrders)): ?>
                    <div class="alert alert-warning">
                        Tidak ada data dari API / gagal mengambil data.  
                        Periksa kembali koneksi API di <strong>config.php</strong>.
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <!-- Pilih dari API -->
                    <div class="mb-3">
                        <label class="form-label">Pilih dari API (PO | SO | Product)</label>
                        <select id="apiSelect" class="form-select" <?= empty($salesOrders) ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih dari API --</option>
                            <?php foreach ($salesOrders as $order):
                                $po   = $order['customer_order']['number'] ?? '';
                                $so   = $order['number'] ?? '';
                                $prod = $order['item']['product']['name'] ?? '';
                                $pid  = $order['item']['product']['id'] ?? 0;
                                $qty  = $order['item']['quantity'] ?? 1;
                            ?>
                                <option
                                    value="<?= htmlspecialchars($pid); ?>"
                                    data-po="<?= htmlspecialchars($po); ?>"
                                    data-so="<?= htmlspecialchars($so); ?>"
                                    data-product="<?= htmlspecialchars($prod); ?>"
                                    data-pcs="<?= (int)$qty; ?>"
                                >
                                    <?= htmlspecialchars($po . ' | ' . $so . ' | ' . $prod); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Setelah memilih, PO, SO, Product dan Pcs akan terisi otomatis.
                        </div>
                    </div>

                    <!-- Hidden Product ID -->
                    <input type="hidden" name="api_product_id" id="api_product_id" value="">

                    <!-- Name -->
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name_label" class="form-control"
                               placeholder="Nama pemilik / nama sample / keterangan">
                    </div>

                    <!-- PO & SO -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">PO</label>
                            <input type="text" name="po_number" id="po_number"
                                   class="form-control" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SO</label>
                            <input type="text" name="so_number" id="so_number"
                                   class="form-control" readonly>
                        </div>
                    </div>

                    <!-- Product -->
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" name="product_name" id="product_name"
                               class="form-control" readonly>
                    </div>

                    <!-- No Bets, Pcs, RFID Tag (multi line) -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">No Bets</label>
                            <input type="text" name="batch_number" class="form-control"
                                   placeholder="Batch / Lot">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Pcs</label>
                            <input type="number" name="pcs" id="pcs"
                                   class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                <span>RFID Tag (banyak, 1 baris 1 tag)</span>
                                <span class="small">
                                    <button type="button" id="btnStartScan" class="btn btn-sm btn-success">
                                        Start
                                    </button>
                                    <button type="button" id="btnStopScan" class="btn btn-sm btn-outline-secondary">
                                        Stop
                                    </button>
                                </span>
                            </label>
                            <!-- textarea besar untuk menampung banyak RFID -->
                            <textarea name="rfid_tag" id="rfid_tag"
                                      class="form-control"
                                      rows="6"
                                      placeholder="Klik Start lalu scan beberapa tag. Setiap tag akan muncul di baris baru."
                                      required></textarea>
                            <div class="form-text">
                                Status: <span id="scanStatus">Idle</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" <?= empty($salesOrders) ? 'disabled' : ''; ?>>
                        <i class="bi bi-save"></i> Simpan Registrasi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- LIST REGISTRASI -->
    <div class="col-md-7">
        <div class="card card-elevated">
            <div class="card-body">
                <h5 class="mb-3">Data Registrasi RFID</h5>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Name</th>
                            <th>PO</th>
                            <th>SO</th>
                            <th>Product</th>
                            <th>No Bets</th>
                            <th>Pcs</th>
                            <th>RFID Tag</th>
                            <th>Waktu</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($registrations)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">
                                    Belum ada data registrasi.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($registrations as $row): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['name_label']); ?></td>
                                    <td><?= htmlspecialchars($row['po_number']); ?></td>
                                    <td><?= htmlspecialchars($row['so_number']); ?></td>
                                    <td><?= htmlspecialchars($row['product_name']); ?></td>
                                    <td><?= htmlspecialchars($row['batch_number']); ?></td>
                                    <td><?= (int)$row['pcs']; ?></td>
                                    <td><?= htmlspecialchars($row['rfid_tag']); ?></td>
                                    <td><?= htmlspecialchars($row['created_at']); ?></td>
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
// ==============================
// 1. Isi otomatis dari API ke form
// ==============================
const apiSelect      = document.getElementById('apiSelect');
const apiProductIdEl = document.getElementById('api_product_id');
const poEl           = document.getElementById('po_number');
const soEl           = document.getElementById('so_number');
const productEl      = document.getElementById('product_name');
const pcsEl          = document.getElementById('pcs');

if (apiSelect) {
    apiSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const pid = opt.value || '';
        const po  = opt.getAttribute('data-po') || '';
        const so  = opt.getAttribute('data-so') || '';
        const prd = opt.getAttribute('data-product') || '';
        const pcs = opt.getAttribute('data-pcs') || '';

        apiProductIdEl.value = pid;
        poEl.value           = po;
        soEl.value           = so;
        productEl.value      = prd;
        if (pcs) pcsEl.value = pcs;
    });
}

// ==============================
// 2. Kontrol START/STOP + polling RFID (multi-tag)
// ==============================
const btnStart   = document.getElementById('btnStartScan');
const btnStop    = document.getElementById('btnStopScan');
const rfidInput  = document.getElementById('rfid_tag');
const scanStatus = document.getElementById('scanStatus');

let scanTimer = null;

function updateStatus(text) {
    if (scanStatus) {
        scanStatus.textContent = text;
    }
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

                // Sekarang API mengembalikan data.tags = array EPC
                const tags = Array.isArray(data.tags) ? data.tags : [];
                if (!tags.length) return;

                if (rfidInput) {
                    // Ambil isi sekarang, pecah per baris
                    let lines = rfidInput.value
                        .split(/\r?\n/)
                        .map(s => s.trim())
                        .filter(s => s !== '');

                    // Tambahkan semua EPC yang belum ada di textarea
                    tags.forEach(epc => {
                        if (epc && !lines.includes(epc)) {
                            lines.push(epc);
                        }
                    });

                    rfidInput.value = lines.join("\n");
                }
            })
            .catch(err => console.error('get_latest_rfid error:', err));
    }, 500); // 0.5 detik
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
            .then(() => {
                // Kalau mau setiap start bersihkan list, buka komentar di bawah:
                // if (rfidInput) rfidInput.value = '';
                startPolling();
            })
            .catch(err => console.error('rfid_control start error:', err));
    });
}

if (btnStop) {
    btnStop.addEventListener('click', () => {
        fetch('rfid_control.php?action=stop')
            .then(r => r.json())
            .then(() => {
                stopPolling();
            })
            .catch(err => console.error('rfid_control stop error:', err));
    });
}

// Set tombol awal
if (btnStop) btnStop.disabled = true;
updateStatus('Idle');
</script>

<?php
include 'layout/footer.php';
