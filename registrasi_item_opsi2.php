<?php
// registrasi_item.php
require_once 'functions.php'; // sudah termasuk config & koneksi PDO

$pageTitle = 'Registrasi Item';

// ==========================
// 0. Filter perusahaan (via GET)
// ==========================
$selectedCompany = trim($_GET['company'] ?? '');

// ==========================
// 1. Ambil data dari API
// ==========================
$salesOrdersAll = fetch_sales_orders_from_api(1, 50); // page 1, 50 data

// Jika fungsi API mengembalikan ['data' => [...]]
if (is_array($salesOrdersAll) && isset($salesOrdersAll['data']) && is_array($salesOrdersAll['data'])) {
    $salesOrdersAll = $salesOrdersAll['data'];
}
if (!is_array($salesOrdersAll)) {
    $salesOrdersAll = [];
}

// Kumpulkan daftar perusahaan
$companyOptions = [];
foreach ($salesOrdersAll as $order) {
    $companyName = $order['company']['name'] ?? '';
    if ($companyName !== '') {
        $companyOptions[$companyName] = $companyName; // unique
    }
}

// Terapkan filter perusahaan
if ($selectedCompany !== '') {
    $salesOrders = array_values(array_filter($salesOrdersAll, function ($order) use ($selectedCompany) {
        $companyName = $order['company']['name'] ?? '';
        return $companyName === $selectedCompany;
    }));
} else {
    $salesOrders = $salesOrdersAll;
}

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
        // Pisah tag per baris
        $tags = preg_split('/\r\n|\r|\n/', $rfidRaw);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags, fn($t) => $t !== '');

        if (empty($tags)) {
            $error = 'RFID Tag tidak valid. Pastikan ada minimal 1 tag.';
        } else {
            // 1) Cek double di FORM
            $counts    = array_count_values($tags);
            $dupInForm = [];
            foreach ($counts as $tag => $cnt) {
                if ($cnt > 1) {
                    $dupInForm[] = $tag;
                }
            }

            if (!empty($dupInForm)) {
                $error = 'Terdapat RFID Tag double di form (tidak disimpan): ' . implode(', ', $dupInForm);
            } else {
                // 2) Cek double di DATABASE
                $tags         = array_values(array_unique($tags));
                $existingTags = [];
                $newTags      = [];

                $cek = $pdo->prepare("SELECT COUNT(*) FROM rfid_registrations WHERE rfid_tag = :tag");

                foreach ($tags as $tag) {
                    $cek->execute([':tag' => $tag]);
                    $jumlah = (int)$cek->fetchColumn();
                    if ($jumlah > 0) {
                        $existingTags[] = $tag;
                    } else {
                        $newTags[] = $tag;
                    }
                }

                if (!empty($existingTags)) {
                    $error = 'RFID Tag berikut sudah terdaftar (double, tidak disimpan): ' . implode(', ', $existingTags);
                } elseif (empty($newTags)) {
                    $error = 'Tidak ada RFID Tag baru untuk disimpan.';
                } else {
                    // 3) Simpan ke DB
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
}

// ==========================
// 3. Ambil data registrasi (10 terakhir)
// ==========================
$stmt = $pdo->query("
    SELECT *
    FROM rfid_registrations
    ORDER BY created_at DESC, id DESC
    LIMIT 10
");
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// 4. Tampilkan halaman
// ==========================
include 'layout/header.php';
?>

<style>
    .rfid-console {
        background-color: #f8f9fa;
        color: #2c3e50;
        font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
        font-size: 0.9rem;
        border: 1px solid #ced4da;
        letter-spacing: 0.5px;
    }
    .rfid-console:focus {
        background-color: #fff;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    }
    @keyframes pulse-green {
        0% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
        70% { box-shadow: 0 0 0 6px rgba(25, 135, 84, 0); }
        100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
    }
    .status-scanning {
        background-color: #198754 !important;
        animation: pulse-green 1.5s infinite;
    }
    .status-idle {
        background-color: #6c757d !important;
    }
    .scan-controls {
        background: #eef2f6;
        border-radius: 10px;
        padding: 15px;
        border: 1px dashed #cbd5e1;
    }
</style>

<div class="row g-4">
    <!-- FORM REGISTRASI -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi bi-pencil-square me-2"></i>Form Registrasi Item
                </h6>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div>Data berhasil disimpan!</div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (empty($salesOrdersAll)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-wifi-off me-2"></i> Gagal terhubung ke API / Data kosong.
                    </div>
                <?php endif; ?>

                <!-- FILTER PERUSAHAAN -->
                <form method="get" class="mb-3">
                    <label class="form-label small text-muted fw-bold text-uppercase">Filter Perusahaan</label>
                    <select name="company" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Semua Perusahaan --</option>
                        <?php foreach ($companyOptions as $companyName): ?>
                            <option value="<?= htmlspecialchars($companyName); ?>"
                                <?= ($selectedCompany === $companyName) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($companyName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small">
                        Pilih perusahaan terlebih dahulu untuk membatasi daftar PO / SO di bawah.
                    </div>
                </form>

                <!-- FORM REGISTRASI (POST) -->
                <form method="post" autocomplete="off">
                    <!-- Sumber Data API -->
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Sumber Data (API)</label>

                        <!-- Kolom search PO/SO/Product -->
                        <input type="text"
                               id="apiSearch"
                               class="form-control form-control-sm mb-2"
                               placeholder="Ketik PO / SO / Product untuk filter...">

                        <select id="apiSelect" class="form-select" <?= empty($salesOrders) ? 'disabled' : ''; ?>>
                            <option value="">-- Pilih PO / SO / Product --</option>
                            <?php foreach ($salesOrders as $order):
                                $po   = $order['customer_order']['number'] ?? '';
                                $so   = $order['number'] ?? '';
                                $prod = $order['item']['product']['name'] ?? '';
                                $pid  = $order['item']['product']['id'] ?? 0;
                                $qty  = $order['item']['quantity'] ?? 1;

                                // nama customer (bisa gabungan (1)...(2)...)
                                $custName = $order['customer']['name'] ?? '';

                                // batches
                                $batchNumbers = [];
                                if (!empty($order['batches']) && is_array($order['batches'])) {
                                    foreach ($order['batches'] as $b) {
                                        if (!empty($b['number'])) {
                                            $batchNumbers[] = $b['number'];
                                        }
                                    }
                                }
                                $batchVal  = implode(', ', $batchNumbers);
                                $batchList = implode('||', $batchNumbers);
                            ?>
                                <option
                                    value="<?= htmlspecialchars($pid); ?>"
                                    data-po="<?= htmlspecialchars($po); ?>"
                                    data-so="<?= htmlspecialchars($so); ?>"
                                    data-product="<?= htmlspecialchars($prod); ?>"
                                    data-pcs="<?= (int)$qty; ?>"
                                    data-customer="<?= htmlspecialchars($custName); ?>"
                                    data-batch="<?= htmlspecialchars($batchVal); ?>"
                                    data-batch-list="<?= htmlspecialchars($batchList); ?>"
                                >
                                    [PO: <?= htmlspecialchars($po); ?>] - <?= htmlspecialchars($prod); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Pilih dari daftar atau ketik di kotak atas untuk menyaring data.</div>
                    </div>

                    <!-- Hidden Product ID -->
                    <input type="hidden" name="api_product_id" id="api_product_id" value="">

                    <!-- PO & SO -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" name="po_number" id="po_number" class="form-control bg-light" placeholder="PO" readonly>
                                <label>PO Number</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" name="so_number" id="so_number" class="form-control bg-light" placeholder="SO" readonly>
                                <label>SO Number</label>
                            </div>
                        </div>
                    </div>

                    <!-- Product -->
                    <div class="form-floating mb-3">
                        <input type="text" name="product_name" id="product_name" class="form-control bg-light" placeholder="Product" readonly>
                        <label>Product Name</label>
                    </div>

                    <!-- Detail Item -->
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Detail Item</label>

                        <!-- Nama Pemilik / Customer + pilihan kalau ada 2 nama -->
                        <div class="form-floating mb-2">
                            <input type="text" name="name_label" id="name_label" class="form-control" placeholder="Customer">
                            <label>Nama Pemilik / Customer</label>

                            <div id="name_choice_wrapper" class="mt-2" style="display:none;">
                                <select id="name_choice" class="form-select form-select-sm">
                                    <!-- opsi via JS -->
                                </select>
                                <div class="form-text small">
                                    Pilih salah satu nama customer, otomatis mengisi kolom di atas.
                                </div>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-floating">
                                    <input type="text" name="batch_number" id="batch_number" class="form-control" placeholder="Batch">
                                    <label>No. Batch / Lot</label>

                                    <div id="batch_choice_wrapper" class="mt-2" style="display:none;">
                                        <select id="batch_choice" class="form-select form-select-sm">
                                            <!-- opsi via JS -->
                                        </select>
                                        <div class="form-text small">
                                            Pilih No. Batch / Lot, otomatis mengisi kolom di atas.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-floating">
                                    <input type="number" name="pcs" id="pcs" class="form-control" min="1" value="1" required>
                                    <label>Qty (Pcs)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RFID Scanner -->
                    <div class="scan-controls mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="fw-bold text-dark"><i class="bi bi-qr-code-scan me-1"></i> RFID Scanner</label>
                            <span id="scanBadge" class="badge rounded-pill status-idle px-3 py-2">
                                <i class="bi bi-circle-fill small me-1"></i> <span id="scanStatusText">Idle</span>
                            </span>
                        </div>

                        <div class="btn-group w-100 mb-2" role="group">
                            <button type="button" id="btnStartScan" class="btn btn-success">
                                <i class="bi bi-play-fill"></i> Start Scan
                            </button>
                            <button type="button" id="btnStopScan" class="btn btn-secondary">
                                <i class="bi bi-stop-fill"></i> Stop
                            </button>
                        </div>

                        <textarea name="rfid_tag" id="rfid_tag"
                                  class="form-control rfid-console"
                                  rows="6"
                                  placeholder="Menunggu scan...
Tag akan muncul di sini (1 baris per tag)."
                                  required></textarea>
                        <div class="form-text text-end fst-italic small mt-1">
                            *Pastikan reader menyala
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm" <?= empty($salesOrders) ? 'disabled' : ''; ?>>
                        <i class="bi bi-save2 me-2"></i> Simpan Registrasi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIWAYAT REGISTRASI -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi bi-list-check me-2"></i>Riwayat Registrasi
                </h6>
                <button class="btn btn-sm btn-outline-light text-muted border-0" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-3">No</th>
                            <th>Info Item</th>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th>RFID Tag</th>
                            <th class="pe-3">Waktu</th>
                        </tr>
                        </thead>
                        <tbody class="border-top-0">
                        <?php if (empty($registrations)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                    Belum ada data registrasi.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($registrations as $row): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-muted"><?= $no++; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['name_label'] ?: '-'); ?></div>
                                        <div class="small text-muted">PO: <?= htmlspecialchars($row['po_number']); ?></div>
                                    </td>
                                    <td>
                                        <span class="d-block text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($row['product_name']); ?>">
                                            <?= htmlspecialchars($row['product_name']); ?>
                                        </span>
                                        <small class="text-muted">Batch: <?= htmlspecialchars($row['batch_number']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary rounded-pill"><?= (int)$row['pcs']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-normal font-monospace">
                                            <?= htmlspecialchars($row['rfid_tag']); ?>
                                        </span>
                                    </td>
                                    <td class="pe-3 small text-muted">
                                        <?= date('d/m/Y', strtotime($row['created_at'])); ?><br>
                                        <?= date('H:i', strtotime($row['created_at'])); ?>
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
// ==============================
// Helper: parsing nama customer
// Format: "(1) NAMA1\r\n(2) NAMA2" -> ["NAMA1","NAMA2"]
// ==============================
function parseCustomerNames(raw) {
    raw = (raw || '').trim();
    if (!raw) return [];

    const names = [];
    const regex = /\(\d+\)\s*([^()]+)/g;
    let m;

    while ((m = regex.exec(raw)) !== null) {
        const nm = (m[1] || '').trim();
        if (nm) names.push(nm);
    }

    if (names.length === 0) {
        names.push(raw);
    }

    return names;
}

// ==============================
// 1. Isi otomatis dari API ke form
// ==============================
const apiSelect          = document.getElementById('apiSelect');
const apiProductIdEl     = document.getElementById('api_product_id');
const poEl               = document.getElementById('po_number');
const soEl               = document.getElementById('so_number');
const productEl          = document.getElementById('product_name');
const pcsEl              = document.getElementById('pcs');
const nameLabelEl        = document.getElementById('name_label');
const batchEl            = document.getElementById('batch_number');
const nameChoiceWrapEl   = document.getElementById('name_choice_wrapper');
const nameChoiceEl       = document.getElementById('name_choice');
const batchChoiceWrapEl  = document.getElementById('batch_choice_wrapper');
const batchChoiceEl      = document.getElementById('batch_choice');

if (apiSelect) {
    apiSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (!opt) return;

        const pid          = opt.value || '';
        const po           = opt.getAttribute('data-po') || '';
        const so           = opt.getAttribute('data-so') || '';
        const prd          = opt.getAttribute('data-product') || '';
        const pcs          = opt.getAttribute('data-pcs') || '';
        const cust         = opt.getAttribute('data-customer') || '';
        const batch        = opt.getAttribute('data-batch') || '';
        const batchListRaw = opt.getAttribute('data-batch-list') || '';

        apiProductIdEl.value = pid;
        poEl.value           = po;
        soEl.value           = so;
        productEl.value      = prd;
        if (pcs)   pcsEl.value   = pcs;
        if (batch) batchEl.value = batch;

        // ---------- Customer (bisa >1 nama) ----------
        const candidates = parseCustomerNames(cust);

        if (candidates.length <= 1) {
            if (nameChoiceWrapEl) nameChoiceWrapEl.style.display = 'none';
            if (nameLabelEl) nameLabelEl.value = candidates[0] || '';
        } else {
            if (nameChoiceWrapEl && nameChoiceEl) {
                nameChoiceWrapEl.style.display = 'block';
                nameChoiceEl.innerHTML = '';
                candidates.forEach((nm) => {
                    const optEl = document.createElement('option');
                    optEl.value = nm;
                    optEl.textContent = nm;
                    nameChoiceEl.appendChild(optEl);
                });
                nameChoiceEl.selectedIndex = 0;
                if (nameLabelEl) nameLabelEl.value = candidates[0];
            }
        }

        // ---------- Batch (No. Batch / Lot) ----------
        let batchCandidates = [];
        if (batchListRaw) {
            batchCandidates = batchListRaw.split('||').map(s => s.trim()).filter(s => s !== '');
        }

        if (batchCandidates.length <= 1) {
            if (batchChoiceWrapEl) batchChoiceWrapEl.style.display = 'none';
            if (batchEl) batchEl.value = batchCandidates[0] || batch || '';
        } else {
            if (batchChoiceWrapEl && batchChoiceEl) {
                batchChoiceWrapEl.style.display = 'block';
                batchChoiceEl.innerHTML = '';
                batchCandidates.forEach((bn) => {
                    const optEl = document.createElement('option');
                    optEl.value = bn;
                    optEl.textContent = bn;
                    batchChoiceEl.appendChild(optEl);
                });
                batchChoiceEl.selectedIndex = 0;
                if (batchEl) batchEl.value = batchCandidates[0];
            }
        }
    });
}

// Dropdown customer & batch
if (nameChoiceEl && nameLabelEl) {
    nameChoiceEl.addEventListener('change', function () {
        nameLabelEl.value = this.value || '';
    });
}
if (batchChoiceEl && batchEl) {
    batchChoiceEl.addEventListener('change', function () {
        batchEl.value = this.value || '';
    });
}

// ==============================
// 1b. FILTER DROPDOWN API DENGAN INPUT KETIK (VERSI BARU)
// ==============================
const apiSearch = document.getElementById('apiSearch');
let allApiOptions = [];

if (apiSelect) {
    for (let i = 0; i < apiSelect.options.length; i++) {
        const opt = apiSelect.options[i];
        if (!opt.value) continue; // skip placeholder

        allApiOptions.push({
            value:      opt.value,
            text:       opt.textContent || '',
            po:         opt.getAttribute('data-po') || '',
            so:         opt.getAttribute('data-so') || '',
            product:    opt.getAttribute('data-product') || '',
            pcs:        opt.getAttribute('data-pcs') || '',
            customer:   opt.getAttribute('data-customer') || '',
            batch:      opt.getAttribute('data-batch') || '',
            batchList:  opt.getAttribute('data-batch-list') || ''
        });
    }
}

// Render ulang isi <select> berdasarkan keyword
function renderApiOptions(keyword) {
    if (!apiSelect) return;

    keyword = (keyword || '').toLowerCase();
    apiSelect.innerHTML = '';

    // placeholder
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '-- Pilih PO / SO / Product --';
    apiSelect.appendChild(placeholder);

    allApiOptions.forEach((o) => {
        const haystack = (o.text + ' ' + o.po + ' ' + o.so + ' ' + o.product).toLowerCase();
        if (!keyword || haystack.includes(keyword)) {
            const opt = document.createElement('option');
            opt.value = o.value;
            opt.textContent = o.text;
            opt.setAttribute('data-po',          o.po);
            opt.setAttribute('data-so',          o.so);
            opt.setAttribute('data-product',     o.product);
            opt.setAttribute('data-pcs',         o.pcs);
            opt.setAttribute('data-customer',    o.customer);
            opt.setAttribute('data-batch',       o.batch);
            opt.setAttribute('data-batch-list',  o.batchList);
            apiSelect.appendChild(opt);
        }
    });
}

// render awal (semua data)
renderApiOptions('');

if (apiSearch) {
    apiSearch.addEventListener('input', function () {
        renderApiOptions(this.value);
        apiSelect.selectedIndex = 0;

        // reset field auto-fill
        if (apiProductIdEl) apiProductIdEl.value = '';
        if (poEl) poEl.value = '';
        if (soEl) soEl.value = '';
        if (productEl) productEl.value = '';
        if (nameLabelEl) nameLabelEl.value = '';
        if (batchEl) batchEl.value = '';
        if (nameChoiceWrapEl) nameChoiceWrapEl.style.display = 'none';
        if (batchChoiceWrapEl) batchChoiceWrapEl.style.display = 'none';
    });
}

// ==============================
// 2. Kontrol START/STOP + polling RFID
// ==============================
const btnStart      = document.getElementById('btnStartScan');
const btnStop       = document.getElementById('btnStopScan');
const rfidInput     = document.getElementById('rfid_tag');
const scanBadge     = document.getElementById('scanBadge');
const scanStatusTxt = document.getElementById('scanStatusText');

let scanTimer = null;

function setVisualStatus(isScanning) {
    if (!scanBadge || !scanStatusTxt || !btnStart || !btnStop) return;

    if (isScanning) {
        scanBadge.classList.remove('status-idle');
        scanBadge.classList.add('status-scanning');
        scanStatusTxt.textContent = 'Scanning...';
        btnStart.disabled = true;
        btnStop.disabled  = false;
        btnStop.classList.remove('btn-secondary');
        btnStop.classList.add('btn-danger');
    } else {
        scanBadge.classList.remove('status-scanning');
        scanBadge.classList.add('status-idle');
        scanStatusTxt.textContent = 'Idle';
        btnStart.disabled = false;
        btnStop.disabled  = true;
        btnStop.classList.remove('btn-danger');
        btnStop.classList.add('btn-secondary');
    }
}

function startPolling() {
    if (scanTimer) return;
    setVisualStatus(true);

    scanTimer = setInterval(() => {
        fetch('get_latest_rfid.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                const tags = Array.isArray(data.tags)
                           ? data.tags
                           : (data.epc ? [data.epc] : []);

                if (!tags.length) return;

                if (rfidInput) {
                    let lines = rfidInput.value
                        .split(/\r?\n/)
                        .map(s => s.trim())
                        .filter(s => s !== '');

                    let added = false;
                    tags.forEach(epc => {
                        if (epc && !lines.includes(epc)) {
                            lines.push(epc);
                            added = true;
                        }
                    });

                    if (added) {
                        rfidInput.value = lines.join("\n");
                        rfidInput.scrollTop = rfidInput.scrollHeight;
                    }
                }
            })
            .catch(err => console.error('get_latest_rfid error:', err));
    }, 500);
}

function stopPolling() {
    if (scanTimer) {
        clearInterval(scanTimer);
        scanTimer = null;
    }
    setVisualStatus(false);
}

if (btnStart) {
    btnStart.addEventListener('click', () => {
        fetch('rfid_control.php?action=start')
            .then(r => r.json())
            .then(res => {
                startPolling();
            })
            .catch(err => {
                console.error('rfid_control start error:', err);
                alert('Gagal memulai scanner hardware.');
            });
    });
}

if (btnStop) {
    btnStop.addEventListener('click', () => {
        fetch('rfid_control.php?action=stop')
            .then(r => r.json())
            .then(res => {
                stopPolling();
            })
            .catch(err => console.error('rfid_control stop error:', err));
    });
}

setVisualStatus(false);
</script>

<?php
include 'layout/footer.php';
?>
