<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'trainer') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';
require_once __DIR__ . '/qr_helper.php';

$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$trainer_user_id = $_SESSION['user_id'];

$message = '';
$message_type = '';

if (isset($_SESSION['qr_management_message'])) {
    $message = $_SESSION['qr_management_message'];
    $message_type = $_SESSION['qr_management_message_type'];
    unset($_SESSION['qr_management_message']);
    unset($_SESSION['qr_management_message_type']);
}

// Handle QR code generation, deletion, regeneration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_qr'])) {
        $member_id = (int)$_POST['member_id'];
        
        $stmt = $conn->prepare("SELECT m.*, u.username FROM members m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        
        if ($member) {
            $qr_dir = 'qrcodes';
            if (!is_dir($qr_dir)) {
                @mkdir($qr_dir, 0755, true);
            }
            
            $filename = "client_" . $member['user_id'] . "_" . time() . ".png";
            $filepath = $qr_dir . '/' . $filename;
            $qr_content = "CLIENT_" . $member['id'];
            
            if (generateQRCodeImage($qr_content, $filepath)) {
                $update_stmt = $conn->prepare("UPDATE members SET qr_code_path = ? WHERE id = ?");
                $update_stmt->bind_param("si", $filepath, $member_id);
                if ($update_stmt->execute()) {
                    $message = "QR code generated successfully for " . $member['full_name'];
                    $message_type = 'success';
                } else {
                    $message = "Error updating database: " . $conn->error;
                    $message_type = 'error';
                }
            } else {
                $message = "Failed to generate QR code image.";
                $message_type = 'error';
            }
        }
    }
    
    if (isset($_POST['delete_qr'])) {
        $member_id = (int)$_POST['member_id'];
        $stmt = $conn->prepare("SELECT qr_code_path FROM members WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result && $result['qr_code_path']) {
            if (file_exists($result['qr_code_path'])) {
                @unlink($result['qr_code_path']);
            }
            $update_stmt = $conn->prepare("UPDATE members SET qr_code_path = NULL WHERE id = ?");
            if ($update_stmt->execute()) {
                $message = "QR code deleted successfully!";
                $message_type = 'success';
            }
        }
    }
    
    if (isset($_POST['regenerate_qr'])) {
        $member_id = (int)$_POST['member_id'];
        
        $stmt = $conn->prepare("SELECT qr_code_path FROM members WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result && $result['qr_code_path'] && file_exists($result['qr_code_path'])) {
            @unlink($result['qr_code_path']);
        }
        
        $stmt = $conn->prepare("SELECT m.*, u.username FROM members m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        
        if ($member) {
            $qr_dir = 'qrcodes';
            if (!is_dir($qr_dir)) {
                @mkdir($qr_dir, 0755, true);
            }
            $filename = "client_" . $member['user_id'] . "_" . time() . ".png";
            $filepath = $qr_dir . '/' . $filename;
            $qr_content = "CLIENT_" . $member['id'];
            
            if (generateQRCodeImage($qr_content, $filepath)) {
                $update_stmt = $conn->prepare("UPDATE members SET qr_code_path = ? WHERE id = ?");
                $update_stmt->bind_param("si", $filepath, $member_id);
                if ($update_stmt->execute()) {
                    $message = "QR code regenerated successfully for " . $member['full_name'];
                    $message_type = 'success';
                }
            } else {
                $message = "Failed to regenerate QR code image.";
                $message_type = 'error';
            }
        }
    }
    
    if ($message) {
        $_SESSION['qr_management_message'] = $message;
        $_SESSION['qr_management_message_type'] = $message_type;
    }
    header('Location: trainermanageqrcodes.php');
    exit();
}

// Fetch clients
$clients_result = $conn->query("
    SELECT m.id, m.full_name, m.membership_plan, m.expiry_date, m.status, m.qr_code_path, 
           u.username, u.email, m.created_at 
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.member_type = 'client' 
    ORDER BY m.created_at DESC
");

$total_clients = $clients_result ? $clients_result->num_rows : 0;
$generated_qr_count = 0;
$active_members_count = 0;

if ($clients_result && $clients_result->num_rows > 0) {
    while ($c = $clients_result->fetch_assoc()) {
        if ($c['qr_code_path'] && file_exists($c['qr_code_path'])) {
            $generated_qr_count++;
        }
        if ($c['status'] === 'active') {
            $active_members_count++;
        }
    }
    $clients_result->data_seek(0);
}

$pending_qr_count = $total_clients - $generated_qr_count;

$page_title = "Client QR Code Management — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="qr-code" style="color: var(--accent);"></i>
        Client QR Code Access Management
      </h1>
      <p class="gym-page-subtitle">Generate, regenerate, and manage digital QR check-in passes for gym members.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="attendance_logs.php" class="gym-btn gym-btn-yellow">
        <i data-lucide="clipboard-check"></i> Attendance Logs
      </a>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div style="background: <?php echo $message_type === 'success' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; border: 1px solid <?php echo $message_type === 'success' ? 'rgba(34, 197, 94, 0.4)' : 'rgba(239, 68, 68, 0.4)'; ?>; color: <?php echo $message_type === 'success' ? '#4ade80' : '#f87171'; ?>; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle-2' : 'alert-triangle'; ?>" style="width: 18px; height: 18px;"></i>
      <span><?php echo htmlspecialchars($message); ?></span>
    </div>
  <?php endif; ?>

  <!-- 4 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Clients</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo number_format($total_clients); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Registered gym members</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="users"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">QR Codes Active</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo number_format($generated_qr_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Generated digital passes</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="qr-code"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Pending QR Codes</div>
        <div class="gym-stat-number" style="color: #f59e0b;"><?php echo number_format($pending_qr_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Awaiting QR generation</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="alert-circle"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Active Memberships</div>
        <div class="gym-stat-number" style="color: #60a5fa;"><?php echo number_format($active_members_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Active gym accounts</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="id-card"></i>
      </div>
    </div>
  </div>

  <!-- Client Directory Table Card -->
  <div class="gym-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
      <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="list" style="color: var(--accent);"></i>
        Client QR Code Directory
      </h2>
      <div style="position: relative; width: 280px; max-width: 100%;">
        <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-dim);"></i>
        <input type="text" id="searchQRClients" placeholder="Search client name or email..." class="gym-form-control" style="padding-left: 38px; height: 40px; margin: 0;">
      </div>
    </div>

    <div class="gym-table-wrapper" style="margin-bottom: 0;">
      <table class="gym-table">
        <thead>
          <tr>
            <th>Client Info</th>
            <th>Membership Status</th>
            <th>QR Status</th>
            <th style="text-align: center;">QR Code Pass</th>
            <th style="text-align: center;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($clients_result && $clients_result->num_rows > 0): ?>
            <?php while ($client = $clients_result->fetch_assoc()): ?>
              <?php $has_qr = ($client['qr_code_path'] && file_exists($client['qr_code_path'])); ?>
              <tr>
                <td>
                  <div style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($client['full_name']); ?></div>
                  <div style="font-size: 0.82rem; color: var(--text-dim);">@<?php echo htmlspecialchars($client['username']); ?> &middot; <?php echo htmlspecialchars($client['email']); ?></div>
                  <span style="font-size: 0.72rem; color: var(--accent); font-weight: 700;">ID: CLIENT_<?php echo $client['id']; ?></span>
                </td>
                <td>
                  <span class="gym-badge gym-badge-info" style="text-transform: capitalize;"><?php echo htmlspecialchars($client['membership_plan']); ?></span>
                  <div style="margin-top: 4px;">
                    <?php if ($client['status'] === 'active'): ?>
                      <span class="gym-badge gym-badge-active">ACTIVE</span>
                    <?php else: ?>
                      <span class="gym-badge gym-badge-inactive">EXPIRED</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <?php if ($has_qr): ?>
                    <span class="gym-badge gym-badge-active">
                      <i data-lucide="check-circle-2" style="width: 12px; height: 12px; display: inline;"></i> Generated
                    </span>
                  <?php else: ?>
                    <span class="gym-badge gym-badge-pending">
                      <i data-lucide="clock" style="width: 12px; height: 12px; display: inline;"></i> Not Generated
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align: center;">
                  <?php if ($has_qr): ?>
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                      <img src="<?php echo htmlspecialchars($client['qr_code_path']); ?>" alt="QR Code" style="width: 64px; height: 64px; border-radius: 8px; border: 1px solid var(--border); background: #fff; padding: 4px;">
                      <a href="<?php echo htmlspecialchars($client['qr_code_path']); ?>" download style="font-size: 0.75rem; color: #60a5fa; font-weight: 700; text-decoration: underline;">Download</a>
                    </div>
                  <?php else: ?>
                    <span style="font-size: 0.78rem; color: var(--text-dim);">- No QR Code -</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display: flex; gap: 6px; align-items: center; justify-content: center; flex-wrap: wrap;">
                    <?php if (!$has_qr): ?>
                      <form method="POST" style="margin: 0;">
                        <input type="hidden" name="member_id" value="<?php echo $client['id']; ?>">
                        <button type="submit" name="generate_qr" class="gym-btn gym-btn-yellow" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                          <i data-lucide="plus" style="width: 14px; height: 14px;"></i> Generate
                        </button>
                      </form>
                    <?php else: ?>
                      <form method="POST" style="margin: 0;">
                        <input type="hidden" name="member_id" value="<?php echo $client['id']; ?>">
                        <button type="submit" name="regenerate_qr" class="gym-btn gym-btn-outline" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important; color: #4ade80 !important; border-color: rgba(74, 222, 128, 0.3) !important;">
                          <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i> Refresh
                        </button>
                      </form>

                      <form method="POST" onsubmit="return confirm('Are you sure you want to delete this QR code?');" style="margin: 0;">
                        <input type="hidden" name="member_id" value="<?php echo $client['id']; ?>">
                        <button type="submit" name="delete_qr" class="gym-btn gym-btn-danger" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                          <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i> Delete
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                <i data-lucide="qr-code" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No client records found.</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
      if (typeof lucide !== 'undefined') {
          lucide.createIcons();
      }

      const searchInput = document.getElementById('searchQRClients');
      if (searchInput) {
          searchInput.addEventListener('input', function(e) {
              const term = e.target.value.toLowerCase().trim();
              const rows = document.querySelectorAll('.gym-table tbody tr');
              rows.forEach(row => {
                  const text = row.textContent.toLowerCase();
                  row.style.display = text.includes(term) ? '' : 'none';
              });
          });
      }
  });
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
