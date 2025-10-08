<?php
// ========== PROFILE.PHP ==========
require_once 'config.php';
requireAuth();

$page_title = 'My Profile';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = sanitize($_POST['name']);
    $pin = sanitize($_POST['pin_code']);
    $userId = $_SESSION['user_id'];
    
    if (!isValidPIN($pin)) {
        setFlash('error', 'PIN must be exactly 4 digits');
    } else {
        // Check PIN uniqueness
        $checkPin = $conn->query("SELECT id FROM users WHERE pin_code = '$pin' AND id != $userId");
        if ($checkPin->num_rows > 0) {
            setFlash('error', 'PIN already exists');
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, pin_code=? WHERE id=?");
            $stmt->bind_param("ssi", $name, $pin, $userId);
            
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $name;
                logActivity('PROFILE_UPDATED', 'Profile updated');
                setFlash('success', 'Profile updated successfully');
            }
        }
    }
    
    header('Location: /profile.php');
    exit;
}

$user = getUserInfo();

// Get user's sales stats
$result = $conn->query("SELECT COUNT(*) as total_sales, COALESCE(SUM(total_amount), 0) as total_revenue 
                        FROM sales WHERE user_id = {$user['id']}");
$stats = $result->fetch_assoc();

include 'header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-8">
        <div class="text-center mb-8">
            <div class="w-24 h-24 rounded-full mx-auto mb-4 flex items-center justify-center text-white text-3xl font-bold"
                 style="background-color: <?php echo $settings['primary_color']; ?>">
                <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
            </div>
            <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></h2>
            <p class="text-gray-600 capitalize"><?php echo $user['role']; ?></p>
        </div>
        
        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="bg-gray-50 rounded-lg p-4 text-center">
                <p class="text-3xl font-bold" style="color: <?php echo $settings['primary_color']; ?>">
                    <?php echo $stats['total_sales']; ?>
                </p>
                <p class="text-sm text-gray-600">Total Sales</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($stats['total_revenue']); ?></p>
                <p class="text-sm text-gray-600">Total Revenue</p>
            </div>
        </div>
        
        <!-- Edit Form -->
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required 
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[<?php echo $settings['primary_color']; ?>] focus:outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">PIN Code (4 digits)</label>
                    <input type="text" name="pin_code" maxlength="4" pattern="\d{4}" required 
                           placeholder="Enter new PIN to change" 
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-[<?php echo $settings['primary_color']; ?>] focus:outline-none">
                </div>
                
                <button type="submit" 
                        class="w-full px-6 py-3 rounded-lg font-semibold text-white text-lg"
                        style="background-color: <?php echo $settings['primary_color']; ?>">
                    <i class="fas fa-save mr-2"></i>Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>