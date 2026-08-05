<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

$logged_in_user_id = $_SESSION['user_id'];
$unread_count = getUnreadCount($logged_in_user_id, $conn);

// Get client details
$stmt_c = $conn->prepare("SELECT m.* FROM members m JOIN users u ON m.user_id = u.id WHERE u.id = ? AND m.member_type = 'client'");
$client = null;
if ($stmt_c) {
    $stmt_c->bind_param("i", $logged_in_user_id);
    $stmt_c->execute();
    $client = $stmt_c->get_result()->fetch_assoc();
    $stmt_c->close();
}

$membership = $client ?? [];
$days_left = 0;
$status_str = 'inactive';

if (!empty($membership['expiry_date'])) {
    $exp_ts = strtotime($membership['expiry_date']);
    $days_left = ceil(($exp_ts - time()) / 86400);
    $status_str = ($days_left > 0 && ($membership['status'] ?? '') === 'active') ? 'active' : 'expired';
}

// Get assigned trainer
$trainer_id = null;
$trainer_name = null;
$stmt_t = $conn->prepare("
    SELECT u.id, u.full_name 
    FROM trainer_client_assignments tca 
    JOIN users u ON tca.trainer_user_id = u.id 
    WHERE tca.client_user_id = ? AND tca.status = 'active' 
    LIMIT 1
");
if ($stmt_t) {
    $stmt_t->bind_param("i", $logged_in_user_id);
    $stmt_t->execute();
    $res_t = $stmt_t->get_result();
    if ($row_t = $res_t->fetch_assoc()) {
        $trainer_id = $row_t['id'];
        $trainer_name = $row_t['full_name'];
    }
    $stmt_t->close();
}

// Membership plans dictionary
$membership_plans = [
    'daily' => ['name' => 'Per Visit Pass', 'price' => 40, 'duration' => '1 day'],
    'weekly' => ['name' => 'Weekly Membership', 'price' => 160, 'duration' => '7 days'],
    'halfmonth' => ['name' => 'Half Month Pass', 'price' => 250, 'duration' => '15 days'],
    'monthly' => ['name' => 'Monthly Unlimited Pass', 'price' => 400, 'duration' => '30 days']
];

$success_message = '';
$error_message = '';

// Process renewal submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_renewal'])) {
    $plan_type = $_POST['plan_type'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $gcash_ref = trim($_POST['gcash_reference'] ?? '');

    if (empty($plan_type) || !array_key_exists($plan_type, $membership_plans)) {
        $error_message = "Please select a valid membership plan.";
    } elseif (empty($payment_method)) {
        $error_message = "Please select a payment method.";
    } elseif (empty($membership['id'])) {
        $error_message = "Member profile record not found.";
    } else {
        $selected_plan = $membership_plans[$plan_type];
        $member_name = $membership['full_name'] ?? $_SESSION['username'];
        $assigned_tr_id = $trainer_id ?: NULL;

        $stmt_r = $conn->prepare("
            INSERT INTO membership_renewal_requests 
            (member_id, member_name, trainer_id, plan_type, amount, payment_method, gcash_reference, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        if ($stmt_r) {
            $stmt_r->bind_param("isissss", 
                $membership['id'], 
                $member_name, 
                $assigned_tr_id, 
                $plan_type, 
                $selected_plan['price'], 
                $payment_method, 
                $gcash_ref
            );
            if ($stmt_r->execute()) {
                $success_message = "Renewal request submitted successfully! Trainer verification is pending.";
            } else {
                $error_message = "Error submitting renewal request: " . $stmt_r->error;
            }
            $stmt_r->close();
        }
    }
}

// Fetch member's renewal requests history
$renewal_requests = [];
if (!empty($membership['id'])) {
    $stmt_h = $conn->prepare("SELECT * FROM membership_renewal_requests WHERE member_id = ? ORDER BY created_at DESC");
    if ($stmt_h) {
        $stmt_h->bind_param("i", $membership['id']);
        $stmt_h->execute();
        $res_h = $stmt_h->get_result();
        while ($r = $res_h->fetch_assoc()) {
            $renewal_requests[] = $r;
        }
        $stmt_h->close();
    }
}

$page_title = "Membership & Renewals — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="id-card" style="color: var(--accent);"></i>
        My Gym Membership & Renewal Portal
      </h1>
      <p class="gym-page-subtitle">View active membership details, check expiration countdowns, and request plan renewals via GCash or Cash.</p>
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

  <!-- 4 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Active Plan</div>
        <div class="gym-stat-number" style="color: var(--accent-light); text-transform: capitalize; font-size: 1.4rem;">
          <?php echo htmlspecialchars($membership['membership_plan'] ?? 'Standard'); ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Current pass type</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="id-card"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Membership Status</div>
        <div class="gym-stat-number" style="font-size: 1.25rem; margin-top: 4px;">
          <?php if ($status_str === 'active'): ?>
            <span class="gym-badge gym-badge-active">ACTIVE</span>
          <?php else: ?>
            <span class="gym-badge gym-badge-inactive">EXPIRED</span>
          <?php endif; ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 6px;">Pass account status</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="shield-check"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Days Remaining</div>
        <div class="gym-stat-number" style="color: <?php echo $days_left > 7 ? '#4ade80' : ($days_left > 0 ? '#f59e0b' : '#f87171'); ?>;">
          <?php echo $days_left > 0 ? $days_left . ' days' : 'Expired'; ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">
          Expires: <?php echo !empty($membership['expiry_date']) ? date('M j, Y', strtotime($membership['expiry_date'])) : 'N/A'; ?>
        </div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="clock"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Assigned Coach</div>
        <div class="gym-stat-number" style="font-size: 1.2rem; color: #60a5fa; margin-top: 4px;">
          <?php echo $trainer_name ? htmlspecialchars($trainer_name) : 'Gym Staff'; ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Assigned fitness trainer</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
        <i data-lucide="user-check"></i>
      </div>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
    
    <!-- LEFT: SUBMIT RENEWAL REQUEST FORM CARD -->
    <div class="gym-card" style="height: fit-content;">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="refresh-cw" style="color: var(--accent);"></i>
        Request Membership Renewal
      </h2>

      <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
        <input type="hidden" name="request_renewal" value="1">

        <div>
          <label class="gym-form-label">Select Membership Plan *</label>
          <select name="plan_type" id="planSelect" class="gym-form-control" required onchange="updatePlanPriceDisplay()">
            <option value="">Choose a membership tier...</option>
            <?php foreach ($membership_plans as $key => $p): ?>
              <option value="<?php echo $key; ?>" data-price="<?php echo $p['price']; ?>">
                <?php echo htmlspecialchars($p['name']); ?> — ₱<?php echo number_format($p['price']); ?> (<?php echo $p['duration']; ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="gym-form-label">Payment Method *</label>
          <select name="payment_method" id="paymentSelect" class="gym-form-control" required onchange="toggleGCashFields()">
            <option value="gcash">GCash Digital Wallet</option>
            <option value="cash">Over-the-Counter Cash (Gym Counter)</option>
          </select>
        </div>

        <!-- GCash Instructions Box -->
        <div id="gcashBox" style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: var(--radius-sm); padding: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <strong style="color: #60a5fa; font-size: 0.9rem;">GCash Payment Info</strong>
            <span style="font-weight: 800; color: #4ade80; font-size: 0.9rem;" id="displayPriceTag">₱400</span>
          </div>
          <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0 0 10px;">
            Send GCash payment to <strong>0917 123 4567</strong> (Boiyets Fitness Gym).
          </p>

          <label class="gym-form-label" style="font-size: 0.78rem;">GCash Reference Number</label>
          <input type="text" name="gcash_reference" placeholder="e.g. 100234567891" class="gym-form-control" style="height: 38px; font-size: 0.85rem;">
        </div>

        <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%; min-height: 42px; margin-top: 6px;">
          <i data-lucide="send"></i> Submit Renewal Request
        </button>
      </form>
    </div>

    <!-- RIGHT: RENEWAL REQUESTS HISTORY CARD -->
    <div class="gym-card">
      <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1.25rem;">
        <i data-lucide="history" style="color: var(--accent);"></i>
        My Renewal Submissions History
      </h2>

      <div class="gym-table-wrapper" style="margin-bottom: 0;">
        <table class="gym-table">
          <thead>
            <tr>
              <th>Plan Requested</th>
              <th>Amount</th>
              <th>Payment Method</th>
              <th>GCash Ref #</th>
              <th>Status</th>
              <th>Submitted Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($renewal_requests)): ?>
              <?php foreach ($renewal_requests as $req): ?>
                <?php $plan_name = $membership_plans[$req['plan_type']]['name'] ?? ucfirst($req['plan_type']); ?>
                <tr>
                  <td style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($plan_name); ?></td>
                  <td style="font-weight: 700; color: #4ade80;">₱<?php echo number_format($req['amount']); ?></td>
                  <td style="text-transform: capitalize; color: var(--text-secondary);"><?php echo htmlspecialchars($req['payment_method']); ?></td>
                  <td style="font-family: monospace; font-size: 0.84rem; color: var(--accent);">
                    <?php echo !empty($req['gcash_reference']) ? htmlspecialchars($req['gcash_reference']) : '-'; ?>
                  </td>
                  <td>
                    <?php if ($req['status'] === 'pending'): ?>
                      <span class="gym-badge gym-badge-pending">Pending</span>
                    <?php elseif ($req['status'] === 'completed' || $req['status'] === 'paid'): ?>
                      <span class="gym-badge gym-badge-active">Approved</span>
                    <?php else: ?>
                      <span class="gym-badge gym-badge-inactive">Declined</span>
                    <?php endif; ?>
                  </td>
                  <td style="color: var(--text-dim); font-size: 0.82rem;"><?php echo date('M j, Y g:i A', strtotime($req['created_at'])); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                  <i data-lucide="inbox" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                  <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No renewal requests submitted yet.</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
      if (typeof lucide !== 'undefined') {
          lucide.createIcons();
      }
      toggleGCashFields();
  });

  function toggleGCashFields() {
      const paySelect = document.getElementById('paymentSelect');
      const gcashBox = document.getElementById('gcashBox');
      if (paySelect && gcashBox) {
          gcashBox.style.display = (paySelect.value === 'gcash') ? 'block' : 'none';
      }
  }

  function updatePlanPriceDisplay() {
      const select = document.getElementById('planSelect');
      const tag = document.getElementById('displayPriceTag');
      if (select && tag) {
          const opt = select.options[select.selectedIndex];
          const price = opt.getAttribute('data-price');
          if (price) {
              tag.textContent = '₱' + price;
          }
      }
  }
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
