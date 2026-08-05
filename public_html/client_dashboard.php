<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';
require_once __DIR__ . '/notification_functions.php';

// Authentication guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    header("Location: index.php");
    exit();
}

checkAndTriggerExpiryAlerts($conn);

$user_id = (int)$_SESSION['user_id'];
$page_title = "Member Portal — Boiyets Fitness Gym";

// Fetch member details
$stmt = $conn->prepare("
    SELECT m.*, u.username, u.email 
    FROM users u 
    LEFT JOIN members m ON u.id = m.user_id 
    WHERE u.id = ?
");
$member = null;
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$days_until_expiry = 999;
if (!empty($member['expiry_date'])) {
    $days_until_expiry = (int)floor((strtotime($member['expiry_date']) - time()) / 86400);
}
?>

$member_id = $member['id'] ?? 0;

// Get attendance count
$my_checkins = 0;
if ($member_id) {
    $res = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE member_id = $member_id");
    if ($res) $my_checkins = $res->fetch_assoc()['total'] ?? 0;
}

// Get assigned workouts
$my_workouts = null;
if ($member_id) {
    $my_workouts = $conn->query("SELECT * FROM workout_plans WHERE member_id = $member_id ORDER BY created_at DESC LIMIT 5");
}

// Get assigned meal plans
$my_meals = null;
if ($member_id) {
    $my_meals = $conn->query("SELECT * FROM meal_plans WHERE member_id = $member_id ORDER BY created_at DESC LIMIT 5");
}

// Get assigned trainer info
$assigned_trainer = null;
if ($user_id) {
    $t_res = $conn->query("
        SELECT u.id as trainer_u_id, u.full_name, u.email 
        FROM trainer_client_assignments tca 
        JOIN users u ON tca.trainer_user_id = u.id 
        WHERE tca.client_user_id = $user_id AND tca.status = 'active' 
        LIMIT 1
    ");
    if ($t_res && $t_res->num_rows > 0) {
        $assigned_trainer = $t_res->fetch_assoc();
    }
}

// Expiry calculation
$expiry_date = !empty($member['expiry_date']) ? strtotime($member['expiry_date']) : null;
$days_left = null;
if ($expiry_date) {
    $days_left = ceil(($expiry_date - time()) / 86400);
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <?php if ($days_until_expiry <= 7 && !empty($member['expiry_date'])): ?>
    <div class="gym-alert gym-alert-danger" style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); padding: 14px 18px; border-radius: var(--radius-md);">
      <div style="display: flex; align-items: center; gap: 12px;">
        <i data-lucide="alert-triangle" style="color: #ef4444; width: 22px; height: 22px;"></i>
        <div>
          <strong style="color: #f87171; font-size: 0.98rem;">Membership Expiring Soon!</strong>
          <p style="margin: 0; color: var(--text-secondary); font-size: 0.85rem;">Your gym pass expires on <strong><?php echo htmlspecialchars($member['expiry_date']); ?></strong> (<?php echo $days_until_expiry <= 0 ? 'Expires today!' : $days_until_expiry . ' days left'; ?>).</p>
        </div>
      </div>
      <a href="membershipclient.php" class="gym-btn gym-btn-yellow" style="min-height: 36px; padding: 0 14px; font-size: 0.82rem;">
        <i data-lucide="refresh-cw"></i> Renew Membership Now
      </a>
    </div>
  <?php endif; ?>

  <!-- Hero Greeting Banner -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="user" style="color: var(--accent);"></i>
        Member Fitness Portal
      </h1>
      <p class="gym-page-subtitle">
        Welcome back, <strong><?php echo htmlspecialchars($member['full_name'] ?? $_SESSION['username']); ?></strong>! Track your gym check-ins, routines, and nutrition programs.
      </p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
      <a href="workoutplansclient.php" class="gym-btn gym-btn-yellow">
        <i data-lucide="dumbbell"></i> My Workout Plans
      </a>
      <a href="nutritionplansclient.php" class="gym-btn gym-btn-outline">
        <i data-lucide="utensils"></i> My Diet Plans
      </a>
      <a href="membershipclient.php" class="gym-btn gym-btn-outline" style="color: #4ade80 !important; border-color: rgba(74, 222, 128, 0.3) !important;">
        <i data-lucide="refresh-cw"></i> Renew Pass
      </a>
    </div>
  </div>

  <!-- 4 High-Impact KPI Stat Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Membership Plan</div>
        <div class="gym-stat-number" style="color: var(--accent-light); text-transform: capitalize; font-size: 1.5rem;">
          <?php echo htmlspecialchars($member['membership_plan'] ?? 'Standard'); ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Active subscription type</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="id-card"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Gym Check-Ins</div>
        <div class="gym-stat-number" style="color: #60a5fa;"><?php echo number_format($my_checkins); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Total attendance sessions</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="clipboard-check"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Assigned Routines</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo number_format($my_workouts ? $my_workouts->num_rows : 0); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Active workout plans</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="dumbbell"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Membership Status</div>
        <div class="gym-stat-number" style="font-size: 1.25rem; margin-top: 4px;">
          <?php if (($member['status'] ?? 'active') === 'active'): ?>
            <span class="gym-badge gym-badge-active">ACTIVE (<?php echo $days_left !== null ? ($days_left > 0 ? $days_left . 'd left' : 'Expiring today') : 'Active'; ?>)</span>
          <?php else: ?>
            <span class="gym-badge gym-badge-inactive">EXPIRED</span>
          <?php endif; ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 6px;">
          Expires: <?php echo $expiry_date ? date('M j, Y', $expiry_date) : 'N/A'; ?>
        </div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(192, 132, 252, 0.15); color: #c084fc; border-color: rgba(192, 132, 252, 0.3);">
        <i data-lucide="shield-check"></i>
      </div>
    </div>
  </div>

  <!-- 2-Column Asymmetric Workspace -->
  <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    
    <!-- LEFT COLUMN: ROUTINES & DIET PLANS -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
      
      <!-- Digital QR Code Check-in Pass Card -->
      <div class="gym-card" style="background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.95));">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
          <div>
            <h2 class="gym-card-title" style="margin: 0 0 6px; color: var(--accent); display: flex; align-items: center; gap: 10px;">
              <i data-lucide="qr-code"></i> Digital Gym Pass QR Code
            </h2>
            <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0; max-width: 420px;">
              Present this digital pass at the entrance scanner to instantly check in to Boiyets Fitness Gym.
            </p>
          </div>

          <?php if (!empty($member['qr_code_path']) && file_exists($member['qr_code_path'])): ?>
            <div style="display: flex; flex-direction: column; align-items: center; gap: 6px; background: #fff; padding: 10px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.5);">
              <img src="<?php echo htmlspecialchars($member['qr_code_path']); ?>" alt="Digital QR Pass" style="width: 110px; height: 110px; border-radius: 6px;">
              <a href="<?php echo htmlspecialchars($member['qr_code_path']); ?>" download style="font-size: 0.75rem; color: #0f172a; font-weight: 800; text-decoration: underline;">Download Pass</a>
            </div>
          <?php else: ?>
            <div style="text-align: center; background: rgba(255,255,255,0.03); padding: 14px 20px; border-radius: var(--radius-sm); border: 1px dashed var(--border);">
              <i data-lucide="qr-code" style="width: 36px; height: 36px; color: var(--text-dim); margin-bottom: 4px;"></i>
              <div style="font-size: 0.82rem; color: var(--text-dim);">No QR Pass Generated</div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Assigned Workout Routines Table Card -->
      <div class="gym-card">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 10px;">
          <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
            <i data-lucide="dumbbell" style="color: var(--accent);"></i>
            My Assigned Workout Routines
          </h2>
          <a href="workoutplansclient.php" style="color: var(--accent); text-decoration: none; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
            View All Workouts <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
          </a>
        </div>

        <div class="gym-table-wrapper" style="margin-bottom: 0;">
          <table class="gym-table">
            <thead>
              <tr>
                <th>Routine Name</th>
                <th>Schedule</th>
                <th>Description</th>
                <th>Date Assigned</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($my_workouts && $my_workouts->num_rows > 0): ?>
                <?php while ($w = $my_workouts->fetch_assoc()): ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($w['plan_name']); ?></td>
                    <td><span class="gym-badge gym-badge-info" style="text-transform: capitalize;"><?php echo htmlspecialchars($w['schedule'] ?? 'Weekly'); ?></span></td>
                    <td style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo !empty($w['description']) ? htmlspecialchars($w['description']) : '-'; ?></td>
                    <td style="color: var(--text-dim); font-size: 0.82rem;"><?php echo date('M j, Y', strtotime($w['created_at'])); ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" style="text-align: center; color: var(--text-dim); padding: 2.5rem 1rem;">
                    <i data-lucide="dumbbell" style="width: 38px; height: 38px; margin: 0 auto 0.5rem; color: #334155; display: block;"></i>
                    No workout routines assigned yet. Your coach will upload routines soon!
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Assigned Nutrition Plans Card -->
      <div class="gym-card">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
            <i data-lucide="utensils" style="color: #c084fc;"></i>
            My Assigned Nutrition Plans
          </h2>
          <a href="nutritionplansclient.php" style="color: #c084fc; text-decoration: none; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
            View All Diets <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
          </a>
        </div>

        <div class="gym-table-wrapper" style="margin-bottom: 0;">
          <table class="gym-table">
            <thead>
              <tr>
                <th>Plan Title</th>
                <th>Daily Target</th>
                <th>Guidelines</th>
                <th>Date Assigned</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($my_meals && $my_meals->num_rows > 0): ?>
                <?php while ($m = $my_meals->fetch_assoc()): ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($m['plan_name']); ?></td>
                    <td style="font-weight: 700; color: #4ade80;">🔥 <?php echo number_format($m['daily_calories'] ?? 2000); ?> kcal</td>
                    <td style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo !empty($m['description']) ? htmlspecialchars($m['description']) : '-'; ?></td>
                    <td style="color: var(--text-dim); font-size: 0.82rem;"><?php echo date('M j, Y', strtotime($m['created_at'])); ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" style="text-align: center; color: var(--text-dim); padding: 2.5rem 1rem;">
                    <i data-lucide="utensils" style="width: 38px; height: 38px; margin: 0 auto 0.5rem; color: #334155; display: block;"></i>
                    No nutrition plans assigned yet. Request a customized meal plan from your trainer.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- RIGHT COLUMN: SHORTCUTS & COACH INFO -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
      
      <!-- Member Shortcuts Card -->
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
          <i data-lucide="zap" style="color: var(--accent);"></i>
          Member Shortcuts
        </h2>

        <div style="display: flex; flex-direction: column; gap: 10px;">
          <a href="workoutplansclient.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start; padding: 12px 14px;">
            <i data-lucide="dumbbell" style="color: var(--accent); width: 18px; height: 18px;"></i>
            <span>View Workout Plans</span>
          </a>

          <a href="nutritionplansclient.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start; padding: 12px 14px;">
            <i data-lucide="utensils" style="color: #c084fc; width: 18px; height: 18px;"></i>
            <span>Nutrition & Diet Guidelines</span>
          </a>

          <a href="myprogressclient.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start; padding: 12px 14px;">
            <i data-lucide="trending-up" style="color: #4ade80; width: 18px; height: 18px;"></i>
            <span>Track Weight Progress</span>
          </a>

          <a href="membershipclient.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start; padding: 12px 14px;">
            <i data-lucide="refresh-cw" style="color: #60a5fa; width: 18px; height: 18px;"></i>
            <span>Renew Membership Plan</span>
          </a>

          <a href="chat.php" class="gym-btn gym-btn-yellow" style="justify-content: center; padding: 12px 14px; margin-top: 6px;">
            <i data-lucide="messages-square"></i> Chat with Coach
          </a>
        </div>
      </div>

      <!-- Assigned Fitness Coach Card -->
      <div class="gym-card">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.05rem; color: var(--text-primary); margin: 0 0 12px; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="user-check" style="color: #60a5fa;"></i>
          Assigned Personal Coach
        </h3>

        <?php if ($assigned_trainer): ?>
          <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px;">
            <div style="font-weight: 700; font-size: 1rem; color: var(--text-primary); margin-bottom: 2px;">
              Coach <?php echo htmlspecialchars($assigned_trainer['full_name']); ?>
            </div>
            <div style="font-size: 0.82rem; color: var(--text-dim); margin-bottom: 12px;">
              <?php echo htmlspecialchars($assigned_trainer['email']); ?>
            </div>
            <a href="chat.php?user_id=<?php echo $assigned_trainer['trainer_u_id']; ?>" class="gym-btn gym-btn-outline" style="width: 100%; min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important; color: #60a5fa !important; border-color: rgba(96, 165, 250, 0.3) !important;">
              <i data-lucide="message-square" style="width: 14px; height: 14px;"></i> Direct Message
            </a>
          </div>
        <?php else: ?>
          <div style="text-align: center; color: var(--text-dim); padding: 1.5rem 1rem; background: var(--bg-surface); border-radius: var(--radius-sm); border: 1px solid var(--border);">
            <i data-lucide="user-plus" style="width: 32px; height: 32px; margin: 0 auto 0.5rem; color: #334155; display: block;"></i>
            <p style="font-size: 0.85rem; margin: 0;">No personal coach assigned yet. Contact reception to be paired with a trainer.</p>
          </div>
        <?php endif; ?>
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
