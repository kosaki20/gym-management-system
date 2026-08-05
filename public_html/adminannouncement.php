<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['create_announcement'])) {
        $title = mysqli_real_escape_string($conn, $_POST['announcement_title']);
        $content = mysqli_real_escape_string($conn, $_POST['announcement_content']);
        $priority = mysqli_real_escape_string($conn, $_POST['priority']);
        $target_audience = mysqli_real_escape_string($conn, $_POST['target_audience']);
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        $sql = "INSERT INTO announcements (title, content, created_by, priority, target_audience, expiry_date) 
                VALUES ('$title', '$content', '" . $_SESSION['username'] . "', '$priority', '$target_audience', " . 
                ($expiry_date ? "'$expiry_date'" : "NULL") . ")";
        
        if ($conn->query($sql)) {
            $message = "Announcement published successfully!";
            $messageType = "success";
        } else {
            $message = "Error creating announcement: " . $conn->error;
            $messageType = "error";
        }
    }
    
    if (isset($_POST['delete_announcement'])) {
        $announcement_id = intval($_POST['announcement_id']);
        $sql = "DELETE FROM announcements WHERE id = $announcement_id";
        
        if ($conn->query($sql)) {
            $message = "Announcement deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Error deleting announcement: " . $conn->error;
            $messageType = "error";
        }
    }
    
    if (isset($_POST['update_announcement'])) {
        $announcement_id = intval($_POST['announcement_id']);
        $title = mysqli_real_escape_string($conn, $_POST['edit_title']);
        $content = mysqli_real_escape_string($conn, $_POST['edit_content']);
        $priority = mysqli_real_escape_string($conn, $_POST['edit_priority']);
        $target_audience = mysqli_real_escape_string($conn, $_POST['edit_target_audience']);
        $expiry_date = !empty($_POST['edit_expiry_date']) ? $_POST['edit_expiry_date'] : null;
        
        $sql = "UPDATE announcements SET 
                title = '$title', 
                content = '$content', 
                priority = '$priority', 
                target_audience = '$target_audience', 
                expiry_date = " . ($expiry_date ? "'$expiry_date'" : "NULL") . "
                WHERE id = $announcement_id";
        
        if ($conn->query($sql)) {
            $message = "Announcement updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating announcement: " . $conn->error;
            $messageType = "error";
        }
    }
}

// Fetch all announcements
$announcements_result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");

$page_title = "Announcement Management — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="megaphone" style="color: var(--accent);"></i>
        Gym Announcements & Broadcasts
      </h1>
      <p class="gym-page-subtitle">Publish system notices, gym schedules, maintenance alerts, and promotional announcements for members and trainers.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="#createAnnouncementCard" class="gym-btn gym-btn-yellow">
        <i data-lucide="plus"></i> New Broadcast
      </a>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div style="background: <?php echo $messageType === 'success' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; border: 1px solid <?php echo $messageType === 'success' ? 'rgba(34, 197, 94, 0.4)' : 'rgba(239, 68, 68, 0.4)'; ?>; color: <?php echo $messageType === 'success' ? '#4ade80' : '#f87171'; ?>; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="<?php echo $messageType === 'success' ? 'check-circle-2' : 'alert-triangle'; ?>" style="width: 18px; height: 18px; color: <?php echo $messageType === 'success' ? '#22c55e' : '#ef4444'; ?>;"></i>
      <span><?php echo htmlspecialchars($message); ?></span>
    </div>
  <?php endif; ?>

  <!-- Create Announcement Form Card -->
  <div class="gym-card" id="createAnnouncementCard" style="margin-bottom: 1.5rem;">
    <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
      <i data-lucide="plus-circle" style="color: var(--accent);"></i>
      Create New Gym Announcement
    </h2>

    <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
        <div>
          <label class="gym-form-label">Announcement Title *</label>
          <input type="text" name="announcement_title" class="gym-form-control" placeholder="e.g. Gym Maintenance Notice or Holiday Hours" required>
        </div>
        
        <div>
          <label class="gym-form-label">Target Audience *</label>
          <select name="target_audience" class="gym-form-control" required>
            <option value="all">All Users & Members</option>
            <option value="clients">Clients / Members Only</option>
            <option value="trainers">Fitness Trainers Only</option>
          </select>
        </div>
      </div>
      
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
        <div>
          <label class="gym-form-label">Priority Level *</label>
          <select name="priority" class="gym-form-control" required>
            <option value="low">Low Priority (General Info)</option>
            <option value="medium" selected>Medium Priority (Standard Notice)</option>
            <option value="high">High Priority (Urgent Alert)</option>
          </select>
        </div>
        
        <div>
          <label class="gym-form-label">Expiration Date (Optional)</label>
          <input type="date" name="expiry_date" class="gym-form-control" min="<?php echo date('Y-m-d'); ?>">
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Leave blank for permanent display</div>
        </div>
      </div>
      
      <div>
        <label class="gym-form-label">Announcement Details & Content *</label>
        <textarea name="announcement_content" rows="4" class="gym-form-control" placeholder="Write full announcement description, rules, schedule adjustments..." required></textarea>
      </div>
      
      <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
        <button type="submit" name="create_announcement" class="gym-btn gym-btn-yellow" style="min-height: 42px; padding: 0 24px;">
          <i data-lucide="send"></i> Publish Announcement
        </button>
      </div>
    </form>
  </div>

  <!-- Announcements Feed Card -->
  <div class="gym-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
      <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="list" style="color: var(--accent);"></i>
        Active Published Announcements
      </h2>
      <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-dim);">
        <?php echo $announcements_result ? $announcements_result->num_rows : 0; ?> total broadcast(s)
      </span>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1rem;">
      <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
        <?php while($announcement = $announcements_result->fetch_assoc()): ?>
          <?php
          $priority_badge = '';
          switch(strtolower($announcement['priority'])) {
            case 'high':
              $priority_badge = '<span class="gym-badge gym-badge-inactive" style="background: rgba(239, 68, 68, 0.2); color: #f87171; border-color: rgba(239, 68, 68, 0.4);"><i data-lucide="alert-triangle" style="width: 12px; height: 12px;"></i> High Priority</span>';
              break;
            case 'medium':
              $priority_badge = '<span class="gym-badge gym-badge-pending"><i data-lucide="info" style="width: 12px; height: 12px;"></i> Medium Priority</span>';
              break;
            case 'low':
              $priority_badge = '<span class="gym-badge gym-badge-active"><i data-lucide="check" style="width: 12px; height: 12px;"></i> Low Priority</span>';
              break;
            default:
              $priority_badge = '<span class="gym-badge">' . ucfirst($announcement['priority']) . '</span>';
          }
          ?>
          <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 18px; transition: border-color 0.2s ease;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
              <div>
                <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.15rem; color: var(--text-primary); margin: 0 0 6px;">
                  <?php echo htmlspecialchars($announcement['title']); ?>
                </h3>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                  <?php echo $priority_badge; ?>
                  <span class="gym-badge gym-badge-info" style="text-transform: capitalize;">
                    <i data-lucide="users" style="width: 12px; height: 12px;"></i> Target: <?php echo htmlspecialchars($announcement['target_audience']); ?>
                  </span>
                  <?php if (!empty($announcement['expiry_date'])): ?>
                    <span style="font-size: 0.78rem; color: var(--text-dim); display: flex; align-items: center; gap: 4px;">
                      <i data-lucide="clock" style="width: 12px; height: 12px;"></i> Expires: <?php echo date('M j, Y', strtotime($announcement['expiry_date'])); ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>

              <div style="display: flex; gap: 6px; align-items: center;">
                <button type="button" onclick="openEditModal(<?php echo $announcement['id']; ?>, '<?php echo addslashes($announcement['title']); ?>', '<?php echo addslashes(str_replace(array("\r", "\n"), array('\r', '\n'), $announcement['content'])); ?>', '<?php echo $announcement['priority']; ?>', '<?php echo $announcement['target_audience']; ?>', '<?php echo $announcement['expiry_date']; ?>')" 
                        class="gym-btn gym-btn-outline" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important; color: #60a5fa !important; border-color: rgba(96, 165, 250, 0.3) !important;">
                  <i data-lucide="edit" style="width: 14px; height: 14px;"></i> Edit
                </button>
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this announcement?');" style="margin: 0;">
                  <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                  <button type="submit" name="delete_announcement" class="gym-btn gym-btn-danger" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i> Delete
                  </button>
                </form>
              </div>
            </div>

            <p style="color: var(--text-secondary); font-size: 0.92rem; line-height: 1.5; margin: 0 0 14px; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>

            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: var(--text-dim); border-top: 1px solid var(--border); padding-top: 10px;">
              <span>Posted by: <strong style="color: var(--accent);"><?php echo htmlspecialchars($announcement['created_by']); ?></strong></span>
              <span><i data-lucide="calendar" style="width: 12px; height: 12px; display: inline;"></i> <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?></span>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
          <i data-lucide="megaphone" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
          <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No active announcements found. Create your first announcement above.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Edit Announcement Modal -->
  <div id="editModal" class="modal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.7); align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); width: 100%; max-width: 580px; padding: 24px; margin: auto;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.2rem; color: var(--accent); margin: 0; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="edit"></i> Edit Announcement
        </h3>
        <button type="button" onclick="closeEditModal()" style="background: transparent; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.2rem;">
          <i data-lucide="x"></i>
        </button>
      </div>

      <form method="POST" id="editForm" style="display: flex; flex-direction: column; gap: 14px;">
        <input type="hidden" name="announcement_id" id="edit_announcement_id">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div>
            <label class="gym-form-label">Title *</label>
            <input type="text" id="edit_title" name="edit_title" class="gym-form-control" required>
          </div>
          
          <div>
            <label class="gym-form-label">Target Audience *</label>
            <select id="edit_target_audience" name="edit_target_audience" class="gym-form-control" required>
              <option value="all">All Users & Members</option>
              <option value="clients">Clients Only</option>
              <option value="trainers">Trainers Only</option>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div>
            <label class="gym-form-label">Priority Level *</label>
            <select id="edit_priority" name="edit_priority" class="gym-form-control" required>
              <option value="low">Low Priority</option>
              <option value="medium">Medium Priority</option>
              <option value="high">High Priority</option>
            </select>
          </div>

          <div>
            <label class="gym-form-label">Expiry Date (Optional)</label>
            <input type="date" id="edit_expiry_date" name="edit_expiry_date" class="gym-form-control">
          </div>
        </div>

        <div>
          <label class="gym-form-label">Content *</label>
          <textarea id="edit_content" name="edit_content" rows="4" class="gym-form-control" required></textarea>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 10px;">
          <button type="button" onclick="closeEditModal()" class="gym-btn gym-btn-outline" style="flex: 1;">Cancel</button>
          <button type="submit" name="update_announcement" class="gym-btn gym-btn-yellow" style="flex: 1;">Update Announcement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
      if (typeof lucide !== 'undefined') {
          lucide.createIcons();
      }
      
      const today = new Date().toISOString().split('T')[0];
      const expiryInput = document.querySelector('input[name="expiry_date"]');
      const editExpiryInput = document.getElementById('edit_expiry_date');
      if (expiryInput) expiryInput.min = today;
      if (editExpiryInput) editExpiryInput.min = today;
  });

  function openEditModal(id, title, content, priority, audience, expiryDate) {
      const modal = document.getElementById('editModal');
      if (modal) {
          modal.style.display = 'flex';
          document.getElementById('edit_announcement_id').value = id;
          document.getElementById('edit_title').value = title;
          document.getElementById('edit_content').value = content;
          document.getElementById('edit_priority').value = priority;
          document.getElementById('edit_target_audience').value = audience;
          document.getElementById('edit_expiry_date').value = expiryDate || '';
      }
  }

  function closeEditModal() {
      const modal = document.getElementById('editModal');
      if (modal) modal.style.display = 'none';
  }

  window.onclick = function(event) {
      const modal = document.getElementById('editModal');
      if (event.target === modal) closeEditModal();
  };
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
