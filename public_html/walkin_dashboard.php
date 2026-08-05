<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    header("Location: index.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database connection
require_once __DIR__ . '/../config/config.php';

$logged_in_user_id = $_SESSION['user_id'];

// Check if user is actually a walk-in client
function isWalkInClient($conn, $user_id) {
    $sql = "SELECT u.client_type, m.member_type 
            FROM users u 
            LEFT JOIN members m ON u.id = m.user_id 
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Check both user client_type and member member_type
    return ($user && ($user['client_type'] === 'walk-in' || $user['member_type'] === 'walk-in'));
}

// Redirect if not a walk-in client
if (!isWalkInClient($conn, $logged_in_user_id)) {
    header("Location: client_dashboard.php");
    exit();
}

// Function to get walk-in client details
function getWalkInDetails($conn, $user_id) {
    $sql = "SELECT m.*, u.client_type FROM members m 
            INNER JOIN users u ON m.user_id = u.id 
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get walk-in attendance
function getWalkInAttendance($conn, $user_id) {
    try {
        $sql = "SELECT COUNT(*) as visit_count FROM attendance a 
                INNER JOIN members m ON a.member_id = m.id 
                INNER JOIN users u ON m.user_id = u.id 
                WHERE u.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['visit_count'];
    } catch (Exception $e) {
        error_log("Error fetching attendance: " . $e->getMessage());
        return 0;
    }
}

// Function to get recent attendance (last 7 visits)
function getRecentAttendance($conn, $user_id) {
    $attendance = [];
    try {
        $sql = "SELECT a.check_in, a.duration_minutes FROM attendance a 
                INNER JOIN members m ON a.member_id = m.id 
                INNER JOIN users u ON m.user_id = u.id 
                WHERE u.id = ? 
                ORDER BY a.check_in DESC 
                LIMIT 7";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error fetching recent attendance: " . $e->getMessage());
        $attendance = [];
    }
    return $attendance;
}

// Function to get membership status
function getWalkInMembershipStatus($conn, $user_id) {
    try {
        $sql = "SELECT m.membership_plan, m.expiry_date, m.status, m.start_date
                FROM members m 
                INNER JOIN users u ON m.user_id = u.id 
                WHERE u.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } catch (Exception $e) {
        error_log("Error fetching membership status: " . $e->getMessage());
        return null;
    }
}

// Function to get active announcements for walk-ins
function getWalkInAnnouncements($conn) {
    $announcements = [];
    try {
        $column_check = $conn->query("SHOW COLUMNS FROM announcements LIKE 'target_audience'");
        
        if ($column_check->num_rows > 0) {
            $result = $conn->query("SELECT * FROM announcements WHERE (expiry_date IS NULL OR expiry_date >= CURDATE()) AND (target_audience = 'all' OR target_audience = 'clients') ORDER BY created_at DESC LIMIT 3");
        } else {
            $result = $conn->query("SELECT * FROM announcements WHERE (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY created_at DESC LIMIT 3");
        }
        
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error fetching announcements: " . $e->getMessage());
        $announcements = [];
    }
    return $announcements;
}

// Get all data
$client = getWalkInDetails($conn, $logged_in_user_id);
$attendanceCount = getWalkInAttendance($conn, $logged_in_user_id);
$recentAttendance = getRecentAttendance($conn, $logged_in_user_id);
$announcements = getWalkInAnnouncements($conn);
$membership = getWalkInMembershipStatus($conn, $logged_in_user_id);

// Calculate membership info
if ($membership) {
    $expiry = new DateTime($membership['expiry_date']);
    $today = new DateTime();
    $daysLeft = $today->diff($expiry)->days;
    if ($today > $expiry) {
        $daysLeft = -$daysLeft;
    }
    $membership['days_left'] = $daysLeft;
}

// If client not found, create basic info
if (!$client) {
    $client = [
        'full_name' => $_SESSION['username'],
        'member_type' => 'walk-in',
        'client_type' => 'walk-in'
    ];
}
// Note: Do NOT close $conn here — HTML template may still need the DB connection

$page_title = "Walk-in Dashboard - Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>



<div class="gym-main-container">
    <!-- Welcome Section -->
    <div class="gym-page-header">
      <div>
        <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
          <i data-lucide="user-check" style="color: var(--accent);"></i>
          Walk-In Member Portal
        </h1>
        <p class="gym-page-subtitle">Welcome, <strong><?php echo htmlspecialchars($client['full_name']); ?></strong>! Pay-per-use daily session access pass.</p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <span class="gym-badge gym-badge-active" style="display: flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 0.84rem;">
          <i data-lucide="clock" style="width: 14px; height: 14px;"></i> Pay-Per-Visit Active
        </span>
      </div>
    </div>
        <p style="font-size: 0.875rem; color: var(--text-secondary);">Today is</p>
        <p style="font-weight: 600;"><?php echo date('l, F j, Y'); ?></p>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="gym-card">
        <p style="font-size: 0.9rem; font-weight: 600; color: var(--accent-light); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
          <i data-lucide="calendar"></i><span>Total Visits</span>
        </p>
        <p style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);"><?php echo $attendanceCount; ?></p>
        <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Gym visits</p>
      </div>
      
      <?php if ($membership): ?>
      <div class="gym-card">
        <p style="font-size: 0.9rem; font-weight: 600; color: var(--accent-light); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
          <i data-lucide="id-card"></i><span>Membership</span>
        </p>
        <p style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);"><?php echo ucfirst($membership['membership_plan']); ?></p>
        <p style="font-size: 0.75rem; margin-top: 0.25rem; <?php echo $membership['status'] === 'active' ? 'color: var(--green);' : 'color: var(--red);'; ?>">
          <?php echo ucfirst($membership['status']); ?>
        </p>
      </div>
      
      <div class="gym-card" style="<?php echo ($membership['days_left'] <= 3 && $membership['days_left'] > 0) ? 'border-left: 4px solid var(--red);' : ''; ?>">
        <p style="font-size: 0.9rem; font-weight: 600; color: var(--accent-light); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
          <i data-lucide="clock"></i><span>Days Left</span>
        </p>
        <p style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);"><?php echo ($membership['days_left'] > 0) ? $membership['days_left'] : '0'; ?></p>
        <p style="font-size: 0.75rem; margin-top: 0.25rem; <?php echo ($membership['days_left'] <= 3 && $membership['days_left'] > 0) ? 'color: var(--red);' : 'color: var(--text-secondary);'; ?>">
          Membership expiry
        </p>
      </div>
      <?php else: ?>
      <div class="gym-card" style="border-left: 4px solid var(--accent);">
        <p style="font-size: 0.9rem; font-weight: 600; color: var(--accent-light); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
          <i data-lucide="alert-circle"></i><span>Status</span>
        </p>
        <p style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">No Plan</p>
        <p style="font-size: 0.75rem; color: var(--accent-light); margin-top: 0.25rem;">Visit reception</p>
      </div>
      <?php endif; ?>
    </div>

    <!-- Main Content Grid -->
    <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; @media(min-width: 1024px){grid-template-columns: repeat(3, minmax(0, 1fr));}">
      <!-- Announcements & Recent Activity -->
      <div style="display: flex; flex-direction: column; gap: 1.5rem; grid-column: span 2 / span 2;">
        <!-- Announcements -->
        <div class="gym-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 0.9rem; font-weight: 600; color: var(--accent-light); display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="megaphone"></i> Announcements</h2>
            <span style="font-size: 0.75rem; color: var(--text-dim);"><?php echo count($announcements); ?> announcements</span>
          </div>
          
          <div id="announcementsList">
            <?php foreach ($announcements as $announcement): ?>
              <div class="announcement-item <?php echo isset($announcement['priority']) && $announcement['priority'] === 'high' ? 'urgent' : ''; ?>">
                <div class="announcement-title">
                  <?php echo htmlspecialchars($announcement['title']); ?>
                  <?php if (isset($announcement['priority'])): ?>
                    <span class="priority-badge priority-<?php echo $announcement['priority']; ?>">
                      <?php echo ucfirst($announcement['priority']); ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                  <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>
                  <?php if (isset($announcement['expiry_date'])): ?>
                    • Expires: <?php echo date('M j, Y', strtotime($announcement['expiry_date'])); ?>
                  <?php endif; ?>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-primary);">
                  <?php echo htmlspecialchars($announcement['content']); ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          
          <?php if (empty($announcements)): ?>
            <div class="empty-state">
              <i data-lucide="megaphone" style="width: 3rem; height: 3rem; margin: 0 auto;"></i>
              <p>No announcements</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Recent Visits -->
        <div class="gym-card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 0.9rem; font-weight: 600; color: var(--accent-light); display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="history"></i> Recent Visits</h2>
            <a href="attendanceclient.php" style="font-size: 0.75rem; color: var(--accent-light); text-decoration: none;">View All</a>
          </div>
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php foreach ($recentAttendance as $visit): ?>
              <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--bg-card); border-radius: 0.5rem;">
                <div>
                  <p style="font-weight: 500; font-size: 0.875rem;">
                    <?php echo date('M j, Y', strtotime($visit['check_in'])); ?>
                  </p>
                  <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                    <?php echo date('g:i A', strtotime($visit['check_in'])); ?>
                    <?php if ($visit['duration_minutes']): ?>
                      • <?php echo $visit['duration_minutes']; ?> mins
                    <?php endif; ?>
                  </p>
                </div>
                <div style="text-align: right;">
                  <span style="font-size: 0.75rem; background: rgba(16, 185, 129, 0.2); color: var(--green); padding: 0.25rem 0.5rem; border-radius: 9999px;">
                    Visited
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($recentAttendance)): ?>
              <div class="empty-state">
                <i data-lucide="calendar" style="width: 3rem; height: 3rem; margin: 0 auto; opacity: 0.5;"></i>
                <p style="color: var(--text-secondary); margin-top: 0.5rem;">No recent visits</p>
                <p style="font-size: 0.875rem; color: var(--text-dim);">Your visits will appear here</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Quick Actions & Membership Status -->
      <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Quick Actions -->
        <div class="gym-card">
          <h2 style="font-size: 0.9rem; font-weight: 600; color: var(--accent-light); display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="zap"></i> Quick Actions</h2>
          <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem;">
            <a href="attendanceclient.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: 0.5rem; text-decoration: none; color: inherit;">
              <i data-lucide="calendar" style="width: 1.25rem; height: 1.25rem; color: var(--blue);"></i>
              <div>
                <p style="font-weight: 500; font-size: 0.875rem;">Check Attendance</p>
                <p style="font-size: 0.75rem; color: var(--text-secondary);">View your visit history</p>
              </div>
            </a>
            
            <a href="membershipclient.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: 0.5rem; text-decoration: none; color: inherit;">
              <i data-lucide="id-card" style="width: 1.25rem; height: 1.25rem; color: var(--green);"></i>
              <div>
                <p style="font-weight: 500; font-size: 0.875rem;">Membership</p>
                <p style="font-size: 0.75rem; color: var(--text-secondary);">Check status & renew</p>
              </div>
            </a>
            
            <a href="feedbacksclient.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: 0.5rem; text-decoration: none; color: inherit;">
              <i data-lucide="message-square" style="width: 1.25rem; height: 1.25rem; color: var(--accent-light);"></i>
              <div>
                <p style="font-weight: 500; font-size: 0.875rem;">Send Feedback</p>
                <p style="font-size: 0.75rem; color: var(--text-secondary);">Share your experience</p>
              </div>
            </a>
          </div>
        </div>

        <!-- Membership Status -->
        <?php if ($membership): ?>
        <div class="gym-card">
          <h2 style="font-size: 0.9rem; font-weight: 600; color: var(--accent-light); display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="id-card"></i> Membership Status</h2>
          <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="color: var(--text-secondary);">Plan:</span>
              <span style="font-weight: 600;"><?php echo ucfirst($membership['membership_plan']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="color: var(--text-secondary);">Status:</span>
              <span style="font-weight: 600; <?php echo $membership['status'] === 'active' ? 'color: var(--green);' : 'color: var(--red);'; ?>">
                <?php echo ucfirst($membership['status']); ?>
              </span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="color: var(--text-secondary);">Started:</span>
              <span style="font-weight: 600;"><?php echo date('M j, Y', strtotime($membership['start_date'])); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="color: var(--text-secondary);">Expires:</span>
              <span style="font-weight: 600;"><?php echo date('M j, Y', strtotime($membership['expiry_date'])); ?></span>
            </div>
            
            <?php if ($membership['days_left'] <= 7 && $membership['days_left'] > 0): ?>
              <div style="margin-top: 0.75rem; padding: 0.75rem; background: rgba(232, 160, 18, 0.2); border: 1px solid rgba(232, 160, 18, 0.3); border-radius: 0.5rem;">
                <p style="color: var(--accent); font-size: 0.875rem; font-weight: 600; text-align: center;">
                  Expires in <?php echo $membership['days_left']; ?> days
                </p>
              </div>
            <?php elseif ($membership['days_left'] <= 0): ?>
              <div style="margin-top: 0.75rem; padding: 0.75rem; background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 0.5rem;">
                <p style="color: var(--red); font-size: 0.875rem; font-weight: 600; text-align: center;">
                  Membership expired
                </p>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="gym-card" style="border-left: 4px solid var(--accent);">
          <h2 style="font-size: 0.9rem; font-weight: 600; color: var(--accent-light); display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="alert-circle"></i> No Active Membership</h2>
          <div style="margin-top: 1rem; text-align: center;">
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.75rem;">You don't have an active membership plan</p>
            <a href="membershipclient.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: var(--accent); color: #000; border-radius: 0.5rem; text-decoration: none; font-size: 0.875rem; font-weight: 500;">
              <i data-lucide="plus" style="width: 1rem; height: 1rem;"></i>
              Get Membership
            </a>
          </div>
        </div>
        <?php endif; ?>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
