<?php
require_once 'config.php';

$productId   = $_GET['product_id'] ?? null;
$productName = $_GET['name'] ?? '';

$pageTitle = 'Form Registrasi Item';
include 'layout/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId   = $_POST['product_id'];
    $productName = $_POST['product_name'];
    $storage     = $_POST['storage_location'];
    $batch       = $_POST['batch_number'];

    $stmt = $pdo->prepare("
        INSERT INTO warehouse_items (api_product_id, product_name, storage_location, batch_number)
        VALUES (:pid, :name, :storage, :batch)
    ");
    $stmt->execute([
        ':pid'     => $productId,
        ':name'    => $productName,
        ':storage' => $storage,
        ':batch'   => $batch,
    ]);

    header('Location: registrasi_item.php?success=1');
    exit;
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card card-elevated">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Registrasi Item</h5>
                    <a href="registrasi_item.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                </div>

                <form method="post">
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($productId); ?>">

                    <div class="mb-3">
                        <label class="form-label">Product (dari API)</label>
                        <input type="text" class="form-control" name="product_name"
                               value="<?= htmlspecialchars($productName); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Storage (Lokasi Rak)</label>
                        <input type="text" class="form-control" name="storage_location" required
                               placeholder="Contoh: RACK-A1 / GUDANG-UTAMA">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">No Bets / Batch</label>
                        <input type="text" class="form-control" name="batch_number"
                               placeholder="Contoh: BATCH-001">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include 'layout/footer.php';
