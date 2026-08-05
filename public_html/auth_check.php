<?php
require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkAuth($required_role = null) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
    
    if ($required_role && $_SESSION['role'] !== $required_role) {
        header("Location: unauthorized.php");
        exit();
    }
    
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Guard a page to a single required role.
 * Redirects to index.php if not logged in, or to the referring page with a 403
 * if the user has the wrong role.
 */
function requireRole(string $role): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
    if ($_SESSION['role'] !== $role) {
        header("Location: index.php");
        exit();
    }
}

/**
 * Guard a page to any of the allowed roles.
 */
function requireAnyRole(array $roles): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
    if (!in_array($_SESSION['role'], $roles, true)) {
        header("Location: index.php");
        exit();
    }
}
?>
