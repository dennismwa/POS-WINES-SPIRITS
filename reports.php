<?php
require_once 'config.php';
requireOwner();

$page_title = 'Advanced Reports & Analytics';
$settings = getSettings();

// Date range
$dateFrom = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$dateTo = isset($_GET['to']) ? sanitize($_GET['to']) : date('Y-m-d');
$reportType = isset($_GET['type']) ? sanitize($_GET['type']) : 'overview';
$sellerFilter = isset($_GET['seller']) ? intval($_GET['seller']) : 0;

// Get all sellers
$sellers = $conn->query("SELECT id, name FROM users WHERE status='active' ORDER BY name");

// Overall Stats
$overallStats = $conn->query("SELECT 
    COUNT(DISTINCT s.id) as total_sales,
    COALESCE(SUM(s.total_amount), 0) as total_revenue,
    COALESCE(AVG(s.total_amount), 0) as avg_sale,
    COALESCE(SUM(CASE WHEN s.payment_method = 'cash' THEN s.total_amount ELSE 0 END), 0) as cash_revenue,
    COALESCE(SUM(CASE WHEN s.payment_method IN ('mpesa', 'mpesa_till') THEN s.total_amount ELSE 0 END), 0) as mpesa_revenue,
    COALESCE(SUM(CASE WHEN s.payment_method = 'card' THEN s.total_amount ELSE 0 END), 0) as card_revenue,
    COUNT(DISTINCT s.user_id) as active_sellers
    FROM sales s 
    WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_assoc();

// Expenses in period
$expenseStats = $conn->query("SELECT 
    COALESCE(SUM(amount), 0) as total_expenses,
    COUNT(*) as expense_count,
    COALESCE(AVG(amount), 0) as avg_expense
    FROM expenses 
    WHERE expense_date BETWEEN '$dateFrom' AND '$dateTo'")->fetch_assoc();

// Profit calculation
$profit = $overallStats['total_revenue'] - $expenseStats['total_expenses'];
$profitMargin = $overallStats['total_revenue'] > 0 ? ($profit / $overallStats['total_revenue']) * 100 : 0;

// Daily sales breakdown
$dailySales = [];
$dailyQuery = $conn->query("SELECT 
    DATE(sale_date) as date,
    COUNT(*) as sales_count,
    COALESCE(SUM(total_amount), 0) as revenue,
    COALESCE(AVG(total_amount), 0) as avg_sale
    FROM sales 
    WHERE DATE(sale_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(sale_date) 
    ORDER BY date ASC");
while ($row = $dailyQuery->fetch_assoc()) {
    $dailySales[] = $row;
}

// Seller performance
$sellerWhere = $sellerFilter > 0 ? "AND s.user_id = $sellerFilter" : "";
$sellerPerformance = $conn->query("SELECT 
    u.id, u.name, u.role,
    COUNT(DISTINCT s.id) as sales_count,
    COALESCE(SUM(s.total_amount), 0) as total_revenue,
    COALESCE(AVG(s.total_amount), 0) as avg_sale,
    COALESCE(SUM(si.quantity), 0) as items_sold,
    MIN(s.sale_date) as first_sale,
    MAX(s.sale_date) as last_sale
    FROM users u
    LEFT JOIN sales s ON u.id = s.user_id AND DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'
    LEFT JOIN sale_items si ON s.id = si.sale_id
    WHERE u.status = 'active' $sellerWhere
    GROUP BY u.id
    ORDER BY total_revenue DESC");

// Top products
$topProducts = $conn->query("SELECT 
    p.name, 
    p.category_id,
    c.name as category_name,
    SUM(si.quantity) as total_qty, 
    SUM(si.subtotal) as total_revenue,
    COUNT(DISTINCT si.sale_id) as times_sold,
    COALESCE(AVG(si.unit_price), 0) as avg_price
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    LEFT JOIN categories c ON p.category_id = c.id
    JOIN sales s ON si.sale_id = s.id 
    WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY si.product_id 
    ORDER BY total_revenue DESC 
    LIMIT 15");

// Category breakdown
$categoryBreakdown = $conn->query("SELECT 
    c.name, 
    c.id,
    COUNT(DISTINCT si.sale_id) as sales_count, 
    SUM(si.subtotal) as revenue,
    SUM(si.quantity) as items_sold
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    JOIN categories c ON p.category_id = c.id 
    JOIN sales s ON si.sale_id = s.id 
    WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY c.id 
    ORDER BY revenue DESC");

// Hourly sales pattern
$hourlySales = $conn->query("SELECT 
    HOUR(sale_date) as hour,
    COUNT(*) as sales_count,
    COALESCE(SUM(total_amount), 0) as revenue
    FROM sales 
    WHERE DATE(sale_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY HOUR(sale_date)
    ORDER BY hour ASC");

// Expense breakdown by category
$expenseBreakdown = $conn->query("SELECT 
    category,
    COUNT(*) as count,
    COALESCE(SUM(amount), 0) as total,
    COALESCE(AVG(amount), 0) as average
    FROM expenses 
    WHERE expense_date BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY category
    ORDER BY total DESC");

include 'header.php';
?>

<style>
.report-card {
    background: white;
    border-radius: 1.25rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.report-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
}

.stat-icon {
    width: 4rem;
    height: 4rem;
    border-radius: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.chart-container {
    position: relative;
    height: 350px;
}

.seller-avatar {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
}

.progress-bar {
    height: 0.75rem;
    border-radius: 0.5rem;
    background: #e5e7eb;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    background: linear-gradient(90deg, currentColor 0%, currentColor 100%);
}

.tab-btn {
    padding: 0.875rem 1.5rem;
    border-radius: 1rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
}

.tab-btn.active {
    background: <?php echo $settings['primary_color']; ?>;
    color: white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.metric-card {
    background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,1) 100%);
    border-radius: 1rem;
    padding: 1.5rem;
    border: 2px solid #f3f4f6;
    transition: all 0.3s;
}

.metric-card:hover {
    border-color: <?php echo $settings['primary_color']; ?>;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .chart-container {
        height: 280px;
    }
    
    .seller-avatar {
        width: 2.5rem;
        height: 2.5rem;
        font-size: 1.125rem;
    }
    
    .stat-icon {
        width: 3rem;
        height: 3rem;
        font-size: 1.5rem;
    }
}

.gradient-text {
    background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
</style>

<!-- Page Header -->
<div class="report-card mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-chart-line gradient-text mr-3"></i>
                Analytics Report
            </h1>
            <p class="text-gray-600">Comprehensive business intelligence and performance metrics</p>
        </div>
        
        <div class="flex gap-2">
            <button onclick="window.print()" 
                    class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition">
                <i class="fas fa-print mr-2"></i>Print
            </button>
            <button onclick="exportReport()" 
                    class="px-6 py-3 rounded-xl font-semibold text-white transition hover:opacity-90 shadow-lg"
                    style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                <i class="fas fa-download mr-2"></i>Export
            </button>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="report-card mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <div class="md:col-span-3">
            <label class="block text-sm font-bold text-gray-700 mb-2">Report Type</label>
            <select name="type" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base font-semibold"
                    style="focus:border-color: <?php echo $settings['primary_color']; ?>">
                <option value="overview" <?php echo $reportType === 'overview' ? 'selected' : ''; ?>>📊 Overview</option>
                <option value="sellers" <?php echo $reportType === 'sellers' ? 'selected' : ''; ?>>👥 Seller Performance</option>
                <option value="daily" <?php echo $reportType === 'daily' ? 'selected' : ''; ?>>📅 Daily Breakdown</option>
                <option value="products" <?php echo $reportType === 'products' ? 'selected' : ''; ?>>📦 Product Analysis</option>
                <option value="expenses" <?php echo $reportType === 'expenses' ? 'selected' : ''; ?>>💰 Expense Tracking</option>
            </select>
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-2">From Date</label>
            <input type="date" name="from" value="<?php echo $dateFrom; ?>" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base">
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-2">To Date</label>
            <input type="date" name="to" value="<?php echo $dateTo; ?>" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base">
        </div>
        
        <div class="md:col-span-3">
            <label class="block text-sm font-bold text-gray-700 mb-2">Filter by Seller</label>
            <select name="seller" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base">
                <option value="0">All Sellers</option>
                <?php 
                $sellers->data_seek(0);
                while ($seller = $sellers->fetch_assoc()): 
                ?>
                <option value="<?php echo $seller['id']; ?>" <?php echo $sellerFilter == $seller['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($seller['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="md:col-span-2 flex items-end">
            <button type="submit" 
                    class="w-full px-6 py-3 rounded-xl font-bold text-white transition hover:opacity-90 shadow-lg"
                    style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                <i class="fas fa-filter mr-2"></i>Apply
            </button>
        </div>
    </form>
</div>

<!-- Key Metrics -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
    <div class="report-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Total Revenue</p>
                <h3 class="text-2xl md:text-3xl font-bold gradient-text">
                    <?php echo formatCurrency($overallStats['total_revenue']); ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1"><?php echo $overallStats['total_sales']; ?> transactions</p>
            </div>
            <div class="stat-icon" style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?>20 0%, <?php echo $settings['primary_color']; ?>10 100%)">
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
                <p class="text-xs text-gray-500 mt-1"><?php echo number_format($profitMargin, 1); ?>% margin</p>
            </div>
            <div class="stat-icon <?php echo $profit >= 0 ? 'bg-green-100' : 'bg-red-100'; ?>">
                <i class="fas fa-chart-line <?php echo $profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>"></i>
            </div>
        </div>
    </div>
    
    <div class="report-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Active Sellers</p>
                <h3 class="text-2xl md:text-3xl font-bold text-purple-600">
                    <?php echo $overallStats['active_sellers']; ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1">team members</p>
            </div>
            <div class="stat-icon bg-purple-100">
                <i class="fas fa-users text-purple-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Report Content -->
<?php if ($reportType === 'overview' || $reportType === 'daily'): ?>
<!-- Daily Sales Breakdown -->
<div class="report-card mb-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-900">📅 Daily Sales Breakdown</h3>
            <p class="text-sm text-gray-600">Performance trend over time</p>
        </div>
    </div>
    
    <div class="chart-container mb-6">
        <canvas id="dailySalesChart"></canvas>
    </div>
    
    <!-- Daily Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
                <tr>
                    <th class="text-left py-3 px-4 text-sm font-bold text-gray-700">Date</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Day</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Sales</th>
                    <th class="text-right py-3 px-4 text-sm font-bold text-gray-700">Revenue</th>
                    <th class="text-right py-3 px-4 text-sm font-bold text-gray-700">Avg Sale</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Performance</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $maxRevenue = 0;
                foreach ($dailySales as $day) {
                    if ($day['revenue'] > $maxRevenue) $maxRevenue = $day['revenue'];
                }
                
                if (count($dailySales) > 0):
                    foreach ($dailySales as $day):
                        $percentage = $maxRevenue > 0 ? ($day['revenue'] / $maxRevenue) * 100 : 0;
                        $dayName = date('D', strtotime($day['date']));
                ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-3 px-4">
                        <span class="font-bold text-gray-900"><?php echo date('M d, Y', strtotime($day['date'])); ?></span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-bold">
                            <?php echo $dayName; ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="font-bold text-lg"><?php echo $day['sales_count']; ?></span>
                    </td>
                    <td class="py-3 px-4 text-right">
                        <span class="font-bold text-lg" style="color: <?php echo $settings['primary_color']; ?>">
                            <?php echo formatCurrency($day['revenue']); ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right">
                        <span class="text-gray-700 font-semibold"><?php echo formatCurrency($day['avg_sale']); ?></span>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 progress-bar">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%; color: <?php echo $settings['primary_color']; ?>"></div>
                            </div>
                            <span class="text-xs font-bold text-gray-600 w-12 text-right"><?php echo round($percentage); ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="6" class="text-center py-12">
                        <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No sales data available</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($reportType === 'overview' || $reportType === 'sellers'): ?>
<!-- Seller Performance -->
<div class="report-card mb-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-900">👥 Seller Performance Analysis</h3>
            <p class="text-sm text-gray-600">Individual team member metrics</p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Seller Revenue Chart -->
        <div class="chart-container">
            <canvas id="sellerRevenueChart"></canvas>
        </div>
        
        <!-- Seller Sales Count Chart -->
        <div class="chart-container">
            <canvas id="sellerSalesChart"></canvas>
        </div>
    </div>
    
    <!-- Seller Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php 
        $sellerPerformance->data_seek(0);
        $sellerColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
        $colorIndex = 0;
        $maxSellerRevenue = 0;
        
        // Get max revenue for percentage calculation
        $tempSellers = [];
        while ($seller = $sellerPerformance->fetch_assoc()) {
            $tempSellers[] = $seller;
            if ($seller['total_revenue'] > $maxSellerRevenue) $maxSellerRevenue = $seller['total_revenue'];
        }
        
        if (count($tempSellers) > 0):
            foreach ($tempSellers as $seller):
                $bgColor = $sellerColors[$colorIndex % count($sellerColors)];
                $percentage = $maxSellerRevenue > 0 ? ($seller['total_revenue'] / $maxSellerRevenue) * 100 : 0;
                $colorIndex++;
        ?>
        <div class="metric-card">
            <div class="flex items-start gap-3 mb-4">
                <div class="seller-avatar" style="background: linear-gradient(135deg, <?php echo $bgColor; ?> 0%, <?php echo $bgColor; ?>dd 100%)">
                    <?php echo strtoupper(substr($seller['name'], 0, 2)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-lg text-gray-900 truncate"><?php echo htmlspecialchars($seller['name']); ?></h4>
                    <span class="px-2 py-1 text-xs font-bold rounded <?php echo $seller['role'] === 'owner' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                        <?php echo ucfirst($seller['role']); ?>
                    </span>
                </div>
            </div>
            
            <div class="space-y-3 mb-4">
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs text-gray-600 font-medium">Revenue</span>
                        <span class="text-lg font-bold" style="color: <?php echo $bgColor; ?>">
                            <?php echo formatCurrency($seller['total_revenue']); ?>
                        </span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%; color: <?php echo $bgColor; ?>"></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-600 mb-1">Sales</p>
                        <p class="text-xl font-bold text-gray-900"><?php echo $seller['sales_count']; ?></p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-600 mb-1">Items</p>
                        <p class="text-xl font-bold text-gray-900"><?php echo $seller['items_sold']; ?></p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-600 mb-1">Avg</p>
                        <p class="text-sm font-bold text-gray-900"><?php echo formatCurrency($seller['avg_sale']); ?></p>
                    </div>
                </div>
            </div>
            
            <?php if ($seller['last_sale']): ?>
            <p class="text-xs text-gray-500 text-center">
                <i class="fas fa-clock mr-1"></i>Last sale: <?php echo date('M d, h:i A', strtotime($seller['last_sale'])); ?>
            </p>
            <?php else: ?>
            <p class="text-xs text-gray-400 text-center italic">No sales in this period</p>
            <?php endif; ?>
        </div>
        <?php endforeach; else: ?>
        <div class="col-span-full text-center py-12">
            <i class="fas fa-users-slash text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">No seller data available</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($reportType === 'overview' || $reportType === 'products'): ?>
<!-- Product Performance -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Top Products -->
    <div class="report-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">
            <i class="fas fa-trophy text-yellow-500 mr-2"></i>Top Selling Products
        </h3>
        <div class="space-y-3">
            <?php 
            $rank = 1;
            $productsData = [];
            $maxProductRevenue = 0;
            while ($product = $topProducts->fetch_assoc()) {
                $productsData[] = $product;
                if ($product['total_revenue'] > $maxProductRevenue) $maxProductRevenue = $product['total_revenue'];
            }
            
            if (count($productsData) > 0):
                foreach ($productsData as $product):
                    $percentage = $maxProductRevenue > 0 ? ($product['total_revenue'] / $maxProductRevenue) * 100 : 0;
                    $medalColor = $rank === 1 ? 'text-yellow-500' : ($rank === 2 ? 'text-gray-400' : ($rank === 3 ? 'text-orange-600' : 'text-gray-300'));
            ?>
            <div class="bg-gray-50 rounded-xl p-4 hover:bg-gray-100 transition">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center text-2xl <?php echo $medalColor; ?>">
                        <?php echo $rank <= 3 ? '<i class="fas fa-medal"></i>' : $rank; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm text-gray-900 truncate"><?php echo htmlspecialchars($product['name']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($product['category_name']); ?> • <?php echo $product['total_qty']; ?> sold • <?php echo $product['times_sold']; ?> orders</p>
                    </div>
                    <p class="font-bold text-sm" style="color: <?php echo $settings['primary_color']; ?>">
                        <?php echo formatCurrency($product['total_revenue']); ?>
                    </p>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%; color: <?php echo $settings['primary_color']; ?>"></div>
                </div>
            </div>
            <?php 
                $rank++;
                endforeach; 
            else: 
            ?>
            <p class="text-center text-gray-400 py-8">No product data</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Category Breakdown -->
    <div class="report-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">
            <i class="fas fa-layer-group text-blue-500 mr-2"></i>Sales by Category
        </h3>
        <div class="space-y-3">
            <?php 
            $catData = [];
            $maxCatRevenue = 0;
            while ($cat = $categoryBreakdown->fetch_assoc()) {
                $catData[] = $cat;
                if ($cat['revenue'] > $maxCatRevenue) $maxCatRevenue = $cat['revenue'];
            }
            
            if (count($catData) > 0):
                $catColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                $catColorIndex = 0;
                foreach ($catData as $cat):
                    $percentage = $maxCatRevenue > 0 ? ($cat['revenue'] / $maxCatRevenue) * 100 : 0;
                    $color = $catColors[$catColorIndex % count($catColors)];
                    $catColorIndex++;
            ?>
            <div class="bg-gray-50 rounded-xl p-4 hover:bg-gray-100 transition">
                <div class="flex justify-between items-center mb-2">
                    <div class="flex-1">
                        <p class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($cat['name']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo $cat['items_sold']; ?> items • <?php echo $cat['sales_count']; ?> sales</p>
                    </div>
                    <p class="font-bold text-sm ml-3" style="color: <?php echo $color; ?>">
                        <?php echo formatCurrency($cat['revenue']); ?>
                    </p>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%; color: <?php echo $color; ?>"></div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <p class="text-center text-gray-400 py-8">No category data</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($reportType === 'overview' || $reportType === 'expenses'): ?>
<!-- Expense Analysis -->
<div class="report-card mb-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-900">💰 Expense Analysis</h3>
            <p class="text-sm text-gray-600">Breakdown by category</p>
        </div>
        <a href="/expenses.php" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold transition text-sm">
            <i class="fas fa-plus mr-2"></i>Add Expense
        </a>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 chart-container">
            <canvas id="expenseChart"></canvas>
        </div>
        
        <div class="space-y-3">
            <?php 
            $expenseData = [];
            $maxExpense = 0;
            while ($expense = $expenseBreakdown->fetch_assoc()) {
                $expenseData[] = $expense;
                if ($expense['total'] > $maxExpense) $maxExpense = $expense['total'];
            }
            
            if (count($expenseData) > 0):
                $expColors = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'];
                $expColorIndex = 0;
                foreach ($expenseData as $expense):
                    $percentage = $maxExpense > 0 ? ($expense['total'] / $maxExpense) * 100 : 0;
                    $color = $expColors[$expColorIndex % count($expColors)];
                    $expColorIndex++;
            ?>
            <div class="bg-gray-50 rounded-xl p-3">
                <div class="flex justify-between items-center mb-2">
                    <div class="flex-1">
                        <p class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($expense['category']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo $expense['count']; ?> transactions</p>
                    </div>
                    <p class="font-bold text-sm" style="color: <?php echo $color; ?>">
                        <?php echo formatCurrency($expense['total']); ?>
                    </p>
                </div>
                <div class="progress-bar h-2">
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%; color: <?php echo $color; ?>"></div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <p class="text-center text-gray-400 py-8">No expense data</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Expense vs Revenue Comparison -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
        <div class="metric-card text-center">
            <p class="text-sm text-gray-600 mb-2">Total Revenue</p>
            <p class="text-3xl font-bold text-green-600"><?php echo formatCurrency($overallStats['total_revenue']); ?></p>
        </div>
        <div class="metric-card text-center">
            <p class="text-sm text-gray-600 mb-2">Total Expenses</p>
            <p class="text-3xl font-bold text-red-600"><?php echo formatCurrency($expenseStats['total_expenses']); ?></p>
        </div>
        <div class="metric-card text-center">
            <p class="text-sm text-gray-600 mb-2">Net Profit</p>
            <p class="text-3xl font-bold <?php echo $profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo formatCurrency($profit); ?>
            </p>
            <p class="text-xs text-gray-500 mt-1"><?php echo number_format($profitMargin, 1); ?>% margin</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Payment Methods & Hourly Pattern -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="report-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">
            <i class="fas fa-credit-card text-green-500 mr-2"></i>Payment Methods
        </h3>
        <div class="chart-container">
            <canvas id="paymentChart"></canvas>
        </div>
    </div>
    
    <div class="report-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">
            <i class="fas fa-clock text-purple-500 mr-2"></i>Sales by Hour
        </h3>
        <div class="chart-container">
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const primaryColor = '<?php echo $settings['primary_color']; ?>';
const currency = '<?php echo $settings['currency']; ?>';

// Daily Sales Chart
const dailySalesData = <?php echo json_encode($dailySales); ?>;
new Chart(document.getElementById('dailySalesChart'), {
    type: 'bar',
    data: {
        labels: dailySalesData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
        datasets: [{
            label: 'Revenue',
            data: dailySalesData.map(d => parseFloat(d.revenue)),
            backgroundColor: primaryColor + '80',
            borderColor: primaryColor,
            borderWidth: 2,
            borderRadius: 8,
            yAxisID: 'y'
        }, {
            label: 'Sales Count',
            data: dailySalesData.map(d => parseInt(d.sales_count)),
            type: 'line',
            borderColor: '#10b981',
            backgroundColor: '#10b98120',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { font: { size: 12, weight: 'bold' } } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) label += ': ';
                        if (context.datasetIndex === 0) {
                            label += currency + ' ' + context.parsed.y.toLocaleString();
                        } else {
                            label += context.parsed.y + ' sales';
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            y: { type: 'linear', position: 'left', ticks: { callback: v => currency + ' ' + v.toLocaleString() } },
            y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => v + ' sales' } }
        }
    }
});

// Seller Performance Charts
const sellerData = <?php 
    $sellerPerformance->data_seek(0);
    $sellers = [];
    while ($s = $sellerPerformance->fetch_assoc()) {
        $sellers[] = $s;
    }
    echo json_encode($sellers); 
?>;

const sellerNames = sellerData.map(s => s.name);
const sellerRevenues = sellerData.map(s => parseFloat(s.total_revenue));
const sellerSalesCounts = sellerData.map(s => parseInt(s.sales_count));
const sellerColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

new Chart(document.getElementById('sellerRevenueChart'), {
    type: 'doughnut',
    data: {
        labels: sellerNames,
        datasets: [{
            data: sellerRevenues,
            backgroundColor: sellerColors,
            borderWidth: 3,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 12, font: { size: 11, weight: 'bold' } } },
            title: { display: true, text: 'Revenue by Seller', font: { size: 14, weight: 'bold' } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + currency + ' ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

new Chart(document.getElementById('sellerSalesChart'), {
    type: 'bar',
    data: {
        labels: sellerNames,
        datasets: [{
            label: 'Number of Sales',
            data: sellerSalesCounts,
            backgroundColor: sellerColors.map(c => c + '80'),
            borderColor: sellerColors,
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            title: { display: true, text: 'Sales Count by Seller', font: { size: 14, weight: 'bold' } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Payment Methods Chart
new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
        labels: ['Cash', 'M-Pesa', 'Card'],
        datasets: [{
            data: [
                <?php echo $overallStats['cash_revenue']; ?>,
                <?php echo $overallStats['mpesa_revenue']; ?>,
                <?php echo $overallStats['card_revenue']; ?>
            ],
            backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6'],
            borderWidth: 3,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 15, font: { size: 12, weight: 'bold' } } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                        return context.label + ': ' + currency + ' ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Hourly Sales Chart
const hourlyData = <?php 
    $hours = array_fill(0, 24, 0);
    while ($hour = $hourlySales->fetch_assoc()) {
        $hours[$hour['hour']] = $hour['revenue'];
    }
    echo json_encode($hours);
?>;

new Chart(document.getElementById('hourlyChart'), {
    type: 'line',
    data: {
        labels: Array.from({length: 24}, (_, i) => i + ':00'),
        datasets: [{
            label: 'Sales',
            data: hourlyData,
            borderColor: '#8b5cf6',
            backgroundColor: '#8b5cf620',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#8b5cf6'
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
                        return currency + ' ' + context.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => currency + ' ' + v.toLocaleString() } }
        }
    }
});

// Expense Chart
const expenseData = <?php echo json_encode($expenseData); ?>;
if (expenseData.length > 0) {
    new Chart(document.getElementById('expenseChart'), {
        type: 'bar',
        data: {
            labels: expenseData.map(e => e.category),
            datasets: [{
                label: 'Total Expenses',
                data: expenseData.map(e => parseFloat(e.total)),
                backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'].map(c => c + '80'),
                borderColor: ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'],
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return currency + ' ' + context.parsed.x.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { callback: v => currency + ' ' + v.toLocaleString() } }
            }
        }
    });
}

function exportReport() {
    alert('Export functionality coming soon!\n\nYou can use the Print button for now.');
}
</script>

<?php include 'footer.php'; ?>
