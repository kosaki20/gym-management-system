<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$action_message = '';
$action_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        if ($user_id === $_SESSION['user_id']) {
            $action_message = "Error: You cannot delete your own logged-in admin account.";
            $action_type = "error";
        } else {
            if ($conn->query("DELETE FROM users WHERE id = $user_id")) {
                $action_message = "User account deleted successfully!";
                $action_type = "success";
            } else {
                $action_message = "Error deleting user: " . $conn->error;
                $action_type = "error";
            }
        }
    }
}

// Fetch all users
$users_result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

// Role counts
$admin_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'] ?? 0;
$trainer_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'trainer'")->fetch_assoc()['count'] ?? 0;
$client_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'client'")->fetch_assoc()['count'] ?? 0;
$total_users = $products_res = $users_result ? $users_result->num_rows : 0;

$page_title = "Manage Users — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="users" style="color: var(--accent);"></i>
        System User Directory & Accounts
      </h1>
      <p class="gym-page-subtitle">Manage user accounts, assign roles, view system access credentials, and communicate with members.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
      <a href="member_registration.php" class="gym-btn gym-btn-yellow">
        <i data-lucide="user-plus"></i> Register Member
      </a>
      <a href="admin_dashboard.php" class="gym-btn gym-btn-outline">
        <i data-lucide="arrow-left"></i> Dashboard
      </a>
    </div>
  </div>

  <?php if (!empty($action_message)): ?>
    <div style="background: <?php echo $action_type === 'success' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; border: 1px solid <?php echo $action_type === 'success' ? 'rgba(34, 197, 94, 0.4)' : 'rgba(239, 68, 68, 0.4)'; ?>; color: <?php echo $action_type === 'success' ? '#4ade80' : '#f87171'; ?>; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="<?php echo $action_type === 'success' ? 'check-circle-2' : 'alert-triangle'; ?>" style="width: 18px; height: 18px;"></i>
      <span><?php echo htmlspecialchars($action_message); ?></span>
    </div>
  <?php endif; ?>

  <!-- KPI Statistics Grid -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Admins</div>
        <div class="gym-stat-number" style="color: #ef4444;"><?php echo number_format($admin_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">System administrators</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
        <i data-lucide="shield"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Trainers</div>
        <div class="gym-stat-number" style="color: #3b82f6;"><?php echo number_format($trainer_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Fitness instructors</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="dumbbell"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Registered Clients</div>
        <div class="gym-stat-number" style="color: #22c55e;"><?php echo number_format($client_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Active gym members</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #22c55e; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="user"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Accounts</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo number_format($total_users); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Registered accounts</div>
      </div>
      <div class="gym-stat-icon">
        <i data-lucide="users"></i>
      </div>
    </div>
  </div>

  <!-- Users Table Card -->
  <div class="gym-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
      <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="list" style="color: var(--accent);"></i>
        System User Directory List
      </h2>
      <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <div style="position: relative; width: 280px; max-width: 100%;">
          <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-dim);"></i>
          <input type="text" id="searchUsers" placeholder="Search by name, username, email..." class="gym-form-control" style="padding-left: 38px; height: 40px; margin: 0;">
        </div>
      </div>
    </div>

    <div class="gym-table-wrapper" style="margin-bottom: 0;">
      <table class="gym-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Account User</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Joined Date</th>
            <th style="text-align: center;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($users_result && $users_result->num_rows > 0): ?>
            <?php while($user = $users_result->fetch_assoc()): ?>
              <?php
              $role_badge = '';
              switch(strtolower($user['role'])) {
                case 'admin':
                  $role_badge = '<span class="gym-badge gym-badge-inactive" style="background: rgba(239, 68, 68, 0.15); color: #f87171; border-color: rgba(239, 68, 68, 0.3);">ADMIN</span>';
                  break;
                case 'trainer':
                  $role_badge = '<span class="gym-badge gym-badge-info" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">TRAINER</span>';
                  break;
                case 'client':
                default:
                  $role_badge = '<span class="gym-badge gym-badge-active" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">CLIENT</span>';
              }
              ?>
              <tr>
                <td style="font-weight: 700; color: var(--text-dim);"><?php echo $user['id']; ?></td>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--bg-surface); border: 1px solid var(--border); color: var(--accent); display: flex; align-items: center; justify-content: center; font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 0.85rem;">
                      <?php echo strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1)); ?>
                    </div>
                    <div style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($user['full_name']); ?></div>
                  </div>
                </td>
                <td style="font-weight: 600; color: var(--text-secondary);"><?php echo htmlspecialchars($user['username']); ?></td>
                <td style="color: var(--text-dim);"><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo $role_badge; ?></td>
                <td style="color: var(--text-dim); font-size: 0.85rem;"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                <td>
                  <div style="display: flex; gap: 6px; align-items: center; justify-content: center;">
                    <a href="chat.php?user_id=<?php echo $user['id']; ?>" class="gym-btn gym-btn-yellow" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                      <i data-lucide="message-square" style="width: 14px; height: 14px;"></i> Chat
                    </a>
                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                      <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user account? This action cannot be undone.')" style="margin: 0;">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" name="delete_user" class="gym-btn gym-btn-danger" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                          <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i> Delete
                        </button>
                      </form>
                    <?php else: ?>
                      <span style="font-size: 0.72rem; color: var(--text-dim); font-weight: 700; padding: 4px 8px;">(Current)</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                <i data-lucide="users" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No users found in system directory.</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }

      const searchInput = document.getElementById('searchUsers');
      if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.gym-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
      }
  });
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
