<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

$user_id = (int)$_SESSION['user_id'];
$unread_count = getUnreadCount($user_id, $conn);

// Auto-create trainer_bookings table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS trainer_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_user_id INT NOT NULL,
    trainer_user_id INT NOT NULL,
    session_type VARCHAR(100) NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    notes TEXT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$message = '';
$message_type = '';

// Handle Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $trainer_id = (int)$_POST['trainer_id'];
    $session_type = trim($_POST['session_type']);
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $notes = trim($_POST['notes']);

    if (empty($trainer_id) || empty($session_type) || empty($booking_date) || empty($start_time)) {
        $message = "Please fill in all required booking fields.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO trainer_bookings (client_user_id, trainer_user_id, session_type, booking_date, start_time, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iissss", $user_id, $trainer_id, $session_type, $booking_date, $start_time, $notes);
        if ($stmt->execute()) {
            $message = "Your training session request has been submitted! Waiting for trainer confirmation.";
            $message_type = "success";
        } else {
            $message = "Error submitting booking: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Fetch available trainers
$trainers_result = $conn->query("SELECT id, full_name, username FROM users WHERE role = 'trainer' ORDER BY full_name ASC");

// Fetch client's existing bookings
$stmt_b = $conn->prepare("
    SELECT tb.*, u.full_name as trainer_name 
    FROM trainer_bookings tb 
    JOIN users u ON tb.trainer_user_id = u.id 
    WHERE tb.client_user_id = ? 
    ORDER BY tb.booking_date DESC, tb.start_time DESC
");
$stmt_b->bind_param("i", $user_id);
$stmt_b->execute();
$my_bookings = $stmt_b->get_result();

$page_title = "Book Training Session — Boiyets Fitness Gym";
require_once __DIR__ . "/includes/header.php";
?>

<div class="gym-layout">
  <?php require_once __DIR__ . "/includes/nav.php"; ?>

  <main class="gym-main-container">
    <div class="gym-page-header">
      <div class="gym-page-title-group">
        <h1 class="gym-page-title">
          <i data-lucide="calendar" class="gym-title-icon" style="color: var(--accent);"></i>
          Book Training Session
        </h1>
        <p class="gym-page-subtitle">Schedule 1-on-1 personal training, workout reviews, or meal consultation sessions.</p>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="gym-alert gym-alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>" style="margin-bottom: 20px;">
        <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>"></i>
        <span><?php echo htmlspecialchars($message); ?></span>
      </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
      
      <!-- BOOKING FORM -->
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
          <i data-lucide="plus-circle" style="color: var(--accent);"></i>
          New Session Request
        </h2>

        <form method="POST" style="display: flex; flex-direction: column; gap: 16px;">
          <input type="hidden" name="submit_booking" value="1">

          <div>
            <label class="gym-form-label">Select Personal Trainer</label>
            <select name="trainer_id" class="gym-form-control" required>
              <option value="">-- Choose a Trainer --</option>
              <?php if ($trainers_result && $trainers_result->num_rows > 0): ?>
                <?php while ($tr = $trainers_result->fetch_assoc()): ?>
                  <option value="<?php echo $tr['id']; ?>"><?php echo htmlspecialchars($tr['full_name'] ?: $tr['username']); ?></option>
                <?php endwhile; ?>
              <?php endif; ?>
            </select>
          </div>

          <div>
            <label class="gym-form-label">Session Type</label>
            <select name="session_type" class="gym-form-control" required>
              <option value="1-on-1 Personal Training">1-on-1 Personal Training</option>
              <option value="Strength & Powerlifting Assessment">Strength & Powerlifting Assessment</option>
              <option value="Body Composition & Meal Plan Review">Body Composition & Meal Plan Review</option>
              <option value="HIIT / Cardio Session">HIIT / Cardio Session</option>
            </select>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <div>
              <label class="gym-form-label">Preferred Date</label>
              <input type="date" name="booking_date" class="gym-form-control" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div>
              <label class="gym-form-label">Preferred Time Slot</label>
              <select name="start_time" class="gym-form-control" required>
                <option value="08:00:00">08:00 AM</option>
                <option value="10:00:00">10:00 AM</option>
                <option value="14:00:00">02:00 PM</option>
                <option value="16:00:00">04:00 PM</option>
                <option value="18:00:00">06:00 PM</option>
              </select>
            </div>
          </div>

          <div>
            <label class="gym-form-label">Session Goals or Notes (Optional)</label>
            <textarea name="notes" class="gym-form-control" rows="3" placeholder="Tell your trainer what you'd like to focus on..."></textarea>
          </div>

          <button type="submit" class="gym-btn gym-btn-yellow" style="min-height: 42px; margin-top: 10px;">
            <i data-lucide="send"></i> Request Booking
          </button>
        </form>
      </div>

      <!-- MY BOOKINGS LIST -->
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
          <i data-lucide="clock" style="color: #60a5fa;"></i>
          My Booking History
        </h2>

        <div class="gym-table-wrapper">
          <table class="gym-table">
            <thead>
              <tr>
                <th>Trainer</th>
                <th>Session</th>
                <th>Date & Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($my_bookings && $my_bookings->num_rows > 0): ?>
                <?php while ($b = $my_bookings->fetch_assoc()): ?>
                  <?php
                    $status_badge = '<span class="gym-badge gym-badge-pending">Pending</span>';
                    if ($b['status'] === 'confirmed') $status_badge = '<span class="gym-badge gym-badge-active">Confirmed</span>';
                    if ($b['status'] === 'cancelled') $status_badge = '<span class="gym-badge gym-badge-inactive">Cancelled</span>';
                  ?>
                  <tr>
                    <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($b['trainer_name']); ?></td>
                    <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($b['session_type']); ?></td>
                    <td style="color: var(--text-dim);">
                      <?php echo date('M j, Y', strtotime($b['booking_date'])); ?><br>
                      <small><?php echo date('g:i A', strtotime($b['start_time'])); ?></small>
                    </td>
                    <td><?php echo $status_badge; ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" style="text-align: center; color: var(--text-dim); padding: 30px;">
                    No training sessions requested yet.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
<?php $conn->close(); ?>
