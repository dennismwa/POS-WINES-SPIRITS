<?php
require_once 'config.php';
requireOwner();

$page_title = 'Stock Movement Report';
$settings = getSettings();

// Get filter parameters
$dateFrom = isset($_GET['from']) ? sanitize($_GET['from']) : date('Y-m-01');
$dateTo = isset($_GET['to']) ? sanitize($_GET['to']) : date('Y-m-d');
$movementType = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$userFilter = isset($_GET['user']) ? intval($_GET['user']) : 0;
$productFilter = isset($_GET['product']) ? intval($_GET['product']) : 0;
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build WHERE clause
$where = ["DATE(sm.created_at) BETWEEN '$dateFrom' AND '$dateTo'"];

if ($movementType) {
    $where[] = "sm.movement_type = '$movementType'";
}

if ($userFilter > 0) {
    $where[] = "sm.user_id = $userFilter";
}

if ($productFilter > 0) {
    $where[] = "sm.product_id = $productFilter";
}

if ($searchQuery) {
    $where[] = "(p.name LIKE '%$searchQuery%' OR p.barcode LIKE '%$searchQuery%' OR p.sku LIKE '%$searchQuery%' OR sm.notes LIKE '%$searchQuery%')";
}

$whereClause = implode(' AND ', $where);

// Get summary statistics
$statsQuery = "SELECT 
    COUNT(*) as total_movements,
    SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END) as total_in,
    SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END) as total_out,
    SUM(CASE WHEN movement_type = 'adjustment' THEN quantity ELSE 0 END) as total_adjustments,
    SUM(CASE WHEN movement_type = 'sale' THEN quantity ELSE 0 END) as total_sales,
    COUNT(DISTINCT user_id) as users_count,
    COUNT(DISTINCT product_id) as products_affected
    FROM stock_movements sm
    WHERE $whereClause";
$stats = $conn->query($statsQuery)->fetch_assoc();

// Get movements with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$movementsQuery = "SELECT 
    sm.*,
    p.name as product_name,
    p.barcode,
    p.sku,
    p.stock_quantity as current_stock,
    u.name as user_name,
    u.role as user_role
    FROM stock_movements sm
    LEFT JOIN products p ON sm.product_id = p.id
    LEFT JOIN users u ON sm.user_id = u.id
    WHERE $whereClause
    ORDER BY sm.created_at DESC
    LIMIT $perPage OFFSET $offset";
$movements = $conn->query($movementsQuery);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM stock_movements sm 
               LEFT JOIN products p ON sm.product_id = p.id 
               WHERE $whereClause";
$totalMovements = $conn->query($countQuery)->fetch_assoc()['total'];
$totalPages = ceil($totalMovements / $perPage);

// Get users for filter
$users = $conn->query("SELECT id, name FROM users WHERE status='active' ORDER BY name");

// Get products for filter
$products = $conn->query("SELECT id, name FROM products WHERE status='active' ORDER BY name LIMIT 100");

include 'header.php';
?>

<style>
.movement-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.movement-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
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

.movement-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.timeline-dot {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .movement-card {
        padding: 1rem;
    }
    
    .stat-icon {
        width: 3rem;
        height: 3rem;
        font-size: 1.25rem;
    }
    
    .movement-badge {
        padding: 0.25rem 0.5rem;
        font-size: 0.625rem;
    }
}
</style>

<!-- Page Header -->
<div class="movement-card mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-exchange-alt text-blue-600 mr-3"></i>
                Stock Movement Report
            </h1>
            <p class="text-gray-600">Complete audit trail of all inventory changes</p>
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

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="movement-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Total Movements</p>
                <h3 class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['total_movements']); ?></h3>
                <p class="text-xs text-gray-500 mt-1">all transactions</p>
            </div>
            <div class="stat-icon bg-blue-100">
                <i class="fas fa-exchange-alt text-blue-600"></i>
            </div>
        </div>
    </div>
    
    <div class="movement-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Stock In</p>
                <h3 class="text-3xl font-bold text-green-600">+<?php echo number_format($stats['total_in']); ?></h3>
                <p class="text-xs text-gray-500 mt-1">units added</p>
            </div>
            <div class="stat-icon bg-green-100">
                <i class="fas fa-arrow-up text-green-600"></i>
            </div>
        </div>
    </div>
    
    <div class="movement-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Stock Out</p>
                <h3 class="text-3xl font-bold text-red-600">-<?php echo number_format($stats['total_out']); ?></h3>
                <p class="text-xs text-gray-500 mt-1">units removed</p>
            </div>
            <div class="stat-icon bg-red-100">
                <i class="fas fa-arrow-down text-red-600"></i>
            </div>
        </div>
    </div>
    
    <div class="movement-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Adjustments</p>
                <h3 class="text-3xl font-bold text-purple-600"><?php echo number_format($stats['total_adjustments']); ?></h3>
                <p class="text-xs text-gray-500 mt-1">manual changes</p>
            </div>
            <div class="stat-icon bg-purple-100">
                <i class="fas fa-edit text-purple-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="movement-card mb-6">
    <form method="GET" class="space-y-4">
        <!-- First Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">From Date</label>
                <input type="date" name="from" value="<?php echo $dateFrom; ?>" 
                       class="w-full px-3 py-2 text-sm border-2 border-gray-200 rounded-lg focus:outline-none">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">To Date</label>
                <input type="date" name="to" value="<?php echo $dateTo; ?>" 
                       class="w-full px-3 py-2 text-sm border-2 border-gray-200 rounded-lg focus:outline-none">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">Movement Type</label>
                <select name="type" class="w-full px-3 py-2 text-sm border-2 border-gray-200 rounded-lg focus:outline-none">
                    <option value="">All Types</option>
                    <option value="in" <?php echo $movementType === 'in' ? 'selected' : ''; ?>>Stock In</option>
                    <option value="out" <?php echo $movementType === 'out' ? 'selected' : ''; ?>>Stock Out</option>
                    <option value="adjustment" <?php echo $movementType === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                    <option value="sale" <?php echo $movementType === 'sale' ? 'selected' : ''; ?>>Sale</option>
                </select>
            </div>
        </div>
        
        <!-- Second Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">User</label>
                <select name="user" class="w-full px-3 py-2 text-sm border-2 border-gray-200 rounded-lg focus:outline-none">
                    <option value="0">All Users</option>
                    <?php 
                    $users->data_seek(0);
                    while ($user = $users->fetch_assoc()): 
                    ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                       placeholder="Product name..." 
                       class="w-full px-3 py-2 text-sm border-2 border-gray-200 rounded-lg focus:outline-none">
            </div>
            
            <div class="sm:col-span-2 lg:col-span-2 flex gap-2">
                <button type="submit" 
                        class="flex-1 px-4 py-2 text-sm rounded-lg font-bold text-white transition hover:opacity-90"
                        style="background-color: <?php echo $settings['primary_color']; ?>">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
                <a href="/stock-movements.php" 
                   class="px-4 py-2 text-sm border-2 border-gray-300 rounded-lg font-bold text-gray-700 hover:bg-gray-50 transition flex items-center justify-center">
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Movements Timeline -->
<div class="movement-card">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <div>
            <h3 class="text-lg md:text-xl font-bold text-gray-900">Movement History</h3>
            <p class="text-xs md:text-sm text-gray-600">Showing <?php echo number_format($totalMovements); ?> movements</p>
        </div>
    </div>

    <!-- Desktop Table View -->
    <div class="desktop-table overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
                <tr>
                    <th class="text-left py-3 px-3 text-xs font-bold text-gray-700">Date & Time</th>
                    <th class="text-left py-3 px-3 text-xs font-bold text-gray-700">Product</th>
                    <th class="text-center py-3 px-3 text-xs font-bold text-gray-700">Type</th>
                    <th class="text-center py-3 px-3 text-xs font-bold text-gray-700">Quantity</th>
                    <th class="text-left py-3 px-3 text-xs font-bold text-gray-700">User</th>
                    <th class="text-left py-3 px-3 text-xs font-bold text-gray-700">Reference</th>
                    <th class="text-center py-3 px-3 text-xs font-bold text-gray-700">Current Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($movements && $movements->num_rows > 0): 
                    $movementColors = [
                        'in' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'arrow-up', 'dot' => 'bg-green-500'],
                        'out' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'arrow-down', 'dot' => 'bg-red-500'],
                        'adjustment' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'icon' => 'edit', 'dot' => 'bg-purple-500'],
                        'sale' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'shopping-cart', 'dot' => 'bg-blue-500']
                    ];
                    
                    $movements->data_seek(0);
                    while ($movement = $movements->fetch_assoc()):
                        $colors = $movementColors[$movement['movement_type']] ?? $movementColors['adjustment'];
                ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                    <td class="py-3 px-3">
                        <div class="flex items-center gap-2">
                            <div class="timeline-dot <?php echo $colors['dot']; ?>"></div>
                            <div>
                                <div class="font-bold text-xs text-gray-900">
                                    <?php echo date('M d, Y', strtotime($movement['created_at'])); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo date('h:i A', strtotime($movement['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    
                    <td class="py-3 px-3">
                        <div>
                            <div class="font-bold text-xs text-gray-900 max-w-[200px] truncate">
                                <?php echo htmlspecialchars($movement['product_name'] ?? 'Deleted Product'); ?>
                            </div>
                            <?php if ($movement['sku']): ?>
                            <div class="text-xs text-gray-500 font-mono">SKU: <?php echo htmlspecialchars($movement['sku']); ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    
                    <td class="py-3 px-3 text-center">
                        <span class="movement-badge <?php echo $colors['bg'] . ' ' . $colors['text']; ?>">
                            <i class="fas fa-<?php echo $colors['icon']; ?>"></i>
                            <span class="hidden lg:inline"><?php echo strtoupper($movement['movement_type']); ?></span>
                        </span>
                    </td>
                    
                    <td class="py-3 px-3 text-center">
                        <span class="font-bold text-lg <?php echo $colors['text']; ?>">
                            <?php echo $movement['movement_type'] === 'in' ? '+' : ($movement['movement_type'] === 'out' ? '-' : ''); ?>
                            <?php echo number_format($movement['quantity']); ?>
                        </span>
                    </td>
                    
                    <td class="py-3 px-3">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-bold"
                                 style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                                <?php echo strtoupper(substr($movement['user_name'] ?? 'SYS', 0, 2)); ?>
                            </div>
                            <div class="hidden xl:block">
                                <div class="font-semibold text-xs text-gray-900">
                                    <?php echo htmlspecialchars($movement['user_name'] ?? 'System'); ?>
                                </div>
                                <div class="text-xs text-gray-500 capitalize">
                                    <?php echo htmlspecialchars($movement['user_role'] ?? 'system'); ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    
                    <td class="py-3 px-3">
                        <?php if ($movement['reference_type']): ?>
                        <div class="text-xs">
                            <span class="font-semibold text-gray-900 capitalize">
                                <?php echo htmlspecialchars($movement['reference_type']); ?>
                            </span>
                            <?php if ($movement['reference_id']): ?>
                            <span class="text-gray-500">#<?php echo $movement['reference_id']; ?></span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="py-3 px-3 text-center">
                        <span class="font-bold text-base text-gray-900">
                            <?php echo number_format($movement['current_stock'] ?? 0); ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="7" class="text-center py-20">
                        <i class="fas fa-exchange-alt text-6xl text-gray-300 mb-4"></i>
                        <p class="text-xl text-gray-500 font-semibold mb-2">No stock movements found</p>
                        <p class="text-gray-400 text-sm">Try adjusting your filters</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="mobile-view">
        <?php if ($movements && $movements->num_rows > 0): 
            $movementColors = [
                'in' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'arrow-up', 'dot' => 'bg-green-500'],
                'out' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'arrow-down', 'dot' => 'bg-red-500'],
                'adjustment' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'icon' => 'edit', 'dot' => 'bg-purple-500'],
                'sale' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'shopping-cart', 'dot' => 'bg-blue-500']
            ];
            
            $movements->data_seek(0);
            while ($movement = $movements->fetch_assoc()):
                $colors = $movementColors[$movement['movement_type']] ?? $movementColors['adjustment'];
        ?>
        <div class="mobile-movement-card type-<?php echo $movement['movement_type']; ?>">
            <!-- Header -->
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <h4 class="font-bold text-sm text-gray-900 mb-1">
                        <?php echo htmlspecialchars($movement['product_name'] ?? 'Deleted Product'); ?>
                    </h4>
                    <?php if ($movement['sku']): ?>
                    <p class="text-xs text-gray-500 font-mono">SKU: <?php echo htmlspecialchars($movement['sku']); ?></p>
                    <?php endif; ?>
                </div>
                <span class="movement-badge <?php echo $colors['bg'] . ' ' . $colors['text']; ?> ml-2">
                    <i class="fas fa-<?php echo $colors['icon']; ?>"></i>
                    <?php echo strtoupper($movement['movement_type']); ?>
                </span>
            </div>
            
            <!-- Quantity -->
            <div class="mb-3 p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-600 font-medium">Quantity Changed</span>
                    <span class="font-bold text-2xl <?php echo $colors['text']; ?>">
                        <?php echo $movement['movement_type'] === 'in' ? '+' : ($movement['movement_type'] === 'out' ? '-' : ''); ?>
                        <?php echo number_format($movement['quantity']); ?>
                    </span>
                </div>
            </div>
            
            <!-- Details Grid -->
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Current Stock</p>
                    <p class="font-bold text-sm text-gray-900"><?php echo number_format($movement['current_stock'] ?? 0); ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Date & Time</p>
                    <p class="font-semibold text-xs text-gray-900"><?php echo date('M d, Y', strtotime($movement['created_at'])); ?></p>
                    <p class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($movement['created_at'])); ?></p>
                </div>
            </div>
            
            <!-- User & Reference -->
            <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-bold"
                         style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                        <?php echo strtoupper(substr($movement['user_name'] ?? 'SYS', 0, 2)); ?>
                    </div>
                    <div>
                        <p class="font-semibold text-xs text-gray-900"><?php echo htmlspecialchars($movement['user_name'] ?? 'System'); ?></p>
                        <p class="text-xs text-gray-500 capitalize"><?php echo htmlspecialchars($movement['user_role'] ?? 'system'); ?></p>
                    </div>
                </div>
                
                <?php if ($movement['reference_type']): ?>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Reference</p>
                    <p class="text-xs font-semibold text-gray-900 capitalize">
                        <?php echo htmlspecialchars($movement['reference_type']); ?>
                        <?php if ($movement['reference_id']): ?>
                        #<?php echo $movement['reference_id']; ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="text-center py-12">
            <i class="fas fa-exchange-alt text-5xl text-gray-300 mb-3"></i>
            <p class="text-lg text-gray-500 font-semibold mb-1">No stock movements found</p>
            <p class="text-gray-400 text-sm">Try adjusting your filters</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="mt-4 md:mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-gray-200 pt-4">
        <div class="text-xs md:text-sm text-gray-600 text-center sm:text-left">
            Showing <span class="font-semibold"><?php echo $offset + 1; ?></span> to 
            <span class="font-semibold"><?php echo min($offset + $perPage, $totalMovements); ?></span> of 
            <span class="font-semibold"><?php echo number_format($totalMovements); ?></span> movements
        </div>
        <div class="flex flex-wrap items-center justify-center gap-2">
            <?php if ($page > 1): 
                $prevParams = $_GET;
                $prevParams['page'] = $page - 1;
            ?>
            <a href="?<?php echo http_build_query($prevParams); ?>" 
               class="px-3 py-2 border-2 border-gray-300 rounded-lg text-xs md:text-sm font-semibold hover:bg-gray-100 transition">
                <i class="fas fa-chevron-left mr-1"></i><span class="hidden sm:inline">Previous</span>
            </a>
            <?php endif; ?>
            
            <div class="flex gap-1">
                <?php 
                $startPage = max(1, $page - 1);
                $endPage = min($totalPages, $page + 1);
                
                // Show first page if not in range
                if ($startPage > 1): ?>
                    <a href="?<?php $fp = $_GET; $fp['page'] = 1; echo http_build_query($fp); ?>" 
                       class="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center rounded-lg text-xs md:text-sm font-semibold text-gray-700 hover:bg-gray-100 transition">
                        1
                    </a>
                    <?php if ($startPage > 2): ?>
                    <span class="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center text-gray-400">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++):
                    $pageParams = $_GET;
                    $pageParams['page'] = $i;
                ?>
                <a href="?<?php echo http_build_query($pageParams); ?>" 
                   class="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center rounded-lg text-xs md:text-sm font-semibold transition <?php echo $i === $page ? 'text-white' : 'text-gray-700 hover:bg-gray-100'; ?>"
                   style="<?php echo $i === $page ? 'background-color: ' . $settings['primary_color'] : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <!-- Show last page if not in range -->
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                    <span class="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center text-gray-400">...</span>
                    <?php endif; ?>
                    <a href="?<?php $lp = $_GET; $lp['page'] = $totalPages; echo http_build_query($lp); ?>" 
                       class="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center rounded-lg text-xs md:text-sm font-semibold text-gray-700 hover:bg-gray-100 transition">
                        <?php echo $totalPages; ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($page < $totalPages):
                $nextParams = $_GET;
                $nextParams['page'] = $page + 1;
            ?>
            <a href="?<?php echo http_build_query($nextParams); ?>" 
               class="px-3 py-2 rounded-lg text-xs md:text-sm font-semibold text-white transition hover:opacity-90"
               style="background-color: <?php echo $settings['primary_color']; ?>">
                <span class="hidden sm:inline">Next</span><i class="fas fa-chevron-right ml-1"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function exportReport() {
    // Get current filters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    // For now, just show an alert
    alert('Export functionality will download a CSV file with all filtered movements.\n\nThis feature can be implemented by creating an export endpoint.');
    
    // In production, you would redirect to an export endpoint:
    // window.location.href = '/api/export-stock-movements.php?' + params.toString();
}
</script>

<?php include 'footer.php'; ?>
