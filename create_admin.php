<?php
// create_admin.php
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? 'admin');
    $full_name = trim($_POST['full_name'] ?? 'Administrator');
    $password  = $_POST['password'] ?? 'admin123';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
        $stmt->execute([':u' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Username sudah ada.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("
                INSERT INTO users (username, full_name, password_hash, role)
                VALUES (:u, :n, :p, 'admin')
            ");
            $ins->execute([
                ':u' => $username,
                ':n' => $full_name,
                ':p' => $hash,
            ]);
            $success = 'User admin berhasil dibuat. Hapus file create_admin.php setelah ini.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Buat Admin</title>
</head>
<body>
<h3>Buat User Admin Pertama</h3>

<?php if (!empty($error)): ?>
    <p style="color:red;"><?= htmlspecialchars($error); ?></p>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <p style="color:green;"><?= htmlspecialchars($success); ?></p>
<?php endif; ?>

<form method="post">
    <div>
        <label>Username: <input type="text" name="username" value="admin"></label>
    </div>
    <div>
        <label>Full name: <input type="text" name="full_name" value="Administrator"></label>
    </div>
    <div>
        <label>Password: <input type="password" name="password" value="admin123"></label>
    </div>
    <button type="submit">Buat Admin</button>
</form>

</body>
</html>
