<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

// Authentication guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'trainer') {
    header("Location: index.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$page_title = "Trainer Dashboard — Boiyets Fitness Gym";

// Fetch Stats
$stats_my_clients = 0;
$stmt = $conn->prepare("SELECT COUNT(DISTINCT client_user_id) as total FROM trainer_client_assignments WHERE trainer_user_id = ? AND status = 'active'");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats_my_clients = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

$total_members = $conn->query("SELECT COUNT(*) as total FROM members WHERE status = 'active'")->fetch_assoc()['total'] ?? 0;
$workout_plans_count = $conn->query("SELECT COUNT(*) as total FROM workout_plans WHERE created_by = $user_id")->fetch_assoc()['total'] ?? 0;
$meal_plans_count = $conn->query("SELECT COUNT(*) as total FROM meal_plans WHERE created_by = $user_id")->fetch_assoc()['total'] ?? 0;

$pending_renewals_count = 0;
$ren_res = $conn->query("SELECT COUNT(*) as total FROM membership_renewal_requests WHERE (trainer_id = $user_id OR trainer_id IS NULL) AND status = 'pending'");
if ($ren_res) {
    $pending_renewals_count = $ren_res->fetch_assoc()['total'] ?? 0;
}

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_action'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = $_POST['booking_action'] === 'confirm' ? 'confirmed' : 'cancelled';
    $stmt_up = $conn->prepare("UPDATE trainer_bookings SET status = ? WHERE id = ? AND trainer_user_id = ?");
    if ($stmt_up) {
        $stmt_up->bind_param("sii", $new_status, $booking_id, $user_id);
        $stmt_up->execute();
        $stmt_up->close();
    }
}

// Fetch Trainer Session Bookings
$trainer_bookings_result = $conn->query("
    SELECT tb.*, u.full_name as client_name, u.email as client_email 
    FROM trainer_bookings tb 
    JOIN users u ON tb.client_user_id = u.id 
    WHERE tb.trainer_user_id = $user_id 
    ORDER BY tb.booking_date ASC, tb.start_time ASC 
    LIMIT 10
");

// Fetch Recent Assigned Clients
$assigned_clients = $conn->query("
    SELECT u.id as client_u_id, u.full_name, u.email, m.membership_plan, m.status, m.id as member_id 
    FROM trainer_client_assignments tca 
    JOIN users u ON tca.client_user_id = u.id 
    LEFT JOIN members m ON u.id = m.user_id 
    WHERE tca.trainer_user_id = $user_id AND tca.status = 'active' 
    ORDER BY tca.assigned_date DESC 
    LIMIT 6
");

// Fetch Recent Renewal Requests
$recent_renewals = $conn->query("
    SELECT mr.*, m.full_name as member_name 
    FROM membership_renewal_requests mr 
    LEFT JOIN members m ON mr.member_id = m.id 
    WHERE (mr.trainer_id = $user_id OR mr.trainer_id IS NULL) 
    ORDER BY mr.created_at DESC 
    LIMIT 4
");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Greeting Banner -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="layout-dashboard" style="color: var(--accent);"></i>
        Fitness Trainer Command Portal
      </h1>
      <p class="gym-page-subtitle">Welcome back, <strong>Coach <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></strong>! Monitor assigned clients, track routines, and manage diet programs.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
      <a href="trainerworkout.php" class="gym-btn gym-btn-yellow">
        <i data-lucide="dumbbell"></i> Workout Routines
      </a>
      <a href="trainermealplan.php" class="gym-btn gym-btn-outline">
        <i data-lucide="utensils"></i> Diet Plans
      </a>
      <a href="trainermanageqrcodes.php" class="gym-btn gym-btn-outline" style="color: #60a5fa !important; border-color: rgba(96, 165, 250, 0.3) !important;">
        <i data-lucide="qr-code"></i> QR Codes
      </a>
    </div>
  </div>

  <!-- 4 High-Impact KPI Stat Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Assigned Clients</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo number_format($stats_my_clients); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Active personal trainees</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="users"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Workout Plans</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo number_format($workout_plans_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Created exercise routines</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="dumbbell"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Nutrition Plans</div>
        <div class="gym-stat-number" style="color: #c084fc;"><?php echo number_format($meal_plans_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Created diet templates</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(192, 132, 252, 0.15); color: #c084fc; border-color: rgba(192, 132, 252, 0.3);">
        <i data-lucide="utensils"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Pending Renewals</div>
        <div class="gym-stat-number" style="color: #f59e0b;"><?php echo number_format($pending_renewals_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Awaiting verification</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="refresh-cw"></i>
      </div>
    </div>
  </div>

  <!-- 2-Column Asymmetric Workspace -->
  <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    
    <!-- LEFT COLUMN -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
      
      <!-- Assigned Clients Card -->
      <div class="gym-card">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 10px;">
          <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
            <i data-lucide="users" style="color: var(--accent);"></i>
            My Assigned Trainees (<?php echo $stats_my_clients; ?>)
          </h2>
          <a href="clientprogress.php" style="color: var(--accent); text-decoration: none; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
            Client Progress Logs <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
          </a>
        </div>

        <div class="gym-table-wrapper" style="margin-bottom: 0;">
          <table class="gym-table">
            <thead>
              <tr>
                <th>Trainee Name</th>
                <th>Email</th>
                <th>Membership Plan</th>
                <th>Status</th>
                <th style="text-align: center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($assigned_clients && $assigned_clients->num_rows > 0): ?>
                <?php while ($c = $assigned_clients->fetch_assoc()): ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($c['full_name']); ?></td>
                    <td style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo htmlspecialchars($c['email']); ?></td>
                    <td><span class="gym-badge gym-badge-info" style="text-transform: capitalize;"><?php echo htmlspecialchars($c['membership_plan'] ?? 'Standard'); ?></span></td>
                    <td><span class="gym-badge gym-badge-active">ACTIVE</span></td>
                    <td>
                      <div style="display: flex; gap: 6px; justify-content: center;">
                        <a href="chat.php?user_id=<?php echo $c['client_u_id']; ?>" class="gym-btn gym-btn-outline" style="min-height: 30px !important; padding: 3px 8px !important; font-size: 0.75rem !important;" title="Send Direct Message">
                          <i data-lucide="message-square" style="width: 12px; height: 12px;"></i> Chat
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 2.5rem 1rem;">
                    <i data-lucide="user-x" style="width: 38px; height: 38px; margin: 0 auto 0.5rem; color: #334155; display: block;"></i>
                    No clients assigned yet. Contact gym management for assignments.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Client Session Bookings Card -->
      <div class="gym-card" style="margin-top: 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
            <i data-lucide="calendar-check" style="color: var(--accent);"></i>
            Upcoming Client Session Bookings
          </h2>
        </div>

        <div class="gym-table-wrapper" style="margin-bottom: 0;">
          <table class="gym-table">
            <thead>
              <tr>
                <th>Client Name</th>
                <th>Session Type</th>
                <th>Date & Time</th>
                <th>Notes</th>
                <th>Status</th>
                <th style="text-align: center;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($trainer_bookings_result && $trainer_bookings_result->num_rows > 0): ?>
                <?php while ($tb = $trainer_bookings_result->fetch_assoc()): ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($tb['client_name']); ?></td>
                    <td style="color: var(--text-secondary); font-size: 0.88rem;"><?php echo htmlspecialchars($tb['session_type']); ?></td>
                    <td style="color: var(--text-dim); font-size: 0.85rem;">
                      <?php echo date('M j, Y', strtotime($tb['booking_date'])); ?> @ <?php echo date('g:i A', strtotime($tb['start_time'])); ?>
                    </td>
                    <td style="color: var(--text-secondary); font-size: 0.82rem; max-width: 200px;"><?php echo htmlspecialchars($tb['notes'] ?: '-'); ?></td>
                    <td>
                      <?php
                        $b_badge = '<span class="gym-badge gym-badge-pending">Pending</span>';
                        if ($tb['status'] === 'confirmed') $b_badge = '<span class="gym-badge gym-badge-active">Confirmed</span>';
                        if ($tb['status'] === 'cancelled') $b_badge = '<span class="gym-badge gym-badge-inactive">Cancelled</span>';
                        echo $b_badge;
                      ?>
                    </td>
                    <td>
                      <?php if ($tb['status'] === 'pending'): ?>
                        <form method="POST" style="display: flex; gap: 6px; justify-content: center; margin: 0;">
                          <input type="hidden" name="booking_id" value="<?php echo $tb['id']; ?>">
                          <button type="submit" name="booking_action" value="confirm" class="gym-btn gym-btn-yellow" style="min-height: 28px !important; padding: 2px 8px !important; font-size: 0.75rem !important;">
                            Confirm
                          </button>
                          <button type="submit" name="booking_action" value="cancel" class="gym-btn gym-btn-outline" style="min-height: 28px !important; padding: 2px 8px !important; font-size: 0.75rem !important; color: #f87171 !important; border-color: rgba(239,68,68,0.3) !important;">
                            Cancel
                          </button>
                        </form>
                      <?php else: ?>
                        <span style="color: var(--text-dim); font-size: 0.78rem; text-align: center; display: block;">No actions</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 2rem 1rem;">
                    No client session requests scheduled.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pending Membership Renewals Card -->
      <div class="gym-card">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
            <i data-lucide="refresh-cw" style="color: #f59e0b;"></i>
            Recent Membership Renewal Submissions
          </h2>
          <a href="trainer_renewal_requests.php" style="color: #f59e0b; text-decoration: none; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
            View All Requests <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
          </a>
        </div>

        <div class="gym-table-wrapper" style="margin-bottom: 0;">
          <table class="gym-table">
            <thead>
              <tr>
                <th>Member</th>
                <th>Plan Requested</th>
                <th>Amount</th>
                <th>Payment</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($recent_renewals && $recent_renewals->num_rows > 0): ?>
                <?php while ($r = $recent_renewals->fetch_assoc()): ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($r['member_name'] ?? 'Client'); ?></td>
                    <td><span class="gym-badge gym-badge-info" style="text-transform: capitalize;"><?php echo htmlspecialchars($r['plan_type']); ?></span></td>
                    <td style="font-weight: 700; color: #4ade80;">₱<?php echo number_format($r['amount']); ?></td>
                    <td style="text-transform: capitalize; color: var(--text-secondary);"><?php echo htmlspecialchars($r['payment_method']); ?></td>
                    <td>
                      <?php if ($r['status'] === 'pending'): ?>
                        <span class="gym-badge gym-badge-pending">Pending</span>
                      <?php elseif ($r['status'] === 'completed'): ?>
                        <span class="gym-badge gym-badge-active">Completed</span>
                      <?php else: ?>
                        <span class="gym-badge gym-badge-inactive">Rejected</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 2rem;">No recent renewal requests.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- RIGHT COLUMN: TRAINER TOOLKIT -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
      
      <!-- Quick Actions Toolkit Card -->
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
          <i data-lucide="zap" style="color: var(--accent);"></i>
          Trainer Tools & Actions
        </h2>

        <div style="display: flex; flex-direction: column; gap: 10px;">
          <a href="trainerworkout.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start; text-align: left; padding: 12px 14px;">
            <i data-lucide="dumbbell" style="color: var(--accent); width: 18px; height: 18px;"></i>
            <span>Create & Assign Workout Plans</span>
          </a>

          <a href="trainermealplan.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start; text-align: left; padding: 12px 14px;">
            <i data-lucide="utensils" style="color: #c084fc; width: 18px; height: 18px;"></i>
            <span>Design Nutrition & Diet Plans</span>
          </a>

          <a href="clientprogress.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start; text-align: left; padding: 12px 14px;">
            <i data-lucide="trending-up" style="color: #4ade80; width: 18px; height: 18px;"></i>
            <span>Record Client Weight Logs</span>
          </a>

          <a href="trainermanageqrcodes.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start; text-align: left; padding: 12px 14px;">
            <i data-lucide="qr-code" style="color: #60a5fa; width: 18px; height: 18px;"></i>
            <span>Manage Member QR Code Passes</span>
          </a>

          <a href="trainer_equipment_monitoring.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start; text-align: left; padding: 12px 14px;">
            <i data-lucide="wrench" style="color: #f59e0b; width: 18px; height: 18px;"></i>
            <span>Inspect Equipment & Facilities</span>
          </a>

          <a href="feedbackstrainer.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start; text-align: left; padding: 12px 14px;">
            <i data-lucide="message-square" style="color: #f87171; width: 18px; height: 18px;"></i>
            <span>Feedback & Incident Reports</span>
          </a>

          <a href="chat.php" class="gym-btn gym-btn-yellow" style="justify-content: center; padding: 12px 14px; margin-top: 6px;">
            <i data-lucide="messages-square"></i> Open Live Messages
          </a>
        </div>
      </div>

      <!-- Equipment Alerts Box -->
      <div class="gym-card" style="background: rgba(245, 158, 11, 0.06); border-color: rgba(245, 158, 11, 0.25);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
          <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.05rem; color: #f59e0b; margin: 0; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="alert-triangle"></i> Maintenance Alerts
          </h3>
          <span style="font-size: 0.85rem; font-weight: 800; color: #f59e0b; background: rgba(245, 158, 11, 0.2); padding: 2px 8px; border-radius: 4px;">
            <?php echo $maint_equip_count; ?> Issue(s)
          </span>
        </div>
        <p style="font-size: 0.84rem; color: var(--text-secondary); margin: 0 0 12px;">
          <?php echo $maint_equip_count > 0 ? "There are {$maint_equip_count} gym machines needing maintenance or under repair." : "All gym machines and facility zones are operating normally."; ?>
        </p>
        <a href="trainer_equipment_monitoring.php" class="gym-btn gym-btn-outline" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important; color: #f59e0b !important; border-color: rgba(245, 158, 11, 0.3) !important;">
          <i data-lucide="wrench" style="width: 14px; height: 14px;"></i> Inspect Equipment Status
        </a>
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
