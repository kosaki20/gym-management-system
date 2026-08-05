<?php
require_once __DIR__ . '/../config/config.php';
// notification_functions.php

/**
 * Get unread notification count for a user
 */
function getUnreadNotificationCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_status = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

/**
 * Get trainer notifications
 */
function getTrainerNotifications($conn, $user_id) {
    $sql = "SELECT * FROM notifications WHERE user_id = ? OR role = 'trainer' ORDER BY created_at DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    return $notifications;
}

/**
 * Get admin notifications
 */
function getAdminNotifications($conn) {
    $sql = "SELECT * FROM notifications WHERE role = 'admin' OR role IS NULL ORDER BY created_at DESC LIMIT 10";
    $result = $conn->query($sql);
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    return $notifications;
}

/**
 * Mark all notifications as read for a user
 */
function markAllNotificationsAsRead($conn, $user_id) {
    $sql = "UPDATE notifications SET read_status = 1 WHERE user_id = ? AND read_status = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

/**
 * Create a new notification
 */
function createNotification($conn, $user_id, $role, $title, $message, $type = 'system', $priority = 'medium') {
    $sql = "INSERT INTO notifications (user_id, role, title, message, type, priority, read_status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssss", $user_id, $role, $title, $message, $type, $priority);
    return $stmt->execute();
}

/**
 * Check and trigger automated membership expiration notifications
 */
function checkAndTriggerExpiryAlerts($conn) {
    // Find active members expiring in <= 7 days
    $sql = "SELECT m.id, m.user_id, m.full_name, m.expiry_date, DATEDIFF(m.expiry_date, CURDATE()) as days_left 
            FROM members m 
            WHERE m.status = 'active' 
            AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    $res = $conn->query($sql);
    if ($res) {
        while ($m = $res->fetch_assoc()) {
            $days = (int)$m['days_left'];
            $user_id = (int)$m['user_id'];
            
            // Check if notification already sent today for this user
            $chk = $conn->query("SELECT id FROM notifications WHERE user_id = $user_id AND type = 'membership' AND DATE(created_at) = CURDATE()");
            if ($chk && $chk->num_rows == 0) {
                $title = "Membership Expiring Soon!";
                $msg = "Your gym membership expires in {$days} day(s) on {$m['expiry_date']}. Please renew to keep full access!";
                createNotification($conn, $user_id, 'client', $title, $msg, 'membership', $days <= 3 ? 'high' : 'medium');
            }
        }
    }
}
?>
