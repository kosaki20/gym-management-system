<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$logged_in_user_id = $_SESSION['user_id'];

// Get client details
$stmt_c = $conn->prepare("SELECT m.* FROM members m JOIN users u ON m.user_id = u.id WHERE u.id = ? AND m.member_type = 'client'");
$client = null;
if ($stmt_c) {
    $stmt_c->bind_param("i", $logged_in_user_id);
    $stmt_c->execute();
    $client = $stmt_c->get_result()->fetch_assoc();
    $stmt_c->close();
}

// Get client attendance history
$attendance = [];
$stmt_a = $conn->prepare("
    SELECT a.* 
    FROM attendance a 
    JOIN members m ON a.member_id = m.id 
    JOIN users u ON m.user_id = u.id 
    WHERE u.id = ? 
    ORDER BY a.check_in DESC
");
if ($stmt_a) {
    $stmt_a->bind_param("i", $logged_in_user_id);
    $stmt_a->execute();
    $res_a = $stmt_a->get_result();
    while ($row = $res_a->fetch_assoc()) {
        $attendance[] = $row;
    }
    $stmt_a->close();
}

// Get today's check-in status
$today = date('Y-m-d');
$today_checkin = null;
$stmt_t = $conn->prepare("
    SELECT a.* 
    FROM attendance a 
    JOIN members m ON a.member_id = m.id 
    JOIN users u ON m.user_id = u.id 
    WHERE u.id = ? AND DATE(a.check_in) = ? 
    ORDER BY a.check_in DESC LIMIT 1
");
if ($stmt_t) {
    $stmt_t->bind_param("is", $logged_in_user_id, $today);
    $stmt_t->execute();
    $today_checkin = $stmt_t->get_result()->fetch_assoc();
    $stmt_t->close();
}

// Weekly and Monthly statistics
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_count = 0;
$stmt_w = $conn->prepare("
    SELECT COUNT(*) as cnt 
    FROM attendance a 
    JOIN members m ON a.member_id = m.id 
    JOIN users u ON m.user_id = u.id 
    WHERE u.id = ? AND DATE(a.check_in) >= ?
");
if ($stmt_w) {
    $stmt_w->bind_param("is", $logged_in_user_id, $week_start);
    $stmt_w->execute();
    $week_count = $stmt_w->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt_w->close();
}

$month_start = date('Y-m-01');
$month_count = 0;
$stmt_m = $conn->prepare("
    SELECT COUNT(*) as cnt 
    FROM attendance a 
    JOIN members m ON a.member_id = m.id 
    JOIN users u ON m.user_id = u.id 
    WHERE u.id = ? AND DATE(a.check_in) >= ?
");
if ($stmt_m) {
    $stmt_m->bind_param("is", $logged_in_user_id, $month_start);
    $stmt_m->execute();
    $month_count = $stmt_m->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt_m->close();
}

$qr_code_path = $client['qr_code_path'] ?? null;
$has_qr_code = ($qr_code_path && file_exists($qr_code_path));

$page_title = "My Gym Attendance — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="clipboard-check" style="color: var(--accent);"></i>
        My Attendance & Digital Check-In Pass
      </h1>
      <p class="gym-page-subtitle">Track your gym visit logs, view check-in timestamps, and download your digital QR scanner pass.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="client_dashboard.php" class="gym-btn gym-btn-outline">
        <i data-lucide="arrow-left"></i> Dashboard
      </a>
    </div>
  </div>

  <!-- 4 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Gym Visits</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo count($attendance); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Lifetime attendance count</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="clipboard-check"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Visits This Week</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo $week_count; ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Since <?php echo date('M j', strtotime($week_start)); ?></div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="calendar"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Visits This Month</div>
        <div class="gym-stat-number" style="color: #60a5fa;"><?php echo $month_count; ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;"><?php echo date('F Y'); ?></div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="trending-up"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Last Gym Visit</div>
        <div class="gym-stat-number" style="font-size: 1.3rem; color: #c084fc; margin-top: 4px;">
          <?php echo !empty($attendance) ? date('M j, Y', strtotime($attendance[0]['check_in'])) : 'No visits yet'; ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Recent check-in timestamp</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(192, 132, 252, 0.15); color: #c084fc; border-color: rgba(192, 132, 252, 0.3);">
        <i data-lucide="clock"></i>
      </div>
    </div>
  </div>

  <!-- 2-Column Asymmetric Workspace -->
  <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    
    <!-- LEFT COLUMN: TODAY'S STATUS & QR CODE -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
      
      <!-- Today's Status Box Card -->
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1rem;">
          <i data-lucide="clock" style="color: var(--accent);"></i>
          Today's Attendance Status
        </h2>

        <div style="text-align: center; background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 18px;">
          <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-primary); margin-bottom: 2px;">
            <?php echo date('g:i A'); ?>
          </div>
          <div style="font-size: 0.85rem; color: var(--text-dim); margin-bottom: 14px;">
            <?php echo date('l, F j, Y'); ?>
          </div>

          <?php if ($today_checkin): ?>
            <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: var(--radius-sm); padding: 12px;">
              <span class="gym-badge gym-badge-active" style="margin-bottom: 6px; display: inline-block;">CHECKED IN TODAY</span>
              <div style="font-size: 1.1rem; font-weight: 700; color: #4ade80;">
                In: <?php echo date('g:i A', strtotime($today_checkin['check_in'])); ?>
                <?php if ($today_checkin['check_out']): ?>
                  &middot; Out: <?php echo date('g:i A', strtotime($today_checkin['check_out'])); ?>
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-sm); padding: 12px;">
              <span class="gym-badge gym-badge-pending" style="margin-bottom: 6px; display: inline-block;">NOT CHECKED IN YET</span>
              <div style="font-size: 0.82rem; color: #f59e0b;">
                Scan your QR code pass at the entrance terminal to register today's session.
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Digital QR Code Card -->
      <div class="gym-card" style="text-align: center;">
        <h2 class="gym-card-title flex items-center justify-center gap-2" style="margin-bottom: 1rem;">
          <i data-lucide="qr-code" style="color: var(--accent);"></i>
          Digital Gym Check-In Pass
        </h2>

        <?php if ($has_qr_code): ?>
          <div style="background: #fff; padding: 14px; border-radius: 12px; display: inline-block; box-shadow: 0 8px 24px rgba(0,0,0,0.5); margin-bottom: 14px;">
            <img src="<?php echo htmlspecialchars($qr_code_path); ?>" alt="Digital QR Pass" style="width: 160px; height: 160px; border-radius: 4px; display: block;">
          </div>
          <div>
            <a href="<?php echo htmlspecialchars($qr_code_path); ?>" download style="display: inline-flex; align-items: center; gap: 8px;" class="gym-btn gym-btn-yellow">
              <i data-lucide="download"></i> Download QR Pass Image
            </a>
          </div>
          <div style="font-size: 0.8rem; color: var(--text-dim); margin-top: 12px; text-align: left;">
            <p style="margin: 0 0 4px; font-weight: 700; color: var(--text-secondary);">Instructions:</p>
            <ul style="margin: 0; padding-left: 18px; line-height: 1.5;">
              <li>Show this QR code at the reception optical scanner.</li>
              <li>Linked to Client ID: <strong>CLIENT_<?php echo $client['id'] ?? 0; ?></strong></li>
            </ul>
          </div>
        <?php else: ?>
          <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); padding: 16px; border-radius: var(--radius-sm);">
            <i data-lucide="alert-circle" style="width: 32px; height: 32px; color: #ef4444; margin: 0 auto 8px; display: block;"></i>
            <div style="font-weight: 700; color: #f87171; font-size: 0.92rem; margin-bottom: 4px;">QR Code Not Yet Generated</div>
            <p style="font-size: 0.8rem; color: var(--text-dim); margin: 0;">Please contact your trainer or reception to activate your digital pass.</p>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- RIGHT COLUMN: ATTENDANCE HISTORY TABLE -->
    <div>
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
          <i data-lucide="history" style="color: var(--accent);"></i>
          Attendance Log Roster (<?php echo count($attendance); ?> Visits)
        </h2>

        <div class="gym-table-wrapper" style="margin-bottom: 0;">
          <table class="gym-table">
            <thead>
              <tr>
                <th>Visit Date</th>
                <th>Check-In Time</th>
                <th>Check-Out Time</th>
                <th>Duration</th>
                <th style="text-align: center;">Session Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($attendance)): ?>
                <?php foreach ($attendance as $v): ?>
                  <?php
                  $cin = strtotime($v['check_in']);
                  $cout = !empty($v['check_out']) ? strtotime($v['check_out']) : null;
                  $duration_str = '-';
                  if ($cin && $cout) {
                      $diff = $cout - $cin;
                      $hrs = floor($diff / 3600);
                      $mins = floor(($diff % 3600) / 60);
                      $duration_str = ($hrs > 0 ? "{$hrs}h " : "") . "{$mins}m";
                  }
                  ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--text-primary);"><?php echo date('F j, Y (D)', $cin); ?></td>
                    <td style="color: #4ade80; font-weight: 700;"><?php echo date('g:i A', $cin); ?></td>
                    <td style="color: var(--text-secondary);"><?php echo $cout ? date('g:i A', $cout) : '<span style="color: var(--text-dim);">-</span>'; ?></td>
                    <td style="color: var(--accent); font-weight: 600;"><?php echo $duration_str; ?></td>
                    <td style="text-align: center;">
                      <?php if ($cout): ?>
                        <span class="gym-badge gym-badge-active">Completed</span>
                      <?php else: ?>
                        <span class="gym-badge gym-badge-info">Active Session</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                    <i data-lucide="clipboard-x" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                    <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No gym check-in visits logged yet.</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
      if (typeof lucide !== 'undefined') {
          lucide.createIcons();
      }
  });
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
