<?php
// ========== SETTINGS.PHP (Owner Only) ==========
require_once 'config.php';
requireOwner();

$page_title = 'System Settings';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $companyName = sanitize($_POST['company_name']);
    $currency = sanitize($_POST['currency']);
    $currencySymbol = sanitize($_POST['currency_symbol']);
    $taxRate = floatval($_POST['tax_rate']);
    $primaryColor = sanitize($_POST['primary_color']);
    $receiptFooter = sanitize($_POST['receipt_footer']);
    $barcodeScanner = isset($_POST['barcode_scanner']) ? 1 : 0;
    $lowStockAlert = isset($_POST['low_stock_alert']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE settings SET 
        company_name=?, currency=?, currency_symbol=?, tax_rate=?, 
        primary_color=?, receipt_footer=?, barcode_scanner_enabled=?, low_stock_alert_enabled=?
        WHERE id=1");
    $stmt->bind_param("sssdsrii", $companyName, $currency, $currencySymbol, $taxRate, 
                     $primaryColor, $receiptFooter, $barcodeScanner, $lowStockAlert);
    
    if ($stmt->execute()) {
        unset($_SESSION['settings']); // Clear cache
        logActivity('SETTINGS_UPDATED', 'System settings updated');
        setFlash('success', 'Settings updated successfully');
    } else {
        setFlash('error', 'Failed to update settings');
    }
    
    header('Location: /settings.php');
    exit;
}

$settings = getSettings();
include 'header.php';
?>

<div class="max-w-4xl">
    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm mb-6 p-2">
        <div class="flex gap-2">
            <button onclick="showTab('general')" id="tab-general" class="tab-btn flex-1 px-6 py-3 rounded-lg font-semibold transition">General</button>
            <button onclick="showTab('appearance')" id="tab-appearance" class="tab-btn flex-1 px-6 py-3 rounded-lg font-semibold transition">Appearance</button>
            <button onclick="showTab('modules')" id="tab-modules" class="tab-btn flex-1 px-6 py-3 rounded-lg font-semibold transition">Modules</button>
        </div>
    </div>
    
    <form method="POST">
        <input type="hidden" name="action" value="update_settings">
        
        <!-- General Tab -->
        <div id="content-general" class="tab-content bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-6">General Settings</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
                    <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" 
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-[<?php echo $settings['primary_color']; ?>] focus:outline-none">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                        <select name="currency" class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none">
                            <option value="KSh" <?php echo $settings['currency'] === 'KSh' ? 'selected' : ''; ?>>KSh</option>
                            <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>USD</option>
                            <option value="EUR" <?php echo $settings['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Currency Symbol</label>
                        <input type="text" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol']); ?>" 
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tax Rate (%)</label>
                    <input type="number" name="tax_rate" step="0.01" value="<?php echo $settings['tax_rate']; ?>" 
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none">
                </div>
            </div>
        </div>
        
        <!-- Appearance Tab -->
        <div id="content-appearance" class="tab-content hidden bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-6">Appearance Settings</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Primary Color</label>
                    <input type="color" name="primary_color" value="<?php echo $settings['primary_color']; ?>" 
                           class="h-12 w-32 border-2 border-gray-200 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Receipt Footer Text</label>
                    <textarea name="receipt_footer" rows="3" 
                              class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none"><?php echo htmlspecialchars($settings['receipt_footer'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Modules Tab -->
        <div id="content-modules" class="tab-content hidden bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-6">Module Settings</h3>
            <div class="space-y-4">
                <label class="flex items-center justify-between p-4 bg-gray-50 rounded-lg cursor-pointer">
                    <div>
                        <p class="font-semibold text-gray-800">Barcode Scanner</p>
                        <p class="text-sm text-gray-600">Enable barcode scanning in POS</p>
                    </div>
                    <input type="checkbox" name="barcode_scanner" value="1" 
                           <?php echo $settings['barcode_scanner_enabled'] ? 'checked' : ''; ?>
                           class="w-6 h-6 rounded">
                </label>
                
                <label class="flex items-center justify-between p-4 bg-gray-50 rounded-lg cursor-pointer">
                    <div>
                        <p class="font-semibold text-gray-800">Low Stock Alerts</p>
                        <p class="text-sm text-gray-600">Show alerts when products reach reorder level</p>
                    </div>
                    <input type="checkbox" name="low_stock_alert" value="1" 
                           <?php echo $settings['low_stock_alert_enabled'] ? 'checked' : ''; ?>
                           class="w-6 h-6 rounded">
                </label>
            </div>
        </div>
        
        <!-- Save Button -->
        <div class="mt-6">
            <button type="submit" 
                    class="w-full px-6 py-3 rounded-lg font-semibold text-white text-lg"
                    style="background-color: <?php echo $settings['primary_color']; ?>">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </div>
    </form>
</div>

<script>
function showTab(tab) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.style.backgroundColor = '';
        el.style.color = '';
    });
    
    // Show selected tab
    document.getElementById('content-' + tab).classList.remove('hidden');
    const btn = document.getElementById('tab-' + tab);
    btn.style.backgroundColor = '<?php echo $settings['primary_color']; ?>';
    btn.style.color = 'white';
}

// Show first tab by default
showTab('general');
</script>

<?php include 'footer.php'; ?>