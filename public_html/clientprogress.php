<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'trainer')) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$trainer_user_id = $_SESSION['user_id'];

// Get active clients
function getClients($conn) {
    $clients = [];
    $sql = "SELECT * FROM members WHERE member_type = 'client' ORDER BY full_name";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
    }
    return $clients;
}

// Get client progress history
function getClientProgressHistory($conn, $memberId) {
    $progressHistory = [];
    $stmt = $conn->prepare("SELECT * FROM client_progress WHERE member_id = ? ORDER BY progress_date ASC");
    if ($stmt) {
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $progressHistory[] = $row;
        }
        $stmt->close();
    }
    return $progressHistory;
}

$success_message = '';
$error_message = '';

// Process progress tracking form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_progress'])) {
    $member_id = (int)$_POST['member_id'];
    $weight = (float)$_POST['weight'];
    $notes = trim($_POST['notes']);
    
    $stmt = $conn->prepare("INSERT INTO client_progress (member_id, weight, notes, progress_date) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("ids", $member_id, $weight, $notes);
        if ($stmt->execute()) {
            $success_message = "Client progress log recorded successfully!";
        } else {
            $error_message = "Error saving progress log: " . $stmt->error;
        }
        $stmt->close();
    }
}

$clients = getClients($conn);

// Total records
$totalProgressRecords = 0;
$clientsWithProgressCount = 0;
$clientProgressData = [];

foreach ($clients as $client) {
    $history = getClientProgressHistory($conn, $client['id']);
    $count = count($history);
    $totalProgressRecords += $count;
    if ($count > 0) {
        $clientsWithProgressCount++;
        $clientProgressData[$client['id']] = [
            'name' => $client['full_name'],
            'labels' => array_map(function($p) { return date('M j, Y', strtotime($p['progress_date'])); }, $history),
            'weights' => array_map(function($p) { return (float)$p['weight']; }, $history)
        ];
    }
}

$page_title = "Client Progress Tracking — Boiyets Fitness Gym";
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
        Client Fitness Progress Tracking
      </h1>
      <p class="gym-page-subtitle">Record weight logs, track body transformation metrics, and monitor client fitness history over time.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="trainerworkout.php" class="gym-btn gym-btn-yellow">
        <i data-lucide="dumbbell"></i> Workout Plans
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

  <!-- KPI Statistics -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Clients</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo count($clients); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Registered gym members</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="users"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Log Records</div>
        <div class="gym-stat-number" style="color: #60a5fa;"><?php echo number_format($totalProgressRecords); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Weight & progress logs</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="activity"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Tracked Clients</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo number_format($clientsWithProgressCount); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">With progress history</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="user-check"></i>
      </div>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
    
    <!-- LEFT: RECORD PROGRESS FORM CARD -->
    <div class="gym-card" style="height: fit-content;">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="plus-circle" style="color: var(--accent);"></i>
        Record New Progress Log
      </h2>

      <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
        <input type="hidden" name="track_progress" value="1">

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
          <label class="gym-form-label">Body Weight (kg) *</label>
          <input type="number" name="weight" step="0.1" min="20" max="300" placeholder="e.g. 72.5" class="gym-form-control" required>
        </div>

        <div>
          <label class="gym-form-label">Progress Notes / Observations</label>
          <textarea name="notes" rows="3" placeholder="e.g. Lost 1.5kg this week, body fat reduced..." class="gym-form-control"></textarea>
        </div>

        <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%; min-height: 42px; margin-top: 6px;">
          <i data-lucide="save"></i> Save Progress Log
        </button>
      </form>
    </div>

    <!-- RIGHT: PROGRESS OVERVIEW & CHART CARD -->
    <div class="gym-card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 12px;">
        <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
          <i data-lucide="trending-up" style="color: #60a5fa;"></i>
          Progress History & Analytics
        </h2>

        <select id="clientSelector" class="gym-form-control" style="width: auto; min-width: 200px; margin: 0;">
          <option value="">Select client for chart...</option>
          <?php foreach ($clients as $client): ?>
            <?php if (!empty($clientProgressData[$client['id']])): ?>
              <option value="<?php echo $client['id']; ?>">
                <?php echo htmlspecialchars($client['full_name']); ?>
              </option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Line Chart Canvas Box -->
      <div id="chartContainer" style="display: none; background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 16px; margin-bottom: 1.5rem; height: 260px;">
        <canvas id="progressChart"></canvas>
      </div>

      <!-- History Breakdown Per Client -->
      <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        <?php $has_records = false; ?>
        <?php foreach ($clients as $client): ?>
          <?php $history = getClientProgressHistory($conn, $client['id']); ?>
          <?php if (!empty($history)): ?>
            <?php $has_records = true; ?>
            <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <strong style="color: var(--text-primary); font-family: 'Outfit', sans-serif; font-size: 1rem;"><?php echo htmlspecialchars($client['full_name']); ?></strong>
                <span style="font-size: 0.78rem; color: var(--accent); font-weight: 700;"><?php echo count($history); ?> log(s)</span>
              </div>

              <div class="gym-table-wrapper" style="margin-bottom: 0;">
                <table class="gym-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Weight</th>
                      <th>Notes / Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($history as $log): ?>
                      <tr>
                        <td style="color: var(--text-dim); font-size: 0.85rem;"><?php echo date('M j, Y', strtotime($log['progress_date'])); ?></td>
                        <td style="font-weight: 700; color: #4ade80;"><?php echo htmlspecialchars($log['weight']); ?> kg</td>
                        <td style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo !empty($log['notes']) ? htmlspecialchars($log['notes']) : '-'; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!$has_records): ?>
          <div style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
            <i data-lucide="activity" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
            <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No progress logs recorded yet.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
  const clientProgressData = <?php echo json_encode($clientProgressData); ?>;
  let progressChart = null;

  document.addEventListener('DOMContentLoaded', function() {
      if (typeof lucide !== 'undefined') {
          lucide.createIcons();
      }

      const selector = document.getElementById('clientSelector');
      if (selector) {
          selector.addEventListener('change', function() {
              const clientId = this.value;
              const container = document.getElementById('chartContainer');
              
              if (clientId && clientProgressData[clientId]) {
                  container.style.display = 'block';
                  renderProgressChart(clientProgressData[clientId]);
              } else {
                  container.style.display = 'none';
                  if (progressChart) {
                      progressChart.destroy();
                      progressChart = null;
                  }
              }
          });
      }
  });

  function renderProgressChart(clientData) {
      const ctx = document.getElementById('progressChart').getContext('2d');
      if (progressChart) {
          progressChart.destroy();
      }

      progressChart = new Chart(ctx, {
          type: 'line',
          data: {
              labels: clientData.labels,
              datasets: [{
                  label: clientData.name + ' Weight (kg)',
                  data: clientData.weights,
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
