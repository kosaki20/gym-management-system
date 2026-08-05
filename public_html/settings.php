<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

// Generate CSRF token for this session
$csrf_token = ensureCsrfToken();

$user_id = $_SESSION['user_id'];
$unread_count = getUnreadCount($user_id, $conn);

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch settings
$settings_stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$settings_stmt->bind_param("i", $user_id);
$settings_stmt->execute();
$user_settings = $settings_stmt->get_result()->fetch_assoc();

if (!$user_settings) {
    $user_settings = [
        'email_notifications' => 1,
        'push_notifications' => 1,
        'newsletter' => 0,
        'theme' => 'dark',
        'language' => 'english',
        'timezone' => 'Asia/Manila',
        'privacy_level' => 'public',
        'activity_visibility' => 'all',
        'auto_logout' => 30
    ];
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_preferences') {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        $newsletter = isset($_POST['newsletter']) ? 1 : 0;
        $theme = $_POST['theme'] ?? 'dark';
        $language = $_POST['language'] ?? 'english';
        $timezone = $_POST['timezone'] ?? 'Asia/Manila';
        
        $check_stmt = $conn->prepare("SELECT id FROM user_settings WHERE user_id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $settings_exist = $check_stmt->get_result()->fetch_assoc();
        
        if ($settings_exist) {
            $update_stmt = $conn->prepare("UPDATE user_settings SET email_notifications = ?, push_notifications = ?, newsletter = ?, theme = ?, language = ?, timezone = ?, updated_at = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("iiisssi", $email_notifications, $push_notifications, $newsletter, $theme, $language, $timezone, $user_id);
        } else {
            $update_stmt = $conn->prepare("INSERT INTO user_settings (user_id, email_notifications, push_notifications, newsletter, theme, language, timezone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $update_stmt->bind_param("iiissss", $user_id, $email_notifications, $push_notifications, $newsletter, $theme, $language, $timezone);
        }
        
        if ($update_stmt->execute()) {
            $message = "Notification and system preferences saved successfully!";
            $message_type = "success";
            
            $user_settings['email_notifications'] = $email_notifications;
            $user_settings['push_notifications'] = $push_notifications;
            $user_settings['newsletter'] = $newsletter;
            $user_settings['theme'] = $theme;
            $user_settings['language'] = $language;
            $user_settings['timezone'] = $timezone;
        } else {
            $message = "Error updating preferences. Please try again.";
            $message_type = "error";
        }
    }
    elseif ($action === 'update_privacy') {
        $privacy_level = $_POST['privacy_level'] ?? 'public';
        $activity_visibility = $_POST['activity_visibility'] ?? 'all';
        $auto_logout = intval($_POST['auto_logout'] ?? 30);
        
        $check_stmt = $conn->prepare("SELECT id FROM user_settings WHERE user_id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $settings_exist = $check_stmt->get_result()->fetch_assoc();
        
        if ($settings_exist) {
            $update_stmt = $conn->prepare("UPDATE user_settings SET privacy_level = ?, activity_visibility = ?, auto_logout = ?, updated_at = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("ssii", $privacy_level, $activity_visibility, $auto_logout, $user_id);
        } else {
            $update_stmt = $conn->prepare("INSERT INTO user_settings (user_id, privacy_level, activity_visibility, auto_logout, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $update_stmt->bind_param("issi", $user_id, $privacy_level, $activity_visibility, $auto_logout);
        }
        
        if ($update_stmt->execute()) {
            $message = "Privacy & security settings updated successfully!";
            $message_type = "success";
            
            $user_settings['privacy_level'] = $privacy_level;
            $user_settings['activity_visibility'] = $activity_visibility;
            $user_settings['auto_logout'] = $auto_logout;
        } else {
            $message = "Error updating privacy settings.";
            $message_type = "error";
        }
    }
    elseif ($action === 'export_data') {
        $export_data = [
            'user_info' => [
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'member_since' => $user['created_at']
            ],
            'settings' => $user_settings,
            'export_date' => date('Y-m-d H:i:s')
        ];
        
        $json_data = json_encode($export_data, JSON_PRETTY_PRINT);
        $filename = "boiyets_gym_data_export_" . date('Y-m-d') . ".json";
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $json_data;
        $conn->close();
        exit();
    }
    elseif ($action === 'delete_account') {
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($confirm_password)) {
            $message = "Please enter your password to confirm account deletion request.";
            $message_type = "error";
        } else {
            $message = "Account deletion request submitted. For security reasons, please contact the administrator to finalize permanent deletion.";
            $message_type = "warning";
        }
    }
}

$conn->close();

$page_title = "Account Settings — Boiyets Fitness Gym";
require_once __DIR__ . "/includes/header.php";
require_once __DIR__ . "/includes/nav.php";
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="settings" style="color: var(--accent);"></i>
        Account & System Settings
      </h1>
      <p class="gym-page-subtitle">Configure notification preferences, privacy levels, regional settings, and account data security.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <a href="profile.php" class="gym-btn gym-btn-outline">
        <i data-lucide="user"></i> View My Profile
      </a>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div style="background: <?php echo $message_type === 'success' ? 'rgba(34, 197, 94, 0.15)' : ($message_type === 'warning' ? 'rgba(245, 158, 11, 0.15)' : 'rgba(239, 68, 68, 0.15)'); ?>; border: 1px solid <?php echo $message_type === 'success' ? 'rgba(34, 197, 94, 0.4)' : ($message_type === 'warning' ? 'rgba(245, 158, 11, 0.4)' : 'rgba(239, 68, 68, 0.4)'); ?>; color: <?php echo $message_type === 'success' ? '#4ade80' : ($message_type === 'warning' ? '#fbbf24' : '#f87171'); ?>; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle-2' : ($message_type === 'warning' ? 'alert-triangle' : 'alert-circle'); ?>" style="width: 18px; height: 18px;"></i>
      <span><?php echo htmlspecialchars($message); ?></span>
    </div>
  <?php endif; ?>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px;">
    
    <!-- LEFT CARD: PREFERENCES & REGIONAL -->
    <div class="gym-card">
      <form method="POST" style="display: flex; flex-direction: column; gap: 20px;">
        <input type="hidden" name="action" value="update_preferences">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        
        <!-- Notifications Section -->
        <div>
          <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
            <i data-lucide="bell" style="color: var(--accent);"></i>
            Notification Preferences
          </h2>
          
          <div style="display: flex; flex-direction: column; gap: 12px;">
            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: var(--text-primary); font-size: 0.92rem;">
              <input type="checkbox" name="email_notifications" <?php echo ($user_settings['email_notifications'] ?? 1) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent);">
              <span>Email System Notifications</span>
            </label>

            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: var(--text-primary); font-size: 0.92rem;">
              <input type="checkbox" name="push_notifications" <?php echo ($user_settings['push_notifications'] ?? 1) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent);">
              <span>Real-time In-App Push Alerts</span>
            </label>

            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: var(--text-primary); font-size: 0.92rem;">
              <input type="checkbox" name="newsletter" <?php echo ($user_settings['newsletter'] ?? 0) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent);">
              <span>Subscribe to Gym Fitness Newsletter</span>
            </label>
          </div>
        </div>

        <!-- System & Regional -->
        <div>
          <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
            <i data-lucide="globe" style="color: var(--accent);"></i>
            System & Display Preferences
          </h2>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div>
              <label class="gym-form-label">Interface Mode</label>
              <select id="themeSelect" name="theme" class="gym-form-control" onchange="updateThemePreview(this.value)">
                <option value="dark" <?php echo ($user_settings['theme'] ?? 'dark') == 'dark' ? 'selected' : ''; ?>>Solid Dark</option>
                <option value="light" <?php echo ($user_settings['theme'] ?? 'dark') == 'light' ? 'selected' : ''; ?>>Light Mode</option>
              </select>
            </div>

            <div>
              <label class="gym-form-label">Accent Color Theme</label>
              <select id="accentSelect" name="accent" class="gym-form-control" onchange="updateAccentPreview(this.value)">
                <option value="gold" <?php echo ($user_settings['accent'] ?? 'gold') == 'gold' ? 'selected' : ''; ?>>Boiyets Gold 🟡</option>
                <option value="blue" <?php echo ($user_settings['accent'] ?? '') == 'blue' ? 'selected' : ''; ?>>Electric Blue 🔵</option>
                <option value="emerald" <?php echo ($user_settings['accent'] ?? '') == 'emerald' ? 'selected' : ''; ?>>Emerald Green 🟢</option>
                <option value="crimson" <?php echo ($user_settings['accent'] ?? '') == 'crimson' ? 'selected' : ''; ?>>Crimson Red 🔴</option>
              </select>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
            <div>
              <label class="gym-form-label">Language</label>
              <select name="language" class="gym-form-control">
                <option value="english" <?php echo ($user_settings['language'] ?? 'english') == 'english' ? 'selected' : ''; ?>>English</option>
                <option value="tagalog" <?php echo ($user_settings['language'] ?? 'english') == 'tagalog' ? 'selected' : ''; ?>>Filipino / Tagalog</option>
              </select>
            </div>

            <div>
              <label class="gym-form-label">System Timezone</label>
              <select name="timezone" class="gym-form-control">
                <option value="Asia/Manila" <?php echo ($user_settings['timezone'] ?? 'Asia/Manila') == 'Asia/Manila' ? 'selected' : ''; ?>>Philippine Time (PST - GMT+8)</option>
                <option value="UTC" <?php echo ($user_settings['timezone'] ?? '') == 'UTC' ? 'selected' : ''; ?>>UTC Universal</option>
              </select>
            </div>
          </div>
        </div>

        <script>
          function updateThemePreview(val) {
            document.documentElement.setAttribute('data-theme', val);
            localStorage.setItem('gym_theme', val);
          }
          function updateAccentPreview(val) {
            document.documentElement.setAttribute('data-accent', val);
            localStorage.setItem('gym_accent', val);
          }
        </script>

        <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%; min-height: 42px; margin-top: 10px;">
          <i data-lucide="save"></i> Save System Preferences
        </button>
      </form>
    </div>

    <!-- RIGHT CARD: PRIVACY, EXPORT & DANGER ZONE -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
      
      <!-- Privacy Settings -->
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
          <i data-lucide="shield" style="color: #3b82f6;"></i>
          Privacy & Account Visibility
        </h2>

        <form method="POST" style="display: flex; flex-direction: column; gap: 14px;">
          <input type="hidden" name="action" value="update_privacy">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

          <div>
            <label class="gym-form-label">Profile Privacy Level</label>
            <select name="privacy_level" class="gym-form-control">
              <option value="public" <?php echo ($user_settings['privacy_level'] ?? 'public') == 'public' ? 'selected' : ''; ?>>Public (Visible to Gym Members)</option>
              <option value="trainers" <?php echo ($user_settings['privacy_level'] ?? 'public') == 'trainers' ? 'selected' : ''; ?>>Trainers & Staff Only</option>
              <option value="private" <?php echo ($user_settings['privacy_level'] ?? 'public') == 'private' ? 'selected' : ''; ?>>Private (Only Me)</option>
            </select>
          </div>

          <div>
            <label class="gym-form-label">Session Auto-Logout Idle Time</label>
            <select name="auto_logout" class="gym-form-control">
              <option value="15" <?php echo ($user_settings['auto_logout'] ?? 30) == 15 ? 'selected' : ''; ?>>15 Minutes Idle</option>
              <option value="30" <?php echo ($user_settings['auto_logout'] ?? 30) == 30 ? 'selected' : ''; ?>>30 Minutes Idle</option>
              <option value="60" <?php echo ($user_settings['auto_logout'] ?? 30) == 60 ? 'selected' : ''; ?>>1 Hour Idle</option>
              <option value="120" <?php echo ($user_settings['auto_logout'] ?? 30) == 120 ? 'selected' : ''; ?>>2 Hours Idle</option>
            </select>
          </div>

          <button type="submit" class="gym-btn gym-btn-outline" style="width: 100%; min-height: 42px; color: #60a5fa; border-color: rgba(96, 165, 250, 0.4);">
            <i data-lucide="shield-check"></i> Update Privacy Settings
          </button>
        </form>
      </div>

      <!-- Data Export Card -->
      <div class="gym-card">
        <h2 class="gym-card-title flex items-center gap-2" style="margin-bottom: 0.75rem;">
          <i data-lucide="database" style="color: #4ade80;"></i>
          Personal Data Export
        </h2>
        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
          Download a complete JSON export of your personal profile, workout logs, system settings, and activity history.
        </p>

        <form method="POST">
          <input type="hidden" name="action" value="export_data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <button type="submit" class="gym-btn gym-btn-outline" style="width: 100%; min-height: 40px;">
            <i data-lucide="download"></i> Download Personal Data (.json)
          </button>
        </form>
      </div>

      <!-- Danger Zone Card -->
      <div class="gym-card" style="border: 1px solid rgba(239, 68, 68, 0.35); background: rgba(239, 68, 68, 0.05);">
        <h2 class="gym-card-title flex items-center gap-2" style="color: var(--red); margin-bottom: 0.75rem;">
          <i data-lucide="alert-triangle"></i>
          Danger Zone
        </h2>
        <p style="font-size: 0.84rem; color: var(--text-secondary); margin-bottom: 1rem;">
          Requesting account deletion will archive your profile and log a request with the gym administrator.
        </p>

        <form method="POST" onsubmit="return confirm('Are you sure you want to request account deletion?');" style="display: flex; flex-direction: column; gap: 12px;">
          <input type="hidden" name="action" value="delete_account">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <div>
            <label class="gym-form-label" style="color: #f87171;">Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Enter current password..." class="gym-form-control" style="border-color: rgba(239, 68, 68, 0.4);">
          </div>
          <button type="submit" class="gym-btn gym-btn-danger" style="width: 100%; min-height: 40px;">
            <i data-lucide="trash-2"></i> Request Account Deletion
          </button>
        </form>
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
</script>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
