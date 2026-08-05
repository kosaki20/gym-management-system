<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/chat_functions.php';

// Auth check — only return JSON for genuine AJAX action requests, not page loads
if (!isset($_SESSION['user_id'])) {
    $is_action_request = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
                         && (isset($_GET['action']) || $_SERVER['REQUEST_METHOD'] === 'POST');
    if ($is_action_request) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header("Location: index.php");
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$current_user_role = $_SESSION['role'];
$current_user_name = $_SESSION['full_name'] ?? $_SESSION['username'];

// Create uploads directory if not exists
$upload_dir = "chat_uploads/";
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

// Get available chat contacts
$chat_users = getChatUsers($current_user_id, $current_user_role, $conn);

// Selected contact
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($chat_users[0]['id'] ?? null);
$selected_user = null;

if ($selected_user_id) {
    foreach ($chat_users as $u) {
        if ($u['id'] == $selected_user_id) {
            $selected_user = $u;
            break;
        }
    }
}

// --- AJAX ENDPOINTS ---
// 1. Fetch Messages via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'fetch_messages' && $selected_user_id) {
    header('Content-Type: application/json');
    $messages = getChatMessages($current_user_id, $selected_user_id, $conn);
    echo json_encode([
        'success' => true,
        'current_user_id' => $current_user_id,
        'messages' => $messages
    ]);
    exit();
}

// 2. Send Message via AJAX or POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : $selected_user_id;
    $attachment_path = null;
    $attachment_type = null;
    
    // Find receiver details
    $receiver_role = 'client';
    if ($receiver_id) {
        $stmt_r = $conn->prepare("SELECT role FROM users WHERE id = ?");
        if ($stmt_r) {
            $stmt_r->bind_param("i", $receiver_id);
            $stmt_r->execute();
            $res_r = $stmt_r->get_result();
            if ($row_r = $res_r->fetch_assoc()) {
                $receiver_role = $row_r['role'];
            }
            $stmt_r->close();
        }
    }

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $max_size = 5 * 1024 * 1024;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file['type'], $allowed_types) && in_array($ext, $allowed_extensions) && $file['size'] <= $max_size) {
            $file_name = uniqid('chat_', true) . '.' . $ext;
            $target_path = $upload_dir . $file_name;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $attachment_path = $target_path;
                $attachment_type = (strpos($file['type'], 'image/') === 0) ? 'image' : 'file';
            }
        }
    }
    
    $inserted_id = false;
    if ((!empty($message) || $attachment_path) && $receiver_id) {
        if ($attachment_path) {
            $inserted_id = sendMessageWithAttachment($current_user_id, $current_user_role, $receiver_id, $receiver_role, $message, $attachment_path, $attachment_type, $conn);
        } else {
            $inserted_id = sendMessage($current_user_id, $current_user_role, $receiver_id, $receiver_role, $message, $conn);
        }
    }
    
    // Check if AJAX submission
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_POST['is_ajax']);
    if ($is_ajax) {
        header('Content-Type: application/json');
        if ($inserted_id) {
            echo json_encode(['success' => true, 'message_id' => $inserted_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save message.']);
        }
        exit();
    }
    
    header("Location: chat.php?user_id=" . $receiver_id);
    exit();
}

// Fetch initial messages for page render
$messages = [];
if ($selected_user_id) {
    $messages = getChatMessages($current_user_id, $selected_user_id, $conn);
}

$unread_count = getUnreadCount($current_user_id, $conn);

$page_title = "Messages & Live Chat — Boiyets Fitness Gym";
require_once __DIR__ . "/includes/header.php";
require_once __DIR__ . "/includes/nav.php";
?>

<style>
  .gym-chat-shell {
    display: grid;
    grid-template-columns: 340px 1fr;
    height: calc(100vh - 160px);
    min-height: 580px;
    max-height: 800px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
  }

  .chat-sidebar-pane {
    display: flex;
    flex-direction: column;
    background: var(--bg-sidebar);
    border-right: 1px solid var(--border);
    height: 100%;
    overflow: hidden;
  }

  .chat-search-wrap {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    background: rgba(15, 23, 42, 0.4);
  }

  .chat-contacts-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .chat-contact-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: var(--radius-md);
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    background: transparent;
  }

  .chat-contact-card:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
  }

  .chat-contact-card.active {
    background: rgba(232, 160, 18, 0.14) !important;
    border-color: rgba(232, 160, 18, 0.35) !important;
  }

  .chat-avatar-badge {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e293b, #0f172a);
    border: 2px solid var(--accent);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 1.05rem;
    flex-shrink: 0;
    position: relative;
  }

  .online-dot-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 11px;
    height: 11px;
    background: #22c55e;
    border: 2px solid #0d1220;
    border-radius: 50%;
  }

  .chat-viewport-pane {
    display: flex;
    flex-direction: column;
    background: var(--bg-card);
    position: relative;
    height: 100%;
    overflow: hidden;
  }

  .chat-viewport-header {
    padding: 16px 24px;
    background: var(--bg-sidebar);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .chat-messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: var(--bg-body);
  }

  .msg-row-item {
    display: flex;
    flex-direction: column;
    max-width: 68%;
  }

  .msg-row-item.sent {
    align-self: flex-end;
    align-items: flex-end;
  }

  .msg-row-item.received {
    align-self: flex-start;
    align-items: flex-start;
  }

  .msg-bubble-box {
    padding: 12px 18px;
    border-radius: 16px;
    font-size: 0.94rem;
    line-height: 1.5;
    word-break: break-word;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
  }

  .msg-row-item.sent .msg-bubble-box {
    background: linear-gradient(135deg, #e8a012, #c78a0e);
    color: #0b0f19;
    font-weight: 600;
    border-bottom-right-radius: 4px;
  }

  .msg-row-item.received .msg-bubble-box {
    background: var(--bg-surface);
    color: var(--text-primary);
    border: 1px solid var(--border);
    border-bottom-left-radius: 4px;
  }

  .msg-time-tag {
    font-size: 0.72rem;
    color: var(--text-dim);
    margin-top: 4px;
    padding: 0 4px;
  }

  .chat-input-bar-wrap {
    padding: 16px 24px;
    background: var(--bg-sidebar);
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .chat-action-btn {
    width: 42px;
    height: 42px;
    border-radius: var(--radius-sm);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .chat-action-btn:hover {
    background: rgba(232, 160, 18, 0.15);
    color: var(--accent);
    border-color: var(--accent);
  }

  .chat-input-text {
    flex: 1;
    height: 42px;
    padding: 10px 16px;
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    font-family: inherit;
    font-size: 0.92rem;
    resize: none;
    outline: none;
    transition: border-color 0.2s ease;
  }

  .chat-input-text:focus {
    border-color: var(--accent);
  }

  .chat-send-btn {
    width: 48px;
    height: 42px;
    border-radius: var(--radius-sm);
    background: var(--accent);
    color: #0b0f19;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 800;
  }

  .chat-send-btn:hover {
    background: var(--accent-light);
    transform: scale(1.04);
  }

  .mobile-back-btn {
    display: none;
  }

  @media (max-width: 768px) {
    .gym-chat-shell {
      grid-template-columns: 1fr;
      height: calc(100vh - 120px);
    }
    .chat-sidebar-pane {
      display: <?php echo $selected_user_id ? 'none' : 'flex'; ?>;
    }
    .chat-viewport-pane {
      display: <?php echo $selected_user_id ? 'flex' : 'none'; ?>;
    }
    .mobile-back-btn {
      display: inline-flex;
    }
  }
</style>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="message-square" style="color: var(--accent);"></i>
        Direct Messages & Live Support
      </h1>
      <p class="gym-page-subtitle">Communicate with administrators, personal trainers, and active gym members in real time.</p>
    </div>
    <div>
      <span class="gym-badge gym-badge-active" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; font-size: 0.82rem;">
        <span style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block;"></span>
        Real-time Live Chat Active
      </span>
    </div>
  </div>

  <!-- Chat Glass Shell -->
  <div class="gym-chat-shell">
    
    <!-- LEFT SIDEBAR: CONTACTS LIST -->
    <div class="chat-sidebar-pane">
      <div class="chat-search-wrap">
        <div style="position: relative;">
          <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-dim);"></i>
          <input type="text" id="contactSearch" class="gym-form-control" placeholder="Search contacts..." onkeyup="filterContacts()" style="padding-left: 38px; height: 40px; margin: 0;">
        </div>
      </div>

      <div class="chat-contacts-scroll" id="contactsList">
        <?php if (empty($chat_users)): ?>
          <div style="text-align: center; padding: 3rem 1rem; color: var(--text-dim);">
            <i data-lucide="user-x" style="width: 38px; height: 38px; margin: 0 auto 0.75rem; color: #334155; display: block;"></i>
            No active contacts found
          </div>
        <?php else: ?>
          <?php foreach ($chat_users as $user): ?>
            <?php
            $is_selected = ($selected_user_id == $user['id']);
            $role_str = strtolower($user['role']);
            $role_style = 'background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3);';
            if ($role_str === 'admin') {
                $role_style = 'background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3);';
            } elseif ($role_str === 'trainer') {
                $role_style = 'background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3);';
            }
            ?>
            <a href="chat.php?user_id=<?php echo $user['id']; ?>" class="chat-contact-card <?php echo $is_selected ? 'active' : ''; ?>">
              <div class="chat-avatar-badge">
                <?php echo strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1)); ?>
                <span class="online-dot-indicator"></span>
              </div>
              <div style="flex: 1; min-width: 0;">
                <div style="font-weight: 700; font-size: 0.94rem; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                  <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                </div>
                <span class="gym-badge" style="font-size: 0.65rem; padding: 1px 6px; text-transform: uppercase; margin-top: 3px; <?php echo $role_style; ?>">
                  <?php echo strtoupper($user['role']); ?>
                </span>
              </div>
              <?php if (!empty($user['unread_count']) && $user['unread_count'] > 0): ?>
                <span style="background: var(--accent); color: #0b0f19; font-weight: 800; font-size: 0.72rem; padding: 2px 8px; border-radius: 12px;">
                  <?php echo $user['unread_count']; ?>
                </span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT MAIN WINDOW: MESSAGES -->
    <div class="chat-viewport-pane">
      <?php if ($selected_user): ?>
        <!-- Top Chat Header -->
        <div class="chat-viewport-header">
          <div style="display: flex; align-items: center; gap: 14px;">
            <a href="chat.php" class="gym-btn gym-btn-outline mobile-back-btn" style="min-height: 34px !important; padding: 4px 10px !important; font-size: 0.8rem;">
              <i data-lucide="chevron-left"></i> Contacts
            </a>

            <div class="chat-avatar-badge">
              <?php echo strtoupper(substr($selected_user['full_name'] ?: $selected_user['username'], 0, 1)); ?>
              <span class="online-dot-indicator"></span>
            </div>
            <div>
              <h2 style="margin: 0; font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.15rem; color: var(--text-primary);">
                <?php echo htmlspecialchars($selected_user['full_name'] ?: $selected_user['username']); ?>
              </h2>
              <span class="gym-badge" style="font-size: 0.65rem; padding: 1px 6px; text-transform: uppercase;">
                <?php echo strtoupper($selected_user['role']); ?>
              </span>
            </div>
          </div>
        </div>

        <!-- Message List Viewport -->
        <div class="chat-messages-area" id="messagesArea">
          <?php if (empty($messages)): ?>
            <div style="text-align: center; margin: auto; color: var(--text-dim);" id="noMessagesPlaceholder">
              <i data-lucide="message-square" style="width: 48px; height: 48px; margin: 0 auto 1rem; color: #334155; display: block;"></i>
              <p style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--text-secondary); margin: 0;">No message history</p>
              <p style="font-size: 0.88rem; margin-top: 0.35rem; color: var(--text-dim);">Send a message to start conversing with <?php echo htmlspecialchars($selected_user['full_name'] ?: $selected_user['username']); ?>.</p>
            </div>
          <?php else: ?>
            <?php foreach ($messages as $msg): ?>
              <?php $is_sent = ($msg['sender_id'] == $current_user_id); ?>
              <div class="msg-row-item <?php echo $is_sent ? 'sent' : 'received'; ?>">
                <div class="msg-bubble-box">
                  <?php if (!empty($msg['message'])): ?>
                    <div><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                  <?php endif; ?>

                  <?php if (!empty($msg['attachment_path'])): ?>
                    <div style="margin-top: 0.5rem;">
                      <?php if ($msg['attachment_type'] == 'image'): ?>
                        <img src="<?php echo htmlspecialchars($msg['attachment_path']); ?>" 
                             alt="Attachment" 
                             style="max-width: 280px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); cursor: pointer;"
                             onclick="window.open('<?php echo htmlspecialchars($msg['attachment_path']); ?>', '_blank')">
                      <?php else: ?>
                        <a href="<?php echo htmlspecialchars($msg['attachment_path']); ?>" download style="color: inherit; font-weight: 700; text-decoration: underline; display: inline-flex; align-items: center; gap: 6px;">
                          <i data-lucide="paperclip" style="width: 14px; height: 14px;"></i> <?php echo basename($msg['attachment_path']); ?>
                        </a>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="msg-time-tag">
                  <?php echo date('g:i A', strtotime($msg['created_at'])); ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Attachment Preview Pill -->
        <div id="attachmentPreview" style="display: none; padding: 8px 24px; background: var(--bg-sidebar); border-top: 1px solid var(--border);">
          <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(232, 160, 18, 0.12); padding: 8px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(232, 160, 18, 0.3); color: var(--accent);">
            <span id="attachmentFileName" style="font-weight: 700; display: flex; align-items: center; gap: 8px; font-size: 0.85rem;">
              <i data-lucide="file" style="width: 16px; height: 16px;"></i> <span id="attachmentNameText"></span>
            </span>
            <button type="button" onclick="clearAttachment()" style="background: none; border: none; color: #ef4444; cursor: pointer;">
              <i data-lucide="x" style="width: 16px; height: 16px;"></i>
            </button>
          </div>
        </div>

        <!-- Chat Input Form -->
        <form method="POST" id="chatForm" class="chat-input-bar-wrap" enctype="multipart/form-data" onsubmit="submitChatForm(event)">
          <input type="hidden" name="receiver_id" value="<?php echo $selected_user_id; ?>">
          <input type="hidden" name="is_ajax" value="1">
          
          <label for="attachmentInput" class="chat-action-btn" title="Attach photo or document">
            <i data-lucide="paperclip" style="width: 20px; height: 20px;"></i>
          </label>
          <input type="file" id="attachmentInput" name="attachment" style="display: none;" onchange="handleFileSelected(this)">

          <textarea name="message" id="messageTextarea" class="chat-input-text" placeholder="Type a message... (Press Enter to send)" onkeydown="handleChatKeyDown(event)"></textarea>

          <button type="submit" class="chat-send-btn" title="Send message">
            <i data-lucide="send" style="width: 20px; height: 20px;"></i>
          </button>
        </form>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-dim); text-align: center; padding: 2rem;">
          <i data-lucide="message-square" style="width: 64px; height: 64px; color: #334155; margin-bottom: 1.25rem;"></i>
          <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-secondary); margin: 0;">Select a Contact to Start Chatting</h2>
          <p style="font-size: 0.9rem; max-width: 360px; margin-top: 0.5rem; color: var(--text-dim);">Choose an administrator, trainer, or gym member from the contacts list on the left.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  const currentUserId = <?php echo (int)$current_user_id; ?>;
  const selectedUserId = <?php echo $selected_user_id ? (int)$selected_user_id : 'null'; ?>;

  document.addEventListener('DOMContentLoaded', function() {
      if (typeof lucide !== 'undefined') {
          lucide.createIcons();
      }
      scrollToBottom();

      if (selectedUserId) {
          // Clear any previous poll timer (prevents duplicate intervals on SPA re-entry)
          if (window.chatPollTimer) {
              clearInterval(window.chatPollTimer);
              window.chatPollTimer = null;
          }
          window.chatPollTimer = setInterval(pollNewMessages, 3000);
      }
  });

  function scrollToBottom() {
      const container = document.getElementById('messagesArea');
      if (container) {
          container.scrollTop = container.scrollHeight;
      }
  }

  function filterContacts() {
      const input = document.getElementById('contactSearch').value.toLowerCase().trim();
      const cards = document.querySelectorAll('.chat-contact-card');
      cards.forEach(card => {
          const text = card.textContent.toLowerCase();
          card.style.display = text.includes(input) ? 'flex' : 'none';
      });
  }

  function handleFileSelected(input) {
      const preview = document.getElementById('attachmentPreview');
      const text = document.getElementById('attachmentNameText');
      if (input.files && input.files[0]) {
          text.textContent = input.files[0].name;
          preview.style.display = 'block';
          if (typeof lucide !== 'undefined') lucide.createIcons();
      } else {
          preview.style.display = 'none';
      }
  }

  function clearAttachment() {
      const input = document.getElementById('attachmentInput');
      const preview = document.getElementById('attachmentPreview');
      if (input) input.value = '';
      if (preview) preview.style.display = 'none';
  }

  function handleChatKeyDown(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          submitChatForm(e);
      }
  }

  function submitChatForm(e) {
      if (e) e.preventDefault();
      const form = document.getElementById('chatForm');
      const textarea = document.getElementById('messageTextarea');
      const text = textarea.value.trim();
      const fileInput = document.getElementById('attachmentInput');

      if (text.length === 0 && (!fileInput.files || fileInput.files.length === 0)) {
          return;
      }

      const formData = new FormData(form);

      fetch('chat.php', {
          method: 'POST',
          headers: {
              'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
      })
      .then(res => {
          if (!res.ok) throw new Error('Server error ' + res.status);
          return res.json();
      })
      .then(data => {
          if (data.success) {
              textarea.value = '';
              clearAttachment();
              pollNewMessages();
          } else {
              alert('Error sending message: ' + (data.message || 'Unknown error'));
          }
      })
      .catch(err => {
          console.error('Send message error:', err);
          alert('Could not send message. Please check your connection and try again.');
      });
  }

  function pollNewMessages() {
      if (!selectedUserId) return;

      fetch(`chat.php?action=fetch_messages&user_id=${selectedUserId}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(res => res.json())
      .then(data => {
          if (data.success && data.messages) {
              renderMessagesList(data.messages);
          }
      })
      .catch(err => console.error('Poll error:', err));
  }

  function renderMessagesList(messages) {
      const area = document.getElementById('messagesArea');
      if (!area) return;

      if (messages.length === 0) return;

      let html = '';
      messages.forEach(msg => {
          const isSent = (parseInt(msg.sender_id) === currentUserId);
          const timeStr = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

          html += `
            <div class="msg-row-item ${isSent ? 'sent' : 'received'}">
              <div class="msg-bubble-box">
                ${msg.message ? `<div>${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>` : ''}
                ${msg.attachment_path ? `
                  <div style="margin-top: 0.5rem;">
                    ${msg.attachment_type === 'image' ? `
                      <img src="${escapeHtml(msg.attachment_path)}" alt="Attachment" style="max-width: 280px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); cursor: pointer;" onclick="window.open('${escapeHtml(msg.attachment_path)}', '_blank')">
                    ` : `
                      <a href="${escapeHtml(msg.attachment_path)}" download style="color: inherit; font-weight: 700; text-decoration: underline; display: inline-flex; align-items: center; gap: 6px;">
                        <i data-lucide="paperclip" style="width: 14px; height: 14px;"></i> ${escapeHtml(msg.attachment_path.split('/').pop())}
                      </a>
                    `}
                  </div>
                ` : ''}
              </div>
              <div class="msg-time-tag">${timeStr}</div>
            </div>
          `;
      });

      const isScrolledToBottom = (area.scrollHeight - area.clientHeight - area.scrollTop) < 100;
      area.innerHTML = html;
      if (typeof lucide !== 'undefined') lucide.createIcons();

      if (isScrolledToBottom) {
          scrollToBottom();
      }
  }

  function escapeHtml(str) {
      if (!str) return '';
      return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }
</script>

<?php 
if (isset($conn) && $conn) {
    $conn->close();
}
require_once __DIR__ . "/includes/footer.php"; 
?>
