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
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);

    // Validate inputs
    if (empty($full_name) || empty($email) || empty($username)) {
        $message = "All fields are required!";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address!";
        $message_type = "error";
    } else {
        // Check if username or email already exists (excluding current user)
        $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssi", $username, $email, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "Username or email already exists!";
            $message_type = "error";
        } else {
            // Handle profile picture upload
            $profile_picture = $user['profile_picture'] ?? ''; // Keep existing picture by default
            
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'profile_pictures/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_tmp = $_FILES['profile_picture']['tmp_name'];
                $file_name = time() . '_' . basename($_FILES['profile_picture']['name']);
                $file_path = $upload_dir . $file_name;
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $file_type = mime_content_type($file_tmp);
                
                if (in_array($file_type, $allowed_types)) {
                    // Validate file size (max 5MB)
                    if ($_FILES['profile_picture']['size'] <= 5 * 1024 * 1024) {
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            // Delete old profile picture if it exists and is not the default
                            if (!empty($user['profile_picture']) && 
                                $user['profile_picture'] != 'https://i.pravatar.cc/120' &&
                                file_exists($user['profile_picture'])) {
                                unlink($user['profile_picture']);
                            }
                            $profile_picture = $file_path;
                        } else {
                            $message = "Failed to upload profile picture.";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Profile picture must be less than 5MB.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Only JPG, PNG, and GIF files are allowed.";
                    $message_type = "error";
                }
            }
            
            // Only proceed with update if no file upload errors
            if ($message_type !== 'error') {
                // Update user profile
                $update_sql = "UPDATE users SET full_name = ?, email = ?, username = ?, profile_picture = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssssi", $full_name, $email, $username, $profile_picture, $user_id);

                if ($update_stmt->execute()) {
                    // Update session variables
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['username'] = $username;
                    $_SESSION['profile_picture'] = $profile_picture;
                    
                    $message = "Profile updated successfully!";
                    $message_type = "success";
                    
                    // Refresh user data
                    $user['full_name'] = $full_name;
                    $user['email'] = $email;
                    $user['username'] = $username;
                    $user['profile_picture'] = $profile_picture;
                } else {
                    $message = "Error updating profile. Please try again.";
                    $message_type = "error";
                }
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
                <i data-lucide="edit-2"></i>
                Edit Profile
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

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <!-- Profile Picture Section -->
                <div class="form-group text-center mb-8">
                    <label class="form-label block text-center mb-4">
                        <i data-lucide="camera" class="w-4 h-4 inline mr-2"></i>
                        Profile Picture
                    </label>
                    <div class="profile-picture-container mx-auto">
                        <?php 
                          $default_avatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'><rect width='100' height='100' fill='%231e293b'/><circle cx='50' cy='38' r='18' fill='%2364748b'/><path d='M20,85 C20,62 33,54 50,54 C67,54 80,62 80,85 Z' fill='%2364748b'/></svg>";
                          $edit_avatar_src = (!empty($user['profile_picture']) && $user['profile_picture'] !== 'https://i.pravatar.cc/120') ? $user['profile_picture'] : $default_avatar;
                        ?>
                        <img src="<?php echo htmlspecialchars($edit_avatar_src); ?>" 
                             alt="Profile Picture" 
                             class="profile-picture"
                             id="profilePicturePreview">
                        <div class="profile-picture-overlay" onclick="document.getElementById('profile_picture').click()">
                            <i data-lucide="camera" class="w-6 h-6 "></i>
                        </div>
                    </div>
                    <input type="file" 
                           id="profile_picture" 
                           name="profile_picture" 
                           class="file-input" 
                           accept="image/jpeg,image/jpg,image/png,image/gif"
                           onchange="previewImage(this)">
                    <div class="file-info">
                        Click on the picture to change it. Max size: 5MB (JPG, PNG, GIF)
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">
                        <i data-lucide="user" class="w-4 h-4 inline mr-2"></i>
                        Full Name
                    </label>
                    <input type="text" id="full_name" name="full_name" class="form-input" 
                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">
                        <i data-lucide="mail" class="w-4 h-4 inline mr-2"></i>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="username">
                        <i data-lucide="at-sign" class="w-4 h-4 inline mr-2"></i>
                        Username
                    </label>
                    <input type="text" id="username" name="username" class="form-input" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i data-lucide="shield" class="w-4 h-4 inline mr-2"></i>
                        Account Role
                    </label>
                    <div class="form-input bg-opacity-50 cursor-not-allowed">
                        <?php echo ucfirst($user['role']); ?>
                    </div>
                    <p class="text-gray-400 text-sm mt-1">Account role cannot be changed</p>
                </div>

                <div class="flex gap-4 mt-8 pt-6 border-t border-gray-700">
                    <button type="submit" class="btn gym-btn gym-btn-yellow">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Save Changes
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

        // Profile picture preview function
        function previewImage(input) {
            const preview = document.getElementById('profilePicturePreview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(file);
            }
        }

        setupDropdowns();
    });
  </script>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
