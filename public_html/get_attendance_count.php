<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'trainer') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection

require_once __DIR__ . '/../config/config.php';

// Check connection

header('Content-Type: application/json');

// Get today's attendance count
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(DISTINCT member_id) as total FROM attendance WHERE DATE(check_in) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

echo json_encode(['success' => true, 'count' => (int)$count]);

$conn->close();
?>
