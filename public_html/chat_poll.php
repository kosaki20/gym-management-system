<?php
require_once __DIR__ . '/../config/config.php';
session_start();
require_once 'chat_functions.php';

// Database connection


// Check connection
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['messages' => [], 'error' => 'Unauthorized']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

header('Content-Type: application/json');

if ($user_id) {
    $new_messages = getNewMessages($user_id, $last_id, $conn);
    echo json_encode(['messages' => $new_messages]);
} else {
    echo json_encode(['messages' => []]);
}

$conn->close();
?>
