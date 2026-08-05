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

// Function to get workout plans
function getClientWorkoutPlans($conn, $user_id) {
    $workoutPlans = [];
    $stmt = $conn->prepare("
        SELECT wp.*, u.full_name as trainer_name 
        FROM workout_plans wp 
        JOIN members m ON wp.member_id = m.id 
        JOIN users u ON wp.created_by = u.id 
        WHERE m.user_id = ? 
        ORDER BY wp.created_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['exercises'] = json_decode($row['exercises'], true) ?? [];
            $workoutPlans[] = $row;
        }
        $stmt->close();
    }
    return $workoutPlans;
}

// Function to get today's completed exercises
function getTodayCompletedExercises($conn, $user_id) {
    $completed = [];
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT ws.completed_exercises, ws.exercise_weights, wp.id as plan_id 
        FROM workout_sessions ws 
        JOIN workout_plans wp ON ws.workout_plan_id = wp.id 
        JOIN members m ON wp.member_id = m.id 
        WHERE m.user_id = ? AND ws.session_date = ?
    ");
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $exercises = json_decode($row['completed_exercises'], true) ?? [];
            $weights = json_decode($row['exercise_weights'], true) ?? [];
            foreach ($exercises as $index => $ex) {
                $key = $row['plan_id'] . '_' . $ex;
                $completed[$key] = [
                    'completed' => true,
                    'weight' => $weights[$index] ?? null
                ];
            }
        }
        $stmt->close();
    }
    return $completed;
}

// Handle exercise completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_done'])) {
    $workout_plan_id = (int)$_POST['workout_plan_id'];
    $exercise_name = trim($_POST['exercise_name']);
    $weight_used = (float)($_POST['weight_used'] ?? 0);
    $today = date('Y-m-d');

    $stmt_m = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
    if ($stmt_m) {
        $stmt_m->bind_param("i", $logged_in_user_id);
        $stmt_m->execute();
        $member = $stmt_m->get_result()->fetch_assoc();
        $stmt_m->close();

        if ($member) {
            $member_id = $member['id'];
            $stmt_s = $conn->prepare("SELECT id, completed_exercises, exercise_weights FROM workout_sessions WHERE member_id = ? AND workout_plan_id = ? AND session_date = ?");
            $stmt_s->bind_param("iis", $member_id, $workout_plan_id, $today);
            $stmt_s->execute();
            $session = $stmt_s->get_result()->fetch_assoc();
            $stmt_s->close();

            if ($session) {
                $completed_exercises = json_decode($session['completed_exercises'], true) ?? [];
                $exercise_weights = json_decode($session['exercise_weights'], true) ?? [];
                $completed_exercises[] = $exercise_name;
                $exercise_weights[] = $weight_used;

                $stmt_u = $conn->prepare("UPDATE workout_sessions SET completed_exercises = ?, exercise_weights = ? WHERE id = ?");
                $stmt_u->bind_param("ssi", json_encode($completed_exercises), json_encode($exercise_weights), $session['id']);
                if ($stmt_u->execute()) {
                    $success_message = "Exercise marked as completed!";
                }
                $stmt_u->close();
            } else {
                $completed_exercises = json_encode([$exercise_name]);
                $exercise_weights = json_encode([$weight_used]);
                $stmt_i = $conn->prepare("INSERT INTO workout_sessions (member_id, workout_plan_id, session_date, completed_exercises, exercise_weights) VALUES (?, ?, ?, ?, ?)");
                $stmt_i->bind_param("iisss", $member_id, $workout_plan_id, $today, $completed_exercises, $exercise_weights);
                if ($stmt_i->execute()) {
                    $success_message = "Exercise marked as completed!";
                }
                $stmt_i->close();
            }
        }
    }
}

// Handle exercise removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_exercise'])) {
    $workout_plan_id = (int)$_POST['workout_plan_id'];
    $exercise_name = trim($_POST['exercise_name']);
    $today = date('Y-m-d');

    $stmt_m = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
    if ($stmt_m) {
        $stmt_m->bind_param("i", $logged_in_user_id);
        $stmt_m->execute();
        $member = $stmt_m->get_result()->fetch_assoc();
        $stmt_m->close();

        if ($member) {
            $member_id = $member['id'];
            $stmt_s = $conn->prepare("SELECT id, completed_exercises, exercise_weights FROM workout_sessions WHERE member_id = ? AND workout_plan_id = ? AND session_date = ?");
            $stmt_s->bind_param("iis", $member_id, $workout_plan_id, $today);
            $session = $stmt_s->get_result()->fetch_assoc();
            $stmt_s->close();

            if ($session) {
                $completed_exercises = json_decode($session['completed_exercises'], true) ?? [];
                $exercise_weights = json_decode($session['exercise_weights'], true) ?? [];
                $idx = array_search($exercise_name, $completed_exercises);
                if ($idx !== false) {
                    unset($completed_exercises[$idx]);
                    unset($exercise_weights[$idx]);
                    $completed_exercises = array_values($completed_exercises);
                    $exercise_weights = array_values($exercise_weights);

                    $stmt_u = $conn->prepare("UPDATE workout_sessions SET completed_exercises = ?, exercise_weights = ? WHERE id = ?");
                    $stmt_u->bind_param("ssi", json_encode($completed_exercises), json_encode($exercise_weights), $session['id']);
                    if ($stmt_u->execute()) {
                        $success_message = "Exercise unmarked!";
                    }
                    $stmt_u->close();
                }
            }
        }
    }
}

// Handle printable checklist export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_checklist'])) {
    $workout_plan_id = (int)$_POST['workout_plan_id'];
    $stmt = $conn->prepare("
        SELECT wp.*, m.full_name as client_name, u.full_name as trainer_name 
        FROM workout_plans wp 
        JOIN members m ON wp.member_id = m.id 
        JOIN users u ON wp.created_by = u.id 
        WHERE wp.id = ? AND m.user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $workout_plan_id, $logged_in_user_id);
        $stmt->execute();
        $plan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($plan) {
            $plan['exercises'] = json_decode($plan['exercises'], true) ?? [];
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="workout_checklist_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $plan['plan_name']) . '_' . date('Y-m-d') . '.html"');
            
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Workout Checklist — ' . htmlspecialchars($plan['plan_name']) . '</title>';
            echo '<style>body{font-family:sans-serif;padding:30px;color:#111;background:#fff;}h1{margin:0;color:#e8a012;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ccc;padding:10px;text-align:left;}th{background:#f4f4f4;}</style></head><body>';
            echo '<h1>BOIYETS FITNESS GYM</h1><h2>' . htmlspecialchars($plan['plan_name']) . '</h2>';
            echo '<p><strong>Trainer:</strong> ' . htmlspecialchars($plan['trainer_name']) . ' | <strong>Date:</strong> ' . date('F j, Y') . '</p>';
            echo '<table><thead><tr><th>#</th><th>Exercise Name</th><th>Sets</th><th>Reps</th><th>Rest</th><th>Weight Logged</th><th>Completed</th></tr></thead><tbody>';
            foreach ($plan['exercises'] as $i => $ex) {
                echo '<tr><td>' . ($i + 1) . '</td><td><strong>' . htmlspecialchars($ex['name']) . '</strong></td><td>' . ($ex['sets'] ?? '-') . '</td><td>' . ($ex['reps'] ?? '-') . '</td><td>' . ($ex['rest'] ?? '-') . 's</td><td>_______ kg</td><td>[  ]</td></tr>';
            }
            echo '</tbody></table><script>window.onload=function(){window.print();}</script></body></html>';
            exit();
        }
    }
}

$client = getClientDetails($conn, $logged_in_user_id);
$workoutPlans = getClientWorkoutPlans($conn, $logged_in_user_id);
$completedToday = getTodayCompletedExercises($conn, $logged_in_user_id);

$page_title = "My Workout Routines — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="dumbbell" style="color: var(--accent);"></i>
        My Assigned Workout Routines
      </h1>
      <p class="gym-page-subtitle">Track your assigned exercise splits, log completed sets and weights, and export printable workout checklists.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="nutritionplansclient.php" class="gym-btn gym-btn-yellow">
        <i data-lucide="utensils"></i> My Nutrition Plans
      </a>
    </div>
  </div>

  <?php if (!empty($success_message)): ?>
    <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="check-circle-2" style="width: 18px; height: 18px; color: #22c55e;"></i>
      <span><?php echo htmlspecialchars($success_message); ?></span>
    </div>
  <?php endif; ?>

  <!-- 3 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Assigned Workout Routines</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo count($workoutPlans); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Custom plans from coach</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="dumbbell"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Exercises Completed Today</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo count($completedToday); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;"><?php echo date('M j, Y'); ?></div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="check-circle-2"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Assigned Coach</div>
        <div class="gym-stat-number" style="font-size: 1.2rem; color: #60a5fa; margin-top: 4px;">
          <?php echo !empty($workoutPlans[0]['trainer_name']) ? htmlspecialchars($workoutPlans[0]['trainer_name']) : 'Gym Staff'; ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Personal Fitness Instructor</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="user-check"></i>
      </div>
    </div>
  </div>

  <!-- Workout Plans Roster -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <?php if (!empty($workoutPlans)): ?>
      <?php foreach ($workoutPlans as $plan): ?>
        <div class="gym-card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 1rem;">
            <div>
              <h2 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.25rem; color: var(--text-primary); margin: 0 0 6px;">
                <?php echo htmlspecialchars($plan['plan_name']); ?>
              </h2>
              <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; font-size: 0.84rem; color: var(--text-secondary);">
                <span>Schedule: <strong style="color: var(--accent); text-transform: capitalize;"><?php echo htmlspecialchars($plan['schedule']); ?></strong></span>
                <span>Instructor: <strong>Coach <?php echo htmlspecialchars($plan['trainer_name']); ?></strong></span>
                <span>Assigned: <?php echo date('M j, Y', strtotime($plan['created_at'])); ?></span>
              </div>
              <?php if (!empty($plan['description'])): ?>
                <p style="font-size: 0.88rem; color: var(--text-dim); margin: 8px 0 0;"><?php echo htmlspecialchars($plan['description']); ?></p>
              <?php endif; ?>
            </div>

            <form method="POST" style="margin: 0;">
              <input type="hidden" name="workout_plan_id" value="<?php echo $plan['id']; ?>">
              <button type="submit" name="export_checklist" class="gym-btn gym-btn-outline" style="min-height: 34px !important; padding: 4px 12px !important; font-size: 0.8rem !important; color: #60a5fa !important; border-color: rgba(96, 165, 250, 0.3) !important;">
                <i data-lucide="download" style="width: 14px; height: 14px;"></i> Export Checklist
              </button>
            </form>
          </div>

          <!-- Exercises List Table -->
          <div class="gym-table-wrapper" style="margin-bottom: 0;">
            <table class="gym-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Exercise Name & Notes</th>
                  <th>Sets</th>
                  <th>Reps</th>
                  <th>Rest Interval</th>
                  <th>Log Weight (kg)</th>
                  <th style="text-align: center;">Daily Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($plan['exercises'] as $idx => $ex): ?>
                  <?php
                  $ex_key = $plan['id'] . '_' . $ex['name'];
                  $is_done = isset($completedToday[$ex_key]);
                  $logged_weight = $is_done ? ($completedToday[$ex_key]['weight'] ?? '') : '';
                  ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--accent);"><?php echo $idx + 1; ?></td>
                    <td>
                      <div style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($ex['name']); ?></div>
                      <?php if (!empty($ex['notes'])): ?>
                        <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 2px;"><?php echo htmlspecialchars($ex['notes']); ?></div>
                      <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($ex['sets'] ?? '-'); ?></strong> sets</td>
                    <td><strong><?php echo htmlspecialchars($ex['reps'] ?? '-'); ?></strong> reps</td>
                    <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($ex['rest'] ?? '-'); ?> sec</td>
                    <td>
                      <?php if ($is_done): ?>
                        <strong style="color: #4ade80;"><?php echo $logged_weight ? htmlspecialchars($logged_weight) . ' kg' : 'Done'; ?></strong>
                      <?php else: ?>
                        <form method="POST" style="display: flex; gap: 6px; margin: 0;" id="form_<?php echo $plan['id'] . '_' . $idx; ?>">
                          <input type="hidden" name="workout_plan_id" value="<?php echo $plan['id']; ?>">
                          <input type="hidden" name="exercise_name" value="<?php echo htmlspecialchars($ex['name']); ?>">
                          <input type="number" name="weight_used" step="0.5" placeholder="kg" class="gym-form-control" style="width: 76px; height: 32px; font-size: 0.8rem; padding: 2px 6px; margin: 0;" required>
                          <button type="submit" name="mark_done" class="gym-btn gym-btn-yellow" style="min-height: 32px !important; padding: 2px 10px !important; font-size: 0.75rem !important;">
                            Log Done
                          </button>
                        </form>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                      <?php if ($is_done): ?>
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                          <span class="gym-badge gym-badge-active">
                            <i data-lucide="check-circle-2" style="width: 12px; height: 12px; display: inline;"></i> Completed
                          </span>
                          <form method="POST" style="margin: 0;">
                            <input type="hidden" name="workout_plan_id" value="<?php echo $plan['id']; ?>">
                            <input type="hidden" name="exercise_name" value="<?php echo htmlspecialchars($ex['name']); ?>">
                            <button type="submit" name="remove_exercise" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.75rem; text-decoration: underline;">
                              Undo
                            </button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span class="gym-badge gym-badge-pending">Pending</span>
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
        <i data-lucide="dumbbell" style="width: 48px; height: 48px; margin: 0 auto 1rem; color: #334155; display: block;"></i>
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 700; color: var(--text-secondary); margin: 0;">No Workout Plans Assigned Yet</h3>
        <p style="font-size: 0.88rem; max-width: 400px; margin: 0.5rem auto 0; color: var(--text-dim);">Your assigned instructor will design personalized exercise routines for you shortly.</p>
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
