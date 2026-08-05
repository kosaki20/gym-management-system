<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'client') {
    header("Location: index.php");
    exit();
}

// Database connection

require_once __DIR__ . '/../config/config.php';

// Check connection

// Get the logged-in client's user ID from session
$logged_in_user_id = $_SESSION['user_id'];

// Function to get client's QR code information
function getClientQRCode($conn, $user_id) {
    $sql = "SELECT m.qr_code_path, m.full_name, m.id as member_id, u.username, m.expiry_date, m.membership_plan
            FROM members m 
            INNER JOIN users u ON m.user_id = u.id 
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

$qrCodeInfo = getClientQRCode($conn, $logged_in_user_id);
$conn->close();
?>

<?php
$page_title = "BOIYETS FITNESS GYM - My QR Code";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<div class="gym-main-container">
  <!-- Topbar -->
  

  
    <div class="  rounded-xl p-8 text-center" style="background: var(--bg-card);">
      <h2 class="text-2xl font-bold text-yellow-400 mb-2">Your Gym Access QR Code</h2>
      <p class="text-gray-400 mb-6">Scan this code at the gym entrance for attendance tracking</p>
      
      <?php if ($qrCodeInfo && $qrCodeInfo['qr_code_path'] && file_exists($qrCodeInfo['qr_code_path'])): ?>
        <div class="bg-white p-6 rounded-lg inline-block mb-6">
          <img src="<?php echo $qrCodeInfo['qr_code_path']; ?>" 
               alt="Your QR Code" 
               class="w-64 h-64 mx-auto">
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div class="bg-gray-700 rounded-lg p-4 text-left">
            <h3 class="font-semibold text-yellow-400 mb-2">Member Information</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($qrCodeInfo['full_name']); ?></p>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($qrCodeInfo['username']); ?></p>
            <p><strong>Member ID:</strong> <?php echo $qrCodeInfo['member_id']; ?></p>
            <p><strong>Plan:</strong> <?php echo ucfirst($qrCodeInfo['membership_plan']); ?></p>
            <p><strong>Expiry:</strong> <?php echo date('M j, Y', strtotime($qrCodeInfo['expiry_date'])); ?></p>
          </div>
          
          <div class="bg-gray-700 rounded-lg p-4 text-left">
            <h3 class="font-semibold text-yellow-400 mb-2">Usage Instructions</h3>
            <ul class="space-y-2 text-sm">
              <li>• Show this QR code at the gym entrance</li>
              <li>• Trainer will scan it to record your attendance</li>
              <li>• Keep this code secure</li>
              <li>• Download a copy for offline use</li>
              <li>• Contact trainer if code doesn't work</li>
            </ul>
          </div>
        </div>
        
        <div class="flex gap-4 justify-center">
          <a href="<?php echo $qrCodeInfo['qr_code_path']; ?>" 
             download="boiyets_qr_code_<?php echo htmlspecialchars($qrCodeInfo['username']); ?>.png" 
             class="bg-yellow-500 hover:bg-yellow-600 text-black px-6 py-3 rounded-lg font-semibold flex items-center gap-2">
            <i data-lucide="download"></i> Download QR Code
          </a>
          <a href="client_dashboard.php" class="bg-gray-600 hover:bg-gray-700 px-6 py-3 rounded-lg font-semibold flex items-center gap-2">
            <i data-lucide="home"></i> Back to Dashboard
          </a>
        </div>
      <?php else: ?>
        <div class="bg-red-900/30 border border-red-700 rounded-lg p-8 max-w-md mx-auto">
          <i data-lucide="qrcode" class="w-16 h-16 text-red-400 mx-auto mb-4"></i>
          <h3 class="text-xl font-semibold text-red-400 mb-2">QR Code Not Available</h3>
          <p class="text-gray-300 mb-4">Your QR code hasn't been generated yet. Please contact your trainer to generate your QR code for gym access.</p>
          <a href="client_dashboard.php" class="bg-gray-600 hover:bg-gray-700 px-6 py-2 rounded-lg font-semibold inline-flex items-center gap-2">
            <i data-lucide="home"></i> Back to Dashboard
          </a>
        </div>
      <?php endif; ?>
    </div>
  

  <script>
    lucide.createIcons();
  </script>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
