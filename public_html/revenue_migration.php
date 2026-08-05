<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Database connection
require_once __DIR__ . '/../config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Revenue System Migration</title>
    
    <link rel='stylesheet' href='assets/css/gym_layout.css'>
    <link rel='stylesheet' href='assets/css/modern.css'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
</head>
<body>
    <div class='container'>
        <h1>Revenue System Database Migration</h1>";

// Create revenue_categories table
$sql_categories = "CREATE TABLE IF NOT EXISTS revenue_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_categories)) {
    echo "<div class='success'>✓ Table 'revenue_categories' created or already exists.</div>";
} else {
    echo "<div class='error'>✗ Error creating 'revenue_categories': " . $conn->error . "</div>";
}

echo "</div></body></html>";
