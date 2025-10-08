<?php
/**
 * Debug Checker
 * Save as debug.php in root directory
 */
session_start();

echo "<!DOCTYPE html><html><head><title>Debug Info</title>";
echo "<style>body{font-family:monospace;padding:20px;} .section{background:#f5f5f5;padding:15px;margin:10px 0;border-radius:5px;} .success{color:green;} .error{color:red;} .warning{color:orange;}</style>";
echo "</head><body>";

echo "<h1>System Debug Information</h1>";

// 1. PHP Version
echo "<div class='section'><h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo (version_compare(PHP_VERSION, '7.0.0') >= 0) ? "<span class='success'>✓ PHP version is OK</span>" : "<span class='error'>✗ PHP version too old</span>";
echo "</div>";

// 2. Required Extensions
echo "<div class='section'><h2>2. Required Extensions</h2>";
$required = ['mysqli', 'json', 'session'];
foreach ($required as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? "<span class='success'>✓ Loaded</span>" : "<span class='error'>✗ Missing</span>") . "<br>";
}
echo "</div>";

// 3. Database Connection
echo "<div class='section'><h2>3. Database Connection</h2>";
try {
    require_once 'config.php';
    if ($conn && $conn->ping()) {
        echo "<span class='success'>✓ Database connected</span><br>";
        echo "Database Name: " . DB_NAME . "<br>";
        
        // Check tables
        $tables = ['sales', 'sale_items', 'products', 'stock_movements', 'users'];
        echo "<br><strong>Tables:</strong><br>";
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            echo "$table: " . ($result && $result->num_rows > 0 ? "<span class='success'>✓ Exists</span>" : "<span class='error'>✗ Missing</span>") . "<br>";
        }
    } else {
        echo "<span class='error'>✗ Database connection failed</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// 4. Session Info
echo "<div class='section'><h2>4. Session Information</h2>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "<span class='success'>✓ Active</span>" : "<span class='error'>✗ Inactive</span>") . "<br>";
echo "User ID: " . (isset($_SESSION['user_id']) ? "<span class='success'>" . $_SESSION['user_id'] . "</span>" : "<span class='error'>Not logged in</span>") . "<br>";
echo "User Name: " . (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : "N/A") . "<br>";
echo "User Role: " . (isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : "N/A") . "<br>";
echo "</div>";

// 5. File Permissions
echo "<div class='section'><h2>5. File Permissions</h2>";
$files = [
    'config.php' => 'config.php',
    'api/complete-sale.php' => 'api/complete-sale.php',
    'pos.php' => 'pos.php'
];

foreach ($files as $label => $path) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        echo "$label: <span class='success'>✓ Exists</span> (Permissions: $perms)<br>";
    } else {
        echo "$label: <span class='error'>✗ Not found</span><br>";
    }
}
echo "</div>";

// 6. Error Log
echo "<div class='section'><h2>6. Recent Errors</h2>";
$error_log = ini_get('error_log');
echo "Error Log Path: " . ($error_log ?: 'Not configured') . "<br><br>";

$log_files = [
    __DIR__ . '/error.log',
    __DIR__ . '/error_log',
    $error_log
];

$found_errors = false;
foreach ($log_files as $log_file) {
    if ($log_file && file_exists($log_file) && is_readable($log_file)) {
        $found_errors = true;
        echo "<strong>Log: $log_file</strong><br>";
        echo "<pre style='background:white;padding:10px;overflow:auto;max-height:300px;'>";
        $lines = file($log_file);
        $recent = array_slice($lines, -20); // Last 20 lines
        echo htmlspecialchars(implode('', $recent));
        echo "</pre>";
    }
}

if (!$found_errors) {
    echo "<span class='warning'>No error logs found or readable</span>";
}
echo "</div>";

// 7. Test Sale
echo "<div class='section'><h2>7. Test Sale API</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<form method='POST' action='api/complete-sale.php' target='_blank'>";
    echo "<input type='hidden' name='items' value='[{\"id\":1,\"name\":\"Test Product\",\"price\":100,\"quantity\":1}]'>";
    echo "<input type='hidden' name='subtotal' value='100'>";
    echo "<input type='hidden' name='tax_amount' value='0'>";
    echo "<input type='hidden' name='total_amount' value='100'>";
    echo "<input type='hidden' name='payment_method' value='cash'>";
    echo "<input type='hidden' name='amount_paid' value='100'>";
    echo "<input type='hidden' name='change_amount' value='0'>";
    echo "<button type='submit'>Test Sale API</button>";
    echo "</form>";
} else {
    echo "<span class='warning'>Please login first to test the API</span>";
}
echo "</div>";

// 8. Functions Check
echo "<div class='section'><h2>8. Required Functions</h2>";
$functions = ['sanitize', 'logActivity', 'generateSaleNumber', 'getCurrentDateTime', 'apiRespond'];
foreach ($functions as $func) {
    echo "$func: " . (function_exists($func) ? "<span class='success'>✓ Exists</span>" : "<span class='error'>✗ Missing</span>") . "<br>";
}
echo "</div>";

echo "</body></html>";
?>