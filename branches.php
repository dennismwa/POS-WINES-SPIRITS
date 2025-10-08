<?php
require_once 'config.php';
requireOwner();

$page_title = 'Branch Management';
$settings = getSettings();

include 'header.php';
?>

<style>
.branch-card {
    background: white;
    border-radius: 1.25rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.branch-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%);
}

.branch-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
}

.branch-icon {
    width: 4.5rem;
    height: 4.5rem;
    border-radius: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

.stat-badge {
    padding: 0.625rem 1.25rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.stat-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.metric-card {
    background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,1) 100%);
    border-radius: 1rem;
    padding: 1.25rem;
    border: 2px solid #f3f4f6;
    transition: all 0.3s;
}

.metric-card:hover {
    border-color: <?php echo $settings['primary_color']; ?>;
    transform: translateY(-2px);
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.gradient-text {
    background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

@media (max-width: 768px) {
    .branch-card {
        padding: 1rem;
    }
    
    .branch-icon {
        width: 3.5rem;
        height: 3.5rem;
        font-size: 1.5rem;
    }
    
    .stat-badge {
        padding: 0.5rem 0.875rem;
        font-size: 0.75rem;
    }
}
</style>

<!-- Page Header -->
<div class="branch-card mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-store gradient-text mr-3"></i>
                Multi-Store Management
            </h1>
            <p class="text-sm md:text-base text-gray-600">Manage multiple locations and track performance</p>
        </div>
        
        <button onclick="openBranchModal()" 
                class="px-6 py-3 rounded-xl font-bold text-white transition hover:opacity-90 shadow-lg text-sm md:text-base"
                style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <i class="fas fa-plus mr-2"></i>Add New Branch
        </button>
    </div>
</div>

<!-- Loading State -->
<div id="loadingState" class="hidden">
    <div class="flex items-center justify-center py-20">
        <div class="text-center">
            <i class="fas fa-spinner fa-spin text-5xl md:text-6xl mb-4" style="color: <?php echo $settings['primary_color']; ?>"></i>
            <p class="text-lg md:text-xl text-gray-600 font-semibold">Loading branches...</p>
        </div>
    </div>
</div>

<!-- Branches Grid -->
<div id="branchesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
    <!-- Branches will be loaded here via JavaScript -->
</div>

<!-- Empty State -->
<div id="emptyState" class="hidden branch-card text-center py-12 md:py-20">
    <i class="fas fa-store text-6xl md:text-7xl text-gray-300 mb-4"></i>
    <h3 class="text-xl md:text-2xl font-bold text-gray-600 mb-3">No Branches Yet</h3>
    <p class="text-sm md:text-base text-gray-500 mb-6">Start by adding your first branch location</p>
    <button onclick="openBranchModal()" 
            class="px-8 py-4 rounded-xl font-bold text-white transition hover:opacity-90 shadow-lg"
            style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
        <i class="fas fa-plus mr-2"></i>Create First Branch
    </button>
</div>

<!-- Branch Modal -->
<div id="branchModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" style="backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="sticky top-0 p-4 md:p-6 rounded-t-2xl text-white z-10" style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl md:text-2xl font-bold" id="branchModalTitle">Add Branch</h3>
                    <p class="text-white/80 text-xs md:text-sm">Create a new store location</p>
                </div>
                <button onclick="closeBranchModal()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-xl md:text-2xl"></i>
                </button>
            </div>
        </div>
        
        <form id="branchForm" class="p-4 md:p-6">
            <input type="hidden" id="branchId" name="id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-xs md:text-sm font-bold text-gray-700 mb-2">Branch Name *</label>
                    <input type="text" id="branchName" name="name" required 
                           class="w-full px-3 md:px-4 py-2 md:py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-sm md:text-base"
                           placeholder="Downtown Branch">
                </div>
                
                <div>
                    <label class="block text-xs md:text-sm font-bold text-gray-700 mb-2">Branch Code *</label>
                    <input type="text" id="branchCode" name="code" required maxlength="20"
                           class="w-full px-3 md:px-4 py-2 md:py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-sm md:text-base font-mono uppercase"
                           placeholder="DWN">
                </div>
                
                <div>
                    <label class="block text-xs md:text-sm font-bold text-gray-700 mb-2">Manager</label>
                    <select id="branchManager" name="manager_id" 
                            class="w-full px-3 md:px-4 py-2 md:py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-sm md:text-base">
                        <option value="">No Manager</option>
                        <!-- Will be populated via JavaScript -->
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-xs md:text-sm font-bold text-gray-700 mb-2">Address</label>
                    <textarea id="branchAddress" name="address" rows="2"
                              class="w-full px-3 md:px-4 py-2 md:py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-sm md:text-base"
                              placeholder="Street address"></textarea>
                </div>
                
                <div>
                    <label class="block text-xs md:text-sm font-bold text-gray-700 mb-2">City</label>
                    <input type="text" id="branchCity" name="city"
                           class="w-full px-3 md:px-4 py-2 md:py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-sm md:text-base"
                           placeholder="Nairobi">
                </div>
                
                <div>
                    <label class="block text-xs md:text-sm font-bold text-gray-700 mb-2">Phone</label>
                    <input type="tel" id="branchPhone" name="phone"
                           class="w-full px-3 md:px-4 py-2 md:py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-sm md:text-base"
                           placeholder="+254 700 000000">
                </div>
                
                <div>
                    <label class="block text-xs md:text-sm font-bold text-gray-700 mb-2">Email</label>
                    <input type="email" id="branchEmail" name="email"
                           class="w-full px-3 md:px-4 py-2 md:py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-sm md:text-base"
                           placeholder="branch@example.com">
                </div>
                
                <div>
                    <label class="block text-xs md:text-sm font-bold text-gray-700 mb-2">Status</label>
                    <select id="branchStatus" name="status"
                            class="w-full px-3 md:px-4 py-2 md:py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-sm md:text-base">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs md:text-sm font-bold text-gray-700 mb-2">Opening Time</label>
                    <input type="time" id="branchOpeningTime" name="opening_time"
                           class="w-full px-3 md:px-4 py-2 md:py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-sm md:text-base">
                </div>
                
                <div>
                    <label class="block text-xs md:text-sm font-bold text-gray-700 mb-2">Closing Time</label>
                    <input type="time" id="branchClosingTime" name="closing_time"
                           class="w-full px-3 md:px-4 py-2 md:py-3 border-2 border-gray-200 rounded-xl focus:outline-none text-sm md:text-base">
                </div>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeBranchModal()" 
                        class="flex-1 px-4 md:px-6 py-2 md:py-3 border-2 border-gray-300 rounded-xl font-bold text-gray-700 hover:bg-gray-50 transition text-sm md:text-base">
                    Cancel
                </button>
                <button type="submit" id="branchSubmitBtn"
                        class="flex-1 px-4 md:px-6 py-2 md:py-3 rounded-xl font-bold text-white transition hover:opacity-90 shadow-lg text-sm md:text-base"
                        style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                    <i class="fas fa-save mr-2"></i>Save Branch
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Branch Details Modal -->
<div id="branchDetailsModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" style="backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="sticky top-0 p-4 md:p-6 bg-white border-b flex items-center justify-between z-10 rounded-t-2xl">
            <h3 class="text-xl md:text-2xl font-bold text-gray-900" id="branchDetailsTitle">Branch Details</h3>
            <button onclick="closeBranchDetails()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl md:text-2xl"></i>
            </button>
        </div>
        
        <div id="branchDetailsContent" class="p-4 md:p-6">
            <!-- Content loaded via JavaScript -->
        </div>
    </div>
</div>

<script>
const primaryColor = '<?php echo $settings['primary_color']; ?>';
const currency = '<?php echo $settings['currency']; ?>';
let branches = [];
let users = [];

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadBranches();
    loadUsers();
});

// Load branches
async function loadBranches() {
    showLoading();
    
    try {
        const formData = new FormData();
        formData.append('action', 'get_branches');
        
        const response = await fetch('/api/branches.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            branches = data.data.branches;
            renderBranches();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading branches:', error);
        showToast('Failed to load branches', 'error');
    } finally {
        hideLoading();
    }
}

// Render branches
function renderBranches() {
    const grid = document.getElementById('branchesGrid');
    const emptyState = document.getElementById('emptyState');
    
    if (branches.length === 0) {
        grid.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
    }
    
    emptyState.classList.add('hidden');
    
    grid.innerHTML = branches.map(branch => `
        <div class="branch-card cursor-pointer" onclick="viewBranchDetails(${branch.id})">
            <div class="flex items-start gap-4 mb-4">
                <div class="branch-icon text-white">
                    <i class="fas fa-store"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-lg md:text-xl text-gray-900 mb-1 truncate">${escapeHtml(branch.name)}</h3>
                    <p class="text-xs md:text-sm text-gray-600 mb-2">${escapeHtml(branch.city || 'N/A')}</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="stat-badge ${branch.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            <span class="status-indicator ${branch.status === 'active' ? 'bg-green-500' : 'bg-red-500'}"></span>
                            ${branch.status.toUpperCase()}
                        </span>
                        <span class="stat-badge bg-blue-100 text-blue-800">
                            <i class="fas fa-code"></i>
                            ${escapeHtml(branch.code)}
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="metric-card text-center">
                    <p class="text-xs text-gray-600 mb-1">Today's Sales</p>
                    <p class="text-base md:text-lg font-bold" style="color: ${primaryColor}">${currency} ${parseFloat(branch.today_revenue).toLocaleString()}</p>
                    <p class="text-xs text-gray-500">${branch.today_sales} transactions</p>
                </div>
                <div class="metric-card text-center">
                    <p class="text-xs text-gray-600 mb-1">Total Stock</p>
                    <p class="text-base md:text-lg font-bold text-gray-900">${parseInt(branch.total_stock).toLocaleString()}</p>
                    <p class="text-xs text-gray-500">${branch.products_count} products</p>
                </div>
            </div>
            
            <div class="pt-3 border-t border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-users text-gray-400 text-sm"></i>
                    <span class="text-xs md:text-sm text-gray-600 font-medium">${branch.staff_count} staff members</span>
                </div>
                ${branch.manager_name ? `
                <div class="flex items-center gap-2">
                    <i class="fas fa-user-tie text-gray-400 text-sm"></i>
                    <span class="text-xs md:text-sm text-gray-600 font-medium">${escapeHtml(branch.manager_name)}</span>
                </div>
                ` : ''}
            </div>
            
            <div class="mt-4 flex gap-2">
                <button onclick="event.stopPropagation(); editBranch(${branch.id})" 
                        class="flex-1 px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold transition text-xs md:text-sm">
                    <i class="fas fa-edit mr-1"></i>Edit
                </button>
                <button onclick="event.stopPropagation(); viewBranchInventory(${branch.id})" 
                        class="flex-1 px-3 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg font-semibold transition text-xs md:text-sm">
                    <i class="fas fa-boxes mr-1"></i>Inventory
                </button>
                ${branch.code !== 'MAIN' ? `
                <button onclick="event.stopPropagation(); deleteBranch(${branch.id}, '${escapeHtml(branch.name)}')" 
                        class="px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-trash"></i>
                </button>
                ` : ''}
            </div>
        </div>
    `).join('');
}

// Load users for manager dropdown
async function loadUsers() {
    try {
        const response = await fetch('/api/users.php');
        const data = await response.json();
        
        if (data.success) {
            users = data.data.users;
            populateManagerDropdown();
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

function populateManagerDropdown() {
    const select = document.getElementById('branchManager');
    select.innerHTML = '<option value="">No Manager</option>' + 
        users.map(user => `<option value="${user.id}">${escapeHtml(user.name)}</option>`).join('');
}

// Branch Modal Functions
function openBranchModal() {
    document.getElementById('branchModal').classList.remove('hidden');
    document.getElementById('branchModal').classList.add('flex');
    document.getElementById('branchModalTitle').textContent = 'Add Branch';
    document.getElementById('branchForm').reset();
    document.getElementById('branchId').value = '';
}

function closeBranchModal() {
    document.getElementById('branchModal').classList.add('hidden');
    document.getElementById('branchModal').classList.remove('flex');
}

function editBranch(id) {
    const branch = branches.find(b => b.id == id);
    if (!branch) return;
    
    document.getElementById('branchModal').classList.remove('hidden');
    document.getElementById('branchModal').classList.add('flex');
    document.getElementById('branchModalTitle').textContent = 'Edit Branch';
    document.getElementById('branchId').value = branch.id;
    document.getElementById('branchName').value = branch.name;
    document.getElementById('branchCode').value = branch.code;
    document.getElementById('branchManager').value = branch.manager_id || '';
    document.getElementById('branchAddress').value = branch.address || '';
    document.getElementById('branchCity').value = branch.city || '';
    document.getElementById('branchPhone').value = branch.phone || '';
    document.getElementById('branchEmail').value = branch.email || '';
    document.getElementById('branchStatus').value = branch.status;
    document.getElementById('branchOpeningTime').value = branch.opening_time || '';
    document.getElementById('branchClosingTime').value = branch.closing_time || '';
}

// Save Branch
document.getElementById('branchForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('branchSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    const formData = new FormData(this);
    formData.append('action', 'save_branch');
    
    try {
        const response = await fetch('/api/branches.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            closeBranchModal();
            loadBranches();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Connection error. Please try again.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Branch';
    }
});

// Delete Branch
async function deleteBranch(id, name) {
    if (!confirm(`Delete branch "${name}"?\n\nThis action cannot be undone.`)) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_branch');
    formData.append('id', id);
    
    try {
        const response = await fetch('/api/branches.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            loadBranches();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Connection error', 'error');
    }
}

// View Branch Details
async function viewBranchDetails(id) {
    const branch = branches.find(b => b.id == id);
    if (!branch) return;
    
    document.getElementById('branchDetailsModal').classList.remove('hidden');
    document.getElementById('branchDetailsModal').classList.add('flex');
    document.getElementById('branchDetailsTitle').textContent = branch.name;
    
    const content = document.getElementById('branchDetailsContent');
    content.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="branch-card">
                <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle" style="color: ${primaryColor}"></i>
                    Branch Information
                </h4>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Code:</span>
                        <span class="font-bold">${escapeHtml(branch.code)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="stat-badge ${branch.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            ${branch.status.toUpperCase()}
                        </span>
                    </div>
                    ${branch.city ? `
                    <div class="flex justify-between">
                        <span class="text-gray-600">City:</span>
                        <span class="font-semibold">${escapeHtml(branch.city)}</span>
                    </div>
                    ` : ''}
                    ${branch.phone ? `
                    <div class="flex justify-between">
                        <span class="text-gray-600">Phone:</span>
                        <span class="font-semibold">${escapeHtml(branch.phone)}</span>
                    </div>
                    ` : ''}
                    ${branch.manager_name ? `
                    <div class="flex justify-between">
                        <span class="text-gray-600">Manager:</span>
                        <span class="font-semibold">${escapeHtml(branch.manager_name)}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
            
            <div class="branch-card">
                <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-chart-bar" style="color: ${primaryColor}"></i>
                    Quick Stats
                </h4>
                <div class="grid grid-cols-2 gap-3">
                    <div class="metric-card text-center">
                        <p class="text-xs text-gray-600 mb-1">Staff</p>
                        <p class="text-2xl font-bold text-purple-600">${branch.staff_count}</p>
                    </div>
                    <div class="metric-card text-center">
                        <p class="text-xs text-gray-600 mb-1">Products</p>
                        <p class="text-2xl font-bold text-blue-600">${branch.products_count}</p>
                    </div>
                    <div class="metric-card text-center">
                        <p class="text-xs text-gray-600 mb-1">Stock Units</p>
                        <p class="text-2xl font-bold text-green-600">${parseInt(branch.total_stock).toLocaleString()}</p>
                    </div>
                    <div class="metric-card text-center">
                        <p class="text-xs text-gray-600 mb-1">Today Sales</p>
                        <p class="text-2xl font-bold" style="color: ${primaryColor}">${branch.today_sales}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex gap-3">
            <button onclick="viewBranchInventory(${branch.id})" 
                    class="flex-1 px-6 py-3 rounded-xl font-bold text-white transition hover:opacity-90"
                    style="background: linear-gradient(135deg, ${primaryColor} 0%, ${primaryColor}dd 100%)">
                <i class="fas fa-boxes mr-2"></i>View Inventory
            </button>
            <button onclick="viewBranchSales(${branch.id})" 
                    class="flex-1 px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-bold transition">
                <i class="fas fa-chart-line mr-2"></i>View Sales
            </button>
        </div>
    `;
}

function closeBranchDetails() {
    document.getElementById('branchDetailsModal').classList.add('hidden');
    document.getElementById('branchDetailsModal').classList.remove('flex');
}

// View Branch Inventory
function viewBranchInventory(id) {
    window.location.href = `/branch-inventory.php?branch_id=${id}`;
}

// View Branch Sales
function viewBranchSales(id) {
    window.location.href = `/sales.php?branch_id=${id}`;
}

// Utility Functions
function showLoading() {
    document.getElementById('loadingState').classList.remove('hidden');
    document.getElementById('branchesGrid').classList.add('hidden');
}

function hideLoading() {
    document.getElementById('loadingState').classList.add('hidden');
    document.getElementById('branchesGrid').classList.remove('hidden');
}

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
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        info: 'info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-4 md:px-6 py-3 rounded-xl shadow-lg z-[200] text-sm md:text-base`;
    toast.style.animation = 'slideIn 0.3s ease-out';
    toast.innerHTML = `<i class="fas fa-${icons[type]} mr-2"></i>${message}`;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBranchModal();
        closeBranchDetails();
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
