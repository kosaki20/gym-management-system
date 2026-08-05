
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'trainer')) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';

// Check connection

// ADD THIS SECTION FOR CHAT FUNCTIONALITY
require_once 'chat_functions.php';
$unread_count = getUnreadCount($_SESSION['user_id'], $conn);

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$member_type_filter = isset($_GET['member_type']) ? $conn->real_escape_string($_GET['member_type']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

// Build WHERE clause for filters
$where_conditions = [];
$query_params = [];

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR contact_number LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $query_params = array_merge($query_params, [$search_term, $search_term, $search_term]);
}

if (!empty($member_type_filter)) {
    $where_conditions[] = "member_type = ?";
    $query_params[] = $member_type_filter;
}

if (!empty($status_filter)) {
    $today = date('Y-m-d');
    switch($status_filter) {
        case 'active':
            $where_conditions[] = "status = 'active' AND expiry_date >= ?";
            $query_params[] = $today;
            break;
        case 'expiring':
            $where_conditions[] = "status = 'active' AND expiry_date >= ? AND expiry_date <= DATE_ADD(?, INTERVAL 7 DAY)";
            $query_params[] = $today;
            $query_params[] = $today;
            break;
        case 'expired':
            $where_conditions[] = "(status = 'expired' OR expiry_date < ?)";
            $query_params[] = $today;
            break;
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM members $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($query_params)) {
    $count_stmt->bind_param(str_repeat('s', count($query_params)), ...$query_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get members with pagination and filters
$members_sql = "SELECT m.*, u.email, u.username 
                FROM members m 
                LEFT JOIN users u ON m.user_id = u.id 
                $where_clause 
                ORDER BY m.full_name 
                LIMIT ? OFFSET ?";

$members_stmt = $conn->prepare($members_sql);
$query_params_with_pagination = $query_params;
$query_params_with_pagination[] = $records_per_page;
$query_params_with_pagination[] = $offset;

if (!empty($query_params_with_pagination)) {
    $param_types = str_repeat('s', count($query_params)) . 'ii';
    $members_stmt->bind_param($param_types, ...$query_params_with_pagination);
} else {
    $members_stmt->bind_param('ii', $records_per_page, $offset);
}

$members_stmt->execute();
$members_result = $members_stmt->get_result();
$allMembers = [];

while ($row = $members_result->fetch_assoc()) {
    // Calculate days left
    $expiry = new DateTime($row['expiry_date']);
    $today = new DateTime();
    $daysLeft = $today->diff($expiry)->days;
    if ($today > $expiry) {
        $daysLeft = -$daysLeft;
    }
    $row['days_left'] = $daysLeft;
    $allMembers[] = $row;
}

// Handle member view request
$view_member = null;
if (isset($_GET['view_member_id'])) {
    $member_id = (int)$_GET['view_member_id'];
    $view_sql = "SELECT m.*, u.email, u.username, u.client_type 
                 FROM members m 
                 LEFT JOIN users u ON m.user_id = u.id 
                 WHERE m.id = ?";
    $view_stmt = $conn->prepare($view_sql);
    $view_stmt->bind_param("i", $member_id);
    $view_stmt->execute();
    $view_result = $view_stmt->get_result();
    $view_member = $view_result->fetch_assoc();
    
    if ($view_member) {
        // Calculate days left for viewed member
        $expiry = new DateTime($view_member['expiry_date']);
        $today = new DateTime();
        $daysLeft = $today->diff($expiry)->days;
        if ($today > $expiry) {
            $daysLeft = -$daysLeft;
        }
        $view_member['days_left'] = $daysLeft;
    }
}
?>

<?php
$page_title = "All Members - Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="users" style="color: var(--accent);"></i>
        Member Management Directory
      </h1>
      <p class="gym-page-subtitle">View, search, filter, and manage all active, walk-in, and expired gym memberships.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
      <a href="member_registration.php?type=walk-in" class="gym-btn gym-btn-yellow">
        <i data-lucide="user-plus"></i> Add Walk-in
      </a>
      <a href="member_registration.php?type=client" class="gym-btn gym-btn-outline">
        <i data-lucide="user-check"></i> Add Client Member
      </a>
    </div>
  </div>

  <!-- KPI Statistics Grid -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Members</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo number_format($total_records); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Registered directory count</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="users"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Active Members</div>
        <div class="gym-stat-number" style="color: #22c55e;">
          <?php 
            $activeCount = array_filter($allMembers, function($member) { return $member['days_left'] > 7; });
            echo count($activeCount);
          ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Good standing</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #22c55e; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="user-check"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Expiring Soon</div>
        <div class="gym-stat-number" style="color: var(--accent);">
          <?php 
            $expiringCount = array_filter($allMembers, function($member) { return $member['days_left'] > 0 && $member['days_left'] <= 7; });
            echo count($expiringCount);
          ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Within 7 days</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="clock"></i>
      </div>
    </div>

    <div class="gym-stat-card" style="border-top-color: var(--red);">
      <div>
        <div class="gym-stat-label">Expired</div>
        <div class="gym-stat-number" style="color: var(--red);">
          <?php 
            $expiredCount = array_filter($allMembers, function($member) { return $member['days_left'] <= 0; });
            echo count($expiredCount);
          ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Renewal required</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: var(--red); border-color: rgba(239, 68, 68, 0.3);">
        <i data-lucide="user-x"></i>
      </div>
    </div>
  </div>

  <!-- Search and Filter Panel Card -->
  <div class="gym-card" style="margin-bottom: 24px !important;">
    <h2 class="gym-card-title">
      <i data-lucide="search" style="color: var(--accent);"></i>
      Filter Member Directory
    </h2>
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; align-items: end;">
      <div>
        <label class="gym-label">Search Keyword</label>
        <input type="text" name="search" class="form-input" placeholder="Name, contact, or email..." value="<?php echo htmlspecialchars($search); ?>" style="margin-bottom: 0 !important;">
      </div>
      <div>
        <label class="gym-label">Member Type</label>
        <select name="member_type" class="form-input" style="margin-bottom: 0 !important;">
          <option value="">All Types</option>
          <option value="client" <?php echo $member_type_filter === 'client' ? 'selected' : ''; ?>>Client</option>
          <option value="walk-in" <?php echo $member_type_filter === 'walk-in' ? 'selected' : ''; ?>>Walk-in</option>
        </select>
      </div>
      <div>
        <label class="gym-label">Status</label>
        <select name="status" class="form-input" style="margin-bottom: 0 !important;">
          <option value="">All Statuses</option>
          <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="expiring" <?php echo $status_filter === 'expiring' ? 'selected' : ''; ?>>Expiring Soon</option>
          <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
        </select>
      </div>
      <div style="display: flex; gap: 8px;">
        <button type="submit" class="gym-btn gym-btn-yellow" style="flex: 1;">
          <i data-lucide="filter"></i> Filter
        </button>
        <a href="all_members.php" class="gym-btn gym-btn-outline" style="min-width: 44px; justify-content: center;" title="Reset Filters">
          <i data-lucide="refresh-cw"></i>
        </a>
      </div>
    </form>
  </div>

  <!-- Members Table Card -->
  <div class="gym-card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 18px;">
      <h2 class="gym-card-title" style="margin: 0 !important;">
        <i data-lucide="list" style="color: var(--accent);"></i>
        Member Directory
      </h2>
      <span class="gym-badge gym-badge-info">Showing <?php echo count($allMembers); ?> of <?php echo $total_records; ?> members</span>
    </div>
    
    <div class="gym-table-wrapper" style="margin-bottom: 0 !important;">
      <table class="gym-table">
        <thead>
          <tr>
            <th>Member Name</th>
            <th>Type</th>
            <th>Contact</th>
            <th>Membership Plan</th>
            <th>Expiry Date</th>
            <th>Status</th>
            <th style="text-align: center;">Actions</th>
          </tr>
        </thead>
        <tbody id="membersTableBody">
          <?php foreach ($allMembers as $member): ?>
            <tr>
              <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($member['full_name']); ?></td>
              <td>
                <span class="gym-badge <?php echo $member['member_type'] === 'client' ? 'gym-badge-info' : 'gym-badge-warning'; ?>" style="text-transform: capitalize;">
                  <?php echo ucfirst($member['member_type']); ?>
                </span>
              </td>
              <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($member['contact_number']); ?></td>
              <td style="font-weight: 600; text-transform: capitalize; color: var(--accent-light);"><?php echo ucfirst($member['membership_plan']); ?></td>
              <td style="white-space: nowrap; font-weight: 600; color: var(--text-primary);"><?php echo date('M j, Y', strtotime($member['expiry_date'])); ?></td>
              <td>
                <?php if ($member['days_left'] > 7): ?>
                  <span class="gym-badge gym-badge-active">Active (<?php echo $member['days_left']; ?>d left)</span>
                <?php elseif ($member['days_left'] > 0): ?>
                  <span class="gym-badge gym-badge-warning">Expiring in <?php echo $member['days_left']; ?>d</span>
                <?php else: ?>
                  <span class="gym-badge gym-badge-expired">Expired</span>
                <?php endif; ?>
              </td>
              <td style="text-align: center;">
                <button onclick="viewMember(<?php echo $member['id']; ?>)" class="gym-btn gym-btn-outline" style="min-height: 30px !important; padding: 4px 10px !important;" title="View Details">
                  <i data-lucide="eye" style="width: 14px; height: 14px;"></i> View
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          
          <?php if (empty($allMembers)): ?>
            <tr>
              <td colspan="7" style="text-align: center; color: var(--text-dim); padding: 40px 20px;">
                <i data-lucide="users" style="width: 44px; height: 44px; margin: 0 auto 10px auto; color: var(--text-dim);"></i>
                <p style="font-weight: 600; color: var(--text-secondary);">No member records found matching your filters.</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px;">
        <button class="gym-btn gym-btn-outline" <?php echo $page <= 1 ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page - 1; ?>)" style="min-height: 34px !important; padding: 4px 12px !important;">
          <i data-lucide="chevron-left"></i> Prev
        </button>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <button class="gym-btn <?php echo $i == $page ? 'gym-btn-yellow' : 'gym-btn-outline'; ?>" onclick="changePage(<?php echo $i; ?>)" style="min-height: 34px !important; padding: 4px 12px !important;">
            <?php echo $i; ?>
          </button>
        <?php endfor; ?>
        
        <button class="gym-btn gym-btn-outline" <?php echo $page >= $total_pages ? 'disabled' : ''; ?> onclick="changePage(<?php echo $page + 1; ?>)" style="min-height: 34px !important; padding: 4px 12px !important;">
          Next <i data-lucide="chevron-right"></i>
        </button>
      </div>
    <?php endif; ?>
  </div>

  <!-- Member View Modal -->
  <?php if ($view_member): ?>
  <div id="memberModal" class="modal" style="display: flex;">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">
          <i data-lucide="user"></i>
          Member Information - <?php echo htmlspecialchars($view_member['full_name']); ?>
        </h2>
        <button class="modal-close" onclick="closeModal()">
          <i data-lucide="x" class="w-6 h-6"></i>
        </button>
      </div>

      <div class="space-y-6">
        <!-- Personal Information -->
        <div>
          <h3 class="text-yellow-400 font-semibold text-lg mb-4 flex items-center gap-2">
            <i data-lucide="user"></i>
            Personal Information
          </h3>
          <div class="info-grid">
            <div class="info-card">
              <div class="info-label">Full Name</div>
              <div class="info-value"><?php echo htmlspecialchars($view_member['full_name']); ?></div>
            </div>
            <div class="info-card">
              <div class="info-label">Age</div>
              <div class="info-value"><?php echo $view_member['age']; ?> years old</div>
            </div>
            <div class="info-card">
              <div class="info-label">Contact Number</div>
              <div class="info-value"><?php echo htmlspecialchars($view_member['contact_number']); ?></div>
            </div>
            <div class="info-card">
              <div class="info-label">Email</div>
              <div class="info-value"><?php echo htmlspecialchars($view_member['email'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-card">
              <div class="info-label">Username</div>
              <div class="info-value"><?php echo htmlspecialchars($view_member['username'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-card">
              <div class="info-label">Client Type</div>
              <div class="info-value"><?php echo htmlspecialchars($view_member['client_type'] ?? 'N/A'); ?></div>
            </div>
            <?php if ($view_member['member_type'] === 'client'): ?>
            <div class="info-card">
              <div class="info-label">Gender</div>
              <div class="info-value"><?php echo htmlspecialchars($view_member['gender'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-card">
              <div class="info-label">Height</div>
              <div class="info-value"><?php echo $view_member['height'] ?? 'N/A'; ?> cm</div>
            </div>
            <div class="info-card">
              <div class="info-label">Weight</div>
              <div class="info-value"><?php echo $view_member['weight'] ?? 'N/A'; ?> kg</div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Address Information -->
        <div>
          <h3 class="text-yellow-400 font-semibold text-lg mb-4 flex items-center gap-2">
            <i data-lucide="map-pin"></i>
            Address Information
          </h3>
          <div class="info-card">
            <div class="info-label">Address</div>
            <div class="info-value"><?php echo htmlspecialchars($view_member['address']); ?></div>
          </div>
        </div>

        <!-- Membership Information -->
        <div>
          <h3 class="text-yellow-400 font-semibold text-lg mb-4 flex items-center gap-2">
            <i data-lucide="id-card"></i>
            Membership Information
          </h3>
          <div class="info-grid">
            <div class="info-card">
              <div class="info-label">Member Type</div>
              <div class="info-value">
                <span class="member-type-badge type-<?php echo $view_member['member_type']; ?>">
                  <?php echo ucfirst($view_member['member_type']); ?>
                </span>
              </div>
            </div>
            <div class="info-card">
              <div class="info-label">Membership Plan</div>
              <div class="info-value"><?php echo ucfirst($view_member['membership_plan']); ?></div>
            </div>
            <div class="info-card">
              <div class="info-label">Start Date</div>
              <div class="info-value"><?php echo date('M j, Y', strtotime($view_member['start_date'])); ?></div>
            </div>
            <div class="info-card">
              <div class="info-label">Expiry Date</div>
              <div class="info-value"><?php echo date('M j, Y', strtotime($view_member['expiry_date'])); ?></div>
            </div>
            <div class="info-card">
              <div class="info-label">Status</div>
              <div class="info-value">
                <?php if ($view_member['days_left'] > 7): ?>
                  <span class="status-badge status-active">Active (<?php echo $view_member['days_left']; ?> days left)</span>
                <?php elseif ($view_member['days_left'] > 0): ?>
                  <span class="status-badge status-expiring">Expiring in <?php echo $view_member['days_left']; ?> days</span>
                <?php else: ?>
                  <span class="status-badge status-expired">Expired</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="info-card">
              <div class="info-label">Registration Date</div>
              <div class="info-value"><?php echo date('M j, Y', strtotime($view_member['created_at'])); ?></div>
            </div>
          </div>
        </div>

        <?php if ($view_member['member_type'] === 'client' && !empty($view_member['fitness_goals'])): ?>
        <!-- Fitness Goals -->
        <div>
          <h3 class="text-yellow-400 font-semibold text-lg mb-4 flex items-center gap-2">
            <i data-lucide="target"></i>
            Fitness Goals
          </h3>
          <div class="info-card">
            <div class="info-label">Goals & Objectives</div>
            <div class="info-value"><?php echo htmlspecialchars($view_member['fitness_goals']); ?></div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="flex gap-3 pt-4 border-t border-gray-700">
          <button onclick="closeModal()" class="btn gym-btn gym-btn-yellow flex-1">
            <i data-lucide="check"></i> Close
          </button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- QR Scanner Container - MOVABLE & TOGGLEABLE -->
  <div id="qrScanner" class="qr-scanner-container hidden">
    <div class="qr-scanner-header cursor-move" id="qrScannerHeader">
      <div class="qr-scanner-title">
        <i data-lucide="scan"></i>
        <span>QR Attendance Scanner</span>
      </div>
      <div class="flex items-center gap-2">
        <div id="qrScannerStatus" class="qr-scanner-status active">Active</div>
        <button id="closeQRScanner" class="text-gray-400 hover: transition-colors">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
    </div>
    <input type="text" id="qrInput" class="qr-input" placeholder="Scan QR code or enter code manually..." autocomplete="off">
    <div class="scanner-instructions">
      Press Enter or click Process after scanning
    </div>
    <div class="qr-scanner-buttons">
      <button id="processQR" class="qr-scanner-btn primary">
        <i data-lucide="check"></i> Process
      </button>
      <button id="toggleScanner" class="qr-scanner-btn secondary">
        <i data-lucide="power"></i> Disable
      </button>
    </div>
    <div id="qrResult" class="qr-scanner-result"></div>
  </div>

  <!-- QR Scanner Toggle Button -->
  <button id="toggleQRScannerBtn" class="fixed bottom-4 right-4 bg-yellow-500 hover:bg-yellow-600  rounded-full w-12 h-12 flex items-center justify-center cursor-pointer shadow-lg z-40 transition-all duration-300">
    <i data-lucide="scan" class="w-6 h-6"></i>
  </button>

  <script>
    // Modal functions
    function viewMember(memberId) {
        window.location.href = `?view_member_id=${memberId}&<?php echo http_build_query($_GET); ?>`;
    }

    function closeModal() {
        // Remove the view_member_id from URL and reload
        const url = new URL(window.location);
        url.searchParams.delete('view_member_id');
        window.location.href = url.toString();
    }

    // Pagination function
    function changePage(page) {
        const url = new URL(window.location);
        url.searchParams.set('page', page);
        window.location.href = url.toString();
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            closeModal();
        }
    });

    // QR Scanner functionality - MOVABLE & TOGGLEABLE VERSION
    let qrScannerActive = true;
    let lastProcessedQR = '';
    let lastProcessedTime = 0;
    let qrProcessing = false;
    let qrCooldown = false;
    let isDragging = false;
    let dragOffset = { x: 0, y: 0 };

    function setupQRScanner() {
        const qrScanner = document.getElementById('qrScanner');
        const qrScannerHeader = document.getElementById('qrScannerHeader');
        const qrInput = document.getElementById('qrInput');
        const processQRBtn = document.getElementById('processQR');
        const toggleScannerBtn = document.getElementById('toggleScanner');
        const toggleQRScannerBtn = document.getElementById('toggleQRScannerBtn');
        const closeQRScannerBtn = document.getElementById('closeQRScanner');
        const qrScannerStatus = document.getElementById('qrScannerStatus');

        // Toggle QR scanner visibility
        toggleQRScannerBtn.addEventListener('click', function() {
            qrScanner.classList.toggle('hidden');
            if (!qrScanner.classList.contains('hidden') && qrScannerActive) {
                setTimeout(() => qrInput.focus(), 100);
            }
        });

        // Close QR scanner
        closeQRScannerBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            qrScanner.classList.add('hidden');
        });

        // Drag and drop functionality
        qrScannerHeader.addEventListener('mousedown', startDrag);
        qrScannerHeader.addEventListener('touchstart', function(e) {
            startDrag(e.touches[0]);
        });

        document.addEventListener('mousemove', drag);
        document.addEventListener('touchmove', function(e) {
            drag(e.touches[0]);
            e.preventDefault();
        }, { passive: false });

        document.addEventListener('mouseup', stopDrag);
        document.addEventListener('touchend', stopDrag);

        function startDrag(e) {
            if (e.target.closest('button')) return; // Don't drag if clicking buttons
            
            isDragging = true;
            qrScanner.classList.add('dragging');
            
            const rect = qrScanner.getBoundingClientRect();
            dragOffset.x = e.clientX - rect.left;
            dragOffset.y = e.clientY - rect.top;
            
            document.body.classList.add('cursor-grabbing');
        }

        function drag(e) {
            if (!isDragging) return;
            
            const x = e.clientX - dragOffset.x;
            const y = e.clientY - dragOffset.y;
            
            // Keep within viewport bounds
            const maxX = window.innerWidth - qrScanner.offsetWidth;
            const maxY = window.innerHeight - qrScanner.offsetHeight;
            
            const boundedX = Math.max(0, Math.min(x, maxX));
            const boundedY = Math.max(0, Math.min(y, maxY));
            
            qrScanner.style.left = boundedX + 'px';
            qrScanner.style.top = boundedY + 'px';
            qrScanner.style.right = 'auto';
            qrScanner.style.bottom = 'auto';
            qrScanner.style.transform = 'none';
        }

        function stopDrag() {
            if (!isDragging) return;
            
            isDragging = false;
            qrScanner.classList.remove('dragging');
            document.body.classList.remove('cursor-grabbing');
        }

        // Process QR code when Enter is pressed
        qrInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && qrScannerActive && !qrProcessing && !qrCooldown) {
                processQRCode();
                e.preventDefault();
            }
        });
        
        // Process QR code when button is clicked
        processQRBtn.addEventListener('click', function() {
            if (qrScannerActive && !qrProcessing && !qrCooldown) {
                processQRCode();
            }
        });
        
        // Toggle scanner on/off
        toggleScannerBtn.addEventListener('click', function() {
            qrScannerActive = !qrScannerActive;
            
            if (qrScannerActive) {
                qrScannerStatus.textContent = 'Active';
                qrScannerStatus.classList.remove('disabled');
                qrScannerStatus.classList.add('active');
                toggleScannerBtn.innerHTML = '<i data-lucide="power"></i> Disable';
                qrInput.disabled = false;
                qrInput.placeholder = 'Scan QR code or enter code manually...';
                processQRBtn.disabled = false;
                if (!qrScanner.classList.contains('hidden')) {
                    qrInput.focus();
                }
                showToast('QR scanner enabled', 'success', 2000);
            } else {
                qrScannerStatus.textContent = 'Disabled';
                qrScannerStatus.classList.remove('active');
                qrScannerStatus.classList.add('disabled');
                toggleScannerBtn.innerHTML = '<i data-lucide="power"></i> Enable';
                qrInput.disabled = true;
                qrInput.placeholder = 'Scanner disabled';
                processQRBtn.disabled = true;
                showToast('QR scanner disabled', 'warning', 2000);
            }
            
            lucide.createIcons();
        });
        
        // Smart focus management
        document.addEventListener('click', function(e) {
            if (qrScannerActive && 
                !qrScanner.classList.contains('hidden') &&
                !e.target.closest('form') && 
                !e.target.closest('select') && 
                !e.target.closest('button') &&
                e.target !== qrInput) {
                setTimeout(() => {
                    if (document.activeElement.tagName !== 'INPUT' && 
                        document.activeElement.tagName !== 'TEXTAREA' &&
                        document.activeElement.tagName !== 'SELECT') {
                        qrInput.focus();
                    }
                }, 100);
            }
        });
        
        // Clear input after successful processing
        qrInput.addEventListener('input', function() {
            if (this.value === lastProcessedQR) {
                this.value = '';
            }
        });
        
        // Close scanner with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !qrScanner.classList.contains('hidden')) {
                qrScanner.classList.add('hidden');
            }
        });
        
        // Initial focus
        setTimeout(() => {
            if (qrScannerActive && !qrScanner.classList.contains('hidden')) {
                qrInput.focus();
            }
        }, 1000);
    }

    function processQRCode() {
        if (qrProcessing || qrCooldown) return;
        
        const qrInput = document.getElementById('qrInput');
        const qrResult = document.getElementById('qrResult');
        const processQRBtn = document.getElementById('processQR');
        const qrCode = qrInput.value.trim();
        
        if (!qrCode) {
            showQRResult('error', 'Error', 'Please enter a QR code');
            showToast('Please enter a QR code', 'error');
            return;
        }
        
        // Prevent processing the same QR code twice in quick succession
        const currentTime = Date.now();
        if (qrCode === lastProcessedQR && (currentTime - lastProcessedTime) < 3000) {
            const timeLeft = Math.ceil((3000 - (currentTime - lastProcessedTime)) / 1000);
            showQRResult('error', 'Cooldown', `Please wait ${timeLeft} seconds before scanning this QR code again`);
            showToast(`Please wait ${timeLeft} seconds before rescanning`, 'warning');
            qrInput.value = '';
            qrInput.focus();
            return;
        }
        
        qrProcessing = true;
        qrCooldown = true;
        setLoadingState(processQRBtn, true);
        processQRBtn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Processing';
        lucide.createIcons();
        
        // Show processing message
        showQRResult('info', 'Processing', 'Scanning QR code...');
        showToast('Processing QR code...', 'info', 2000);
        
        // Make AJAX call to process the QR code
        fetch('process_qr.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'qr_code=' + encodeURIComponent(qrCode)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showQRResult('success', 'Success', data.message);
                showToast(data.message, 'success');
                lastProcessedQR = qrCode;
                lastProcessedTime = Date.now();
                
                // Update attendance count if element exists
                const attendanceCount = document.getElementById('attendanceCount');
                if (attendanceCount) {
                    const currentCount = parseInt(attendanceCount.textContent || '0');
                    attendanceCount.textContent = currentCount + 1;
                }
                
                // Trigger custom event for other components
                window.dispatchEvent(new CustomEvent('qrScanSuccess', { 
                    detail: { message: data.message, qrCode: qrCode } 
                }));
                
            } else {
                showQRResult('error', 'Error', data.message || 'Unknown error occurred');
                showToast(data.message || 'Unknown error occurred', 'error');
                lastProcessedQR = qrCode;
                lastProcessedTime = Date.now();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showQRResult('error', 'Network Error', 'Failed to process QR code. Please try again.');
            showToast('Network error occurred', 'error');
            lastProcessedQR = qrCode;
            lastProcessedTime = Date.now();
        })
        .finally(() => {
            qrProcessing = false;
            setLoadingState(processQRBtn, false);
            processQRBtn.innerHTML = '<i data-lucide="check"></i> Process';
            lucide.createIcons();
            
            // Clear input and refocus after processing
            setTimeout(() => {
                qrInput.value = '';
                const qrScanner = document.getElementById('qrScanner');
                if (qrScannerActive && !qrScanner.classList.contains('hidden')) {
                    qrInput.focus();
                }
            }, 500);
            
            // Enable scanning again after 3 seconds
            setTimeout(() => {
                qrCooldown = false;
            }, 3000);
        });
    }

    function showQRResult(type, title, message) {
        const qrResult = document.getElementById('qrResult');
        qrResult.className = 'qr-scanner-result ' + type;
        qrResult.innerHTML = `
            <div class="qr-result-title">${title}</div>
            <div class="qr-result-message">${message}</div>
        `;
        qrResult.style.display = 'block';
        
        // Auto-hide result after appropriate time
        let hideTime = type === 'success' ? 4000 : 5000;
        if (title === 'Cooldown') hideTime = 3000;
        if (title === 'Processing') hideTime = 2000;
        
        setTimeout(() => {
            qrResult.style.display = 'none';
        }, hideTime);
    }

    // Helper functions
    function setLoadingState(button, isLoading) {
        button.disabled = isLoading;
        button.style.opacity = isLoading ? 0.7 : 1;
    }

    function showToast(message, type = 'info', duration = 3000) {
        // Simple toast implementation
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? 'var(--green)' : type === 'error' ? 'var(--red)' : type === 'warning' ? 'var(--accent)' : 'var(--blue)'};
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, duration);
    }

    // Add CSS animation for toast
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);

    // Global function to open QR scanner
    function openQRScanner() {
        const qrScanner = document.getElementById('qrScanner');
        const qrInput = document.getElementById('qrInput');
        
        qrScanner.classList.remove('hidden');
        if (qrScannerActive) {
            setTimeout(() => qrInput.focus(), 100);
        }
    }

    // Sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize icons
        lucide.createIcons();
        
        // Setup QR Scanner
        setupQRScanner();
        
        // Sidebar toggle functionality
                return icons[type] || '<i data-lucide="bell" class="w-4 h-4 text-gray-400"></i>';
    }
    
    function formatTime(timeString) {
        const time = new Date(timeString);
        const now = new Date();
        const diffMs = now - time;
        const diffMins = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return time.toLocaleDateString();
    }
  </script>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php $conn->close(); ?>
