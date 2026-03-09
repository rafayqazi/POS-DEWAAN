<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

// Fetch Data
$expenses = readCSV('expenses');

// Filtering Logic
$f_cat = $_GET['category'] ?? '';
$f_from = $_GET['from'] ?? '';
$f_to = $_GET['to'] ?? '';

if ($f_cat) {
    $expenses = array_filter($expenses, function($e) use ($f_cat) {
        return $e['category'] === $f_cat;
    });
}

if ($f_from || $f_to) {
    $expenses = array_filter($expenses, function($e) use ($f_from, $f_to) {
        $eDate = substr($e['date'], 0, 10);
        $matchesFrom = !$f_from || $eDate >= $f_from;
        $matchesTo = !$f_to || $eDate <= $f_to;
        return $matchesFrom && $matchesTo;
    });
}

// Sort Descending (Newest First)
usort($expenses, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

$report_title = "Expenses Report";
$date_range = "All Time";
if ($f_from && $f_to) {
    $date_range = date('d M Y', strtotime($f_from)) . " to " . date('d M Y', strtotime($f_to));
} elseif ($f_from) {
    $date_range = "From " . date('d M Y', strtotime($f_from));
} elseif ($f_to) {
    $date_range = "Until " . date('d M Y', strtotime($f_to));
}

if ($f_cat) {
    $report_title .= " - " . $f_cat;
}

$total_amount = 0;
foreach($expenses as $e) {
    $total_amount += (float)$e['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $report_title ?></title>
    <link rel="icon" type="image/png" href="../<?= getSetting('business_favicon', 'assets/img/favicon.png') ?>">
    <style>
        @page { size: auto; margin: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 0; padding: 1.5cm; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #ef4444; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #ef4444; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
        .header p { margin: 5px 0; color: #666; font-size: 14px; font-weight: 600; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th { background-color: #f8fafc; color: #475569; font-weight: bold; text-align: left; padding: 12px; border: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 10px; border: 1px solid #e2e8f0; vertical-align: middle; }
        tr:nth-child(even) { background-color: #fffafa; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        
        .summary-box { float: right; width: 280px; margin-top: 30px; background: #fff1f2; padding: 20px; border: 1px solid #fecaca; border-radius: 12px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 800; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        @media print {
            body { margin: 0; padding: 1cm; }
            .no-print { display: none; }
            .summary-box { border: 1px solid #fca5a5; background: #fff1f2 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 25px; text-align: right; display: flex; justify-content: flex-end; gap: 12px;">
        <button onclick="window.print()" style="background: #ef4444; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
            Print / Save PDF
        </button>
        <button onclick="window.close()" style="background: #e2e8f0; color: #475569; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold;">
            Close
        </button>
    </div>

    <div id="reportContent">
        <div class="header">
            <h1><?= getSetting('business_name', 'DEWAAN') ?> - EXPENSES</h1>
            <p><?= $report_title ?></p>
            <p style="font-size: 11px; color: #94a3b8;">Period: <?= $date_range ?> | Generated: <?= date('d M Y, h:i A') ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="40" class="text-center">Sr #</th>
                    <th width="100">Date</th>
                    <th width="120">Category</th>
                    <th>Title / Description</th>
                    <th width="120" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 40px; color: #94a3b8;">No expense records found for the selected criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php $sn = 1; foreach ($expenses as $e): ?>
                        <tr>
                            <td class="text-center text-gray-400"><?= $sn++ ?></td>
                            <td class="font-bold"><?= date('d M Y', strtotime($e['date'])) ?></td>
                            <td><span class="badge"><?= htmlspecialchars($e['category']) ?></span></td>
                            <td>
                                <div class="font-bold"><?= htmlspecialchars($e['title']) ?></div>
                                <?php if(!empty($e['description'])): ?>
                                    <div style="font-size: 10px; color: #64748b; margin-top: 2px;"><?= htmlspecialchars($e['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-bold" style="color: #ef4444;"><?= formatCurrency((float)$e['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-row" style="margin-bottom: 12px; border-bottom: 1px solid #fecaca; padding-bottom: 8px;">
                <span style="font-size: 11px; font-weight: bold; color: #991b1b; text-transform: uppercase;">Total Records</span>
                <span style="font-weight: 800; color: #b91c1c;"><?= count($expenses) ?></span>
            </div>
            <div class="summary-row">
                <span style="font-size: 14px; font-weight: 800; color: #991b1b; text-transform: uppercase;">Grand Total</span>
                <span style="font-size: 20px; font-weight: 900; color: #ef4444;"><?= formatCurrency($total_amount) ?></span>
            </div>
        </div>

        <div style="clear: both;"></div>

        <!-- Mandatory Developer Footer -->
        <div style="margin-top:60px; border-top:1px solid #eee; padding-top:20px; text-align:center; font-size:9px; color:#aaa;">
            <p style="margin:0; font-weight:bold; color:#888;">Software Developed by Abdul Rafay</p>
            <p style="margin:4px 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>

</body>
</html>
