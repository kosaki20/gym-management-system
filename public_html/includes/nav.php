<?php
// Sidebar Navigation Component
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'guest';
$default_avatar = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'><rect width='100' height='100' fill='%231e293b'/><circle cx='50' cy='38' r='18' fill='%2364748b'/><path d='M20,85 C20,62 33,54 50,54 C67,54 80,62 80,85 Z' fill='%2364748b'/></svg>";
$user_avatar = (!empty($_SESSION['profile_picture']) && $_SESSION['profile_picture'] !== 'https://i.pravatar.cc/120') ? $_SESSION['profile_picture'] : $default_avatar;
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Overlay -->
<div class="sidebar-mobile-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="mainSidebar">
    <a href="<?php
        echo $user_role === 'admin' ? 'admin_dashboard.php' :
            ($user_role === 'trainer' ? 'trainer_dashboard.php' : 'client_dashboard.php');
    ?>" class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="fa-solid fa-dumbbell"></i></div>
        <div class="sidebar-brand-text">BOIYETS <span>GYM</span></div>
    </a>

    <nav class="sidebar-nav">
        <?php if ($user_role === 'admin'): ?>
            <div class="sidebar-section-label">Main</div>
            <a href="admin_dashboard.php" class="sidebar-link <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i>
                <span class="sidebar-link-text">Dashboard</span>
            </a>
            <a href="all_members.php" class="sidebar-link <?php echo $current_page == 'all_members.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i>
                <span class="sidebar-link-text">Members</span>
            </a>
            <a href="attendance_logs.php" class="sidebar-link <?php echo $current_page == 'attendance_logs.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard-check"></i>
                <span class="sidebar-link-text">Attendance</span>
            </a>

            <div class="sidebar-section-label">Operations</div>
            <a href="equipment_monitoring.php" class="sidebar-link <?php echo $current_page == 'equipment_monitoring.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-toolbox"></i>
                <span class="sidebar-link-text">Equipment</span>
            </a>
            <a href="revenue.php" class="sidebar-link <?php echo $current_page == 'revenue.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span class="sidebar-link-text">Sales & Revenue</span>
            </a>
            <a href="products.php" class="sidebar-link <?php echo $current_page == 'products.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-box"></i>
                <span class="sidebar-link-text">Products</span>
            </a>

            <div class="sidebar-section-label">Communication</div>
            <a href="chat.php" class="sidebar-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-comments"></i>
                <span class="sidebar-link-text">Messages</span>
            </a>
            <a href="adminannouncement.php" class="sidebar-link <?php echo $current_page == 'adminannouncement.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-bullhorn"></i>
                <span class="sidebar-link-text">Announcements</span>
            </a>
            <a href="feedbacksadmin.php" class="sidebar-link <?php echo $current_page == 'feedbacksadmin.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-message"></i>
                <span class="sidebar-link-text">Feedback</span>
            </a>

            <div class="sidebar-section-label">Settings</div>
            <a href="view_users.php" class="sidebar-link <?php echo $current_page == 'view_users.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-gear"></i>
                <span class="sidebar-link-text">User Accounts</span>
            </a>
            <a href="audit_log.php" class="sidebar-link <?php echo $current_page == 'audit_log.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-shield-halved"></i>
                <span class="sidebar-link-text">Audit Logs</span>
            </a>
            <a href="settings.php" class="sidebar-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear"></i>
                <span class="sidebar-link-text">Settings</span>
            </a>

        <?php elseif ($user_role === 'trainer'): ?>
            <div class="sidebar-section-label">Main</div>
            <a href="trainer_dashboard.php" class="sidebar-link <?php echo $current_page == 'trainer_dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i>
                <span class="sidebar-link-text">Dashboard</span>
            </a>

            <div class="sidebar-section-label">Manage</div>
            <a href="trainerworkout.php" class="sidebar-link <?php echo $current_page == 'trainerworkout.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-dumbbell"></i>
                <span class="sidebar-link-text">Workout Plans</span>
            </a>
            <a href="trainermealplan.php" class="sidebar-link <?php echo $current_page == 'trainermealplan.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-utensils"></i>
                <span class="sidebar-link-text">Meal Plans</span>
            </a>
            <a href="clientprogress.php" class="sidebar-link <?php echo $current_page == 'clientprogress.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span class="sidebar-link-text">Client Progress</span>
            </a>
            <a href="trainermanageqrcodes.php" class="sidebar-link <?php echo $current_page == 'trainermanageqrcodes.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-qrcode"></i>
                <span class="sidebar-link-text">QR Codes</span>
            </a>

            <div class="sidebar-section-label">Operations</div>
            <a href="trainer_equipment_monitoring.php" class="sidebar-link <?php echo $current_page == 'trainer_equipment_monitoring.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-toolbox"></i>
                <span class="sidebar-link-text">Equipment</span>
            </a>
            <a href="trainer_renewal_requests.php" class="sidebar-link <?php echo $current_page == 'trainer_renewal_requests.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-rotate"></i>
                <span class="sidebar-link-text">Renewals</span>
            </a>

            <div class="sidebar-section-label">Communication</div>
            <a href="chat.php" class="sidebar-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-comments"></i>
                <span class="sidebar-link-text">Messages</span>
            </a>
            <a href="feedbackstrainer.php" class="sidebar-link <?php echo $current_page == 'feedbackstrainer.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-message"></i>
                <span class="sidebar-link-text">Feedback</span>
            </a>

        <?php elseif ($user_role === 'client'): ?>
            <div class="sidebar-section-label">Main</div>
            <a href="client_dashboard.php" class="sidebar-link <?php echo $current_page == 'client_dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i>
                <span class="sidebar-link-text">My Portal</span>
            </a>

            <div class="sidebar-section-label">Fitness</div>
            <a href="booking.php" class="sidebar-link <?php echo $current_page == 'booking.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-plus"></i>
                <span class="sidebar-link-text">Book Session</span>
            </a>
            <a href="workoutplansclient.php" class="sidebar-link <?php echo $current_page == 'workoutplansclient.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-fire"></i>
                <span class="sidebar-link-text">Workouts</span>
            </a>
            <a href="nutritionplansclient.php" class="sidebar-link <?php echo $current_page == 'nutritionplansclient.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-apple-whole"></i>
                <span class="sidebar-link-text">Nutrition</span>
            </a>
            <a href="myprogressclient.php" class="sidebar-link <?php echo $current_page == 'myprogressclient.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span class="sidebar-link-text">My Progress</span>
            </a>

            <div class="sidebar-section-label">Account</div>
            <a href="membershipclient.php" class="sidebar-link <?php echo $current_page == 'membershipclient.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-id-card"></i>
                <span class="sidebar-link-text">Membership</span>
            </a>
            <a href="attendanceclient.php" class="sidebar-link <?php echo $current_page == 'attendanceclient.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-check"></i>
                <span class="sidebar-link-text">Attendance</span>
            </a>
            <a href="chat.php" class="sidebar-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-comments"></i>
                <span class="sidebar-link-text">Messages</span>
            </a>
            <a href="feedbacksclient.php" class="sidebar-link <?php echo $current_page == 'feedbacksclient.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-message"></i>
                <span class="sidebar-link-text">Feedback</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="Avatar" class="sidebar-user-avatar" loading="lazy">
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="sidebar-user-role"><?php echo htmlspecialchars($user_role); ?></div>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout" title="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span class="sidebar-logout-text">Sign Out</span>
        </a>
    </div>
</aside>

<!-- Mobile FAB Toggle -->
<button class="sidebar-mobile-toggle" id="sidebarToggle" onclick="toggleMobileSidebar()" aria-label="Toggle menu">
    <i class="fa-solid fa-bars" id="sidebarToggleIcon"></i>
</button>

<script>
function toggleMobileSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const icon = document.getElementById('sidebarToggleIcon');
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('show');
    icon.classList.toggle('fa-bars');
    icon.classList.toggle('fa-xmark');
}
function closeMobileSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const icon = document.getElementById('sidebarToggleIcon');
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('show');
    icon.classList.add('fa-bars');
    icon.classList.remove('fa-xmark');
}
</script>
