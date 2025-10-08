<?php
require_once 'config.php';
requireAuth();

$page_title = 'Sales History';
$settings = getSettings();
$isOwner = $_SESSION['role'] === 'owner';

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$userFilter = isset($_GET['user']) ? intval($_GET['user']) : 0;
$paymentFilter = isset($_GET['payment']) ? sanitize($_GET['payment']) : '';
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$where = ["DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'"];

if (!$isOwner) {
    $where[] = "s.user_id = " . $_SESSION['user_id'];
} elseif ($userFilter > 0) {
    $where[] = "s.user_id = $userFilter";
}

if ($paymentFilter) {
    $where[] = "s.payment_method = '$paymentFilter'";
}

if ($searchQuery) {
    $where[] = "(s.sale_number LIKE '%$searchQuery%' OR s.mpesa_reference LIKE '%$searchQuery%')";
}

$whereClause = implode(' AND ', $where);

// Get summary stats
$statsQuery = "SELECT 
    COUNT(*) as total_sales,
    COALESCE(SUM(total_amount), 0) as total_revenue,
    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_sales,
    COALESCE(SUM(CASE WHEN payment_method IN ('mpesa', 'mpesa_till') THEN total_amount ELSE 0 END), 0) as mpesa_sales,
    COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END), 0) as card_sales,
    COALESCE(AVG(total_amount), 0) as avg_sale
    FROM sales s WHERE $whereClause";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Get sales with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

$salesQuery = "SELECT s.*, u.name as seller_name, COUNT(si.id) as item_count 
               FROM sales s 
               JOIN users u ON s.user_id = u.id 
               LEFT JOIN sale_items si ON s.id = si.sale_id 
               WHERE $whereClause 
               GROUP BY s.id 
               ORDER BY s.sale_date DESC 
               LIMIT $perPage OFFSET $offset";
$salesResult = $conn->query($salesQuery);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM sales s WHERE $whereClause";
$countResult = $conn->query($countQuery);
$totalSales = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalSales / $perPage);

// Get users for filter (owner only)
$users = [];
if ($isOwner) {
    $usersResult = $conn->query("SELECT id, name FROM users WHERE status = 'active' ORDER BY name");
    while ($row = $usersResult->fetch_assoc()) {
        $users[] = $row;
    }
}

include 'header.php';
?>

<style>
.stat-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.filter-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

.sales-table {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.payment-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}
</style>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Sales</p>
                <h3 class="text-3xl font-bold text-gray-900"><?php echo $stats['total_sales']; ?></h3>
                <p class="text-xs text-gray-500 mt-1">transactions</p>
            </div>
            <div class="stat-icon bg-blue-100">
                <i class="fas fa-receipt text-blue-600"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Revenue</p>
                <h3 class="text-2xl font-bold" style="color: <?php echo $settings['primary_color']; ?>">
                    <?php echo formatCurrency($stats['total_revenue']); ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1">period total</p>
            </div>
            <div class="stat-icon" style="background-color: <?php echo $settings['primary_color']; ?>20;">
                <i class="fas fa-money-bill-wave" style="color: <?php echo $settings['primary_color']; ?>"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Average Sale</p>
                <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($stats['avg_sale']); ?></h3>
                <p class="text-xs text-gray-500 mt-1">per transaction</p>
            </div>
            <div class="stat-icon bg-green-100">
                <i class="fas fa-chart-line text-green-600"></i>
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Payment Split</p>
                <div class="flex gap-2 mt-2">
                    <div class="text-center">
                        <p class="text-xs text-gray-500">Cash</p>
                        <p class="text-sm font-bold text-green-600"><?php echo $stats['total_revenue'] > 0 ? round(($stats['cash_sales'] / $stats['total_revenue']) * 100) : 0; ?>%</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-500">M-Pesa</p>
                        <p class="text-sm font-bold text-blue-600"><?php echo $stats['total_revenue'] > 0 ? round(($stats['mpesa_sales'] / $stats['total_revenue']) * 100) : 0; ?>%</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-500">Card</p>
                        <p class="text-sm font-bold text-purple-600"><?php echo $stats['total_revenue'] > 0 ? round(($stats['card_sales'] / $stats['total_revenue']) * 100) : 0; ?>%</p>
                    </div>
                </div>
            </div>
            <div class="stat-icon bg-purple-100">
                <i class="fas fa-chart-pie text-purple-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filter-card">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">From Date</label>
            <input type="date" name="date_from" value="<?php echo $dateFrom; ?>" 
                   class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none transition"
                   style="focus:border-color: <?php echo $settings['primary_color']; ?>">
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">To Date</label>
            <input type="date" name="date_to" value="<?php echo $dateTo; ?>" 
                   class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none transition"
                   style="focus:border-color: <?php echo $settings['primary_color']; ?>">
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Sale # or M-Pesa ref..." 
                   class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none transition"
                   style="focus:border-color: <?php echo $settings['primary_color']; ?>">
        </div>
        
        <?php if ($isOwner): ?>
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Seller</label>
            <select name="user" class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none transition">
                <option value="">All Sellers</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>" <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="<?php echo $isOwner ? 'md:col-span-2' : 'md:col-span-4'; ?>">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment</label>
            <select name="payment" class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none transition">
                <option value="">All Methods</option>
                <option value="cash" <?php echo $paymentFilter == 'cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="mpesa" <?php echo $paymentFilter == 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
                <option value="mpesa_till" <?php echo $paymentFilter == 'mpesa_till' ? 'selected' : ''; ?>>M-Pesa Till</option>
                <option value="card" <?php echo $paymentFilter == 'card' ? 'selected' : ''; ?>>Card</option>
            </select>
        </div>
        
        <div class="md:col-span-2 flex gap-2">
            <button type="submit" 
                    class="flex-1 px-6 py-2 rounded-lg font-semibold text-white transition hover:opacity-90"
                    style="background-color: <?php echo $settings['primary_color']; ?>">
                <i class="fas fa-filter mr-2"></i>Filter
            </button>
            <a href="/sales.php" 
               class="px-4 py-2 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition flex items-center justify-center">
                <i class="fas fa-redo"></i>
            </a>
        </div>
    </form>
</div>

<!-- Sales Table -->
<div class="sales-table">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
                <tr>
                    <th class="text-left py-4 px-6 text-sm font-bold text-gray-700">Sale #</th>
                    <th class="text-left py-4 px-6 text-sm font-bold text-gray-700">Date & Time</th>
                    <?php if ($isOwner): ?>
                    <th class="text-left py-4 px-6 text-sm font-bold text-gray-700">Seller</th>
                    <?php endif; ?>
                    <th class="text-left py-4 px-6 text-sm font-bold text-gray-700">Payment</th>
                    <th class="text-center py-4 px-6 text-sm font-bold text-gray-700">Items</th>
                    <th class="text-right py-4 px-6 text-sm font-bold text-gray-700">Total</th>
                    <th class="text-center py-4 px-6 text-sm font-bold text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($salesResult && $salesResult->num_rows > 0): 
                    $paymentColors = [
                        'cash' => ['bg' => 'bg-green-100', 'text' => 'text-green-800'],
                        'mpesa' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800'],
                        'mpesa_till' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800'],
                        'card' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800']
                    ];
                    while ($sale = $salesResult->fetch_assoc()):
                        $colors = $paymentColors[$sale['payment_method']];
                ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                    <td class="py-4 px-6">
                        <button onclick="viewSaleDetails(<?php echo $sale['id']; ?>)" 
                                class="font-semibold text-sm hover:underline transition"
                                style="color: <?php echo $settings['primary_color']; ?>">
                            <?php echo htmlspecialchars($sale['sale_number']); ?>
                        </button>
                    </td>
                    <td class="py-4 px-6">
                        <div class="text-sm">
                            <div class="font-semibold text-gray-900"><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></div>
                            <div class="text-gray-500 text-xs"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></div>
                        </div>
                    </td>
                    <?php if ($isOwner): ?>
                    <td class="py-4 px-6">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold" 
                                 style="background-color: <?php echo $settings['primary_color']; ?>">
                                <?php echo strtoupper(substr($sale['seller_name'], 0, 2)); ?>
                            </div>
                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sale['seller_name']); ?></span>
                        </div>
                    </td>
                    <?php endif; ?>
                    <td class="py-4 px-6">
                        <span class="payment-badge <?php echo $colors['bg'] . ' ' . $colors['text']; ?>">
                            <?php echo strtoupper(str_replace('_', ' ', $sale['payment_method'])); ?>
                        </span>
                    </td>
                    <td class="py-4 px-6 text-center">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 text-gray-700 font-bold">
                            <?php echo $sale['item_count']; ?>
                        </span>
                    </td>
                    <td class="py-4 px-6 text-right">
                        <span class="font-bold text-gray-900 text-lg"><?php echo formatCurrency($sale['total_amount']); ?></span>
                    </td>
                    <td class="py-4 px-6 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="viewSaleDetails(<?php echo $sale['id']; ?>)" 
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" 
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <a href="/receipt.php?id=<?php echo $sale['id']; ?>" target="_blank" 
                               class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition" 
                               title="Print Receipt">
                                <i class="fas fa-print"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="<?php echo $isOwner ? '7' : '6'; ?>" class="text-center py-20">
                        <i class="fas fa-receipt text-6xl text-gray-300 mb-4"></i>
                        <p class="text-xl text-gray-500 font-semibold mb-2">No sales found</p>
                        <p class="text-gray-400 text-sm">Try adjusting your filters or date range</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50">
        <div class="text-sm text-gray-600">
            Showing <span class="font-semibold"><?php echo $offset + 1; ?></span> to 
            <span class="font-semibold"><?php echo min($offset + $perPage, $totalSales); ?></span> of 
            <span class="font-semibold"><?php echo $totalSales; ?></span> results
        </div>
        <div class="flex gap-2">
            <?php if ($page > 1): 
                $prevParams = $_GET;
                $prevParams['page'] = $page - 1;
            ?>
            <a href="?<?php echo http_build_query($prevParams); ?>" 
               class="px-4 py-2 border-2 border-gray-300 rounded-lg text-sm font-semibold hover:bg-gray-100 transition">
                <i class="fas fa-chevron-left mr-2"></i>Previous
            </a>
            <?php endif; ?>
            
            <div class="flex gap-1">
                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                    $pageParams = $_GET;
                    $pageParams['page'] = $i;
                ?>
                <a href="?<?php echo http_build_query($pageParams); ?>" 
                   class="w-10 h-10 flex items-center justify-center rounded-lg text-sm font-semibold transition <?php echo $i === $page ? 'text-white' : 'text-gray-700 hover:bg-gray-100'; ?>"
                   style="<?php echo $i === $page ? 'background-color: ' . $settings['primary_color'] : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </div>
            
            <?php if ($page < $totalPages):
                $nextParams = $_GET;
                $nextParams['page'] = $page + 1;
            ?>
            <a href="?<?php echo http_build_query($nextParams); ?>" 
               class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition hover:opacity-90"
               style="background-color: <?php echo $settings['primary_color']; ?>">
                Next<i class="fas fa-chevron-right ml-2"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Sale Details Modal -->
<div id="saleDetailsModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" style="backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="sticky top-0 bg-white p-6 border-b border-gray-200 flex items-center justify-between z-10 rounded-t-2xl">
            <h3 class="text-2xl font-bold text-gray-900">Sale Details</h3>
            <button onclick="closeSaleDetails()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div id="saleDetailsContent" class="p-6">
            <div class="flex items-center justify-center py-12">
                <i class="fas fa-spinner fa-spin text-4xl" style="color: <?php echo $settings['primary_color']; ?>"></i>
            </div>
        </div>
    </div>
</div>

<script>
const primaryColor = '<?php echo $settings['primary_color']; ?>';
const currency = '<?php echo $settings['currency']; ?>';

async function viewSaleDetails(saleId) {
    document.getElementById('saleDetailsModal').classList.remove('hidden');
    document.getElementById('saleDetailsContent').innerHTML = `
        <div class="flex items-center justify-center py-12">
            <i class="fas fa-spinner fa-spin text-4xl" style="color: ${primaryColor}"></i>
        </div>
    `;
    
    try {
        const response = await fetch(`/api/sale-details.php?id=${saleId}`);
        const data = await response.json();
        
        if (data.success) {
            const sale = data.data.sale;
            const items = data.data.items;
            
            let itemsHtml = '';
            items.forEach(item => {
                itemsHtml += `
                    <tr class="border-b border-gray-100">
                        <td class="py-3 px-4">
                            <div class="font-semibold text-gray-900">${item.product_name}</div>
                            <div class="text-xs text-gray-500">${currency} ${parseFloat(item.unit_price).toFixed(2)} each</div>
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-lg">${item.quantity}</td>
                        <td class="py-3 px-4 text-right font-bold text-lg" style="color: ${primaryColor}">${currency} ${parseFloat(item.subtotal).toFixed(2)}</td>
                    </tr>
                `;
            });
            
            document.getElementById('saleDetailsContent').innerHTML = `
                <div class="space-y-6">
                    <!-- Sale Info -->
                    <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-xl">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Sale Number</p>
                            <p class="font-bold text-gray-900">${sale.sale_number}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Date & Time</p>
                            <p class="font-semibold text-gray-900">${new Date(sale.sale_date).toLocaleString()}</p>
                        </div>
                        <?php if ($isOwner): ?>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Seller</p>
                            <p class="font-semibold text-gray-900">${sale.seller_name}</p>
                        </div>
                        <?php endif; ?>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Payment Method</p>
                            <p class="font-semibold text-gray-900">${sale.payment_method.toUpperCase().replace('_', ' ')}</p>
                        </div>
                        ${sale.mpesa_reference ? `
                        <div class="col-span-2">
                            <p class="text-sm text-gray-600 mb-1">M-Pesa Reference</p>
                            <p class="font-mono font-semibold text-gray-900">${sale.mpesa_reference}</p>
                        </div>
                        ` : ''}
                    </div>
                    
                    <!-- Items Table -->
                    <div>
                        <h4 class="font-bold text-gray-900 mb-3 text-lg">Items Sold</h4>
                        <div class="border-2 border-gray-200 rounded-xl overflow-hidden">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="text-left py-3 px-4 text-sm font-bold text-gray-700">Product</th>
                                        <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Qty</th>
                                        <th class="text-right py-3 px-4 text-sm font-bold text-gray-700">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${itemsHtml}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Totals -->
                    <div class="space-y-3 p-4 bg-gray-50 rounded-xl">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 font-medium">Subtotal:</span>
                            <span class="font-bold text-gray-900">${currency} ${parseFloat(sale.subtotal).toFixed(2)}</span>
                        </div>
                        ${parseFloat(sale.tax_amount) > 0 ? `
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 font-medium">Tax:</span>
                            <span class="font-bold text-gray-900">${currency} ${parseFloat(sale.tax_amount).toFixed(2)}</span>
                        </div>
                        ` : ''}
                        <div class="flex justify-between text-xl font-bold pt-3 border-t-2 border-gray-300">
                            <span>Total:</span>
                            <span style="color: ${primaryColor}">${currency} ${parseFloat(sale.total_amount).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between text-sm pt-2">
                            <span class="text-gray-600 font-medium">Amount Paid:</span>
                            <span class="font-bold text-gray-900">${currency} ${parseFloat(sale.amount_paid).toFixed(2)}</span>
                        </div>
                        ${parseFloat(sale.change_amount) > 0 ? `
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 font-medium">Change Given:</span>
                            <span class="font-bold text-green-600">${currency} ${parseFloat(sale.change_amount).toFixed(2)}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex gap-3">
                        <a href="/receipt.php?id=${sale.id}" target="_blank" 
                           class="flex-1 px-6 py-3 rounded-xl font-bold text-white text-center transition hover:opacity-90 shadow-lg"
                           style="background: linear-gradient(135deg, ${primaryColor} 0%, ${primaryColor}dd 100%)">
                            <i class="fas fa-print mr-2"></i>Print Receipt
                        </a>
                        <button onclick="closeSaleDetails()" 
                                class="flex-1 px-6 py-3 border-2 border-gray-300 rounded-xl font-bold text-gray-700 hover:bg-gray-50 transition">
                            Close
                        </button>
                    </div>
                </div>
            `;
        } else {
            showToast(data.message || 'Failed to load sale details', 'error');
            closeSaleDetails();
        }
    } catch (error) {
        showToast('An error occurred while loading sale details', 'error');
        console.error(error);
        closeSaleDetails();
    }
}

function closeSaleDetails() {
    document.getElementById('saleDetailsModal').classList.add('hidden');
}

function showToast(message, type) {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>${message}`;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php include 'footer.php'; ?>