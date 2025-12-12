<?php

// surat_jalan_cetak.php

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sjId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($sjId <= 0) {
    die('ID Surat Jalan tidak valid.');
}

// ==============================
// 1. Ambil data header + gudang
// ==============================
$stmt = $pdo->prepare("
    SELECT sj.*, w.name AS warehouse_name, w.code AS warehouse_code, w.description AS warehouse_desc
    FROM surat_jalan sj
    LEFT JOIN warehouses w ON sj.warehouse_id = w.id
    WHERE sj.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $sjId]);
$sj = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sj) {
    die('Surat Jalan tidak ditemukan.');
}

// ==============================
// 2. Ambil detail item
// ==============================
$itemStmt = $pdo->prepare("
    SELECT *
    FROM surat_jalan_items
    WHERE surat_jalan_id = :id
    ORDER BY product_name ASC, batch_number ASC, id ASC
");
$itemStmt->execute([':id' => $sjId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// ==============================
// 3. Helper: format tanggal Indonesia
// ==============================
function tanggal_indo($dateStr)
{
    if (!$dateStr) return '';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
             'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $ts = strtotime($dateStr);
    if (!$ts) return $dateStr;
    $d = (int)date('d', $ts);
    $m = (int)date('m', $ts);
    $y = date('Y', $ts);
    return $d . ' ' . ($bulan[$m] ?? date('F', $ts)) . ' ' . $y;
}

// ==============================
// 4. Mapping header perusahaan
// ==============================
function getCompanyHeader($warehouseName)
{
    $warehouseName = trim((string)$warehouseName);

    // DEFAULT jika nama gudang tidak cocok mapping apa pun
    $default = [
        'logo'       => 'img/default-logo.png',     // opsional
        'short_name' => $warehouseName ?: 'PERUSAHAAN',
        'full_name'  => $warehouseName ?: 'PERUSAHAAN',
        'address'    => '',
        'phone'      => '',
        'web'        => '',
        'city_line'  => 'Sukoharjo',               // untuk teks tanggal di bawah
    ];

    $map = [
        'PT. Dua Naga Kosmetindo' => [
            'logo'       => 'img/naga.png',        // C:\laragon\www\naga\img\naga.png
            'short_name' => 'PT. DUA NAGA KOSMETINDO',
            'full_name'  => 'PT. DUA NAGA KOSMETINDO',
            'address'    => 'DK. Gambiran RT.003 RW.001 Krajan, Gatak, Sukoharjo, Jawa Tengah, Indonesia',
            'phone'      => 'Telepon : (0271) 7470508',
            'web'        => '',
            'city_line'  => 'Sukoharjo',
        ],
        'CV. Zweena Adi Nugraha' => [
            'logo'       => 'img/zweena.png',
            'short_name' => 'CV. ZWEENA ADI NUGRAHA',
            'full_name'  => 'CV. ZWEENa ADI NUGRAHA',
            'address'    => 'Dk. Bulurejo Rt. 04 Rw. 01, Ds. Krajan, Kec. Gatak - Kab. Sukoharjo, Jawa Tengah - Indonesia',
            'phone'      => 'Telepon : 0812-2155-525',
            'web'        => 'Laman : http://www.zweena.co.id',
            'city_line'  => 'Sukoharjo',
        ],
        'PT. Phytomed Neo Farma' => [
            'logo'       => 'img/neo.png',
            'short_name' => 'PT. PHYTOMED NEO FARMA',
            'full_name'  => 'PT. PHYTOMED NEO FARMA',
            'address'    => 'Alamat PT. Phytomed Neo Farma',
            'phone'      => '',
            'web'        => '',
            'city_line'  => 'Sukoharjo',
        ],
        'CV. Indo Naga Food' => [
            'logo'       => 'img/indo.png',
            'short_name' => 'CV. INDO NAGA FOOD',
            'full_name'  => 'CV. INDO NAGA FOOD',
            'address'    => 'Alamat CV. Indo Naga Food',
            'phone'      => '',
            'web'        => '',
            'city_line'  => 'Sukoharjo',
        ],
    ];

    return $map[$warehouseName] ?? $default;
}

$company    = getCompanyHeader($sj['warehouse_name'] ?? '');
$tanggalSj  = $sj['tanggal_sj'] ? tanggal_indo($sj['tanggal_sj']) : tanggal_indo(date('Y-m-d'));
$todayPrint = tanggal_indo(date('Y-m-d'));
$printedBy  = $_SESSION['user']['username'] ?? 'System';

// Hitung total (Qty PCS & jumlah baris sebagai QTY COLLY)
$totalPcs   = 0;
$totalColly = 0;
foreach ($items as $it) {
    $totalPcs   += (int)$it['qty'];
    $totalColly += 1;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - <?= htmlspecialchars($sj['no_sj']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 10px;
        }
        table {
            border-collapse: collapse;
        }
        .wrap {
            width: 100%;
            max-width: 1100px; /* mendekati A4 landscape */
            margin: 0 auto;
        }
        .tbl-main {
            width: 100%;
            border: 1px solid #000;
        }
        .tbl-main td {
            vertical-align: top;
        }

        /* HEADER ATAS */
        .logo-cell {
            width: 140px;
            border-right: 1px solid #000;
            text-align: center;
            padding: 10px 5px;
        }
        .logo-cell img {
            max-width: 90px;
            max-height: 60px;
        }
        .logo-sub {
            margin-top: 8px;
            font-size: 9px;
            font-weight: bold;
        }
        .company-cell {
            border-right: 1px solid #000;
            text-align: center;
            padding: 5px 10px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .company-address {
            font-size: 9px;
            margin-top: 4px;
        }
        .company-address div {
            margin: 1px 0;
        }
        .header-info-cell {
            width: 170px;
            padding: 0;
        }
        .mini-info {
            width: 100%;
            border-left: 1px solid #000;
            border-bottom: 1px solid #000;
            font-size: 9px;
        }
        .mini-info td {
            border-bottom: 1px solid #000;
            padding: 2px 3px;
        }
        .mini-info tr:last-child td {
            border-bottom: none;
        }
        .mini-info td:first-child {
            width: 55%;
        }

        .title-row td {
            border-top: 1px solid #000;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            padding: 3px 0 5px 0;
        }

        /* BLOK CUSTOMER & NO SJ */
        .customer-block td {
            font-size: 10px;
            padding: 3px 5px;
        }
        .customer-block td.label {
            width: 90px;
        }
        .customer-block td.colon {
            width: 10px;
        }
        .customer-block td.right-label {
            width: 130px;
        }

        /* TABEL DETAIL BARANG */
        .detail-header th,
        .detail-header td {
            border: 1px solid #000;
            text-align: center;
            font-size: 10px;
            padding: 3px 2px;
            font-weight: bold;
        }
        .detail-body td {
            border: 1px solid #000;
            font-size: 10px;
            padding: 3px 3px;
        }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-bold   { font-weight: bold; }

        /* FOOTER TTD */
        .footer-ttd td {
            font-size: 10px;
            padding: 3px 5px;
        }
        .footer-ttd .space {
            height: 40px;
        }

        .no-print {
            text-align: center;
            margin-bottom: 10px;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0; margin: 0; }
            .wrap { margin: 0; max-width: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">Cetak / Print</button>
</div>

<div class="wrap">
    <table class="tbl-main">
        <!-- HEADER ATAS -->
        <tr>
            <td class="logo-cell">
                <img src="<?= htmlspecialchars($company['logo']); ?>" alt="Logo">
                <div class="logo-sub"><?= htmlspecialchars($company['short_name']); ?></div>
            </td>
            <td class="company-cell">
                <div class="company-name"><?= htmlspecialchars($company['full_name']); ?></div>
                <div class="company-address">
                    <?php if (!empty($company['address'])): ?>
                        <div><?= htmlspecialchars($company['address']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($company['phone'])): ?>
                        <div><?= htmlspecialchars($company['phone']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($company['web'])): ?>
                        <div><?= htmlspecialchars($company['web']); ?></div>
                    <?php endif; ?>
                </div>
            </td>
            <td class="header-info-cell">
                <table class="mini-info">
                    <tr>
                        <td>Halaman</td>
                        <td>: 1 dari 1</td>
                    </tr>
                    <tr>
                        <td>Nomor</td>
                        <td>: <?= htmlspecialchars($sj['no_sj']); ?></td>
                    </tr>
                    <tr>
                        <td>Tanggal berlaku</td>
                        <td>: <?= $tanggalSj; ?></td>
                    </tr>
                    <tr>
                        <td>Mengganti No.</td>
                        <td>: -</td>
                    </tr>
                    <tr>
                        <td>Tanggal</td>
                        <td>: <?= $tanggalSj; ?></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr class="title-row">
            <td colspan="3">SURAT JALAN</td>
        </tr>

        <!-- BLOK CUSTOMER / NO SJ / PO -->
        <tr>
            <td colspan="3">
                <table width="100%" class="customer-block">
                    <tr>
                        <td class="label">Nama Customer</td>
                        <td class="colon">:</td>
                        <td><?= htmlspecialchars($sj['customer_name']); ?></td>
                        <td class="right-label">Nomor SJ / Tanggal SJ</td>
                        <td class="colon">:</td>
                        <td><?= htmlspecialchars($sj['no_sj']); ?> / <?= $tanggalSj; ?></td>
                    </tr>
                    <tr>
                        <td class="label">Alamat Customer</td>
                        <td class="colon">:</td>
                        <td><?= nl2br(htmlspecialchars($sj['customer_address'])); ?></td>
                        <td class="right-label">Nomor PO / Tanggal PO</td>
                        <td class="colon">:</td>
                        <td><?= htmlspecialchars($sj['po_number'] ?: '-'); ?> / -</td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- HEADER TABEL DETAIL -->
        <tr>
            <td colspan="3">
                <table width="100%">
                    <tr class="detail-header">
                        <th style="width: 4%;">No</th>
                        <th style="width: 32%;">Nama Barang</th>
                        <th style="width: 14%;">Batch</th>
                        <th style="width: 8%;">Jumlah</th>
                        <th style="width: 6%;">Satuan</th>
                        <th style="width: 8%;">QTY</th>
                        <th style="width: 8%;">Satuan</th>
                        <th style="width: 10%;">Total</th>
                        <th style="width: 10%;">Keterangan</th>
                    </tr>

                    <?php if (empty($items)): ?>
                        <tr class="detail-body">
                            <td colspan="9" class="text-center">Tidak ada item.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        // Grouping per product_name
                        $groups = [];
                        foreach ($items as $row) {
                            $key = $row['product_name'];
                            if (!isset($groups[$key])) {
                                $groups[$key] = [];
                            }
                            $groups[$key][] = $row;
                        }

                        $no = 1;
                        foreach ($groups as $productName => $rows):
                            $rowspan = count($rows);
                            $first   = true;
                            $subTotalPcs = 0;
                            foreach ($rows as $r) {
                                $subTotalPcs += (int)$r['qty'];
                            }
                            $subTotalFormatted = number_format($subTotalPcs, 0, ',', '.');

                            foreach ($rows as $idx => $r):
                                ?>
                                <tr class="detail-body">
                                    <?php if ($first): ?>
                                        <td class="text-center" rowspan="<?= $rowspan; ?>"><?= $no; ?></td>
                                        <td rowspan="<?= $rowspan; ?>">
                                            <?= htmlspecialchars($productName); ?>
                                        </td>
                                    <?php endif; ?>

                                    <td class="text-center"><?= htmlspecialchars($r['batch_number']); ?></td>
                                    <td class="text-right"><?= (int)$r['qty']; ?></td>
                                    <td class="text-center"><?= htmlspecialchars($r['unit']); ?></td>
                                    <td class="text-center">1</td>
                                    <td class="text-center">COLLY</td>
                                    <td class="text-right">
                                        <?= number_format((int)$r['qty'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- Keterangan: diambil dari catatan (notes) header Surat Jalan = input manual di barang_keluar.php -->
                                        <?= htmlspecialchars($sj['notes'] ?? ''); ?>
                                    </td>
                                </tr>
                                <?php
                                $first = false;
                            endforeach;
                            $no++;
                        endforeach;
                        ?>

                        <!-- BARIS TOTAL DI BAWAH -->
                        <tr class="detail-body text-bold">
                            <td colspan="3" class="text-center">TOTAL</td>
                            <td class="text-right"><?= $totalPcs; ?></td>
                            <td class="text-center">PCS</td>
                            <td class="text-right"><?= $totalColly; ?></td>
                            <td class="text-center">COLLY</td>
                            <td class="text-right"><?= number_format($totalPcs, 0, ',', '.'); ?></td>
                            <td class="text-center"></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </td>
        </tr>

        <!-- FOOTER TTD -->
        <tr>
            <td colspan="3">
                <table width="100%" class="footer-ttd">
                    <tr>
                        <td style="width:25%;"></td>
                        <td style="width:25%;"></td>
                        <td style="width:25%;"></td>
                        <td style="width:25%; text-align: right;">
                            <?= htmlspecialchars($company['city_line']); ?>, <?= $todayPrint; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Disiapkan Oleh,</td>
                        <td>Dicek Kembali Oleh,</td>
                        <td>Penerima,</td>
                        <td>Disetujui Oleh,</td>
                    </tr>
                    <tr>
                        <td class="space"></td>
                        <td class="space"></td>
                        <td class="space"></td>
                        <td class="space"></td>
                    </tr>
                    <tr>
                        <td>Gudang</td>
                        <td>Security</td>
                        <td>Customer/Ekspedisi</td>
                        <td>Office</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="font-size:9px; padding-top:5px;">
                            <i>Dicetak oleh: <?= htmlspecialchars($printedBy); ?> pada <?= date('d/m/Y H:i'); ?></i>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>
</div>

</body>
</html>
