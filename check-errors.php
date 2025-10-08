<?php
// check-errors.php
echo "<h2>PHP Error Log</h2>";
echo "<pre>";

// Try to find and display error log
$error_log_paths = [
    __DIR__ . '/error.log',
    __DIR__ . '/error_log',
    ini_get('error_log'),
    '/tmp/error_log'
];

foreach ($error_log_paths as $path) {
    if (file_exists($path)) {
        echo "=== Error log found at: $path ===\n\n";
        echo file_get_contents($path);
        echo "\n\n";
    }
}

echo "</pre>";

echo "<h2>PHP Info</h2>";
echo "Display Errors: " . ini_get('display_errors') . "<br>";
echo "Error Reporting: " . ini_get('error_reporting') . "<br>";
echo "Log Errors: " . ini_get('log_errors') . "<br>";
echo "Error Log: " . ini_get('error_log') . "<br>";
?>