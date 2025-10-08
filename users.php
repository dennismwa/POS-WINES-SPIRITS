<?php
require_once 'config.php';
requireOwner();

$page_title = 'User Management';
$settings = getSettings();

// Handle AJAX user operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'save_user') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = sanitize($_POST['name']);
        $pin = sanitize($_POST['pin_code']);
        $role = sanitize($_POST['role']);
        $status = sanitize($_POST['status']);
        $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
        
        if (!isValidPIN($pin)) {
            respond(false, 'PIN must be exactly 4 digits');
        }
        
        // Check PIN uniqueness
        $checkPin = $conn->query("SELECT id FROM users WHERE pin_code = '$pin' AND id != $id");
        if ($checkPin->num_rows > 0) {
            respond(false, 'PIN already in use');
        }
        
        if ($id > 0) {
            // Update existing user
            $stmt = $conn->prepare("UPDATE users SET name=?, pin_code=?, role=?, status=?, email=?, phone=? WHERE id=?");
            $stmt->bind_param("ssssssi", $name, $pin, $role, $status, $email, $phone, $id);
            
            if ($stmt->execute()) {
                logActivity('USER_UPDATED', "Updated user: $name (ID: $id)");
                respond(true, 'User updated successfully');
            } else {
                respond(false, 'Failed to update user');
            }
        } else {
            // Create new user
            $permissions = json_encode($role === 'owner' ? ['all'] : ['pos', 'view_products', 'view_own_sales']);
            $stmt = $conn->prepare("INSERT INTO users (name, pin_code, role, status, email, phone, permissions) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $name, $pin, $role, $status, $email, $phone, $permissions);
            
            if ($stmt->execute()) {
                logActivity('USER_CREATED', "Created user: $name");
                respond(true, 'User created successfully');
            } else {
                respond(false, 'Failed to create user');
            }
        }
        $stmt->close();
    }
    
    if ($action === 'delete_user') {
        $id = intval($_POST['id']);
        
        if ($id == $_SESSION['user_id']) {
            respond(false, 'Cannot delete yourself');
        }
        
        $stmt = $conn->prepare("UPDATE users SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            logActivity('USER_DELETED', "Deactivated user ID: $id");
            respond(true, 'User deactivated successfully');
        } else {
            respond(false, 'Failed to deactivate user');
        }
        $stmt->close();
    }
    
    if ($action === 'toggle_status') {
        $id = intval($_POST['id']);
        $newStatus = sanitize($_POST['new_status']);
        
        if ($id == $_SESSION['user_id']) {
            respond(false, 'Cannot change your own status');
        }
        
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->bind_param("si", $newStatus, $id);
        
        if ($stmt->execute()) {
            logActivity('USER_STATUS_CHANGED', "Changed user ID $id status to: $newStatus");
            respond(true, 'User status updated');
        } else {
            respond(false, 'Failed to update status');
        }
        $stmt->close();
    }
    
    exit;
}

// Get users with statistics
$users = $conn->query("SELECT u.*, 
                       COUNT(DISTINCT s.id) as sales_count,
                       COALESCE(SUM(s.total_amount), 0) as total_sales,
                       MAX(s.sale_date) as last_sale
                       FROM users u 
                       LEFT JOIN sales s ON u.id = s.user_id
                       GROUP BY u.id
                       ORDER BY u.created_at DESC");

// Get overall stats
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$activeUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE status='active'")->fetch_assoc()['count'];
$totalOwners = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='owner' AND status='active'")->fetch_assoc()['count'];
$totalSellers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='seller' AND status='active'")->fetch_assoc()['count'];

include 'header.php';
?>

<style>
.user-card {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.user-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.15);
}

.user-avatar {
    width: 4rem;
    height: 4rem;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
}

@media (max-width: 768px) {
    .user-card {
        padding: 1rem;
    }
    
    .user-avatar {
        width: 3rem;
        height: 3rem;
        font-size: 1.25rem;
    }
}
</style>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
    <div class="user-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Owners</p>
                <h3 class="text-3xl md:text-4xl font-bold text-purple-600"><?php echo $totalOwners; ?></h3>
                <p class="text-xs text-gray-500 mt-1">full access</p>
            </div>
            <div class="w-12 h-12 md:w-14 md:h-14 bg-purple-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-crown text-purple-600 text-xl md:text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="user-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1 font-medium">Sellers</p>
                <h3 class="text-3xl md:text-4xl font-bold" style="color: <?php echo $settings['primary_color']; ?>"><?php echo $totalSellers; ?></h3>
                <p class="text-xs text-gray-500 mt-1">limited access</p>
            </div>
            <div class="w-12 h-12 md:w-14 md:h-14 rounded-xl flex items-center justify-center" style="background-color: <?php echo $settings['primary_color']; ?>20;">
                <i class="fas fa-user-tie text-xl md:text-2xl" style="color: <?php echo $settings['primary_color']; ?>"></i>
            </div>
        </div>
    </div>
</div>

<!-- Add User Button -->
<div class="user-card mb-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Team Members</h2>
            <p class="text-sm text-gray-600">Manage user accounts and permissions</p>
        </div>
        <button onclick="openUserModal()" 
                class="px-6 py-3 rounded-lg font-bold text-white transition hover:opacity-90 shadow-lg"
                style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <i class="fas fa-plus mr-2"></i>Add New User
        </button>
    </div>
</div>

<!-- Users Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
    <?php while ($user = $users->fetch_assoc()): 
        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
        $colorIndex = $user['id'] % count($colors);
        $bgColor = $colors[$colorIndex];
    ?>
    <div class="user-card">
        <div class="flex items-start gap-4 mb-4">
            <div class="user-avatar" style="background: linear-gradient(135deg, <?php echo $bgColor; ?> 0%, <?php echo $bgColor; ?>dd 100%)">
                <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-lg text-gray-900 truncate"><?php echo htmlspecialchars($user['name']); ?></h3>
                <div class="flex items-center gap-2 mt-1">
                    <span class="px-2 py-1 text-xs font-bold rounded <?php echo $user['role'] === 'owner' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                        <i class="fas fa-<?php echo $user['role'] === 'owner' ? 'crown' : 'user-tie'; ?> mr-1"></i>
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    <span class="px-2 py-1 text-xs font-bold rounded <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- User Info -->
        <div class="space-y-2 mb-4 p-3 bg-gray-50 rounded-lg">
            <?php if ($user['email']): ?>
            <div class="flex items-center gap-2 text-sm">
                <i class="fas fa-envelope text-gray-400 w-4"></i>
                <span class="text-gray-700 truncate"><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($user['phone']): ?>
            <div class="flex items-center gap-2 text-sm">
                <i class="fas fa-phone text-gray-400 w-4"></i>
                <span class="text-gray-700"><?php echo htmlspecialchars($user['phone']); ?></span>
            </div>
            <?php endif; ?>
            <div class="flex items-center gap-2 text-sm">
                <i class="fas fa-key text-gray-400 w-4"></i>
                <span class="text-gray-700 font-mono">PIN: ••••</span>
                <button onclick="alert('PIN: <?php echo $user['pin_code']; ?>')" class="text-xs text-blue-600 hover:text-blue-800">
                    Show
                </button>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <p class="text-2xl font-bold" style="color: <?php echo $settings['primary_color']; ?>"><?php echo $user['sales_count']; ?></p>
                <p class="text-xs text-gray-600 font-medium">Sales</p>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <p class="text-lg font-bold text-green-600"><?php echo formatCurrency($user['total_sales']); ?></p>
                <p class="text-xs text-gray-600 font-medium">Revenue</p>
            </div>
        </div>
        
        <?php if ($user['last_sale']): ?>
        <p class="text-xs text-gray-500 mb-4 text-center">
            <i class="fas fa-clock mr-1"></i>Last sale: <?php echo date('M d, Y', strtotime($user['last_sale'])); ?>
        </p>
        <?php endif; ?>
        
        <!-- Actions -->
        <div class="flex gap-2">
            <button onclick='editUser(<?php echo json_encode($user); ?>)' 
                    class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold transition text-sm">
                <i class="fas fa-edit mr-1"></i>Edit
            </button>
            <?php if ($user['id'] != $_SESSION['user_id']): ?>
            <button onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>')" 
                    class="flex-1 px-4 py-2 <?php echo $user['status'] === 'active' ? 'bg-orange-500 hover:bg-orange-600' : 'bg-green-500 hover:bg-green-600'; ?> text-white rounded-lg font-semibold transition text-sm">
                <i class="fas fa-<?php echo $user['status'] === 'active' ? 'pause' : 'play'; ?> mr-1"></i>
                <?php echo $user['status'] === 'active' ? 'Disable' : 'Enable'; ?>
            </button>
            <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['name'])); ?>')" 
                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold transition">
                <i class="fas fa-trash"></i>
            </button>
            <?php else: ?>
            <div class="flex-1 px-4 py-2 bg-gray-200 text-gray-500 rounded-lg font-semibold text-center text-sm">
                <i class="fas fa-user-shield mr-1"></i>You
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<!-- User Modal -->
<div id="userModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" style="backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="sticky top-0 bg-white p-6 rounded-t-2xl text-white z-10" style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-2xl font-bold" id="modalTitle">Add User</h3>
                    <p class="text-white/80 text-sm">Create a new team member</p>
                </div>
                <button onclick="closeUserModal()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <form id="userForm" class="p-6">
            <input type="hidden" id="userId" name="id">
            <input type="hidden" name="action" value="save_user">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Full Name *</label>
                    <input type="text" name="name" id="userName" required 
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base"
                           placeholder="John Doe">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" id="userEmail" 
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base"
                           placeholder="john@example.com">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Phone</label>
                    <input type="tel" name="phone" id="userPhone" 
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base"
                           placeholder="+254 700 000000">
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">PIN Code (4 digits) *</label>
                    <input type="text" name="pin_code" id="userPin" required maxlength="4" pattern="\d{4}" 
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-lg font-mono font-bold"
                           placeholder="1234">
                    <p class="text-xs text-gray-500 mt-1">Used for login</p>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Role *</label>
                    <select name="role" id="userRole" required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base">
                        <option value="seller">Seller</option>
                        <option value="owner">Owner (Admin)</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Status</label>
                    <select name="status" id="userStatus" 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none text-base">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <!-- Role Permissions Info -->
            <div class="bg-gray-50 rounded-xl p-4 mb-6">
                <h4 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                    <i class="fas fa-shield-alt" style="color: <?php echo $settings['primary_color']; ?>"></i>
                    Role Permissions
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="font-bold text-purple-600 mb-2">
                            <i class="fas fa-crown mr-1"></i>Owner (Admin)
                        </p>
                        <ul class="space-y-1 text-gray-700">
                            <li><i class="fas fa-check text-green-600 mr-2"></i>Full system access</li>
                            <li><i class="fas fa-check text-green-600 mr-2"></i>View all sales & reports</li>
                            <li><i class="fas fa-check text-green-600 mr-2"></i>Manage users</li>
                            <li><i class="fas fa-check text-green-600 mr-2"></i>Manage products & inventory</li>
                            <li><i class="fas fa-check text-green-600 mr-2"></i>System settings</li>
                        </ul>
                    </div>
                    <div>
                        <p class="font-bold text-blue-600 mb-2">
                            <i class="fas fa-user-tie mr-1"></i>Seller
                        </p>
                        <ul class="space-y-1 text-gray-700">
                            <li><i class="fas fa-check text-green-600 mr-2"></i>Point of Sale (POS)</li>
                            <li><i class="fas fa-check text-green-600 mr-2"></i>View products</li>
                            <li><i class="fas fa-check text-green-600 mr-2"></i>View own sales only</li>
                            <li><i class="fas fa-times text-red-600 mr-2"></i>Cannot manage users</li>
                            <li><i class="fas fa-times text-red-600 mr-2"></i>Cannot change settings</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeUserModal()" 
                        class="flex-1 px-6 py-3 border-2 border-gray-300 rounded-lg font-bold text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="userSubmitBtn"
                        class="flex-1 px-6 py-3 rounded-lg font-bold text-white transition hover:opacity-90 shadow-lg"
                        style="background: linear-gradient(135deg, <?php echo $settings['primary_color']; ?> 0%, <?php echo $settings['primary_color']; ?>dd 100%)">
                    <i class="fas fa-save mr-2"></i>Save User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openUserModal() {
    document.getElementById('userModal').classList.remove('hidden');
    document.getElementById('userModal').classList.add('flex');
    document.getElementById('modalTitle').textContent = 'Add User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
}

function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
    document.getElementById('userModal').classList.remove('flex');
}

function editUser(user) {
    document.getElementById('userModal').classList.remove('hidden');
    document.getElementById('userModal').classList.add('flex');
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('userId').value = user.id;
    document.getElementById('userName').value = user.name;
    document.getElementById('userEmail').value = user.email || '';
    document.getElementById('userPhone').value = user.phone || '';
    document.getElementById('userPin').value = user.pin_code;
    document.getElementById('userRole').value = user.role;
    document.getElementById('userStatus').value = user.status;
}

function deleteUser(id, name) {
    if (!confirm(`Delete user "${name}"?\n\nThis will deactivate their account.`)) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('id', id);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => showToast('Connection error', 'error'));
}

function toggleUserStatus(id, newStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('id', id);
    formData.append('new_status', newStatus);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => showToast('Connection error', 'error'));
}

document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('userSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    const formData = new FormData(this);

    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save User';
        }
    })
    .catch(err => {
        showToast('Connection error', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save User';
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

// ESC to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUserModal();
    }
});
</script>

<style>
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<?php include 'footer.php'; ?> 