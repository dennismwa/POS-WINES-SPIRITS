<?php
require_once 'config.php';
requireOwner();

$page_title = 'Expense Management';
$settings = getSettings();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'add_expense') {
        $category = sanitize($_POST['category']);
        $amount = floatval($_POST['amount']);
        $description = sanitize($_POST['description']);
        $expenseDate = sanitize($_POST['expense_date']);
        $receipt = isset($_POST['receipt_number']) ? sanitize($_POST['receipt_number']) : '';
        $paymentMethod = isset($_POST['payment_method']) ? sanitize($_POST['payment_method']) : 'cash';
        $vendor = isset($_POST['vendor']) ? sanitize($_POST['vendor']) : '';
        
        if (empty($category) || $amount <= 0 || empty($description) || empty($expenseDate)) {
            respond(false, 'All required fields must be filled');
        }
        
        // Check if payment_method and vendor columns exist, if not use old schema
        $checkColumns = $conn->query("SHOW COLUMNS FROM expenses LIKE 'payment_method'");
        $hasNewColumns = $checkColumns && $checkColumns->num_rows > 0;
        
        if ($hasNewColumns) {
            $stmt = $conn->prepare("INSERT INTO expenses (user_id, category, amount, description, expense_date, receipt_number, payment_method, vendor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdsssss", $_SESSION['user_id'], $category, $amount, $description, $expenseDate, $receipt, $paymentMethod, $vendor);
        } else {
            $stmt = $conn->prepare("INSERT INTO expenses (user_id, category, amount, description, expense_date, receipt_number) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdsss", $_SESSION['user_id'], $category, $amount, $description, $expenseDate, $receipt);
        }
        
        if ($stmt->execute()) {
            logActivity('EXPENSE_ADDED', "Added expense: $category - " . formatCurrency($amount));
            respond(true, 'Expense added successfully');
        } else {
            respond(false, 'Failed to add expense');
        }
    }
    
    if ($action === 'update_expense') {
        $id = (int)$_POST['id'];
        $category = sanitize($_POST['category']);
        $amount = floatval($_POST['amount']);
        $description = sanitize($_POST['description']);
        $expenseDate = sanitize($_POST['expense_date']);
        $receipt = isset($_POST['receipt_number']) ? sanitize($_POST['receipt_number']) : '';
        $paymentMethod = isset($_POST['payment_method']) ? sanitize($_POST['payment_method']) : 'cash';
        $vendor = isset($_POST['vendor']) ? sanitize($_POST['vendor']) : '';
        
        $stmt = $conn->prepare("UPDATE expenses SET category=?, amount=?, description=?, expense_date=?, receipt_number=?, payment_method=?, vendor=? WHERE id=?");
        $stmt->bind_param("sdssssi", $category, $amount, $description, $expenseDate, $receipt, $paymentMethod, $vendor, $id);
        
        if ($stmt->execute()) {
            logActivity('EXPENSE_UPDATED', "Updated expense ID: $id");
            respond(true, 'Expense updated successfully');
        } else {
            respond(false, 'Failed to update expense');
        }
    }
    
    if ($action === 'delete_expense') {
        $id = (int)$_POST['id'];
        
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            logActivity('EXPENSE_DELETED', "Deleted expense ID: $id");
            respond(true, 'Expense deleted successfully');
        } else {
            respond(false, 'Failed to delete expense');
        }
    }
    
    exit;
}

// Get filter parameters
$dateFrom = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$dateTo = isset($_GET['to']) ? sanitize($_GET['to']) : date('Y-m-d');
$categoryFilter = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$paymentFilter = isset($_GET['payment']) ? sanitize($_GET['payment']) : '';
$userFilter = isset($_GET['user']) ? intval($_GET['user']) : 0;

// Build query
$where = ["expense_date BETWEEN '$dateFrom' AND '$dateTo'"];
if ($categoryFilter) {
    $where[] = "category = '$categoryFilter'";
}
if ($paymentFilter) {
    $where[] = "payment_method = '$paymentFilter'";
}
if ($userFilter > 0) {
    $where[] = "user_id = $userFilter";
}
$whereClause = implode(' AND ', $where);

// Get expenses
$expenses = $conn->query("SELECT e.*, u.name as added_by 
                          FROM expenses e 
                          LEFT JOIN users u ON e.user_id = u.id 
                          WHERE $whereClause 
                          ORDER BY expense_date DESC, created_at DESC");

// Get summary
$summary = $conn->query("SELECT 
                         COALESCE(SUM(amount), 0) as total,
                         COUNT(*) as count,
                         COALESCE(AVG(amount), 0) as average,
                         COALESCE(MAX(amount), 0) as highest,
                         COALESCE(MIN(amount), 0) as lowest
                         FROM expenses 
                         WHERE $whereClause")->fetch_assoc();

// Get category breakdown
$categoryBreakdown = $conn->query("SELECT category, SUM(amount) as total, COUNT(*) as count, AVG(amount) as average
                                   FROM expenses 
                                   WHERE $whereClause 
                                   GROUP BY category 
                                   ORDER BY total DESC");

// Get payment method breakdown
$paymentBreakdown = $conn->query("SELECT 
                                  SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash,
                                  SUM(CASE WHEN payment_method = 'mpesa' THEN amount ELSE 0 END) as mpesa,
                                  SUM(CASE WHEN payment_method = 'card' THEN amount ELSE 0 END) as card,
                                  SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount ELSE 0 END) as bank
                                  FROM expenses 
                                  WHERE $whereClause")->fetch_assoc();

// Get daily trend
$dailyTrend = [];
$dailyQuery = $conn->query("SELECT DATE(expense_date) as date, SUM(amount) as total, COUNT(*) as count
                            FROM expenses 
                            WHERE $whereClause
                            GROUP BY DATE(expense_date)
                            ORDER BY date ASC");
while ($row = $dailyQuery->fetch_assoc()) {
    $dailyTrend[] = $row;
}

// Get unique categories
$categories = $conn->query("SELECT DISTINCT category FROM expenses ORDER BY category");

// Get users for filter
$users = $conn->query("SELECT id, name FROM users WHERE status='active' ORDER BY name");

include 'header.php';
?>

<style>
.expense-card {
    background: white;
    border-radius: 1.25rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.expense-card:hover {
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

.category-badge {
    padding: 0.625rem 1.25rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.category-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.chart-container {
    position: relative;
    height: 300px;
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
}

@media (max-width: 768px) {
    .expense-card {
        padding: 1rem;
    }
    
    .stat-icon {
        width: 3rem;
        height: 3rem;
        font-size: 1.5rem;
    }
    
    .chart-container {
        height: 250px;
    }
}
</style>

<!-- Page Header -->
<div class="expense-card mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-money-bill-wave text-red-600 mr-3"></i>
                Expense Management
            </h1>
            <p class="text-gray-600">Track and analyze business expenses</p>
        </div>
        
        <button onclick="openExpenseModal()" 
                class="px-8 py-4 rounded-xl font-bold text-white text-lg transition hover:opacity-90 shadow-lg"
                style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <i class="fas fa-plus mr-2"></i>Add Expense
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
    <div class="expense-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Total Expenses</p>
                <h3 class="text-2xl md:text-3xl font-bold text-red-600"><?php echo formatCurrency($summary['total']); ?></h3>
                <p class="text-xs text-gray-500 mt-1"><?php echo $summary['count']; ?> transactions</p>
            </div>
            <div class="stat-icon bg-red-100">
                <i class="fas fa-money-bill-wave text-red-600"></i>
            </div>
        </div>
    </div>
    
    <div class="expense-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Average Expense</p>
                <h3 class="text-2xl md:text-3xl font-bold text-blue-600"><?php echo formatCurrency($summary['average']); ?></h3>
                <p class="text-xs text-gray-500 mt-1">per transaction</p>
            </div>
            <div class="stat-icon bg-blue-100">
                <i class="fas fa-chart-line text-blue-600"></i>
            </div>
        </div>
    </div>
    
    <div class="expense-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Highest Expense</p>
                <h3 class="text-xl md:text-2xl font-bold text-orange-600"><?php echo formatCurrency($summary['highest']); ?></h3>
                <p class="text-xs text-gray-500 mt-1">single transaction</p>
            </div>
            <div class="stat-icon bg-orange-100">
                <i class="fas fa-arrow-up text-orange-600"></i>
            </div>
        </div>
    </div>
    
    <div class="expense-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Daily Average</p>
                <?php
                $days = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1);
                $avgPerDay = $summary['total'] / $days;
                ?>
                <h3 class="text-xl md:text-2xl font-bold text-purple-600"><?php echo formatCurrency($avgPerDay); ?></h3>
                <p class="text-xs text-gray-500 mt-1">over <?php echo round($days); ?> days</p>
            </div>
            <div class="stat-icon bg-purple-100">
                <i class="fas fa-calendar-day text-purple-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="expense-card mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-2">From Date</label>
            <input type="date" name="from" value="<?php echo $dateFrom; ?>" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none transition text-base"
                   style="focus:border-color: <?php echo $settings['primary_color']; ?>">
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-2">To Date</label>
            <input type="date" name="to" value="<?php echo $dateTo; ?>" 
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none transition text-base"
                   style="focus:border-color: <?php echo $settings['primary_color']; ?>">
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-2">Category</label>
            <select name="category" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base">
                <option value="">All Categories</option>
                <?php 
                $categories->data_seek(0);
                while ($cat = $categories->fetch_assoc()): 
                ?>
                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $categoryFilter === $cat['category'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['category']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-2">Payment Method</label>
            <select name="payment" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base">
                <option value="">All Methods</option>
                <option value="cash" <?php echo $paymentFilter === 'cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="mpesa" <?php echo $paymentFilter === 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
                <option value="card" <?php echo $paymentFilter === 'card' ? 'selected' : ''; ?>>Card</option>
                <option value="bank_transfer" <?php echo $paymentFilter === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
            </select>
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-2">Added By</label>
            <select name="user" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base">
                <option value="0">All Users</option>
                <?php while ($user = $users->fetch_assoc()): ?>
                <option value="<?php echo $user['id']; ?>" <?php echo $userFilter === $user['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="md:col-span-2 flex gap-2">
            <button type="submit" 
                    class="flex-1 px-6 py-3 rounded-xl font-bold text-white transition hover:opacity-90 shadow-lg"
                    style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                <i class="fas fa-filter mr-2"></i>Filter
            </button>
            <a href="/expenses.php" 
               class="px-4 py-3 border-2 border-gray-300 rounded-xl font-bold text-gray-700 hover:bg-gray-50 transition flex items-center justify-center">
                <i class="fas fa-redo"></i>
            </a>
        </div>
    </form>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Category Breakdown Chart -->
    <div class="expense-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">
            <i class="fas fa-chart-pie text-blue-500 mr-2"></i>Expenses by Category
        </h3>
        <div class="chart-container">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
    
    <!-- Daily Trend Chart -->
    <div class="expense-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">
            <i class="fas fa-chart-area text-purple-500 mr-2"></i>Daily Expense Trend
        </h3>
        <div class="chart-container">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
</div>

<!-- Category Breakdown & Payment Methods -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Category Breakdown List -->
    <div class="expense-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">
            <i class="fas fa-layer-group text-green-500 mr-2"></i>Category Breakdown
        </h3>
        <div class="space-y-3">
            <?php 
            $categoryBreakdown->data_seek(0);
            if ($categoryBreakdown->num_rows > 0):
                $categoryColors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-orange-500', 'bg-pink-500', 'bg-indigo-500', 'bg-red-500', 'bg-yellow-500'];
                $colorIndex = 0;
                $maxCat = 0;
                $catData = [];
                while ($cat = $categoryBreakdown->fetch_assoc()) {
                    $catData[] = $cat;
                    if ($cat['total'] > $maxCat) $maxCat = $cat['total'];
                }
                
                foreach ($catData as $cat):
                    $percentage = $maxCat > 0 ? ($cat['total'] / $maxCat) * 100 : 0;
                    $color = $categoryColors[$colorIndex % count($categoryColors)];
                    $colorIndex++;
            ?>
            <div class="bg-gray-50 rounded-xl p-4 hover:bg-gray-100 transition">
                <div class="flex justify-between items-center mb-2">
                    <div class="flex-1">
                        <p class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($cat['category']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo $cat['count']; ?> transactions • Avg: <?php echo formatCurrency($cat['average']); ?></p>
                    </div>
                    <p class="font-bold text-lg ml-3 text-red-600">
                        <?php echo formatCurrency($cat['total']); ?>
                    </p>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?php echo $color; ?>" style="width: <?php echo $percentage; ?>%"></div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <p class="text-center text-gray-400 py-8">No expenses yet</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Payment Methods -->
    <div class="expense-card">
        <h3 class="text-lg font-bold text-gray-900 mb-4">
            <i class="fas fa-credit-card text-purple-500 mr-2"></i>Payment Methods
        </h3>
        <div class="space-y-4 mb-6">
            <?php 
            $paymentMethods = [
                ['name' => 'Cash', 'amount' => $paymentBreakdown['cash'], 'icon' => 'fa-money-bill-wave', 'color' => 'green'],
                ['name' => 'M-Pesa', 'amount' => $paymentBreakdown['mpesa'], 'icon' => 'fa-mobile-alt', 'color' => 'blue'],
                ['name' => 'Card', 'amount' => $paymentBreakdown['card'], 'icon' => 'fa-credit-card', 'color' => 'purple'],
                ['name' => 'Bank Transfer', 'amount' => $paymentBreakdown['bank'], 'icon' => 'fa-university', 'color' => 'indigo']
            ];
            
            $totalPayments = array_sum(array_column($paymentMethods, 'amount'));
            
            foreach ($paymentMethods as $method):
                $percentage = $totalPayments > 0 ? ($method['amount'] / $totalPayments) * 100 : 0;
                if ($method['amount'] > 0):
            ?>
            <div class="bg-gray-50 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-<?php echo $method['color']; ?>-100 rounded-xl flex items-center justify-center">
                            <i class="fas <?php echo $method['icon']; ?> text-<?php echo $method['color']; ?>-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900"><?php echo $method['name']; ?></p>
                            <p class="text-xs text-gray-500"><?php echo number_format($percentage, 1); ?>%</p>
                        </div>
                    </div>
                    <p class="font-bold text-lg text-red-600"><?php echo formatCurrency($method['amount']); ?></p>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
        
        <div class="chart-container" style="height: 200px;">
            <canvas id="paymentChart"></canvas>
        </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="expense-card">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-bold text-gray-900">Expense Records</h3>
            <p class="text-sm text-gray-600">Showing <?php echo $expenses->num_rows; ?> expenses</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
                <tr>
                    <th class="text-left py-4 px-4 text-sm font-bold text-gray-700">Date</th>
                    <th class="text-left py-4 px-4 text-sm font-bold text-gray-700">Category</th>
                    <th class="text-left py-4 px-4 text-sm font-bold text-gray-700">Description</th>
                    <th class="text-left py-4 px-4 text-sm font-bold text-gray-700">Vendor</th>
                    <th class="text-left py-4 px-4 text-sm font-bold text-gray-700">Payment</th>
                    <th class="text-left py-4 px-4 text-sm font-bold text-gray-700">Receipt</th>
                    <th class="text-right py-4 px-4 text-sm font-bold text-gray-700">Amount</th>
                    <th class="text-left py-4 px-4 text-sm font-bold text-gray-700">Added By</th>
                    <th class="text-center py-4 px-4 text-sm font-bold text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($expenses->num_rows > 0): 
                    while ($expense = $expenses->fetch_assoc()): 
                        $paymentColors = [
                            'cash' => 'bg-green-100 text-green-800',
                            'mpesa' => 'bg-blue-100 text-blue-800',
                            'card' => 'bg-purple-100 text-purple-800',
                            'bank_transfer' => 'bg-indigo-100 text-indigo-800'
                        ];
                        $paymentColor = $paymentColors[$expense['payment_method']] ?? 'bg-gray-100 text-gray-800';
                ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                    <td class="py-4 px-4">
                        <div class="text-sm">
                            <div class="font-bold text-gray-900"><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('l', strtotime($expense['expense_date'])); ?></div>
                        </div>
                    </td>
                    <td class="py-4 px-4">
                        <span class="category-badge bg-blue-100 text-blue-800">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($expense['category']); ?>
                        </span>
                    </td>
                    <td class="py-4 px-4">
                        <p class="text-sm text-gray-900 font-medium max-w-xs truncate"><?php echo htmlspecialchars($expense['description']); ?></p>
                    </td>
                    <td class="py-4 px-4">
                        <?php if ($expense['vendor']): ?>
                        <span class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($expense['vendor']); ?></span>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-4 px-4">
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $paymentColor; ?>">
                            <?php echo strtoupper(str_replace('_', ' ', $expense['payment_method'])); ?>
                        </span>
                    </td>
                    <td class="py-4 px-4">
                        <?php if ($expense['receipt_number']): ?>
                        <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($expense['receipt_number']); ?></span>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-4 px-4 text-right">
                        <span class="text-lg font-bold text-red-600"><?php echo formatCurrency($expense['amount']); ?></span>
                    </td>
                    <td class="py-4 px-4">
                        <span class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($expense['added_by']); ?></span>
                    </td>
                    <td class="py-4 px-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick='editExpense(<?php echo json_encode($expense); ?>)' 
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" 
                                    title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteExpense(<?php echo $expense['id']; ?>, '<?php echo htmlspecialchars(addslashes($expense['description'])); ?>')" 
                                    class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" 
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="9" class="text-center py-20">
                        <i class="fas fa-money-bill-wave text-6xl text-gray-300 mb-4"></i>
                        <p class="text-xl text-gray-500 font-semibold mb-2">No expenses recorded</p>
                        <p class="text-gray-400 text-sm">Start tracking your expenses by adding one</p>
                        <button onclick="openExpenseModal()" 
                                class="mt-4 px-6 py-3 rounded-xl font-bold text-white transition hover:opacity-90 shadow-lg"
                                style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                            <i class="fas fa-plus mr-2"></i>Add First Expense
                        </button>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Expense Modal -->
<div id="expenseModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" style="backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="sticky top-0 p-6 rounded-t-2xl text-white z-10" style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-2xl font-bold" id="modalTitle">Add Expense</h3>
                    <p class="text-white/80 text-sm">Record a business expense</p>
                </div>
                <button onclick="closeExpenseModal()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <form id="expenseForm" class="p-6">
            <input type="hidden" id="expenseId" name="id">
            <input type="hidden" id="expenseAction" name="action" value="add_expense">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Category *</label>
                    <select name="category" id="expenseCategory" required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none transition text-base"
                            style="focus:border-color: <?php echo $settings['primary_color']; ?>">
                        <option value="">Select Category</option>
                        <option value="Utilities">⚡ Utilities</option>
                        <option value="Rent">🏢 Rent</option>
                        <option value="Salaries">💼 Salaries</option>
                        <option value="Supplies">📦 Supplies</option>
                        <option value="Transport">🚗 Transport</option>
                        <option value="Marketing">📢 Marketing</option>
                        <option value="Maintenance">🔧 Maintenance</option>
                        <option value="Insurance">🛡️ Insurance</option>
                        <option value="Licenses">📜 Licenses & Permits</option>
                        <option value="Equipment">💻 Equipment</option>
                        <option value="Food & Drinks">🍔 Food & Drinks</option>
                        <option value="Office Supplies">✏️ Office Supplies</option>
                        <option value="Security">🔒 Security</option>
                        <option value="Internet">🌐 Internet</option>
                        <option value="Other">📋 Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Amount (<?php echo $settings['currency']; ?>) *</label>
                    <input type="number" step="0.01" name="amount" id="expenseAmount" required min="0.01"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none transition text-lg font-bold"
                           style="focus:border-color: <?php echo $settings['primary_color']; ?>; color: #ef4444;"
                           placeholder="0.00">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Date *</label>
                    <input type="date" name="expense_date" id="expenseDate" value="<?php echo date('Y-m-d'); ?>" required 
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none transition text-base"
                           style="focus:border-color: <?php echo $settings['primary_color']; ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Payment Method *</label>
                    <select name="payment_method" id="expensePayment" required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base">
                        <option value="cash">💵 Cash</option>
                        <option value="mpesa">📱 M-Pesa</option>
                        <option value="card">💳 Card</option>
                        <option value="bank_transfer">🏦 Bank Transfer</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Vendor/Supplier</label>
                    <input type="text" name="vendor" id="expenseVendor"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none transition text-base"
                           style="focus:border-color: <?php echo $settings['primary_color']; ?>"
                           placeholder="e.g., ABC Suppliers">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Receipt Number</label>
                    <input type="text" name="receipt_number" id="expenseReceipt"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none transition text-base"
                           style="focus:border-color: <?php echo $settings['primary_color']; ?>"
                           placeholder="Optional">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Description *</label>
                    <textarea name="description" id="expenseDescription" rows="3" required 
                              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none transition text-base"
                              style="focus:border-color: <?php echo $settings['primary_color']; ?>"
                              placeholder="What was this expense for?"></textarea>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeExpenseModal()" 
                        class="flex-1 px-6 py-3 border-2 border-gray-300 rounded-xl font-bold text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="submitBtn"
                        class="flex-1 px-6 py-3 rounded-xl font-bold text-white transition hover:opacity-90 shadow-lg"
                        style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                    <i class="fas fa-save mr-2"></i>Save Expense
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const primaryColor = '<?php echo $settings['primary_color']; ?>';
const currency = '<?php echo $settings['currency']; ?>';

// Category Chart
const categoryData = <?php 
    $categoryBreakdown->data_seek(0);
    $cats = [];
    while ($c = $categoryBreakdown->fetch_assoc()) {
        $cats[] = $c;
    }
    echo json_encode($cats);
?>;

if (categoryData.length > 0) {
    const categoryColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: categoryData.map(c => c.category),
            datasets: [{
                data: categoryData.map(c => parseFloat(c.total)),
                backgroundColor: categoryColors,
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { padding: 12, font: { size: 11, weight: 'bold' } } },
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
}

// Daily Trend Chart
const trendData = <?php echo json_encode($dailyTrend); ?>;
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
        datasets: [{
            label: 'Daily Expenses',
            data: trendData.map(d => parseFloat(d.total)),
            borderColor: '#ef4444',
            backgroundColor: '#ef444420',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#ef4444'
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

// Payment Methods Chart
const paymentData = <?php echo json_encode($paymentBreakdown); ?>;
new Chart(document.getElementById('paymentChart'), {
    type: 'pie',
    data: {
        labels: ['Cash', 'M-Pesa', 'Card', 'Bank Transfer'],
        datasets: [{
            data: [
                parseFloat(paymentData.cash),
                parseFloat(paymentData.mpesa),
                parseFloat(paymentData.card),
                parseFloat(paymentData.bank)
            ],
            backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6', '#6366f1'],
            borderWidth: 3,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 10, font: { size: 11, weight: 'bold' } } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + currency + ' ' + context.parsed.toLocaleString();
                    }
                }
            }
        }
    }
});

function openExpenseModal() {
    document.getElementById('expenseModal').classList.remove('hidden');
    document.getElementById('expenseModal').classList.add('flex');
    document.getElementById('modalTitle').textContent = 'Add Expense';
    document.getElementById('expenseAction').value = 'add_expense';
    document.getElementById('expenseForm').reset();
    document.getElementById('expenseId').value = '';
    document.getElementById('expenseDate').value = '<?php echo date('Y-m-d'); ?>';
}

function closeExpenseModal() {
    document.getElementById('expenseModal').classList.add('hidden');
    document.getElementById('expenseModal').classList.remove('flex');
}

function editExpense(expense) {
    document.getElementById('expenseModal').classList.remove('hidden');
    document.getElementById('expenseModal').classList.add('flex');
    document.getElementById('modalTitle').textContent = 'Edit Expense';
    document.getElementById('expenseAction').value = 'update_expense';
    document.getElementById('expenseId').value = expense.id;
    document.getElementById('expenseCategory').value = expense.category;
    document.getElementById('expenseAmount').value = expense.amount;
    document.getElementById('expenseDate').value = expense.expense_date;
    document.getElementById('expensePayment').value = expense.payment_method || 'cash';
    document.getElementById('expenseVendor').value = expense.vendor || '';
    document.getElementById('expenseReceipt').value = expense.receipt_number || '';
    document.getElementById('expenseDescription').value = expense.description;
}

function deleteExpense(id, description) {
    if (!confirm(`Delete expense: "${description}"?\n\nThis action cannot be undone.`)) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_expense');
    formData.append('id', id);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Expense deleted successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to delete expense', 'error');
        }
    })
    .catch(err => {
        showToast('Connection error. Please try again.', 'error');
        console.error(err);
    });
}

document.getElementById('expenseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    const formData = new FormData(this);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to save expense', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Expense';
        }
    })
    .catch(err => {
        showToast('Connection error. Please try again.', 'error');
        console.error(err);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Expense';
    });
});

function showToast(message, type) {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-xl shadow-lg z-50`;
    toast.style.animation = 'slideIn 0.3s ease-out';
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>${message}`;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeExpenseModal();
    }
});
</script>

<style>
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}
</style>

<?php include 'footer.php'; ?>
