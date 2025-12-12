<?php
// preview_rfid_tags.php
header('Content-Type: application/json');

// Pastikan pakai file yang ada $pdo
require_once 'functions.php';

try {
    // Baca body JSON dari fetch()
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data) || !isset($data['tags']) || !is_array($data['tags'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Input tidak valid',
            'items'   => [],
        ]);
        exit;
    }

    // Bersihkan & unikkan tag
    $tags = array_map('trim', $data['tags']);
    $tags = array_filter($tags, fn($t) => $t !== '');
    $tags = array_values(array_unique($tags));

    // Batasi jumlah untuk keamanan
    if (count($tags) > 200) {
        $tags = array_slice($tags, 0, 200);
    }

    if (empty($tags)) {
        echo json_encode([
            'success' => true,
            'items'   => [],
        ]);
        exit;
    }

    // Buat placeholder IN (?, ?, ...)
    $placeholders = implode(',', array_fill(0, count($tags), '?'));

    $sql = "
        SELECT
            rfid_tag,
            product_name,
            po_number,
            so_number,
            name_label,
            pcs
        FROM rfid_registrations
        WHERE rfid_tag IN ($placeholders)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($tags);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // mapping tag => data
    $map = [];
    foreach ($rows as $row) {
        $tag = $row['rfid_tag'];
        $map[$tag] = $row;
    }

    $items = [];
    foreach ($tags as $tag) {
        if (isset($map[$tag])) {
            $row = $map[$tag];
            $items[] = [
                'tag'          => $tag,
                'registered'   => true,
                'product_name' => $row['product_name'] ?? '',
                'po_number'    => $row['po_number'] ?? '',
                'so_number'    => $row['so_number'] ?? '',
                'name_label'   => $row['name_label'] ?? '',
                'pcs'          => isset($row['pcs']) ? (int)$row['pcs'] : null,
            ];
        } else {
            // tag belum registrasi
            $items[] = [
                'tag'          => $tag,
                'registered'   => false,
                'product_name' => '',
                'po_number'    => '',
                'so_number'    => '',
                'name_label'   => '',
                'pcs'          => null,
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'items'   => $items,
    ]);
} catch (Throwable $e) {
    // Jangan kirim error HTML ke client, cukup log & kirim JSON error
    error_log('preview_rfid_tags error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'items'   => [],
    ]);
}
