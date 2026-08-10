<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDB();
ensureQuantityColumns($pdo);
$user = currentUser();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . baseUrl('admin/dashboard.php'));
    exit;
}

function redirectDashboard(array $extra = []): void
{
    $params = array_filter(array_merge([
        'search' => $_GET['search'] ?? null,
        'filter' => $_GET['filter'] ?? null,
        'date' => $_GET['date'] ?? null,
    ], $extra));
    $url = baseUrl('admin/dashboard.php');
    if ($params) {
        $url .= '?' . http_build_query($params);
    }
    header('Location: ' . $url);
    exit;
}

switch ($action) {
    case 'create':
        $input = normalizeInboundShipmentInput($_POST);

        if ($input['is_valid']) {
            $stmt = $pdo->prepare(
                'INSERT INTO inbound_shipments (inbound_date, product_name, shipment_number, total_quantity, quantity, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $input['inbound_date'], $input['product_name'],
                $input['shipment_number'], $input['total_quantity'], $input['quantity'], $user['id'],
            ]);
            flash('success', __('msg.inbound_saved'));
        } elseif (!isValidDateYmd($input['inbound_date'])) {
            flash('error', __('msg.inbound_date_required'));
            redirectDashboard(['add' => 1]);
        } else {
            flash('error', __('msg.inbound_validation'));
        }
        break;

    case 'update':
        $id = (int) ($_POST['id'] ?? 0);
        $input = normalizeInboundShipmentInput($_POST);

        if ($id && $input['is_valid']) {
            $stmt = $pdo->prepare(
                'UPDATE inbound_shipments SET inbound_date=?, product_name=?, shipment_number=?, total_quantity=?, quantity=?
                 WHERE id=?'
            );
            $stmt->execute([
                $input['inbound_date'], $input['product_name'],
                $input['shipment_number'], $input['total_quantity'], $input['quantity'], $id,
            ]);
            flash('success', __('msg.inbound_updated'));
        } elseif (!isValidDateYmd($input['inbound_date'])) {
            flash('error', __('msg.inbound_date_required'));
            header('Location: ' . baseUrl('admin/dashboard.php?edit=' . $id));
            exit;
        } else {
            flash('error', __('msg.inbound_update_failed'));
        }
        break;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare('SELECT shipment_number FROM inbound_shipments WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $delCount = $pdo->prepare('DELETE FROM counting_records WHERE shipment_number = ?');
                $delCount->execute([$row['shipment_number']]);
            }
            $stmt = $pdo->prepare('DELETE FROM inbound_shipments WHERE id = ?');
            $stmt->execute([$id]);
            flash('success', __('msg.inbound_deleted'));
        }
        break;

    case 'delete_counting':
        $countId = (int) ($_POST['counting_id'] ?? 0);
        $shipmentId = (int) ($_POST['shipment_id'] ?? 0);
        if ($countId) {
            $stmt = $pdo->prepare('DELETE FROM counting_records WHERE id = ?');
            $stmt->execute([$countId]);
            flash('success', __('msg.counting_deleted'));
        }
        if ($shipmentId) {
            header('Location: ' . baseUrl('admin/shipment-detail.php?id=' . $shipmentId));
        } else {
            header('Location: ' . baseUrl('admin/dashboard.php') . '#pending-shipments');
        }
        exit;

    case 'update_counting_cartons':
        $countId = (int) ($_POST['counting_id'] ?? 0);
        $shipmentId = (int) ($_POST['shipment_id'] ?? 0);
        $totalCounted = max(0, (int) ($_POST['total_counted'] ?? 0));
        if ($countId && $shipmentId) {
            $stmt = $pdo->prepare('UPDATE counting_records SET total_counted = ? WHERE id = ?');
            $stmt->execute([$totalCounted, $countId]);
            flash('success', __('msg.cartons_updated'));
        } else {
            flash('error', __('msg.invalid_action'));
        }
        header('Location: ' . baseUrl('admin/shipment-detail.php?id=' . $shipmentId));
        exit;

    default:
        flash('error', __('msg.invalid_action'));
}

redirectDashboard();
