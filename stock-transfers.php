<?php
require_once 'config.php';
requireOwner();

$page_title = 'Stock Transfers';
$settings = getSettings();

include 'header.php';
?>

<style>
.transfer-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.transfer-card:hover {
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .transfer-card {
        padding: 1rem;
    }
}
</style>

<!-- Header -->
<div class="transfer-card mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-exchange-alt gradient-text mr-3"></i>
                Stock Transfer History
            </h1>
            <p class="text-sm md:text-base text-gray-600">Track inventory movements between branches</p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="transfer-card mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <select id="statusFilter" onchange="loadTransfers()" 
                class="px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="in_transit">In Transit</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
        
        <select id="branchFilter" onchange="loadTransfers()" 
                class="px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none">
            <option value="">All Branches</option>
            <!-- Populated via JavaScript -->
        </select>
        
        <button onclick="loadTransfers()" 
                class="px-6 py-2 rounded-lg font-bold text-white transition hover:opacity-90"
                style="background-color: <?php echo $settings['primary_color']; ?>">
            <i class="fas fa-sync-alt mr-2"></i>Refresh
        </button>
    </div>
</div>

<!-- Transfers List -->
<div id="transfersList" class="space-y-4">
    <!-- Content loaded via JavaScript -->
</div>

<script>
const primaryColor = '<?php echo $settings['primary_color']; ?>';
let transfers = [];
let branches = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadBranches();
    loadTransfers();
});

// Load branches for filter
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
            branches = data.data.branches;
            const select = document.getElementById('branchFilter');
            select.innerHTML = '<option value="">All Branches</option>' +
                branches.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

// Load transfers
async function loadTransfers() {
    const status = document.getElementById('statusFilter').value;
    const branchId = document.getElementById('branchFilter').value;
    
    const params = new URLSearchParams({
        action: 'get_transfers',
        status: status,
        branch_id: branchId
    });
    
    try {
        const response = await fetch(`/api/branches.php?${params}`, {
            method: 'POST',
            body: new FormData()
        });
        
        const data = await response.json();
        
        if (data.success) {
            transfers = data.data.transfers;
            renderTransfers();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading transfers:', error);
        showToast('Failed to load transfers', 'error');
    }
}

// Render transfers
function renderTransfers() {
    const container = document.getElementById('transfersList');
    
    if (transfers.length === 0) {
        container.innerHTML = `
            <div class="transfer-card text-center py-20">
                <i class="fas fa-exchange-alt text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl text-gray-500 font-semibold mb-2">No transfers found</p>
                <p class="text-gray-400">Stock transfers between branches will appear here</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = transfers.map(transfer => {
        const statusConfig = getStatusConfig(transfer.status);
        
        return `
            <div class="transfer-card">
                <div class="flex flex-col md:flex-row gap-4">
                    <!-- Transfer Icon & Number -->
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-xl flex items-center justify-center text-white text-2xl"
                             style="background: linear-gradient(135deg, ${primaryColor} 0%, ${primaryColor}dd 100%)">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div>
                            <p class="font-bold text-lg text-gray-900">${transfer.transfer_number}</p>
                            <p class="text-sm text-gray-600">${new Date(transfer.created_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                    
                    <!-- Transfer Details -->
                    <div class="flex-1">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-600 mb-1">From</p>
                                <p class="font-semibold text-gray-900">${escapeHtml(transfer.from_branch_name)}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 mb-1">To</p>
                                <p class="font-semibold text-gray-900">${escapeHtml(transfer.to_branch_name)}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Product</p>
                                <p class="font-semibold text-gray-900">${escapeHtml(transfer.product_name)}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Quantity</p>
                                <p class="font-bold text-2xl" style="color: ${primaryColor}">${transfer.quantity}</p>
                            </div>
                        </div>
                        
                        ${transfer.notes ? `
                        <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-600 mb-1">Notes</p>
                            <p class="text-sm text-gray-700">${escapeHtml(transfer.notes)}</p>
                        </div>
                        ` : ''}
                    </div>
                    
                    <!-- Status & Actions -->
                    <div class="flex flex-col items-end justify-between gap-4">
                        <span class="status-badge ${statusConfig.class}">
                            <i class="fas fa-${statusConfig.icon}"></i>
                            ${statusConfig.label}
                        </span>
                        
                        ${transfer.status === 'pending' ? `
                        <div class="flex gap-2">
                            <button onclick="completeTransfer(${transfer.id})" 
                                    class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold transition text-sm">
                                <i class="fas fa-check mr-1"></i>Complete
                            </button>
                            <button onclick="cancelTransfer(${transfer.id})" 
                                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold transition text-sm">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        ` : ''}
                        
                        <div class="text-xs text-gray-500">
                            <p>Initiated by: ${escapeHtml(transfer.initiated_by_name)}</p>
                            ${transfer.received_by_name ? `<p>Received by: ${escapeHtml(transfer.received_by_name)}</p>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function getStatusConfig(status) {
    const configs = {
        pending: { label: 'Pending', class: 'bg-yellow-100 text-yellow-800', icon: 'clock' },
        in_transit: { label: 'In Transit', class: 'bg-blue-100 text-blue-800', icon: 'truck' },
        completed: { label: 'Completed', class: 'bg-green-100 text-green-800', icon: 'check-circle' },
        cancelled: { label: 'Cancelled', class: 'bg-red-100 text-red-800', icon: 'times-circle' }
    };
    return configs[status] || configs.pending;
}

// Complete Transfer
async function completeTransfer(id) {
    if (!confirm('Complete this transfer?\n\nStock will be added to the destination branch.')) return;
    
    const formData = new FormData();
    formData.append('action', 'complete_transfer');
    formData.append('transfer_id', id);
    
    try {
        const response = await fetch('/api/branches.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            loadTransfers();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Connection error', 'error');
    }
}

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
