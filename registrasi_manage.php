<?php
// registrasi_manage.php
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// require_once 'auth.php'; // Aktifkan jika ada sistem login

$pageTitle = 'Manajemen Registrasi RFID';

// Ambil flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ==========================
// 1. Handle Action (POST)
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'ID data tidak valid.'];
        header('Location: registrasi_manage.php');
        exit;
    }

    // Cek Data
    $stmt = $pdo->prepare("SELECT * FROM rfid_registrations WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reg) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Data tidak ditemukan.'];
        header('Location: registrasi_manage.php');
        exit;
    }

    // Logic Action
    if ($action === 'toggle') {
        $newStatus = ($reg['is_active'] ?? 0) ? 0 : 1;
        $upd = $pdo->prepare("UPDATE rfid_registrations SET is_active = :st WHERE id = :id");
        $upd->execute([':st' => $newStatus, ':id' => $id]);

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';
        $icon       = $newStatus ? 'check-circle' : 'slash-circle';
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "RFID <b>{$reg['rfid_tag']}</b> berhasil <b>$statusText</b>."];
        
        header('Location: registrasi_manage.php');
        exit;

    } elseif ($action === 'delete') {
        // Validasi: Harus non-aktif dulu
        if (!empty($reg['is_active'])) {
            $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Gagal Hapus: Nonaktifkan status tag terlebih dahulu.'];
            header('Location: registrasi_manage.php');
            exit;
        }

        // Validasi: Cek penggunaan di history
        $cekMov = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE rfid_tag = :tag");
        $cekMov->execute([':tag' => $reg['rfid_tag']]);
        if ($cekMov->fetchColumn() > 0) {
            $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Gagal Hapus: Tag ini memiliki riwayat transaksi gudang.'];
            header('Location: registrasi_manage.php');
            exit;
        }

        $del = $pdo->prepare("DELETE FROM rfid_registrations WHERE id = :id");
        $del->execute([':id' => $id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Data registrasi <b>{$reg['rfid_tag']}</b> dihapus permanen."];
        header('Location: registrasi_manage.php');
        exit;
    }
}

// ==========================
// 2. Filter & Data (GET)
// ==========================
$statusFilter = $_GET['status'] ?? 'all';
$q            = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM rfid_registrations WHERE 1=1";
$params = [];

if ($statusFilter === 'active') {
    $sql .= " AND is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $sql .= " AND is_active = 0";
}

if ($q !== '') {
    $sql .= " AND (rfid_tag LIKE :q OR product_name LIKE :q OR po_number LIKE :q OR so_number LIKE :q OR name_label LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$sql .= " ORDER BY created_at DESC, id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<style>
    .card-modern {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }
    .badge-soft-success { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
    .badge-soft-secondary { background-color: rgba(108, 117, 125, 0.1); color: #6c757d; }
    
    .rfid-pill {
        font-family: 'Consolas', monospace;
        font-size: 0.85rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #334155;
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-block;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .table-hover tbody tr:hover {
        background-color: #f8fafc;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    .action-btn:hover {
        transform: translateY(-2px);
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Manajemen Registrasi RFID</h4>
        <small class="text-muted">Kelola status tag, perbaiki data, atau hapus item yang salah input.</small>
    </div>
    
    <div class="d-flex gap-2">
        <div class="bg-white border rounded px-3 py-2 text-center shadow-sm">
            <small class="text-muted d-block text-uppercase" style="font-size: 0.65rem;">Total Data</small>
            <span class="fw-bold text-primary"><?= count($rows); ?></span>
        </div>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= $flash['type']; ?> alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
        <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> fs-4 me-3"></i>
        <div><?= $flash['msg']; ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card card-modern mb-4">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-funnel-fill"></i></span>
                    <select name="status" class="form-select border-start-0 bg-light fw-bold text-secondary" onchange="this.form.submit()">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : ''; ?>>Aktif (Active)</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : ''; ?>>Tidak Aktif (Inactive)</option>
                    </select>
                </div>
            </div>

            <div class="col-md-7">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 ps-0" 
                           placeholder="Cari Tag ID, Nama Produk, PO, atau SO..." 
                           value="<?= htmlspecialchars($q); ?>">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Cari</button>
                </div>
            </div>

            <div class="col-md-2 text-end">
                <a href="registrasi_manage.php" class="btn btn-light border w-100 text-muted" data-bs-toggle="tooltip" title="Reset semua filter">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card card-modern">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-secondary small text-uppercase fw-bold">
                <tr>
                    <th class="ps-4" width="5%">No</th>
                    <th width="20%">RFID Tag</th>
                    <th width="30%">Info Produk</th>
                    <th width="15%">Referensi (PO/SO)</th>
                    <th width="10%">Status</th>
                    <th width="10%">Terdaftar</th>
                    <th class="text-center" width="10%">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="mb-2">
                            <i class="bi bi-folder2-open fs-1 text-muted opacity-25"></i>
                        </div>
                        <h6 class="text-muted fw-bold">Data Tidak Ditemukan</h6>
                        <small class="text-muted">Coba sesuaikan kata kunci pencarian atau filter status Anda.</small>
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = 1; ?>
                <?php foreach ($rows as $row): ?>
                    <?php 
                        $isActive = !empty($row['is_active']);
                        $rowClass = $isActive ? '' : 'bg-light text-muted'; // Efek redup untuk baris non-aktif
                    ?>
                    <tr class="<?= $rowClass; ?>">
                        <td class="ps-4 small text-muted"><?= $no++; ?></td>
                        
                        <td>
                            <span class="rfid-pill" title="ID Unik RFID">
                                <i class="bi bi-upc-scan me-1 text-secondary"></i>
                                <?= htmlspecialchars($row['rfid_tag']); ?>
                            </span>
                        </td>

                        <td>
                            <div class="fw-bold text-dark text-truncate" style="max-width: 250px;">
                                <?= htmlspecialchars($row['product_name']); ?>
                            </div>
                            <div class="small text-muted d-flex align-items-center gap-2 mt-1">
                                <span><i class="bi bi-tag me-1"></i> <?= htmlspecialchars($row['name_label'] ?: '-'); ?></span>
                                <span class="border-start ps-2"><i class="bi bi-box me-1"></i> Qty: <b><?= (int)$row['pcs']; ?></b></span>
                            </div>
                        </td>

                        <td>
                            <div class="d-flex flex-column gap-1" style="font-size: 0.8rem;">
                                <?php if ($row['po_number']): ?>
                                    <span class="badge bg-white text-dark border fw-normal text-start">
                                        PO: <?= htmlspecialchars($row['po_number']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($row['so_number']): ?>
                                    <span class="badge bg-white text-dark border fw-normal text-start">
                                        SO: <?= htmlspecialchars($row['so_number']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!$row['po_number'] && !$row['so_number']): ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge badge-soft-success rounded-pill px-3 py-2">
                                    <i class="bi bi-check-circle-fill me-1"></i> Aktif
                                </span>
                            <?php else: ?>
                                <span class="badge badge-soft-secondary rounded-pill px-3 py-2">
                                    <i class="bi bi-pause-circle-fill me-1"></i> Non-Aktif
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="small text-muted">
                            <div><?= date('d M Y', strtotime($row['created_at'])); ?></div>
                            <div style="font-size: 0.75rem;"><?= date('H:i', strtotime($row['created_at'])); ?> WIB</div>
                        </td>

                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-2">
                                <form method="post">
                                    <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <?php if ($isActive): ?>
                                        <button type="submit" class="btn action-btn btn-outline-warning border-0 bg-warning bg-opacity-10 text-warning" 
                                                data-bs-toggle="tooltip" title="Nonaktifkan Tag"
                                                onclick="return confirm('Nonaktifkan tag ini agar tidak terbaca sebagai stok aktif?');">
                                            <i class="bi bi-pause-fill fs-5"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn action-btn btn-outline-success border-0 bg-success bg-opacity-10 text-success" 
                                                data-bs-toggle="tooltip" title="Aktifkan Tag"
                                                onclick="return confirm('Aktifkan kembali tag ini?');">
                                            <i class="bi bi-play-fill fs-5"></i>
                                        </button>
                                    <?php endif; ?>
                                </form>

                                <?php if (!$isActive): ?>
                                    <form method="post">
                                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn action-btn btn-outline-danger border-0 bg-danger bg-opacity-10 text-danger" 
                                                data-bs-toggle="tooltip" title="Hapus Permanen"
                                                onclick="return confirm('PERINGATAN: Hapus permanen?\nPastikan tag ini tidak memiliki riwayat transaksi penting.');">
                                            <i class="bi bi-trash-fill fs-5"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn action-btn btn-light text-muted border-0" disabled style="opacity: 0.3;" title="Nonaktifkan dulu untuk menghapus">
                                        <i class="bi bi-trash-fill fs-5"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="card-footer bg-white border-top py-3">
        <div class="d-flex justify-content-between align-items-center small text-muted">
            <span>Menampilkan <?= count($rows); ?> data terbaru (Limit 200).</span>
            <span><i class="bi bi-info-circle me-1"></i> Gunakan pencarian untuk data spesifik.</span>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php
include 'layout/footer.php';
?>