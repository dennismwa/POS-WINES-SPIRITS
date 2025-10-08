<?php
require_once 'config.php';
requireAuth();

$page_title = 'Stock Management';
$settings = getSettings();

// Handle AJAX stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'adjust_stock') {
        $productId = intval($_POST['product_id']);
        $type = sanitize($_POST['adjustment_type']);
        $quantity = intval($_POST['quantity']);
        $notes = sanitize($_POST['notes']);
        
        if ($quantity <= 0) {
            respond(false, 'Quantity must be greater than zero');
        }
        
        $product = $conn->query("SELECT * FROM products WHERE id = $productId")->fetch_assoc();
        if (!$product) {
            respond(false, 'Product not found');
        }
        
        $conn->begin_transaction();
        
        try {
            if ($type === 'in') {
                $newStock = $product['stock_quantity'] + $quantity;
                $conn->query("UPDATE products SET stock_quantity = $newStock WHERE id = $productId");
                recordStockMovement($productId, $_SESSION['user_id'], 'in', $quantity, null, null, $notes);
            } elseif ($type === 'out') {
                if ($product['stock_quantity'] < $quantity) {
                    throw new Exception('Insufficient stock');
                }
                $newStock = $product['stock_quantity'] - $quantity;
                $conn->query("UPDATE products SET stock_quantity = $newStock WHERE id = $productId");
                recordStockMovement($productId, $_SESSION['user_id'], 'out', $quantity, null, null, $notes);
            } elseif ($type === 'adjustment') {
                $conn->query("UPDATE products SET stock_quantity = $quantity WHERE id = $productId");
                recordStockMovement($productId, $_SESSION['user_id'], 'adjustment', $quantity, null, null, $notes);
            }
            
            $conn->commit();
            logActivity('STOCK_ADJUSTED', "Adjusted stock for product ID: $productId - Type: $type, Qty: $quantity");
            respond(true, 'Stock adjusted successfully');
            
        } catch (Exception $e) {
            $conn->rollback();
            respond(false, $e->getMessage());
        }
    }
    
    exit;
}

// Get filter parameters
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$stockFilter = isset($_GET['stock']) ? sanitize($_GET['stock']) : 'all';

// Build query
$where = ["p.status = 'active'"];
if ($searchQuery) {
    $where[] = "(p.name LIKE '%$searchQuery%' OR p.barcode LIKE '%$searchQuery%' OR p.sku LIKE '%$searchQuery%')";
}
if ($categoryFilter > 0) {
    $where[] = "p.category_id = $categoryFilter";
}
if ($stockFilter === 'low') {
    $where[] = "p.stock_quantity <= p.reorder_level AND p.stock_quantity > 0";
} elseif ($stockFilter === 'out') {
    $where[] = "p.stock_quantity = 0";
} elseif ($stockFilter === 'good') {
    $where[] = "p.stock_quantity > p.reorder_level";
}

$whereClause = implode(' AND ', $where);

// Get inventory stats
$stats = $conn->query("SELECT 
    COALESCE(SUM(stock_quantity * cost_price), 0) as cost_value,
    COALESCE(SUM(stock_quantity * selling_price), 0) as sell_value,
    COUNT(*) as total_products,
    SUM(CASE WHEN stock_quantity <= reorder_level AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
    SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
    FROM products WHERE status = 'active'")->fetch_assoc();

// Get products
$products = $conn->query("SELECT p.*, c.name as category_name 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE $whereClause 
                         ORDER BY p.stock_quantity ASC, p.name ASC");

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

// Get recent stock movements
$recentMovements = $conn->query("SELECT sm.*, p.name as product_name, u.name as user_name 
                                 FROM stock_movements sm 
                                 JOIN products p ON sm.product_id = p.id 
                                 JOIN users u ON sm.user_id = u.id 
                                 ORDER BY sm.created_at DESC 
                                 LIMIT 10");

include 'header.php';
?>

<style>
.inventory-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.stat-badge {
    padding: 0.5rem 1rem;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.action-btn {
    padding: 0.5rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .inventory-card {
        padding: 1rem;
    }
}
</style>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
    <div class="inventory-card hover:shadow-xl">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Inventory Cost</p>
                <h3 class="text-2xl md:text-3xl font-bold text-blue-600"><?php echo formatCurrency($stats['cost_value']); ?></h3>
                <p class="text-xs text-gray-500 mt-1">wholesale value</p>
            </div>
            <div class="w-12 h-12 md:w-14 md:h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-box text-blue-600 text-xl md:text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="inventory-card hover:shadow-xl">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Potential Revenue</p>
                <h3 class="text-2xl md:text-3xl font-bold text-green-600"><?php echo formatCurrency($stats['sell_value']); ?></h3>
                <p class="text-xs text-gray-500 mt-1">retail value</p>
            </div>
            <div class="w-12 h-12 md:w-14 md:h-14 bg-green-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-money-bill-wave text-green-600 text-xl md:text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="inventory-card hover:shadow-xl">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Low Stock</p>
                <h3 class="text-2xl md:text-3xl font-bold text-orange-600"><?php echo $stats['low_stock']; ?></h3>
                <p class="text-xs text-gray-500 mt-1">need reorder</p>
            </div>
            <div class="w-12 h-12 md:w-14 md:h-14 bg-orange-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-orange-600 text-xl md:text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="inventory-card hover:shadow-xl">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Out of Stock</p>
                <h3 class="text-2xl md:text-3xl font-bold text-red-600"><?php echo $stats['out_of_stock']; ?></h3>
                <p class="text-xs text-gray-500 mt-1">urgent items</p>
            </div>
            <div class="w-12 h-12 md:w-14 md:h-14 bg-red-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-times-circle text-red-600 text-xl md:text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="inventory-card mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
        <div class="md:col-span-4">
            <label class="block text-sm font-bold text-gray-700 mb-2">Search Products</label>
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Name, barcode, or SKU..." 
                       class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base">
            </div>
        </div>
        
        <div class="md:col-span-3">
            <label class="block text-sm font-bold text-gray-700 mb-2">Category</label>
            <select name="category" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base">
                <option value="0">All Categories</option>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="md:col-span-3">
            <label class="block text-sm font-bold text-gray-700 mb-2">Stock Status</label>
            <select name="stock" class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base">
                <option value="all" <?php echo $stockFilter === 'all' ? 'selected' : ''; ?>>All Products</option>
                <option value="good" <?php echo $stockFilter === 'good' ? 'selected' : ''; ?>>In Stock</option>
                <option value="low" <?php echo $stockFilter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out" <?php echo $stockFilter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
            </select>
        </div>
        
        <div class="md:col-span-2 flex gap-2">
            <button type="submit" 
                    class="flex-1 px-6 py-3 rounded-lg font-bold text-white transition hover:opacity-90"
                    style="background-color: <?php echo $settings['primary_color']; ?>">
                <i class="fas fa-filter"></i>
            </button>
            <a href="/inventory.php" 
               class="px-4 py-3 border-2 border-gray-300 rounded-lg font-bold text-gray-700 hover:bg-gray-50 transition flex items-center justify-center">
                <i class="fas fa-redo"></i>
            </a>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="inventory-card mb-6 overflow-hidden">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-4">
        <div>
            <h3 class="text-lg font-bold text-gray-900">Stock Overview</h3>
            <p class="text-sm text-gray-600">Showing <?php echo $products->num_rows; ?> products</p>
        </div>
        <button onclick="openAdjustModal()" 
                class="px-6 py-3 rounded-lg font-bold text-white transition hover:opacity-90 shadow-lg"
                style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <i class="fas fa-exchange-alt mr-2"></i>Adjust Stock
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
                <tr>
                    <th class="text-left py-3 px-4 text-xs md:text-sm font-bold text-gray-700">Product</th>
                    <th class="text-left py-3 px-4 text-xs md:text-sm font-bold text-gray-700">Category</th>
                    <th class="text-center py-3 px-4 text-xs md:text-sm font-bold text-gray-700">Stock</th>
                    <th class="text-center py-3 px-4 text-xs md:text-sm font-bold text-gray-700">Reorder</th>
                    <th class="text-right py-3 px-4 text-xs md:text-sm font-bold text-gray-700">Value</th>
                    <th class="text-center py-3 px-4 text-xs md:text-sm font-bold text-gray-700">Status</th>
                    <th class="text-center py-3 px-4 text-xs md:text-sm font-bold text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($products->num_rows > 0): 
                    while ($product = $products->fetch_assoc()):
                        $stockStatus = getStockStatus($product['stock_quantity'], $product['reorder_level']);
                        $stockValue = $product['stock_quantity'] * $product['selling_price'];
                ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                    <td class="py-3 px-4">
                        <p class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                        <?php if ($product['sku']): ?>
                        <p class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars($product['sku']); ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4">
                        <span class="stat-badge bg-gray-100 text-gray-800 text-xs">
                            <?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="font-bold text-xl"><?php echo $product['stock_quantity']; ?></span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="text-sm text-gray-600"><?php echo $product['reorder_level']; ?></span>
                    </td>
                    <td class="py-3 px-4 text-right">
                        <span class="font-bold text-sm"><?php echo formatCurrency($stockValue); ?></span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="stat-badge text-xs <?php echo $stockStatus['color'] === 'green' ? 'bg-green-100 text-green-800' : ($stockStatus['color'] === 'orange' ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'); ?>">
                            <?php echo $stockStatus['label']; ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <button onclick='adjustStockModal(<?php echo json_encode($product); ?>)' 
                                class="action-btn hover:bg-blue-50 text-blue-600" 
                                title="Adjust Stock">
                            <i class="fas fa-edit text-base md:text-lg"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="7" class="text-center py-20">
                        <i class="fas fa-boxes text-6xl text-gray-300 mb-4"></i>
                        <p class="text-xl text-gray-500 font-semibold">No products found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Movements -->
<div class="inventory-card">
    <h3 class="text-lg font-bold text-gray-900 mb-4">Recent Stock Movements</h3>
    <div class="space-y-3">
        <?php if ($recentMovements->num_rows > 0): 
            while ($movement = $recentMovements->fetch_assoc()):
                $icon = $movement['movement_type'] === 'in' ? 'arrow-up' : ($movement['movement_type'] === 'out' ? 'arrow-down' : 'sync');
                $color = $movement['movement_type'] === 'in' ? 'text-green-600 bg-green-100' : ($movement['movement_type'] === 'out' ? 'text-red-600 bg-red-100' : 'text-blue-600 bg-blue-100');
        ?>
        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center <?php echo $color; ?>">
                <i class="fas fa-<?php echo $icon; ?>"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-bold text-sm text-gray-900 truncate"><?php echo htmlspecialchars($movement['product_name']); ?></p>
                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($movement['user_name']); ?> • <?php echo date('M d, Y h:i A', strtotime($movement['created_at'])); ?></p>
            </div>
            <div class="text-right">
                <p class="font-bold text-lg <?php echo $movement['movement_type'] === 'in' ? 'text-green-600' : ($movement['movement_type'] === 'out' ? 'text-red-600' : 'text-blue-600'); ?>">
                    <?php echo $movement['movement_type'] === 'in' ? '+' : ($movement['movement_type'] === 'out' ? '-' : ''); ?><?php echo $movement['quantity']; ?>
                </p>
                <p class="text-xs text-gray-500 uppercase"><?php echo $movement['movement_type']; ?></p>
            </div>
        </div>
        <?php endwhile; else: ?>
        <p class="text-center text-gray-400 py-8">No movements yet</p>
        <?php endif; ?>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div id="adjustModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" style="backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl">
        <div class="p-6 rounded-t-2xl text-white" style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-2xl font-bold">Adjust Stock</h3>
                    <p class="text-white/80 text-sm">Update inventory levels</p>
                </div>
                <button onclick="closeAdjustModal()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <form id="adjustForm" class="p-6">
            <input type="hidden" id="productId" name="product_id">
            <input type="hidden" name="action" value="adjust_stock">
            
            <div id="productInfo" class="p-4 bg-gray-50 rounded-xl mb-6">
                <p class="font-bold text-lg text-gray-900" id="selectedProductName"></p>
                <div class="flex items-center gap-4 mt-2">
                    <p class="text-sm text-gray-600">Current Stock: <span id="currentStock" class="font-bold text-xl" style="color: <?php echo $settings['primary_color']; ?>"></span></p>
                    <p class="text-sm text-gray-600">Reorder Level: <span id="reorderLevel" class="font-bold"></span></p>
                </div>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Adjustment Type *</label>
                    <select name="adjustment_type" id="adjustmentType" required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base">
                        <option value="in">Stock In (Add)</option>
                        <option value="out">Stock Out (Remove)</option>
                        <option value="adjustment">Set Exact Amount</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Quantity *</label>
                    <input type="number" name="quantity" id="adjustQuantity" required min="1" 
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-lg font-bold">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Notes/Reason</label>
                    <textarea name="notes" id="adjustNotes" rows="3" 
                              class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none"
                              placeholder="Why are you adjusting this stock?"></textarea>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeAdjustModal()" 
                        class="flex-1 px-6 py-3 border-2 border-gray-300 rounded-lg font-bold text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="adjustSubmitBtn"
                        class="flex-1 px-6 py-3 rounded-lg font-bold text-white transition hover:opacity-90 shadow-lg"
                        style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                    <i class="fas fa-save mr-2"></i>Adjust Stock
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdjustModal() {
    document.getElementById('adjustModal').classList.remove('hidden');
    document.getElementById('adjustModal').classList.add('flex');
    document.getElementById('adjustForm').reset();
}

function closeAdjustModal() {
    document.getElementById('adjustModal').classList.add('hidden');
    document.getElementById('adjustModal').classList.remove('flex');
}

function adjustStockModal(product) {
    document.getElementById('productId').value = product.id;
    document.getElementById('selectedProductName').textContent = product.name;
    document.getElementById('currentStock').textContent = product.stock_quantity;
    document.getElementById('reorderLevel').textContent = product.reorder_level;
    openAdjustModal();
}

document.getElementById('adjustForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('adjustSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    const formData = new FormData(this);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save mr-2"></i>Adjust Stock';
        }
    })
    .catch(err => {
        showToast('Connection error', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i>Adjust Stock';
    });
});

function showToast(message, type) {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    toast.style.animation = 'slideIn 0.3s ease-out';
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>${message}`;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}

// ESC key to close
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdjustModal();
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
</style>


<?php include 'footer.php'; ?>
