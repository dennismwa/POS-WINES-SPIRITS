<?php
/**
 * System Diagnostic Tool
 * DELETE THIS FILE AFTER FIXING ISSUES FOR SECURITY
 */

// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #ea580c; }
        .test-item { padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ccc; }
        .success { background: #d4edda; border-color: #28a745; }
        .warning { background: #fff3cd; border-color: #ffc107; }
        .error { background: #f8d7da; border-color: #dc3545; }
        .info { background: #d1ecf1; border-color: #17a2b8; }
        .test-title { font-weight: bold; margin-bottom: 5px; }
        .test-details { font-size: 14px; color: #666; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 System Diagnostics</h1>
        <p><strong>WARNING:</strong> Delete this file after fixing issues!</p>
        <hr>

<?php
// Test 1: PHP Version
echo '<div class="test-item ' . (version_compare(PHP_VERSION, '7.4.0', '>=') ? 'success' : 'error') . '">';
echo '<div class="test-title">PHP Version</div>';
echo '<div class="test-details">Current: ' . PHP_VERSION . ' (Required: 7.4+)</div>';
echo '</div>';

// Test 2: Required Extensions
$required_extensions = ['mysqli', 'json', 'session'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo '<div class="test-item ' . ($loaded ? 'success' : 'error') . '">';
    echo '<div class="test-title">' . ($loaded ? '✓' : '✗') . ' PHP Extension: ' . $ext . '</div>';
    echo '<div class="test-details">' . ($loaded ? 'Loaded' : 'NOT LOADED') . '</div>';
    echo '</div>';
}

// Test 3: Database Connection
echo '<div class="test-item ';
try {
    $conn = new mysqli('localhost', 'vxjtgclw_Spirits', 'SGL~3^5O?]Xie%!6', 'vxjtgclw_Spirits');
    if ($conn->connect_error) {
        echo 'error"><div class="test-title">✗ Database Connection</div>';
        echo '<div class="test-details">Failed: ' . htmlspecialchars($conn->connect_error) . '</div>';
    } else {
        echo 'success"><div class="test-title">✓ Database Connection</div>';
        echo '<div class="test-details">Connected successfully</div>';
        
        // Check users
        $result = $conn->query("SELECT id, name, pin_code, role FROM users WHERE status='active'");
        if ($result) {
            echo '</div><div class="test-item info"><div class="test-title">Active Users:</div><div class="test-details">';
            while ($user = $result->fetch_assoc()) {
                echo "• " . htmlspecialchars($user['name']) . " (PIN: " . htmlspecialchars($user['pin_code']) . ", Role: " . $user['role'] . ")<br>";
            }
            echo '</div>';
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo 'error"><div class="test-title">✗ Database Connection</div>';
    echo '<div class="test-details">Exception: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Test 4: Session Test
echo '<div class="test-item ';
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo 'success"><div class="test-title">✓ Session Working</div>';
    echo '<div class="test-details">Session started successfully</div>';
} catch (Exception $e) {
    echo 'error"><div class="test-title">✗ Session Failed</div>';
    echo '<div class="test-details">' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div>';

// Test 5: File Permissions
$files_to_check = ['config.php', 'index.php', '.htaccess'];
foreach ($files_to_check as $file) {
    $exists = file_exists($file);
    echo '<div class="test-item ' . ($exists ? 'success' : 'error') . '">';
    echo '<div class="test-title">' . ($exists ? '✓' : '✗') . ' File: ' . $file . '</div>';
    if ($exists) {
        echo '<div class="test-details">Permissions: ' . substr(sprintf('%o', fileperms($file)), -4) . '</div>';
    } else {
        echo '<div class="test-details">File does not exist!</div>';
    }
    echo '</div>';
}

// Test 6: Error Log
echo '<div class="test-item info">';
echo '<div class="test-title">📋 Recent Errors (Last 10 lines)</div>';
echo '<div class="test-details"><pre style="background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 300px;">';
if (file_exists('error.log')) {
    $lines = file('error.log');
    $recent = array_slice($lines, -10);
    echo htmlspecialchars(implode('', $recent));
} else {
    echo 'No error log found';
}
echo '</pre></div>';
echo '</div>';

?>
        <div class="test-item warning">
            <div class="test-title">⚡ Next Steps</div>
            <div class="test-details">
                <ol>
                    <li>Note the valid PINs shown above</li>
                    <li>Try logging in with one of those PINs</li>
                    <li>If login fails, check the error log above</li>
                    <li><strong>DELETE this diagnostic.php file when done!</strong></li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>
<?php
ob_end_flush();
?>