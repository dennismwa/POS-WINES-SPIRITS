<?php
require_once 'config.php';
requireOwner();

$page_title = 'Reports & Analytics';
$settings = getSettings();

// Date range
$dateFrom = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$dateTo = isset($_GET['to']) ? sanitize($_GET['to']) : date('Y-m-d');
$reportType = isset($_GET['type']) ? sanitize($_GET['type']) : 'sales';

// Sales Report Data
$salesStats = $conn->query("SELECT 
    COUNT(*) as total_sales,
    COALESCE(SUM(total_amount), 0) as total_revenue,
    COALESCE(AVG(total_amount), 0) as avg_sale,
    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_revenue,
    COALESCE(SUM(CASE WHEN payment_method IN ('mpesa', 'mpesa_till') THEN total_amount ELSE 0 END), 0) as mpesa_revenue,
    COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END), 0) as card_revenue
    FROM sales 
    WHERE DATE(sale_date) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_assoc();

// Daily sales for chart
$dailySales = [];
$dailyQuery = $conn->query("SELECT DATE(sale_date) as date, COALESCE(SUM(total_amount), 0) as total 
                            FROM sales 
                            WHERE DATE(sale_date) BETWEEN '$dateFrom' AND '$dateTo'
                            GROUP BY DATE(sale_date) 
                            ORDER BY date ASC");
while ($row = $dailyQuery->fetch_assoc()) {
    $dailySales[] = $row;
}

// Top products
$topProducts = $conn->query("SELECT p.name, SUM(si.quantity) as total_qty, SUM(si.subtotal) as total_revenue 
                             FROM sale_items si 
                             JOIN products p ON si.product_id = p.id 
                             JOIN sales s ON si.sale_id = s.id 
                             WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'
                             GROUP BY si.product_id 
                             ORDER BY total_revenue DESC 
                             LIMIT 10");

// Low stock products
$lowStock = $conn->query("SELECT p.*, c.name as category_name 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE p.stock_quantity <= p.reorder_level 
                         AND p.status = 'active' 
                         ORDER BY p.stock_quantity ASC 
                         LIMIT 10");

// Inventory value
$inventoryStats = $conn->query("SELECT 
    COALESCE(SUM(stock_quantity * cost_price), 0) as cost_value,
    COALESCE(SUM(stock_quantity * selling_price), 0) as selling_value,
    COUNT(*) as total_products,
    COALESCE(SUM(stock_quantity), 0) as total_items
    FROM products 
    WHERE status = 'active'")->fetch_assoc();

// Expenses
$expenseStats = $conn->query("SELECT 
    COALESCE(SUM(amount), 0) as total_expenses,
    COUNT(*) as expense_count
    FROM expenses 
    WHERE expense_date BETWEEN '$dateFrom' AND '$dateTo'")->fetch_assoc();

// Category breakdown
$categoryBreakdown = $conn->query("SELECT c.name, COUNT(DISTINCT si.sale_id) as sales_count, SUM(si.subtotal) as revenue 
                                   FROM sale_items si 
                                   JOIN products p ON si.product_id = p.id 
                                   JOIN categories c ON p.category_id = c.id 
                                   JOIN sales s ON si.sale_id = s.id 
                                   WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'
                                   GROUP BY c.id 
                                   ORDER BY revenue DESC");

// Profit calculation
$profit = $salesStats['total_revenue'] - $expenseStats['total_expenses'];

include 'header.php';
?>

<style>
.report-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.report-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.15);
}

.chart-container {
    position: relative;
    height: 300px;
}

@media (max-width: 768px) {
    .chart-container {
        height: 250px;
    }
}

.stat-icon {
    width: 4rem;
    height: 4rem;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.report-tabs {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
}

.report-tab {
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    border: 2px solid #e5e7eb;
}

.report-tab.active {
    background: <?php echo $settings['primary_color']; ?>;
    color: white;
    border-color: <?php echo $settings['primary_color']; ?>;
}

.progress-bar {
    height: 0.5rem;
    border-radius: 0.25rem;
    background: #e5e7eb;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    transition: width 0.3s;
}
</style>

<!-- Date Filter & Report Types -->
<div class="report-card mb-6">
    <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1">
            <label class="block text-sm font-bold text-gray-700 mb-2">Report Type</label>
            <select name="type" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base">
                <option value="sales" <?php echo $reportType === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                <option value="inventory" <?php echo $reportType === 'inventory' ? 'selected' : ''; ?>>Inventory Report</option>
                <option value="profit" <?php echo $reportType === 'profit' ? 'selected' : ''; ?>>Profit & Loss</option>
                <option value="products" <?php echo $reportType === 'products' ? 'selected' : ''; ?>>Product Performance</option>
            </select>
        </div>
        
        <div class="flex-1">
            <label class="block text-sm font-bold text-gray-700 mb-2">From Date</label>
            <input type="date" name="from" value="<?php echo $dateFrom; ?>" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base">
        </div>
        
        <div class="flex-1">
            <label class="block text-sm font-bold text-gray-700 mb-2">To Date</label>
            <input type="date" name="to" value="<?php echo $dateTo; ?>" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base">
        </div>
        
        <button type="submit" 
                class="px-8 py-3 rounded-lg font-bold text-white transition hover:opacity-90 shadow-lg whitespace-nowrap"
                style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <i class="fas fa-chart-bar mr-2"></i>Generate Report
        </button>
    </form>
</div>

<!-- Overview Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
    <div class="report-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Total Revenue</p>
                <h3 class="text-2xl md:text-3xl font-bold" style="color: <?php echo $settings['primary_color']; ?>">
                    <?php echo formatCurrency($salesStats['total_revenue']); ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1"><?php echo $salesStats['total_sales']; ?> sales</p>
            </div>
            <div class="stat-icon" style="background-color: <?php echo $settings['primary_color']; ?>20;">
                <i class="fas fa-dollar-sign" style="color: <?php echo $settings['primary_color']; ?>"></i>
            </div>
        </div>
    </div>
    
    <div class="report-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Total Expenses</p>
                <h3 class="text-2xl md:text-3xl font-bold text-red-600">
                    <?php echo formatCurrency($expenseStats['total_expenses']); ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1"><?php echo $expenseStats['expense_count']; ?> expenses</p>
            </div>
            <div class="stat-icon bg-red-100">
                <i class="fas fa-receipt text-red-600"></i>
            </div>
        </div>
    </div>
    
    <div class="report-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Net Profit</p>
                <h3 class="text-2xl md:text-3xl font-bold <?php echo $profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo formatCurrency($profit); ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1">
                    <?php echo $salesStats['total_revenue'] > 0 ? round(($profit / $salesStats['total_revenue']) * 100, 1) : 0; ?>% margin
                </p>
            </div>
            <div class="stat-icon <?php echo $profit >= 0 ? 'bg-green-100' : 'bg-red-100'; ?>">
                <i class="fas fa-chart-line <?php echo $profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>"></i>
            </div>
        </div>
    </div>
    
    <div class="report-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Inventory Value</p>
                <h3 class="text-2xl md:text-3xl font-bold text-blue-600">
                    <?php echo formatCurrency($inventoryStats['selling_value']); ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1"><?php echo $inventoryStats['total_items']; ?> items</p>
            </div>
            <div class="stat-icon bg-blue-100">
                <i class="fas fa-boxes text-blue-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Sales Trend -->
    <div class="report-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Sales Trend</h3>
        <div class="chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </div>
    
    <!-- Payment Methods -->
    <div class="report-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Payment Methods</h3>
        <div class="chart-container">
            <canvas id="paymentChart"></canvas>
        </div>
    </div>
</div>

<!-- Top Products & Category Breakdown -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Top Products -->
    <div class="report-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Top Selling Products</h3>
        <div class="space-y-3">
            <?php 
            $rank = 1;
            $maxRevenue = 0;
            $productsData = [];
            while ($product = $topProducts->fetch_assoc()) {
                $productsData[] = $product;
                if ($product['total_revenue'] > $maxRevenue) $maxRevenue = $product['total_revenue'];
            }
            
            if (count($productsData) > 0):
                foreach ($productsData as $product):
                    $percentage = $maxRevenue > 0 ? ($product['total_revenue'] / $maxRevenue) * 100 : 0;
            ?>
            <div>
                <div class="flex justify-between items-center mb-2">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold flex-shrink-0"
                             style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                            <?php echo $rank++; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-sm text-gray-900 truncate"><?php echo htmlspecialchars($product['name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo $product['total_qty']; ?> sold</p>
                        </div>
                    </div>
                    <p class="font-bold text-sm ml-2" style="color: <?php echo $settings['primary_color']; ?>">
                        <?php echo formatCurrency($product['total_revenue']); ?>
                    </p>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $settings['primary_color']; ?>"></div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <p class="text-center text-gray-400 py-8">No sales data</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Category Breakdown -->
    <div class="report-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Sales by Category</h3>
        <div class="space-y-3">
            <?php 
            $catData = [];
            $maxCatRevenue = 0;
            while ($cat = $categoryBreakdown->fetch_assoc()) {
                $catData[] = $cat;
                if ($cat['revenue'] > $maxCatRevenue) $maxCatRevenue = $cat['revenue'];
            }
            
            if (count($catData) > 0):
                $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                $colorIndex = 0;
                foreach ($catData as $cat):
                    $percentage = $maxCatRevenue > 0 ? ($cat['revenue'] / $maxCatRevenue) * 100 : 0;
                    $color = $colors[$colorIndex % count($colors)];
                    $colorIndex++;
            ?>
            <div>
                <div class="flex justify-between items-center mb-2">
                    <div class="flex-1">
                        <p class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($cat['name']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo $cat['sales_count']; ?> sales</p>
                    </div>
                    <p class="font-bold text-sm" style="color: <?php echo $color; ?>">
                        <?php echo formatCurrency($cat['revenue']); ?>
                    </p>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $color; ?>"></div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <p class="text-center text-gray-400 py-8">No category data</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Low Stock Alert -->
<?php if ($lowStock->num_rows > 0): ?>
<div class="report-card">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-gray-900">Low Stock Alert</h3>
        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-bold">
            <i class="fas fa-exclamation-triangle mr-1"></i><?php echo $lowStock->num_rows; ?> Items
        </span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
                <tr>
                    <th class="text-left py-3 px-4 text-sm font-bold text-gray-700">Product</th>
                    <th class="text-left py-3 px-4 text-sm font-bold text-gray-700">Category</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Current Stock</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Reorder Level</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $lowStock->fetch_assoc()): ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <p class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($item['name']); ?></p>
                    </td>
                    <td class="py-3 px-4">
                        <span class="text-xs px-2 py-1 bg-gray-100 rounded"><?php echo htmlspecialchars($item['category_name']); ?></span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="font-bold text-lg <?php echo $item['stock_quantity'] == 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                            <?php echo $item['stock_quantity']; ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="text-sm text-gray-600"><?php echo $item['reorder_level']; ?></span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="px-3 py-1 text-xs font-bold rounded-full <?php echo $item['stock_quantity'] == 0 ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800'; ?>">
                            <?php echo $item['stock_quantity'] == 0 ? 'OUT OF STOCK' : 'LOW STOCK'; ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const primaryColor = '<?php echo $settings['primary_color']; ?>';
const dailySalesData = <?php echo json_encode($dailySales); ?>;

// Sales Trend Chart
const salesCtx = document.getElementById('salesChart');
if (salesCtx) {
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: dailySalesData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
            datasets: [{
                label: 'Daily Sales',
                data: dailySalesData.map(d => parseFloat(d.total)),
                borderColor: primaryColor,
                backgroundColor: primaryColor + '33',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '<?php echo $settings['currency']; ?> ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '<?php echo $settings['currency']; ?> ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Payment Methods Chart
const paymentCtx = document.getElementById('paymentChart');
if (paymentCtx) {
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: ['Cash', 'M-Pesa', 'Card'],
            datasets: [{
                data: [
                    <?php echo $salesStats['cash_revenue']; ?>,
                    <?php echo $salesStats['mpesa_revenue']; ?>,
                    <?php echo $salesStats['card_revenue']; ?>
                ],
                backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: { size: 12, weight: 'bold' }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': <?php echo $settings['currency']; ?> ' + context.parsed.toFixed(2) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}
</script>

<?php include 'footer.php'; ?>