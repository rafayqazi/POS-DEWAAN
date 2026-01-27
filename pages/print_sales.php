<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

// Manual Join
$sales = readCSV('sales');
$customers = readCSV('customers');
$c_map = [];
foreach($customers as $c) $c_map[$c['id']] = $c['name'];

// Filtering Logic
$f_type = $_GET['f_type'] ?? 'month';
if ($f_type == 'range' && !empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to = $_GET['to'];
    $sales = array_filter($sales, function($s) use ($from, $to) {
        $date = date('Y-m-d', strtotime($s['sale_date']));
        return $date >= $from && $date <= $to;
    });
} elseif ($f_type == 'year' && !empty($_GET['year'])) {
    $year = $_GET['year'];
    $sales = array_filter($sales, function($s) use ($year) {
        return strpos($s['sale_date'], $year) === 0;
    });
} elseif ($f_type == 'month' && !empty($_GET['month'])) {
    $month = $_GET['month'];
    $sales = array_filter($sales, function($s) use ($month) {
        return strpos($s['sale_date'], $month) === 0;
    });
} elseif (isset($_GET['date'])) {
    $target_date = $_GET['date'];
    $sales = array_filter($sales, function($s) use ($target_date) {
        return strpos($s['sale_date'], $target_date) === 0;
    });
}

// Sort Descending
usort($sales, function($a, $b) {
    return strtotime($b['sale_date']) - strtotime($a['sale_date']);
});

$report_title = "Sales Report";
if ($f_type == 'range') {
    $report_title .= " (" . date('d M Y', strtotime($from)) . " to " . date('d M Y', strtotime($to)) . ")";
} elseif ($f_type == 'year') {
    $report_title .= " - Year " . $year;
} elseif ($f_type == 'month') {
    $report_title .= " - " . date('F Y', strtotime($month . '-01'));
} elseif (isset($_GET['date'])) {
    $report_title .= " - " . date('d M Y', strtotime($_GET['date']));
} else {
    $report_title .= " - All Time";
}

$total_collected = 0;
foreach($sales as $s) {
    $total_collected += (float)$s['paid_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $report_title ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #0d9488; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #0d9488; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; font-size: 14px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th { background-color: #f1f5f9; color: #475569; font-weight: bold; text-align: left; padding: 12px; border: 1px solid #e2e8f0; text-transform: uppercase; }
        td { padding: 10px; border: 1px solid #e2e8f0; vertical-align: middle; }
        tr:nth-child(even) { background-color: #f8fafc; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        
        .footer { margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 20px; font-size: 12px; color: #64748b; display: flex; justify-content: justify-between; }
        
        .summary-box { float: right; width: 250px; margin-top: 20px; background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .summary-box { border: 1px solid #ccc; }
        }
        
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-paid { background-color: #dcfce7; color: #166534; }
        .badge-due { background-color: #fee2e2; color: #991b1b; }

        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .summary-box { border: 1px solid #ccc; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; text-align: right; display: flex; justify-content: flex-end; gap: 10px;">
        <button onclick="downloadPDF()" style="background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; display: flex; items-center: center;">
            <i class="fas fa-file-pdf" style="margin-right: 8px;"></i> Download PDF
        </button>
        <button onclick="window.print()" style="background: #0d9488; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold;">
            <i class="fas fa-print" style="margin-right: 8px;"></i> Print Report
        </button>
        <button onclick="window.close()" style="background: #e2e8f0; color: #475569; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold;">
            Close
        </button>
    </div>

    <div id="reportContent" style="position: relative;">
        <div class="header">
            <h1>Fashion Shines - MANAGEMENT SYSTEM</h1>
            <p><?= $report_title ?></p>
            <p>Generated on: <?= date('d M Y, h:i A') ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="40">Sr #</th>
                    <th>Date & Time</th>
                    <th>Customer Name</th>
                    <th class="text-right">Total Amount</th>
                    <th class="text-right">Paid Amount</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach ($sales as $s): 
                    $is_due = $s['total_amount'] > $s['paid_amount'];
                ?>
                    <tr>
                        <td class="text-center"><?= $sn++ ?></td>
                        <td><?= date('d M Y, h:i A', strtotime($s['sale_date'])) ?></td>
                        <td><?= isset($c_map[$s['customer_id']]) ? htmlspecialchars($c_map[$s['customer_id']]) : 'Walk-in Customer' ?></td>
                        <td class="text-right font-bold"><?= formatCurrency((float)$s['total_amount']) ?></td>
                        <td class="text-right"><?= formatCurrency((float)$s['paid_amount']) ?></td>
                        <td class="text-center">
                            <span class="badge <?= $is_due ? 'badge-due' : 'badge-paid' ?>">
                                <?= $is_due ? 'DUE' : 'PAID' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-row font-bold" style="color: #0d9488; font-size: 16px; margin-top: 10px; border-top: 1px solid #cbd5e1; pt: 10px;">
                <span>Total Collected:</span>
                <span><?= formatCurrency($total_collected) ?></span>
            </div>
            <div class="summary-row" style="font-size: 12px; color: #64748b; margin-top: 5px;">
                <span>Total Sales In List:</span>
                <span><?= count($sales) ?></span>
            </div>
        </div>

        <div style="clear: both;"></div>

        <div style="border-top: 1px solid #ddd; margin-top: 30px; padding-top: 10px; text-align: center; font-size: 10px; color: #888;">
            <p style="margin: 0; font-weight: bold;">Software by Abdul Rafay</p>
            <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>

    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.getElementById('reportContent');
            const opt = {
                margin:       [0.5, 0.5, 0.5, 0.5],
                filename:     '<?= str_replace(' ', '_', strtolower($report_title)) ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>

    <script>
        // Auto trigger print dialog on load if not already printing
        window.onload = function() {
            // setTimeout(() => window.print(), 500);
        };
    </script>
</body>
</html>
