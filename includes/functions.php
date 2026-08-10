<?php
/**
 * Shared utility functions
 */
require_once __DIR__ . '/lang.php';

function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** Display name stored on counting records — always from logged-in session, not POST. */
function counterNameFromUser(?array $user): string
{
    return trim($user['username'] ?? '');
}

/** Normalize admin inbound form: shipment number, date, cartons, and product quantity required. */
function normalizeInboundShipmentInput(array $post): array
{
    $shipmentNumber = trim($post['shipment_number'] ?? '');
    $totalQuantity = max(0, (int) ($post['total_quantity'] ?? 0));
    $quantity = max(0, (int) ($post['quantity'] ?? 0));
    $inboundDate = trim($post['inbound_date'] ?? '');
    $productName = trim($post['product_name'] ?? '');

    return [
        'shipment_number' => $shipmentNumber,
        'total_quantity' => $totalQuantity,
        'quantity' => $quantity,
        'inbound_date' => $inboundDate,
        'product_name' => $productName,
        'is_valid' => $shipmentNumber !== '' && $quantity > 0 && isValidDateYmd($inboundDate),
    ];
}

/** @return bool True if Y-m-d and a real calendar date. */
function isValidDateYmd(string $date): bool
{
    $date = trim($date);
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $parts = explode('-', $date);
    return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
}

/** Admin dashboard: unified search on product name and shipment number. */
function buildUnifiedSearch(string $search): array
{
    $search = trim($search);
    if ($search === '') {
        return ['sql' => '', 'params' => []];
    }

    return [
        'sql' => ' AND (s.product_name LIKE ? OR s.shipment_number LIKE ?)',
        'params' => ['%' . $search . '%', '%' . $search . '%'],
    ];
}

/** Admin dashboard: date filter tabs (today / daily / past7). */
function buildDateFilter(string $filter, ?string $filterDate = null): ?array
{
    $today = date('Y-m-d');
    switch ($filter) {
        case 'today':
            return ['start' => $today, 'end' => $today];
        case 'daily':
            $date = ($filterDate !== null && isValidDateYmd($filterDate)) ? $filterDate : $today;
            return ['start' => $date, 'end' => $date];
        case 'past7':
            return ['start' => date('Y-m-d', strtotime('-6 days')), 'end' => $today];
        default:
            return null;
    }
}

/** Normalize counting record form: shipment number, quantity counted, and date required. */
function normalizeCountingTime(string $time): string
{
    $time = trim($time);
    if ($time === '') {
        return '00:00:00';
    }
    $parts = explode(':', $time);
    if (count($parts) === 2) {
        return $parts[0] . ':' . $parts[1] . ':00';
    }

    return $time;
}

function normalizeCountingRecordInput(array $post, PDO $pdo): array
{
    $shipmentNumber = trim($post['shipment_number'] ?? '');
    $inbound = $shipmentNumber !== '' ? getInboundShipmentByCode($pdo, $shipmentNumber) : null;
    $countingDate = trim($post['counting_date'] ?? '');
    $startTime = normalizeCountingTime($post['start_time'] ?? '');
    $completionTime = normalizeCountingTime($post['completion_time'] ?? '');
    if ($completionTime === '00:00:00' && $startTime !== '00:00:00') {
        $completionTime = $startTime;
    }

    $quantityCounted = max(0, (int) ($post['quantity_counted'] ?? 0));
    $remarks = trim($post['remarks'] ?? '');

    return [
        'shipment_number' => $shipmentNumber,
        'inbound' => $inbound,
        'product_name' => $inbound['product_name'] ?? '',
        'counting_date' => $countingDate,
        'start_time' => $startTime,
        'completion_time' => $completionTime,
        'total_counted' => 0,
        'quantity_counted' => $quantityCounted,
        'remarks' => $remarks,
        'is_valid' => $inbound !== null && $shipmentNumber !== '' && $quantityCounted >= 1 && isValidDateYmd($countingDate),
    ];
}

function formatPieceQty(int $qty): string
{
    return (string) (int) $qty;
}

function renderPieceQty(int $qty): string
{
    return (string) (int) $qty;
}

function renderBoxQty(int $qty): string
{
    return renderPieceQty($qty);
}

/** Lookup inbound shipment by shipment number. */
function getInboundShipmentByCode(PDO $pdo, string $code): ?array
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT shipment_number, product_name, total_quantity, quantity FROM inbound_shipments
         WHERE shipment_number = ? LIMIT 1'
    );
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function getInboundShipmentByNumber(PDO $pdo, string $shipmentNumber): ?array
{
    return getInboundShipmentByCode($pdo, $shipmentNumber);
}

function fetchCountingRecords(PDO $pdo, string $shipmentNumber): array
{
    $stmt = $pdo->prepare(
        'SELECT cr.*, u.username AS counter_username
         FROM counting_records cr
         JOIN users u ON cr.user_id = u.id
         WHERE cr.shipment_number = ?
         ORDER BY cr.updated_at DESC'
    );
    $stmt->execute([$shipmentNumber]);
    return $stmt->fetchAll();
}

/** Sum counted cartons for a shipment; optionally exclude one record (for edits). */
function sumCountedCartonsForShipment(PDO $pdo, string $shipmentNumber, ?int $excludeRecordId = null): int
{
    $sql = 'SELECT COALESCE(SUM(total_counted), 0) AS cartons
            FROM counting_records WHERE shipment_number = ?';
    $params = [$shipmentNumber];
    if ($excludeRecordId) {
        $sql .= ' AND id != ?';
        $params[] = $excludeRecordId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return (int) ($row['cartons'] ?? 0);
}

/** Sum counted product quantity for a shipment; optionally exclude one record (for edits). */
function sumCountedPiecesForShipment(PDO $pdo, string $shipmentNumber, ?int $excludeRecordId = null): int
{
    $sql = 'SELECT COALESCE(SUM(quantity_counted), 0) AS pieces
            FROM counting_records WHERE shipment_number = ?';
    $params = [$shipmentNumber];
    if ($excludeRecordId) {
        $sql .= ' AND id != ?';
        $params[] = $excludeRecordId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return (int) ($row['pieces'] ?? 0);
}

/**
 * Remaining countable product quantity (null = no admin cap).
 *
 * @param array{shipment_number:string,quantity?:int} $inbound
 */
function shipmentCountingCapacity(PDO $pdo, array $inbound, ?int $excludeRecordId = null): array
{
    $adminPieces = (int) ($inbound['quantity'] ?? 0);
    $countedPieces = sumCountedPiecesForShipment($pdo, $inbound['shipment_number'], $excludeRecordId);

    return [
        'counted_pieces' => $countedPieces,
        'remaining_pieces' => $adminPieces > 0 ? max(0, $adminPieces - $countedPieces) : null,
        'admin_pieces' => $adminPieces,
    ];
}

/** Whether this shipment should appear in the pending list (still countable). */
function shipmentShowsInPendingList(PDO $pdo, array $shipment, bool $forUser = false): bool
{
    if ($forUser) {
        $progress = getShipmentProgress($pdo, $shipment);

        return userDisplayStatus($progress['status']) !== 'completed';
    }

    $capacity = shipmentCountingCapacity($pdo, $shipment, null);

    return shipmentAcceptsNewCounting($capacity);
}

/** Whether a new counting entry may still be added (by product quantity). */
function shipmentAcceptsNewCounting(array $capacity): bool
{
    $remainingPieces = $capacity['remaining_pieces'];

    return $remainingPieces === null || $remainingPieces > 0;
}

/**
 * Validate counting input against remaining product quantity.
 *
 * @return string|null Flash message key if over remaining capacity
 */
function validateCountingQuantities(array $capacity, int $quantityCounted): ?string
{
    if ($capacity['remaining_pieces'] !== null && $quantityCounted > $capacity['remaining_pieces']) {
        return 'msg.counting_exceeds_quantity';
    }

    return null;
}

/**
 * Full progress info for dashboard
 */
function getShipmentProgress(PDO $pdo, array $shipment): array
{
    $records = fetchCountingRecords($pdo, $shipment['shipment_number']);
    $capacity = shipmentCountingCapacity($pdo, $shipment, null);
    $adminPieces = $capacity['admin_pieces'];
    $countedPieces = $capacity['counted_pieces'];
    $adminCartons = (int) ($shipment['total_quantity'] ?? 0);
    $countedCartons = sumCountedCartonsForShipment($pdo, $shipment['shipment_number'], null);
    $latest = $records[0] ?? null;

    $progressMeta = [
        'counted_qty' => $countedPieces,
        'counted_pieces' => $countedPieces,
        'admin_qty' => $adminPieces,
        'admin_pieces' => $adminPieces,
        'admin_cartons' => $adminCartons,
        'counted_cartons' => $countedCartons,
        'remaining_pieces' => $capacity['remaining_pieces'],
        'accepts_new_counting' => shipmentAcceptsNewCounting($capacity),
        'accepts_new_counting_user' => shipmentAcceptsNewCounting($capacity),
        'records' => $records,
        'latest' => $latest,
    ];

    if (empty($records)) {
        return array_merge($progressMeta, [
            'status' => 'not_started',
            'counted' => false,
            'quantity_match' => null,
            'pieces_match' => null,
            'percent' => 0,
        ]);
    }

    $remainingPieces = $capacity['remaining_pieces'];
    $piecesMatch = $adminPieces > 0 ? ($countedPieces === $adminPieces) : null;

    if ($adminPieces > 0) {
        $percent = min(100, (int) round(($countedPieces / $adminPieces) * 100));
        if ($remainingPieces === 0) {
            $status = $piecesMatch ? 'completed' : 'mismatched';
        } elseif ($piecesMatch) {
            $status = 'matched';
        } else {
            $status = 'mismatched';
        }
    } else {
        $percent = $countedPieces > 0 ? 100 : 0;
        $status = 'mismatched';
    }

    return array_merge($progressMeta, [
        'status' => $status,
        'counted' => true,
        'quantity_match' => $piecesMatch,
        'pieces_match' => $piecesMatch,
        'percent' => $percent,
    ]);
}

/** @deprecated Use getShipmentProgress */
function getShipmentStatus(PDO $pdo, array $shipment): array
{
    return getShipmentProgress($pdo, $shipment);
}

function renderStatusBadge(string $status): string
{
    $map = [
        'not_started' => ['class' => 'status-badge status-not-started', 'dot' => 'dot-red', 'label' => __('status.not_started')],
        'matched' => ['class' => 'status-badge status-matched', 'dot' => 'dot-blue', 'label' => __('status.matched')],
        'mismatched' => ['class' => 'status-badge status-mismatched', 'dot' => 'dot-orange', 'label' => __('status.mismatched')],
        'completed' => ['class' => 'status-badge status-completed', 'dot' => 'dot-cyan', 'label' => __('status.completed')],
        'partial' => ['class' => 'status-badge status-mismatched', 'dot' => 'dot-orange', 'label' => __('status.mismatched')],
    ];
    $m = $map[$status] ?? $map['not_started'];

    return '<span class="' . e($m['class']) . '">'
        . '<span class="dot ' . e($m['dot']) . '"></span>'
        . e($m['label']) . '</span>';
}

/** Wrap multiple status badges side by side (e.g. matched + completed). */
function renderStatusBadgeGroup(array $statuses): string
{
    $html = '';
    foreach ($statuses as $status) {
        $html .= renderStatusBadge($status);
    }

    return '<span class="status-badge-group">' . $html . '</span>';
}

/**
 * Admin shipment status: when fully counted and quantities match, show blue + cyan.
 *
 * @param array{status?:string} $progress
 */
function renderShipmentStatusDisplay(array $progress, bool $forUser = false): string
{
    $status = $progress['status'] ?? 'not_started';

    if ($forUser) {
        return renderStatusBadge('not_started');
    }

    if ($status === 'completed') {
        return renderStatusBadgeGroup(['matched', 'completed']);
    }

    return renderStatusBadge($status);
}

/** Whether a submitted counting record counts as done on the user dashboard. */
function userCountingRecordIsDone(array $record): bool
{
    return (int) ($record['quantity_counted'] ?? 0) >= 1
        && isValidDateYmd((string) ($record['counting_date'] ?? ''));
}

/** User "My Records" row: submitted counting = 完成 (not shipment-level status). */
function renderUserRecordStatus(array $record): string
{
    return renderStatusBadge(userCountingRecordIsDone($record) ? 'completed' : 'not_started');
}

/** Users only see 未开始 (pending) or 完成 (submitted record). */
function userDisplayStatus(string $status): string
{
    return $status === 'completed' ? 'completed' : 'not_started';
}

function renderUserStatusBadge(string $status): string
{
    return renderStatusBadge($status === 'completed' ? 'completed' : 'not_started');
}

function statusDots(array $status): string
{
    if (!$status['counted']) {
        $title = __('status.not_counted');
        return '<span class="status-dots" title="' . e($title) . '">'
            . '<span class="dot dot-red" title="' . e($title) . '"></span></span>';
    }
    $title = __('status.counted');
    $html = '<span class="status-dots" title="' . e($title) . '">'
        . '<span class="dot dot-green" title="' . e($title) . '"></span>';
    if (($status['pieces_match'] ?? null) === true) {
        $html .= '<span class="dot dot-blue" title="' . e(__('status.matched')) . '"></span>';
    } elseif (($status['pieces_match'] ?? null) === false) {
        $html .= '<span class="dot dot-orange" title="' . e(__('status.mismatched')) . '"></span>';
    } elseif ($status['quantity_match'] === true) {
        $html .= '<span class="dot dot-blue" title="' . e(__('status.matched')) . '"></span>';
    } elseif ($status['quantity_match'] === false) {
        $html .= '<span class="dot dot-orange" title="' . e(__('status.mismatched')) . '"></span>';
    }
    return $html . '</span>';
}

function ensureQuantityColumns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $pdo->query('SELECT quantity FROM inbound_shipments LIMIT 1');
    } catch (PDOException $e) {
        try {
            $pdo->exec(
                'ALTER TABLE inbound_shipments ADD COLUMN quantity INT NOT NULL DEFAULT 0
                 COMMENT \'Product quantity (pieces)\' AFTER total_quantity'
            );
        } catch (PDOException $ignored) {
        }
    }
    try {
        $pdo->query('SELECT quantity_counted FROM counting_records LIMIT 1');
    } catch (PDOException $e) {
        try {
            $pdo->exec(
                'ALTER TABLE counting_records ADD COLUMN quantity_counted INT NOT NULL DEFAULT 0
                 COMMENT \'Counted product quantity\' AFTER total_counted'
            );
        } catch (PDOException $ignored) {
        }
    }
}

function ensureRemarksColumn(PDO $pdo): void
{
    ensureQuantityColumns($pdo);
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $pdo->query('SELECT remarks FROM counting_records LIMIT 1');
    } catch (PDOException $e) {
        try {
            $pdo->exec(
                'ALTER TABLE counting_records ADD COLUMN remarks TEXT DEFAULT NULL
                 COMMENT \'Optional remarks\' AFTER counted_by'
            );
        } catch (PDOException $ignored) {
        }
    }
}
