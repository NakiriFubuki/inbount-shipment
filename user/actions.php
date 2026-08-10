<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireUser();

$pdo = getDB();
ensureQuantityColumns($pdo);
ensureRemarksColumn($pdo);
$user = currentUser();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . baseUrl('user/dashboard.php'));
    exit;
}

switch ($action) {
    case 'create':
        $input = normalizeCountingRecordInput($_POST, $pdo);
        $countedBy = counterNameFromUser($user);

        if ($input['is_valid'] && $countedBy !== '') {
            if (!shipmentShowsInPendingList($pdo, $input['inbound'], true)) {
                flash('error', __('msg.shipment_fully_counted'));
                break;
            }
            $capacity = shipmentCountingCapacity($pdo, $input['inbound'], null);
            $qtyError = validateCountingQuantities($capacity, $input['quantity_counted']);
            if ($qtyError) {
                flash('error', __($qtyError, ['max' => (int) $capacity['remaining_pieces']]));
                break;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO counting_records
                (shipment_number, product_name, counting_date, start_time, completion_time,
                 total_counted, quantity_counted, counted_by, remarks, user_id)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $input['shipment_number'], $input['product_name'], $input['counting_date'],
                $input['start_time'], $input['completion_time'],
                $input['quantity_counted'], $countedBy, $input['remarks'] ?: null, $user['id'],
            ]);
            flash('success', __('msg.counting_saved'));
        } elseif ($input['shipment_number'] !== '' && !$input['inbound']) {
            flash('error', __('msg.invalid_shipment'));
        } elseif ($input['inbound'] && $input['quantity_counted'] < 1) {
            flash('error', __('msg.counting_quantity_required'));
        } elseif ($input['inbound'] && !isValidDateYmd($input['counting_date'])) {
            flash('error', __('msg.counting_date_required'));
        } else {
            flash('error', __('msg.counting_validation'));
        }
        break;

    case 'update':
        $id = (int) ($_POST['id'] ?? 0);
        $input = normalizeCountingRecordInput($_POST, $pdo);
        $countedBy = counterNameFromUser($user);

        if ($id && $input['is_valid'] && $countedBy !== '') {
            $existingStmt = $pdo->prepare(
                'SELECT total_counted FROM counting_records WHERE id = ? AND user_id = ?'
            );
            $existingStmt->execute([$id, $user['id']]);
            $existing = $existingStmt->fetch();
            if (!$existing) {
                flash('error', __('msg.cannot_update_record'));
                break;
            }
            $capacity = shipmentCountingCapacity($pdo, $input['inbound'], $id);
            $qtyError = validateCountingQuantities($capacity, $input['quantity_counted']);
            if ($qtyError) {
                flash('error', __($qtyError, ['max' => (int) $capacity['remaining_pieces']]));
                break;
            }
            $stmt = $pdo->prepare(
                'UPDATE counting_records SET shipment_number=?, product_name=?, counting_date=?,
                 start_time=?, completion_time=?, total_counted=?, quantity_counted=?, counted_by=?, remarks=?
                 WHERE id=? AND user_id=?'
            );
            $stmt->execute([
                $input['shipment_number'], $input['product_name'], $input['counting_date'],
                $input['start_time'], $input['completion_time'],
                (int) ($existing['total_counted'] ?? 0), $input['quantity_counted'],
                $countedBy, $input['remarks'] ?: null, $id, $user['id'],
            ]);
            if ($stmt->rowCount() > 0) {
                flash('success', __('msg.counting_updated'));
            } else {
                flash('error', __('msg.cannot_update_record'));
            }
        } elseif ($input['shipment_number'] !== '' && !$input['inbound']) {
            flash('error', __('msg.invalid_shipment'));
        } elseif ($input['inbound'] && $input['quantity_counted'] < 1) {
            flash('error', __('msg.counting_quantity_required'));
        } elseif ($input['inbound'] && !isValidDateYmd($input['counting_date'])) {
            flash('error', __('msg.counting_date_required'));
        } else {
            flash('error', __('msg.counting_update_failed'));
        }
        break;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare('DELETE FROM counting_records WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
            flash('success', __('msg.counting_deleted'));
        }
        break;

    default:
        flash('error', __('msg.invalid_action'));
}

$redirect = baseUrl('user/dashboard.php');
if ($action === 'delete') {
    $redirect .= '#pending-shipments';
}
header('Location: ' . $redirect);
exit;
