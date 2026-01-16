<?php
require_once 'db.php';

echo "<h2>Initializing Excel Database...</h2>";

// Define Schemas (Headers)
$tables = [
    'users' => ['id', 'username', 'password', 'created_at'],
    'products' => ['id', 'name', 'category', 'description', 'buy_price', 'sell_price', 'stock_quantity', 'unit', 'created_at'],
    'dealers' => ['id', 'name', 'phone', 'address', 'created_at'],
    'dealer_transactions' => ['id', 'dealer_id', 'type', 'amount', 'description', 'date', 'created_at'],
    'customers' => ['id', 'name', 'phone', 'address', 'created_at'],
    'sales' => ['id', 'customer_id', 'total_amount', 'paid_amount', 'payment_method', 'sale_date'], // remaining calculated on fly
    'sale_items' => ['id', 'sale_id', 'product_id', 'quantity', 'price_per_unit', 'total_price'],
    'customer_payments' => ['id', 'customer_id', 'amount', 'date', 'notes']
];

foreach ($tables as $table => $headers) {
    initCSV($table, $headers);
    echo "Created table: <strong>$table</strong> (Excel file: data/$table.csv)<br>";
}

// Seed Admin
$users = readCSV('users');
$admin_exists = false;
foreach ($users as $u) {
    if ($u['username'] === 'Deewan') {
        $admin_exists = true; 
        break;
    }
}

if (!$admin_exists) {
    insertCSV('users', [
        'username' => 'Deewan',
        'password' => password_hash('admin', PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    echo "Admin user 'Deewan' created successfully.<br>";
} else {
    echo "Admin user already exists.<br>";
}

echo "<h3>Setup Complete! You can now use the internal Database in Excel format.</h3>";
echo "<a href='../login.php'>Go to Login</a>";
?>
