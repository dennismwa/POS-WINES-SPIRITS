<?php
require_once 'config.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'owner') {
        header('Location: /dashboard.php');
    } else {
        header('Location: /pos.php');
    }
    exit;
}

$settings = getSettings();

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
    $pin = sanitize($_POST['pin']);
    
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE pin_code = ? AND status = 'active'");
    $stmt->bind_param("s", $pin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        
        // Create session record
        $session_token = bin2hex(random_bytes(32));
        $ip_address = getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $stmt2 = $conn->prepare("INSERT INTO sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("isss", $user['id'], $session_token, $ip_address, $user_agent);
        $stmt2->execute();
        $stmt2->close();
        
        logActivity('Login', 'User logged in successfully');
        
        respond(true, 'Login successful', [
            'redirect' => $user['role'] === 'owner' ? '/dashboard.php' : '/pos.php'
        ]);
    } else {
        respond(false, 'Invalid PIN code');
    }
    
    $stmt->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['company_name']); ?> - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: <?php echo $settings['primary_color']; ?>;
        }
        .btn-primary {
            background-color: var(--primary-color);
        }
        .btn-primary:hover {
            filter: brightness(0.9);
        }
        .pin-dot {
            transition: all 0.3s ease;
        }
        .pin-dot.filled {
            background-color: var(--primary-color);
            transform: scale(1.2);
        }
        .number-btn {
            transition: all 0.2s ease;
        }
        .number-btn:active {
            transform: scale(0.95);
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo and Title -->
            <div class="text-center mb-8">
                <div class="mb-4">
                    <img src="<?php echo htmlspecialchars($settings['logo_path']); ?>" 
                         alt="Logo" 
                         class="h-24 mx-auto rounded-lg shadow-lg"
                         onerror="this.style.display='none'">
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <?php echo htmlspecialchars($settings['company_name']); ?>
                </h1>
                <p class="text-gray-600">Enter your PIN to continue</p>
            </div>

            <!-- PIN Display -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-6">
                <div class="flex justify-center gap-4 mb-8" id="pinDisplay">
                    <div class="pin-dot w-4 h-4 rounded-full border-2 border-gray-300"></div>
                    <div class="pin-dot w-4 h-4 rounded-full border-2 border-gray-300"></div>
                    <div class="pin-dot w-4 h-4 rounded-full border-2 border-gray-300"></div>
                    <div class="pin-dot w-4 h-4 rounded-full border-2 border-gray-300"></div>
                </div>

                <!-- Number Pad -->
                <div class="grid grid-cols-3 gap-4" id="numberPad">
                    <button class="number-btn bg-gray-100 hover:bg-gray-200 rounded-xl h-16 text-2xl font-semibold text-gray-800 shadow-sm" data-num="1">1</button>
                    <button class="number-btn bg-gray-100 hover:bg-gray-200 rounded-xl h-16 text-2xl font-semibold text-gray-800 shadow-sm" data-num="2">2</button>
                    <button class="number-btn bg-gray-100 hover:bg-gray-200 rounded-xl h-16 text-2xl font-semibold text-gray-800 shadow-sm" data-num="3">3</button>
                    <button class="number-btn bg-gray-100 hover:bg-gray-200 rounded-xl h-16 text-2xl font-semibold text-gray-800 shadow-sm" data-num="4">4</button>
                    <button class="number-btn bg-gray-100 hover:bg-gray-200 rounded-xl h-16 text-2xl font-semibold text-gray-800 shadow-sm" data-num="5">5</button>
                    <button class="number-btn bg-gray-100 hover:bg-gray-200 rounded-xl h-16 text-2xl font-semibold text-gray-800 shadow-sm" data-num="6">6</button>
                    <button class="number-btn bg-gray-100 hover:bg-gray-200 rounded-xl h-16 text-2xl font-semibold text-gray-800 shadow-sm" data-num="7">7</button>
                    <button class="number-btn bg-gray-100 hover:bg-gray-200 rounded-xl h-16 text-2xl font-semibold text-gray-800 shadow-sm" data-num="8">8</button>
                    <button class="number-btn bg-gray-100 hover:bg-gray-200 rounded-xl h-16 text-2xl font-semibold text-gray-800 shadow-sm" data-num="9">9</button>
                    <button class="number-btn bg-red-100 hover:bg-red-200 rounded-xl h-16 text-xl font-semibold text-red-600 shadow-sm" id="clearBtn">
                        <i class="fas fa-backspace"></i>
                    </button>
                    <button class="number-btn bg-gray-100 hover:bg-gray-200 rounded-xl h-16 text-2xl font-semibold text-gray-800 shadow-sm" data-num="0">0</button>
                    <button class="number-btn btn-primary hover:opacity-90 rounded-xl h-16 text-xl font-semibold text-white shadow-sm" id="enterBtn">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <div id="errorMsg" class="mt-4 text-red-600 text-center text-sm hidden"></div>
            </div>

            <p class="text-center text-sm text-gray-500">
                <i class="fas fa-lock mr-1"></i>
                Secure Login System
            </p>
        </div>
    </div>

    <script>
        let pin = '';
        const pinDots = document.querySelectorAll('.pin-dot');
        const errorMsg = document.getElementById('errorMsg');

        // Number buttons
        document.querySelectorAll('[data-num]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (pin.length < 4) {
                    pin += btn.dataset.num;
                    updatePinDisplay();
                    
                    // Auto-submit when 4 digits entered
                    if (pin.length === 4) {
                        setTimeout(submitPin, 300);
                    }
                }
            });
        });

        // Clear button
        document.getElementById('clearBtn').addEventListener('click', () => {
            if (pin.length > 0) {
                pin = pin.slice(0, -1);
                updatePinDisplay();
            }
        });

        // Enter button
        document.getElementById('enterBtn').addEventListener('click', submitPin);

        // Keyboard support
        document.addEventListener('keydown', (e) => {
            if (e.key >= '0' && e.key <= '9' && pin.length < 4) {
                pin += e.key;
                updatePinDisplay();
                
                // Auto-submit when 4 digits entered
                if (pin.length === 4) {
                    setTimeout(submitPin, 300);
                }
            } else if (e.key === 'Backspace') {
                pin = pin.slice(0, -1);
                updatePinDisplay();
            } else if (e.key === 'Enter' && pin.length > 0) {
                submitPin();
            }
        });

        function updatePinDisplay() {
            pinDots.forEach((dot, index) => {
                if (index < pin.length) {
                    dot.classList.add('filled');
                } else {
                    dot.classList.remove('filled');
                }
            });
            errorMsg.classList.add('hidden');
        }

        function submitPin() {
            if (pin.length === 0) return;

            const formData = new FormData();
            formData.append('pin', pin);

            // Disable input during submission
            document.querySelectorAll('.number-btn').forEach(btn => btn.disabled = true);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    errorMsg.classList.add('hidden');
                    errorMsg.classList.remove('text-red-600');
                    errorMsg.classList.add('text-green-600');
                    errorMsg.textContent = 'Login successful! Redirecting...';
                    errorMsg.classList.remove('hidden');
                    
                    setTimeout(() => {
                        window.location.href = data.data.redirect;
                    }, 500);
                } else {
                    errorMsg.textContent = data.message;
                    errorMsg.classList.remove('hidden');
                    pin = '';
                    updatePinDisplay();
                    
                    // Shake animation
                    pinDots.forEach(dot => {
                        dot.style.animation = 'shake 0.5s';
                        setTimeout(() => dot.style.animation = '', 500);
                    });
                    
                    // Re-enable input
                    document.querySelectorAll('.number-btn').forEach(btn => btn.disabled = false);
                }
            })
            .catch(err => {
                errorMsg.textContent = 'Connection error. Please try again.';
                errorMsg.classList.remove('hidden');
                pin = '';
                updatePinDisplay();
                document.querySelectorAll('.number-btn').forEach(btn => btn.disabled = false);
            });
        }
    </script>
</body>
</html>