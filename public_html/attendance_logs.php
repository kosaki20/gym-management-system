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
require_once __DIR__ . '/chat_functions.php';

$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$trainer_user_id = $_SESSION['user_id'];

// Function to get trainer notifications
function getTrainerNotifications($conn, $trainer_user_id) {
    $notifications = [];
    $sql = "SELECT * FROM notifications 
            WHERE (user_id = ? OR user_id IS NULL OR role = 'trainer') 
            AND (read_status = 0 OR read_status IS NULL)
            ORDER BY created_at DESC 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $trainer_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    return $notifications;
}

$notifications = getTrainerNotifications($conn, $trainer_user_id);
$notification_count = count($notifications);

// Function to get attendance records
function getAttendanceRecords($conn, $dateFilter = null) {
    $records = [];
    $sql = "SELECT a.*, m.full_name, m.member_type 
            FROM attendance a 
            JOIN members m ON a.member_id = m.id";
    
    if ($dateFilter) {
        $sql .= " WHERE DATE(a.check_in) = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $dateFilter);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql .= " ORDER BY a.check_in DESC LIMIT 100";
        $result = $conn->query($sql);
    }
    
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    return $records;
}

// Handle manual attendance check-in
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_attendance'])) {
    $memberId = intval($_POST['member_id']);
    
    $stmt = $conn->prepare("SELECT id, full_name FROM members WHERE id = ?");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $member = $result->fetch_assoc();
        $today = date('Y-m-d');
        
        $checkStmt = $conn->prepare("SELECT id FROM attendance WHERE member_id = ? AND DATE(check_in) = ?");
        $checkStmt->bind_param("is", $memberId, $today);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $message = "Member is already checked in today";
            $messageType = "error";
        } else {
            $insertStmt = $conn->prepare("INSERT INTO attendance (member_id, check_in) VALUES (?, NOW())");
            $insertStmt->bind_param("i", $memberId);
            if ($insertStmt->execute()) {
                $message = "Successfully checked in " . htmlspecialchars($member['full_name']);
                $messageType = "success";
            } else {
                $message = "Error checking in member: " . $conn->error;
                $messageType = "error";
            }
        }
    } else {
        $message = "Member not found";
        $messageType = "error";
    }
}

// Get all members for dropdown
$membersResult = $conn->query("SELECT id, full_name FROM members ORDER BY full_name");

// Get attendance records
$dateFilter = $_GET['date'] ?? null;
$attendanceRecords = getAttendanceRecords($conn, $dateFilter);

$page_title = "Attendance Logs — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="calendar" style="color: var(--accent);"></i>
        Member Attendance Logs
      </h1>
      <p class="gym-page-subtitle">Track real-time member check-ins, manual check-in entries, and QR code scans.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <form method="GET" style="display: flex; gap: 8px; margin: 0;">
        <input type="date" name="date" value="<?php echo htmlspecialchars($dateFilter ?: date('Y-m-d')); ?>" class="gym-form-control" style="width: auto; height: 38px; padding: 0 12px;">
        <button type="submit" class="gym-btn gym-btn-yellow" style="min-height: 38px;">
          <i data-lucide="filter"></i> Filter
        </button>
        <?php if ($dateFilter): ?>
          <a href="attendance_logs.php" class="gym-btn gym-btn-outline" style="min-height: 38px; color: #ef4444; border-color: rgba(239,68,68,0.4);">
            <i data-lucide="x"></i> Clear
          </a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Manual Attendance Card -->
  <div class="gym-card" style="margin-bottom: 1.5rem;">
    <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
      <i data-lucide="user-plus" style="color: var(--accent);"></i>
      Manual Member Check In
    </h2>
    
    <?php if (!empty($message)): ?>
      <div style="background: <?php echo $messageType === 'success' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; border: 1px solid <?php echo $messageType === 'success' ? 'rgba(34, 197, 94, 0.4)' : 'rgba(239, 68, 68, 0.4)'; ?>; color: <?php echo $messageType === 'success' ? '#4ade80' : '#f87171'; ?>; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 16px; font-weight: 500;">
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: flex-end;">
      <div>
        <label class="gym-form-label">Select Active Member</label>
        <select name="member_id" class="gym-form-control" required style="width: 100%; height: 42px;">
          <option value="">Choose a member...</option>
          <?php if ($membersResult && $membersResult->num_rows > 0): ?>
            <?php while ($member = $membersResult->fetch_assoc()): ?>
              <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['full_name']); ?></option>
            <?php endwhile; ?>
          <?php endif; ?>
        </select>
      </div>

      <div>
        <button type="submit" name="manual_attendance" class="gym-btn gym-btn-yellow" style="width: 100%; height: 42px;">
          <i data-lucide="log-in"></i> Check In Member
        </button>
      </div>

      <div>
        <button type="button" onclick="openQRScanner()" class="gym-btn gym-btn-outline" style="width: 100%; height: 42px;">
          <i data-lucide="scan"></i> Use QR Scanner
        </button>
      </div>
    </form>
  </div>

  <!-- Attendance Records Table Card -->
  <div class="gym-card" style="margin-bottom: 1.5rem;">
    <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
      <i data-lucide="list" style="color: var(--accent);"></i>
      Attendance Records <?php echo $dateFilter ? "for " . date('M j, Y', strtotime($dateFilter)) : "(Last 100 Records)"; ?>
    </h2>

    <div class="gym-table-wrapper" style="margin-bottom: 0;">
      <table class="gym-table">
        <thead>
          <tr>
            <th>Member Name</th>
            <th>Member Type</th>
            <th>Check In Time</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($attendanceRecords)): ?>
            <tr>
              <td colspan="4" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                <i data-lucide="calendar-x" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No attendance records found</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($attendanceRecords as $record): ?>
              <tr>
                <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($record['full_name']); ?></td>
                <td>
                  <span class="gym-badge <?php echo strtolower($record['member_type']) === 'client' ? 'gym-badge-active' : 'gym-badge-pending'; ?>">
                    <?php echo ucfirst($record['member_type']); ?>
                  </span>
                </td>
                <td><?php echo date('g:i A', strtotime($record['check_in'])); ?></td>
                <td style="color: var(--text-dim);"><?php echo date('M j, Y', strtotime($record['check_in'])); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- QR Scanner Container - MOVABLE & TOGGLEABLE -->
  <div id="qrScanner" class="qr-scanner-container hidden" style="position: fixed; bottom: 85px; right: 24px; z-index: 999; background: #0d1220; border: 1px solid #1e2740; border-radius: 12px; padding: 16px; width: 320px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
    <div class="qr-scanner-header cursor-move" id="qrScannerHeader" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; cursor: move;">
      <div class="qr-scanner-title" style="font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
        <i data-lucide="scan" style="color: var(--accent);"></i>
        <span>QR Attendance Scanner</span>
      </div>
      <div style="display: flex; align-items: center; gap: 8px;">
        <span id="qrScannerStatus" class="qr-scanner-status active" style="font-size: 0.72rem; padding: 2px 8px; border-radius: 10px; background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4);">Active</span>
        <button id="closeQRScanner" type="button" style="background: none; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.1rem;">
          <i data-lucide="x"></i>
        </button>
      </div>
    </div>

    <input type="text" id="qrInput" class="gym-form-control" placeholder="Scan QR code or enter code manually..." autocomplete="off" style="margin-bottom: 10px; width: 100%;">
    
    <div class="scanner-instructions" style="font-size: 0.78rem; color: var(--text-dim); margin-bottom: 12px;">
      Press Enter or click Process after scanning
    </div>

    <div class="qr-scanner-buttons" style="display: flex; gap: 8px; margin-bottom: 10px;">
      <button id="processQR" type="button" class="gym-btn gym-btn-yellow" style="flex: 1; min-height: 36px; padding: 0 12px;">
        <i data-lucide="check"></i> Process
      </button>
      <button id="toggleScanner" type="button" class="gym-btn gym-btn-outline" style="flex: 1; min-height: 36px; padding: 0 12px;">
        <i data-lucide="power"></i> Disable
      </button>
    </div>
    
    <div id="qrResult" class="qr-scanner-result" style="display: none; padding: 8px 12px; border-radius: 8px; font-size: 0.85rem;"></div>
  </div>

  <!-- QR Scanner Toggle Floating Button -->
  <button id="toggleQRScannerBtn" type="button" style="position: fixed; bottom: 24px; right: 24px; background: var(--accent); border-radius: 50%; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; color: #0b0f19; box-shadow: 0 6px 20px rgba(0,0,0,0.4); z-index: 1000;">
    <i data-lucide="scan" style="width: 24px; height: 24px;"></i>
  </button>
</div>

<script>
  let isDragging = false;
  let dragOffset = { x: 0, y: 0 };
  let qrScannerActive = true;
  let qrProcessing = false;
  let qrCooldown = false;
  let lastProcessedQR = '';
  let lastProcessedTime = 0;

  document.addEventListener('DOMContentLoaded', function() {
      if (typeof lucide !== 'undefined') {
          lucide.createIcons();
      }

      const qrScanner = document.getElementById('qrScanner');
      const qrScannerHeader = document.getElementById('qrScannerHeader');
      const qrInput = document.getElementById('qrInput');
      const processQRBtn = document.getElementById('processQR');
      const toggleScannerBtn = document.getElementById('toggleScanner');
      const toggleQRScannerBtn = document.getElementById('toggleQRScannerBtn');
      const closeQRScannerBtn = document.getElementById('closeQRScanner');
      const qrScannerStatus = document.getElementById('qrScannerStatus');

      // Drag functionality
      if (qrScannerHeader && qrScanner) {
          qrScannerHeader.addEventListener('mousedown', startDrag);
          qrScannerHeader.addEventListener('touchstart', function(e) {
              startDrag(e.touches[0]);
          });
      }

      document.addEventListener('mousemove', drag);
      document.addEventListener('touchmove', function(e) {
          if (isDragging) {
              drag(e.touches[0]);
              e.preventDefault();
          }
      }, { passive: false });

      document.addEventListener('mouseup', stopDrag);
      document.addEventListener('touchend', stopDrag);

      function startDrag(e) {
          if (e.target.closest('button')) return;
          isDragging = true;
          if (qrScanner) {
              qrScanner.classList.add('dragging');
              const rect = qrScanner.getBoundingClientRect();
              dragOffset.x = e.clientX - rect.left;
              dragOffset.y = e.clientY - rect.top;
          }
          document.body.style.cursor = 'grabbing';
      }

      function drag(e) {
          if (!isDragging || !qrScanner) return;
          const x = e.clientX - dragOffset.x;
          const y = e.clientY - dragOffset.y;
          const maxX = window.innerWidth - qrScanner.offsetWidth;
          const maxY = window.innerHeight - qrScanner.offsetHeight;
          const boundedX = Math.max(0, Math.min(x, maxX));
          const boundedY = Math.max(0, Math.min(y, maxY));

          qrScanner.style.left = boundedX + 'px';
          qrScanner.style.top = boundedY + 'px';
          qrScanner.style.right = 'auto';
          qrScanner.style.bottom = 'auto';
      }

      function stopDrag() {
          if (!isDragging) return;
          isDragging = false;
          if (qrScanner) qrScanner.classList.remove('dragging');
          document.body.style.cursor = 'default';
      }

      // Scanner visibility toggles
      if (toggleQRScannerBtn && qrScanner) {
          toggleQRScannerBtn.addEventListener('click', function() {
              qrScanner.classList.toggle('hidden');
              if (!qrScanner.classList.contains('hidden') && qrInput) {
                  qrInput.focus();
              }
          });
      }

      if (closeQRScannerBtn && qrScanner) {
          closeQRScannerBtn.addEventListener('click', function(e) {
              e.stopPropagation();
              qrScanner.classList.add('hidden');
          });
      }

      // Enter key processing
      if (qrInput) {
          qrInput.addEventListener('keypress', function(e) {
              if (e.key === 'Enter' && qrScannerActive && !qrProcessing && !qrCooldown) {
                  processQRCode();
                  e.preventDefault();
              }
          });
      }

      if (processQRBtn) {
          processQRBtn.addEventListener('click', function() {
              if (qrScannerActive && !qrProcessing && !qrCooldown) {
                  processQRCode();
              }
          });
      }

      if (toggleScannerBtn && qrScannerStatus) {
          toggleScannerBtn.addEventListener('click', function() {
              qrScannerActive = !qrScannerActive;
              if (qrScannerActive) {
                  qrScannerStatus.textContent = 'Active';
                  qrScannerStatus.style.background = 'rgba(34, 197, 94, 0.2)';
                  qrScannerStatus.style.color = '#4ade80';
                  toggleScannerBtn.innerHTML = '<i data-lucide="power"></i> Disable';
                  if (qrInput) qrInput.disabled = false;
              } else {
                  qrScannerStatus.textContent = 'Disabled';
                  qrScannerStatus.style.background = 'rgba(239, 68, 68, 0.2)';
                  qrScannerStatus.style.color = '#f87171';
                  toggleScannerBtn.innerHTML = '<i data-lucide="power"></i> Enable';
                  if (qrInput) qrInput.disabled = true;
              }
              if (typeof lucide !== 'undefined') lucide.createIcons();
          });
      }
  });

  function openQRScanner() {
      const qrScanner = document.getElementById('qrScanner');
      const qrInput = document.getElementById('qrInput');
      if (qrScanner) {
          qrScanner.classList.remove('hidden');
          if (qrInput) {
              setTimeout(() => qrInput.focus(), 100);
          }
      }
  }

  function processQRCode() {
      if (qrProcessing || qrCooldown) return;
      const qrInput = document.getElementById('qrInput');
      const qrCode = qrInput ? qrInput.value.trim() : '';

      if (!qrCode) {
          showQRResult('error', 'Error', 'Please enter a QR code');
          return;
      }

      const currentTime = Date.now();
      if (qrCode === lastProcessedQR && (currentTime - lastProcessedTime) < 3000) {
          showQRResult('error', 'Cooldown', 'Please wait a few seconds before rescanning');
          if (qrInput) qrInput.value = '';
          return;
      }

      qrProcessing = true;
      qrCooldown = true;
      showQRResult('info', 'Processing', 'Scanning QR code...');

      fetch('process_qr.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'qr_code=' + encodeURIComponent(qrCode)
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              showQRResult('success', 'Success', data.message);
              lastProcessedQR = qrCode;
              lastProcessedTime = Date.now();
              setTimeout(() => window.location.reload(), 1500);
          } else {
              showQRResult('error', 'Error', data.message || 'Unknown error occurred');
              lastProcessedQR = qrCode;
              lastProcessedTime = Date.now();
          }
      })
      .catch(error => {
          showQRResult('error', 'Error', 'Failed to process QR code');
      })
      .finally(() => {
          qrProcessing = false;
          if (qrInput) qrInput.value = '';
          setTimeout(() => { qrCooldown = false; }, 3000);
      });
  }

  function showQRResult(type, title, message) {
      const qrResult = document.getElementById('qrResult');
      if (!qrResult) return;
      qrResult.style.display = 'block';
      qrResult.style.background = type === 'success' ? 'rgba(34, 197, 94, 0.2)' : (type === 'error' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(59, 130, 246, 0.2)');
      qrResult.style.color = type === 'success' ? '#4ade80' : (type === 'error' ? '#f87171' : '#60a5fa');
      qrResult.style.border = '1px solid ' + (type === 'success' ? 'rgba(34, 197, 94, 0.4)' : (type === 'error' ? 'rgba(239, 68, 68, 0.4)' : 'rgba(59, 130, 246, 0.4)'));
      qrResult.innerHTML = `<strong>${title}:</strong> ${message}`;
      setTimeout(() => { qrResult.style.display = 'none'; }, 4000);
  }
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
