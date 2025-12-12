<?php
// master_user.php
require_once 'auth.php';
require_admin();

$pageTitle = 'Manajemen User';

$error = '';
$success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int)($_POST['id'] ?? 0);
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? 'user';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($username === '' || $full_name === '') {
        $error = 'Username dan nama lengkap wajib diisi.';
    } elseif (!in_array($role, ['admin','user'], true)) {
        $error = 'Role tidak valid.';
    } else {
        if ($id > 0) {
            // Update
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=:u, full_name=:n, password_hash=:p, role=:r, is_active=:a WHERE id=:id");
                $stmt->execute([':u'=>$username, ':n'=>$full_name, ':p'=>$hash, ':r'=>$role, ':a'=>$is_active, ':id'=>$id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=:u, full_name=:n, role=:r, is_active=:a WHERE id=:id");
                $stmt->execute([':u'=>$username, ':n'=>$full_name, ':r'=>$role, ':a'=>$is_active, ':id'=>$id]);
            }
            $success = 'Data user berhasil diperbarui.';
        } else {
            // Insert
            if ($password === '') {
                $error = 'Password wajib diisi untuk user baru.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, password_hash, role, is_active) VALUES (:u, :n, :p, :r, :a)");
                try {
                    $stmt->execute([':u'=>$username, ':n'=>$full_name, ':p'=>$hash, ':r'=>$role, ':a'=>$is_active]);
                    $success = 'User baru berhasil ditambahkan.';
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $error = 'Username sudah digunakan. Pilih username lain.';
                    } else {
                        throw $e;
                    }
                }
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($delId > 0) {
        if (!empty($_SESSION['user']) && $_SESSION['user']['id'] == $delId) {
            $error = 'Tidak bisa menghapus user yang sedang login.';
        } else {
            $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $del->execute([':id' => $delId]);
            $success = 'User berhasil dihapus.';
        }
    }
}

// Get Data
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Edit Mode
$editData = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($users as $u) {
        if ((int)$u['id'] === $editId) {
            $editData = $u;
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
    .avatar-initials {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.9rem;
        margin-right: 10px;
        text-transform: uppercase;
    }
    .badge-soft-primary { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
    .badge-soft-secondary { background-color: rgba(108, 117, 125, 0.1); color: #6c757d; }
    .badge-soft-success { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
    .badge-soft-danger { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
    
    .btn-action {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; padding: 0;
    }
    /* Warna acak untuk avatar */
    .bg-color-1 { background: #4e73df; }
    .bg-color-2 { background: #1cc88a; }
    .bg-color-3 { background: #36b9cc; }
    .bg-color-4 { background: #f6c23e; }
    .bg-color-5 { background: #e74a3b; }
    .bg-color-6 { background: #858796; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Manajemen User</h4>
        <small class="text-muted">Kelola akun dan hak akses pengguna sistem</small>
    </div>
    <?php if ($editData): ?>
        <a href="master_user.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Mode Tambah
        </a>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi <?= $editData ? 'bi-person-gear' : 'bi-person-plus'; ?> me-2"></i>
                    <?= $editData ? 'Edit Data User' : 'Tambah User Baru'; ?>
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
                        <label class="form-label small fw-bold text-muted">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="user123"
                                   value="<?= htmlspecialchars($editData['username'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-card-heading"></i></span>
                            <input type="text" name="full_name" class="form-control" placeholder="Budi Santoso"
                                   value="<?= htmlspecialchars($editData['full_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">
                            Password <?= $editData ? '<span class="fw-normal text-muted">(Isi jika ingin ubah)</span>' : ''; ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="inputPassword" class="form-control" placeholder="******">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Role</label>
                            <select name="role" class="form-select">
                                <option value="user" <?= ($editData['role'] ?? '') === 'user' ? 'selected' : ''; ?>>User (Staff)</option>
                                <option value="admin" <?= ($editData['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Status Akun</label>
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active"
                                       <?= !isset($editData['is_active']) || $editData['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="is_active">Aktif</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="bi bi-save me-2"></i>
                        <?= $editData ? 'Simpan Perubahan' : 'Buat User'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-modern h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark">
                    <i class="bi bi-people me-2"></i>Daftar Pengguna
                </h6>
                <span class="badge bg-light text-dark border"><?= count($users); ?> Users</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                        <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-emoji-frown fs-1 d-block mb-2 opacity-50"></i>
                                    Belum ada data user.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            // Array warna random untuk avatar
                            $bgColors = ['bg-color-1','bg-color-2','bg-color-3','bg-color-4','bg-color-5','bg-color-6'];
                            ?>
                            <?php foreach ($users as $index => $u): ?>
                                <?php 
                                    $initial = strtoupper(substr($u['full_name'], 0, 1));
                                    $colorClass = $bgColors[$index % count($bgColors)];
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initials <?= $colorClass; ?>">
                                                <?= $initial; ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($u['full_name']); ?></div>
                                                <div class="small text-muted">@<?= htmlspecialchars($u['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge badge-soft-primary rounded-pill px-3">
                                                <i class="bi bi-shield-lock-fill me-1"></i> Admin
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-secondary rounded-pill px-3">
                                                <i class="bi bi-person me-1"></i> Staff
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['is_active']): ?>
                                            <span class="badge badge-soft-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('d M Y', strtotime($u['created_at'])); ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="master_user.php?edit=<?= (int)$u['id']; ?>" 
                                               class="btn btn-action btn-outline-warning" 
                                               data-bs-toggle="tooltip" title="Edit Data">
                                                <i class="bi bi-pencil-square small"></i>
                                            </a>
                                            <a href="master_user.php?delete=<?= (int)$u['id']; ?>"
                                               class="btn btn-action btn-outline-danger ms-1"
                                               onclick="return confirm('Hapus user <?= htmlspecialchars($u['username']); ?>?');"
                                               data-bs-toggle="tooltip" title="Hapus User">
                                                <i class="bi bi-trash3 small"></i>
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
    // Toggle Password Visibility
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#inputPassword');

    togglePassword.addEventListener('click', function (e) {
        // toggle the type attribute
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        // toggle the eye icon
        this.querySelector('i').classList.toggle('bi-eye');
        this.querySelector('i').classList.toggle('bi-eye-slash');
    });

    // Init Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
</script>

<?php
include 'layout/footer.php';
?>