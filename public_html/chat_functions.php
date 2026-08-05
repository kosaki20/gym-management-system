<?php
require_once __DIR__ . '/../config/config.php';

function getChatUsers($current_user_id, $current_user_role, $conn) {
    $users = [];
    
    if ($current_user_role === 'admin') {
        // Admin can chat with all trainers and clients except self
        $sql = "SELECT id, username, full_name, role, 
                       (SELECT COUNT(*) FROM chat_messages 
                        WHERE sender_id = users.id 
                        AND receiver_id = ? 
                        AND is_read = FALSE) as unread_count
                FROM users 
                WHERE role IN ('trainer', 'client') 
                AND id != ?
                ORDER BY role ASC, full_name ASC, username ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $current_user_id, $current_user_id);
    } elseif ($current_user_role === 'trainer') {
        // Trainer can chat with admins, assigned clients, or all active clients, excluding self
        $sql = "SELECT u.id, u.username, u.full_name, u.role,
                       (SELECT COUNT(*) FROM chat_messages 
                        WHERE sender_id = u.id 
                        AND receiver_id = ? 
                        AND is_read = FALSE) as unread_count
                FROM users u
                WHERE u.role IN ('admin', 'client') 
                AND u.id != ?
                ORDER BY u.role ASC, u.full_name ASC, u.username ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $current_user_id, $current_user_id);
    } else {
        // Client can chat with admins and trainers, excluding self
        $sql = "SELECT u.id, u.username, u.full_name, u.role,
                       (SELECT COUNT(*) FROM chat_messages 
                        WHERE sender_id = u.id 
                        AND receiver_id = ? 
                        AND is_read = FALSE) as unread_count
                FROM users u
                WHERE u.role IN ('admin', 'trainer') 
                AND u.id != ?
                ORDER BY u.role ASC, u.full_name ASC, u.username ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $current_user_id, $current_user_id);
    }
    
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
    }
    
    return $users;
}

function getChatMessages($user1_id, $user2_id, $conn) {
    $sql = "SELECT cm.*, u.full_name as sender_name, u.role as sender_role
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE (cm.sender_id = ? AND cm.receiver_id = ?) 
               OR (cm.sender_id = ? AND cm.receiver_id = ?)
            ORDER BY cm.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $messages = [];
    if ($stmt) {
        $stmt->bind_param("iiii", $user1_id, $user2_id, $user2_id, $user1_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
    }
    
    // Mark messages as read
    markMessagesAsRead($user1_id, $user2_id, $conn);
    
    return $messages;
}

function sendMessage($sender_id, $sender_role, $receiver_id, $receiver_role, $message, $conn) {
    if (strlen($message) > 2000) {
        return false;
    }
    
    $sql = "INSERT INTO chat_messages (sender_id, sender_role, receiver_id, receiver_role, message, is_read, created_at) 
            VALUES (?, ?, ?, ?, ?, 0, NOW())";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("isiss", $sender_id, $sender_role, $receiver_id, $receiver_role, $message);
        $result = $stmt->execute();
        $message_id = $conn->insert_id;
        $stmt->close();
        return $result ? $message_id : false;
    }
    return false;
}

function sendMessageWithAttachment($sender_id, $sender_role, $receiver_id, $receiver_role, $message, $attachment_path, $attachment_type, $conn) {
    if (strlen($message) > 2000) {
        return false;
    }
    
    $sql = "INSERT INTO chat_messages (sender_id, sender_role, receiver_id, receiver_role, message, attachment_path, attachment_type, is_read, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("isissss", $sender_id, $sender_role, $receiver_id, $receiver_role, $message, $attachment_path, $attachment_type);
        $result = $stmt->execute();
        $message_id = $conn->insert_id;
        $stmt->close();
        return $result ? $message_id : false;
    }
    return false;
}

function markMessagesAsRead($receiver_id, $sender_id, $conn) {
    $sql = "UPDATE chat_messages 
            SET is_read = TRUE 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $sender_id, $receiver_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

function getUnreadCount($user_id, $conn) {
    $sql = "SELECT COUNT(*) as unread_count 
            FROM chat_messages 
            WHERE receiver_id = ? AND is_read = FALSE";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['unread_count'] ?? 0;
    }
    return 0;
}

function getNewMessages($user_id, $last_message_id, $conn) {
    $sql = "SELECT cm.*, u.full_name as sender_name, u.role as sender_role
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.receiver_id = ? AND cm.id > ?
            ORDER BY cm.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $messages = [];
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $last_message_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
    }
    return $messages;
}

function getUserDisplayName($user) {
    return !empty($user['full_name']) ? $user['full_name'] : $user['username'];
}
?>
