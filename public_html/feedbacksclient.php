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

function getClientFeedback($conn, $user_id) {
    $feedback = [];
    $stmt = $conn->prepare("SELECT f.* FROM feedback f WHERE f.user_id = ? ORDER BY f.created_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $feedback[] = $row;
        }
        $stmt->close();
    }
    return $feedback;
}

// Handle feedback submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    $subject = trim($_POST['subject']);
    $category = trim($_POST['category']);
    $message = trim($_POST['message']);
    $rating = !empty($_POST['rating']) ? (int)$_POST['rating'] : NULL;
    $urgent = isset($_POST['urgent']) ? 1 : 0;

    if (empty($subject) || empty($category) || empty($message)) {
        $error_message = "Please fill in all required fields (Subject, Category, Message).";
    } else {
        $stmt_i = $conn->prepare("
            INSERT INTO feedback (user_id, user_role, subject, category, message, rating, urgent, status, created_at) 
            VALUES (?, 'client', ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        if ($stmt_i) {
            $stmt_i->bind_param("isssii", $logged_in_user_id, $subject, $category, $message, $rating, $urgent);
            if ($stmt_i->execute()) {
                $success_message = "Feedback submitted successfully! Management will review your submission.";
            } else {
                $error_message = "Error submitting feedback: " . $conn->error;
            }
            $stmt_i->close();
        }
    }
}

$feedback_history = getClientFeedback($conn, $logged_in_user_id);

$total_submitted = count($feedback_history);
$pending_count = 0;
$resolved_count = 0;
foreach ($feedback_history as $f) {
    if (($f['status'] ?? 'pending') === 'pending') {
        $pending_count++;
    } else {
        $resolved_count++;
    }
}

$page_title = "My Feedback — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="message-square" style="color: var(--accent);"></i>
        Member Feedback & Suggestions Portal
      </h1>
      <p class="gym-page-subtitle">Submit gym reviews, report equipment issues, or send suggestions to gym management.</p>
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
        <div class="gym-stat-label">Total Feedbacks Sent</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo $total_submitted; ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Submitted suggestions & reviews</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="message-square"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Pending Reviews</div>
        <div class="gym-stat-number" style="color: #f59e0b;"><?php echo $pending_count; ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Awaiting staff inspection</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="clock"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Reviewed & Resolved</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo $resolved_count; ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Processed feedback tickets</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="check-circle-2"></i>
      </div>
    </div>
  </div>

  <!-- 2-Column Workspace -->
  <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    
    <!-- LEFT: SUBMIT FEEDBACK FORM CARD -->
    <div class="gym-card" style="height: fit-content;">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="send" style="color: var(--accent);"></i>
        Send New Feedback
      </h2>

      <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
        <input type="hidden" name="submit_feedback" value="1">
        <input type="hidden" name="rating" id="selectedRatingInput" value="5">

        <div>
          <label class="gym-form-label">Subject *</label>
          <input type="text" name="subject" placeholder="What is your feedback about?" class="gym-form-control" required>
        </div>

        <div>
          <label class="gym-form-label">Category *</label>
          <select name="category" class="gym-form-control" required>
            <option value="workout">Workout Plan & Routines</option>
            <option value="nutrition">Nutrition & Diet Guidelines</option>
            <option value="trainer">Trainer & Coaching Staff</option>
            <option value="facility">Gym Cleanliness & Facilities</option>
            <option value="equipment">Workout Equipment & Machines</option>
            <option value="service">Customer Service</option>
            <option value="other">General / Other</option>
          </select>
        </div>

        <div>
          <label class="gym-form-label">Rating Experience</label>
          <div style="display: flex; gap: 8px; font-size: 1.4rem; color: #f59e0b; cursor: pointer; margin-top: 4px;" id="starRatingContainer">
            <i data-lucide="star" class="star-icon" data-val="1" onclick="setRating(1)" style="fill: #f59e0b;"></i>
            <i data-lucide="star" class="star-icon" data-val="2" onclick="setRating(2)" style="fill: #f59e0b;"></i>
            <i data-lucide="star" class="star-icon" data-val="3" onclick="setRating(3)" style="fill: #f59e0b;"></i>
            <i data-lucide="star" class="star-icon" data-val="4" onclick="setRating(4)" style="fill: #f59e0b;"></i>
            <i data-lucide="star" class="star-icon" data-val="5" onclick="setRating(5)" style="fill: #f59e0b;"></i>
          </div>
        </div>

        <div>
          <label class="gym-form-label">Detailed Message *</label>
          <textarea name="message" rows="4" placeholder="Provide detailed remarks or report an issue..." class="gym-form-control" required></textarea>
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
          <input type="checkbox" name="urgent" id="chkUrgent" style="width: 16px; height: 16px; accent-color: var(--accent);">
          <label for="chkUrgent" style="font-size: 0.85rem; color: var(--text-secondary); cursor: pointer; font-weight: 600;">
            Mark as Urgent Attention Needed
          </label>
        </div>

        <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%; min-height: 42px; margin-top: 6px;">
          <i data-lucide="send"></i> Submit Feedback
        </button>
      </form>
    </div>

    <!-- RIGHT: FEEDBACK HISTORY TABLE CARD -->
    <div>
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
          <i data-lucide="history" style="color: var(--accent);"></i>
          Feedback History & Responses (<?php echo $total_submitted; ?>)
        </h2>

        <div class="gym-table-wrapper" style="margin-bottom: 0;">
          <table class="gym-table">
            <thead>
              <tr>
                <th>Subject & Category</th>
                <th>Rating</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Date Sent</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($feedback_history)): ?>
                <?php foreach ($feedback_history as $fb): ?>
                  <tr>
                    <td>
                      <div style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($fb['subject']); ?></div>
                      <div style="font-size: 0.82rem; color: var(--text-dim); text-transform: capitalize; margin-top: 2px;">
                        Category: <?php echo htmlspecialchars($fb['category']); ?>
                      </div>
                      <p style="font-size: 0.84rem; color: var(--text-secondary); margin: 6px 0 0; background: var(--bg-surface); padding: 8px; border-radius: 6px; border: 1px solid var(--border);">
                        <?php echo htmlspecialchars($fb['message']); ?>
                      </p>
                      <?php if (!empty($fb['response'])): ?>
                        <div style="margin-top: 6px; font-size: 0.82rem; color: #60a5fa; background: rgba(59, 130, 246, 0.1); padding: 6px 10px; border-radius: 4px; border: 1px solid rgba(59, 130, 246, 0.25);">
                          <strong>Response:</strong> <?php echo htmlspecialchars($fb['response']); ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td style="color: #f59e0b; font-weight: 700;">
                      <?php echo !empty($fb['rating']) ? str_repeat('★', (int)$fb['rating']) : '-'; ?>
                    </td>
                    <td>
                      <?php if (!empty($fb['urgent'])): ?>
                        <span class="gym-badge gym-badge-inactive" style="background: rgba(239, 68, 68, 0.2); color: #f87171;">URGENT</span>
                      <?php else: ?>
                        <span class="gym-badge gym-badge-info">NORMAL</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (($fb['status'] ?? 'pending') === 'pending'): ?>
                        <span class="gym-badge gym-badge-pending">Pending</span>
                      <?php else: ?>
                        <span class="gym-badge gym-badge-active">Resolved</span>
                      <?php endif; ?>
                    </td>
                    <td style="color: var(--text-dim); font-size: 0.82rem;"><?php echo date('M j, Y', strtotime($fb['created_at'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                    <i data-lucide="message-square" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                    <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No feedback entries submitted yet.</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
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

  function setRating(val) {
      document.getElementById('selectedRatingInput').value = val;
      const stars = document.querySelectorAll('#starRatingContainer .star-icon');
      stars.forEach(s => {
          const v = parseInt(s.getAttribute('data-val'));
          if (v <= val) {
              s.style.fill = '#f59e0b';
              s.style.color = '#f59e0b';
          } else {
              s.style.fill = 'none';
              s.style.color = '#64748b';
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
