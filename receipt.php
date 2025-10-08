<?php
// ==================== RECEIPT.PHP ====================
require_once 'config.php';
requireAuth();

$saleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$saleId) {
    die('Invalid sale ID');
}

// Get sale details
$saleQuery = "SELECT s.*, u.name as seller_name FROM sales s JOIN users u ON s.user_id = u.id WHERE s.id = $saleId";
$saleResult = $conn->query($saleQuery);

if ($saleResult->num_rows === 0) {
    die('Sale not found');
}

$sale = $saleResult->fetch_assoc();

// Check access (sellers can only see their own sales)
if ($_SESSION['role'] !== 'owner' && $sale['user_id'] != $_SESSION['user_id']) {
    die('Access denied');
}

// Get sale items
$itemsQuery = "SELECT * FROM sale_items WHERE sale_id = $saleId ORDER BY id";
$itemsResult = $conn->query($itemsQuery);

$settings = getSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo $sale['sale_number']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body {
                margin: 0;
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
            @page {
                margin: 0.5cm;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            max-width: 350px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .receipt-container {
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px dashed #333;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 10px;
            object-fit: contain;
        }
        
        .company-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .receipt-title {
            font-size: 16px;
            font-weight: bold;
            margin: 15px 0 5px;
            text-transform: uppercase;
        }
        
        .sale-info {
            margin-bottom: 20px;
            font-size: 12px;
        }
        
        .sale-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .separator {
            border-top: 1px dashed #333;
            margin: 15px 0;
        }
        
        .separator-solid {
            border-top: 2px solid #333;
            margin: 15px 0;
        }
        
        .items-table {
            width: 100%;
            margin-bottom: 15px;
        }
        
        .items-header {
            display: flex;
            justify-content: space-between;
            padding-bottom: 5px;
            border-bottom: 1px solid #333;
            font-weight: bold;
            font-size: 11px;
        }
        
        .item-row {
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .item-name {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 2px;
        }
        
        .item-details {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #666;
        }
        
        .text-right {
            text-align: right;
        }
        
        .totals {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #333;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .total-line {
            font-size: 18px;
            font-weight: bold;
            padding-top: 8px;
            margin-top: 8px;
            border-top: 2px solid #333;
        }
        
        .payment-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #333;
            font-size: 12px;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px dashed #333;
            font-size: 12px;
        }
        
        .thank-you {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .powered-by {
            margin-top: 10px;
            font-size: 10px;
            color: #666;
        }
        
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-print {
            background-color: <?php echo $settings['primary_color']; ?>;
            color: white;
        }
        
        .btn-close {
            background-color: #6b7280;
            color: white;
        }
        
        @media (max-width: 400px) {
            body {
                margin: 10px;
                padding: 10px;
            }
            .receipt-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Print/Close Buttons -->
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print
        </button>
        <button onclick="window.close()" class="btn btn-close">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <!-- Receipt Content -->
    <div class="receipt-container">
        <div class="receipt-header">
            <img src="<?php echo $settings['logo_path']; ?>" alt="Logo" class="logo" 
                 onerror="this.style.display='none'">
            <div class="company-name"><?php echo htmlspecialchars($settings['company_name']); ?></div>
            <div class="receipt-title">SALES RECEIPT</div>
        </div>
        
        <div class="sale-info">
            <div class="sale-info-row">
                <span>Receipt #:</span>
                <span><strong><?php echo htmlspecialchars($sale['sale_number']); ?></strong></span>
            </div>
            <div class="sale-info-row">
                <span>Date:</span>
                <span><?php echo date('d M Y', strtotime($sale['sale_date'])); ?></span>
            </div>
            <div class="sale-info-row">
                <span>Time:</span>
                <span><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></span>
            </div>
            <div class="sale-info-row">
                <span>Served by:</span>
                <span><?php echo htmlspecialchars($sale['seller_name']); ?></span>
            </div>
        </div>
        
        <div class="separator"></div>
        
        <!-- Items -->
        <div class="items-table">
            <div class="items-header">
                <span style="flex: 2">ITEM</span>
                <span style="width: 50px; text-align: center">QTY</span>
                <span style="width: 70px; text-align: right">PRICE</span>
                <span style="width: 70px; text-align: right">TOTAL</span>
            </div>
            
            <?php while ($item = $itemsResult->fetch_assoc()): ?>
            <div class="item-row">
                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                <div class="item-details">
                    <span style="flex: 2"></span>
                    <span style="width: 50px; text-align: center"><?php echo $item['quantity']; ?></span>
                    <span style="width: 70px; text-align: right"><?php echo number_format($item['unit_price'], 2); ?></span>
                    <span style="width: 70px; text-align: right; font-weight: bold"><?php echo number_format($item['subtotal'], 2); ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <div class="separator"></div>
        
        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span><?php echo $settings['currency_symbol']; ?> <?php echo number_format($sale['subtotal'], 2); ?></span>
            </div>
            <?php if ($sale['tax_amount'] > 0): ?>
            <div class="total-row">
                <span>Tax:</span>
                <span><?php echo $settings['currency_symbol']; ?> <?php echo number_format($sale['tax_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($sale['discount_amount'] > 0): ?>
            <div class="total-row">
                <span>Discount:</span>
                <span>-<?php echo $settings['currency_symbol']; ?> <?php echo number_format($sale['discount_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row total-line">
                <span>TOTAL:</span>
                <span><?php echo $settings['currency_symbol']; ?> <?php echo number_format($sale['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <!-- Payment Info -->
        <div class="payment-info">
            <div class="payment-row">
                <span>Payment Method:</span>
                <span><strong><?php echo strtoupper(str_replace('_', ' ', $sale['payment_method'])); ?></strong></span>
            </div>
            <?php if ($sale['mpesa_reference']): ?>
            <div class="payment-row">
                <span>M-Pesa Ref:</span>
                <span><?php echo htmlspecialchars($sale['mpesa_reference']); ?></span>
            </div>
            <?php endif; ?>
            <div class="payment-row">
                <span>Amount Paid:</span>
                <span><?php echo $settings['currency_symbol']; ?> <?php echo number_format($sale['amount_paid'], 2); ?></span>
            </div>
            <?php if ($sale['change_amount'] > 0): ?>
            <div class="payment-row">
                <span>Change Given:</span>
                <span><?php echo $settings['currency_symbol']; ?> <?php echo number_format($sale['change_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="thank-you">Thank You for Your Business!</div>
            <?php if ($settings['receipt_footer']): ?>
            <div style="white-space: pre-line; margin: 10px 0;"><?php echo nl2br(htmlspecialchars($settings['receipt_footer'])); ?></div>
            <?php endif; ?>
            <div class="separator" style="margin: 15px 0;"></div>
            <div class="powered-by">
                Powered by <?php echo htmlspecialchars($settings['company_name']); ?> POS System<br>
                <?php echo date('Y'); ?> - All Rights Reserved
            </div>
        </div>
    </div>
</body>
</html>