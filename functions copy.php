<?php
require_once 'config.php';

/**
 * Ambil data sales orders dari API (paginated).
 * Return: array daftar sales orders.
 */
function fetch_sales_orders_from_api($page = 1, $perPage = 50)
{
    global $API_BASE_URL, $API_APP_ID, $API_KEY;

    $url = $API_BASE_URL . '/api/client/sales-orders?paginate=true'
         . '&per_page=' . intval($perPage)
         . '&page=' . intval($page);

    $headers = [
        'X-App-Id: ' . $API_APP_ID,
        'X-Api-Key: ' . $API_KEY,
        'Accept: application/json',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    if ($response === false) {
        error_log('cURL error: ' . curl_error($ch));
        curl_close($ch);
        return [];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('HTTP error: ' . $httpCode . ' | Response: ' . $response);
        return [];
    }

    $data = json_decode($response, true);

    if ($data === null) {
        error_log('Gagal decode JSON: ' . $response);
        return [];
    }

    if (isset($data['data']) && is_array($data['data'])) {
        return $data['data'];
    }

    if (is_array($data)) {
        return $data;
    }

    return [];
}

