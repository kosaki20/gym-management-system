<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection

require_once __DIR__ . '/../config/config.php';

// Check connection
// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// ADD THIS SECTION FOR CHAT FUNCTIONALITY
require_once 'chat_functions.php';
$unread_count = getUnreadCount($_SESSION['user_id'], $conn);

// Initialize notification variables
$notification_count = 0;
$notifications = [];

// Include notification functions if trainer or admin
if (in_array($_SESSION['role'], ['trainer', 'admin'])) {
    require_once 'notification_functions.php';
    $notification_count = getUnreadNotificationCount($conn, $user_id);
    
    if ($_SESSION['role'] == 'trainer') {
        $notifications = getTrainerNotifications($conn, $user_id);
    } else {
        $notifications = getAdminNotifications($conn);
    }
}

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
                <i data-lucide="user"></i>
                My Profile
            </h1>
        </div>

        <div class="profile-card">
            <!-- Profile Header -->
            <div class="flex items-center gap-6 mb-8">
                <div class="relative">
                    <?php 
                      $default_avatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'><rect width='100' height='100' fill='%231e293b'/><circle cx='50' cy='38' r='18' fill='%2364748b'/><path d='M20,85 C20,62 33,54 50,54 C67,54 80,62 80,85 Z' fill='%2364748b'/></svg>";
                      $avatar_src = (!empty($user['profile_picture']) && $user['profile_picture'] !== 'https://i.pravatar.cc/120') ? $user['profile_picture'] : $default_avatar;
                    ?>
                    <img src="<?php echo htmlspecialchars($avatar_src); ?>" class="w-24 h-24 rounded-full border-4 border-yellow-400/50" style="object-fit: cover;">
                    <div class="absolute -bottom-2 -right-2">
                        <span class="role-badge role-<?php echo $_SESSION['role']; ?>">
                            <i data-lucide="<?php echo $_SESSION['role'] == 'admin' ? 'shield' : ($_SESSION['role'] == 'trainer' ? 'dumbbell' : 'user'); ?>" class="w-4 h-4"></i>
                            <?php echo ucfirst($_SESSION['role']); ?>
                        </span>
                    </div>
                </div>
                <div>
                    <h2 class="text-2xl font-bold "><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <p class="text-gray-400">Welcome to your profile dashboard</p>
                </div>
            </div>

            <!-- Profile Information Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">
                        <i data-lucide="user" class="w-4 h-4 inline mr-2"></i>
                        Username
                    </div>
                    <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">
                        <i data-lucide="mail" class="w-4 h-4 inline mr-2"></i>
                        Email Address
                    </div>
                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">
                        <i data-lucide="shield" class="w-4 h-4 inline mr-2"></i>
                        Account Role
                    </div>
                    <div class="info-value">
                        <span class="role-badge role-<?php echo $_SESSION['role']; ?>">
                            <?php echo ucfirst($_SESSION['role']); ?>
                        </span>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">
                        <i data-lucide="calendar" class="w-4 h-4 inline mr-2"></i>
                        Member Since
                    </div>
                    <div class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                </div>

                <?php if ($user['client_type']): ?>
                <div class="info-item">
                    <div class="info-label">
                        <i data-lucide="users" class="w-4 h-4 inline mr-2"></i>
                        Client Type
                    </div>
                    <div class="info-value capitalize"><?php echo str_replace('-', ' ', $user['client_type']); ?></div>
                </div>
                <?php endif; ?>

                <div class="info-item">
                    <div class="info-label">
                        <i data-lucide="clock" class="w-4 h-4 inline mr-2"></i>
                        Last Login
                    </div>
                    <div class="info-value"><?php echo $user['last_activity'] ? date('F j, Y g:i A', strtotime($user['last_activity'])) : 'Never'; ?></div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-4 mt-8 pt-6 border-t border-gray-700">
                <a href="edit_profile.php" class="btn gym-btn gym-btn-yellow">
                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                    Edit Profile
                </a>
                <a href="change_password.php" class="btn gym-btn gym-btn-outline">
                    <i data-lucide="key" class="w-4 h-4"></i>
                    Change Password
                </a>
            </div>
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

        // Enhanced notification functionality
        function setupDropdowns() {
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const userMenuButton = document.getElementById('userMenuButton');
            const userDropdown = document.getElementById('userDropdown');
            
            // Close all dropdowns
            function closeAllDropdowns() {
                if (notificationDropdown) notificationDropdown.classList.add('hidden');
                if (userDropdown) userDropdown.classList.add('hidden');
            }
            
            // Toggle notification dropdown
            if (notificationBell) {
                notificationBell.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const isHidden = notificationDropdown.classList.contains('hidden');
                    
                    closeAllDropdowns();
                    
                    if (isHidden) {
                        notificationDropdown.classList.remove('hidden');
                    }
                });
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
                if ((!notificationDropdown || !notificationDropdown.contains(e.target)) && (!notificationBell || !notificationBell.contains(e.target)) &&
                    (!userDropdown || !userDropdown.contains(e.target)) && (!userMenuButton || !userMenuButton.contains(e.target))) {
                    closeAllDropdowns();
                }
            });
            
            // Mark all as read
            const markAllRead = document.getElementById('markAllRead');
            if (markAllRead) {
                markAllRead.addEventListener('click', function(e) {
                    e.stopPropagation();
                    markAllNotificationsAsRead();
                });
            }
            
            // Close dropdowns when pressing Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAllDropdowns();
                }
            });
        }

        // AJAX function to mark all notifications as read
        function markAllNotificationsAsRead() {
            fetch('notification_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide notification badge
                    document.getElementById('notificationBadge').classList.add('hidden');
                    // Refresh the page to update notifications
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        setupDropdowns();
    });
  </script>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
