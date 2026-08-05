<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';

// Generate CSRF token for this session
$csrf_token = ensureCsrfToken();

// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken();
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "All fields are required!";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match!";
        $message_type = "error";
    } elseif (strlen($new_password) < 6) {
        $message = "New password must be at least 6 characters long!";
        $message_type = "error";
    } else {
        // Verify current password — support bcrypt and legacy MD5 (upgrades automatically)
        $stored_hash = $user['password'];
        $password_valid = false;

        if (password_verify($current_password, $stored_hash)) {
            // Already bcrypt
            $password_valid = true;
        } elseif (strlen($stored_hash) === 32 && ctype_xdigit($stored_hash) && md5($current_password) === $stored_hash) {
            // Legacy MD5 hash — accept and upgrade to bcrypt immediately
            $password_valid = true;
        }

        if (!$password_valid) {
            $message = "Current password is incorrect!";
            $message_type = "error";
        } else {
            // Save new password as bcrypt
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_hashed, $user_id);

            if ($update_stmt->execute()) {
                $message = "Password changed successfully!";
                $message_type = "success";

                // Regenerate CSRF token after successful action
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];

                // Clear form fields
                $_POST = array();
            } else {
                $message = "Error changing password. Please try again.";
                $message_type = "error";
            }
        }
    }
}

// Include chat functionality
require_once 'chat_functions.php';
$unread_count = getUnreadCount($_SESSION['user_id'], $conn);

$conn->close();
?>

<?php
$page_title = "Page Name - Boiyets Fitness Gym";
require_once __DIR__ . "/includes/header.php";
require_once __DIR__ . "/includes/nav.php";
?>



  <!-- Topbar -->
  

  <div class="gym-main-container">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-yellow-400 flex items-center gap-2">
                <i data-lucide="key"></i>
                Change Password
            </h1>
            <a href="profile.php" class="btn gym-btn gym-btn-outline">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Profile
            </a>
        </div>

        <div class="card max-w-2xl">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i data-lucide="<?php echo $message_type == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label class="form-label" for="current_password">
                        <i data-lucide="lock" class="w-4 h-4 inline mr-2"></i>
                        Current Password
                    </label>
                    <input type="password" id="current_password" name="current_password" class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['current_password'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">
                        <i data-lucide="lock" class="w-4 h-4 inline mr-2"></i>
                        New Password
                    </label>
                    <input type="password" id="new_password" name="new_password" class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['new_password'] ?? ''); ?>" required>
                    <div id="passwordStrength" class="password-strength"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">
                        <i data-lucide="lock" class="w-4 h-4 inline mr-2"></i>
                        Confirm New Password
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['confirm_password'] ?? ''); ?>" required>
                    <div id="passwordMatch" class="password-strength"></div>
                </div>

                <div class="bg-yellow-400/10 border border-yellow-400/30  p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <i data-lucide="shield" class="w-5 h-5 text-yellow-400 mt-0.5 flex-shrink-0"></i>
                        <div>
                            <h3 class="text-yellow-400 font-semibold mb-2">Password Requirements</h3>
                            <ul class="text-sm text-gray-300 space-y-1">
                                <li>• At least 6 characters long</li>
                                <li>• Should not be the same as your current password</li>
                                <li>• Use a combination of letters, numbers, and symbols for better security</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4 mt-8 pt-6 border-t border-gray-700">
                    <button type="submit" class="btn gym-btn gym-btn-yellow">
                        <i data-lucide="key" class="w-4 h-4"></i>
                        Change Password
                    </button>
                    <a href="profile.php" class="btn gym-btn gym-btn-outline">
                        <i data-lucide="x" class="w-4 h-4"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();
        
        // Sidebar toggle
        document.getElementById('toggleSidebar').addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('w-60')) {
                sidebar.classList.remove('w-60');
                sidebar.classList.add('w-16', 'sidebar-collapsed');
            } else {
                sidebar.classList.remove('w-16', 'sidebar-collapsed');
                sidebar.classList.add('w-60');
            }
        });

        // Members submenu toggle (for trainers)
        const membersToggle = document.getElementById('membersToggle');
        const membersSubmenu = document.getElementById('membersSubmenu');
        const membersChevron = document.getElementById('membersChevron');
        
        if (membersToggle) {
            membersToggle.addEventListener('click', () => {
                membersSubmenu.classList.toggle('open');
                membersChevron.classList.toggle('rotate');
            });
        }

        // Plans submenu toggle (for trainers)
        const plansToggle = document.getElementById('plansToggle');
        const plansSubmenu = document.getElementById('plansSubmenu');
        const plansChevron = document.getElementById('plansChevron');
        
        if (plansToggle) {
            plansToggle.addEventListener('click', () => {
                plansSubmenu.classList.toggle('open');
                plansChevron.classList.toggle('rotate');
            });
        }
        
        // Hover to open sidebar (for collapsed state)
        const sidebar = document.getElementById('sidebar');
        sidebar.addEventListener('mouseenter', () => {
            if (sidebar.classList.contains('sidebar-collapsed')) {
                sidebar.classList.remove('w-16', 'sidebar-collapsed');
                sidebar.classList.add('w-60');
            }
        });
        
        sidebar.addEventListener('mouseleave', () => {
            if (!sidebar.classList.contains('sidebar-collapsed') && window.innerWidth > 768) {
                sidebar.classList.remove('w-60');
                sidebar.classList.add('w-16', 'sidebar-collapsed');
            }
        });

        // Enhanced dropdown functionality
        function setupDropdowns() {
            const userMenuButton = document.getElementById('userMenuButton');
            const userDropdown = document.getElementById('userDropdown');
            
            // Close all dropdowns
            function closeAllDropdowns() {
                if (userDropdown) userDropdown.classList.add('hidden');
            }
            
            // Toggle user dropdown
            if (userMenuButton) {
                userMenuButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const isHidden = userDropdown.classList.contains('hidden');
                    
                    closeAllDropdowns();
                    
                    if (isHidden) {
                        userDropdown.classList.remove('hidden');
                    }
                });
            }
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if ((!userDropdown || !userDropdown.contains(e.target)) && (!userMenuButton || !userMenuButton.contains(e.target))) {
                    closeAllDropdowns();
                }
            });
            
            // Close dropdowns when pressing Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAllDropdowns();
                }
            });
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            const strengthText = document.getElementById('passwordStrength');
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength++;
            
            let text = '';
            let className = '';
            
            switch(strength) {
                case 0:
                case 1:
                    text = 'Weak password';
                    className = 'strength-weak';
                    break;
                case 2:
                case 3:
                    text = 'Medium password';
                    className = 'strength-medium';
                    break;
                case 4:
                case 5:
                    text = 'Strong password';
                    className = 'strength-strong';
                    break;
            }
            
            strengthText.textContent = text;
            strengthText.className = 'password-strength ' + className;
        }

        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchText.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchText.textContent = 'Passwords match';
                matchText.className = 'password-strength strength-strong';
            } else {
                matchText.textContent = 'Passwords do not match';
                matchText.className = 'password-strength strength-weak';
            }
        }

        // Add event listeners for password validation
        document.getElementById('new_password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
        
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

        setupDropdowns();
    });
  </script>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
