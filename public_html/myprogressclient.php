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

function getClientProgress($conn, $user_id) {
    $progress = [];
    $stmt = $conn->prepare("
        SELECT cp.* 
        FROM client_progress cp 
        JOIN members m ON cp.member_id = m.id 
        JOIN users u ON m.user_id = u.id 
        WHERE u.id = ? 
        ORDER BY cp.progress_date ASC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $progress[] = $row;
        }
        $stmt->close();
    }
    return $progress;
}

// Handle new weight progress submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_progress'])) {
    $weight = (float)$_POST['weight'];
    $notes = trim($_POST['notes']);
    $progress_date = $_POST['progress_date'];

    $stmt_m = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
    if ($stmt_m) {
        $stmt_m->bind_param("i", $logged_in_user_id);
        $stmt_m->execute();
        $member = $stmt_m->get_result()->fetch_assoc();
        $stmt_m->close();

        if ($member) {
            $member_id = $member['id'];
            $stmt_i = $conn->prepare("INSERT INTO client_progress (member_id, weight, notes, progress_date) VALUES (?, ?, ?, ?)");
            if ($stmt_i) {
                $stmt_i->bind_param("idss", $member_id, $weight, $notes, $progress_date);
                if ($stmt_i->execute()) {
                    $success_message = "Weight progress log recorded successfully!";
                } else {
                    $error_message = "Error saving progress log: " . $conn->error;
                }
                $stmt_i->close();
            }
        }
    }
}

$progress = getClientProgress($conn, $logged_in_user_id);

$total_entries = count($progress);
$latest_weight = $total_entries > 0 ? end($progress)['weight'] : null;
$starting_weight = $total_entries > 0 ? $progress[0]['weight'] : null;
$weight_change = ($latest_weight !== null && $starting_weight !== null) ? ($latest_weight - $starting_weight) : 0;

$chart_labels = [];
$chart_weights = [];
foreach ($progress as $entry) {
    $chart_labels[] = date('M j', strtotime($entry['progress_date']));
    $chart_weights[] = (float)$entry['weight'];
}

$page_title = "My Body Progress — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="trending-up" style="color: var(--accent);"></i>
        My Body Weight Progress & Analytics
      </h1>
      <p class="gym-page-subtitle">Track weight transformations over time, log body measurement entries, and visualize progress charts.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="client_dashboard.php" class="gym-btn gym-btn-outline">
        <i data-lucide="arrow-left"></i> Dashboard
      </a>
    </div>
  </div>

  <?php if (!empty($success_message)): ?>
    <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="check-circle-2" style="width: 18px; height: 18px; color: #22c55e;"></i>
      <span><?php echo htmlspecialchars($success_message); ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($error_message)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="alert-triangle" style="width: 18px; height: 18px; color: #ef4444;"></i>
      <span><?php echo htmlspecialchars($error_message); ?></span>
    </div>
  <?php endif; ?>

  <!-- 3 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Current Weight</div>
        <div class="gym-stat-number" style="color: var(--accent-light);">
          <?php echo $latest_weight !== null ? number_format($latest_weight, 1) . ' kg' : 'N/A'; ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Latest measurement entry</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="scale"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Weight Change</div>
        <div class="gym-stat-number" style="color: <?php echo $weight_change < 0 ? '#4ade80' : ($weight_change > 0 ? '#f87171' : 'var(--text-primary)'); ?>;">
          <?php echo ($weight_change > 0 ? '+' : '') . number_format($weight_change, 1); ?> kg
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Since initial starting weight</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="trending-up"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Log Entries</div>
        <div class="gym-stat-number" style="color: #c084fc;"><?php echo $total_entries; ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Recorded progress entries</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(192, 132, 252, 0.15); color: #c084fc; border-color: rgba(192, 132, 252, 0.3);">
        <i data-lucide="calendar"></i>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div class="gym-tabs-container" style="margin-bottom: 1.5rem;">
    <button type="button" class="gym-tab-btn active" id="btn-tab-chart" onclick="switchProgressTab('tab-chart', this)">
      <i data-lucide="trending-up"></i> Progress Chart Analytics
    </button>
    <button type="button" class="gym-tab-btn" id="btn-tab-add" onclick="switchProgressTab('tab-add', this)">
      <i data-lucide="plus"></i> Add New Weight Entry
    </button>
    <button type="button" class="gym-tab-btn" id="btn-tab-history" onclick="switchProgressTab('tab-history', this)">
      <i data-lucide="history"></i> Weight Log History (<?php echo $total_entries; ?>)
    </button>
  </div>

  <!-- TAB 1: CHART ANALYTICS -->
  <div id="tab-chart" class="progress-tab-pane">
    <div class="gym-card">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="scale" style="color: var(--accent);"></i>
        Weight Transformation Analytics Chart
      </h2>

      <?php if ($total_entries >= 2): ?>
        <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 16px; height: 320px;">
          <canvas id="weightChartCanvas"></canvas>
        </div>
      <?php else: ?>
        <div style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
          <i data-lucide="line-chart" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
          <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">Not Enough Progress Logs Yet</p>
          <p style="font-size: 0.88rem; color: var(--text-dim); margin-top: 4px;">Log at least 2 weight entries to generate your transformation chart.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TAB 2: ADD WEIGHT ENTRY -->
  <div id="tab-add" class="progress-tab-pane" style="display: none;">
    <div class="gym-card" style="max-width: 580px;">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="plus-circle" style="color: var(--accent);"></i>
        Log New Body Weight Entry
      </h2>

      <form method="POST" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <input type="hidden" name="add_progress" value="1">

        <div>
          <label class="gym-form-label">Measurement Date *</label>
          <input type="date" name="progress_date" value="<?php echo date('Y-m-d'); ?>" class="gym-form-control" required>
        </div>

        <div>
          <label class="gym-form-label">Body Weight (kg) *</label>
          <input type="number" name="weight" step="0.1" min="20" max="300" placeholder="e.g. 72.5" class="gym-form-control" required>
        </div>

        <div>
          <label class="gym-form-label">Notes & Observations</label>
          <textarea name="notes" rows="3" placeholder="Describe energy levels, diet adherence, strength gains..." class="gym-form-control"></textarea>
        </div>

        <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%; min-height: 42px; margin-top: 6px;">
          <i data-lucide="save"></i> Save Weight Entry Log
        </button>
      </form>
    </div>
  </div>

  <!-- TAB 3: WEIGHT LOG HISTORY -->
  <div id="tab-history" class="progress-tab-pane" style="display: none;">
    <div class="gym-card">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="history" style="color: var(--accent);"></i>
        Weight History Log Roster (<?php echo $total_entries; ?>)
      </h2>

      <div class="gym-table-wrapper" style="margin-bottom: 0;">
        <table class="gym-table">
          <thead>
            <tr>
              <th># Log Entry</th>
              <th>Date Measured</th>
              <th>Recorded Weight</th>
              <th>Weight Delta</th>
              <th>Notes / Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($progress)): ?>
              <?php
              $reversed = array_reverse($progress);
              $total = count($progress);
              foreach ($reversed as $idx => $entry):
                $original_index = $total - 1 - $idx;
                $delta = 0;
                if ($original_index > 0) {
                    $prev = $progress[$original_index - 1];
                    $delta = $entry['weight'] - $prev['weight'];
                }
              ?>
                <tr>
                  <td style="font-weight: 700; color: var(--accent);">#<?php echo $total - $idx; ?></td>
                  <td style="color: var(--text-primary); font-weight: 600;"><?php echo date('F j, Y', strtotime($entry['progress_date'])); ?></td>
                  <td style="font-weight: 800; color: var(--accent-light); font-size: 1.05rem;"><?php echo number_format($entry['weight'], 1); ?> kg</td>
                  <td>
                    <?php if ($original_index > 0): ?>
                      <span style="font-weight: 700; color: <?php echo $delta < 0 ? '#4ade80' : ($delta > 0 ? '#f87171' : 'var(--text-dim)'); ?>;">
                        <?php echo ($delta > 0 ? '+' : '') . number_format($delta, 1); ?> kg
                      </span>
                    <?php else: ?>
                      <span style="color: var(--text-dim); font-size: 0.8rem;">Initial Log</span>
                    <?php endif; ?>
                  </td>
                  <td style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo !empty($entry['notes']) ? htmlspecialchars($entry['notes']) : '-'; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                  <i data-lucide="history" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                  <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No weight entries recorded yet.</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  const chartLabels = <?php echo json_encode($chart_labels); ?>;
  const chartWeights = <?php echo json_encode($chart_weights); ?>;

  document.addEventListener('DOMContentLoaded', function() {
      if (typeof lucide !== 'undefined') {
          lucide.createIcons();
      }

      if (chartLabels.length >= 2) {
          renderWeightChart();
      }
  });

  function switchProgressTab(tabId, btn) {
      document.querySelectorAll('.progress-tab-pane').forEach(pane => pane.style.display = 'none');
      document.querySelectorAll('.gym-tab-btn').forEach(b => b.classList.remove('active'));
      
      const target = document.getElementById(tabId);
      if (target) target.style.display = 'block';
      if (btn) btn.classList.add('active');
  }

  function renderWeightChart() {
      const canvas = document.getElementById('weightChartCanvas');
      if (!canvas) return;
      
      const ctx = canvas.getContext('2d');
      new Chart(ctx, {
          type: 'line',
          data: {
              labels: chartLabels,
              datasets: [{
                  label: 'Weight Transformation (kg)',
                  data: chartWeights,
                  borderColor: '#e8a012',
                  backgroundColor: 'rgba(232, 160, 18, 0.15)',
                  borderWidth: 3,
                  fill: true,
                  tension: 0.35,
                  pointBackgroundColor: '#e8a012',
                  pointBorderColor: '#0b0f19',
                  pointBorderWidth: 2,
                  pointRadius: 5
              }]
          },
          options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                  legend: {
                      labels: { color: '#e8ecf4', font: { family: 'Outfit', size: 13 } }
                  }
              },
              scales: {
                  x: {
                      grid: { color: 'rgba(255, 255, 255, 0.06)' },
                      ticks: { color: '#64748b' }
                  },
                  y: {
                      grid: { color: 'rgba(255, 255, 255, 0.06)' },
                      ticks: {
                          color: '#64748b',
                          callback: function(val) { return val + ' kg'; }
                      }
                  }
              }
          }
      });
  }
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
