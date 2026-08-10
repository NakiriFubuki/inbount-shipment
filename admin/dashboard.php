<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDB();
ensureQuantityColumns($pdo);
$activeNav = 'admin';
$pageTitle = __('admin.page_title');

$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? '';
$filterDate = $_GET['date'] ?? date('Y-m-d');
$showForm = isset($_GET['add']) || isset($_GET['edit']);

$sql = 'SELECT s.*, u.username AS admin_name FROM inbound_shipments s
        JOIN users u ON s.created_by = u.id WHERE 1=1';
$params = [];

$unified = buildUnifiedSearch($search);
if ($unified['sql']) {
    $sql .= $unified['sql'];
    $params = array_merge($params, $unified['params']);
}

$dateRange = buildDateFilter($filter, $filterDate);
if ($dateRange) {
    $sql .= ' AND s.inbound_date BETWEEN ? AND ?';
    $params[] = $dateRange['start'];
    $params[] = $dateRange['end'];
}

$sql .= ' ORDER BY s.inbound_date DESC, s.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shipments = $stmt->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$editRecord = null;
if ($editId) {
    $showForm = true;
    foreach ($shipments as $s) {
        if ((int) $s['id'] === $editId) {
            $editRecord = $s;
            break;
        }
    }
    if (!$editRecord) {
        $stmt = $pdo->prepare('SELECT * FROM inbound_shipments WHERE id = ?');
        $stmt->execute([$editId]);
        $editRecord = $stmt->fetch();
    }
}

$queryParams = [];
if ($search !== '') {
    $queryParams['search'] = $search;
}
if ($filter !== '') {
    $queryParams['filter'] = $filter;
    if ($filter === 'daily') {
        $queryParams['date'] = $filterDate;
    }
}
$queryString = http_build_query($queryParams);

$pendingShipments = [];
$pendingStmt = $pdo->query(
    'SELECT s.* FROM inbound_shipments s ORDER BY s.inbound_date DESC, s.created_at DESC'
);
foreach ($pendingStmt->fetchAll() as $ps) {
    if (!shipmentShowsInPendingList($pdo, $ps, false)) {
        continue;
    }
    $ps['_progress'] = getShipmentProgress($pdo, $ps);
    $pendingShipments[] = $ps;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header page-header-row">
    <div>
        <h1><?= e(__('admin.heading')) ?></h1>
    </div>
    <div class="page-header-actions">
        <a href="<?= baseUrl('admin/dashboard.php?add=1') ?>" class="btn btn-primary"><?= e(__('admin.add_new')) ?></a>
        <a href="<?= baseUrl('admin/users.php') ?>" class="btn btn-outline"><?= e(__('nav.user_management')) ?></a>
    </div>
</div>

<!-- Search Product (reference layout) -->
<div class="card card-search">
    <div class="search-section-label"><?= e(__('admin.search_product')) ?></div>
    <form method="get" class="search-product-form">
        <?php if ($filter): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
        <?php if ($filter === 'daily'): ?><input type="hidden" name="date" value="<?= e($filterDate) ?>"><?php endif; ?>
        <input type="text" name="search" class="form-control search-product-input"
               placeholder="<?= e(__('admin.search_product_hint')) ?>"
               value="<?= e($search) ?>">
        <button type="submit" class="btn btn-primary btn-search"><?= e(__('common.search')) ?></button>
        <a href="<?= baseUrl('admin/dashboard.php') ?>" class="btn btn-outline"><?= e(__('admin.reset')) ?></a>
    </form>
    <div class="filter-tabs" style="margin-top: 1rem;">
        <a href="?filter=today" class="filter-tab <?= $filter === 'today' ? 'active' : '' ?>"><?= e(__('admin.filter_today')) ?></a>
        <a href="?filter=daily&date=<?= e($filterDate) ?>" class="filter-tab <?= $filter === 'daily' ? 'active' : '' ?>"><?= e(__('admin.filter_daily')) ?></a>
        <a href="?filter=past7" class="filter-tab <?= $filter === 'past7' ? 'active' : '' ?>"><?= e(__('admin.filter_past7')) ?></a>
        <a href="<?= baseUrl('admin/dashboard.php') ?>" class="filter-tab <?= $filter === '' ? 'active' : '' ?>"><?= e(__('admin.filter_all')) ?></a>
    </div>
</div>

<?php if (!empty($pendingShipments)): ?>
<div class="card card-shipments" id="pending-shipments">
    <div class="card-header">
        <div>
            <h2><?= e(__('admin.pending_shipments')) ?> <span class="badge badge-user"><?= count($pendingShipments) ?></span></h2>
        </div>
    </div>
    <div class="table-responsive table-shipments-wrap">
        <table class="table-shipments">
            <thead>
                <tr>
                    <th><?= e(__('admin.status')) ?></th>
                    <th><?= e(__('admin.inbound_date')) ?></th>
                    <th><?= e(__('admin.product_name')) ?></th>
                    <th><?= e(__('admin.shipment_number')) ?></th>
                    <th><?= e(__('admin.cartons')) ?></th>
                    <th><?= e(__('admin.quantity')) ?></th>
                    <th><?= e(__('admin.counted_cartons')) ?></th>
                    <th><?= e(__('admin.counted_quantity')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingShipments as $ps):
                $pProgress = $ps['_progress'];
                $detailUrl = baseUrl('admin/shipment-detail.php?id=' . (int) $ps['id']);
            ?>
                <tr class="shipment-row" data-href="<?= e($detailUrl) ?>" tabindex="0" role="link">
                    <td><?= renderShipmentStatusDisplay($pProgress) ?></td>
                    <td><?= e($ps['inbound_date']) ?></td>
                    <td><?= e($ps['product_name']) ?></td>
                    <td><strong><?= e($ps['shipment_number']) ?></strong></td>
                    <td><?= renderBoxQty((int) ($ps['total_quantity'] ?? 0)) ?></td>
                    <td><?= renderPieceQty((int) ($ps['quantity'] ?? 0)) ?></td>
                    <td><?= renderBoxQty((int) ($pProgress['counted_cartons'] ?? 0)) ?></td>
                    <td><?= renderPieceQty((int) ($pProgress['counted_pieces'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($showForm): ?>
<div class="card" id="manage-form">
    <div class="card-header">
        <h2><?= $editRecord ? e(__('admin.edit_record')) : e(__('admin.add_record')) ?></h2>
        <a href="<?= baseUrl('admin/dashboard.php') . ($queryString ? '?' . $queryString : '') ?>" class="btn btn-outline btn-sm"><?= e(__('common.cancel_edit')) ?></a>
    </div>
    <form method="post" action="<?= baseUrl('admin/actions.php') ?><?= $queryString ? '?' . $queryString : '' ?>">
        <input type="hidden" name="action" value="<?= $editRecord ? 'update' : 'create' ?>">
        <?php if ($editRecord): ?>
        <input type="hidden" name="id" value="<?= (int) $editRecord['id'] ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="shipment_number"><?= e(__('admin.inbound_shipment_no')) ?> <?= e(__('common.required')) ?></label>
                <input type="text" id="shipment_number" name="shipment_number" class="form-control" required
                       value="<?= e($editRecord['shipment_number'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="total_quantity"><?= e(__('admin.cartons')) ?> <span class="label-optional"><?= e(__('common.optional')) ?></span></label>
                <input type="number" id="total_quantity" name="total_quantity" class="form-control"
                       min="0" step="1" inputmode="numeric"
                       value="<?= e($editRecord['total_quantity'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label for="quantity"><?= e(__('admin.quantity')) ?> <?= e(__('common.required')) ?></label>
                <input type="number" id="quantity" name="quantity" class="form-control"
                       required min="1" step="1"
                       value="<?= e($editRecord['quantity'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="inbound_date"><?= e(__('admin.inbound_date')) ?> <?= e(__('common.required')) ?></label>
                <input type="date" id="inbound_date" name="inbound_date" class="form-control" required
                       value="<?= e($editRecord['inbound_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label for="product_name"><?= e(__('admin.product_name')) ?> <span class="label-optional"><?= e(__('common.optional')) ?></span></label>
                <input type="text" id="product_name" name="product_name" class="form-control"
                       placeholder="<?= e(__('admin.ph.product')) ?>"
                       value="<?= e($editRecord['product_name'] ?? '') ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">
            <?= $editRecord ? e(__('common.save_changes')) : e(__('common.save')) ?>
        </button>
    </form>
</div>
<?php endif; ?>

<!-- Recent Shipments Table -->
<div class="card card-shipments">
    <div class="card-header card-header-with-legend">
        <div class="card-header-title-row">
            <h2><?= e(__('admin.recent_shipments')) ?></h2>
            <div class="status-legend status-legend--inline" aria-label="<?= e(__('admin.status')) ?>">
                <span><?= renderStatusBadge('not_started') ?></span>
                <span><?= renderStatusBadge('matched') ?></span>
                <span><?= renderStatusBadge('mismatched') ?></span>
                <span><?= renderStatusBadge('completed') ?></span>
            </div>
        </div>
        <span><?= e(__('common.records_count', ['count' => count($shipments)])) ?></span>
    </div>

    <?php if (empty($shipments)): ?>
    <div class="empty-state"><?= e(__('admin.empty_records')) ?></div>
    <?php else: ?>
    <div class="table-responsive table-shipments-wrap">
        <table class="table-shipments">
            <thead>
                <tr>
                    <th><?= e(__('admin.status')) ?></th>
                    <th><?= e(__('admin.inbound_date')) ?></th>
                    <th><?= e(__('admin.product_name')) ?></th>
                    <th><?= e(__('admin.shipment_number')) ?></th>
                    <th><?= e(__('admin.cartons')) ?></th>
                    <th><?= e(__('admin.quantity')) ?></th>
                    <th><?= e(__('admin.counted_cartons')) ?></th>
                    <th><?= e(__('admin.counted_quantity')) ?></th>
                    <th><?= e(__('admin.counted_by')) ?></th>
                    <th><?= e(__('admin.counting_date')) ?></th>
                    <th><?= e(__('admin.start_time')) ?></th>
                    <th><?= e(__('admin.com_time')) ?></th>
                    <th><?= e(__('admin.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($shipments as $shipment):
                $progress = getShipmentProgress($pdo, $shipment);
                $latest = $progress['latest'];
                $detailUrl = baseUrl('admin/shipment-detail.php?id=' . (int) $shipment['id']);
            ?>
                <tr class="shipment-row" data-href="<?= e($detailUrl) ?>" tabindex="0" role="link">
                    <td><?= renderShipmentStatusDisplay($progress) ?></td>
                    <td><?= e($shipment['inbound_date']) ?></td>
                    <td><?= e($shipment['product_name']) ?></td>
                    <td><strong><?= e($shipment['shipment_number']) ?></strong></td>
                    <td><?= renderBoxQty((int) ($shipment['total_quantity'] ?? 0)) ?></td>
                    <td><?= renderPieceQty((int) ($shipment['quantity'] ?? 0)) ?></td>
                    <td><?= renderBoxQty((int) ($progress['counted_cartons'] ?? 0)) ?></td>
                    <td><?= renderPieceQty((int) ($progress['counted_pieces'] ?? 0)) ?></td>
                    <td><?= $latest ? e($latest['counted_by']) : e(__('common.dash')) ?></td>
                    <td><?= $latest ? e($latest['counting_date']) : e(__('common.dash')) ?></td>
                    <td><?= $latest ? e(substr($latest['start_time'], 0, 8)) : e(__('common.dash')) ?></td>
                    <td><?= $latest ? e(substr($latest['completion_time'], 0, 8)) : e(__('common.dash')) ?></td>
                    <td class="td-actions" onclick="event.stopPropagation();">
                        <div class="btn-group">
                            <a href="?edit=<?= (int) $shipment['id'] ?><?= $queryString ? '&' . $queryString : '' ?>"
                               class="btn btn-warning btn-sm"><?= e(__('common.edit')) ?></a>
                            <form method="post" action="<?= baseUrl('admin/actions.php') ?><?= $queryString ? '?' . $queryString : '' ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $shipment['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="<?= e(__('admin.delete_confirm')) ?>"><?= e(__('common.delete')) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
