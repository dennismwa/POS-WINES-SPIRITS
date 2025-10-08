<?php
/**
 * Fixed Complete Sale API
 * api/complete-sale.php
 */

// Disable all output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header first
header('Content-Type: application/json; charset=utf-8');

try {
    // Load config
    if (!file_exists('../config.php')) {
        throw new Exception('Config file not found');
    }
    
    require_once '../config.php';
    
    // Check if we have a database connection
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check authentication
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated. Please login again.',
            'data' => null,
            'timestamp' => time()
        ]);
        exit;
    }
    
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
            'data' => null,
            'timestamp' => time()
        ]);
        exit;
    }
    
    // Validate items
    if (!isset($_POST['items']) || empty($_POST['items'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No items in cart',
            'data' => null,
            'timestamp' => time()
        ]);
        exit;
    }
    
    // Parse items
    $items = json_decode($_POST['items'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format: ' . json_last_error_msg());
    }
    
    if (!is_array($items) || empty($items)) {
        throw new Exception('Items must be a non-empty array');
    }
    
    // Get and validate all POST data
    $paymentMethod = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cash';
    $mpesaReference = isset($_POST['mpesa_reference']) ? trim($_POST['mpesa_reference']) : null;
    $subtotal = isset($_POST['subtotal']) ? floatval($_POST['subtotal']) : 0;
    $taxAmount = isset($_POST['tax_amount']) ? floatval($_POST['tax_amount']) : 0;
    $totalAmount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $amountPaid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0;
    $changeAmount = isset($_POST['change_amount']) ? floatval($_POST['change_amount']) : 0;
    $customerId = isset($_POST['customer_id']) && !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $discountAmount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Sanitize strings
    if (function_exists('sanitize')) {
        $paymentMethod = sanitize($paymentMethod);
        if ($mpesaReference) $mpesaReference = sanitize($mpesaReference);
        if ($notes) $notes = sanitize($notes);
    } else {
        $paymentMethod = htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8');
        if ($mpesaReference) $mpesaReference = htmlspecialchars($mpesaReference, ENT_QUOTES, 'UTF-8');
        if ($notes) $notes = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');
    }
    
    // Validate payment method
    $validMethods = ['cash', 'mpesa', 'mpesa_till', 'card'];
    if (!in_array($paymentMethod, $validMethods)) {
        throw new Exception('Invalid payment method');
    }
    
    // Check M-Pesa reference
    if (in_array($paymentMethod, ['mpesa', 'mpesa_till']) && empty($mpesaReference)) {
        throw new Exception('M-Pesa reference is required');
    }
    
    // Validate amounts
    if ($totalAmount <= 0) {
        throw new Exception('Total amount must be greater than zero');
    }
    
    if ($amountPaid < $totalAmount) {
        throw new Exception('Insufficient payment amount');
    }
    
    // Validate items structure
    foreach ($items as $idx => $item) {
        if (!isset($item['id']) || !isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
            throw new Exception("Invalid item at position " . ($idx + 1));
        }
    }
    
    // Calculate and verify subtotal
    $calculatedSubtotal = 0;
    foreach ($items as $item) {
        $calculatedSubtotal += floatval($item['price']) * intval($item['quantity']);
    }
    
    if (abs($calculatedSubtotal - $subtotal) > 0.02) {
        throw new Exception('Subtotal mismatch');
    }
    
    // Start database transaction
    $conn->begin_transaction();
    
    try {
        // Generate sale number
        $saleNumber = 'ZWS-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        $userId = intval($_SESSION['user_id']);
        $saleDate = date('Y-m-d H:i:s');
        
        // Insert sale
        $sql = "INSERT INTO sales (
            sale_number, user_id, customer_id, subtotal, tax_amount, 
            discount_amount, total_amount, payment_method, mpesa_reference, 
            amount_paid, change_amount, sale_date, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param(
            "siidddsssddss",
            $saleNumber, $userId, $customerId, $subtotal, $taxAmount,
            $discountAmount, $totalAmount, $paymentMethod, $mpesaReference,
            $amountPaid, $changeAmount, $saleDate, $notes
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert sale: ' . $stmt->error);
        }
        
        $saleId = $conn->insert_id;
        $stmt->close();
        
        if (!$saleId) {
            throw new Exception('Failed to get sale ID');
        }
        
        // Process each item
        $stockErrors = [];
        $processedItems = 0;
        
        foreach ($items as $item) {
            $productId = intval($item['id']);
            $productName = trim($item['name']);
            $quantity = intval($item['quantity']);
            $unitPrice = floatval($item['price']);
            $itemDiscount = isset($item['discount']) ? floatval($item['discount']) : 0;
            $itemSubtotal = ($quantity * $unitPrice) - $itemDiscount;
            
            // Check current stock
            $checkStmt = $conn->prepare("SELECT stock_quantity, name FROM products WHERE id = ? LIMIT 1");
            if (!$checkStmt) {
                throw new Exception('Failed to prepare stock check: ' . $conn->error);
            }
            
            $checkStmt->bind_param("i", $productId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows === 0) {
                $checkStmt->close();
                $stockErrors[] = "Product not found: $productName (ID: $productId)";
                continue;
            }
            
            $productData = $result->fetch_assoc();
            $currentStock = intval($productData['stock_quantity']);
            $checkStmt->close();
            
            if ($currentStock < $quantity) {
                $stockErrors[] = "$productName: Stock $currentStock, Need $quantity";
                continue;
            }
            
            // Insert sale item - FIXED: correct parameter count
            $itemStmt = $conn->prepare("INSERT INTO sale_items (
                sale_id, product_id, product_name, quantity, unit_price, discount, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if (!$itemStmt) {
                throw new Exception('Failed to prepare sale item: ' . $conn->error);
            }
            
            // FIXED: Changed from "iisidd" to "iisiddd" (7 parameters, 7 types)
            $itemStmt->bind_param("iisiddd", $saleId, $productId, $productName, $quantity, $unitPrice, $itemDiscount, $itemSubtotal);
            
            if (!$itemStmt->execute()) {
                throw new Exception('Failed to insert sale item: ' . $itemStmt->error);
            }
            $itemStmt->close();
            
            // Update stock
            $updateStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");
            if (!$updateStmt) {
                throw new Exception('Failed to prepare stock update: ' . $conn->error);
            }
            
            $updateStmt->bind_param("iii", $quantity, $productId, $quantity);
            $updateStmt->execute();
            
            if ($updateStmt->affected_rows === 0) {
                $updateStmt->close();
                throw new Exception("Stock update failed for: $productName");
            }
            $updateStmt->close();
            
            // Record stock movement
            $movementStmt = $conn->prepare("INSERT INTO stock_movements (
                product_id, user_id, movement_type, quantity, reference_type, reference_id, notes
            ) VALUES (?, ?, 'sale', ?, 'sale', ?, ?)");
            
            if ($movementStmt) {
                $movementNote = "Sale: $saleNumber";
                $movementStmt->bind_param("iiiis", $productId, $userId, $quantity, $saleId, $movementNote);
                $movementStmt->execute();
                $movementStmt->close();
            }
            
            $processedItems++;
        }
        
        // Check if we had stock errors
        if (!empty($stockErrors)) {
            throw new Exception("Stock errors:\n" . implode("\n", $stockErrors));
        }
        
        if ($processedItems === 0) {
            throw new Exception('No items were processed');
        }
        
        // Update customer if exists
        if ($customerId) {
            $custStmt = $conn->prepare("UPDATE customers SET 
                loyalty_points = loyalty_points + ?,
                total_purchases = total_purchases + ?,
                last_purchase = ?
                WHERE id = ?");
            
            if ($custStmt) {
                $points = floor($totalAmount / 100);
                $custStmt->bind_param("idsi", $points, $totalAmount, $saleDate, $customerId);
                $custStmt->execute();
                $custStmt->close();
            }
        }
        
        // Log activity if function exists
        if (function_exists('logActivity')) {
            try {
                logActivity('SALE_COMPLETED', "Sale $saleNumber completed");
            } catch (Exception $e) {
                // Don't fail if logging fails
                error_log("Activity log failed: " . $e->getMessage());
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Success response
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Sale completed successfully',
            'data' => [
                'sale_id' => $saleId,
                'sale_number' => $saleNumber,
                'total' => $totalAmount,
                'change' => $changeAmount,
                'sale_date' => $saleDate,
                'items_count' => $processedItems
            ],
            'timestamp' => time()
        ]);
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error
    error_log("Complete Sale Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null,
        'timestamp' => time()
    ]);
    exit;
}