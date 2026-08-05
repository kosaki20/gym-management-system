<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'trainer') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$trainer_id = $_SESSION['user_id'];

// Corrected SQL query joining members (m) and users (u)
$requests_sql = "SELECT mr.*, m.contact_number, m.status as current_status, m.full_name as member_full_name, u.email, u.username
                 FROM membership_renewal_requests mr
                 LEFT JOIN members m ON mr.member_id = m.id
                 LEFT JOIN users u ON m.user_id = u.id
                 WHERE mr.trainer_id = ? OR mr.trainer_id IS NULL
                 ORDER BY mr.created_at DESC";

$renewal_requests = [];
$stmt = $conn->prepare($requests_sql);
if ($stmt) {
    $stmt->bind_param("i", $trainer_id);
    $stmt->execute();
    $requests_result = $stmt->get_result();
    while ($row = $requests_result->fetch_assoc()) {
        $renewal_requests[] = $row;
    }
    $stmt->close();
}

// Membership plans dictionary
$membership_plans = [
    'daily' => ['name' => 'Per Visit', 'price' => 40],
    'weekly' => ['name' => 'Weekly Pass', 'price' => 160],
    'halfmonth' => ['name' => 'Half Month', 'price' => 250],
    'monthly' => ['name' => 'Monthly Membership', 'price' => 400]
];

// Stats
$pending_count = 0;
$completed_count = 0;
$rejected_count = 0;
foreach ($renewal_requests as $req) {
    if ($req['status'] === 'pending') $pending_count++;
    elseif ($req['status'] === 'completed') $completed_count++;
    elseif ($req['status'] === 'rejected') $rejected_count++;
}

$page_title = "Membership Renewal Requests — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="refresh-cw" style="color: var(--accent);"></i>
        Client Membership Renewal Requests
      </h1>
      <p class="gym-page-subtitle">Review, verify payments, and process membership extension requests for your assigned clients.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="membership_status.php" class="gym-btn gym-btn-outline">
        <i data-lucide="users"></i> Member Roster
      </a>
    </div>
  </div>

  <!-- 4 KPI Statistics Cards -->
  <div class="gym-stats-grid">
    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Total Requests</div>
        <div class="gym-stat-number" style="color: var(--accent-light);"><?php echo count($renewal_requests); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">All submitted renewals</div>
      </div>
      <div class="gym-stat-icon"><i data-lucide="inbox"></i></div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Pending Approval</div>
        <div class="gym-stat-number" style="color: #f59e0b;"><?php echo number_format($pending_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Awaiting verification</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3);">
        <i data-lucide="clock"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Completed</div>
        <div class="gym-stat-number" style="color: #4ade80;"><?php echo number_format($completed_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Processed renewals</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border-color: rgba(34, 197, 94, 0.3);">
        <i data-lucide="check-circle-2"></i>
      </div>
    </div>

    <div class="gym-stat-card">
      <div>
        <div class="gym-stat-label">Rejected / Declined</div>
        <div class="gym-stat-number" style="color: #ef4444;"><?php echo number_format($rejected_count); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Declined submissions</div>
      </div>
      <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
        <i data-lucide="x-circle"></i>
      </div>
    </div>
  </div>

  <!-- Renewal Requests Table Card -->
  <div class="gym-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
      <h2 class="gym-card-title" style="margin: 0; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="list" style="color: var(--accent);"></i>
        Pending & Past Renewal Requests
      </h2>
    </div>

    <div class="gym-table-wrapper" style="margin-bottom: 0;">
      <table class="gym-table">
        <thead>
          <tr>
            <th>Member Details</th>
            <th>Requested Plan</th>
            <th>Amount</th>
            <th>Payment Method</th>
            <th>GCash Ref #</th>
            <th>Status</th>
            <th>Date Requested</th>
            <th style="text-align: center;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($renewal_requests)): ?>
            <?php foreach ($renewal_requests as $request): ?>
              <?php
              $display_name = !empty($request['member_name']) ? $request['member_name'] : ($request['member_full_name'] ?? 'Unknown Member');
              $plan_info = $membership_plans[$request['plan_type']]['name'] ?? ucfirst($request['plan_type']);
              ?>
              <tr>
                <td>
                  <div style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($display_name); ?></div>
                  <div style="font-size: 0.82rem; color: var(--text-dim);">
                    <?php echo htmlspecialchars($request['contact_number'] ?? 'No Phone'); ?>
                    <?php if (!empty($request['email'])): ?>
                      &middot; <?php echo htmlspecialchars($request['email']); ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <span class="gym-badge gym-badge-info"><?php echo htmlspecialchars($plan_info); ?></span>
                </td>
                <td style="font-weight: 700; color: #4ade80;">
                  ₱<?php echo number_format($request['amount']); ?>
                </td>
                <td style="text-transform: capitalize; color: var(--text-secondary);">
                  <?php echo htmlspecialchars($request['payment_method']); ?>
                </td>
                <td style="font-family: monospace; font-size: 0.85rem; color: var(--accent);">
                  <?php echo !empty($request['gcash_reference']) ? htmlspecialchars($request['gcash_reference']) : '<span style="color: var(--text-dim);">-</span>'; ?>
                </td>
                <td>
                  <?php if ($request['status'] === 'pending'): ?>
                    <span class="gym-badge gym-badge-pending">Pending</span>
                  <?php elseif ($request['status'] === 'completed'): ?>
                    <span class="gym-badge gym-badge-active">Completed</span>
                  <?php else: ?>
                    <span class="gym-badge gym-badge-inactive">Rejected</span>
                  <?php endif; ?>
                </td>
                <td style="color: var(--text-dim); font-size: 0.84rem;">
                  <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                </td>
                <td>
                  <div style="display: flex; gap: 6px; align-items: center; justify-content: center;">
                    <?php if ($request['status'] === 'pending'): ?>
                      <button type="button" onclick="processRenewal(<?php echo $request['member_id']; ?>, '<?php echo $request['plan_type']; ?>', '<?php echo $request['payment_method']; ?>', this)" class="gym-btn gym-btn-yellow" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important;">
                        <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i> Process
                      </button>

                      <?php if ($request['payment_method'] === 'gcash' && !empty($request['gcash_screenshot'])): ?>
                        <button type="button" onclick="viewScreenshot('<?php echo htmlspecialchars($request['gcash_screenshot']); ?>')" class="gym-btn gym-btn-outline" style="min-height: 32px !important; padding: 4px 10px !important; font-size: 0.78rem !important; color: #60a5fa !important; border-color: rgba(96, 165, 250, 0.3) !important;">
                          <i data-lucide="image" style="width: 14px; height: 14px;"></i> Proof
                        </button>
                      <?php endif; ?>
                    <?php elseif ($request['status'] === 'completed'): ?>
                      <span style="color: #4ade80; font-size: 0.8rem; font-weight: 700;">Done</span>
                    <?php else: ?>
                      <span style="color: #ef4444; font-size: 0.8rem; font-weight: 700;">Declined</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 3rem 1rem;">
                <i data-lucide="inbox" style="width: 42px; height: 42px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
                <p style="font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 0;">No membership renewal requests found.</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Screenshot Proof Modal -->
  <div id="screenshotModal" class="modal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.75); align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); width: 100%; max-width: 520px; padding: 24px; margin: auto;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.2rem; color: var(--accent); margin: 0; display: flex; align-items: center; gap: 8px;">
          <i data-lucide="image"></i> GCash Payment Proof
        </h3>
        <button type="button" onclick="closeScreenshotModal()" style="background: transparent; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.2rem;">
          <i data-lucide="x"></i>
        </button>
      </div>
      <div style="text-align: center; background: #000; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
        <img id="screenshotImage" src="" alt="GCash Screenshot Proof" style="max-width: 100%; max-height: 480px; object-fit: contain; border-radius: 6px;">
      </div>
      <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
        <button type="button" onclick="closeScreenshotModal()" class="gym-btn gym-btn-outline">Close</button>
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

  function viewScreenshot(imagePath) {
      document.getElementById('screenshotImage').src = imagePath;
      const modal = document.getElementById('screenshotModal');
      if (modal) modal.style.display = 'flex';
  }

  function closeScreenshotModal() {
      const modal = document.getElementById('screenshotModal');
      if (modal) modal.style.display = 'none';
  }

  function processRenewal(memberId, planType, paymentMethod, btnElement) {
      if (!confirm('Are you sure you want to process this client membership renewal?')) return;

      const originalText = btnElement.innerHTML;
      btnElement.innerHTML = '<i data-lucide="loader" class="animate-spin" style="width: 14px; height: 14px;"></i> Processing...';
      btnElement.disabled = true;

      fetch('renew_membership.php', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `member_id=${memberId}&plan_type=${planType}&payment_method=${paymentMethod}`
      })
      .then(res => res.json())
      .then(data => {
          if (data.success) {
              alert('Membership renewed successfully!');
              location.reload();
          } else {
              alert('Error processing renewal: ' + (data.message || 'Unknown error'));
              btnElement.innerHTML = originalText;
              btnElement.disabled = false;
          }
      })
      .catch(err => {
          alert('Network communication error. Please try again.');
          btnElement.innerHTML = originalText;
          btnElement.disabled = false;
      });
  }

  window.onclick = function(event) {
      const modal = document.getElementById('screenshotModal');
      if (event.target === modal) modal.style.display = 'none';
  };
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . '/includes/footer.php'; 
?>
