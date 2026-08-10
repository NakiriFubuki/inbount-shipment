<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDB();
ensureQuantityColumns($pdo);
ensureRemarksColumn($pdo);
$activeNav = 'admin';
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*, u.username AS admin_name FROM inbound_shipments s
     JOIN users u ON s.created_by = u.id WHERE s.id = ?'
);
$stmt->execute([$id]);
$shipment = $stmt->fetch();

if (!$shipment) {
    flash('error', __('msg.invalid_action'));
    header('Location: ' . baseUrl('admin/dashboard.php'));
    exit;
}

$progress = getShipmentProgress($pdo, $shipment);
$pageTitle = __('admin.shipment_detail');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= e(__('admin.shipment_detail')) ?></h1>
    <p>
        <a href="<?= baseUrl('admin/dashboard.php') ?>" class="btn btn-outline btn-sm"><?= e(__('admin.back_dashboard')) ?></a>
        <a href="<?= baseUrl('admin/dashboard.php?edit=' . $id) ?>" class="btn btn-warning btn-sm"><?= e(__('common.edit')) ?></a>
    </p>
</div>

<div class="detail-grid">
    <div class="card">
        <div class="card-header"><h2><?= e(__('admin.product_name')) ?></h2></div>
        <div class="detail-body">
            <dl class="detail-list">
                <dt><?= e(__('admin.status')) ?></dt>
                <dd><?= renderShipmentStatusDisplay($progress) ?></dd>
                <dt><?= e(__('admin.inbound_shipment_no')) ?></dt>
                <dd><strong><?= e($shipment['shipment_number']) ?></strong></dd>
                <dt><?= e(__('admin.product_name')) ?></dt>
                <dd><?= e($shipment['product_name']) ?></dd>
                <dt><?= e(__('admin.inbound_date')) ?></dt>
                <dd><?= e($shipment['inbound_date']) ?></dd>
                <dt><?= e(__('admin.cartons')) ?></dt>
                <dd><?= renderBoxQty((int) ($shipment['total_quantity'] ?? 0)) ?></dd>
                <dt><?= e(__('admin.quantity')) ?></dt>
                <dd><?= renderPieceQty((int) ($shipment['quantity'] ?? 0)) ?></dd>
                <dt><?= e(__('admin.counted_cartons')) ?></dt>
                <dd><?= renderBoxQty((int) ($progress['counted_cartons'] ?? 0)) ?></dd>
                <dt><?= e(__('admin.counted_quantity')) ?></dt>
                <dd><?= renderPieceQty((int) ($progress['counted_pieces'] ?? 0)) ?></dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><?= e(__('admin.all_counting_records')) ?></h2>
            <span><?= count($progress['records']) ?></span>
        </div>
        <?php if (empty($progress['records'])): ?>
        <div class="empty-state"><?= e(__('admin.waiting_count')) ?></div>
        <?php else: ?>
        <div class="table-responsive table-shipments-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('admin.counted_by')) ?></th>
                        <th><?= e(__('admin.counting_date')) ?></th>
                        <th><?= e(__('admin.start_time')) ?></th>
                        <th><?= e(__('admin.com_time')) ?></th>
                        <th><?= e(__('admin.counted_cartons')) ?></th>
                        <th><?= e(__('admin.counted_quantity')) ?></th>
                        <th><?= e(__('admin.remarks')) ?></th>
                        <th><?= e(__('admin.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($progress['records'] as $rec): ?>
                    <tr>
                        <td><?= e($rec['counted_by']) ?> (<?= e($rec['counter_username']) ?>)</td>
                        <td><?= e($rec['counting_date']) ?></td>
                        <td><?= e(substr($rec['start_time'], 0, 8)) ?></td>
                        <td><?= e(substr($rec['completion_time'], 0, 8)) ?></td>
                        <td class="td-cartons-edit">
                            <form method="post" action="<?= baseUrl('admin/actions.php') ?>" class="inline-carton-form">
                                <input type="hidden" name="action" value="update_counting_cartons">
                                <input type="hidden" name="counting_id" value="<?= (int) $rec['id'] ?>">
                                <input type="hidden" name="shipment_id" value="<?= (int) $shipment['id'] ?>">
                                <input type="number" name="total_counted" class="form-control form-control-sm carton-input"
                                       min="0" step="1" value="<?= (int) ($rec['total_counted'] ?? 0) ?>">
                                <button type="submit" class="btn btn-outline btn-sm"><?= e(__('common.save')) ?></button>
                            </form>
                        </td>
                        <td><?= renderPieceQty((int) ($rec['quantity_counted'] ?? 0)) ?></td>
                        <td><?= e($rec['remarks'] ?: '-') ?></td>
                        <td class="td-actions">
                            <form method="post" action="<?= baseUrl('admin/actions.php') ?>">
                                <input type="hidden" name="action" value="delete_counting">
                                <input type="hidden" name="counting_id" value="<?= (int) $rec['id'] ?>">
                                <input type="hidden" name="shipment_id" value="<?= (int) $shipment['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="<?= e(__('admin.delete_counting_confirm')) ?>">
                                    <?= e(__('common.delete')) ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
