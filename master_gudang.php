<?php
// master_gudang.php
require_once 'auth.php';      // cek login
require_admin();              // hanya admin yang boleh

$pageTitle = 'Master Lokasi Perusahaan';

// ==========================
// LOGIC PHP (TIDAK BERUBAH)
// ==========================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $code        = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $code === '') {
        $error = 'Nama dan kode Perusahaan wajib diisi.';
    } else {
        if ($id > 0) {
            // update
            $stmt = $pdo->prepare("UPDATE warehouses SET name = :n, code = :c, description = :d WHERE id = :id");
            $stmt->execute([':n' => $name, ':c' => $code, ':d' => $description, ':id' => $id]);
            $success = 'Data Perusahaan berhasil diperbarui.';
        } else {
            // insert
            $stmt = $pdo->prepare("INSERT INTO warehouses (name, code, description) VALUES (:n, :c, :d)");
            try {
                $stmt->execute([':n' => $name, ':c' => $code, ':d' => $description]);
                $success = 'Perusahaan baru berhasil ditambahkan.';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'uq_warehouses_code') !== false) {
                    $error = 'Kode Perusahaan sudah digunakan. Pilih kode lain.';
                } else {
                    throw $e;
                }
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($delId > 0) {
        $cek = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE warehouse_id = :id");
        $cek->execute([':id' => $delId]);
        if ($cek->fetchColumn() > 0) {
            $error = 'Perusahaan ini sudah dipakai di transaksi dan tidak bisa dihapus.';
        } else {
            $del = $pdo->prepare("DELETE FROM warehouses WHERE id = :id");
            $del->execute([':id' => $delId]);
            $success = 'Perusahaan berhasil dihapus.';
        }
    }
}

// Data tabel
$stmt = $pdo->query("SELECT * FROM warehouses ORDER BY name ASC");
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data edit
$editData = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($warehouses as $g) {
        if ((int)$g['id'] === $editId) {
            $editData = $g;
            break;
        }
    }
}

include 'layout/header.php';
?>

<style>
    .card-modern {
        border: none;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: transform 0.2s;
    }
    .form-icon-group .input-group-text {
        background-color: #f8f9fa;
        border-right: none;
        color: #6c757d;
    }
    .form-icon-group .form-control {
        border-left: none;
    }
    .form-icon-group .form-control:focus {
        box-shadow: none;
        border-color: #ced4da;
    }
    .form-icon-group:focus-within .input-group-text {
        border-color: #86b7fe; 
        color: #0d6efd;
    }
    .form-icon-group:focus-within .form-control {
        border-color: #86b7fe;
    }
    .table-hover tbody tr:hover {
        background-color: #f1f4f9;
    }
    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Master Perusahaan</h4>
        <small class="text-muted">Kelola lokasi penyimpanan barang</small>
    </div>
    <?php if ($editData): ?>
        <a href="master_gudang.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Tambah Baru
        </a>
    <?php endif; ?>
</div>

<div class="row g-4">
    
    <div class="col-lg-4">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi <?= $editData ? 'bi-pencil-square' : 'bi-plus-circle'; ?> me-2"></i>
                    <?= $editData ? 'Edit Data Perusahaan' : 'Tambah Perusahaan Baru'; ?>
                </h6>
            </div>
            <div class="card-body">
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center small py-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?= htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success d-flex align-items-center small py-2" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div><?= htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="id" value="<?= $editData['id'] ?? 0; ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Perusahaan</label>
                        <div class="input-group form-icon-group">
                            <span class="input-group-text"><i class="bi bi-building"></i></span>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: PT Dua Naga"
                                   value="<?= htmlspecialchars($editData['name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kode Perusahaan (Unik)</label>
                        <div class="input-group form-icon-group">
                            <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                            <input type="text" name="code" class="form-control text-uppercase" placeholder="Contoh: WH-001"
                                   value="<?= htmlspecialchars($editData['code'] ?? ''); ?>" required>
                        </div>
                        <div class="form-text small text-end fst-italic">Kode tidak boleh sama dengan Perusahaan lain.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Deskripsi / Alamat</label>
                        <div class="input-group form-icon-group">
                            <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                            <textarea name="description" class="form-control" rows="3" placeholder="Keterangan lokasi atau detail Perusahaan..."><?= htmlspecialchars($editData['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="bi bi-save me-2"></i>
                        <?= $editData ? 'Simpan Perubahan' : 'Simpan Data'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark">
                    <i class="bi bi-list-ul me-2"></i>Daftar Perusahaan
                </h6>
                <span class="badge bg-light text-dark border"><?= count($warehouses); ?> Lokasi</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                        <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-4" width="5%">No</th>
                            <th width="30%">Nama Perusahaan</th>
                            <th width="15%">Kode</th>
                            <th>Deskripsi</th>
                            <th class="text-center" width="15%">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($warehouses)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted opacity-50 mb-2">
                                        <i class="bi bi-inbox fs-1"></i>
                                    </div>
                                    <div class="text-muted fw-bold">Belum ada data Perusahaan</div>
                                    <small class="text-muted">Silakan tambah data melalui form di samping.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($warehouses as $g): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= $no++; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($g['name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-3">
                                            <?= htmlspecialchars($g['code']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?= htmlspecialchars($g['description'] ?: '-'); ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="master_gudang.php?edit=<?= (int)$g['id']; ?>" 
                                               class="btn btn-action btn-outline-warning" 
                                               data-bs-toggle="tooltip" title="Edit">
                                                <i class="bi bi-pencil-fill small"></i>
                                            </a>
                                            <a href="master_gudang.php?delete=<?= (int)$g['id']; ?>"
                                               class="btn btn-action btn-outline-danger ms-1"
                                               onclick="return confirm('Yakin ingin menghapus Perusahaan <?= htmlspecialchars($g['name']); ?>?');"
                                               data-bs-toggle="tooltip" title="Hapus">
                                                <i class="bi bi-trash-fill small"></i>
                                            </a>
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
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
</script>

<?php
include 'layout/footer.php';
?>
