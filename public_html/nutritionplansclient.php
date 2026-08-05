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
$success_message = '';
$error_message = '';
$info_message = '';

// Function to get client details
function getClientDetails($conn, $user_id) {
    $stmt = $conn->prepare("SELECT m.* FROM members m JOIN users u ON m.user_id = u.id WHERE u.id = ? AND m.member_type = 'client'");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row;
    }
    return null;
}

// Function to get meal plans
function getClientMealPlans($conn, $user_id) {
    $mealPlans = [];
    $stmt = $conn->prepare("
        SELECT mp.*, u.full_name as trainer_name 
        FROM meal_plans mp 
        JOIN members m ON mp.member_id = m.id 
        JOIN users u ON mp.created_by = u.id 
        WHERE m.user_id = ? 
        ORDER BY mp.created_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['meals'] = json_decode($row['meals'], true) ?? [];
            $mealPlans[] = $row;
        }
        $stmt->close();
    }
    return $mealPlans;
}

// Function to get today's completed meals
function getTodayCompletedMeals($conn, $user_id) {
    $today = date('Y-m-d');
    $completedMeals = [];
    $stmt_m = $conn->prepare("SELECT m.id FROM members m JOIN users u ON m.user_id = u.id WHERE u.id = ?");
    if ($stmt_m) {
        $stmt_m->bind_param("i", $user_id);
        $stmt_m->execute();
        $member = $stmt_m->get_result()->fetch_assoc();
        $stmt_m->close();

        if ($member) {
            $member_id = $member['id'];
            $stmt = $conn->prepare("SELECT completed_meals FROM nutrition_sessions WHERE member_id = ? AND session_date = ? ORDER BY created_at DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("is", $member_id, $today);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $completedMeals = json_decode($row['completed_meals'], true) ?: [];
                }
                $stmt->close();
            }
        }
    }
    return $completedMeals;
}

// Handle meal completion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_meal_done'])) {
    $meal_plan_id = (int)$_POST['meal_plan_id'];
    $meal_name = trim($_POST['meal_name']);
    $today = date('Y-m-d');

    $stmt_m = $conn->prepare("SELECT m.id FROM members m JOIN users u ON m.user_id = u.id WHERE u.id = ?");
    if ($stmt_m) {
        $stmt_m->bind_param("i", $logged_in_user_id);
        $stmt_m->execute();
        $member = $stmt_m->get_result()->fetch_assoc();
        $stmt_m->close();

        if ($member) {
            $member_id = $member['id'];
            $stmt_s = $conn->prepare("SELECT * FROM nutrition_sessions WHERE member_id = ? AND meal_plan_id = ? AND session_date = ?");
            $stmt_s->bind_param("iis", $member_id, $meal_plan_id, $today);
            $stmt_s->execute();
            $existing_session = $stmt_s->get_result()->fetch_assoc();
            $stmt_s->close();

            if ($existing_session) {
                $completed_meals = json_decode($existing_session['completed_meals'], true) ?: [];
                if (!in_array($meal_name, $completed_meals)) {
                    $completed_meals[] = $meal_name;
                    $stmt_u = $conn->prepare("UPDATE nutrition_sessions SET completed_meals = ? WHERE id = ?");
                    $stmt_u->bind_param("si", json_encode($completed_meals), $existing_session['id']);
                    if ($stmt_u->execute()) {
                        $success_message = "Meal '$meal_name' marked as completed!";
                    }
                    $stmt_u->close();
                } else {
                    $info_message = "Meal '$meal_name' is already marked completed for today.";
                }
            } else {
                $completed_json = json_encode([$meal_name]);
                $stmt_i = $conn->prepare("INSERT INTO nutrition_sessions (member_id, meal_plan_id, session_date, completed_meals) VALUES (?, ?, ?, ?)");
                $stmt_i->bind_param("iiss", $member_id, $meal_plan_id, $today, $completed_json);
                if ($stmt_i->execute()) {
                    $success_message = "Meal '$meal_name' marked as completed!";
                }
                $stmt_i->close();
            }
        }
    }
}

// Handle printable export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_meal_plan'])) {
    $meal_plan_id = (int)$_POST['meal_plan_id'];
    $stmt = $conn->prepare("
        SELECT mp.*, m.full_name as client_name, u.full_name as trainer_name 
        FROM meal_plans mp 
        JOIN members m ON mp.member_id = m.id 
        JOIN users u ON mp.created_by = u.id 
        WHERE mp.id = ? AND m.user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $meal_plan_id, $logged_in_user_id);
        $stmt->execute();
        $plan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($plan) {
            $plan['meals'] = json_decode($plan['meals'], true) ?? [];
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="meal_plan_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $plan['plan_name']) . '_' . date('Y-m-d') . '.html"');
            
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Nutrition Plan — ' . htmlspecialchars($plan['plan_name']) . '</title>';
            echo '<style>body{font-family:sans-serif;padding:30px;color:#111;background:#fff;}h1{margin:0;color:#e8a012;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ccc;padding:10px;text-align:left;}th{background:#f4f4f4;}</style></head><body>';
            echo '<h1>BOIYETS FITNESS GYM</h1><h2>' . htmlspecialchars($plan['plan_name']) . '</h2>';
            echo '<p><strong>Target Calories:</strong> ' . ($plan['daily_calories'] ?? 2000) . ' kcal | <strong>Trainer:</strong> ' . htmlspecialchars($plan['trainer_name']) . '</p>';
            echo '<table><thead><tr><th>#</th><th>Meal Name</th><th>Time</th><th>Calories</th><th>Description & Ingredients</th><th>Completed</th></tr></thead><tbody>';
            foreach ($plan['meals'] as $i => $m) {
                echo '<tr><td>' . ($i + 1) . '</td><td><strong>' . htmlspecialchars($m['name']) . '</strong></td><td>' . ($m['time'] ?? '-') . '</td><td>' . ($m['calories'] ?? '0') . ' kcal</td><td>' . htmlspecialchars($m['description'] ?? '-') . '</td><td>[  ]</td></tr>';
            }
            echo '</tbody></table><script>window.onload=function(){window.print();}</script></body></html>';
            exit();
        }
    }
}

$client = getClientDetails($conn, $logged_in_user_id);
$mealPlans = getClientMealPlans($conn, $logged_in_user_id);
$todayCompletedMeals = getTodayCompletedMeals($conn, $logged_in_user_id);

$totalMealsToday = 0;
$completedMealsToday = 0;
foreach ($mealPlans as $plan) {
    if (isset($plan['meals']) && is_array($plan['meals'])) {
        $totalMealsToday += count($plan['meals']);
        foreach ($plan['meals'] as $meal) {
            if (in_array($meal['name'], $todayCompletedMeals)) {
                $completedMealsToday++;
            }
        }
    }
}

$progressPercentage = $totalMealsToday > 0 ? round(($completedMealsToday / $totalMealsToday) * 100) : 0;

$page_title = "My Nutrition & Diet Plans — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="utensils" style="color: var(--accent);"></i>
        My Nutrition & Diet Plans
      </h1>
      <p class="gym-page-subtitle">Track your daily calorie goals, macros, meal breakdown schedules, and mark consumed meals.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="workoutplansclient.php" class="gym-btn gym-btn-yellow">
        <i data-lucide="dumbbell"></i> My Workout Routines
      </a>
    </div>
  </div>

  <?php if (!empty($success_message)): ?>
    <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="check-circle-2" style="width: 18px; height: 18px; color: #22c55e;"></i>
      <span><?php echo htmlspecialchars($success_message); ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($info_message)): ?>
    <div style="background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.4); color: #60a5fa; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="info" style="width: 18px; height: 18px; color: #3b82f6;"></i>
      <span><?php echo htmlspecialchars($info_message); ?></span>
    </div>
  <?php endif; ?>

  <!-- 4 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Assigned Diet Plans</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo count($mealPlans); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Custom programs from trainer</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="utensils"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Meals Completed Today</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo $completedMealsToday; ?> / <?php echo $totalMealsToday; ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;"><?php echo date('M j, Y'); ?></div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="check-circle-2"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Daily Goal Progress</div>
        <div class="gym-stat-number" style="color: #c084fc;"><?php echo $progressPercentage; ?>%</div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Calorie & meal adherence</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(192, 132, 252, 0.15); color: #c084fc; border-color: rgba(192, 132, 252, 0.3);">
        <i data-lucide="pie-chart"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Daily Calorie Goal</div>
        <div class="gym-stat-number" style="color: #f59e0b; font-size: 1.5rem;">
          🔥 <?php echo number_format($mealPlans[0]['daily_calories'] ?? 2000); ?> kcal
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Target daily energy intake</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="flame"></i>
      </div>
    </div>
  </div>

  <!-- Nutrition Plans List -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <?php if (!empty($mealPlans)): ?>
      <?php foreach ($mealPlans as $plan): ?>
        <div class="gym-card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 1rem;">
            <div>
              <h2 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.25rem; color: var(--text-primary); margin: 0 0 6px;">
                <?php echo htmlspecialchars($plan['plan_name']); ?>
              </h2>
              <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; font-size: 0.84rem; color: var(--text-secondary);">
                <span style="color: #4ade80; font-weight: 700;">🔥 <?php echo number_format($plan['daily_calories'] ?? 2000); ?> kcal / day</span>
                <span>Instructor: <strong>Coach <?php echo htmlspecialchars($plan['trainer_name']); ?></strong></span>
                <span>Assigned: <?php echo date('M j, Y', strtotime($plan['created_at'])); ?></span>
              </div>
              
              <!-- Macros Pills Bar -->
              <div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">
                <?php if (isset($plan['protein_goal'])): ?>
                  <span style="font-size: 0.78rem; background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); padding: 2px 10px; border-radius: 4px; font-weight: 700;">
                    Protein: <?php echo $plan['protein_goal']; ?>g
                  </span>
                <?php endif; ?>
                <?php if (isset($plan['carbs_goal'])): ?>
                  <span style="font-size: 0.78rem; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 2px 10px; border-radius: 4px; font-weight: 700;">
                    Carbs: <?php echo $plan['carbs_goal']; ?>g
                  </span>
                <?php endif; ?>
                <?php if (isset($plan['fat_goal'])): ?>
                  <span style="font-size: 0.78rem; background: rgba(192, 132, 252, 0.15); color: #c084fc; border: 1px solid rgba(192, 132, 252, 0.3); padding: 2px 10px; border-radius: 4px; font-weight: 700;">
                    Fats: <?php echo $plan['fat_goal']; ?>g
                  </span>
                <?php endif; ?>
              </div>

              <?php if (!empty($plan['description'])): ?>
                <p style="font-size: 0.88rem; color: var(--text-dim); margin: 8px 0 0;"><?php echo htmlspecialchars($plan['description']); ?></p>
              <?php endif; ?>
            </div>

            <form method="POST" style="margin: 0;">
              <input type="hidden" name="meal_plan_id" value="<?php echo $plan['id']; ?>">
              <button type="submit" name="export_meal_plan" class="gym-btn gym-btn-outline" style="min-height: 34px !important; padding: 4px 12px !important; font-size: 0.8rem !important; color: #60a5fa !important; border-color: rgba(96, 165, 250, 0.3) !important;">
                <i data-lucide="download" style="width: 14px; height: 14px;"></i> Export Diet Plan
              </button>
            </form>
          </div>

          <!-- Meals Table -->
          <div class="gym-table-wrapper" style="margin-bottom: 0;">
            <table class="gym-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Meal Name</th>
                  <th>Scheduled Time</th>
                  <th>Estimated Calories</th>
                  <th>Description & Preparation</th>
                  <th style="text-align: center;">Daily Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($plan['meals'] as $idx => $m): ?>
                  <?php $is_done = in_array($m['name'], $todayCompletedMeals); ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--accent);"><?php echo $idx + 1; ?></td>
                    <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($m['name']); ?></td>
                    <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($m['time'] ?? '-'); ?></td>
                    <td style="font-weight: 700; color: #4ade80;"><?php echo htmlspecialchars($m['calories'] ?? '0'); ?> kcal</td>
                    <td style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo !empty($m['description']) ? htmlspecialchars($m['description']) : '-'; ?></td>
                    <td style="text-align: center;">
                      <?php if ($is_done): ?>
                        <span class="gym-badge gym-badge-active">
                          <i data-lucide="check-circle-2" style="width: 12px; height: 12px; display: inline;"></i> Eaten Today
                        </span>
                      <?php else: ?>
                        <form method="POST" style="margin: 0;">
                          <input type="hidden" name="meal_plan_id" value="<?php echo $plan['id']; ?>">
                          <input type="hidden" name="meal_name" value="<?php echo htmlspecialchars($m['name']); ?>">
                          <button type="submit" name="mark_meal_done" class="gym-btn gym-btn-yellow" style="min-height: 30px !important; padding: 3px 10px !important; font-size: 0.75rem !important;">
                            Log Eaten
                          </button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="gym-card" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
        <i data-lucide="utensils" style="width: 48px; height: 48px; margin: 0 auto 1rem; color: #334155; display: block;"></i>
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-secondary); margin: 0;">No Nutrition Plans Assigned Yet</h3>
        <p style="font-size: 0.88rem; max-width: 400px; margin: 0.5rem auto 0; color: var(--text-dim);">Your assigned instructor will design personalized diet guidelines and macro targets for you shortly.</p>
      </div>
    <?php endif; ?>
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
