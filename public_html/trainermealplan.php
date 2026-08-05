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

// Function to get meal plans
function getMealPlans($conn, $trainer_id = null) {
    $mealPlans = [];
    $sql = "SELECT mp.*, m.full_name, m.fitness_goals 
            FROM meal_plans mp 
            JOIN members m ON mp.member_id = m.id 
            WHERE m.member_type = 'client'";
    
    if ($trainer_id) {
        $sql .= " AND mp.created_by = ?";
    }
    $sql .= " ORDER BY mp.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($trainer_id) {
        $stmt->bind_param("i", $trainer_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['meals'] = json_decode($row['meals'], true) ?: [];
        $mealPlans[] = $row;
    }
    return $mealPlans;
}

// Function to get meal templates
function getMealTemplates($conn, $trainer_id = null) {
    $templates = [];
    $sql = "SELECT * FROM meal_templates WHERE 1=1";
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
        $row['meals'] = json_decode($row['meals'], true) ?: [];
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

// Ensure meal_templates table exists
$createTableSQL = "CREATE TABLE IF NOT EXISTS meal_templates (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    description TEXT,
    daily_calories INT(11) DEFAULT 2000,
    protein_goal DECIMAL(6,2) DEFAULT 150.00,
    carbs_goal DECIMAL(6,2) DEFAULT 200.00,
    fat_goal DECIMAL(6,2) DEFAULT 60.00,
    goal ENUM('weight_loss','muscle_gain','maintenance','general_fitness') DEFAULT 'general_fitness',
    meals LONGTEXT NOT NULL,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($createTableSQL);

$meal_success = '';
$meal_error = '';

// Save template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meal_template'])) {
    $template_name = trim($_POST['template_name']);
    $description = trim($_POST['description']);
    $daily_calories = (int)($_POST['daily_calories'] ?? 2000);
    $protein_goal = (float)($_POST['protein_goal'] ?? 150);
    $carbs_goal = (float)($_POST['carbs_goal'] ?? 200);
    $fat_goal = (float)($_POST['fat_goal'] ?? 60);
    $goal = $_POST['goal'] ?? 'general_fitness';
    
    $meals = [];
    if (isset($_POST['meal_names'])) {
        for ($i = 0; $i < count($_POST['meal_names']); $i++) {
            if (!empty($_POST['meal_names'][$i])) {
                $meals[] = [
                    'name' => $_POST['meal_names'][$i],
                    'time' => $_POST['meal_times'][$i] ?? '',
                    'calories' => $_POST['meal_calories'][$i] ?? 0,
                    'description' => $_POST['meal_descriptions'][$i] ?? ''
                ];
            }
        }
    }
    
    if (empty($meals)) {
        $meal_error = "Please add at least one meal to the template.";
    } else {
        $meals_json = json_encode($meals);
        $sql = "INSERT INTO meal_templates (template_name, description, daily_calories, protein_goal, carbs_goal, fat_goal, goal, meals, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssidddssi", $template_name, $description, $daily_calories, $protein_goal, $carbs_goal, $fat_goal, $goal, $meals_json, $current_trainer_id);
        
        if ($stmt->execute()) {
            $meal_success = "Meal template created successfully!";
        } else {
            $meal_error = "Error creating meal template: " . $conn->error;
        }
    }
}

// Assign template to client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_template'])) {
    $template_id = (int)$_POST['template_id'];
    $member_id = (int)$_POST['member_id'];
    $plan_name = trim($_POST['plan_name']);
    
    $tmpl_stmt = $conn->prepare("SELECT * FROM meal_templates WHERE id = ?");
    $tmpl_stmt->bind_param("i", $template_id);
    $tmpl_stmt->execute();
    $tmpl_data = $tmpl_stmt->get_result()->fetch_assoc();
    
    if ($tmpl_data) {
        $meals_json = $tmpl_data['meals'];
        $desc = "Assigned from template: " . $tmpl_data['template_name'];
        
        $ins_stmt = $conn->prepare("INSERT INTO meal_plans (member_id, plan_name, description, daily_calories, protein_goal, carbs_goal, fat_goal, meals, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins_stmt->bind_param("isiddddsi", $member_id, $plan_name, $desc, $tmpl_data['daily_calories'], $tmpl_data['protein_goal'], $tmpl_data['carbs_goal'], $tmpl_data['fat_goal'], $meals_json, $current_trainer_id);
        
        if ($ins_stmt->execute()) {
            $meal_success = "Meal template assigned to client successfully!";
        } else {
            $meal_error = "Error assigning template: " . $conn->error;
        }
    } else {
        $meal_error = "Selected meal template not found.";
    }
}

// Save custom plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meal_plan'])) {
    $member_id = (int)$_POST['member_id'];
    $plan_name = trim($_POST['plan_name']);
    $description = trim($_POST['description']);
    $daily_calories = (int)($_POST['daily_calories'] ?? 2000);
    $protein_goal = (float)($_POST['protein_goal'] ?? 150);
    $carbs_goal = (float)($_POST['carbs_goal'] ?? 200);
    $fat_goal = (float)($_POST['fat_goal'] ?? 60);
    
    $meals = [];
    if (isset($_POST['meal_names'])) {
        for ($i = 0; $i < count($_POST['meal_names']); $i++) {
            if (!empty($_POST['meal_names'][$i])) {
                $meals[] = [
                    'name' => $_POST['meal_names'][$i],
                    'time' => $_POST['meal_times'][$i] ?? '',
                    'calories' => $_POST['meal_calories'][$i] ?? 0,
                    'description' => $_POST['meal_descriptions'][$i] ?? ''
                ];
            }
        }
    }
    
    if (empty($meals)) {
        $meal_error = "Please add at least one meal to the plan.";
    } else {
        $meals_json = json_encode($meals);
        $sql = "INSERT INTO meal_plans (member_id, plan_name, description, daily_calories, protein_goal, carbs_goal, fat_goal, meals, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issddddsi", $member_id, $plan_name, $description, $daily_calories, $protein_goal, $carbs_goal, $fat_goal, $meals_json, $current_trainer_id);
        
        if ($stmt->execute()) {
            $meal_success = "Custom meal plan created successfully!";
        } else {
            $meal_error = "Error creating meal plan: " . $conn->error;
        }
    }
}

// Delete meal plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_meal_plan'])) {
    $plan_id = (int)$_POST['plan_id'];
    $stmt = $conn->prepare("DELETE FROM meal_plans WHERE id = ?");
    $stmt->bind_param("i", $plan_id);
    if ($stmt->execute()) {
        $meal_success = "Meal plan deleted successfully!";
    } else {
        $meal_error = "Error deleting meal plan: " . $conn->error;
    }
}

// Delete meal template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_meal_template'])) {
    $template_id = (int)$_POST['template_id'];
    $stmt = $conn->prepare("DELETE FROM meal_templates WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    if ($stmt->execute()) {
        $meal_success = "Meal template deleted successfully!";
    } else {
        $meal_error = "Error deleting meal template: " . $conn->error;
    }
}

$clients = getClients($conn);
$mealPlans = getMealPlans($conn, $current_trainer_id);
$mealTemplates = getMealTemplates($conn, $current_trainer_id);

$page_title = "Nutrition & Meal Plans — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="utensils" style="color: var(--accent);"></i>
        Trainer Nutrition & Meal Plans Manager
      </h1>
      <p class="gym-page-subtitle">Design daily calorie & macro diet plans, reusable nutrition templates, and assign dietary guidelines to active clients.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="trainerworkout.php" class="gym-btn gym-btn-yellow">
        <i data-lucide="dumbbell"></i> Manage Workout Plans
      </a>
    </div>
  </div>

  <?php if (!empty($meal_success)): ?>
    <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="check-circle-2" style="width: 18px; height: 18px; color: #22c55e;"></i>
      <span><?php echo htmlspecialchars($meal_success); ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($meal_error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="alert-triangle" style="width: 18px; height: 18px; color: #ef4444;"></i>
      <span><?php echo htmlspecialchars($meal_error); ?></span>
    </div>
  <?php endif; ?>

  <!-- 4 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Active Meal Plans</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo count($mealPlans); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Assigned to members</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="utensils"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Meal Templates</div>
        <div class="gym-stat-number" style="color: #c084fc;"><?php echo count($mealTemplates); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Reusable diet programs</div>
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
        <div class="gym-stat-label">Clients with Diets</div>
        <div class="gym-stat-number" style="color: #60a5fa;">
          <?php echo count(array_unique(array_column($mealPlans, 'member_id'))); ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">With active meal plans</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="user-check"></i>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div class="gym-tabs-container" style="margin-bottom: 1.5rem;">
    <button type="button" class="gym-tab-btn active" id="btn-tab-templates" onclick="switchMealTab('templates-tab', this)">
      <i data-lucide="layout-template"></i> Meal Templates (<?php echo count($mealTemplates); ?>)
    </button>
    <button type="button" class="gym-tab-btn" id="btn-tab-assign" onclick="switchMealTab('assign-tab', this)">
      <i data-lucide="user-check"></i> Assign to Client
    </button>
    <button type="button" class="gym-tab-btn" id="btn-tab-custom" onclick="switchMealTab('custom-tab', this)">
      <i data-lucide="plus"></i> Custom Diet Builder
    </button>
    <button type="button" class="gym-tab-btn" id="btn-tab-plans" onclick="switchMealTab('plans-tab', this)">
      <i data-lucide="list"></i> All Active Diets (<?php echo count($mealPlans); ?>)
    </button>
  </div>

  <!-- TAB 1: MEAL TEMPLATES -->
  <div id="templates-tab" class="meal-tab-pane">
    <div class="gym-card" style="margin-bottom: 1.5rem;">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="layout-template" style="color: var(--accent);"></i>
        Create Reusable Meal Template
      </h2>

      <form method="POST" id="templateForm" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <input type="hidden" name="save_meal_template" value="1">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
          <!-- Basic Info & Macros -->
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="gym-form-label">Template Name *</label>
              <input type="text" name="template_name" class="gym-form-control" placeholder="e.g. High Protein Lean Muscle Diet" required>
            </div>

            <div>
              <label class="gym-form-label">Dietary Goal Target</label>
              <select name="goal" class="gym-form-control">
                <option value="weight_loss">Weight Loss / Cutting</option>
                <option value="muscle_gain">Muscle Building / Bulking</option>
                <option value="maintenance">Maintenance</option>
                <option value="general_fitness" selected>General Health & Fitness</option>
              </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="gym-form-label">Daily Target Calories</label>
                <input type="number" name="daily_calories" class="gym-form-control" placeholder="2200 kcal" value="2200" required>
              </div>
              <div>
                <label class="gym-form-label">Protein Target (g)</label>
                <input type="number" name="protein_goal" class="gym-form-control" placeholder="160 g" value="160">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="gym-form-label">Carbs Target (g)</label>
                <input type="number" name="carbs_goal" class="gym-form-control" placeholder="200 g" value="200">
              </div>
              <div>
                <label class="gym-form-label">Fats Target (g)</label>
                <input type="number" name="fat_goal" class="gym-form-control" placeholder="60 g" value="60">
              </div>
            </div>

            <div>
              <label class="gym-form-label">Dietary Description & Guidelines</label>
              <textarea name="description" rows="3" class="gym-form-control" placeholder="Describe hydration recommendations, supplement guidelines..."></textarea>
            </div>
          </div>

          <!-- Meal Items List Builder -->
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <label class="gym-form-label" style="margin: 0;">Daily Meal Menu *</label>
              <span id="tmpl-meal-count" style="font-size: 0.78rem; color: var(--accent); font-weight: 700;">1 Meal Added</span>
            </div>

            <div id="template-meals-container" style="display: flex; flex-direction: column; gap: 10px; max-height: 380px; overflow-y: auto; padding-right: 4px;">
              <div class="meal-item-box" style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                  <strong style="font-size: 0.88rem; color: var(--text-primary);">Meal #1</strong>
                  <button type="button" onclick="this.closest('.meal-item-box').remove(); updateTmplMealCount();" style="background: none; border: none; color: #ef4444; cursor: pointer;">
                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                  </button>
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <input type="text" name="meal_names[]" class="gym-form-control" placeholder="Meal Name (e.g. Breakfast)" required>
                    <input type="text" name="meal_times[]" class="gym-form-control" placeholder="Timing (e.g. 8:00 AM)" required>
                  </div>
                  <input type="number" name="meal_calories[]" class="gym-form-control" placeholder="Estimated Calories (e.g. 500)" required>
                  <textarea name="meal_descriptions[]" class="gym-form-control" rows="2" placeholder="Ingredients (e.g. 4 Egg Whites, 1 cup Oatmeal, 1 Banana)..." required></textarea>
                </div>
              </div>
            </div>

            <button type="button" onclick="addTemplateMeal()" class="gym-btn gym-btn-outline" style="width: 100%;">
              <i data-lucide="plus"></i> Add Another Meal
            </button>

            <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%; min-height: 42px; margin-top: 6px;">
              <i data-lucide="save"></i> Save Meal Template
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Templates Library -->
    <?php if (!empty($mealTemplates)): ?>
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
          <i data-lucide="library" style="color: var(--accent);"></i>
          Saved Meal Templates Library (<?php echo count($mealTemplates); ?>)
        </h2>

        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
          <?php foreach ($mealTemplates as $tmpl): ?>
            <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 18px;">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                <div>
                  <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.15rem; color: var(--text-primary); margin: 0 0 6px;">
                    <?php echo htmlspecialchars($tmpl['template_name']); ?>
                  </h3>
                  <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                    <span class="gym-badge gym-badge-active" style="text-transform: capitalize;"><?php echo str_replace('_', ' ', htmlspecialchars($tmpl['goal'])); ?></span>
                    <span style="font-size: 0.82rem; color: var(--accent); font-weight: 700; background: rgba(232, 160, 18, 0.15); padding: 2px 8px; border-radius: 4px;">
                      🔥 <?php echo number_format($tmpl['daily_calories']); ?> kcal / day
                    </span>
                    <span style="font-size: 0.78rem; color: var(--text-dim);">
                      (P: <?php echo $tmpl['protein_goal']; ?>g | C: <?php echo $tmpl['carbs_goal']; ?>g | F: <?php echo $tmpl['fat_goal']; ?>g)
                    </span>
                  </div>
                  <?php if ($tmpl['description']): ?>
                    <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 8px 0 0;"><?php echo htmlspecialchars($tmpl['description']); ?></p>
                  <?php endif; ?>
                </div>

                <div style="display: flex; gap: 6px;">
                  <button type="button" onclick="quickAssignMealTemplate(<?php echo $tmpl['id']; ?>)" class="gym-btn gym-btn-yellow" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                    <i data-lucide="user-check" style="width: 14px; height: 14px;"></i> Assign
                  </button>
                  <form method="POST" onsubmit="return confirm('Are you sure you want to delete this template?');" style="margin: 0;">
                    <input type="hidden" name="template_id" value="<?php echo $tmpl['id']; ?>">
                    <button type="submit" name="delete_meal_template" class="gym-btn gym-btn-danger" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                      <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                    </button>
                  </form>
                </div>
              </div>

              <!-- Meals Table -->
              <div class="gym-table-wrapper" style="margin-bottom: 0;">
                <table class="gym-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Meal Name</th>
                      <th>Scheduled Time</th>
                      <th>Calories</th>
                      <th>Ingredients & Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($tmpl['meals'] as $idx => $m): ?>
                      <tr>
                        <td style="font-weight: 700; color: var(--accent);"><?php echo $idx + 1; ?></td>
                        <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($m['name']); ?></td>
                        <td><?php echo htmlspecialchars($m['time'] ?? '-'); ?></td>
                        <td style="font-weight: 700; color: #4ade80;"><?php echo htmlspecialchars($m['calories'] ?? '0'); ?> kcal</td>
                        <td style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo htmlspecialchars($m['description'] ?? '-'); ?></td>
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

  <!-- TAB 2: ASSIGN MEAL TEMPLATE -->
  <div id="assign-tab" class="meal-tab-pane" style="display: none;">
    <div class="gym-card">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="user-check" style="color: var(--accent);"></i>
        Assign Meal Template to Active Client
      </h2>

      <form method="POST" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <input type="hidden" name="assign_template" value="1">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <label class="gym-form-label">Select Meal Template *</label>
              <select name="template_id" id="assign_template_id" class="gym-form-control" required onchange="updateMealTemplatePreview(this)">
                <option value="">Choose a meal template...</option>
                <?php foreach ($mealTemplates as $tmpl): ?>
                  <option value="<?php echo $tmpl['id']; ?>" data-meals='<?php echo htmlspecialchars(json_encode($tmpl['meals']), ENT_QUOTES); ?>'>
                    <?php echo htmlspecialchars($tmpl['template_name']); ?> (<?php echo $tmpl['daily_calories']; ?> kcal)
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
              <label class="gym-form-label">Custom Diet Title for Client *</label>
              <input type="text" name="plan_name" id="assign_plan_name" class="gym-form-control" placeholder="e.g. Personalized Weight Loss Diet" required>
            </div>

            <button type="submit" class="gym-btn gym-btn-yellow" style="min-height: 42px; width: 100%;">
              <i data-lucide="user-check"></i> Assign Meal Plan to Client
            </button>
          </div>

          <!-- Template Preview Box -->
          <div>
            <label class="gym-form-label">Meal Menu Preview</label>
            <div id="meal-template-preview-box" style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px; min-height: 220px; color: var(--text-dim); font-size: 0.88rem;">
              <p style="text-align: center; margin: 3rem 0;">Select a template on the left to preview meals.</p>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- TAB 3: CUSTOM DIET BUILDER -->
  <div id="custom-tab" class="meal-tab-pane" style="display: none;">
    <div class="gym-card">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="plus-circle" style="color: var(--accent);"></i>
        Create Custom Meal Plan
      </h2>

      <form method="POST" id="customMealForm" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <input type="hidden" name="save_meal_plan" value="1">

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
              <input type="text" name="plan_name" class="gym-form-control" placeholder="e.g. Personalized Macro Diet" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="gym-form-label">Daily Calories</label>
                <input type="number" name="daily_calories" class="gym-form-control" placeholder="2000 kcal" value="2000" required>
              </div>
              <div>
                <label class="gym-form-label">Protein (g)</label>
                <input type="number" name="protein_goal" class="gym-form-control" placeholder="150 g" value="150">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <label class="gym-form-label">Carbs (g)</label>
                <input type="number" name="carbs_goal" class="gym-form-control" placeholder="200 g" value="200">
              </div>
              <div>
                <label class="gym-form-label">Fats (g)</label>
                <input type="number" name="fat_goal" class="gym-form-control" placeholder="60 g" value="60">
              </div>
            </div>

            <div>
              <label class="gym-form-label">Special Dietary Instructions</label>
              <textarea name="description" rows="3" class="gym-form-control" placeholder="Describe timing, water intake, restrictions..."></textarea>
            </div>
          </div>

          <!-- Meals List Builder -->
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <label class="gym-form-label" style="margin: 0;">Custom Meals *</label>
              <span id="custom-meal-count" style="font-size: 0.78rem; color: var(--accent); font-weight: 700;">1 Meal Added</span>
            </div>

            <div id="custom-meals-container" style="display: flex; flex-direction: column; gap: 10px; max-height: 380px; overflow-y: auto; padding-right: 4px;">
              <div class="meal-item-box" style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                  <strong style="font-size: 0.88rem; color: var(--text-primary);">Meal #1</strong>
                  <button type="button" onclick="this.closest('.meal-item-box').remove(); updateCustomMealCount();" style="background: none; border: none; color: #ef4444; cursor: pointer;">
                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                  </button>
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <input type="text" name="meal_names[]" class="gym-form-control" placeholder="Meal Name (e.g. Lunch)" required>
                    <input type="text" name="meal_times[]" class="gym-form-control" placeholder="Timing (e.g. 12:30 PM)" required>
                  </div>
                  <input type="number" name="meal_calories[]" class="gym-form-control" placeholder="Calories (e.g. 600)" required>
                  <textarea name="meal_descriptions[]" class="gym-form-control" rows="2" placeholder="Ingredients & prep instructions..." required></textarea>
                </div>
              </div>
            </div>

            <button type="button" onclick="addCustomMeal()" class="gym-btn gym-btn-outline" style="width: 100%;">
              <i data-lucide="plus"></i> Add Another Meal
            </button>

            <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%; min-height: 42px; margin-top: 6px;">
              <i data-lucide="save"></i> Save Custom Meal Plan
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- TAB 4: ALL ACTIVE PLANS -->
  <div id="plans-tab" class="meal-tab-pane" style="display: none;">
    <div class="gym-card">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="list" style="color: var(--accent);"></i>
        All Active Client Meal Plans (<?php echo count($mealPlans); ?>)
      </h2>

      <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        <?php if (!empty($mealPlans)): ?>
          <?php foreach ($mealPlans as $plan): ?>
            <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 18px;">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                <div>
                  <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.15rem; color: var(--text-primary); margin: 0 0 6px;">
                    <?php echo htmlspecialchars($plan['plan_name']); ?>
                  </h3>
                  <div style="display: flex; gap: 12px; align-items: center; font-size: 0.85rem; color: var(--text-secondary); flex-wrap: wrap;">
                    <span>Client: <strong style="color: var(--accent);"><?php echo htmlspecialchars($plan['full_name']); ?></strong></span>
                    <span style="font-weight: 700; color: #4ade80;">🔥 <?php echo number_format($plan['daily_calories']); ?> kcal / day</span>
                    <span>Created: <?php echo date('M j, Y', strtotime($plan['created_at'])); ?></span>
                  </div>
                  <?php if (!empty($plan['description'])): ?>
                    <p style="font-size: 0.85rem; color: var(--text-dim); margin: 6px 0 0;"><?php echo htmlspecialchars($plan['description']); ?></p>
                  <?php endif; ?>
                </div>

                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this meal plan?');" style="margin: 0;">
                  <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                  <button type="submit" name="delete_meal_plan" class="gym-btn gym-btn-danger" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i> Delete
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
                      <th>Calories</th>
                      <th>Ingredients & Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($plan['meals'] as $idx => $m): ?>
                      <tr>
                        <td style="font-weight: 700; color: var(--accent);"><?php echo $idx + 1; ?></td>
                        <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($m['name']); ?></td>
                        <td><?php echo htmlspecialchars($m['time'] ?? '-'); ?></td>
                        <td style="font-weight: 700; color: #4ade80;"><?php echo htmlspecialchars($m['calories'] ?? '0'); ?> kcal</td>
                        <td style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo htmlspecialchars($m['description'] ?? '-'); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
            <i data-lucide="utensils" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
            <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No active meal plans assigned to clients yet.</p>
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

  function switchMealTab(tabId, btn) {
      document.querySelectorAll('.meal-tab-pane').forEach(pane => pane.style.display = 'none');
      document.querySelectorAll('.gym-tab-btn').forEach(b => b.classList.remove('active'));
      
      const targetPane = document.getElementById(tabId);
      if (targetPane) targetPane.style.display = 'block';
      if (btn) btn.classList.add('active');
  }

  function addTemplateMeal() {
      const container = document.getElementById('template-meals-container');
      const count = container.children.length + 1;
      const div = document.createElement('div');
      div.className = 'meal-item-box';
      div.style.cssText = 'background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px;';
      div.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <strong style="font-size: 0.88rem; color: var(--text-primary);">Meal #${count}</strong>
          <button type="button" onclick="this.closest('.meal-item-box').remove(); updateTmplMealCount();" style="background: none; border: none; color: #ef4444; cursor: pointer;">
            <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
          </button>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
            <input type="text" name="meal_names[]" class="gym-form-control" placeholder="Meal Name" required>
            <input type="text" name="meal_times[]" class="gym-form-control" placeholder="Timing" required>
          </div>
          <input type="number" name="meal_calories[]" class="gym-form-control" placeholder="Calories" required>
          <textarea name="meal_descriptions[]" class="gym-form-control" rows="2" placeholder="Ingredients & prep instructions..." required></textarea>
        </div>
      `;
      container.appendChild(div);
      updateTmplMealCount();
      if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  function updateTmplMealCount() {
      const container = document.getElementById('template-meals-container');
      const tag = document.getElementById('tmpl-meal-count');
      if (tag && container) {
          tag.textContent = container.children.length + ' Meal(s) Added';
      }
  }

  function addCustomMeal() {
      const container = document.getElementById('custom-meals-container');
      const count = container.children.length + 1;
      const div = document.createElement('div');
      div.className = 'meal-item-box';
      div.style.cssText = 'background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px;';
      div.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <strong style="font-size: 0.88rem; color: var(--text-primary);">Meal #${count}</strong>
          <button type="button" onclick="this.closest('.meal-item-box').remove(); updateCustomMealCount();" style="background: none; border: none; color: #ef4444; cursor: pointer;">
            <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
          </button>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
            <input type="text" name="meal_names[]" class="gym-form-control" placeholder="Meal Name" required>
            <input type="text" name="meal_times[]" class="gym-form-control" placeholder="Timing" required>
          </div>
          <input type="number" name="meal_calories[]" class="gym-form-control" placeholder="Calories" required>
          <textarea name="meal_descriptions[]" class="gym-form-control" rows="2" placeholder="Ingredients & prep instructions..." required></textarea>
        </div>
      `;
      container.appendChild(div);
      updateCustomMealCount();
      if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  function updateCustomMealCount() {
      const container = document.getElementById('custom-meals-container');
      const tag = document.getElementById('custom-meal-count');
      if (tag && container) {
          tag.textContent = container.children.length + ' Meal(s) Added';
      }
  }

  function updateMealTemplatePreview(select) {
      const option = select.options[select.selectedIndex];
      const json = option.getAttribute('data-meals');
      const preview = document.getElementById('meal-template-preview-box');
      
      if (json && select.value) {
          try {
              const meals = JSON.parse(json);
              let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
              meals.forEach((m, i) => {
                  html += `
                    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; padding: 10px;">
                      <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong style="color: var(--text-primary);">${i + 1}. ${m.name}</strong>
                        <span style="font-size: 0.8rem; color: #4ade80; font-weight: 700;">${m.calories || 0} kcal</span>
                      </div>
                      <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;">Time: ${m.time || '-'}</div>
                      ${m.description ? `<div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Ingredients: ${m.description}</div>` : ''}
                    </div>
                  `;
              });
              html += '</div>';
              preview.innerHTML = html;
          } catch(e) {
              preview.innerHTML = '<p style="text-align: center; color: var(--red);">Error reading template preview.</p>';
          }
      } else {
          preview.innerHTML = '<p style="text-align: center; margin: 3rem 0;">Select a template on the left to preview meals.</p>';
      }
  }

  function quickAssignMealTemplate(tmplId) {
      switchMealTab('assign-tab', document.getElementById('btn-tab-assign'));
      const select = document.getElementById('assign_template_id');
      if (select) {
          select.value = tmplId;
          updateMealTemplatePreview(select);
      }
  }
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
