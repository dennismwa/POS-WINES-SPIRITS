<?php
require_once 'config.php';
requireOwner();

$page_title = 'Branch Performance Comparison';
$settings = getSettings();

// Date range
$dateFrom = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$dateTo = isset($_GET['to']) ? sanitize($_GET['to']) : date('Y-m-d');

// Get branch performance data
$branchesQuery = "SELECT b.*,
    COUNT(DISTINCT s.id) as sales_count,
    COALESCE(SUM(s.total_amount), 0) as total_revenue,
    COALESCE(AVG(s.total_amount), 0) as avg_sale,
    COUNT(DISTINCT s.user_id) as active_staff,
    COALESCE(SUM(e.amount), 0) as total_expenses,
    (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND status = 'active') as staff_count,
    (SELECT SUM(stock_quantity) FROM branch_inventory WHERE branch_id = b.id) as total_stock
    FROM branches b
    LEFT JOIN sales s ON b.id = s.branch_id AND DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'
    LEFT JOIN expenses e ON b.id = e.branch_id AND DATE(e.expense_date) BETWEEN '$dateFrom' AND '$dateTo'
    WHERE b.status = 'active'
    GROUP BY b.id
    ORDER BY total_revenue DESC";

$branches = $conn->query($branchesQuery);
$branchData = [];
while ($row = $branches->fetch_assoc()) {
    $row['profit'] = $row['total_revenue'] - $row['total_expenses'];
    $row['profit_margin'] = $row['total_revenue'] > 0 ? ($row['profit'] / $row['total_revenue']) * 100 : 0;
    $branchData[] = $row;
}

include 'header.php';
?>

<style>
.comparison-card {
    background: white;
    border-radius: 1.25rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.comparison-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.15);
}

.metric-bar {
    height: 0.75rem;
    border-radius: 0.5rem;
    background: #e5e7eb;
    overflow: hidden;
}

.metric-fill {
    height: 100%;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.rank-badge {
    width: 3rem;
    height: 3rem;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 700;
}

@media (max-width: 768px) {
    .comparison-card {
        padding: 1rem;
    }
    
    .rank-badge {
        width: 2.5rem;
        height: 2.5rem;
        font-size: 1rem;
    }
}
</style>

<!-- Header -->
<div class="comparison-card mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-chart-line gradient-text mr-3"></i>
                Branch Performance Comparison
            </h1>
            <p class="text-sm md:text-base text-gray-600">Compare metrics across all locations</p>
        </div>
        
        <div class="flex gap-2">
            <button onclick="window.print()" 
                    class="px-4 md:px-6 py-2 md:py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-semibold transition">
                <i class="fas fa-print mr-2"></i>Print
            </button>
            <a href="/branches.php" 
               class="px-4 md:px-6 py-2 md:py-3 rounded-xl font-semibold text-white transition hover:opacity-90"
               style="background-color: <?php echo $settings['primary_color']; ?>">
                <i class="fas fa-store mr-2"></i>Manage Branches
            </a>
        </div>
    </div>
</div>

<!-- Date Filter -->
<div class="comparison-card mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-2">Date Range</label>
            <div class="grid grid-cols-2 gap-3">
                <input type="date" name="from" value="<?php echo $dateFrom; ?>" 
                       class="px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none">
                <input type="date" name="to" value="<?php echo $dateTo; ?>" 
                       class="px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none">
            </div>
        </div>
        <button type="submit" 
                class="px-6 py-2 rounded-lg font-bold text-white transition hover:opacity-90"
                style="background-color: <?php echo $settings['primary_color']; ?>">
            <i class="fas fa-filter mr-2"></i>Apply Filter
        </button>
        <a href="/branch-comparison.php" 
           class="px-6 py-2 border-2 border-gray-300 rounded-lg font-bold text-gray-700 hover:bg-gray-50 transition text-center">
            <i class="fas fa-redo mr-2"></i>Reset
        </a>
    </form>
</div>

<!-- Performance Rankings -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mb-6">
    <?php 
    $maxRevenue = max(array_column($branchData, 'total_revenue'));
    $rank = 1;
    foreach ($branchData as $branch):
        $percentage = $maxRevenue > 0 ? ($branch['total_revenue'] / $maxRevenue) * 100 : 0;
        $rankColors = [1 => 'bg-yellow-500', 2 => 'bg-gray-400', 3 => 'bg-orange-600'];
        $rankColor = $rankColors[$rank] ?? 'bg-blue-500';
    ?>
    <div class="comparison-card">
        <div class="flex items-start gap-4 mb-4">
            <div class="rank-badge <?php echo $rankColor; ?> text-white">
                <?php echo $rank <= 3 ? '<i class="fas fa-medal"></i>' : $rank; ?>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-lg md:text-xl text-gray-900 mb-1 truncate">
                    <?php echo htmlspecialchars($branch['name']); ?>
                </h3>
                <span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-800">
                    <?php echo htmlspecialchars($branch['code']); ?>
                </span>
            </div>
        </div>
        
        <div class="space-y-4">
            <!-- Revenue -->
            <div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600 font-medium">Revenue</span>
                    <span class="text-xl font-bold" style="color: <?php echo $settings['primary_color']; ?>">
                        <?php echo formatCurrency($branch['total_revenue']); ?>
                    </span>
                </div>
                <div class="metric-bar">
                    <div class="metric-fill" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $settings['primary_color']; ?>"></div>
                </div>
            </div>
            
            <!-- Key Metrics Grid -->
            <div class="grid grid-cols-2 gap-3">
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-600 mb-1">Sales</p>
                    <p class="text-xl md:text-2xl font-bold text-gray-900"><?php echo $branch['sales_count']; ?></p>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-600 mb-1">Avg Sale</p>
                    <p class="text-sm md:text-base font-bold text-green-600"><?php echo formatCurrency($branch['avg_sale']); ?></p>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-600 mb-1">Profit</p>
                    <p class="text-sm md:text-base font-bold <?php echo $branch['profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo formatCurrency($branch['profit']); ?>
                    </p>
                </div>
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-600 mb-1">Margin</p>
                    <p class="text-sm md:text-base font-bold text-blue-600"><?php echo number_format($branch['profit_margin'], 1); ?>%</p>
                </div>
            </div>
            
            <div class="pt-3 border-t border-gray-200 flex justify-between text-sm">
                <span class="text-gray-600">
                    <i class="fas fa-users mr-1"></i><?php echo $branch['staff_count']; ?> staff
                </span>
                <span class="text-gray-600">
                    <i class="fas fa-box mr-1"></i><?php echo number_format($branch['total_stock']); ?> units
                </span>
            </div>
        </div>
    </div>
    <?php 
        $rank++;
    endforeach; 
    ?>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Revenue Comparison Chart -->
    <div class="comparison-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-bar text-blue-500"></i>
            Revenue Comparison
        </h3>
        <div style="height: 300px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    
    <!-- Sales Volume Chart -->
    <div class="comparison-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <i class="fas fa-shopping-cart text-green-500"></i>
            Sales Volume
        </h3>
        <div style="height: 300px;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>
</div>

<!-- Detailed Comparison Table -->
<div class="comparison-card">
    <h3 class="text-lg font-bold text-gray-900 mb-4">Detailed Metrics</h3>
    
    <!-- Desktop Table -->
    <div class="overflow-x-auto hidden md:block">
        <table class="w-full">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
                <tr>
                    <th class="text-left py-3 px-4 text-sm font-bold text-gray-700">Branch</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Sales</th>
                    <th class="text-right py-3 px-4 text-sm font-bold text-gray-700">Revenue</th>
                    <th class="text-right py-3 px-4 text-sm font-bold text-gray-700">Expenses</th>
                    <th class="text-right py-3 px-4 text-sm font-bold text-gray-700">Profit</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Margin</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Staff</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branchData as $branch): ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-4 px-4">
                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($branch['name']); ?></div>
                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($branch['code']); ?></div>
                    </td>
                    <td class="py-4 px-4 text-center font-bold text-lg"><?php echo $branch['sales_count']; ?></td>
                    <td class="py-4 px-4 text-right font-bold" style="color: <?php echo $settings['primary_color']; ?>">
                        <?php echo formatCurrency($branch['total_revenue']); ?>
                    </td>
                    <td class="py-4 px-4 text-right font-semibold text-red-600">
                        <?php echo formatCurrency($branch['total_expenses']); ?>
                    </td>
                    <td class="py-4 px-4 text-right font-bold <?php echo $branch['profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo formatCurrency($branch['profit']); ?>
                    </td>
                    <td class="py-4 px-4 text-center">
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $branch['profit_margin'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo number_format($branch['profit_margin'], 1); ?>%
                        </span>
                    </td>
                    <td class="py-4 px-4 text-center font-semibold"><?php echo $branch['staff_count']; ?></td>
                    <td class="py-4 px-4 text-center font-semibold"><?php echo number_format($branch['total_stock']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Mobile Cards -->
    <div class="grid gap-4 md:hidden">
        <?php foreach ($branchData as $branch): ?>
        <div class="border-2 border-gray-200 rounded-xl p-4">
            <h4 class="font-bold text-gray-900 mb-3"><?php echo htmlspecialchars($branch['name']); ?></h4>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-xs text-gray-600">Sales</p>
                    <p class="text-xl font-bold text-gray-900"><?php echo $branch['sales_count']; ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-600">Revenue</p>
                    <p class="text-sm font-bold" style="color: <?php echo $settings['primary_color']; ?>">
                        <?php echo formatCurrency($branch['total_revenue']); ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-600">Profit</p>
                    <p class="text-sm font-bold <?php echo $branch['profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo formatCurrency($branch['profit']); ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-600">Margin</p>
                    <p class="text-xl font-bold text-blue-600"><?php echo number_format($branch['profit_margin'], 1); ?>%</p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const branchData = <?php echo json_encode($branchData); ?>;
const primaryColor = '<?php echo $settings['primary_color']; ?>';
const currency = '<?php echo $settings['currency']; ?>';

// Revenue Chart
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: branchData.map(b => b.name),
        datasets: [{
            label: 'Revenue',
            data: branchData.map(b => parseFloat(b.total_revenue)),
            backgroundColor: primaryColor + '80',
            borderColor: primaryColor,
            borderWidth: 2,
            borderRadius: 8
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
            y: {
                beginAtZero: true,
                ticks: { callback: v => currency + ' ' + v.toLocaleString() }
            }
        }
    }
});

// Sales Chart
new Chart(document.getElementById('salesChart'), {
    type: 'doughnut',
    data: {
        labels: branchData.map(b => b.name),
        datasets: [{
            data: branchData.map(b => parseInt(b.sales_count)),
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
            borderWidth: 3,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed + ' sales';
                    }
                }
            }
        }
    }
});
</script>

<style>
.gradient-text {
    background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
</style>

<?php include 'footer.php'; ?>
