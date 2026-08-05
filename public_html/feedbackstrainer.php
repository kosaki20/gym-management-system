<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'trainer') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$trainer_user_id = $_SESSION['user_id'];

$success_message = '';
$error_message = '';

// Process trainer response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond_feedback'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $admin_notes = trim($_POST['admin_notes']);
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE feedback SET admin_notes = ?, status = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ssi", $admin_notes, $status, $feedback_id);
        if ($stmt->execute()) {
            $success_message = "Feedback response saved successfully!";
        } else {
            $error_message = "Error saving response: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Process trainer feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $subject = trim($_POST['subject']);
    $category = $_POST['category'];
    $message = trim($_POST['message']);
    $rating = !empty($_POST['rating']) ? (int)$_POST['rating'] : NULL;
    $urgent = isset($_POST['urgent']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO feedback (user_id, user_role, subject, category, message, rating, urgent, status, created_at) VALUES (?, 'trainer', ?, ?, ?, ?, ?, 'pending', NOW())");
    if ($stmt) {
        $stmt->bind_param("isssii", $trainer_user_id, $subject, $category, $message, $rating, $urgent);
        if ($stmt->execute()) {
            $success_message = "Your feedback has been submitted successfully!";
        } else {
            $error_message = "Error submitting feedback: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all feedbacks
$feedbacks = [];
$res = $conn->query("SELECT f.*, u.full_name as user_name, u.role as user_role FROM feedback f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $feedbacks[] = $row;
    }
}

// Stats
$total_feedbacks = count($feedbacks);
$ratings = array_filter(array_column($feedbacks, 'rating'));
$avg_rating = !empty($ratings) ? round(array_sum($ratings) / count($ratings), 1) : 0;
$pending_count = 0;
$trainer_count = 0;

foreach ($feedbacks as $fb) {
    if ($fb['status'] === 'pending') $pending_count++;
    if ($fb['category'] === 'trainer' || $fb['user_role'] === 'trainer') $trainer_count++;
}

$page_title = "Feedback & Incident Reports — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="message-square" style="color: var(--accent);"></i>
        Feedback & Incident Reports
      </h1>
      <p class="gym-page-subtitle">Review member comments, submit internal trainer reports, and respond to gym inquiries.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="trainer_dashboard.php" class="gym-btn gym-btn-outline">
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

  <!-- 4 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Feedbacks</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo number_format($total_feedbacks); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Submitted by members & staff</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="message-square"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Average Satisfaction</div>
        <div class="gym-stat-number" style="color: #f59e0b;"><?php echo $avg_rating; ?> <span style="font-size: 1.1rem; color: #f59e0b;">★</span></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Based on 5-star ratings</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="star"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Pending Review</div>
        <div class="gym-stat-number" style="color: #3b82f6;"><?php echo number_format($pending_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Awaiting response</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="clock"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Trainer Submissions</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo number_format($trainer_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Internal trainer logs</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="user-check"></i>
      </div>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">

    <!-- LEFT: SUBMIT FEEDBACK FORM CARD -->
    <div class="gym-card" style="height: fit-content;">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="send" style="color: var(--accent);"></i>
        Submit Feedback / Issue Report
      </h2>

      <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
        <input type="hidden" name="submit_feedback" value="1">

        <div>
          <label class="gym-form-label">Subject *</label>
          <input type="text" name="subject" class="gym-form-control" placeholder="What is your report or feedback about?" required>
        </div>

        <div>
          <label class="gym-form-label">Category *</label>
          <select name="category" class="gym-form-control" required>
            <option value="">Select category...</option>
            <option value="workout">Workout Plans & Routines</option>
            <option value="nutrition">Nutrition & Diet Plans</option>
            <option value="facility">Gym Facility / Hygiene</option>
            <option value="equipment">Equipment Breakdown / Repair</option>
            <option value="service">Customer Service & Support</option>
            <option value="other">General Inquiries / Other</option>
          </select>
        </div>

        <div>
          <label class="gym-form-label">Satisfaction Rating (Optional)</label>
          <div id="starContainer" style="display: flex; gap: 6px; cursor: pointer; color: var(--text-dim); font-size: 1.5rem; user-select: none;">
            <span class="star-btn" data-val="1">★</span>
            <span class="star-btn" data-val="2">★</span>
            <span class="star-btn" data-val="3">★</span>
            <span class="star-btn" data-val="4">★</span>
            <span class="star-btn" data-val="5">★</span>
          </div>
          <input type="hidden" name="rating" id="ratingInput" value="">
        </div>

        <div>
          <label class="gym-form-label">Detailed Message *</label>
          <textarea name="message" rows="4" class="gym-form-control" placeholder="Provide detailed explanation or observation..." required></textarea>
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
          <input type="checkbox" name="urgent" id="urgent_check" style="width: 18px; height: 18px; accent-color: var(--accent); cursor: pointer;">
          <label for="urgent_check" style="font-size: 0.88rem; color: var(--text-secondary); cursor: pointer; font-weight: 500;">
            Mark as Urgent (Requires Immediate Attention)
          </label>
        </div>

        <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%; min-height: 42px; margin-top: 6px;">
          <i data-lucide="send"></i> Submit Feedback Report
        </button>
      </form>
    </div>

    <!-- RIGHT: FEEDBACK ROSTER CARD -->
    <div class="gym-card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 12px;">
        <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
          <i data-lucide="list" style="color: var(--accent);"></i>
          Feedback & Issue Log Roster
        </h2>

        <div style="position: relative; width: 240px;">
          <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: var(--text-dim);"></i>
          <input type="text" id="searchFeedback" placeholder="Search feedback..." class="gym-form-control" style="padding-left: 34px; height: 36px; margin: 0; font-size: 0.85rem;">
        </div>
      </div>

      <div style="display: flex; flex-direction: column; gap: 1rem;" id="feedbackList">
        <?php if (!empty($feedbacks)): ?>
          <?php foreach ($feedbacks as $fb): ?>
            <?php
            $submitter_name = !empty($fb['user_name']) ? $fb['user_name'] : 'Anonymous Member';
            $is_urgent = !empty($fb['urgent']);
            $rating_val = (int)($fb['rating'] ?? 0);
            ?>
            <div class="feedback-card-item" style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 16px;">
              
              <!-- Submitter Info Row -->
              <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                <div>
                  <div style="display: flex; items-center; gap: 8px; flex-wrap: wrap;">
                    <strong style="color: var(--text-primary); font-family: 'Outfit', sans-serif; font-size: 1rem;">
                      <?php echo htmlspecialchars($submitter_name); ?>
                    </strong>

                    <span class="gym-badge gym-badge-info" style="text-transform: capitalize; font-size: 0.72rem;">
                      <?php echo htmlspecialchars($fb['category']); ?>
                    </span>

                    <?php if ($fb['status'] === 'pending'): ?>
                      <span class="gym-badge gym-badge-pending">Pending</span>
                    <?php elseif ($fb['status'] === 'reviewed'): ?>
                      <span class="gym-badge gym-badge-info">Reviewed</span>
                    <?php else: ?>
                      <span class="gym-badge gym-badge-active">Resolved</span>
                    <?php endif; ?>

                    <?php if ($is_urgent): ?>
                      <span class="gym-badge gym-badge-inactive" style="background: rgba(239, 68, 68, 0.2); color: #f87171; border-color: rgba(239, 68, 68, 0.4);">
                        ⚠️ URGENT
                      </span>
                    <?php endif; ?>
                  </div>

                  <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 4px;">
                    Logged on <?php echo date('M j, Y g:i A', strtotime($fb['created_at'])); ?>
                  </div>
                </div>

                <?php if ($rating_val > 0): ?>
                  <div style="color: #f59e0b; font-size: 1rem; font-weight: 700; letter-spacing: 2px;">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                      <?php echo ($s <= $rating_val) ? '★' : '<span style="color: #334155;">★</span>'; ?>
                    <?php endfor; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Subject & Message -->
              <?php if (!empty($fb['subject'])): ?>
                <h4 style="color: var(--accent-light); font-size: 0.95rem; font-weight: 700; margin: 0 0 6px;">
                  <?php echo htmlspecialchars($fb['subject']); ?>
                </h4>
              <?php endif; ?>

              <p style="color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin: 0 0 12px; white-space: pre-wrap;">
                <?php echo htmlspecialchars($fb['message']); ?>
              </p>

              <!-- Trainer Response Box -->
              <?php if (!empty($fb['admin_notes'])): ?>
                <div style="background: rgba(34, 197, 94, 0.08); border-left: 3px solid #22c55e; padding: 10px 14px; border-radius: 0 6px 6px 0; margin-top: 8px;">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <strong style="color: #4ade80; font-size: 0.82rem;">Trainer Response:</strong>
                    <span style="font-size: 0.72rem; color: var(--text-dim);"><?php echo date('M j, Y g:i A', strtotime($fb['updated_at'])); ?></span>
                  </div>
                  <div style="color: var(--text-primary); font-size: 0.85rem; line-height: 1.4;"><?php echo nl2br(htmlspecialchars($fb['admin_notes'])); ?></div>
                </div>
              <?php else: ?>
                <!-- Response Form -->
                <form method="POST" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px; margin-top: 10px;">
                  <input type="hidden" name="feedback_id" value="<?php echo $fb['id']; ?>">
                  <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px; margin-bottom: 8px;">
                    <div>
                      <label class="gym-form-label" style="font-size: 0.75rem;">Set Status</label>
                      <select name="status" class="gym-form-control" style="height: 36px; font-size: 0.82rem;">
                        <option value="pending" <?php echo $fb['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="reviewed" <?php echo $fb['status'] == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                        <option value="resolved" <?php echo $fb['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                      </select>
                    </div>

                    <div>
                      <label class="gym-form-label" style="font-size: 0.75rem;">Your Response Note</label>
                      <input type="text" name="admin_notes" class="gym-form-control" placeholder="Write response to member..." required style="height: 36px; font-size: 0.82rem;">
                    </div>
                  </div>

                  <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" name="respond_feedback" class="gym-btn gym-btn-yellow" style="min-height: 32px !important; padding: 4px 12px !important; font-size: 0.78rem !important;">
                      <i data-lucide="check" style="width: 14px; height: 14px;"></i> Save Response
                    </button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
            <i data-lucide="message-square" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
            <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No feedback entries found.</p>
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

      // Star rating click logic
      const stars = document.querySelectorAll('#starContainer .star-btn');
      const ratingInput = document.getElementById('ratingInput');

      stars.forEach(s => {
          s.addEventListener('click', function() {
              const val = parseInt(this.getAttribute('data-val'));
              ratingInput.value = val;

              stars.forEach(st => {
                  const sv = parseInt(st.getAttribute('data-val'));
                  if (sv <= val) {
                      st.style.color = '#f59e0b';
                  } else {
                      st.style.color = 'var(--text-dim)';
                  }
              });
          });
      });

      // Search feedback list
      const searchInput = document.getElementById('searchFeedback');
      if (searchInput) {
          searchInput.addEventListener('input', function(e) {
              const term = e.target.value.toLowerCase().trim();
              const items = document.querySelectorAll('.feedback-card-item');
              items.forEach(item => {
                  const txt = item.textContent.toLowerCase();
                  item.style.display = txt.includes(term) ? 'block' : 'none';
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
