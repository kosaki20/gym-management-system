<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';
require_once __DIR__ . '/notification_functions.php';

$user_id = $_SESSION['user_id'];
$unread_count = getUnreadCount($user_id, $conn);
$notification_count = getUnreadNotificationCount($conn, $user_id);
$notifications = getAdminNotifications($conn);

// Auto-create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS system_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_role VARCHAR(50) DEFAULT 'system',
    action VARCHAR(255) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Fetch logs
$logs_result = $conn->query("
    SELECT sal.*, u.full_name, u.username 
    FROM system_audit_logs sal 
    LEFT JOIN users u ON sal.user_id = u.id 
    ORDER BY sal.created_at DESC 
    LIMIT 100
");

$page_title = "System Audit Logs — Boiyets Fitness Gym";
require_once __DIR__ . "/includes/header.php";
?>

<div class="gym-layout">
  <?php require_once __DIR__ . "/includes/nav.php"; ?>

  <main class="gym-main-container">
    <div class="gym-page-header">
      <div class="gym-page-title-group">
        <h1 class="gym-page-title">
          <i data-lucide="shield-alert" class="gym-title-icon" style="color: var(--accent);"></i>
          System Audit Logs
        </h1>
        <p class="gym-page-subtitle">Track administrative activities, security operations, and system events.</p>
      </div>
      <div class="gym-page-actions">
        <a href="backup_db.php" class="gym-btn gym-btn-yellow">
          <i data-lucide="database"></i> Export SQL Backup
        </a>
      </div>
    </div>

    <div class="gym-card">
      <div class="gym-table-wrapper">
        <table class="gym-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Role</th>
              <th>Action</th>
              <th>Details</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($logs_result && $logs_result->num_rows > 0): ?>
              <?php while ($log = $logs_result->fetch_assoc()): ?>
                <tr>
                  <td style="font-weight: 700; color: var(--text-dim);">#<?php echo $log['id']; ?></td>
                  <td style="font-weight: 700; color: var(--text-primary);">
                    <?php echo htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'System'); ?>
                  </td>
                  <td>
                    <span class="gym-badge gym-badge-info">
                      <?php echo strtoupper(htmlspecialchars($log['user_role'] ?? 'SYSTEM')); ?>
                    </span>
                  </td>
                  <td style="font-weight: 600; color: var(--accent);"><?php echo htmlspecialchars($log['action']); ?></td>
                  <td style="color: var(--text-secondary); max-width: 300px; word-break: break-word;"><?php echo htmlspecialchars($log['details'] ?? '-'); ?></td>
                  <td style="color: var(--text-dim);"><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 30px;">
                  No audit logs recorded yet.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
<?php $conn->close(); ?>
