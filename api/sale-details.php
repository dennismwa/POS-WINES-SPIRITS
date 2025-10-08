<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    apiRespond(false, 'Sale ID is required', null, 400);
}

$saleId = intval($_GET['id']);

// Get sale details
$saleQuery = "SELECT s.*, u.name as seller_name 
              FROM sales s 
              JOIN users u ON s.user_id = u.id 
              WHERE s.id = $saleId";
$saleResult = $conn->query($saleQuery);

if (!$saleResult || $saleResult->num_rows === 0) {
    apiRespond(false, 'Sale not found', null, 404);
}

$sale = $saleResult->fetch_assoc();

// Check access (sellers can only see their own sales)
if ($_SESSION['role'] !== 'owner' && $sale['user_id'] != $_SESSION['user_id']) {
    apiRespond(false, 'Access denied', null, 403);
}

// Get sale items
$itemsQuery = "SELECT si.*, p.barcode, p.sku 
               FROM sale_items si 
               LEFT JOIN products p ON si.product_id = p.id 
               WHERE si.sale_id = $saleId 
               ORDER BY si.id";
$itemsResult = $conn->query($itemsQuery);

$items = [];
if ($itemsResult) {
    while ($row = $itemsResult->fetch_assoc()) {
        $items[] = $row;
    }
}

apiRespond(true, 'Sale details retrieved successfully', [
    'sale' => $sale,
    'items' => $items
]);