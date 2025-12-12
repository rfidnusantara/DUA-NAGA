<?php
// audit_barang.php
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Dompdf\Dompdf; // Untuk export PDF (butuh library Dompdf, lihat catatan di bawah)

$pageTitle = 'Stock Opname / Audit';

// ==========================
// 1. Ambil data filter dari GET
// ==========================
$startDate    = $_GET['start_date']   ?? '';
$endDate      = $_GET['end_date']     ?? '';
$filterWh     = $_GET['warehouse_id'] ?? '';
$filterUser   = $_GET['created_by']   ?? '';

// ==========================
// 2. Ambil data Gudang & User untuk Filter
// ==========================
$whStmt = $pdo->query("SELECT id, name FROM warehouses ORDER BY name ASC");
$warehouses = $whStmt->fetchAll(PDO::FETCH_ASSOC);

$userStmt = $pdo->query("SELECT username, full_name FROM users ORDER BY full_name ASC");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// 3. Build WHERE clause (dipakai untuk LIST & EXPORT)
// ==========================
$where  = "WHERE 1=1";
$params = [];

if ($startDate !== '') {
    $where .= " AND sm.movement_time >= :start_date";
    $params[':start_date'] = $startDate . ' 00:00:00';
}
if ($endDate !== '') {
    $where .= " AND sm.movement_time <= :end_date";
    $params[':end_date'] = $endDate . ' 23:59:59';
}
if ($filterWh !== '') {
    $where .= " AND sm.warehouse_id = :wh_id";
    $params[':wh_id'] = (int)$filterWh;
}
if ($filterUser !== '') {
    $where .= " AND sm.created_by = :created_by";
    $params[':created_by'] = $filterUser;
}

// ==========================
// 4. Handle EXPORT (Excel / Word / PDF)
// ==========================
if (isset($_GET['export'])) {
    $format = strtolower($_GET['export']);

    // Query tanpa LIMIT untuk export full data berdasarkan filter
    $sqlExport = "
        SELECT
            sm.*,
            w.name AS warehouse_name,
            rr.product_name,
            rr.po_number,
            rr.so_number,
            rr.name_label,
            u.full_name AS user_full_name,
            u.username  AS user_username
        FROM stock_movements sm
        LEFT JOIN warehouses w
            ON sm.warehouse_id = w.id
        LEFT JOIN rfid_registrations rr
            ON sm.registration_id = rr.id
        LEFT JOIN users u
            ON sm.created_by = u.username
        $where
        ORDER BY sm.movement_time DESC, sm.id DESC
    ";
    $stmtExport = $pdo->prepare($sqlExport);
    $stmtExport->execute($params);
    $rowsExport = $stmtExport->fetchAll(PDO::FETCH_ASSOC);

    // Siapkan HTML tabel (dipakai Excel, Word, PDF)
    ob_start();
    ?>
    <h3>Audit Pergerakan Barang (IN/OUT)</h3>
    <p>
        Periode:
        <?= $startDate ? htmlspecialchars($startDate) : '-' ?>
        s/d
        <?= $endDate ? htmlspecialchars($endDate) : '-' ?>
    </p>
    <table border="1" cellspacing="0" cellpadding="5">
        <thead>
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Waktu</th>
            <th>Gudang</th>
            <th>Status</th>
            <th>Produk</th>
            <th>PO</th>
            <th>SO</th>
            <th>Label/Nama</th>
            <th>RFID Tag</th>
            <th>User</th>
            <th>Catatan</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rowsExport)): ?>
            <tr>
                <td colspan="12">Tidak ada data.</td>
            </tr>
        <?php else: ?>
            <?php $no = 1; ?>
            <?php foreach ($rowsExport as $r): 
                $dateObj = new DateTime($r['movement_time']);
                $tgl = $dateObj->format('d/m/Y');
                $jam = $dateObj->format('H:i');
                $userName = !empty($r['user_full_name']) ? $r['user_full_name'] : ($r['created_by'] ?? 'System');
                $statusText = ($r['movement_type'] === 'IN') ? 'IN - Barang Masuk' : 'OUT - Barang Keluar';
            ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td><?= $tgl; ?></td>
                    <td><?= $jam; ?></td>
                    <td><?= htmlspecialchars($r['warehouse_name']); ?></td>
                    <td><?= htmlspecialchars($statusText); ?></td>
                    <td><?= htmlspecialchars($r['product_name']); ?></td>
                    <td><?= htmlspecialchars($r['po_number']); ?></td>
                    <td><?= htmlspecialchars($r['so_number']); ?></td>
                    <td><?= htmlspecialchars($r['name_label']); ?></td>
                    <td><?= htmlspecialchars($r['rfid_tag']); ?></td>
                    <td><?= htmlspecialchars($userName); ?></td>
                    <td><?= htmlspecialchars($r['notes'] ?? '-'); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
    $htmlExport = ob_get_clean();

    $filenameBase = 'audit_pergerakan_' . date('Ymd_His');

    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment; filename=\"{$filenameBase}.xls\"");
        echo $htmlExport;
        exit;
    } elseif ($format === 'word') {
        header('Content-Type: application/msword');
        header("Content-Disposition: attachment; filename=\"{$filenameBase}.doc\"");
        echo $htmlExport;
        exit;
    } elseif ($format === 'pdf') {
        // Butuh library Dompdf (composer require dompdf/dompdf)
        if (class_exists(Dompdf::class)) {
            $dompdf = new Dompdf();
            $dompdf->loadHtml($htmlExport);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $dompdf->stream($filenameBase . '.pdf', ['Attachment' => true]);
            exit;
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo "<p><strong>Export PDF gagal:</strong> Library <code>Dompdf</code> belum terpasang.</p>";
            echo "<p>Silakan install dengan: <code>composer require dompdf/dompdf</code>, lalu coba lagi.</p>";
            echo $htmlExport;
            exit;
        }
    } else {
        // Format tak dikenal
        header('Content-Type: text/plain; charset=utf-8');
        echo "Format export tidak dikenal.";
        exit;
    }
}

// ==========================
// 5. Ambil data untuk tampilan (dibatasi 200)
// ==========================
$sqlList = "
    SELECT
        sm.*,
        w.name AS warehouse_name,
        rr.product_name,
        rr.po_number,
        rr.so_number,
        rr.name_label,
        u.full_name AS user_full_name,
        u.username  AS user_username
    FROM stock_movements sm
    LEFT JOIN warehouses w
        ON sm.warehouse_id = w.id
    LEFT JOIN rfid_registrations rr
        ON sm.registration_id = rr.id
    LEFT JOIN users u
        ON sm.created_by = u.username
    $where
    ORDER BY sm.movement_time DESC, sm.id DESC
    LIMIT 200
";
$stmt = $pdo->prepare($sqlList);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// untuk generate URL export (bawa semua filter)
$currentQuery = $_GET;
unset($currentQuery['export']); // buang param export kalau ada

$excelQuery = $currentQuery;
$excelQuery['export'] = 'excel';

$wordQuery = $currentQuery;
$wordQuery['export'] = 'word';

$pdfQuery = $currentQuery;
$pdfQuery['export'] = 'pdf';

include 'layout/header.php';
?>

<style>
    .avatar-initial {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #e9ecef;
        color: #495057;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
        margin-right: 8px;
    }
    .table-modern th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        color: #8898aa;
        background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    .table-modern td {
        vertical-align: middle;
        padding: 1rem 0.75rem;
        border-bottom: 1px solid #f1f3f9;
        font-size: 0.9rem;
    }
    .badge-soft-success {
        background-color: rgba(45, 206, 137, 0.1);
        color: #2dce89;
    }
    .badge-soft-danger {
        background-color: rgba(245, 54, 92, 0.1);
        color: #f5365c;
    }
    .search-box {
        position: relative;
    }
    .search-box i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
    }
    .search-box input {
        padding-left: 40px;
        border-radius: 20px;
    }
</style>

<div class="row">
    <div class="col-12">

        <!-- FILTER BAR -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
                <form class="row g-2 align-items-end" method="get">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Tanggal Mulai</label>
                        <input type="date"
                               name="start_date"
                               class="form-control form-control-sm"
                               value="<?= htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Tanggal Selesai</label>
                        <input type="date"
                               name="end_date"
                               class="form-control form-control-sm"
                               value="<?= htmlspecialchars($endDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Gudang</label>
                        <select name="warehouse_id" class="form-select form-select-sm">
                            <option value="">Semua Gudang</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= (int)$wh['id']; ?>"
                                    <?= ($filterWh !== '' && (int)$filterWh === (int)$wh['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($wh['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">User</label>
                        <select name="created_by" class="form-select form-select-sm">
                            <option value="">Semua User</option>
                            <?php foreach ($users as $u): 
                                $label = $u['full_name'] ?: $u['username'];
                            ?>
                                <option value="<?= htmlspecialchars($u['username']); ?>"
                                    <?= ($filterUser !== '' && $filterUser === $u['username']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mt-2">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i> Terapkan Filter
                            </button>
                            <a href="audit_barang.php" class="btn btn-outline-secondary btn-sm">
                                Reset
                            </a>
                        </div>
                    </div>

                    <div class="col-md-4 mt-2">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Cari di tabel (PO, RFID, User...)">
                        </div>
                    </div>

                    <div class="col-md-4 mt-2 text-md-end">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-download me-1"></i> Export
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="?<?= htmlspecialchars(http_build_query($excelQuery)); ?>">
                                    <i class="bi bi-file-earmark-excel me-1 text-success"></i> Excel
                                </a></li>
                                <li><a class="dropdown-item" href="?<?= htmlspecialchars(http_build_query($wordQuery)); ?>">
                                    <i class="bi bi-file-earmark-word me-1 text-primary"></i> Word
                                </a></li>
                                <li><a class="dropdown-item" href="?<?= htmlspecialchars(http_build_query($pdfQuery)); ?>">
                                    <i class="bi bi-file-earmark-pdf me-1 text-danger"></i> PDF
                                </a></li>
                            </ul>
                        </div>
                        <button class="btn btn-light border btn-sm ms-1" onclick="location.reload()" title="Refresh Data">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- HEADER INFO -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="mb-1 fw-bold text-primary">Audit Log Pergerakan</h4>
                    <p class="text-muted mb-0 small">
                        Monitoring real-time aktivitas <strong>Masuk/Keluar</strong> barang via RFID.<br>
                        Periode:
                        <strong><?= $startDate ? htmlspecialchars($startDate) : 'Semua'; ?></strong>
                        s/d
                        <strong><?= $endDate ? htmlspecialchars($endDate) : 'Semua'; ?></strong>
                    </p>
                </div>
            </div>
        </div>

        <!-- TABEL AUDIT -->
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover table-modern mb-0" id="auditTable">
                    <thead>
                        <tr>
                            <th class="ps-4" width="5%">No</th>
                            <th width="15%">Waktu & Gudang</th>
                            <th width="10%">Status</th>
                            <th width="30%">Detail Barang</th>
                            <th width="15%">RFID Tag</th>
                            <th width="15%">User</th>
                            <th class="pe-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-clipboard-x fs-1 d-block mb-2 opacity-25"></i>
                                    <div class="text-muted fw-bold">Tidak ada aktivitas sesuai filter.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                // Format Waktu
                                $dateObj = new DateTime($row['movement_time']);
                                $dateStr = $dateObj->format('d M Y');
                                $timeStr = $dateObj->format('H:i');

                                // User Display
                                $userName = !empty($row['user_full_name']) ? $row['user_full_name'] : ($row['created_by'] ?? 'System');
                                $initial  = strtoupper(substr($userName, 0, 1));
                                
                                // Warna Badge
                                $isMasuk = ($row['movement_type'] === 'IN');
                                $badgeClass = $isMasuk ? 'badge-soft-success' : 'badge-soft-danger';
                                $badgeIcon  = $isMasuk ? 'bi-arrow-down-circle-fill' : 'bi-arrow-up-circle-fill';
                                $badgeText  = $isMasuk ? 'Barang Masuk' : 'Barang Keluar';
                                ?>
                                <tr>
                                    <td class="ps-4 text-muted"><?= $no++; ?></td>
                                    
                                    <td>
                                        <div class="fw-bold text-dark"><?= $dateStr; ?></div>
                                        <div class="small text-muted mb-1"><?= $timeStr; ?> WIB</div>
                                        <div class="badge bg-light text-dark border">
                                            <i class="bi bi-building me-1"></i> <?= htmlspecialchars($row['warehouse_name']); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge <?= $badgeClass; ?> p-2 rounded-pill d-flex align-items-center justify-content-center gap-1" style="width: fit-content;">
                                            <i class="bi <?= $badgeIcon; ?>"></i> <?= $isMasuk ? 'IN' : 'OUT'; ?>
                                        </span>
                                        <small class="d-block text-muted" style="font-size: 0.7rem;"><?= $badgeText; ?></small>
                                    </td>

                                    <td>
                                        <div class="fw-bold text-primary mb-1">
                                            <?= htmlspecialchars($row['product_name']); ?>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10">
                                                PO: <?= htmlspecialchars($row['po_number']); ?>
                                            </span>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10">
                                                SO: <?= htmlspecialchars($row['so_number']); ?>
                                            </span>
                                        </div>
                                        <?php if(!empty($row['name_label'])): ?>
                                            <small class="text-muted d-block fst-italic">
                                                <i class="bi bi-tag-fill me-1 text-warning"></i> 
                                                <?= htmlspecialchars($row['name_label']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="font-monospace bg-light text-dark px-2 py-1 rounded border small">
                                            <?= htmlspecialchars($row['rfid_tag']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initial">
                                                <?= $initial; ?>
                                            </div>
                                            <div style="line-height: 1.2;">
                                                <div class="fw-bold small text-dark"><?= htmlspecialchars($userName); ?></div>
                                                <small class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($row['created_by'] ?? '-'); ?></small>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="pe-4 text-muted small">
                                        <?= htmlspecialchars($row['notes'] ?? '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Menampilkan <?= count($rows); ?> data terbaru
                    <?= ($startDate || $endDate || $filterWh || $filterUser) ? 'sesuai filter.' : 'terakhir.'; ?>
                </small>
            </div>
        </div>

    </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toUpperCase();
    let table  = document.getElementById('auditTable');
    let tr     = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        let textContent = tr[i].textContent || tr[i].innerText;
        if (textContent.toUpperCase().indexOf(filter) > -1) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
});
</script>

<?php
include 'layout/footer.php';
?>
