<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'trainer') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$current_trainer_id = $_SESSION['user_id'];

// Function to get workout plans
function getWorkoutPlans($conn, $trainer_id = null) {
    $workoutPlans = [];
    $sql = "SELECT wp.*, m.full_name, m.fitness_goals 
            FROM workout_plans wp 
            JOIN members m ON wp.member_id = m.id 
            WHERE m.member_type = 'client'";
    
    if ($trainer_id) {
        $sql .= " AND wp.created_by = ?";
    }
    $sql .= " ORDER BY wp.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($trainer_id) {
        $stmt->bind_param("i", $trainer_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['exercises'] = json_decode($row['exercises'], true) ?: [];
        $workoutPlans[] = $row;
    }
    return $workoutPlans;
}

// Function to get workout templates
function getWorkoutTemplates($conn, $trainer_id = null) {
    $templates = [];
    $sql = "SELECT * FROM workout_templates WHERE 1=1";
    if ($trainer_id) {
        $sql .= " AND created_by = ?";
    }
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($trainer_id) {
        $stmt->bind_param("i", $trainer_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['exercises'] = json_decode($row['exercises'], true) ?: [];
        $templates[] = $row;
    }
    return $templates;
}

// Function to get active clients
function getClients($conn) {
    $clients = [];
    $sql = "SELECT * FROM members WHERE member_type = 'client' AND status = 'active' ORDER BY full_name";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }
    return $clients;
}

// Ensure workout_templates table exists
$createTableSQL = "CREATE TABLE IF NOT EXISTS workout_templates (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    description TEXT,
    exercises LONGTEXT NOT NULL,
    schedule ENUM('daily','weekly','custom') DEFAULT 'weekly',
    difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    goal ENUM('weight_loss','muscle_gain','strength','endurance','general_fitness') DEFAULT 'general_fitness',
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($createTableSQL);

$workout_success = '';
$workout_error = '';

// Save template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_workout_template'])) {
    $template_name = trim($_POST['template_name']);
    $description = trim($_POST['description']);
    $schedule = $_POST['schedule'] ?? 'weekly';
    $difficulty = $_POST['difficulty'] ?? 'beginner';
    $goal = $_POST['goal'] ?? 'general_fitness';
    
    $exercises = [];
    if (isset($_POST['exercise_names'])) {
        for ($i = 0; $i < count($_POST['exercise_names']); $i++) {
            if (!empty($_POST['exercise_names'][$i])) {
                $exercises[] = [
                    'name' => $_POST['exercise_names'][$i],
                    'sets' => $_POST['exercise_sets'][$i] ?? '3',
                    'reps' => $_POST['exercise_reps'][$i] ?? '8-12',
                    'rest' => $_POST['exercise_rest'][$i] ?? '60s',
                    'notes' => $_POST['exercise_notes'][$i] ?? ''
                ];
            }
        }
    }
    
    if (empty($exercises)) {
        $workout_error = "Please add at least one exercise to the template.";
    } else {
        $exercises_json = json_encode($exercises);
        $sql = "INSERT INTO workout_templates (template_name, description, exercises, schedule, difficulty, goal, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $template_name, $description, $exercises_json, $schedule, $difficulty, $goal, $current_trainer_id);
        
        if ($stmt->execute()) {
            $workout_success = "Workout template created successfully!";
        } else {
            $workout_error = "Error creating workout template: " . $conn->error;
        }
    }
}

// Assign template to client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_template'])) {
    $template_id = (int)$_POST['template_id'];
    $member_id = (int)$_POST['member_id'];
    $plan_name = trim($_POST['plan_name']);
    
    $tmpl_stmt = $conn->prepare("SELECT * FROM workout_templates WHERE id = ?");
    $tmpl_stmt->bind_param("i", $template_id);
    $tmpl_stmt->execute();
    $tmpl_data = $tmpl_stmt->get_result()->fetch_assoc();
    
    if ($tmpl_data) {
        $exercises_json = $tmpl_data['exercises'];
        $schedule = $tmpl_data['schedule'];
        $description = "Assigned from template: " . $tmpl_data['template_name'];
        
        $ins_stmt = $conn->prepare("INSERT INTO workout_plans (member_id, plan_name, description, schedule, exercises, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $ins_stmt->bind_param("issssi", $member_id, $plan_name, $description, $schedule, $exercises_json, $current_trainer_id);
        
        if ($ins_stmt->execute()) {
            $workout_success = "Workout template assigned to client successfully!";
        } else {
            $workout_error = "Error assigning template: " . $conn->error;
        }
    } else {
        $workout_error = "Selected template not found.";
    }
}

// Save custom plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_workout_plan'])) {
    $member_id = (int)$_POST['member_id'];
    $plan_name = trim($_POST['plan_name']);
    $schedule = $_POST['schedule'] ?? 'weekly';
    $description = trim($_POST['description']);
    
    $exercises = [];
    if (isset($_POST['exercise_names'])) {
        for ($i = 0; $i < count($_POST['exercise_names']); $i++) {
            if (!empty($_POST['exercise_names'][$i])) {
                $exercises[] = [
                    'name' => $_POST['exercise_names'][$i],
                    'sets' => $_POST['exercise_sets'][$i] ?? '3',
                    'reps' => $_POST['exercise_reps'][$i] ?? '8-12',
                    'rest' => $_POST['exercise_rest'][$i] ?? '60s',
                    'notes' => $_POST['exercise_notes'][$i] ?? ''
                ];
            }
        }
    }
    
    if (empty($exercises)) {
        $workout_error = "Please add at least one exercise to the workout plan.";
    } else {
        $exercises_json = json_encode($exercises);
        $sql = "INSERT INTO workout_plans (member_id, plan_name, description, schedule, exercises, created_by) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssi", $member_id, $plan_name, $description, $schedule, $exercises_json, $current_trainer_id);
        
        if ($stmt->execute()) {
            $workout_success = "Custom workout plan created successfully!";
        } else {
            $workout_error = "Error creating workout plan: " . $conn->error;
        }
    }
}

// Delete workout plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_workout_plan'])) {
    $plan_id = (int)$_POST['plan_id'];
    $stmt = $conn->prepare("DELETE FROM workout_plans WHERE id = ?");
    $stmt->bind_param("i", $plan_id);
    if ($stmt->execute()) {
        $workout_success = "Workout plan deleted successfully!";
    } else {
        $workout_error = "Error deleting workout plan: " . $conn->error;
    }
}

// Delete workout template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_workout_template'])) {
    $template_id = (int)$_POST['template_id'];
    $stmt = $conn->prepare("DELETE FROM workout_templates WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    if ($stmt->execute()) {
        $workout_success = "Workout template deleted successfully!";
    } else {
        $workout_error = "Error deleting workout template: " . $conn->error;
    }
}

$clients = getClients($conn);
$workoutPlans = getWorkoutPlans($conn, $current_trainer_id);
$workoutTemplates = getWorkoutTemplates($conn, $current_trainer_id);

$page_title = "Workout Plans & Templates — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="dumbbell" style="color: var(--accent);"></i>
        Trainer Workout Plans & Reusable Templates
      </h1>
      <p class="gym-page-subtitle">Build customized fitness routines, reusable workout splits, and assign workout plans to active clients.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="trainermealplan.php" class="gym-btn gym-btn-yellow">
        <i data-lucide="utensils"></i> Manage Meal Plans
      </a>
    </div>
  </div>

  <?php if (!empty($workout_success)): ?>
    <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="check-circle-2" style="width: 18px; height: 18px; color: #22c55e;"></i>
      <span><?php echo htmlspecialchars($workout_success); ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($workout_error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="alert-triangle" style="width: 18px; height: 18px; color: #ef4444;"></i>
      <span><?php echo htmlspecialchars($workout_error); ?></span>
    </div>
  <?php endif; ?>

  <!-- 4 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Active Client Plans</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo count($workoutPlans); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Assigned to members</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="list-checks"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Workout Templates</div>
        <div class="gym-stat-number" style="color: #c084fc;"><?php echo count($workoutTemplates); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Reusable routine splits</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(192, 132, 252, 0.15); color: #c084fc; border-color: rgba(192, 132, 252, 0.3);">
        <i data-lucide="layout-template"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Active Clients</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo count($clients); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Registered gym members</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="users"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Clients with Plans</div>
        <div class="gym-stat-number" style="color: #60a5fa;">
          <?php echo count(array_unique(array_column($workoutPlans, 'member_id'))); ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">With active routines</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="user-check"></i>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div class="gym-tabs-container" style="margin-bottom: 1.5rem;">
    <button type="button" class="gym-tab-btn active" id="btn-tab-templates" onclick="switchWorkoutTab('templates-tab', this)">
      <i data-lucide="layout-template"></i> Workout Templates (<?php echo count($workoutTemplates); ?>)
    </button>
    <button type="button" class="gym-tab-btn" id="btn-tab-assign" onclick="switchWorkoutTab('assign-tab', this)">
      <i data-lucide="user-check"></i> Assign to Client
    </button>
    <button type="button" class="gym-tab-btn" id="btn-tab-custom" onclick="switchWorkoutTab('custom-tab', this)">
      <i data-lucide="plus"></i> Custom Plan Builder
    </button>
    <button type="button" class="gym-tab-btn" id="btn-tab-plans" onclick="switchWorkoutTab('plans-tab', this)">
      <i data-lucide="list"></i> All Active Plans (<?php echo count($workoutPlans); ?>)
    </button>
  </div>

  <!-- TAB 1: WORKOUT TEMPLATES -->
  <div id="templates-tab" class="workout-tab-pane">
    <div class="gym-card" style="margin-bottom: 1.5rem;">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="layout-template" style="color: var(--accent);"></i>
        Create Reusable Workout Template
      </h2>

      <form method="POST" id="templateForm" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <input type="hidden" name="save_workout_template" value="1">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
          <!-- Left: Basic Info -->
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="gym-form-label">Template Name *</label>
              <input type="text" name="template_name" class="gym-form-control" placeholder="e.g. Beginner 4-Day Full Body Split" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="gym-form-label">Difficulty Level</label>
                <select name="difficulty" class="gym-form-control">
                  <option value="beginner">Beginner</option>
                  <option value="intermediate">Intermediate</option>
                  <option value="advanced">Advanced</option>
                </select>
              </div>

              <div>
                <label class="gym-form-label">Primary Fitness Goal</label>
                <select name="goal" class="gym-form-control">
                  <option value="weight_loss">Weight Loss</option>
                  <option value="muscle_gain">Muscle Gain</option>
                  <option value="strength">Strength & Power</option>
                  <option value="endurance">Endurance</option>
                  <option value="general_fitness">General Fitness</option>
                </select>
              </div>
            </div>

            <div>
              <label class="gym-form-label">Frequency / Schedule</label>
              <select name="schedule" class="gym-form-control">
                <option value="daily">Daily Program</option>
                <option value="weekly" selected>Weekly Routine</option>
                <option value="custom">Custom Schedule</option>
              </select>
            </div>

            <div>
              <label class="gym-form-label">Template Description & Focus</label>
              <textarea name="description" rows="3" class="gym-form-control" placeholder="Describe the focus area, recommended warm-ups, or notes..."></textarea>
            </div>
          </div>

          <!-- Right: Exercise List Builder -->
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <label class="gym-form-label" style="margin: 0;">Exercise List *</label>
              <span id="tmpl-ex-count" style="font-size: 0.78rem; color: var(--accent); font-weight: 700;">1 Exercise Added</span>
            </div>

            <div id="template-exercises-container" style="display: flex; flex-direction: column; gap: 10px; max-height: 380px; overflow-y: auto; padding-right: 4px;">
              <div class="exercise-item-box" style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                  <strong style="font-size: 0.88rem; color: var(--text-primary);">Exercise #1</strong>
                  <button type="button" onclick="this.closest('.exercise-item-box').remove(); updateTmplCount();" style="background: none; border: none; color: #ef4444; cursor: pointer;">
                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                  </button>
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                  <input type="text" name="exercise_names[]" class="gym-form-control" placeholder="Exercise Name (e.g. Barbell Squats)" required>
                  <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                    <input type="text" name="exercise_sets[]" class="gym-form-control" placeholder="3 Sets" required>
                    <input type="text" name="exercise_reps[]" class="gym-form-control" placeholder="10-12 Reps" required>
                    <input type="text" name="exercise_rest[]" class="gym-form-control" placeholder="60s Rest" required>
                  </div>
                  <input type="text" name="exercise_notes[]" class="gym-form-control" placeholder="Technique notes or form cues...">
                </div>
              </div>
            </div>

            <button type="button" onclick="addTemplateExercise()" class="gym-btn gym-btn-outline" style="width: 100%;">
              <i data-lucide="plus"></i> Add Another Exercise
            </button>

            <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%; min-height: 42px; margin-top: 6px;">
              <i data-lucide="save"></i> Save Workout Template
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Templates Library -->
    <?php if (!empty($workoutTemplates)): ?>
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
          <i data-lucide="library" style="color: var(--accent);"></i>
          Saved Workout Templates Library (<?php echo count($workoutTemplates); ?>)
        </h2>

        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
          <?php foreach ($workoutTemplates as $tmpl): ?>
            <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 18px;">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                <div>
                  <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.15rem; color: var(--text-primary); margin: 0 0 6px;">
                    <?php echo htmlspecialchars($tmpl['template_name']); ?>
                  </h3>
                  <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <span class="gym-badge gym-badge-info" style="text-transform: capitalize;"><?php echo htmlspecialchars($tmpl['difficulty']); ?></span>
                    <span class="gym-badge gym-badge-active" style="text-transform: capitalize;"><?php echo str_replace('_', ' ', htmlspecialchars($tmpl['goal'])); ?></span>
                    <span class="gym-badge gym-badge-pending" style="text-transform: capitalize;"><?php echo htmlspecialchars($tmpl['schedule']); ?></span>
                  </div>
                  <?php if ($tmpl['description']): ?>
                    <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 8px 0 0;"><?php echo htmlspecialchars($tmpl['description']); ?></p>
                  <?php endif; ?>
                </div>

                <div style="display: flex; gap: 6px;">
                  <button type="button" onclick="quickAssignTemplate(<?php echo $tmpl['id']; ?>)" class="gym-btn gym-btn-yellow" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                    <i data-lucide="user-check" style="width: 14px; height: 14px;"></i> Assign
                  </button>
                  <form method="POST" onsubmit="return confirm('Are you sure you want to delete this template?');" style="margin: 0;">
                    <input type="hidden" name="template_id" value="<?php echo $tmpl['id']; ?>">
                    <button type="submit" name="delete_workout_template" class="gym-btn gym-btn-danger" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                      <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                    </button>
                  </form>
                </div>
              </div>

              <!-- Exercise Table -->
              <div class="gym-table-wrapper" style="margin-bottom: 0;">
                <table class="gym-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Exercise Name</th>
                      <th>Sets</th>
                      <th>Reps</th>
                      <th>Rest Interval</th>
                      <th>Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($tmpl['exercises'] as $idx => $ex): ?>
                      <tr>
                        <td style="font-weight: 700; color: var(--accent);"><?php echo $idx + 1; ?></td>
                        <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($ex['name']); ?></td>
                        <td><?php echo htmlspecialchars($ex['sets'] ?? '3'); ?></td>
                        <td><?php echo htmlspecialchars($ex['reps'] ?? '8-12'); ?></td>
                        <td><?php echo htmlspecialchars($ex['rest'] ?? '60s'); ?></td>
                        <td style="color: var(--text-dim); font-size: 0.84rem;"><?php echo !empty($ex['notes']) ? htmlspecialchars($ex['notes']) : '-'; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- TAB 2: ASSIGN TEMPLATE TO CLIENT -->
  <div id="assign-tab" class="workout-tab-pane" style="display: none;">
    <div class="gym-card">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="user-check" style="color: var(--accent);"></i>
        Assign Workout Template to Active Client
      </h2>

      <form method="POST" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <input type="hidden" name="assign_template" value="1">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="gym-form-label">Select Template *</label>
              <select name="template_id" id="assign_template_id" class="gym-form-control" required onchange="updateTemplatePreview(this)">
                <option value="">Choose a workout template...</option>
                <?php foreach ($workoutTemplates as $tmpl): ?>
                  <option value="<?php echo $tmpl['id']; ?>" data-exercises='<?php echo htmlspecialchars(json_encode($tmpl['exercises']), ENT_QUOTES); ?>'>
                    <?php echo htmlspecialchars($tmpl['template_name']); ?> (<?php echo ucfirst($tmpl['difficulty']); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="gym-form-label">Select Client *</label>
              <select name="member_id" class="gym-form-control" required>
                <option value="">Choose an active client...</option>
                <?php foreach ($clients as $client): ?>
                  <option value="<?php echo $client['id']; ?>">
                    <?php echo htmlspecialchars($client['full_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="gym-form-label">Custom Plan Title for Client *</label>
              <input type="text" name="plan_name" id="assign_plan_name" class="gym-form-control" placeholder="e.g., Personalized Strength Split" required>
            </div>

            <button type="submit" class="gym-btn gym-btn-yellow" style="min-height: 42px; width: 100%;">
              <i data-lucide="user-check"></i> Assign Plan to Client
            </button>
          </div>

          <!-- Template Preview Box -->
          <div>
            <label class="gym-form-label">Template Exercise Preview</label>
            <div id="template-preview-box" style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px; min-height: 220px; color: var(--text-dim); font-size: 0.88rem;">
              <p style="text-align: center; margin: 3rem 0;">Select a template on the left to preview exercises.</p>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- TAB 3: CUSTOM PLAN BUILDER -->
  <div id="custom-tab" class="workout-tab-pane" style="display: none;">
    <div class="gym-card">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="plus-circle" style="color: var(--accent);"></i>
        Create Custom Workout Plan
      </h2>

      <form method="POST" id="customPlanForm" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <input type="hidden" name="save_workout_plan" value="1">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
          <!-- Basic Info -->
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="gym-form-label">Select Client *</label>
              <select name="member_id" class="gym-form-control" required>
                <option value="">Choose a client...</option>
                <?php foreach ($clients as $client): ?>
                  <option value="<?php echo $client['id']; ?>">
                    <?php echo htmlspecialchars($client['full_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="gym-form-label">Plan Name *</label>
              <input type="text" name="plan_name" class="gym-form-control" placeholder="e.g. Weight Loss Cardio Split" required>
            </div>

            <div>
              <label class="gym-form-label">Schedule</label>
              <select name="schedule" class="gym-form-control">
                <option value="daily">Daily Program</option>
                <option value="weekly" selected>Weekly Routine</option>
                <option value="custom">Custom Schedule</option>
              </select>
            </div>

            <div>
              <label class="gym-form-label">Instructions & Notes</label>
              <textarea name="description" rows="3" class="gym-form-control" placeholder="Describe instructions or goals..."></textarea>
            </div>
          </div>

          <!-- Exercises Builder -->
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <label class="gym-form-label" style="margin: 0;">Custom Exercises *</label>
              <span id="custom-ex-count" style="font-size: 0.78rem; color: var(--accent); font-weight: 700;">1 Exercise Added</span>
            </div>

            <div id="custom-exercises-container" style="display: flex; flex-direction: column; gap: 10px; max-height: 380px; overflow-y: auto; padding-right: 4px;">
              <div class="exercise-item-box" style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                  <strong style="font-size: 0.88rem; color: var(--text-primary);">Exercise #1</strong>
                  <button type="button" onclick="this.closest('.exercise-item-box').remove(); updateCustomCount();" style="background: none; border: none; color: #ef4444; cursor: pointer;">
                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                  </button>
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                  <input type="text" name="exercise_names[]" class="gym-form-control" placeholder="Exercise Name (e.g. Bench Press)" required>
                  <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                    <input type="text" name="exercise_sets[]" class="gym-form-control" placeholder="3 Sets" required>
                    <input type="text" name="exercise_reps[]" class="gym-form-control" placeholder="8-12 Reps" required>
                    <input type="text" name="exercise_rest[]" class="gym-form-control" placeholder="60s Rest" required>
                  </div>
                  <input type="text" name="exercise_notes[]" class="gym-form-control" placeholder="Technique notes...">
                </div>
              </div>
            </div>

            <button type="button" onclick="addCustomExercise()" class="gym-btn gym-btn-outline" style="width: 100%;">
              <i data-lucide="plus"></i> Add Another Exercise
            </button>

            <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%; min-height: 42px; margin-top: 6px;">
              <i data-lucide="save"></i> Save Custom Workout Plan
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- TAB 4: ALL ACTIVE PLANS -->
  <div id="plans-tab" class="workout-tab-pane" style="display: none;">
    <div class="gym-card">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="list" style="color: var(--accent);"></i>
        All Client Active Workout Plans (<?php echo count($workoutPlans); ?>)
      </h2>

      <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        <?php if (!empty($workoutPlans)): ?>
          <?php foreach ($workoutPlans as $plan): ?>
            <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 18px;">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                <div>
                  <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.15rem; color: var(--text-primary); margin: 0 0 6px;">
                    <?php echo htmlspecialchars($plan['plan_name']); ?>
                  </h3>
                  <div style="display: flex; gap: 12px; align-items: center; font-size: 0.85rem; color: var(--text-secondary); flex-wrap: wrap;">
                    <span>Client: <strong style="color: var(--accent);"><?php echo htmlspecialchars($plan['full_name']); ?></strong></span>
                    <span>Schedule: <strong style="color: var(--text-primary); text-transform: capitalize;"><?php echo htmlspecialchars($plan['schedule']); ?></strong></span>
                    <span>Created: <?php echo date('M j, Y', strtotime($plan['created_at'])); ?></span>
                  </div>
                  <?php if (!empty($plan['description'])): ?>
                    <p style="font-size: 0.85rem; color: var(--text-dim); margin: 6px 0 0;"><?php echo htmlspecialchars($plan['description']); ?></p>
                  <?php endif; ?>
                </div>

                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this workout plan?');" style="margin: 0;">
                  <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                  <button type="submit" name="delete_workout_plan" class="gym-btn gym-btn-danger" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i> Delete
                  </button>
                </form>
              </div>

              <!-- Exercise Table -->
              <div class="gym-table-wrapper" style="margin-bottom: 0;">
                <table class="gym-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Exercise Name</th>
                      <th>Sets</th>
                      <th>Reps</th>
                      <th>Rest Interval</th>
                      <th>Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($plan['exercises'] as $idx => $ex): ?>
                      <tr>
                        <td style="font-weight: 700; color: var(--accent);"><?php echo $idx + 1; ?></td>
                        <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($ex['name']); ?></td>
                        <td><?php echo htmlspecialchars($ex['sets'] ?? '3'); ?></td>
                        <td><?php echo htmlspecialchars($ex['reps'] ?? '8-12'); ?></td>
                        <td><?php echo htmlspecialchars($ex['rest'] ?? '60s'); ?></td>
                        <td style="color: var(--text-dim); font-size: 0.84rem;"><?php echo !empty($ex['notes']) ? htmlspecialchars($ex['notes']) : '-'; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
            <i data-lucide="list" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
            <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No active workout plans assigned to clients yet.</p>
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

  function switchWorkoutTab(tabId, btn) {
      document.querySelectorAll('.workout-tab-pane').forEach(pane => pane.style.display = 'none');
      document.querySelectorAll('.gym-tab-btn').forEach(b => b.classList.remove('active'));
      
      const targetPane = document.getElementById(tabId);
      if (targetPane) targetPane.style.display = 'block';
      if (btn) btn.classList.add('active');
  }

  function addTemplateExercise() {
      const container = document.getElementById('template-exercises-container');
      const count = container.children.length + 1;
      const div = document.createElement('div');
      div.className = 'exercise-item-box';
      div.style.cssText = 'background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px;';
      div.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <strong style="font-size: 0.88rem; color: var(--text-primary);">Exercise #${count}</strong>
          <button type="button" onclick="this.closest('.exercise-item-box').remove(); updateTmplCount();" style="background: none; border: none; color: #ef4444; cursor: pointer;">
            <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
          </button>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <input type="text" name="exercise_names[]" class="gym-form-control" placeholder="Exercise Name" required>
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
            <input type="text" name="exercise_sets[]" class="gym-form-control" placeholder="Sets" required>
            <input type="text" name="exercise_reps[]" class="gym-form-control" placeholder="Reps" required>
            <input type="text" name="exercise_rest[]" class="gym-form-control" placeholder="Rest" required>
          </div>
          <input type="text" name="exercise_notes[]" class="gym-form-control" placeholder="Technique notes...">
        </div>
      `;
      container.appendChild(div);
      updateTmplCount();
      if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  function updateTmplCount() {
      const container = document.getElementById('template-exercises-container');
      const tag = document.getElementById('tmpl-ex-count');
      if (tag && container) {
          tag.textContent = container.children.length + ' Exercise(s) Added';
      }
  }

  function addCustomExercise() {
      const container = document.getElementById('custom-exercises-container');
      const count = container.children.length + 1;
      const div = document.createElement('div');
      div.className = 'exercise-item-box';
      div.style.cssText = 'background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px;';
      div.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <strong style="font-size: 0.88rem; color: var(--text-primary);">Exercise #${count}</strong>
          <button type="button" onclick="this.closest('.exercise-item-box').remove(); updateCustomCount();" style="background: none; border: none; color: #ef4444; cursor: pointer;">
            <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
          </button>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <input type="text" name="exercise_names[]" class="gym-form-control" placeholder="Exercise Name" required>
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
            <input type="text" name="exercise_sets[]" class="gym-form-control" placeholder="Sets" required>
            <input type="text" name="exercise_reps[]" class="gym-form-control" placeholder="Reps" required>
            <input type="text" name="exercise_rest[]" class="gym-form-control" placeholder="Rest" required>
          </div>
          <input type="text" name="exercise_notes[]" class="gym-form-control" placeholder="Technique notes...">
        </div>
      `;
      container.appendChild(div);
      updateCustomCount();
      if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  function updateCustomCount() {
      const container = document.getElementById('custom-exercises-container');
      const tag = document.getElementById('custom-ex-count');
      if (tag && container) {
          tag.textContent = container.children.length + ' Exercise(s) Added';
      }
  }

  function updateTemplatePreview(select) {
      const option = select.options[select.selectedIndex];
      const json = option.getAttribute('data-exercises');
      const preview = document.getElementById('template-preview-box');
      
      if (json && select.value) {
          try {
              const exercises = JSON.parse(json);
              let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
              exercises.forEach((ex, i) => {
                  html += `
                    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                      <strong style="color: var(--text-primary);">${i + 1}. ${ex.name}</strong>
                      <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;">
                        Sets: ${ex.sets || '3'} | Reps: ${ex.reps || '8-12'} | Rest: ${ex.rest || '60s'}
                      </div>
                      ${ex.notes ? `<div style="font-size: 0.75rem; color: var(--accent); margin-top: 2px;">Note: ${ex.notes}</div>` : ''}
                    </div>
                  `;
              });
              html += '</div>';
              preview.innerHTML = html;
          } catch(e) {
              preview.innerHTML = '<p style="text-align: center; color: var(--red);">Error reading template preview.</p>';
          }
      } else {
          preview.innerHTML = '<p style="text-align: center; margin: 3rem 0;">Select a template on the left to preview exercises.</p>';
      }
  }

  function quickAssignTemplate(tmplId) {
      switchWorkoutTab('assign-tab', document.getElementById('btn-tab-assign'));
      const select = document.getElementById('assign_template_id');
      if (select) {
          select.value = tmplId;
          updateTemplatePreview(select);
      }
  }
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
