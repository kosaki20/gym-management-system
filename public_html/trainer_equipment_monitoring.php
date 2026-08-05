<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'trainer') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$unread_count = 0;
if (file_exists(__DIR__ . '/chat_functions.php')) {
    require_once __DIR__ . '/chat_functions.php';
    $unread_count = getUnreadCount($_SESSION['user_id'], $conn);
}

// Handle equipment status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_equipment_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token invalid. Please try again.";
        header("Location: trainer_equipment_monitoring.php");
        exit();
    }

    $equipment_id = (int)$_POST['equipment_id'];
    $new_status = $_POST['status'];
    $note = trim($_POST['note']);
    $updated_by = $_SESSION['user_id'];

    $current_stmt = $conn->prepare("SELECT status FROM equipment WHERE id = ?");
    if ($current_stmt) {
        $current_stmt->bind_param("i", $equipment_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        
        if ($current_result->num_rows > 0) {
            $old_status = $current_result->fetch_assoc()['status'];

            $update_stmt = $conn->prepare("UPDATE equipment SET status = ?, last_updated = NOW() WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("si", $new_status, $equipment_id);
                if ($update_stmt->execute()) {
                    $log_stmt = $conn->prepare("INSERT INTO equipment_logs (equipment_id, old_status, new_status, updated_by, note) VALUES (?, ?, ?, ?, ?)");
                    if ($log_stmt) {
                        $log_stmt->bind_param("issis", $equipment_id, $old_status, $new_status, $updated_by, $note);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    $_SESSION['success'] = "Equipment status updated successfully!";
                } else {
                    $_SESSION['error'] = "Error updating equipment status: " . $update_stmt->error;
                }
                $update_stmt->close();
            }
        }
        $current_stmt->close();
    }
    header("Location: trainer_equipment_monitoring.php");
    exit();
}

// Handle facility status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_facility_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token invalid. Please try again.";
        header("Location: trainer_equipment_monitoring.php");
        exit();
    }

    $facility_id = (int)$_POST['facility_id'];
    $new_condition = $_POST['condition'];
    $notes = trim($_POST['notes']);
    $updated_by = $_SESSION['user_id'];

    $update_stmt = $conn->prepare("UPDATE facilities SET facility_condition = ?, notes = ?, last_updated = NOW(), updated_by = ? WHERE id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param("ssii", $new_condition, $notes, $updated_by, $facility_id);
        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Facility condition updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating facility condition: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
    header("Location: trainer_equipment_monitoring.php");
    exit();
}

// Filters & State
$tab = $_GET['tab'] ?? 'equipment';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$location_filter = $_GET['location'] ?? '';
$search = trim($_GET['search'] ?? '');

$categories_result = $conn->query("SELECT DISTINCT category FROM equipment ORDER BY category");
$locations_result = $conn->query("SELECT DISTINCT location FROM equipment ORDER BY location");

// Equipment Query
$equipment_where = ["1=1"];
$equipment_params = [];
$equipment_types = "";

if ($status_filter) {
    $equipment_where[] = "e.status = ?";
    $equipment_params[] = $status_filter;
    $equipment_types .= "s";
}
if ($category_filter) {
    $equipment_where[] = "e.category = ?";
    $equipment_params[] = $category_filter;
    $equipment_types .= "s";
}
if ($location_filter) {
    $equipment_where[] = "e.location = ?";
    $equipment_params[] = $location_filter;
    $equipment_types .= "s";
}
if ($search) {
    $equipment_where[] = "(e.name LIKE ? OR e.notes LIKE ?)";
    $equipment_params[] = "%$search%";
    $equipment_params[] = "%$search%";
    $equipment_types .= "ss";
}

$equipment_sql = "SELECT e.*, u.username as created_by_name FROM equipment e LEFT JOIN users u ON e.created_by = u.id WHERE " . implode(" AND ", $equipment_where) . " ORDER BY e.name ASC";
$equipment_stmt = $conn->prepare($equipment_sql);
if ($equipment_stmt && !empty($equipment_params)) {
    $equipment_stmt->bind_param($equipment_types, ...$equipment_params);
    $equipment_stmt->execute();
    $equipment_result = $equipment_stmt->get_result();
} else {
    $equipment_result = $conn->query($equipment_sql);
}

// Facilities Query
$facilities_sql = "SELECT f.*, u.username as updated_by_name FROM facilities f LEFT JOIN users u ON f.updated_by = u.id ORDER BY f.name ASC";
$facilities_result = $conn->query($facilities_sql);

// Statistics
$total_equip = $conn->query("SELECT COUNT(*) as total FROM equipment")->fetch_assoc()['total'] ?? 0;
$maint_equip = $conn->query("SELECT COUNT(*) as total FROM equipment WHERE status IN ('Needs Maintenance', 'Under Repair')")->fetch_assoc()['total'] ?? 0;
$broken_equip = $conn->query("SELECT COUNT(*) as total FROM equipment WHERE status = 'Broken'")->fetch_assoc()['total'] ?? 0;
$total_facilities = $conn->query("SELECT COUNT(*) as total FROM facilities")->fetch_assoc()['total'] ?? 0;

$page_title = "Equipment & Facility Monitoring — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="wrench" style="color: var(--accent);"></i>
        Trainer Equipment & Facility Inspection
      </h1>
      <p class="gym-page-subtitle">Inspect gym machine status, report equipment breakdowns, and log facility zone conditions.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="trainer_dashboard.php" class="gym-btn gym-btn-outline">
        <i data-lucide="arrow-left"></i> Dashboard
      </a>
    </div>
  </div>

  <!-- Flash Notifications -->
  <?php if (isset($_SESSION['success'])): ?>
    <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="check-circle-2" style="width: 18px; height: 18px; color: #22c55e;"></i>
      <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
    </div>
  <?php endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="alert-triangle" style="width: 18px; height: 18px; color: #ef4444;"></i>
      <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
    </div>
  <?php endif; ?>

  <!-- 4 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Equipment</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo number_format($total_equip); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Tracked gym assets</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="dumbbell"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Needs Maintenance</div>
        <div class="gym-stat-number" style="color: #f59e0b;"><?php echo number_format($maint_equip); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Pending repairs</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="wrench"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Out-of-Service</div>
        <div class="gym-stat-number" style="color: #ef4444;"><?php echo number_format($broken_equip); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Broken machines</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
        <i data-lucide="alert-octagon"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Monitored Zones</div>
        <div class="gym-stat-number" style="color: #3b82f6;"><?php echo number_format($total_facilities); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Gym facility areas</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="building"></i>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div class="gym-tabs-container" style="margin-bottom: 1.5rem;">
    <a href="?tab=equipment" class="gym-tab-btn <?php echo $tab == 'equipment' ? 'active' : ''; ?>">
      <i data-lucide="dumbbell"></i> Equipment Inventory (<?php echo $total_equip; ?>)
    </a>
    <a href="?tab=facilities" class="gym-tab-btn <?php echo $tab == 'facilities' ? 'active' : ''; ?>">
      <i data-lucide="building"></i> Facility Zones (<?php echo $total_facilities; ?>)
    </a>
  </div>

  <?php if ($tab == 'equipment'): ?>
    <!-- Equipment Filter Bar Card -->
    <div class="gym-card" style="margin-bottom: 1.5rem;">
      <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: flex-end;">
        <input type="hidden" name="tab" value="equipment">
        
        <div>
          <label class="gym-form-label">Category</label>
          <select name="category" class="gym-form-control">
            <option value="">All Categories</option>
            <?php if ($categories_result && $categories_result->num_rows > 0): ?>
              <?php 
              $categories_result->data_seek(0);
              while($cat = $categories_result->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cat['category']); ?>
                </option>
              <?php endwhile; ?>
            <?php endif; ?>
          </select>
        </div>

        <div>
          <label class="gym-form-label">Location</label>
          <select name="location" class="gym-form-control">
            <option value="">All Locations</option>
            <?php if ($locations_result && $locations_result->num_rows > 0): ?>
              <?php 
              $locations_result->data_seek(0);
              while($loc = $locations_result->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location_filter == $loc['location'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($loc['location']); ?>
                </option>
              <?php endwhile; ?>
            <?php endif; ?>
          </select>
        </div>

        <div>
          <label class="gym-form-label">Status</label>
          <select name="status" class="gym-form-control">
            <option value="">All Statuses</option>
            <option value="Good" <?php echo $status_filter == 'Good' ? 'selected' : ''; ?>>Good Condition</option>
            <option value="Needs Maintenance" <?php echo $status_filter == 'Needs Maintenance' ? 'selected' : ''; ?>>Needs Maintenance</option>
            <option value="Under Repair" <?php echo $status_filter == 'Under Repair' ? 'selected' : ''; ?>>Under Repair</option>
            <option value="Broken" <?php echo $status_filter == 'Broken' ? 'selected' : ''; ?>>Broken / Unusable</option>
          </select>
        </div>

        <div>
          <label class="gym-form-label">Search Keyword</label>
          <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search machine name..." class="gym-form-control">
        </div>

        <div>
          <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%;">
            <i data-lucide="filter"></i> Filter Results
          </button>
        </div>
      </form>
    </div>

    <!-- Equipment Table Card -->
    <div class="gym-card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
          <i data-lucide="list" style="color: var(--accent);"></i>
          Equipment Inventory List
        </h2>
      </div>

      <div class="gym-table-wrapper" style="margin-bottom: 0;">
        <table class="gym-table">
          <thead>
            <tr>
              <th>Equipment Name</th>
              <th>Category</th>
              <th>Location</th>
              <th>Status</th>
              <th>Last Updated</th>
              <th style="text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($equipment_result && $equipment_result->num_rows > 0): ?>
              <?php while($equipment = $equipment_result->fetch_assoc()): ?>
                <?php
                $status_badge = '';
                switch($equipment['status']) {
                  case 'Good':
                    $status_badge = '<span class="gym-badge gym-badge-active">Good</span>';
                    break;
                  case 'Needs Maintenance':
                    $status_badge = '<span class="gym-badge gym-badge-pending">Needs Maintenance</span>';
                    break;
                  case 'Under Repair':
                    $status_badge = '<span class="gym-badge gym-badge-info">Under Repair</span>';
                    break;
                  case 'Broken':
                    $status_badge = '<span class="gym-badge gym-badge-inactive">Broken</span>';
                    break;
                  default:
                    $status_badge = '<span class="gym-badge">' . htmlspecialchars($equipment['status']) . '</span>';
                }
                ?>
                <tr>
                  <td>
                    <div style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($equipment['name']); ?></div>
                    <?php if (!empty($equipment['notes'])): ?>
                      <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 2px;"><?php echo htmlspecialchars($equipment['notes']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($equipment['category']); ?></td>
                  <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($equipment['location']); ?></td>
                  <td><?php echo $status_badge; ?></td>
                  <td style="color: var(--text-dim); font-size: 0.84rem;">
                    <?php echo date('M j, Y g:i A', strtotime($equipment['last_updated'])); ?>
                  </td>
                  <td>
                    <div style="display: flex; gap: 6px; align-items: center; justify-content: center;">
                      <button type="button" onclick="openUpdateStatusModal(<?php echo htmlspecialchars(json_encode($equipment)); ?>)" class="gym-btn gym-btn-outline" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important; color: #60a5fa !important; border-color: rgba(96, 165, 250, 0.3) !important;" title="Update Status">
                        <i data-lucide="edit" style="width: 14px; height: 14px;"></i> Update Status
                      </button>
                      <button type="button" onclick="openViewLogsModal(<?php echo $equipment['id']; ?>)" class="gym-btn gym-btn-outline" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important; color: #4ade80 !important; border-color: rgba(74, 222, 128, 0.3) !important;" title="View History Logs">
                        <i data-lucide="history" style="width: 14px; height: 14px;"></i> History
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                  <i data-lucide="dumbbell" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                  <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No equipment records found matching filter criteria.</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab == 'facilities'): ?>

    <!-- Facilities Table Card -->
    <div class="gym-card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
          <i data-lucide="building" style="color: #3b82f6;"></i>
          Gym Facility Zones & Condition Status
        </h2>
      </div>

      <div class="gym-table-wrapper" style="margin-bottom: 0;">
        <table class="gym-table">
          <thead>
            <tr>
              <th>Facility Zone</th>
              <th>Condition</th>
              <th>Notes / Remarks</th>
              <th>Last Inspection Date</th>
              <th style="text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($facilities_result && $facilities_result->num_rows > 0): ?>
              <?php while($facility = $facilities_result->fetch_assoc()): ?>
                <?php
                $cond_badge = '';
                switch($facility['facility_condition']) {
                  case 'Good':
                    $cond_badge = '<span class="gym-badge gym-badge-active">Good</span>';
                    break;
                  case 'Needs Maintenance':
                    $cond_badge = '<span class="gym-badge gym-badge-pending">Needs Maintenance</span>';
                    break;
                  case 'Under Repair':
                    $cond_badge = '<span class="gym-badge gym-badge-info">Under Repair</span>';
                    break;
                  case 'Closed':
                    $cond_badge = '<span class="gym-badge gym-badge-inactive">Closed</span>';
                    break;
                  default:
                    $cond_badge = '<span class="gym-badge">' . htmlspecialchars($facility['facility_condition']) . '</span>';
                }
                ?>
                <tr>
                  <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($facility['name']); ?></td>
                  <td><?php echo $cond_badge; ?></td>
                  <td style="color: var(--text-secondary);">
                    <?php echo !empty($facility['notes']) ? htmlspecialchars($facility['notes']) : '<span style="color: var(--text-dim);">-</span>'; ?>
                  </td>
                  <td style="color: var(--text-dim); font-size: 0.84rem;">
                    <?php echo date('M j, Y g:i A', strtotime($facility['last_updated'])); ?>
                    <?php if (!empty($facility['updated_by_name'])): ?>
                      <span style="display: block; font-size: 0.75rem; color: var(--accent);">by <?php echo htmlspecialchars($facility['updated_by_name']); ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display: flex; justify-content: center;">
                      <button type="button" onclick="openUpdateFacilityModal(<?php echo htmlspecialchars(json_encode($facility)); ?>)" class="gym-btn gym-btn-outline" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important; color: #60a5fa !important; border-color: rgba(96, 165, 250, 0.3) !important;" title="Update Condition">
                        <i data-lucide="edit" style="width: 14px; height: 14px;"></i> Update Zone
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                  <i data-lucide="building" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                  <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No facility records found.</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- Update Equipment Status Modal -->
  <div id="updateStatusModal" class="modal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.7); align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); width: 100%; max-width: 480px; padding: 24px; margin: auto;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.2rem; color: #60a5fa; margin: 0; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="edit"></i> Update Equipment Status
        </h3>
        <button type="button" onclick="closeUpdateStatusModal()" style="background: transparent; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.2rem;">
          <i data-lucide="x"></i>
        </button>
      </div>

      <form method="POST" style="display: flex; flex-direction: column; gap: 14px;">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="equipment_id" id="update_equipment_id">

        <div>
          <label class="gym-form-label">Machine Name</label>
          <input type="text" id="equipment_name_display" class="gym-form-control" readonly style="opacity: 0.7;">
        </div>

        <div>
          <label class="gym-form-label">New Operational Status *</label>
          <select name="status" id="new_status" required class="gym-form-control">
            <option value="Good">Good Condition</option>
            <option value="Needs Maintenance">Needs Maintenance</option>
            <option value="Under Repair">Under Repair</option>
            <option value="Broken">Broken</option>
          </select>
        </div>

        <div>
          <label class="gym-form-label">Inspection / Repair Notes</label>
          <textarea name="note" id="status_note" rows="3" class="gym-form-control" placeholder="Describe the issue or maintenance work done..."></textarea>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 10px;">
          <button type="button" onclick="closeUpdateStatusModal()" class="gym-btn gym-btn-outline" style="flex: 1;">Cancel</button>
          <button type="submit" name="update_equipment_status" class="gym-btn gym-btn-yellow" style="flex: 1;">Update Status</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Update Facility Condition Modal -->
  <div id="updateFacilityModal" class="modal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.7); align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); width: 100%; max-width: 480px; padding: 24px; margin: auto;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.2rem; color: #3b82f6; margin: 0; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="building"></i> Update Facility Condition
        </h3>
        <button type="button" onclick="closeUpdateFacilityModal()" style="background: transparent; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.2rem;">
          <i data-lucide="x"></i>
        </button>
      </div>

      <form method="POST" style="display: flex; flex-direction: column; gap: 14px;">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="facility_id" id="update_facility_id">

        <div>
          <label class="gym-form-label">Facility Zone Name</label>
          <input type="text" id="facility_name_display" class="gym-form-control" readonly style="opacity: 0.7;">
        </div>

        <div>
          <label class="gym-form-label">New Condition *</label>
          <select name="condition" id="new_condition" required class="gym-form-control">
            <option value="Good">Good Condition</option>
            <option value="Needs Maintenance">Needs Maintenance</option>
            <option value="Under Repair">Under Repair</option>
            <option value="Closed">Closed</option>
          </select>
        </div>

        <div>
          <label class="gym-form-label">Inspection Notes</label>
          <textarea name="notes" id="facility_notes" rows="3" class="gym-form-control" placeholder="Describe the zone condition or maintenance details..."></textarea>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 10px;">
          <button type="button" onclick="closeUpdateFacilityModal()" class="gym-btn gym-btn-outline" style="flex: 1;">Cancel</button>
          <button type="submit" name="update_facility_status" class="gym-btn gym-btn-yellow" style="flex: 1;">Update Condition</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View History Logs Modal -->
  <div id="viewLogsModal" class="modal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.7); align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); width: 100%; max-width: 640px; padding: 24px; margin: auto;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.2rem; color: #4ade80; margin: 0; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="history"></i> Equipment Status History
        </h3>
        <button type="button" onclick="closeViewLogsModal()" style="background: transparent; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.2rem;">
          <i data-lucide="x"></i>
        </button>
      </div>

      <div id="logsContent" style="max-height: 380px; overflow-y: auto; padding-right: 4px;">
        <div style="text-align: center; padding: 2rem; color: var(--text-dim);">
          <p>Loading status history...</p>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
        <button type="button" onclick="closeViewLogsModal()" class="gym-btn gym-btn-outline">Close</button>
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

  function openUpdateStatusModal(equipment) {
      const modal = document.getElementById('updateStatusModal');
      if (modal) {
          modal.style.display = 'flex';
          document.getElementById('update_equipment_id').value = equipment.id;
          document.getElementById('equipment_name_display').value = equipment.name;
          document.getElementById('new_status').value = equipment.status;
          document.getElementById('status_note').value = '';
      }
  }

  function closeUpdateStatusModal() {
      const modal = document.getElementById('updateStatusModal');
      if (modal) modal.style.display = 'none';
  }

  function openUpdateFacilityModal(facility) {
      const modal = document.getElementById('updateFacilityModal');
      if (modal) {
          modal.style.display = 'flex';
          document.getElementById('update_facility_id').value = facility.id;
          document.getElementById('facility_name_display').value = facility.name;
          document.getElementById('new_condition').value = facility.facility_condition;
          document.getElementById('facility_notes').value = facility.notes || '';
      }
  }

  function closeUpdateFacilityModal() {
      const modal = document.getElementById('updateFacilityModal');
      if (modal) modal.style.display = 'none';
  }

  function openViewLogsModal(equipmentId) {
      const modal = document.getElementById('viewLogsModal');
      const logsContent = document.getElementById('logsContent');
      if (modal) {
          modal.style.display = 'flex';
          logsContent.innerHTML = '<div style="text-align:center; padding: 2rem; color: var(--text-dim);">Loading history...</div>';
          
          fetch(`equipment_ajax.php?action=get_logs&equipment_id=${equipmentId}`)
              .then(res => res.json())
              .then(data => {
                  if (data.success && data.logs && data.logs.length > 0) {
                      let html = '<div style="display:flex; flex-direction:column; gap: 10px;">';
                      data.logs.forEach(log => {
                          html += `
                              <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px 14px;">
                                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 6px;">
                                      <span style="font-size: 0.82rem; font-weight:700; color: var(--text-secondary);">${log.old_status} ➔ <strong style="color: var(--accent);">${log.new_status}</strong></span>
                                      <span style="font-size: 0.75rem; color: var(--text-dim);">${log.formatted_date || log.date_updated}</span>
                                  </div>
                                  ${log.note ? `<div style="font-size: 0.85rem; color: var(--text-primary); margin-bottom: 4px;">${log.note}</div>` : ''}
                                  <div style="font-size: 0.72rem; color: var(--text-dim);">Logged by: ${log.updated_by_name}</div>
                              </div>
                          `;
                      });
                      html += '</div>';
                      logsContent.innerHTML = html;
                  } else {
                      logsContent.innerHTML = '<div style="text-align:center; padding: 2rem; color: var(--text-dim);">No history logs found for this equipment.</div>';
                  }
              })
              .catch(err => {
                  logsContent.innerHTML = '<div style="text-align:center; padding: 2rem; color: var(--red);">Error loading status history logs.</div>';
              });
      }
  }

  function closeViewLogsModal() {
      const modal = document.getElementById('viewLogsModal');
      if (modal) modal.style.display = 'none';
  }

  window.onclick = function(event) {
      const modals = ['updateStatusModal', 'updateFacilityModal', 'viewLogsModal'];
      modals.forEach(modalId => {
          const modal = document.getElementById(modalId);
          if (event.target === modal) modal.style.display = 'none';
      });
  };
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
