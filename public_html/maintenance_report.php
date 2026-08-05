<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'trainer')) {
    header("Location: index.php");
    exit();
}

// Database connection
require_once __DIR__ . '/../config/config.php';

// Include chat functions if the file exists
$unread_count = 0;
if (file_exists('chat_functions.php')) {
    require_once 'chat_functions.php';
    $unread_count = getUnreadCount($_SESSION['user_id'], $conn);
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$export = $_GET['export'] ?? '';

// Build query for equipment needing attention
$where_conditions = ["e.status IN ('Needs Maintenance', 'Under Repair', 'Broken')"];
$params = [];
$types = "";

if ($status_filter) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($category_filter) {
    $where_conditions[] = "e.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_conditions);
// Get maintenance equipment - SIMPLIFIED
$maintenance_sql = "SELECT e.*, u.username as updated_by_name,
                    el.note as last_note, el.date_updated as last_log_date
                    FROM equipment e 
                    LEFT JOIN users u ON e.created_by = u.id 
                    LEFT JOIN equipment_logs el ON e.id = el.equipment_id 
                    WHERE $where_sql 
                    ORDER BY e.last_updated DESC, e.name ASC";

$maintenance_stmt = $conn->prepare($maintenance_sql);
if ($maintenance_stmt && !empty($params)) {
    $maintenance_stmt->bind_param($types, ...$params);
    $maintenance_stmt->execute();
    $maintenance_result = $maintenance_stmt->get_result();
} else {
    $maintenance_result = $conn->query($maintenance_sql);
}

// Get facilities needing attention - FIXED: using correct column name 'facility_condition'
$facilities_sql = "SELECT f.*, u.username as updated_by_name 
                   FROM facilities f 
                   LEFT JOIN users u ON f.updated_by = u.id 
                   WHERE f.facility_condition IN ('Needs Maintenance', 'Under Repair', 'Closed')
                   ORDER BY f.name ASC";
$facilities_result = $conn->query($facilities_sql);

// Get unique categories for filter
$categories_result = $conn->query("SELECT DISTINCT category FROM equipment ORDER BY category");

// Get maintenance statistics
$stats_sql = "SELECT 
                COUNT(*) as total_issues,
                SUM(CASE WHEN status = 'Needs Maintenance' THEN 1 ELSE 0 END) as needs_maintenance,
                SUM(CASE WHEN status = 'Under Repair' THEN 1 ELSE 0 END) as under_repair,
                SUM(CASE WHEN status = 'Broken' THEN 1 ELSE 0 END) as broken
              FROM equipment 
              WHERE status IN ('Needs Maintenance', 'Under Repair', 'Broken')";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Handle export
if ($export == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="maintenance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Equipment Name', 'Category', 'Location', 'Status', 'Last Updated', 'Note']);
    
    // Reset and re-fetch equipment data for export
    $export_sql = "SELECT e.* FROM equipment e WHERE $where_sql ORDER BY e.name ASC";
    $export_stmt = $conn->prepare($export_sql);
    if ($export_stmt && !empty($params)) {
        $export_stmt->bind_param($types, ...$params);
        $export_stmt->execute();
        $export_result = $export_stmt->get_result();
    } else {
        $export_result = $conn->query($export_sql);
    }
    
    while($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['name'],
            $row['category'],
            $row['location'],
            $row['status'],
            $row['last_updated'],
            $row['notes'] ?: 'No notes'
        ]);
    }
    fclose($output);
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'trainer';
?>

<?php
$page_title = "Maintenance Report — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="clipboard-list" style="color: var(--accent);"></i>
        Equipment Maintenance Report & Audit
      </h1>
      <p class="gym-page-subtitle">Summary of all equipment and facility areas requiring maintenance, active repairs, or replacements.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
      <a href="equipment_monitoring.php" class="gym-btn gym-btn-outline">
        <i data-lucide="wrench"></i> Equipment Monitoring
      </a>
    </div>
  </div>

  <!-- KPI Statistics Grid -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card" style="border-top-color: var(--red);">
      <div>
        <div class="gym-stat-label">Total Maintenance Issues</div>
        <div class="gym-stat-number" style="color: var(--red);"><?php echo number_format($stats['total_issues'] ?? 0); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Items needing attention</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: var(--red); border-color: rgba(239, 68, 68, 0.3);">
        <i data-lucide="alert-triangle"></i>
      </div>
    </div>

    <div class="gym-stat-card" style="border-top-color: #f59e0b;">
      <div>
        <div class="gym-stat-label">Needs Maintenance</div>
        <div class="gym-stat-number" style="color: #f59e0b;"><?php echo number_format($stats['needs_maintenance'] ?? 0); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Scheduled for checkup</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="tool"></i>
      </div>
    </div>

    <div class="gym-stat-card" style="border-top-color: #3b82f6;">
      <div>
        <div class="gym-stat-label">Under Repair</div>
        <div class="gym-stat-number" style="color: #3b82f6;"><?php echo number_format($stats['under_repair'] ?? 0); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Active repair work</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="wrench"></i>
      </div>
    </div>

    <div class="gym-stat-card" style="border-top-color: var(--red);">
      <div>
        <div class="gym-stat-label">Broken / Out of Service</div>
        <div class="gym-stat-number" style="color: var(--red);"><?php echo number_format($stats['broken'] ?? 0); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Decommissioned assets</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: var(--red); border-color: rgba(239, 68, 68, 0.3);">
        <i data-lucide="x-circle"></i>
      </div>
    </div>
  </div>

      <!-- Action Bar -->
      <div class="card">
        <div class="flex flex-wrap gap-2 justify-between mb-4">
          <div class="flex gap-2">
            <!-- Export Dropdown -->
            <div class="export-container">
              <button id="exportButton" class="button-sm btn-outline">
                <i data-lucide="download"></i> Export Report
                <i data-lucide="chevron-down" class="w-4 h-4"></i>
              </button>
              <div id="exportDropdown" class="export-dropdown">
                <div class="dropdown-header">
                  <h3>Export Options</h3>
                  <p>Choose export format</p>
                </div>
                <a href="?export=csv&status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>" class="export-option">
                  <i data-lucide="file-spreadsheet"></i> Export as CSV
                </a>
              </div>
            </div>
          </div>
          <div class="flex gap-2">
            <!-- Quick Status Filters -->
            <a href="?status=" class="button-sm <?php echo empty($status_filter) ? 'btn-active' : 'btn-outline'; ?>">
              All Issues
            </a>
            <a href="?status=Needs Maintenance" class="button-sm <?php echo $status_filter == 'Needs Maintenance' ? 'btn-active' : 'btn-outline'; ?>">
              Maintenance
            </a>
            <a href="?status=Under Repair" class="button-sm <?php echo $status_filter == 'Under Repair' ? 'btn-active' : 'btn-outline'; ?>">
              Under Repair
            </a>
            <a href="?status=Broken" class="button-sm <?php echo $status_filter == 'Broken' ? 'btn-active' : 'btn-outline'; ?>">
              Broken
            </a>
          </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4" id="filterForm">
          <div>
            <label class="form-label">Category</label>
            <select name="category" class="form-input">
              <option value="">All Categories</option>
              <?php 
              $categories_result->data_seek(0); // Reset pointer
              while($cat = $categories_result->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cat['category']); ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="flex items-end">
            <button type="submit" class="button-sm gym-btn gym-btn-yellow w-full">
              <i data-lucide="filter"></i> Apply Filters
            </button>
          </div>
         <div class="flex items-end">
  <a href="maintenance_report.php" class="button-sm btn-outline w-full">
    <i data-lucide="refresh-cw"></i> Clear Filters
  </a>
</div>
        </form>
      </div>

<!-- Section Header -->
<div class="flex items-center gap-2 mb-4">
  <div class="h-0.5 flex-1 bg-gray-700"></div>
  <span class="text-sm font-semibold text-yellow-400 px-4">EQUIPMENT MAINTENANCE</span>
  <div class="h-0.5 flex-1 bg-gray-700"></div>
</div>
      <!-- Equipment Maintenance Section -->
      <div class="card">
        <h2 class="text-lg font-semibold text-yellow-400 mb-4 flex items-center gap-2">
          <i data-lucide="dumbbell"></i>
          Equipment Needing Attention (<?php echo $maintenance_result ? $maintenance_result->num_rows : 0; ?>)
        </h2>
        
        <?php if ($maintenance_result && $maintenance_result->num_rows > 0): ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-400 gym-table">
              <thead class="text-xs text-gray-400 uppercase bg-card">
                <tr>
                  <th class="p-4">Equipment</th>
                  <th class="p-4">Category</th>
                  <th class="p-4">Location</th>
                  <th class="p-4">Status</th>
                  <th class="p-4">Priority</th> <!-- ADD THIS COLUMN -->
                  <th class="p-4">Last Updated</th>
                  <th class="p-4">Issue Details</th>
                  <th class="p-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while($equipment = $maintenance_result->fetch_assoc()): ?>
                <tr class="border-b border-gray-700 hover:bg-card">
                  <td class="p-4">
                    <div class="font-medium "><?php echo htmlspecialchars($equipment['name']); ?></div>
                    <?php if (!empty($equipment['notes'])): ?>
                      <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($equipment['notes']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="p-4"><?php echo htmlspecialchars($equipment['category']); ?></td>
                  <td class="p-4"><?php echo htmlspecialchars($equipment['location']); ?></td>
                  <td class="p-4">
  <?php
    $status_class = '';
    switch($equipment['status']) {
      case 'Needs Maintenance': $status_class = 'badge-needs-maintenance'; break;
      case 'Under Repair': $status_class = 'badge-under-repair'; break;
      case 'Broken': $status_class = 'badge-broken'; break;
    }
  ?>
  <span class="badge <?php echo $status_class; ?>">
    <span class="status-indicator status-<?php echo strtolower(str_replace(' ', '-', $equipment['status'])); ?>"></span>
    <?php echo htmlspecialchars($equipment['status']); ?>
  </span>
</td>

<!-- ADD PRIORITY COLUMN HERE -->
<td class="p-4">
  <?php
    $priority = '';
    $priority_class = '';
    if ($equipment['status'] == 'Broken') {
      $priority = 'High';
      $priority_class = 'badge-broken';
    } elseif ($equipment['status'] == 'Under Repair') {
      $priority = 'Medium';
      $priority_class = 'badge-under-repair';
    } else {
      $priority = 'Low';
      $priority_class = 'badge-needs-maintenance';
    }
  ?>
  <span class="badge <?php echo $priority_class; ?>">
    <?php echo $priority; ?>
  </span>
</td>
<!-- END PRIORITY COLUMN -->

<td class="p-4 whitespace-nowrap">
  <?php echo date('M j, Y g:i A', strtotime($equipment['last_updated'])); ?>
  <?php if (!empty($equipment['updated_by_name'])): ?>
    <div class="text-xs text-gray-400">by <?php echo htmlspecialchars($equipment['updated_by_name']); ?></div>
  <?php endif; ?>
</td>
                  <td class="p-4 whitespace-nowrap">
                    <?php echo date('M j, Y g:i A', strtotime($equipment['last_updated'])); ?>
                    <?php if (!empty($equipment['updated_by_name'])): ?>
                      <div class="text-xs text-gray-400">by <?php echo htmlspecialchars($equipment['updated_by_name']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="p-4">
                    <?php 
                      $issue_note = !empty($equipment['last_note']) ? $equipment['last_note'] : $equipment['notes'];
                      echo $issue_note ? htmlspecialchars($issue_note) : '<span class="text-gray-500">No details</span>';
                    ?>
                  </td>
                  <td class="p-4">
                    <a href="equipment_monitoring.php?tab=equipment" class="text-blue-400 hover:text-blue-300 transition-colors" title="Update Status">
                      <i data-lucide="edit" class="w-4 h-4"></i>
                    </a>
                  </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i data-lucide="check-circle" class="w-12 h-12 mx-auto text-green-400"></i>
            <p>No maintenance issues found!</p>
            <p class="text-sm mt-2">All equipment is in good condition</p>
          </div>
        <?php endif; ?>
      </div>
<!-- Section Header -->
<div class="flex items-center gap-2 mb-4 mt-8">
  <div class="h-0.5 flex-1 bg-gray-700"></div>
  <span class="text-sm font-semibold text-yellow-400 px-4">FACILITY MAINTENANCE</span>
  <div class="h-0.5 flex-1 bg-gray-700"></div>
</div>
      <!-- Facilities Maintenance Section -->
      <div class="card">
        <h2 class="text-lg font-semibold text-yellow-400 mb-4 flex items-center gap-2">
          <i data-lucide="building"></i>
          Facilities Needing Attention (<?php echo $facilities_result ? $facilities_result->num_rows : 0; ?>)
        </h2>
        
        <?php if ($facilities_result && $facilities_result->num_rows > 0): ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-400 gym-table">
              <thead class="text-xs text-gray-400 uppercase bg-card">
                <tr>
                  <th class="p-4">Facility</th>
                  <th class="p-4">Condition</th>
                  <th class="p-4">Issue Details</th>
                  <th class="p-4">Last Updated</th>
                  <th class="p-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while($facility = $facilities_result->fetch_assoc()): ?>
                <tr class="border-b border-gray-700 hover:bg-card">
                  <td class="p-4 font-medium "><?php echo htmlspecialchars($facility['name']); ?></td>
                 <td class="p-4">
    <?php
        $condition_class = '';
        switch($facility['facility_condition']) {  // ← Changed to 'facility_condition'
          case 'Needs Maintenance': $condition_class = 'badge-needs-maintenance'; break;
          case 'Under Repair': $condition_class = 'badge-under-repair'; break;
          case 'Closed': $condition_class = 'badge-closed'; break;
          case 'Good': $condition_class = 'badge-good'; break;  // Added for completeness
        }
    ?>
    <span class="badge <?php echo $condition_class; ?>">
        <span class="status-indicator status-<?php echo strtolower(str_replace(' ', '-', $facility['facility_condition'])); ?>"></span>
        <?php echo htmlspecialchars($facility['facility_condition']); ?>
    </span>
</td>
                  <td class="p-4">
                    <?php echo !empty($facility['notes']) ? htmlspecialchars($facility['notes']) : '<span class="text-gray-500">No details</span>'; ?>
                  </td>
                  <td class="p-4 whitespace-nowrap">
                    <?php echo date('M j, Y g:i A', strtotime($facility['last_updated'])); ?>
                    <?php if (!empty($facility['updated_by_name'])): ?>
                      <div class="text-xs text-gray-400">by <?php echo htmlspecialchars($facility['updated_by_name']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="p-4">
                    <a href="equipment_monitoring.php?tab=facilities" class="text-blue-400 hover:text-blue-300 transition-colors" title="Update Condition">
                      <i data-lucide="edit" class="w-4 h-4"></i>
                    </a>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
       <?php else: ?>
  <div class="empty-state">
    <i data-lucide="check-circle" class="w-12 h-12 mx-auto text-green-400"></i>
    <p>All facilities are operational!</p>
    <p class="text-sm mt-2">No maintenance issues reported</p>
  </div>
<?php endif; ?>
      </div>

    
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();
        
        // Sidebar toggle
        document.getElementById('toggleSidebar').addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('w-60')) {
                sidebar.classList.remove('w-60');
                sidebar.classList.add('w-16', 'sidebar-collapsed');
            } else {
                sidebar.classList.remove('w-16', 'sidebar-collapsed');
                sidebar.classList.add('w-60');
            }
        });

        // Export dropdown functionality
        const exportButton = document.getElementById('exportButton');
        const exportDropdown = document.getElementById('exportDropdown');

        exportButton.addEventListener('click', (e) => {
            e.stopPropagation();
            exportDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!exportButton.contains(e.target) && !exportDropdown.contains(e.target)) {
                exportDropdown.classList.remove('show');
            }
        });

        // Dropdown functionality
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');

        userMenuButton.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        });
    });
  </script>
<?php require_once __DIR__ . "/includes/footer.php"; ?>

<?php 
// Close statements and connection properly
if (isset($maintenance_stmt)) $maintenance_stmt->close();
if (isset($maintenance_result)) $maintenance_result->free();
if (isset($facilities_result)) $facilities_result->free();
if (isset($stats_result)) $stats_result->free();
if (isset($categories_result)) $categories_result->free();
$conn->close(); 
?>
