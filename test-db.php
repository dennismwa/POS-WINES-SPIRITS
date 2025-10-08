<?php
require_once 'config.php';

echo "<h2>Database Test</h2>";

// Test connection
if ($conn->ping()) {
    echo "✓ Database connected successfully<br>";
    echo "Database: " . DB_NAME . "<br>";
    
    // Test if sales table exists
    $result = $conn->query("SHOW TABLES LIKE 'sales'");
    if ($result->num_rows > 0) {
        echo "✓ Sales table exists<br>";
    } else {
        echo "✗ Sales table NOT found<br>";
    }
    
    // Test if we can insert
    $conn->begin_transaction();
    try {
        $testSale = 'TEST-' . time();
        $stmt = $conn->prepare("INSERT INTO sales (sale_number, user_id, subtotal, total_amount, payment_method, sale_date) VALUES (?, 1, 100, 100, 'cash', NOW())");
        $stmt->bind_param("s", $testSale);
        $stmt->execute();
        $id = $conn->insert_id;
        echo "✓ Can insert into sales table (ID: $id)<br>";
        
        // Delete test record
        $conn->query("DELETE FROM sales WHERE id = $id");
        $conn->commit();
        echo "✓ Test record cleaned up<br>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "✗ Error: " . $e->getMessage() . "<br>";
    }
    
} else {
    echo "✗ Database connection failed<br>";
}

// Check session
echo "<h2>Session Test</h2>";
if (isset($_SESSION['user_id'])) {
    echo "✓ User logged in (ID: " . $_SESSION['user_id'] . ")<br>";
} else {
    echo "✗ Not logged in<br>";
}
?>