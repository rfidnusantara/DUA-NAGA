<?php
// get_latest_rfid.php
header('Content-Type: application/json');

// File ini ditulis oleh run_registrasi.py / run_rfid.py (PATH_LATEST)
$latestPath = __DIR__ . '/static_files/reads_latest.json';

if (!file_exists($latestPath)) {
    echo json_encode(['success' => false, 'message' => 'File reads_latest.json belum ada']);
    exit;
}

$json = file_get_contents($latestPath);
if ($json === false || trim($json) === '') {
    echo json_encode(['success' => false, 'message' => 'File kosong']);
    exit;
}

$data = json_decode($json, true);
if (!is_array($data) || empty($data)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

/**
 * Struktur reads_latest.json (dari Python) kira-kira:
 * {
 *   "CODE1": { "epc": "E28069....", "timestamp": "2025-12-07 12:00:00", ... },
 *   "CODE2": { "epc": "E28069....", "timestamp": "2025-12-07 12:00:01", ... },
 *   ...
 * }
 *
 * Kita ingin mengembalikan SEMUA EPC unik yang ada di file tersebut,
 * bukan hanya yang timestamp-nya paling baru.
 */

$epcMap = []; // pakai associative array untuk buang duplikat: epc => true

foreach ($data as $code => $info) {
    if (!is_array($info)) {
        continue;
    }

    $epc = $info['epc'] ?? '';
    if ($epc === '') {
        continue;
    }

    // buat unik, kunci = epc
    $epcMap[$epc] = true;
}

$tags = array_keys($epcMap);

if (empty($tags)) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada EPC di file']);
    exit;
}

// Opsional: sort epc biar rapi (boleh dihapus kalau tidak perlu)
sort($tags);

echo json_encode([
    'success' => true,
    'tags'    => $tags,
    'count'   => count($tags),
]);
