<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireUser();

$pdo = getDB();
ensureQuantityColumns($pdo);
ensureRemarksColumn($pdo);
$user = currentUser();
$activeNav = 'user';
$pageTitle = __('user.page_title');

$allInboundStmt = $pdo->query(
    'SELECT s.* FROM inbound_shipments s ORDER BY s.inbound_date DESC, s.created_at DESC'
);
$allInbound = $allInboundStmt->fetchAll();

$progressBySn = [];
foreach ($allInbound as $s) {
    $progress = getShipmentProgress($pdo, $s);
    $progressBySn[$s['shipment_number']] = $progress;
}

$pendingShipments = [];
$pendingStmt = $pdo->query(
    'SELECT s.* FROM inbound_shipments s ORDER BY s.inbound_date DESC, s.created_at DESC'
);
foreach ($pendingStmt->fetchAll() as $s) {
    if (!shipmentShowsInPendingList($pdo, $s, true)) {
        continue;
    }
    $progress = $progressBySn[$s['shipment_number']] ?? getShipmentProgress($pdo, $s);
    $s['_progress'] = $progress;
    $pendingShipments[] = $s;
}

$stmt = $pdo->prepare(
    'SELECT cr.*
     FROM counting_records cr
     WHERE cr.user_id = ?
     ORDER BY cr.counting_date DESC, cr.created_at DESC'
);
$stmt->execute([$user['id']]);
$records = $stmt->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$editRecord = null;
if ($editId) {
    foreach ($records as $r) {
        if ((int) $r['id'] === $editId) {
            $editRecord = $r;
            break;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= e(__('user.heading')) ?></h1>
</div>

<?php if (!empty($pendingShipments)): ?>
<div class="card card-shipments" id="pending-shipments">
    <div class="card-header">
        <div>
            <h2><?= e(__('user.pending_shipments')) ?> <span class="badge badge-user"><?= count($pendingShipments) ?></span></h2>
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
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingShipments as $ps):
                $progress = $ps['_progress'];
            ?>
                <tr class="pending-row" data-shipment="<?= e($ps['shipment_number']) ?>"
                    data-product="<?= e($ps['product_name']) ?>"
                    data-cartons="<?= (int) ($ps['total_quantity'] ?? 0) ?>">
                    <td><?= renderShipmentStatusDisplay($progress, true) ?></td>
                    <td><?= e($ps['inbound_date']) ?></td>
                    <td><?= e($ps['product_name']) ?></td>
                    <td><strong><?= e($ps['shipment_number']) ?></strong></td>
                    <td><?= renderBoxQty((int) ($ps['total_quantity'] ?? 0)) ?></td>
                    <td><?= renderPieceQty((int) ($ps['quantity'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card card-shipments" id="pending-shipments">
    <div class="card-header">
        <h2><?= e(__('user.pending_shipments')) ?></h2>
    </div>
    <p class="empty-state">
        <?= e(empty($allInbound) ? __('user.empty_pending_no_inbound') : __('user.empty_pending_all_done')) ?>
    </p>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><?= $editRecord ? e(__('user.edit_record')) : e(__('user.new_record')) ?></h2>
        <?php if ($editRecord): ?>
        <a href="<?= baseUrl('user/dashboard.php') ?>" class="btn btn-outline btn-sm"><?= e(__('common.cancel_edit')) ?></a>
        <?php endif; ?>
    </div>
    <form method="post" action="<?= baseUrl('user/actions.php') ?>" id="counting-form"
          data-quantity-required-msg="<?= e(__('msg.counting_quantity_required')) ?>"
          data-date-required-msg="<?= e(__('msg.counting_date_required')) ?>">
        <input type="hidden" name="action" value="<?= $editRecord ? 'update' : 'create' ?>">
        <?php if ($editRecord): ?>
        <input type="hidden" name="id" value="<?= (int) $editRecord['id'] ?>">
        <?php endif; ?>

        <?php
        $selectedShipment = $editRecord['shipment_number'] ?? '';
        $shipmentOptions = [];
        foreach ($allInbound as $ps) {
            $sn = $ps['shipment_number'];
            $allowPick = shipmentShowsInPendingList($pdo, $ps, true)
                || ($editRecord && ($editRecord['shipment_number'] ?? '') === $sn);
            if ($allowPick) {
                $shipmentOptions[$sn] = $ps;
            }
        }
        $selectedProduct = '';
        if ($selectedShipment && isset($shipmentOptions[$selectedShipment])) {
            $selectedProduct = $shipmentOptions[$selectedShipment]['product_name'];
        }
        $shipmentSearchList = array_values(array_map(static function ($ps) {
            return [
                'sn' => $ps['shipment_number'],
                'product' => $ps['product_name'],
                'cartons' => (int) ($ps['total_quantity'] ?? 0),
            ];
        }, $shipmentOptions));
        $shipmentListJson = json_encode($shipmentSearchList, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $selectedCartons = 0;
        if ($selectedShipment && isset($shipmentOptions[$selectedShipment])) {
            $selectedCartons = (int) ($shipmentOptions[$selectedShipment]['total_quantity'] ?? 0);
        }
        $pageScriptsBeforeApp = 'window.shipmentProducts=' . json_encode(array_column($allInbound, 'product_name', 'shipment_number'), JSON_UNESCAPED_UNICODE) . ';'
            . 'window.shipmentCartons=' . json_encode(array_column($allInbound, 'total_quantity', 'shipment_number'), JSON_UNESCAPED_UNICODE) . ';'
            . 'window.shipmentSearchList=' . json_encode($shipmentSearchList, JSON_UNESCAPED_UNICODE) . ';';
        ?>
        <div class="form-row">
            <div class="form-group">
                <label for="shipment_search"><?= e(__('user.inbound_shipment_no')) ?> <?= e(__('common.required')) ?></label>
                <div class="shipment-search-wrap" id="shipment-search-wrap"
                     data-shipment-list="<?= e($shipmentListJson ?: '[]') ?>"
                     data-placeholder="<?= e(__('user.search_shipment_ph')) ?>"
                     data-no-results="<?= e(__('user.search_no_results')) ?>"
                     data-pick-msg="<?= e(__('user.search_pick_shipment')) ?>">
                    <input type="text" id="shipment_search" class="form-control shipment-search-input"
                           autocomplete="off" spellcheck="false" role="combobox" aria-expanded="false"
                           aria-controls="shipment-search-dropdown" aria-autocomplete="list"
                           placeholder="<?= e(__('user.search_shipment_ph')) ?>"
                           value="<?= e($selectedShipment) ?>">
                    <input type="hidden" name="shipment_number" id="shipment_number"
                           value="<?= e($selectedShipment) ?>">
                    <ul class="shipment-search-dropdown" id="shipment-search-dropdown" role="listbox" hidden></ul>
                </div>
            </div>
            <div class="form-group">
                <label for="product_name_display"><?= e(__('admin.product_name')) ?></label>
                <input type="text" id="product_name_display" class="form-control form-control-readonly" readonly
                       tabindex="-1" aria-readonly="true"
                       value="<?= e($selectedProduct) ?>"
                       placeholder="<?= e(__('user.select_shipment')) ?>">
            </div>
            <div class="form-group">
                <label for="cartons_display"><?= e(__('admin.cartons')) ?></label>
                <input type="text" id="cartons_display" class="form-control form-control-readonly" readonly
                       tabindex="-1" aria-readonly="true"
                       value="<?= renderBoxQty($selectedCartons) ?>"
                       placeholder="<?= e(__('user.cartons_readonly_hint')) ?>">
            </div>
            <div class="form-group">
                <label for="counting_date"><?= e(__('user.counting_date')) ?> <?= e(__('common.required')) ?></label>
                <input type="date" id="counting_date" name="counting_date" class="form-control" required
                       value="<?= e($editRecord['counting_date'] ?? date('Y-m-d')) ?>">
            </div>
        </div>

        <?php
        $initStart = $editRecord ? substr($editRecord['start_time'], 0, 8) : '';
        $initEnd = $editRecord ? substr($editRecord['completion_time'], 0, 8) : '';
        if (strlen($initStart) === 5) {
            $initStart .= ':00';
        }
        if (strlen($initEnd) === 5) {
            $initEnd .= ':00';
        }
        ?>
        <div class="counting-timer-box" id="counting-timer-box"
             data-init-start="<?= e($initStart) ?>"
             data-init-end="<?= e($initEnd) ?>"
             data-label-start="<?= e(__('user.timer_start_btn')) ?>"
             data-label-stop="<?= e(__('user.timer_stop_btn')) ?>"
             data-status-not-started="<?= e(__('user.timer_not_started')) ?>"
             data-status-running="<?= e(__('user.timer_running')) ?>"
             data-status-stopped="<?= e(__('user.timer_stopped')) ?>"
             data-status-manual="<?= e(__('user.timer_manual_set')) ?>">
            <div class="counting-timer-header">
                <label><?= e(__('user.timer_title')) ?> <span class="label-optional"><?= e(__('common.optional')) ?></span></label>
                <p class="timer-hint"><?= e(__('user.timer_manual_hint')) ?></p>
            </div>
            <div class="counting-timer-body">
                <div class="timer-display-wrap">
                    <span class="timer-label"><?= e(__('user.timer_elapsed')) ?></span>
                    <div class="timer-display" id="timer-display">00:00:00</div>
                    <span class="timer-status" id="timer-status"><?= e(__('user.timer_not_started')) ?></span>
                </div>
                <div class="timer-actions">
                    <button type="button" id="timer-toggle" class="btn btn-primary btn-timer">
                        <?= e(__('user.timer_start_btn')) ?>
                    </button>
                    <button type="button" id="timer-reset" class="btn btn-outline btn-sm" style="display:none;">
                        <?= e(__('user.timer_reset_btn')) ?>
                    </button>
                </div>
                <div class="timer-manual-times">
                    <div class="timer-time-field">
                        <label for="start_time"><?= e(__('user.timer_started_at')) ?></label>
                        <input type="time" id="start_time" name="start_time" class="form-control timer-time-input"
                               step="1" value="<?= e($initStart) ?>">
                    </div>
                    <div class="timer-time-field">
                        <label for="completion_time"><?= e(__('user.timer_stopped_at')) ?></label>
                        <input type="time" id="completion_time" name="completion_time" class="form-control timer-time-input"
                               step="1" value="<?= e($initEnd) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="quantity_counted"><?= e(__('user.quantity_counted')) ?> <?= e(__('common.required')) ?></label>
                <input type="number" id="quantity_counted" name="quantity_counted" class="form-control"
                       required min="1" step="1" inputmode="numeric"
                       value="<?= e($editRecord['quantity_counted'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="counted_by_display"><?= e(__('user.counted_by')) ?></label>
                <input type="text" id="counted_by_display" class="form-control form-control-readonly" readonly
                       tabindex="-1" aria-readonly="true"
                       value="<?= e($user['username']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="remarks"><?= e(__('user.remarks')) ?> <span class="label-optional"><?= e(__('common.optional')) ?></span></label>
            <textarea id="remarks" name="remarks" class="form-control" rows="2"
                      placeholder="<?= e(__('user.ph.remarks')) ?>"><?= e($editRecord['remarks'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">
            <?= $editRecord ? e(__('common.save_changes')) : e(__('common.save')) ?>
        </button>
    </form>
</div>

<div class="card card-shipments">
    <div class="card-header card-header-with-legend">
        <div class="card-header-title-row">
            <h2><?= e(__('user.my_records')) ?></h2>
            <div class="status-legend status-legend--inline" aria-label="<?= e(__('admin.status')) ?>">
                <span><?= renderStatusBadge('not_started') ?></span>
                <span><?= renderStatusBadge('completed') ?></span>
            </div>
        </div>
        <span><?= e(__('common.records_short', ['count' => count($records)])) ?></span>
    </div>

    <?php if (empty($records)): ?>
    <div class="empty-state"><?= e(__('user.empty_records')) ?></div>
    <?php else: ?>
    <div class="table-responsive table-shipments-wrap">
        <table class="table-shipments">
            <thead>
                <tr>
                    <th><?= e(__('admin.status')) ?></th>
                    <th><?= e(__('admin.shipment_number')) ?></th>
                    <th><?= e(__('admin.product_name')) ?></th>
                    <th><?= e(__('user.counting_date')) ?></th>
                    <th><?= e(__('user.quantity_counted')) ?></th>
                    <th><?= e(__('admin.counted_cartons')) ?></th>
                    <th><?= e(__('admin.start_time')) ?></th>
                    <th><?= e(__('admin.com_time')) ?></th>
                    <th><?= e(__('user.counted_by')) ?></th>
                    <th class="th-remarks"><?= e(__('user.remarks')) ?></th>
                    <th><?= e(__('admin.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($records as $rec): ?>
                <tr>
                    <td><?= renderUserRecordStatus($rec) ?></td>
                    <td><strong><?= e($rec['shipment_number']) ?></strong></td>
                    <td><?= e($rec['product_name']) ?></td>
                    <td><?= e($rec['counting_date']) ?></td>
                    <td><?= renderPieceQty((int) ($rec['quantity_counted'] ?? 0)) ?></td>
                    <td><?= renderBoxQty((int) ($rec['total_counted'] ?? 0)) ?></td>
                    <td><?= e(substr($rec['start_time'], 0, 5)) ?></td>
                    <td><?= e(substr($rec['completion_time'], 0, 5)) ?></td>
                    <td><?= e($rec['counted_by']) ?></td>
                    <td class="td-remarks"><?= e($rec['remarks'] ?: __('common.dash')) ?></td>
                    <td class="td-actions">
                        <div class="btn-group">
                            <a href="?edit=<?= (int) $rec['id'] ?>" class="btn btn-warning btn-sm"><?= e(__('common.edit')) ?></a>
                            <form method="post" action="<?= baseUrl('user/actions.php') ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $rec['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="<?= e(__('user.delete_confirm')) ?>"><?= e(__('common.delete')) ?></button>
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
