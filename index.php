<?php
// index.php
require_once 'functions.php';

$pageTitle = 'Dashboard Utama';

// ==========================
// 0. FILTER GUDANG (GET)
// ==========================
$selectedWarehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;

// ==========================
// 0a. JUMLAH DASAR
// ==========================

// Jumlah tag aktif & non-aktif
$activeTags = (int)$pdo->query("
    SELECT COUNT(*) FROM rfid_registrations WHERE is_active = 1
")->fetchColumn();

$inactiveTags = (int)$pdo->query("
    SELECT COUNT(*) FROM rfid_registrations WHERE is_active = 0
")->fetchColumn();

// Total registrasi (aktif + tidak aktif)
$totalRegistrations = (int)$pdo->query("
    SELECT COUNT(*) FROM rfid_registrations
")->fetchColumn();

// Daftar gudang
$warehousesStmt = $pdo->query("SELECT id, name, code FROM warehouses ORDER BY name ASC");
$warehouses = $warehousesStmt->fetchAll(PDO::FETCH_ASSOC);

// Jumlah gudang (global)
$totalWarehouses = count($warehouses);

// ==========================
// 1. SUBQUERY: LAST MOVEMENT PER RFID
// ==========================
$lastMovementSubquery = "
    SELECT sm.*
    FROM stock_movements sm
    INNER JOIN (
        SELECT rfid_tag, MAX(id) AS max_id
        FROM stock_movements
        GROUP BY rfid_tag
    ) x ON sm.rfid_tag = x.rfid_tag AND sm.id = x.max_id
";

// ==========================
// 2. KPI UTAMA
// ==========================

// 2a. Stok sekarang berdasarkan tag aktif (is_active = 1)
// - Jika filter gudang dipilih -> hitung stok untuk gudang tsb (pakai last movement)
// - Jika tidak -> stok total semua gudang
if ($selectedWarehouseId > 0) {
    $currentStockStmt = $pdo->prepare("
        SELECT COALESCE(SUM(rr.pcs), 0)
        FROM rfid_registrations rr
        LEFT JOIN (
            $lastMovementSubquery
        ) lm ON lm.rfid_tag = rr.rfid_tag
        WHERE rr.is_active = 1
          AND (lm.movement_type = 'IN' OR lm.movement_type IS NULL)
          AND lm.warehouse_id = :wh_id
    ");
    $currentStockStmt->execute([':wh_id' => $selectedWarehouseId]);
    $currentStock = (int)$currentStockStmt->fetchColumn();
} else {
    $currentStock = (int)$pdo->query("
        SELECT COALESCE(SUM(pcs), 0)
        FROM rfid_registrations
        WHERE is_active = 1
    ")->fetchColumn();
}

// 2b. Total pergerakan (IN + OUT)
if ($selectedWarehouseId > 0) {
    $totalMovementsStmt = $pdo->prepare("
        SELECT COUNT(*) FROM stock_movements WHERE warehouse_id = :wh_id
    ");
    $totalMovementsStmt->execute([':wh_id' => $selectedWarehouseId]);
    $totalMovements = (int)$totalMovementsStmt->fetchColumn();
} else {
    $totalMovements = (int)$pdo->query("
        SELECT COUNT(*) FROM stock_movements
    ")->fetchColumn();
}

// ==========================
// 3. STOK PER GUDANG (PIE CHART)
// ==========================
// Hanya tag aktif + last movement IN / belum pernah bergerak
if ($selectedWarehouseId > 0) {
    $stockQueryStmt = $pdo->prepare("
        SELECT
            COALESCE(w.name, 'Belum Ada Gudang') AS warehouse_name,
            COALESCE(SUM(rr.pcs), 0) AS total_qty
        FROM rfid_registrations rr
        LEFT JOIN (
            $lastMovementSubquery
        ) lm ON lm.rfid_tag = rr.rfid_tag
        LEFT JOIN warehouses w ON lm.warehouse_id = w.id
        WHERE rr.is_active = 1
          AND (lm.movement_type = 'IN' OR lm.movement_type IS NULL)
          AND lm.warehouse_id = :wh_id
        GROUP BY w.id, w.name
        HAVING total_qty > 0
        ORDER BY total_qty DESC
    ");
    $stockQueryStmt->execute([':wh_id' => $selectedWarehouseId]);
    $whStockData = $stockQueryStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stockQuery = $pdo->query("
        SELECT
            COALESCE(w.name, 'Belum Ada Gudang') AS warehouse_name,
            COALESCE(SUM(rr.pcs), 0) AS total_qty
        FROM rfid_registrations rr
        LEFT JOIN (
            $lastMovementSubquery
        ) lm ON lm.rfid_tag = rr.rfid_tag
        LEFT JOIN warehouses w ON lm.warehouse_id = w.id
        WHERE rr.is_active = 1
          AND (lm.movement_type = 'IN' OR lm.movement_type IS NULL)
        GROUP BY w.id, w.name
        HAVING total_qty > 0
        ORDER BY total_qty DESC
    ");
    $whStockData = $stockQuery->fetchAll(PDO::FETCH_ASSOC);
}

$chartWhLabels = [];
$chartWhData   = [];
foreach ($whStockData as $d) {
    $chartWhLabels[] = $d['warehouse_name'];
    $chartWhData[]   = (int)$d['total_qty'];
}

// ==========================
// 4. TREN 7 HARI TERAKHIR (CHART BAR)
// ==========================
if ($selectedWarehouseId > 0) {
    $trendQueryStmt = $pdo->prepare("
        SELECT 
            DATE(movement_time) AS m_date,
            SUM(CASE WHEN movement_type = 'IN'  THEN 1 ELSE 0 END) AS total_in,
            SUM(CASE WHEN movement_type = 'OUT' THEN 1 ELSE 0 END) AS total_out
        FROM stock_movements
        WHERE movement_time >= DATE(NOW()) - INTERVAL 6 DAY
          AND warehouse_id = :wh_id
        GROUP BY DATE(movement_time)
        ORDER BY m_date ASC
    ");
    $trendQueryStmt->execute([':wh_id' => $selectedWarehouseId]);
    $trendData = $trendQueryStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $trendQuery = $pdo->query("
        SELECT 
            DATE(movement_time) AS m_date,
            SUM(CASE WHEN movement_type = 'IN'  THEN 1 ELSE 0 END) AS total_in,
            SUM(CASE WHEN movement_type = 'OUT' THEN 1 ELSE 0 END) AS total_out
        FROM stock_movements
        WHERE movement_time >= DATE(NOW()) - INTERVAL 6 DAY
        GROUP BY DATE(movement_time)
        ORDER BY m_date ASC
    ");
    $trendData = $trendQuery->fetchAll(PDO::FETCH_ASSOC);
}

$chartDays = [];
$chartIn   = [];
$chartOut  = [];
foreach ($trendData as $t) {
    $chartDays[] = date('d M', strtotime($t['m_date']));
    $chartIn[]   = (int)$t['total_in'];
    $chartOut[]  = (int)$t['total_out'];
}

// ==========================
// 5. TOP STOK PER PRODUK (untuk tabel di kartu)
// ==========================
if ($selectedWarehouseId > 0) {
    $stockPerProductStmt = $pdo->prepare("
        SELECT
            COALESCE(w.name, 'Belum Ada Gudang') AS warehouse_name,
            COALESCE(rr.product_name, 'Unknown') AS product_name,
            COALESCE(rr.po_number, '-') AS po_number,
            COALESCE(SUM(rr.pcs), 0) AS qty
        FROM rfid_registrations rr
        LEFT JOIN (
            $lastMovementSubquery
        ) lm ON lm.rfid_tag = rr.rfid_tag
        LEFT JOIN warehouses w ON lm.warehouse_id = w.id
        WHERE rr.is_active = 1
          AND (lm.movement_type = 'IN' OR lm.movement_type IS NULL)
          AND lm.warehouse_id = :wh_id
        GROUP BY w.id, w.name, rr.product_name, rr.po_number
        HAVING qty > 0
        ORDER BY qty DESC
        LIMIT 10
    ");
    $stockPerProductStmt->execute([':wh_id' => $selectedWarehouseId]);
    $topProducts = $stockPerProductStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stockPerProductStmt = $pdo->query("
        SELECT
            COALESCE(w.name, 'Belum Ada Gudang') AS warehouse_name,
            COALESCE(rr.product_name, 'Unknown') AS product_name,
            COALESCE(rr.po_number, '-') AS po_number,
            COALESCE(SUM(rr.pcs), 0) AS qty
        FROM rfid_registrations rr
        LEFT JOIN (
            $lastMovementSubquery
        ) lm ON lm.rfid_tag = rr.rfid_tag
        LEFT JOIN warehouses w ON lm.warehouse_id = w.id
        WHERE rr.is_active = 1
          AND (lm.movement_type = 'IN' OR lm.movement_type IS NULL)
        GROUP BY w.id, w.name, rr.product_name, rr.po_number
        HAVING qty > 0
        ORDER BY qty DESC
        LIMIT 10
    ");
    $topProducts = $stockPerProductStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==========================
// 6. DETAIL ITEM AKTIF (UNTUK MODAL DETAIL)
// ==========================
if ($selectedWarehouseId > 0) {
    $detailItemsStmt = $pdo->prepare("
        SELECT
            COALESCE(w.name, 'Belum Ada Gudang') AS warehouse_name,
            rr.po_number,
            rr.so_number,
            rr.product_name,
            rr.batch_number,
            rr.pcs,
            rr.rfid_tag,
            rr.name_label
        FROM rfid_registrations rr
        LEFT JOIN (
            $lastMovementSubquery
        ) lm ON lm.rfid_tag = rr.rfid_tag
        LEFT JOIN warehouses w ON lm.warehouse_id = w.id
        WHERE rr.is_active = 1
          AND (lm.movement_type = 'IN' OR lm.movement_type IS NULL)
          AND lm.warehouse_id = :wh_id
        ORDER BY w.name, rr.product_name, rr.batch_number, rr.rfid_tag
    ");
    $detailItemsStmt->execute([':wh_id' => $selectedWarehouseId]);
    $detailItems = $detailItemsStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $detailItemsStmt = $pdo->query("
        SELECT
            COALESCE(w.name, 'Belum Ada Gudang') AS warehouse_name,
            rr.po_number,
            rr.so_number,
            rr.product_name,
            rr.batch_number,
            rr.pcs,
            rr.rfid_tag,
            rr.name_label
        FROM rfid_registrations rr
        LEFT JOIN (
            $lastMovementSubquery
        ) lm ON lm.rfid_tag = rr.rfid_tag
        LEFT JOIN warehouses w ON lm.warehouse_id = w.id
        WHERE rr.is_active = 1
          AND (lm.movement_type = 'IN' OR lm.movement_type IS NULL)
        ORDER BY w.name, rr.product_name, rr.batch_number, rr.rfid_tag
    ");
    $detailItems = $detailItemsStmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .stat-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        transition: transform 0.2s;
        overflow: hidden;
        position: relative;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display:flex; align-items:center; justify-content:center;
        font-size:1.5rem;
    }
    .bg-icon-primary { background: rgba(13,110,253,0.1); color:#0d6efd; }
    .bg-icon-success { background: rgba(25,135,84,0.1);  color:#198754; }
    .bg-icon-warning { background: rgba(255,193,7,0.1);  color:#ffc107; }
    .bg-icon-info    { background: rgba(13,202,240,0.1); color:#0dcaf0; }

    .progress-thin {
        height:6px;
        border-radius:3px;
        background-color:#e9ecef;
        margin-top:5px;
    }
</style>

<!-- ====================== -->
<!--  FILTER GUDANG         -->
<!-- ====================== -->
<div class="mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4 col-lg-3">
            <label class="form-label small text-muted fw-bold text-uppercase">
                Filter Gudang
            </label>
            <select name="warehouse_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">-- Semua Perusahaan --</option>
                <?php foreach ($warehouses as $g): ?>
                    <option value="<?= (int)$g['id']; ?>"
                        <?= ($selectedWarehouseId === (int)$g['id']) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($g['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-auto">
            <small class="text-muted">
                Tampilan stok dan detail di bawah akan mengikuti Perusahaan yang dipilih.
            </small>
        </div>
    </form>
</div>

<!-- ====================== -->
<!--  KPI SECTION           -->
<!-- ====================== -->
<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1">Overview Perusahaan</h4>
    <small class="text-muted d-block mb-3">
        Stok dihitung berdasarkan <strong>tag aktif</strong> (is_active=1). Tag yang sudah barang keluar (OUT) tidak masuk stok.
        <?php if ($selectedWarehouseId > 0): ?>
            <br><span class="text-primary">Filter: hanya Perusahaan yang dipilih.</span>
        <?php endif; ?>
    </small>

    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-icon-primary me-3">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold"><?= number_format($currentStock); ?></h5>
                        <small class="text-muted">Stok Aktif (Pcs)</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-icon-warning me-3">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold"><?= number_format($totalMovements); ?></h5>
                        <small class="text-muted">Total Aktivitas IN/OUT<?= $selectedWarehouseId > 0 ? ' (Gudang ini)' : ''; ?></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-icon-success me-3">
                        <i class="bi bi-building"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold"><?= number_format($totalWarehouses); ?></h5>
                        <small class="text-muted">Jumlah Perusahaan (Total)</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-icon-info me-3">
                        <i class="bi bi-tags"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold"><?= number_format($activeTags); ?></h5>
                        <small class="text-muted">
                            Tag Aktif (Global)
                            <span class="d-block" style="font-size:0.7rem;">
                                Total: <?= number_format($totalRegistrations); ?> (Nonaktif: <?= number_format($inactiveTags); ?>)
                            </span>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================== -->
<!--  CHART SECTION         -->
<!-- ====================== -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom-0 py-3">
                <h6 class="m-0 fw-bold">
                    <i class="bi bi-graph-up me-2 text-primary"></i>Aktivitas 7 Hari Terakhir
                    <?php if ($selectedWarehouseId > 0): ?>
                        <span class="text-muted" style="font-size:0.8rem;">(Perusahaan terpilih)</span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($chartDays)): ?>
                    <div class="text-center text-muted py-4 small">
                        Belum ada pergerakan barang dalam 7 hari terakhir
                        <?= $selectedWarehouseId > 0 ? ' untuk gudang ini.' : '.'; ?>
                    </div>
                <?php endif; ?>
                <canvas id="movementChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom-0 py-3">
                <h6 class="m-0 fw-bold">
                    <i class="bi bi-pie-chart me-2 text-primary"></i>Sebaran Stok per Perusahaan
                    <?php if ($selectedWarehouseId > 0): ?>
                        <span class="text-muted" style="font-size:0.8rem;">(Hanya Perusahaan terpilih)</span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <?php if (empty($chartWhData)): ?>
                    <div class="text-center text-muted small">
                        Belum ada data stok per Perusahaan
                        <?= $selectedWarehouseId > 0 ? ' untuk gudang ini.' : '(tidak ada tag aktif).'; ?>
                    </div>
                <?php else: ?>
                    <div style="width: 250px; height: 250px;">
                        <canvas id="warehouseChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ====================== -->
<!--  TOP PRODUCT (FULL WIDTH) -->
<!-- ====================== -->
<div class="row g-3 mb-4">
    <div class="col-lg-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold">
                    Top Stok Produk (Tag Aktif)
                    <?php if ($selectedWarehouseId > 0): ?>
                        <span class="text-muted" style="font-size:0.8rem;">- Perusahaan terpilih</span>
                    <?php endif; ?>
                </h6>
                <!-- BUTTON DETAIL (BUKA MODAL) -->
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#detailStokModal">
                    Detail
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-3">Produk</th>
                            <th>Perusahaan</th>
                            <th>PO</th>
                            <th class="pe-3 text-end">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php if (empty($topProducts)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    Belum ada stok aktif per produk
                                    <?= $selectedWarehouseId > 0 ? ' untuk gudang ini.' : '.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topProducts as $prod): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-dark">
                                        <?= htmlspecialchars($prod['product_name']); ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($prod['warehouse_name']); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($prod['po_number']); ?>
                                        </span>
                                    </td>
                                    <td class="pe-3 text-end">
                                        <div class="fw-bold"><?= number_format($prod['qty']); ?></div>
                                        <div class="progress progress-thin">
                                            <div class="progress-bar bg-primary" role="progressbar"
                                                 style="width: <?= max(10, min(100, $prod['qty'])); ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-0 text-end py-2">
                <span class="small text-muted">
                    Klik tombol <strong>Detail</strong> untuk melihat daftar item per tag.
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ====================== -->
<!--  MODAL DETAIL STOK     -->
<!-- ====================== -->
<div class="modal fade" id="detailStokModal" tabindex="-1" aria-labelledby="detailStokModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailStokModalLabel">
            Detail Stok Aktif per Item
            <?php if ($selectedWarehouseId > 0): ?>
                <span class="text-muted" style="font-size:0.8rem;">(Perusahaan terpilih)</span>
            <?php endif; ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr class="small text-uppercase text-muted">
                        <th>Perusahaan</th>
                        <th>PO</th>
                        <th>SO</th>
                        <th>Product Name</th>
                        <th>Detail Item</th>
                        <th>No Batch</th>
                        <th class="text-end">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($detailItems)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">
                                Tidak ada item aktif dalam stok
                                <?= $selectedWarehouseId > 0 ? ' untuk gudang ini.' : '.'; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($detailItems as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['warehouse_name']); ?></td>
                                <td><?= htmlspecialchars($row['po_number'] ?: '-'); ?></td>
                                <td><?= htmlspecialchars($row['so_number'] ?: '-'); ?></td>
                                <td><?= htmlspecialchars($row['product_name']); ?></td>
                                <td>
                                    <div class="font-monospace small">
                                        <?= htmlspecialchars($row['rfid_tag']); ?>
                                    </div>
                                    <?php if (!empty($row['name_label'])): ?>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($row['name_label']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['batch_number'] ?: '-'); ?></td>
                                <td class="text-end"><?= (int)$row['pcs']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer">
        <small class="text-muted me-auto">
            Data hanya menampilkan <strong>tag aktif</strong> dengan pergerakan terakhir <strong>IN</strong> / belum pernah keluar.
        </small>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
    // PIE CHART GUDANG
    (function() {
        const ctxWh = document.getElementById('warehouseChart');
        if (!ctxWh) return;
        const labels = <?= json_encode($chartWhLabels); ?>;
        const data   = <?= json_encode($chartWhData); ?>;
        if (!labels.length) return;

        new Chart(ctxWh, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: ['#435ebe','#57caeb','#ff7976','#5ddab4','#ffc107'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 8 }
                    }
                }
            }
        });
    })();

    // BAR CHART MOVEMENT
    (function() {
        const ctxMove = document.getElementById('movementChart');
        if (!ctxMove) return;
        const labels = <?= json_encode($chartDays); ?>;
        const inData = <?= json_encode($chartIn); ?>;
        const outData= <?= json_encode($chartOut); ?>;
        if (!labels.length) return;

        new Chart(ctxMove, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Barang Masuk',
                        data: inData,
                        backgroundColor: '#5ddab4',
                        borderRadius: 4
                    },
                    {
                        label: 'Barang Keluar',
                        data: outData,
                        backgroundColor: '#ff7976',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top', align: 'end' }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { borderDash: [2,4] } }
                }
            }
        });
    })();
</script>

<?php
include 'layout/footer.php';
?>
