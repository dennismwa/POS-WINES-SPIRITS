</main>
        
        <!-- Compact Footer -->
        <footer class="bg-white border-t mt-8 no-print">
            <div class="px-4 md:px-6 py-4">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <!-- Left: Copyright -->
                    <div class="text-center md:text-left">
                        <p class="text-sm text-gray-600">
                            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['company_name']); ?>. All rights reserved.
                        </p>
                    </div>
                    
                    <!-- Center: Quick Stats (Owner Only) -->
                    <?php if ($_SESSION['role'] === 'owner'): ?>
                    <div class="flex gap-6 text-sm">
                        <div class="text-center">
                            <i class="fas fa-wine-bottle text-primary mr-1"></i>
                            <span class="font-semibold"><?php echo $conn->query("SELECT COUNT(*) as count FROM products WHERE status='active'")->fetch_assoc()['count']; ?></span>
                            <span class="text-gray-500 ml-1">Products</span>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-receipt text-primary mr-1"></i>
                            <span class="font-semibold"><?php echo $conn->query("SELECT COUNT(*) as count FROM sales")->fetch_assoc()['count']; ?></span>
                            <span class="text-gray-500 ml-1">Sales</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Right: System Info -->
                    <div class="text-center md:text-right">
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-code mr-1"></i>v1.0.0
                            <span class="mx-2">|</span>
                            <i class="fas fa-clock mr-1"></i><?php echo date('H:i:s'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav bg-white border-t shadow-2xl md:hidden no-print">
        <div class="flex justify-around items-center py-2">
            <?php if ($_SESSION['role'] === 'owner'): ?>
            <a href="/dashboard.php" class="flex flex-col items-center py-2 px-3 transition <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'text-primary' : 'text-gray-600'; ?>">
                <i class="fas fa-chart-line text-xl mb-1"></i>
                <span class="text-xs font-medium">Dashboard</span>
            </a>
            <?php endif; ?>
            
            <a href="/pos.php" class="flex flex-col items-center py-2 px-3 transition <?php echo basename($_SERVER['PHP_SELF']) === 'pos.php' ? 'text-primary' : 'text-gray-600'; ?>">
                <div class="relative">
                    <i class="fas fa-cash-register text-xl mb-1"></i>
                </div>
                <span class="text-xs font-medium">POS</span>
            </a>
            
            <a href="/products.php" class="flex flex-col items-center py-2 px-3 transition <?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'text-primary' : 'text-gray-600'; ?>">
                <i class="fas fa-wine-bottle text-xl mb-1"></i>
                <span class="text-xs font-medium">Products</span>
            </a>
            
            <a href="/sales.php" class="flex flex-col items-center py-2 px-3 transition <?php echo basename($_SERVER['PHP_SELF']) === 'sales.php' ? 'text-primary' : 'text-gray-600'; ?>">
                <i class="fas fa-receipt text-xl mb-1"></i>
                <span class="text-xs font-medium">Sales</span>
            </a>
            
            <?php if ($_SESSION['role'] === 'owner'): ?>
            <a href="/settings.php" class="flex flex-col items-center py-2 px-3 transition <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'text-primary' : 'text-gray-600'; ?>">
                <i class="fas fa-cog text-xl mb-1"></i>
                <span class="text-xs font-medium">Settings</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>
</body>
</html>