<?php
require_once 'config.php';

/**
 * Ambil data sales orders dari API (paginated).
 *
 * @param int   $page        Halaman ke berapa
 * @param int   $perPage     Jumlah data per halaman
 * @param array $params      Filter tambahan: ['search' => 'xxx', 'sort_column' => 'number', ...]
 * @param bool  $raw         Kalau true, kembalikan full response (data+meta+links).
 *
 * @return array             Jika $raw=false => array data orders saja.
 *                           Jika $raw=true  => array full (seperti JSON asli).
 */
function fetch_sales_orders_from_api($page = 1, $perPage = 50, array $params = [], $raw = false)
{
    global $API_BASE_URL, $API_APP_ID, $API_KEY;

    // Base URL + param wajib
    $query = [
        'paginate'   => 'true',
        'per_page'   => (int)$perPage,
        'page'       => (int)$page,
    ];

    // Tambah parameter opsional (search, sort_column, sort_order, dll)
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') continue;
        $query[$key] = $value;
    }

    $url = $API_BASE_URL . '/api/client/sales-orders?' . http_build_query($query);

    $headers = [
        'X-App-Id: ' . $API_APP_ID,
        'X-Api-Key: ' . $API_KEY,
        'Accept: application/json',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // Optional: kalau hosting-mu bermasalah SSL, sementara bisa di-off (tidak disarankan di production)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    if ($response === false) {
        error_log('cURL error (sales-orders): ' . curl_error($ch));
        curl_close($ch);
        return [];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('HTTP error (sales-orders): ' . $httpCode . ' | Response: ' . $response);
        return [];
    }

    $data = json_decode($response, true);
    if ($data === null) {
        error_log('Gagal decode JSON (sales-orders): ' . $response);
        return [];
    }

    // Kalau kamu butuh full meta & links (misal mau looping semua page)
    if ($raw) {
        return $data;
    }

    // Untuk pemakaian biasa di dropdown: cukup bagian 'data'
    if (isset($data['data']) && is_array($data['data'])) {
        return $data['data'];
    }

    // Fallback: kalau API suatu hari berubah tapi masih array
    if (is_array($data)) {
        return $data;
    }

    return [];
}
