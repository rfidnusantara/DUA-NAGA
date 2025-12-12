<?php
// auth.php
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// helper untuk cek role admin
function require_admin() {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        echo "Akses ditolak. Halaman ini hanya untuk admin.";
        exit;
    }
}
