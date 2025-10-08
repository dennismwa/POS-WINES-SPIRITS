<?php
require_once 'config.php';
requireAuth();

$page_title = 'Products';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    if ($action === 'add' || $action === 'edit') {
        $category_id = (int)$_POST['category_id'];
        $name = sanitize($_POST['name']);
        $barcode = sanitize($_POST['barcode']);
        $description = sanitize($_POST['description']);
        $cost_price = floatval($_POST['cost_price']);
        $selling_price = floatval($_POST['selling_price']);
        $stock_quantity = (int)$_POST['stock_quantity'];
        $reorder_level = (int)$_POST['reorder_level'];
        $supplier = sanitize($_POST['supplier']);
        $unit = sanitize($_POST['unit']);
        $sku = sanitize($_POST['sku']);
        $location = sanitize($_POST['location']);
        $expiry_date = !empty($_POST['expiry_date']) ? sanitize($_POST['expiry_date']) : null;
        
        if ($action === 'add') {
            // Check if barcode exists
            if (!empty($barcode)) {
                $check = $conn->query("SELECT id FROM products WHERE barcode='$barcode'");
                if ($check->num_rows > 0) {
                    respond(false, 'Barcode already exists');
                }
            }
            
            $stmt = $conn->prepare("INSERT INTO products (category_id, name, barcode, description, cost_price, selling_price, stock_quantity, reorder_level, supplier, unit, sku, location, expiry_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssddiisssssi", $category_id, $name, $barcode, $description, $cost_price, $selling_price, $stock_quantity, $reorder_level, $supplier, $unit, $sku, $location, $expiry_date, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $product_id = $conn->insert_id;
                
                // Log initial stock
                if ($stock_quantity > 0) {
                    recordStockMovement($product_id, $_SESSION['user_id'], 'in', $stock_quantity, null, null, 'Initial stock');
                }
                
                logActivity('PRODUCT_ADDED', "Added product: $name");
                respond(true, 'Product added successfully');
            } else {
                respond(false, 'Failed to add product');
            }
        } else {
            $id = (int)$_POST['id'];
            
            // Get old stock quantity for comparison
            $old_stock = $conn->query("SELECT stock_quantity FROM products WHERE id=$id")->fetch_assoc()['stock_quantity'];
            
            $stmt = $conn->prepare("UPDATE products SET category_id=?, name=?, barcode=?, description=?, cost_price=?, selling_price=?, stock_quantity=?, reorder_level=?, supplier=?, unit=?, sku=?, location=?, expiry_date=? WHERE id=?");
            $stmt->bind_param("isssddissssssi", $category_id, $name, $barcode, $description, $cost_price, $selling_price, $stock_quantity, $reorder_level, $supplier, $unit, $sku, $location, $expiry_date, $id);
            
            if ($stmt->execute()) {
                // Log stock adjustment if changed
                if ($old_stock != $stock_quantity) {
                    $diff = $stock_quantity - $old_stock;
                    $movement_type = $diff > 0 ? 'in' : 'out';
                    $abs_diff = abs($diff);
                    recordStockMovement($id, $_SESSION['user_id'], $movement_type, $abs_diff, null, null, 'Stock adjustment via edit');
                }
                
                logActivity('PRODUCT_UPDATED', "Updated product: $name");
                respond(true, 'Product updated successfully');
            } else {
                respond(false, 'Failed to update product');
            }
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE products SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            logActivity('PRODUCT_DELETED', "Deleted product ID: $id");
            respond(true, 'Product deleted successfully');
        } else {
            respond(false, 'Failed to delete product');
        }
        $stmt->close();
    }
    
    if ($action === 'get') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();
        respond(true, '', $product);
    }
    
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$stock_filter = isset($_GET['stock']) ? sanitize($_GET['stock']) : 'all';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'name_asc';

// Build query
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'active'";

if (!empty($search)) {
    $query .= " AND (p.name LIKE '%$search%' OR p.barcode LIKE '%$search%' OR p.sku LIKE '%$search%')";
}

if ($category_filter > 0) {
    $query .= " AND p.category_id = $category_filter";
}

if ($stock_filter === 'low') {
    $query .= " AND p.stock_quantity <= p.reorder_level AND p.stock_quantity > 0";
} elseif ($stock_filter === 'out') {
    $query .= " AND p.stock_quantity = 0";
} elseif ($stock_filter === 'in_stock') {
    $query .= " AND p.stock_quantity > p.reorder_level";
}

// Sorting
switch ($sort) {
    case 'name_asc':
        $query .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY p.name DESC";
        break;
    case 'price_asc':
        $query .= " ORDER BY p.selling_price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.selling_price DESC";
        break;
    case 'stock_asc':
        $query .= " ORDER BY p.stock_quantity ASC";
        break;
    case 'stock_desc':
        $query .= " ORDER BY p.stock_quantity DESC";
        break;
    case 'newest':
        $query .= " ORDER BY p.created_at DESC";
        break;
    default:
        $query .= " ORDER BY p.name ASC";
}

$products = $conn->query($query);
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

// Get statistics
$total_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE status='active'")->fetch_assoc()['count'];
$low_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level AND stock_quantity > 0 AND status='active'")->fetch_assoc()['count'];
$out_of_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0 AND status='active'")->fetch_assoc()['count'];
$total_value = $conn->query("SELECT SUM(stock_quantity * selling_price) as value FROM products WHERE status='active'")->fetch_assoc()['value'];

$settings = getSettings();
include 'header.php';
?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Total Products</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo $total_products; ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-wine-bottle text-blue-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Low Stock</p>
                <p class="text-2xl font-bold text-orange-600"><?php echo $low_stock; ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-orange-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Out of Stock</p>
                <p class="text-2xl font-bold text-red-600"><?php echo $out_of_stock; ?></p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-times-circle text-red-600 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Inventory Value</p>
                <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($total_value ?: 0); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Product Management</h2>
                <p class="text-sm text-gray-600 mt-1">Manage your inventory products</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button onclick="openProductModal()" 
                        class="px-4 py-2 rounded-lg font-semibold text-white transition hover:opacity-90"
                        style="background-color: <?php echo $settings['primary_color']; ?>">
                    <i class="fas fa-plus mr-2"></i>Add Product
                </button>
            </div>
        </div>
    </div>

    <!-- Advanced Filters -->
    <div class="p-6 border-b bg-gray-50">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, barcode, or SKU..." 
                           class="w-full pl-10 pr-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>
            
            <select name="category" class="px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                <option value="0">All Categories</option>
                <?php 
                $categories_copy = $conn->query("SELECT * FROM categories WHERE status='active' ORDER BY name");
                while ($cat = $categories_copy->fetch_assoc()): 
                ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>

            <select name="stock" class="px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                <option value="all" <?php echo $stock_filter === 'all' ? 'selected' : ''; ?>>All Stock Levels</option>
                <option value="in_stock" <?php echo $stock_filter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
            </select>

            <select name="sort" class="px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price (Low-High)</option>
                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price (High-Low)</option>
                <option value="stock_asc" <?php echo $sort === 'stock_asc' ? 'selected' : ''; ?>>Stock (Low-High)</option>
                <option value="stock_desc" <?php echo $sort === 'stock_desc' ? 'selected' : ''; ?>>Stock (High-Low)</option>
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
            </select>

            <div class="md:col-span-5 flex gap-2">
                <button type="submit" 
                        class="px-6 py-2 rounded-lg font-semibold text-white transition hover:opacity-90"
                        style="background-color: <?php echo $settings['primary_color']; ?>">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
                <a href="/products.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium">
                    <i class="fas fa-redo mr-2"></i>Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU/Barcode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cost</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($product = $products->fetch_assoc()): 
                    $stock_value = $product['stock_quantity'] * $product['selling_price'];
                    $profit_margin = $product['selling_price'] > 0 ? (($product['selling_price'] - $product['cost_price']) / $product['selling_price']) * 100 : 0;
                    
                    // Stock status
                    if ($product['stock_quantity'] <= 0) {
                        $stockClass = 'bg-red-100 text-red-800';
                        $stockLabel = 'Out of Stock';
                    } elseif ($product['stock_quantity'] <= $product['reorder_level']) {
                        $stockClass = 'bg-orange-100 text-orange-800';
                        $stockLabel = 'Low Stock';
                    } else {
                        $stockClass = 'bg-green-100 text-green-800';
                        $stockLabel = 'In Stock';
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary bg-opacity-10 rounded-lg flex items-center justify-center">
                                <i class="fas fa-wine-bottle text-primary"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                                <?php if ($product['description']): ?>
                                <p class="text-xs text-gray-500 line-clamp-1"><?php echo htmlspecialchars($product['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <span class="px-2 py-1 bg-gray-100 rounded text-xs"><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php if ($product['sku']): ?>
                        <div class="font-mono text-xs"><?php echo htmlspecialchars($product['sku']); ?></div>
                        <?php endif; ?>
                        <?php if ($product['barcode']): ?>
                        <div class="font-mono text-xs text-gray-500"><?php echo htmlspecialchars($product['barcode']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?php echo formatCurrency($product['cost_price']); ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-semibold text-gray-900"><?php echo formatCurrency($product['selling_price']); ?></div>
                        <div class="text-xs text-green-600">+<?php echo number_format($profit_margin, 1); ?>% margin</div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 text-xs font-medium rounded-full <?php echo $stockClass; ?>">
                            <?php echo $product['stock_quantity']; ?> <?php echo htmlspecialchars($product['unit'] ?: 'units'); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                        <?php echo formatCurrency($stock_value); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <div class="flex items-center gap-2">
                            <button onclick="editProduct(<?php echo $product['id']; ?>)" 
                                    class="text-blue-600 hover:text-blue-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" 
                                    class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($products->num_rows === 0): ?>
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-inbox text-5xl mb-4"></i>
                        <p>No products found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Product Modal -->
<div id="productModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b p-6 flex items-center justify-between z-10">
            <h3 class="text-xl font-bold" id="modalTitle">Add Product</h3>
            <button onclick="closeProductModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="productForm" class="p-6">
            <input type="hidden" id="productId" name="id">
            <input type="hidden" id="productAction" name="action" value="add">

            <!-- Basic Information -->
            <div class="mb-6">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-primary"></i>
                    Basic Information
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                        <input type="text" name="name" id="productName" required 
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                        <select name="category_id" id="productCategory" required 
                                class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select Category</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit *</label>
                        <select name="unit" id="productUnit" required 
                                class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="bottle">Bottle</option>
                            <option value="case">Case</option>
                            <option value="pack">Pack</option>
                            <option value="liter">Liter</option>
                            <option value="ml">ML</option>
                            <option value="piece">Piece</option>
                            <option value="box">Box</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">SKU</label>
                        <input type="text" name="sku" id="productSKU" 
                               placeholder="e.g., WNE-001" 
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Barcode</label>
                        <input type="text" name="barcode" id="productBarcode" 
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="productDescription" rows="2" 
                                  class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                </div>
            </div>

            <!-- Pricing & Stock -->
            <div class="mb-6">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-dollar-sign text-primary"></i>
                    Pricing & Stock
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cost Price (KSh) *</label>
                        <input type="number" step="0.01" name="cost_price" id="productCost" required 
                               onchange="calculateMargin()"
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Selling Price (KSh) *</label>
                        <input type="number" step="0.01" name="selling_price" id="productPrice" required 
                               onchange="calculateMargin()"
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Profit Margin</label>
                        <input type="text" id="profitMargin" readonly 
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg bg-gray-50 font-semibold text-green-600">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Stock Quantity *</label>
                        <input type="number" name="stock_quantity" id="productStock" required value="0"
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reorder Level *</label>
                        <input type="number" name="reorder_level" id="productReorder" required value="10"
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                        <p class="text-xs text-gray-500 mt-1">Alert when stock reaches this level</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Stock Value</label>
                        <input type="text" id="stockValue" readonly 
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg bg-gray-50 font-semibold text-primary">
                    </div>
                </div>
            </div>

            <!-- Additional Details -->
            <div class="mb-6">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-clipboard-list text-primary"></i>
                    Additional Details
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Supplier</label>
                        <input type="text" name="supplier" id="productSupplier" 
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Storage Location</label>
                        <input type="text" name="location" id="productLocation" 
                               placeholder="e.g., Shelf A3, Warehouse B"
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Expiry Date (Optional)</label>
                        <input type="date" name="expiry_date" id="productExpiry" 
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-6 border-t">
                <button type="button" onclick="closeProductModal()" 
                        class="flex-1 px-6 py-3 border-2 border-gray-300 rounded-lg hover:bg-gray-50 font-medium">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 px-6 py-3 rounded-lg font-semibold text-white transition hover:opacity-90"
                        style="background-color: <?php echo $settings['primary_color']; ?>">
                    <i class="fas fa-save mr-2"></i>Save Product
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Calculate margin and stock value
function calculateMargin() {
    const cost = parseFloat(document.getElementById('productCost').value) || 0;
    const price = parseFloat(document.getElementById('productPrice').value) || 0;
    const stock = parseFloat(document.getElementById('productStock').value) || 0;
    
    if (price > 0) {
        const margin = ((price - cost) / price) * 100;
        document.getElementById('profitMargin').value = margin.toFixed(2) + '%';
    }
    
    const stockValue = stock * price;
    document.getElementById('stockValue').value = 'KSh ' + stockValue.toFixed(2);
}

document.getElementById('productStock').addEventListener('input', calculateMargin);

// Product modal functions
function openProductModal() {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('productAction').value = 'add';
    document.getElementById('productForm').reset();
    document.getElementById('profitMargin').value = '';
    document.getElementById('stockValue').value = '';
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('productModal').classList.add('flex');
}

function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
    document.getElementById('productModal').classList.remove('flex');
}

async function editProduct(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('id', id);

        const response = await fetch('', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            const product = data.data;
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('productAction').value = 'edit';
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productCategory').value = product.category_id;
            document.getElementById('productUnit').value = product.unit || 'bottle';
            document.getElementById('productSKU').value = product.sku || '';
            document.getElementById('productBarcode').value = product.barcode || '';
            document.getElementById('productDescription').value = product.description || '';
            document.getElementById('productCost').value = product.cost_price;
            document.getElementById('productPrice').value = product.selling_price;
            document.getElementById('productStock').value = product.stock_quantity;
            document.getElementById('productReorder').value = product.reorder_level;
            document.getElementById('productSupplier').value = product.supplier || '';
            document.getElementById('productLocation').value = product.location || '';
            document.getElementById('productExpiry').value = product.expiry_date || '';
            
            calculateMargin();
            
            document.getElementById('productModal').classList.remove('hidden');
            document.getElementById('productModal').classList.add('flex');
        }
    } catch (error) {
        alert('Error loading product');
        console.error(error);
    }
}

async function deleteProduct(id, name) {
    if (!confirm(`Delete product "${name}"?`)) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        const response = await fetch('', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error deleting product');
        console.error(error);
    }
}

// Submit product form
document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

    try {
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error saving product');
        console.error(error);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Product';
    }
});
</script>

<?php include 'footer.php'; ?>