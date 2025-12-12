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
// 0. FILTER PERUSAHAAN (GET)
// ==========================
$selectedCompany = trim($_GET['company'] ?? '');

// ==========================
// 1. Daftar gudang (Perusahaan Asal Barang)
// ==========================
$stmt = $pdo->query("SELECT id, name, code FROM warehouses ORDER BY name ASC");
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// 1b. (Opsional) Daftar PO dari barang aktif
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

// ==========================
// 1c. Ambil data API → Mapping PO -> raw name/address + FILTER PERUSAHAAN
// ==========================

$rawApi = fetch_sales_orders_from_api(1, 50);

if (is_array($rawApi) && isset($rawApi['data']) && is_array($rawApi['data'])) {
    $salesOrdersAll = $rawApi['data'];
} else {
    $salesOrdersAll = is_array($rawApi) ? $rawApi : [];
}

// Kumpulkan daftar perusahaan dari API (dinamis)
$companyOptions = [];
foreach ($salesOrdersAll as $order) {
    $companyName = $order['company']['name'] ?? '';
    if ($companyName !== '') {
        $companyOptions[$companyName] = $companyName;
    }
}

// Terapkan filter perusahaan
if ($selectedCompany !== '') {
    $salesOrdersApi = array_filter($salesOrdersAll, function ($order) use ($selectedCompany) {
        $companyName = $order['company']['name'] ?? '';
        return $companyName === $selectedCompany;
    });
} else {
    $salesOrdersApi = $salesOrdersAll;
}

/**
 * $poCustomerMap = [
 *   'PO-001' => [
 *       'name_raw'    => '(1) LUKI ...(2) BAGASKORO ...',
 *       'address_raw' => '(1) Jl....(2) Perum ...',
 *   ],
 *   ...
 * ];
 */
$poCustomerMap = [];

if (is_array($salesOrdersApi)) {
    foreach ($salesOrdersApi as $order) {
        $po = trim($order['customer']['customer_order']['number'] ?? $order['customer_order']['number'] ?? '');
        if ($po === '') continue;

        $custNameRaw    = trim($order['customer']['name'] ?? '');
        $custAddressRaw = trim($order['customer']['address'] ?? '');

        if ($custNameRaw === '' && $custAddressRaw === '') continue;

        $poCustomerMap[$po] = [
            'name_raw'    => $custNameRaw,
            'address_raw' => $custAddressRaw,
        ];
    }
}

$error = '';
$successMsg = '';

// ==========================
// 2. Handle Form Submit
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouseId     = (int)($_POST['warehouse_id'] ?? 0);
    $rfidRaw         = trim($_POST['rfid_tags'] ?? '');
    $notes           = trim($_POST['notes'] ?? ''); // keterangan manual untuk di Surat Jalan
    $customerName    = trim($_POST['customer_name'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $poNumber        = trim($_POST['po_number'] ?? '');

    if ($warehouseId <= 0) {
        $error = 'Silakan pilih Perusahaan asal barang.';
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
            $counts    = array_count_values($tags);
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
                        ':tanggal_sj'       => $tanggalSj,
                        ':customer_name'    => $customerName,
                        ':customer_address' => $customerAddress,
                        ':po_number'        => $poNumber,
                        ':warehouse_id'     => $warehouseId,
                        ':notes'            => $notes,
                        ':created_by'       => $currentUser,
                    ]);

                    $sjId = (int)$pdo->lastInsertId();
                    $noSj = 'SJ/' . date('ym') . '/' . str_pad($sjId, 4, '0', STR_PAD_LEFT);

                    $pdo->prepare("UPDATE surat_jalan SET no_sj = :no_sj WHERE id = :id")
                        ->execute([':no_sj' => $noSj, ':id' => $sjId]);

                    // C. Detail Item Surat Jalan
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

                    $pdo->commit();

                    if (!empty($notRegistered)) {
                        $_SESSION['flash_error'] =
                            'Peringatan: Sebagian tag tidak terdaftar dan dilewati: ' .
                            implode(', ', $notRegistered);
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

include 'layout/header.php';
?>

<style>
    .rfid-console {
        background-color: #212529;
        color: #ffc107;
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
    #previewContainer {
        min-height: 120px;
        max-height: 420px;
        overflow-y: auto;
        font-size: 0.85rem;
    }
</style>

<div class="row g-4">
    <!-- KIRI: FORM + SCANNER -->
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

                <!-- FILTER PERUSAHAAN (GET) -->
                <form method="get" class="mb-3">
                    <label class="form-label small text-muted fw-bold text-uppercase">
                        Filter Perusahaan (API)
                    </label>
                    <select name="company" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Perusahaan --</option>
                        <?php
                        if (!empty($companyOptions)) {
                            ksort($companyOptions);
                            foreach ($companyOptions as $compName):
                        ?>
                            <option value="<?= htmlspecialchars($compName); ?>"
                                <?= ($selectedCompany === $compName) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($compName); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        ?>
                    </select>
                    <div class="form-text small">
                        Gunakan filter ini untuk mempersempit daftar PO, nama, dan alamat customer.
                    </div>
                </form>

                <!-- FORM UTAMA (POST) -->
                <form method="post" autocomplete="off">

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">
                            1. Tujuan Pengiriman
                        </label>
                        <div class="card bg-light border-0 p-3">
                            <div class="mb-2">
                                <label class="small text-muted">Nama Customer</label>
                                <input type="text" name="customer_name" id="customer_name"
                                       class="form-control form-control-sm fw-bold"
                                       placeholder="Otomatis dari API"
                                       value="<?= htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                                       readonly required>

                                <div id="customer_choice_wrapper" class="mt-2" style="display:none;">
                                    <select id="customer_choice" class="form-select form-select-sm"></select>
                                    <div class="form-text small">
                                        Pilih Nama Customer jika PO memiliki lebih dari satu data.
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="small text-muted">Alamat Lengkap</label>
                                <textarea name="customer_address" id="customer_address"
                                          class="form-control form-control-sm"
                                          rows="2"
                                          placeholder="Otomatis dari API"
                                          readonly required><?= htmlspecialchars($_POST['customer_address'] ?? ''); ?></textarea>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="small text-muted">No. PO Customer</label>

                                    <!-- INPUT PENCARIAN PO -->
                                    <input type="text"
                                           id="po_search"
                                           class="form-control form-control-sm mb-1"
                                           placeholder="Ketik untuk mencari PO...">

                                    <select name="po_number" id="po_number" class="form-select form-select-sm">
                                        <option value="">-- Pilih PO dari API --</option>
                                        <?php foreach ($poCustomerMap as $po => $info): ?>
                                            <?php
                                            $nameRaw   = $info['name_raw'] ?? '';
                                            $nameParts = preg_split('/\(\d+\)/', $nameRaw);
                                            $firstName = trim($nameParts[1] ?? $nameParts[0] ?? $nameRaw);
                                            ?>
                                            <option
                                                value="<?= htmlspecialchars($po); ?>"
                                                data-name-raw="<?= htmlspecialchars($info['name_raw'] ?? ''); ?>"
                                                data-address-raw="<?= htmlspecialchars($info['address_raw'] ?? ''); ?>"
                                                <?= (isset($_POST['po_number']) && $_POST['po_number'] === $po) ? 'selected' : ''; ?>
                                            >
                                                <?= htmlspecialchars($po . ' - ' . $firstName); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">
                                        PO, nama & alamat dari API. Gunakan kotak di atas untuk mencari PO.
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Perusahaan Untuk Surat Jalan</label>
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

                    <!-- SCANNER -->
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
                        <input type="text" name="notes"
                               class="form-control form-control-sm"
                               placeholder="Keterangan untuk Surat Jalan (Opsional)"
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

    <!-- KANAN: LIVE PREVIEW ITEM -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-danger">
                    <i class="bi bi-eye me-2"></i>Live Preview Item Barang Keluar
                </h6>
                <small class="text-muted" style="font-size: 0.75rem;">
                    Otomatis update saat tag di-scan
                </small>
            </div>
            <div class="card-body p-0">
                <div id="previewContainer" class="border-top bg-white p-0">
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-upc-scan fs-3 d-block mb-2"></i>
                        Belum ada item di-scan.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ========== Helper pecah "(1) ... (2) ..." ==========
function parseIndexedList(raw) {
    raw = (raw || '').trim();
    if (!raw) return [];
    raw = raw.replace(/\r\n/g, '\n');
    const parts = raw.split(/\(\d+\)/).map(s => s.trim()).filter(Boolean);
    return parts;
}

function buildCustomers(nameRaw, addressRaw) {
    const names = parseIndexedList(nameRaw);
    const addrs = parseIndexedList(addressRaw);
    const len   = Math.max(names.length, addrs.length);
    const result = [];
    for (let i = 0; i < len; i++) {
        const nm = (names[i] || names[names.length - 1] || '').trim();
        const ad = (addrs[i] || addrs[addrs.length - 1] || '').trim();
        if (!nm && !ad) continue;
        result.push({ name: nm, address: ad });
    }
    return result;
}

// ============================
// PO SELECT -> Isi Nama & Alamat + Pencarian
// ============================
const poSelect         = document.getElementById('po_number');
const poSearchInput    = document.getElementById('po_search');
const custNameEl       = document.getElementById('customer_name');
const custAddressEl    = document.getElementById('customer_address');
const custChoiceWrapEl = document.getElementById('customer_choice_wrapper');
const custChoiceEl     = document.getElementById('customer_choice');

function resetCustomerFields() {
    if (custNameEl)    custNameEl.value    = '';
    if (custAddressEl) custAddressEl.value = '';
    if (custChoiceWrapEl) custChoiceWrapEl.style.display = 'none';
    if (custChoiceEl)  custChoiceEl.innerHTML = '';
    if (custChoiceEl)  delete custChoiceEl.dataset.customers;
}

function applyCustomerFromPo() {
    if (!poSelect || !custNameEl || !custAddressEl) return;

    const opt = poSelect.options[poSelect.selectedIndex];
    if (!opt || !opt.value) {
        resetCustomerFields();
        return;
    }

    const nameRaw = opt.dataset.nameRaw    || '';
    const addrRaw = opt.dataset.addressRaw || '';
    let customers = buildCustomers(nameRaw, addrRaw);

    if (!customers.length) {
        resetCustomerFields();
        return;
    }

    if (customers.length === 1) {
        if (custChoiceWrapEl) custChoiceWrapEl.style.display = 'none';
        custNameEl.value    = customers[0].name    || '';
        custAddressEl.value = customers[0].address || '';
        return;
    }

    if (!custChoiceWrapEl || !custChoiceEl) return;

    custChoiceWrapEl.style.display = 'block';
    custChoiceEl.innerHTML = '';
    custChoiceEl.dataset.customers = JSON.stringify(customers);

    customers.forEach((c, idx) => {
        const o = document.createElement('option');
        o.value = String(idx);
        o.textContent = c.name || ('Customer #' + (idx + 1));
        custChoiceEl.appendChild(o);
    });

    custChoiceEl.selectedIndex = 0;
    custNameEl.value    = customers[0].name    || '';
    custAddressEl.value = customers[0].address || '';
}

// ubah pilihan customer di dropdown
if (custChoiceEl && custNameEl && custAddressEl) {
    custChoiceEl.addEventListener('change', function () {
        let customers = [];
        if (this.dataset.customers) {
            try {
                customers = JSON.parse(this.dataset.customers);
            } catch (e) {
                customers = [];
            }
        }
        const idx = parseInt(this.value, 10);
        const c   = (Array.isArray(customers) && customers[idx]) ? customers[idx] : {};
        custNameEl.value    = c.name    || '';
        custAddressEl.value = c.address || '';
    });
}

// event perubahan PO
if (poSelect) {
    poSelect.addEventListener('change', applyCustomerFromPo);
    window.addEventListener('DOMContentLoaded', applyCustomerFromPo);
}

// FITUR PENCARIAN PO (filter option di select)
if (poSelect && poSearchInput) {
    poSearchInput.addEventListener('input', function () {
        const term = this.value.toLowerCase();

        for (let i = 0; i < poSelect.options.length; i++) {
            const opt = poSelect.options[i];
            if (!opt.value) { // option kosong ("-- pilih...")
                opt.hidden = false;
                continue;
            }
            const text = opt.text.toLowerCase();
            opt.hidden = text.indexOf(term) === -1;
        }
    });
}

// ============================
// Scanner & Preview
// ============================
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
            '<div class="text-center text-muted py-4 small">' +
            '<i class="bi bi-upc-scan fs-3 d-block mb-2"></i>' +
            'Belum ada item di-scan.' +
            '</div>';
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

            let html  = '<div class="table-responsive">';
            html     += '<table class="table table-sm table-bordered mb-0 small align-middle">';
            html     += '<thead class="table-light text-muted"><tr>' +
                        '<th style="width: 28%;">Tag</th>' +
                        '<th>Produk</th>' +
                        '<th style="width: 12%; text-align:center;">Status</th>' +
                        '</tr></thead><tbody>';

            data.items.forEach(row => {
                const registered = !!row.registered;
                const statusBadge = registered
                    ? '<span class="badge bg-success">OK</span>'
                    : '<span class="badge bg-danger">Not Found</span>';

                const productName = registered
                    ? (row.product_name || '-')
                    : '<span class="text-danger fst-italic">Tidak terdaftar</span>';

                html += `<tr>
                    <td class="font-monospace">${row.tag}</td>
                    <td>${productName}</td>
                    <td class="text-center">${statusBadge}</td>
                </tr>`;
            });

            html += '</tbody></table></div>';
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
?>
