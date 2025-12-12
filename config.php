<?php
// =======================
// 1. KONEKSI DB GUDANG
// =======================
// Hapus ':8080'. MySQL biasanya hanya 'localhost' atau '127.0.0.1'
$host = 'localhost'; 
$dbname = 'db_gudang';
$username = 'root';
$password = '';
date_default_timezone_set('Asia/Jakarta');

try {
    // Format DSN yang benar. Port default 3306 tidak perlu ditulis, 
    // tapi jika mau spesifik bisa tambah: ;port=3306
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set error mode ke Exception agar mudah debugging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Opsi tambahan untuk mencegah "MySQL server has gone away" pada script berat
    $pdo->setAttribute(PDO::ATTR_PERSISTENT, true); 
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    die('Koneksi db_gudang gagal: ' . $e->getMessage());
}

// =======================
// 2. KONFIGURASI API
// =======================
$API_BASE_URL = 'https://core.db.nagaverse.id';
$API_APP_ID   = 'fee8c492-acec-4058-a7c1-e540ee6e6eef';
$API_KEY      = '7f5b370fb4ecfc552cd4be8bb5ada61a6c69260262ef2029a422a78b517cefc8';
?>