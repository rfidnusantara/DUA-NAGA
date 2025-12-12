<?php
// login.php
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kalau sudah login, langsung ke dashboard
if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        // Ambil user berdasarkan username
        $stmt = $pdo->prepare("
            SELECT * FROM users
            WHERE username = :u
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Di sini kita pakai kolom 'password' biasa (tanpa hash)
        if (!$user || $user['password'] !== $password) {
            $error = 'Username atau password salah / user nonaktif.';
        } else {
            // Simpan user ke session
            $_SESSION['user'] = [
                'id'        => $user['id'],
                'username'  => $user['username'],
                'full_name' => $user['full_name'],
                'role'      => $user['role'],
            ];

            header('Location: index.php');
            exit;
        }
    }
}

// Set judul halaman untuk header
$pageTitle = 'Login Sistem Gudang';

include 'layout/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-4">
        <div class="card card-elevated">
            <div class="card-body">
                <h4 class="mb-3 text-center">Login Sistem Gudang</h4>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2">
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include 'layout/footer.php';
