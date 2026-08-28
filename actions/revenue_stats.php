<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

if (!hasPermission('view_sensitive_stats')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$mode   = $_POST['mode'] ?? 'today';
$from   = $_POST['from'] ?? '';
$to     = $_POST['to'] ?? '';

$today_str = date('Y-m-d');

switch ($mode) {
    case 'today':
        $start = $today_str;
        $end   = $today_str;
        $label = 'Today';
        break;
    case '7days':
        $start = date('Y-m-d', strtotime('-6 days'));
        $end   = $today_str;
        $label = 'Last 7 Days';
        break;
    case '30days':
        $start = date('Y-m-d', strtotime('-29 days'));
        $end   = $today_str;
        $label = 'Last 30 Days';
        break;
    case '90days':
        $start = date('Y-m-d', strtotime('-89 days'));
        $end   = $today_str;
        $label = 'Last 90 Days';
        break;
    case 'custom':
        if (empty($from) || empty($to)) {
            echo json_encode(['success' => false, 'message' => 'Date range required']);
            exit;
        }
        $from_ts = strtotime($from);
        $to_ts   = strtotime($to);
        if (!$from_ts || !$to_ts) {
            echo json_encode(['success' => false, 'message' => 'Invalid dates']);
            exit;
        }
        $start = date('Y-m-d', $from_ts);
        $end   = date('Y-m-d', $to_ts);
        if ($start > $end) { list($start, $end) = [$end, $start]; }
        $label = date('d M', $from_ts) . ' - ' . date('d M Y', $to_ts);
        break;
    default:
        $start = $today_str;
        $end   = $today_str;
        $label = 'Today';
}

$sales      = filterDataByRole('sales', readCSV('sales'));
$sale_items = readCSV('sale_items');

$revenue = 0;
$profit  = 0;
$count   = 0;

foreach ($sales as $s) {
    $sale_date = substr($s['sale_date'], 0, 10);
    if ($sale_date >= $start && $sale_date <= $end) {
        $revenue += (float)$s['total_amount'];
        $count++;
        foreach ($sale_items as $si) {
            if ($si['sale_id'] == $s['id']) {
                $qty = (float)$si['quantity'] - (float)($si['returned_qty'] ?? 0);
                if ($qty > 0) {
                    $buy_p   = (float)($si['avg_buy_price'] ?: $si['buy_price']);
                    $sell_p  = (float)$si['price_per_unit'];
                    $profit += ($sell_p - $buy_p) * $qty;
                }
            }
        }
    }
}

echo json_encode([
    'success'   => true,
    'revenue'   => $revenue,
    'profit'    => $profit,
    'count'     => $count,
    'label'     => $label,
    'start'     => $start,
    'end'       => $end,
    'mode'      => $mode,
    'formatted' => formatCurrency($revenue),
]);
