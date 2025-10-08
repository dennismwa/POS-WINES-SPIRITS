<?php
require_once 'config.php';
requireOwner();

$page_title = 'Dashboard';

// Get settings
$settings = getSettings();

// Get today's stats
$today = date('Y-m-d');
$today_sales_query = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(sale_date) = '$today'");
$today_sales = $today_sales_query ? $today_sales_query->fetch_assoc() : ['count' => 0, 'total' => 0];

// Get this month's stats
$this_month = date('Y-m');
$month_sales_query = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = '$this_month'");
$month_sales = $month_sales_query ? $month_sales_query->fetch_assoc() : ['count' => 0, 'total' => 0];

// Get low stock products
$low_stock_query = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level AND status = 'active'");
$low_stock = $low_stock_query ? $low_stock_query->fetch_assoc() : ['count' => 0];

// Get total products
$total_products_query = $conn->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
$total_products = $total_products_query ? $total_products_query->fetch_assoc() : ['count' => 0];

// Get recent sales
$recent_sales = $conn->query("SELECT s.*, u.name as seller_name FROM sales s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.sale_date DESC LIMIT 10");

// Get top products
$top_products = $conn->query("SELECT p.name, SUM(si.quantity) as total_sold, SUM(si.subtotal) as total_revenue FROM sale_items si JOIN products p ON si.product_id = p.id JOIN sales s ON si.sale_id = s.id WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = '$this_month' GROUP BY p.id ORDER BY total_sold DESC LIMIT 5");

// Get daily sales for chart (last 7 days)
$daily_sales = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(sale_date) = '$date'");
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $daily_sales[] = [
        'date' => date('M d', strtotime($date)),
        'total' => $row['total']
    ];
}

include 'header.php';
?>

<!-- Quick Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Today's Sales -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
            </div>
            <span class="text-sm text-gray-500">Today</span>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-1"><?php echo formatCurrency($today_sales['total']); ?></h3>
        <p class="text-sm text-gray-600"><?php echo $today_sales['count']; ?> transactions</p>
    </div>

    <!-- This Month -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-calendar-alt text-green-600 text-xl"></i>
            </div>
            <span class="text-sm text-gray-500">This Month</span>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-1"><?php echo formatCurrency($month_sales['total']); ?></h3>
        <p class="text-sm text-gray-600"><?php echo $month_sales['count']; ?> transactions</p>
    </div>

    <!-- Total Products -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-wine-bottle text-purple-600 text-xl"></i>
            </div>
            <span class="text-sm text-gray-500">Products</span>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-1"><?php echo $total_products['count']; ?></h3>
        <p class="text-sm text-gray-600">Active products</p>
    </div>

    <!-- Low Stock Alert -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <span class="text-sm text-gray-500">Low Stock</span>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-1"><?php echo $low_stock['count']; ?></h3>
        <p class="text-sm text-gray-600">Need reorder</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Sales Chart -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Sales Trend (Last 7 Days)</h3>
        <canvas id="salesChart" height="80"></canvas>
    </div>

    <!-- Top Products -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Top Products This Month</h3>
        <div class="space-y-4">
            <?php 
            $rank = 1;
            if ($top_products && $top_products->num_rows > 0) {
                while ($product = $top_products->fetch_assoc()): 
            ?>
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-primary bg-opacity-10 rounded-lg flex items-center justify-center">
                    <span class="text-primary font-bold text-sm"><?php echo $rank++; ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm truncate"><?php echo htmlspecialchars($product['name']); ?></p>
                    <p class="text-xs text-gray-500"><?php echo $product['total_sold']; ?> sold</p>
                </div>
                <p class="text-sm font-bold text-primary"><?php echo formatCurrency($product['total_revenue']); ?></p>
            </div>
            <?php 
                endwhile;
            } else {
            ?>
            <p class="text-center text-gray-400 py-8">No sales data yet</p>
            <?php } ?>
        </div>
    </div>
</div>

<!-- Recent Sales -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-6 border-b">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800">Recent Sales</h3>
            <a href="/sales.php" class="text-primary hover:underline text-sm font-medium">View All</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sale #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                if ($recent_sales && $recent_sales->num_rows > 0) {
                    while ($sale = $recent_sales->fetch_assoc()): 
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sale['sale_number']); ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></span>
                        <span class="text-xs text-gray-500 block"><?php echo date('h:i A', strtotime($sale['sale_date'])); ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($sale['seller_name']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $sale['payment_method'] === 'cash' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                            <?php echo strtoupper(str_replace('_', ' ', $sale['payment_method'])); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                        <?php echo formatCurrency($sale['total_amount']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <a href="/receipt.php?id=<?php echo $sale['id']; ?>" target="_blank" class="text-primary hover:underline">
                            <i class="fas fa-print mr-1"></i>Receipt
                        </a>
                    </td>
                </tr>
                <?php 
                    endwhile;
                } else {
                ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">No sales yet</td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Sales Chart
const dailySalesData = <?php echo json_encode($daily_sales); ?>;
const ctx = document.getElementById('salesChart');

if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dailySalesData.map(d => d.date),
            datasets: [{
                label: 'Sales (<?php echo $settings['currency']; ?>)',
                data: dailySalesData.map(d => d.total),
                borderColor: '<?php echo $settings['primary_color']; ?>',
                backgroundColor: '<?php echo $settings['primary_color']; ?>33',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
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
</script>

<?php include 'footer.php'; ?>