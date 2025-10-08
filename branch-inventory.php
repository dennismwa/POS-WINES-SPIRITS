<?php
require_once 'config.php';
requireOwner();

$page_title = 'Branch Inventory';
$settings = getSettings();
$branchId = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;

// Get branch details
$branchQuery = $conn->query("SELECT * FROM branches WHERE id = $branchId");
if ($branchQuery->num_rows === 0) {
    die('Branch not found');
}
$branch = $branchQuery->fetch_assoc();

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

.inventory-card:hover {
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

@media (max-width: 768px) {
    .inventory-card {
        padding: 1rem;
    }
}
</style>

<!-- Header -->
<div class="inventory-card mb-6">
    <div class="flex items-center gap-4 mb-4">
        <a href="/branches.php" class="text-gray-600 hover:text-gray-900 transition">
            <i class="fas fa-arrow-left text-xl"></i>
        </a>
        <div class="flex-1">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1">
                <i class="fas fa-boxes gradient-text mr-2"></i>
                <?php echo htmlspecialchars($branch['name']); ?> - Inventory
            </h1>
            <p class="text-sm text-gray-600">Manage stock levels for this branch</p>
        </div>
        <button onclick="openTransferModal()" 
                class="px-4 md:px-6 py-2 md:py-3 rounded-xl font-bold text-white transition hover:opacity-90 shadow-lg text-sm md:text-base"
                style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <i class="fas fa-exchange-alt mr-2"></i>Transfer Stock
        </button>
    </div>
    
    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-2">
            <input type="text" id="searchProduct" placeholder="Search products..." 
                   class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none text-sm md:text-base">
        </div>
        <select id="stockFilter" onchange="loadInventory()" 
                class="px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none text-sm md:text-base">
            <option value="all">All Stock Levels</option>
            <option value="in_stock">In Stock</option>
            <option value="low">Low Stock</option>
            <option value="out">Out of Stock</option>
        </select>
        <button onclick="loadInventory()" 
                class="px-4 py-2 rounded-lg font-bold text-white transition hover:opacity-90 text-sm md:text-base"
                style="background-color: <?php echo $settings['primary_color']; ?>">
            <i class="fas fa-search mr-2"></i>Search
        </button>
    </div>
</div>

<!-- Inventory Table -->
<div class="inventory-card">
    <div id="inventoryTable" class="overflow-x-auto">
        <!-- Content loaded via JavaScript -->
    </div>
</div>

<!-- Transfer Modal -->
<div id="transferModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" style="backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl">
        <div class="p-4 md:p-6 rounded-t-2xl text-white" style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl md:text-2xl font-bold">Transfer Stock</h3>
                    <p class="text-white/80 text-xs md:text-sm">Move inventory between branches</p>
                </div>
                <button onclick="closeTransferModal()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-xl md:text-2xl"></i>
                </button>
            </div>
        </div>
        
        <form id="transferForm" class="p-4 md:p-6">
            <div class="space-y-4">
                <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                    <p class="text-sm font-semibold text-blue-900 mb-1">
                        <i class="fas fa-info-circle mr-2"></i>Source Branch
                    </p>
                    <p class="text-lg font-bold text-blue-700"><?php echo htmlspecialchars($branch['name']); ?></p>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Destination Branch *</label>
                    <select id="toBranch" name="to_branch_id" required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base">
                        <option value="">Select destination...</option>
                        <!-- Populated via JavaScript -->
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Product *</label>
                    <select id="transferProduct" name="product_id" required onchange="updateAvailableStock()"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base">
                        <option value="">Select product...</option>
                        <!-- Populated via JavaScript -->
                    </select>
                    <p id="availableStock" class="text-sm text-gray-600 mt-2 hidden">
                        <i class="fas fa-box mr-1"></i>Available: <span class="font-bold">0</span> units
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Quantity *</label>
                    <input type="number" id="transferQuantity" name="quantity" required min="1"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-lg font-bold"
                           placeholder="0">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Notes</label>
                    <textarea id="transferNotes" name="notes" rows="3"
                              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-base"
                              placeholder="Optional transfer notes..."></textarea>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeTransferModal()" 
                        class="flex-1 px-6 py-3 border-2 border-gray-300 rounded-xl font-bold text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="transferSubmitBtn"
                        class="flex-1 px-6 py-3 rounded-xl font-bold text-white transition hover:opacity-90 shadow-lg"
                        style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                    <i class="fas fa-exchange-alt mr-2"></i>Transfer Stock
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const branchId = <?php echo $branchId; ?>;
const primaryColor = '<?php echo $settings['primary_color']; ?>';
const currency = '<?php echo $settings['currency']; ?>';
let inventory = [];
let branches = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadInventory();
    loadBranches();
    
    // Search with debounce
    let searchTimeout;
    document.getElementById('searchProduct').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadInventory(), 300);
    });
});

// Load inventory
async function loadInventory() {
    const search = document.getElementById('searchProduct').value;
    const stockFilter = document.getElementById('stockFilter').value;
    
    const params = new URLSearchParams({
        action: 'get_branch_inventory',
        branch_id: branchId,
        search: search,
        stock_filter: stockFilter
    });
    
    try {
        const response = await fetch(`/api/branches.php?${params}`, {
            method: 'POST',
            body: new FormData()
        });
        
        const data = await response.json();
        
        if (data.success) {
            inventory = data.data.inventory;
            renderInventory();
            populateProductDropdown();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading inventory:', error);
        showToast('Failed to load inventory', 'error');
    }
}

// Render inventory table
function renderInventory() {
    const table = document.getElementById('inventoryTable');
    
    if (inventory.length === 0) {
        table.innerHTML = `
            <div class="text-center py-20">
                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl text-gray-500 font-semibold mb-2">No products found</p>
                <p class="text-gray-400">Try adjusting your search or filters</p>
            </div>
        `;
        return;
    }
    
    // Desktop view
    const desktopTable = `
        <table class="w-full hidden md:table">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
                <tr>
                    <th class="text-left py-3 px-4 text-sm font-bold text-gray-700">Product</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Stock</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Reorder Level</th>
                    <th class="text-right py-3 px-4 text-sm font-bold text-gray-700">Value</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Status</th>
                    <th class="text-center py-3 px-4 text-sm font-bold text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody>
                ${inventory.map(item => {
                    const stockValue = item.stock_quantity * item.selling_price;
                    const stockStatus = getStockStatus(item.stock_quantity, item.reorder_level);
                    
                    return `
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                            <td class="py-4 px-4">
                                <div class="font-bold text-gray-900">${escapeHtml(item.name)}</div>
                                ${item.sku ? `<div class="text-xs text-gray-500 font-mono">${escapeHtml(item.sku)}</div>` : ''}
                                <div class="text-xs text-gray-500">${escapeHtml(item.category_name || 'N/A')}</div>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <span class="text-2xl font-bold text-gray-900">${item.stock_quantity}</span>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <span class="text-sm text-gray-600">${item.reorder_level}</span>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <span class="font-bold text-lg" style="color: ${primaryColor}">${currency} ${stockValue.toLocaleString()}</span>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <span class="px-3 py-1 rounded-full text-xs font-bold ${stockStatus.class}">
                                    ${stockStatus.label}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <button onclick="openTransferModalForProduct(${item.product_id})" 
                                        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold transition text-sm">
                                    <i class="fas fa-exchange-alt mr-1"></i>Transfer
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
    `;
    
    // Mobile view
    const mobileCards = `
        <div class="grid gap-4 md:hidden">
            ${inventory.map(item => {
                const stockValue = item.stock_quantity * item.selling_price;
                const stockStatus = getStockStatus(item.stock_quantity, item.reorder_level);
                
                return `
                    <div class="border-2 border-gray-200 rounded-xl p-4 hover:border-${primaryColor} transition">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-900 mb-1">${escapeHtml(item.name)}</h4>
                                ${item.sku ? `<p class="text-xs text-gray-500 font-mono">${escapeHtml(item.sku)}</p>` : ''}
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold ${stockStatus.class}">
                                ${stockStatus.label}
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="text-center p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-600 mb-1">Stock</p>
                                <p class="text-2xl font-bold text-gray-900">${item.stock_quantity}</p>
                            </div>
                            <div class="text-center p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-600 mb-1">Value</p>
                                <p class="text-sm font-bold" style="color: ${primaryColor}">${currency} ${stockValue.toLocaleString()}</p>
                            </div>
                        </div>
                        
                        <button onclick="openTransferModalForProduct(${item.product_id})" 
                                class="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold transition">
                            <i class="fas fa-exchange-alt mr-2"></i>Transfer Stock
                        </button>
                    </div>
                `;
            }).join('')}
        </div>
    `;
    
    table.innerHTML = desktopTable + mobileCards;
}

function getStockStatus(stock, reorder) {
    if (stock === 0) {
        return { label: 'Out of Stock', class: 'bg-red-100 text-red-800' };
    } else if (stock <= reorder) {
        return { label: 'Low Stock', class: 'bg-orange-100 text-orange-800' };
    } else {
        return { label: 'In Stock', class: 'bg-green-100 text-green-800' };
    }
}

// Load branches for transfer
async function loadBranches() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_branches');
        
        const response = await fetch('/api/branches.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            branches = data.data.branches.filter(b => b.id != branchId && b.status === 'active');
            populateBranchDropdown();
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

function populateBranchDropdown() {
    const select = document.getElementById('toBranch');
    select.innerHTML = '<option value="">Select destination...</option>' +
        branches.map(b => `<option value="${b.id}">${escapeHtml(b.name)} (${escapeHtml(b.code)})</option>`).join('');
}

function populateProductDropdown() {
    const select = document.getElementById('transferProduct');
    select.innerHTML = '<option value="">Select product...</option>' +
        inventory.filter(item => item.stock_quantity > 0).map(item => 
            `<option value="${item.product_id}" data-stock="${item.stock_quantity}">
                ${escapeHtml(item.name)} (Available: ${item.stock_quantity})
            </option>`
        ).join('');
}

// Transfer Modal Functions
function openTransferModal() {
    document.getElementById('transferModal').classList.remove('hidden');
    document.getElementById('transferModal').classList.add('flex');
    document.getElementById('transferForm').reset();
    document.getElementById('availableStock').classList.add('hidden');
}

function openTransferModalForProduct(productId) {
    openTransferModal();
    document.getElementById('transferProduct').value = productId;
    updateAvailableStock();
}

function closeTransferModal() {
    document.getElementById('transferModal').classList.add('hidden');
    document.getElementById('transferModal').classList.remove('flex');
}

function updateAvailableStock() {
    const select = document.getElementById('transferProduct');
    const stockDiv = document.getElementById('availableStock');
    const quantityInput = document.getElementById('transferQuantity');
    
    const selectedOption = select.options[select.selectedIndex];
    const stock = selectedOption.getAttribute('data-stock');
    
    if (stock) {
        stockDiv.classList.remove('hidden');
        stockDiv.querySelector('span').textContent = stock;
        quantityInput.max = stock;
    } else {
        stockDiv.classList.add('hidden');
        quantityInput.max = '';
    }
}

// Submit Transfer
document.getElementById('transferForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('transferSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    const formData = new FormData(this);
    formData.append('action', 'initiate_transfer');
    formData.append('from_branch_id', branchId);
    
    try {
        const response = await fetch('/api/branches.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Transfer initiated successfully', 'success');
            closeTransferModal();
            loadInventory();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Connection error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-exchange-alt mr-2"></i>Transfer Stock';
    }
});

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type) {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-xl shadow-lg z-[200]`;
    toast.style.animation = 'slideIn 0.3s ease-out';
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>${message}`;
    
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTransferModal();
    }
});
</script>

<style>
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.gradient-text {
    background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
</style>

<?php include 'footer.php'; ?>
