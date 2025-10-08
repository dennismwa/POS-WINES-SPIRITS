<?php
// ========== 403.PHP ==========
$page_title = '403 - Access Denied';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="text-center">
        <div class="inline-block mb-6">
            <i class="fas fa-ban text-8xl text-red-500"></i>
        </div>
        <h1 class="text-6xl font-bold text-gray-800 mb-4">403</h1>
        <h2 class="text-2xl font-semibold text-gray-700 mb-4">Access Denied</h2>
        <p class="text-gray-600 mb-8 max-w-md mx-auto">
            You don't have permission to access this page. Please contact your administrator if you believe this is an error.
        </p>
        <div class="flex gap-4 justify-center">
            <a href="javascript:history.back()" 
               class="px-6 py-3 bg-gray-200 hover:bg-gray-300 rounded-lg font-semibold text-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>Go Back
            </a>
            <a href="/dashboard.php" 
               class="px-6 py-3 bg-orange-600 hover:bg-orange-700 rounded-lg font-semibold text-white transition">
                <i class="fas fa-home mr-2"></i>Dashboard
            </a>
            <a href="/logout.php" 
               class="px-6 py-3 border-2 border-gray-300 hover:bg-gray-50 rounded-lg font-semibold text-gray-700 transition">
                <i class="fas fa-sign-out-alt mr-2"></i>Logout
            </a>
        </div>
    </div>
</body>
</html>
