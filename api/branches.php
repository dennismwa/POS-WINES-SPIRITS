<?php
/**
 * Branch Management API
 * api/branches.php
 */

require_once '../config.php';
requireOwner();

header('Content-Type: application/json');

$action = isset($_POST['action']) ? sanitize($_POST['action']) : '';

// ==================== GET BRANCHES ====================
if ($action === 'get_branches') {
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
    
    $where = [];
    if ($status) {
        $where[] = "status = '$status'";
    }
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT b.*, 
              u.name as manager_name,
              (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND status = 'active') as staff_count,
              (SELECT COUNT(DISTINCT product_id) FROM branch_inventory WHERE branch_id = b.id) as products_count,
              (SELECT SUM(stock_quantity) FROM branch_inventory WHERE branch_id = b.id) as total_stock,
              (SELECT COUNT(*) FROM sales WHERE branch_id = b.id AND DATE(sale_date) = CURDATE()) as today_sales,
              (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE branch_id = b.id AND DATE(sale_date) = CURDATE()) as today_revenue
              FROM branches b
              LEFT JOIN users u ON b.manager_id = u.id
              $whereClause
              ORDER BY b.created_at DESC";
    
    $result = $conn->query($query);
    $branches = [];
    
    while ($row = $result->fetch_assoc()) {
        $branches[] = $row;
    }
    
    respond(true, 'Branches retrieved', ['branches' => $branches]);
}

// ==================== CREATE/UPDATE BRANCH ====================
if ($action === 'save_branch') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = sanitize($_POST['name']);
    $code = strtoupper(sanitize($_POST['code']));
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $phone = sanitize($_POST['phone']);
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : null;
    $manager_id = isset($_POST['manager_id']) && !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
    $status = sanitize($_POST['status']);
    $opening_time = isset($_POST['opening_time']) ? sanitize($_POST['opening_time']) : null;
    $closing_time = isset($_POST['closing_time']) ? sanitize($_POST['closing_time']) : null;
    
    // Validate required fields
    if (empty($name) || empty($code)) {
        respond(false, 'Branch name and code are required', null, 400);
    }
    
    // Check if code exists (excluding current record)
    $checkCode = $conn->prepare("SELECT id FROM branches WHERE code = ? AND id != ?");
    $checkCode->bind_param("si", $code, $id);
    $checkCode->execute();
    if ($checkCode->get_result()->num_rows > 0) {
        respond(false, 'Branch code already exists', null, 400);
    }
    $checkCode->close();
    
    if ($id > 0) {
        // Update existing branch
        $stmt = $conn->prepare("UPDATE branches SET 
            name=?, code=?, address=?, city=?, phone=?, email=?, 
            manager_id=?, status=?, opening_time=?, closing_time=?
            WHERE id=?");
        $stmt->bind_param("ssssssisssi", $name, $code, $address, $city, $phone, $email, 
                         $manager_id, $status, $opening_time, $closing_time, $id);
        
        if ($stmt->execute()) {
            logActivity('BRANCH_UPDATED', "Updated branch: $name");
            respond(true, 'Branch updated successfully');
        } else {
            respond(false, 'Failed to update branch', null, 500);
        }
    } else {
        // Create new branch
        $stmt = $conn->prepare("INSERT INTO branches 
            (name, code, address, city, phone, email, manager_id, status, opening_time, closing_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssss", $name, $code, $address, $city, $phone, $email, 
                         $manager_id, $status, $opening_time, $closing_time);
        
        if ($stmt->execute()) {
            $branchId = $conn->insert_id;
            
            // Copy all products from main branch with 0 stock
            $conn->query("INSERT INTO branch_inventory (branch_id, product_id, stock_quantity, reorder_level)
                         SELECT $branchId, id, 0, reorder_level 
                         FROM products WHERE status = 'active'");
            
            logActivity('BRANCH_CREATED', "Created branch: $name");
            respond(true, 'Branch created successfully', ['branch_id' => $branchId]);
        } else {
            respond(false, 'Failed to create branch', null, 500);
        }
    }
    $stmt->close();
}

// ==================== DELETE BRANCH ====================
if ($action === 'delete_branch') {
    $id = intval($_POST['id']);
    
    // Check if it's the main branch
    $checkMain = $conn->query("SELECT code FROM branches WHERE id = $id");
    $branch = $checkMain->fetch_assoc();
    
    if ($branch['code'] === 'MAIN') {
        respond(false, 'Cannot delete the main branch', null, 400);
    }
    
    // Check if branch has active sales
    $checkSales = $conn->query("SELECT COUNT(*) as count FROM sales WHERE branch_id = $id");
    $salesCount = $checkSales->fetch_assoc()['count'];
    
    if ($salesCount > 0) {
        respond(false, "Cannot delete branch with $salesCount sales records. Deactivate instead.", null, 400);
    }
    
    $conn->begin_transaction();
    
    try {
        // Delete branch inventory
        $conn->query("DELETE FROM branch_inventory WHERE branch_id = $id");
        
        // Unassign users
        $conn->query("UPDATE users SET branch_id = NULL WHERE branch_id = $id");
        
        // Delete branch
        $conn->query("DELETE FROM branches WHERE id = $id");
        
        $conn->commit();
        logActivity('BRANCH_DELETED', "Deleted branch ID: $id");
        respond(true, 'Branch deleted successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        respond(false, 'Failed to delete branch: ' . $e->getMessage(), null, 500);
    }
}

// ==================== GET BRANCH INVENTORY ====================
if ($action === 'get_branch_inventory') {
    $branchId = intval($_GET['branch_id']);
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $stockFilter = isset($_GET['stock_filter']) ? sanitize($_GET['stock_filter']) : 'all';
    
    $where = ["bi.branch_id = $branchId"];
    
    if ($search) {
        $where[] = "(p.name LIKE '%$search%' OR p.barcode LIKE '%$search%' OR p.sku LIKE '%$search%')";
    }
    
    if ($stockFilter === 'low') {
        $where[] = "bi.stock_quantity <= bi.reorder_level AND bi.stock_quantity > 0";
    } elseif ($stockFilter === 'out') {
        $where[] = "bi.stock_quantity = 0";
    } elseif ($stockFilter === 'in_stock') {
        $where[] = "bi.stock_quantity > bi.reorder_level";
    }
    
    $whereClause = implode(' AND ', $where);
    
    $query = "SELECT bi.*, p.name, p.barcode, p.sku, p.selling_price, p.cost_price,
              c.name as category_name
              FROM branch_inventory bi
              JOIN products p ON bi.product_id = p.id
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE $whereClause
              ORDER BY p.name ASC";
    
    $result = $conn->query($query);
    $inventory = [];
    
    while ($row = $result->fetch_assoc()) {
        $inventory[] = $row;
    }
    
    respond(true, 'Inventory retrieved', ['inventory' => $inventory]);
}

// ==================== INITIATE STOCK TRANSFER ====================
if ($action === 'initiate_transfer') {
    $fromBranchId = intval($_POST['from_branch_id']);
    $toBranchId = intval($_POST['to_branch_id']);
    $productId = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
    
    if ($fromBranchId === $toBranchId) {
        respond(false, 'Cannot transfer to the same branch', null, 400);
    }
    
    if ($quantity <= 0) {
        respond(false, 'Quantity must be greater than zero', null, 400);
    }
    
    $conn->begin_transaction();
    
    try {
        // Check source branch stock
        $checkStock = $conn->prepare("SELECT stock_quantity FROM branch_inventory 
                                      WHERE branch_id = ? AND product_id = ?");
        $checkStock->bind_param("ii", $fromBranchId, $productId);
        $checkStock->execute();
        $stockResult = $checkStock->get_result();
        
        if ($stockResult->num_rows === 0) {
            throw new Exception('Product not found in source branch');
        }
        
        $currentStock = $stockResult->fetch_assoc()['stock_quantity'];
        $checkStock->close();
        
        if ($currentStock < $quantity) {
            throw new Exception("Insufficient stock. Available: $currentStock");
        }
        
        // Generate transfer number
        $transferNumber = 'TRF-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        
        // Create transfer record
        $stmt = $conn->prepare("INSERT INTO stock_transfers 
            (transfer_number, from_branch_id, to_branch_id, product_id, quantity, transfer_date, initiated_by, status, notes)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, 'pending', ?)");
        $userId = $_SESSION['user_id'];
        $stmt->bind_param("siiiiis", $transferNumber, $fromBranchId, $toBranchId, $productId, $quantity, $userId, $notes);
        $stmt->execute();
        $transferId = $conn->insert_id;
        $stmt->close();
        
        // Deduct from source branch
        $conn->query("UPDATE branch_inventory 
                     SET stock_quantity = stock_quantity - $quantity 
                     WHERE branch_id = $fromBranchId AND product_id = $productId");
        
        $conn->commit();
        logActivity('STOCK_TRANSFER_INITIATED', "Transfer #$transferNumber: $quantity units");
        respond(true, 'Transfer initiated successfully', ['transfer_id' => $transferId, 'transfer_number' => $transferNumber]);
        
    } catch (Exception $e) {
        $conn->rollback();
        respond(false, $e->getMessage(), null, 500);
    }
}

// ==================== COMPLETE STOCK TRANSFER ====================
if ($action === 'complete_transfer') {
    $transferId = intval($_POST['transfer_id']);
    
    $conn->begin_transaction();
    
    try {
        // Get transfer details
        $stmt = $conn->prepare("SELECT * FROM stock_transfers WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $transferId);
        $stmt->execute();
        $transfer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$transfer) {
            throw new Exception('Transfer not found or already completed');
        }
        
        // Add to destination branch
        $checkDest = $conn->query("SELECT id FROM branch_inventory 
                                  WHERE branch_id = {$transfer['to_branch_id']} 
                                  AND product_id = {$transfer['product_id']}");
        
        if ($checkDest->num_rows > 0) {
            $conn->query("UPDATE branch_inventory 
                         SET stock_quantity = stock_quantity + {$transfer['quantity']}
                         WHERE branch_id = {$transfer['to_branch_id']} 
                         AND product_id = {$transfer['product_id']}");
        } else {
            $conn->query("INSERT INTO branch_inventory (branch_id, product_id, stock_quantity, reorder_level)
                         SELECT {$transfer['to_branch_id']}, {$transfer['product_id']}, {$transfer['quantity']}, reorder_level
                         FROM products WHERE id = {$transfer['product_id']}");
        }
        
        // Update transfer status
        $userId = $_SESSION['user_id'];
        $conn->query("UPDATE stock_transfers 
                     SET status = 'completed', received_by = $userId, completed_at = NOW() 
                     WHERE id = $transferId");
        
        $conn->commit();
        logActivity('STOCK_TRANSFER_COMPLETED', "Transfer #{$transfer['transfer_number']} completed");
        respond(true, 'Transfer completed successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        respond(false, $e->getMessage(), null, 500);
    }
}

// ==================== GET TRANSFERS ====================
if ($action === 'get_transfers') {
    $branchId = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
    
    $where = [];
    if ($branchId > 0) {
        $where[] = "(st.from_branch_id = $branchId OR st.to_branch_id = $branchId)";
    }
    if ($status) {
        $where[] = "st.status = '$status'";
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT st.*, 
              p.name as product_name, p.barcode, p.sku,
              bf.name as from_branch_name, bt.name as to_branch_name,
              ui.name as initiated_by_name, ur.name as received_by_name
              FROM stock_transfers st
              JOIN products p ON st.product_id = p.id
              JOIN branches bf ON st.from_branch_id = bf.id
              JOIN branches bt ON st.to_branch_id = bt.id
              JOIN users ui ON st.initiated_by = ui.id
              LEFT JOIN users ur ON st.received_by = ur.id
              $whereClause
              ORDER BY st.created_at DESC
              LIMIT 100";
    
    $result = $conn->query($query);
    $transfers = [];
    
    while ($row = $result->fetch_assoc()) {
        $transfers[] = $row;
    }
    
    respond(true, 'Transfers retrieved', ['transfers' => $transfers]);
}

respond(false, 'Invalid action', null, 400);
?>
