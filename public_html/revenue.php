<?php
require_once __DIR__ . '/../config/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();

// Handle AJAX requests FIRST, before any HTML output
if (isset($_GET['ajax']) && $_GET['ajax'] == 'chart_data') {
    // Set JSON header immediately
    header('Content-Type: application/json');
    ob_clean(); // Clear any output buffers


    // Check if user is authenticated
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        $conn->close();
        exit();
    }

    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 month'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $tab = $_GET['tab'] ?? 'revenue';
    
    try {
        if ($tab == 'revenue') {
            // Get daily revenue data - IMPROVED VERSION
            $chart_sql = "SELECT 
                            dates.date as date,
                            COALESCE(SUM(re.amount), 0) as daily_revenue,
                            COALESCE(SUM(mp.amount), 0) as membership_revenue,
                            (COALESCE(SUM(re.amount), 0) + COALESCE(SUM(mp.amount), 0)) as total_revenue
                          FROM (
                            SELECT DATE(revenue_date) as date FROM revenue_entries 
                            WHERE revenue_date BETWEEN ? AND ?
                            UNION 
                            SELECT DATE(payment_date) as date FROM membership_payments 
                            WHERE payment_date BETWEEN ? AND ?
                            AND status = 'completed'
                            UNION
                            SELECT DATE(? + INTERVAL seq.seq DAY) as date
                            FROM (
                                SELECT a.N + b.N * 10 + c.N * 100 as seq
                                FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
                                CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                                CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
                            ) seq
                            WHERE DATE(? + INTERVAL seq.seq DAY) BETWEEN ? AND ?
                          ) dates
                          LEFT JOIN revenue_entries re ON dates.date = DATE(re.revenue_date)
                          LEFT JOIN membership_payments mp ON dates.date = DATE(mp.payment_date) AND mp.status = 'completed'
                          GROUP BY dates.date
                          ORDER BY dates.date ASC";
            
            $chart_stmt = $conn->prepare($chart_sql);
            if (!$chart_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $chart_stmt->bind_param("ssssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $start_date, $start_date, $end_date);
            if (!$chart_stmt->execute()) {
                throw new Exception("Execute failed: " . $chart_stmt->error);
            }
            $chart_result = $chart_stmt->get_result();
            
            $revenue_chart_data = [];
            while($row = $chart_result->fetch_assoc()) {
                $total_revenue = floatval($row['daily_revenue']) + floatval($row['membership_revenue']);
                $revenue_chart_data[$row['date']] = $total_revenue;
            }
            $chart_stmt->close();
            
            // Get category data for chart - IMPROVED VERSION
            $category_sql = "SELECT 
                            'Membership Fees' as category_name,
                            'var(--blue)' as category_color,
                            COALESCE(SUM(mp.amount), 0) as total_amount,
                            COUNT(mp.id) as transaction_count,
                            COALESCE(AVG(mp.amount), 0) as average_amount
                          FROM membership_payments mp
                          WHERE mp.payment_date BETWEEN ? AND ?
                          AND mp.status = 'completed'
                          
                          UNION ALL
                          
                          SELECT 
                            rc.name as category_name,
                            rc.color as category_color,
                            COALESCE(SUM(re.amount), 0) as total_amount,
                            COUNT(re.id) as transaction_count,
                            COALESCE(AVG(re.amount), 0) as average_amount
                          FROM revenue_categories rc
                          LEFT JOIN revenue_entries re ON rc.id = re.category_id 
                            AND re.revenue_date BETWEEN ? AND ?
                          WHERE rc.id IN (1, 4)
                          GROUP BY rc.id, rc.name, rc.color
                          
                          HAVING total_amount > 0
                          ORDER BY total_amount DESC";
            
            $category_stmt = $conn->prepare($category_sql);
            if (!$category_stmt) {
                throw new Exception("Prepare failed: " . $category_stmt->error);
            }
            
            $category_stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
            if (!$category_stmt->execute()) {
                throw new Exception("Execute failed: " . $category_stmt->error);
            }
            $category_result = $category_stmt->get_result();
            
            $category_labels = [];
            $category_data = [];
            $category_colors = [];
            $category_details = [];
            
            while($row = $category_result->fetch_assoc()) {
                $category_labels[] = $row['category_name'];
                $category_data[] = floatval($row['total_amount']);
                $category_colors[] = $row['category_color'];
                $category_details[] = [
                    'transactions' => $row['transaction_count'],
                    'average' => floatval($row['average_amount'])
                ];
            }
            $category_stmt->close();
            
            // Generate chart labels with all dates in range
            $chart_labels = [];
            $chart_revenue = [];
            
            $current_date = $start_date;
            while (strtotime($current_date) <= strtotime($end_date)) {
                $date_key = date('Y-m-d', strtotime($current_date));
                $label = date('M j', strtotime($current_date));
                
                $chart_labels[] = $label;
                $chart_revenue[] = $revenue_chart_data[$date_key] ?? 0;
                
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
            
            echo json_encode([
                'success' => true,
                'line_chart' => [
                    'labels' => $chart_labels,
                    'data' => $chart_revenue
                ],
                'pie_chart' => [
                    'labels' => $category_labels,
                    'data' => $category_data,
                    'colors' => $category_colors,
                    'details' => $category_details
                ]
            ]);
            
        } elseif ($tab == 'expenses') {
            // Get daily expenses for chart - IMPROVED VERSION
            $chart_sql = "SELECT 
                            dates.date as date,
                            COALESCE(SUM(e.amount), 0) as daily_expenses
                          FROM (
                            SELECT DATE(expense_date) as date FROM expenses 
                            WHERE expense_date BETWEEN ? AND ?
                            UNION
                            SELECT DATE(? + INTERVAL seq.seq DAY) as date
                            FROM (
                                SELECT a.N + b.N * 10 + c.N * 100 as seq
                                FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
                                CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                                CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
                            ) seq
                            WHERE DATE(? + INTERVAL seq.seq DAY) BETWEEN ? AND ?
                          ) dates
                          LEFT JOIN expenses e ON dates.date = DATE(e.expense_date)
                          GROUP BY dates.date 
                          ORDER BY dates.date ASC";
            
            $chart_stmt = $conn->prepare($chart_sql);
            if (!$chart_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $chart_stmt->bind_param("ssssss", $start_date, $end_date, $start_date, $start_date, $start_date, $end_date);
            if (!$chart_stmt->execute()) {
                throw new Exception("Execute failed: " . $chart_stmt->error);
            }
            $chart_result = $chart_stmt->get_result();
            
            $expense_chart_data = [];
            while($row = $chart_result->fetch_assoc()) {
                $expense_chart_data[$row['date']] = floatval($row['daily_expenses']);
            }
            $chart_stmt->close();
            
            // Get expense category data for chart
            $category_sql = "SELECT 
                            ec.name as category_name,
                            ec.color as category_color,
                            COALESCE(SUM(e.amount), 0) as total_amount,
                            COUNT(e.id) as transaction_count,
                            COALESCE(AVG(e.amount), 0) as average_amount
                          FROM expense_categories ec
                          LEFT JOIN expenses e ON ec.id = e.category_id AND e.expense_date BETWEEN ? AND ?
                          GROUP BY ec.id, ec.name, ec.color
                          HAVING total_amount > 0
                          ORDER BY total_amount DESC";
            
            $category_stmt = $conn->prepare($category_sql);
            if (!$category_stmt) {
                throw new Exception("Prepare failed: " . $category_stmt->error);
            }
            
            $category_stmt->bind_param("ss", $start_date, $end_date);
            if (!$category_stmt->execute()) {
                throw new Exception("Execute failed: " . $category_stmt->error);
            }
            $category_result = $category_stmt->get_result();
            
            $category_labels = [];
            $category_data = [];
            $category_colors = [];
            $category_details = [];
            
            while($row = $category_result->fetch_assoc()) {
                $category_labels[] = $row['category_name'];
                $category_data[] = floatval($row['total_amount']);
                $category_colors[] = $row['category_color'];
                $category_details[] = [
                    'transactions' => $row['transaction_count'],
                    'average' => floatval($row['average_amount'])
                ];
            }
            $category_stmt->close();
            
            // Generate chart labels with all dates in range
            $chart_labels = [];
            $chart_expenses = [];
            
            $current_date = $start_date;
            while (strtotime($current_date) <= strtotime($end_date)) {
                $date_key = date('Y-m-d', strtotime($current_date));
                $label = date('M j', strtotime($current_date));
                
                $chart_labels[] = $label;
                $chart_expenses[] = $expense_chart_data[$date_key] ?? 0;
                
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
            
            echo json_encode([
                'success' => true,
                'line_chart' => [
                    'labels' => $chart_labels,
                    'data' => $chart_expenses
                ],
                'pie_chart' => [
                    'labels' => $category_labels,
                    'data' => $category_data,
                    'colors' => $category_colors,
                    'details' => $category_details
                ]
            ]);
            
        } elseif ($tab == 'profit') {
            // Improved profit SQL query with complete date range
            $profit_sql = "SELECT 
                            dates.date as date,
                            COALESCE(SUM(re.amount), 0) + COALESCE(SUM(mp.amount), 0) as revenue,
                            COALESCE(SUM(e.amount), 0) as expenses,
                            (COALESCE(SUM(re.amount), 0) + COALESCE(SUM(mp.amount), 0) - COALESCE(SUM(e.amount), 0)) as profit
                          FROM (
                            SELECT DATE(? + INTERVAL seq.seq DAY) as date
                            FROM (
                                SELECT a.N + b.N * 10 + c.N * 100 as seq
                                FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
                                CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                                CROSS JOIN (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
                            ) seq
                            WHERE DATE(? + INTERVAL seq.seq DAY) BETWEEN ? AND ?
                          ) dates
                          LEFT JOIN revenue_entries re ON dates.date = DATE(re.revenue_date)
                          LEFT JOIN membership_payments mp ON dates.date = DATE(mp.payment_date) AND mp.status = 'completed'
                          LEFT JOIN expenses e ON dates.date = DATE(e.expense_date)
                          GROUP BY dates.date
                          ORDER BY dates.date ASC";
            
            $profit_stmt = $conn->prepare($profit_sql);
            if (!$profit_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $profit_stmt->bind_param("ssss", $start_date, $start_date, $start_date, $end_date);
            if (!$profit_stmt->execute()) {
                throw new Exception("Execute failed: " . $profit_stmt->error);
            }
            $profit_result = $profit_stmt->get_result();
            
            $profit_data = [];
            while($row = $profit_result->fetch_assoc()) {
                $profit_data[$row['date']] = [
                    'revenue' => floatval($row['revenue']),
                    'expenses' => floatval($row['expenses']),
                    'profit' => floatval($row['profit'])
                ];
            }
            $profit_stmt->close();
            
            // Generate chart labels and data with all dates in range
            $chart_labels = [];
            $chart_revenue = [];
            $chart_expenses = [];
            $chart_profit = [];
            
            $current_date = $start_date;
            while (strtotime($current_date) <= strtotime($end_date)) {
                $date_key = date('Y-m-d', strtotime($current_date));
                $label = date('M j', strtotime($current_date));
                
                $chart_labels[] = $label;
                $chart_revenue[] = $profit_data[$date_key]['revenue'] ?? 0;
                $chart_expenses[] = $profit_data[$date_key]['expenses'] ?? 0;
                $chart_profit[] = $profit_data[$date_key]['profit'] ?? 0;
                
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
            
            echo json_encode([
                'success' => true,
                'line_chart' => [
                    'labels' => $chart_labels,
                    'revenue' => $chart_revenue,
                    'expenses' => $chart_expenses,
                    'profit' => $chart_profit
                ]
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    $conn->close();
    exit();
}

// Handle AJAX request for notifications

if (isset($_GET['ajax']) && $_GET['ajax'] == 'notifications') {
    header('Content-Type: application/json');
    ob_clean();


    // Check if user is authenticated
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        $conn->close();
        exit();
    }
    
    // Get notifications for current user
    $notifications_sql = "SELECT * FROM notifications 
                         WHERE (user_id = ? OR role = ?) 
                         ORDER BY created_at DESC 
                         LIMIT 10";
    $notifications_stmt = $conn->prepare($notifications_sql);
    $notifications_stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['role']);
    $notifications_stmt->execute();
    $notifications_result = $notifications_stmt->get_result();
    
    $notifications = [];
    while($row = $notifications_result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'],
            'read_status' => $row['read_status'],
            'created_at' => $row['created_at'],
            'time_ago' => time_ago($row['created_at'])
        ];
    }
    
    // Mark notifications as read when fetched
    $mark_read_sql = "UPDATE notifications SET read_status = 1 
                     WHERE (user_id = ? OR role = ?) AND read_status = 0";
    $mark_read_stmt = $conn->prepare($mark_read_sql);
    $mark_read_stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['role']);
    $mark_read_stmt->execute();
    $mark_read_stmt->close();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => 0
    ]);
    
    $notifications_stmt->close();
    $conn->close();
    exit();
}

// Helper function for time ago


function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

function render_payment_badge($method) {
    $m = strtolower(trim($method));
    switch($m) {
        case 'cash':
            return '<span class="gym-badge gym-badge-active"><i data-lucide="banknote" style="width:12px;height:12px;"></i> Cash</span>';
        case 'gcash':
            return '<span class="gym-badge gym-badge-info"><i data-lucide="smartphone" style="width:12px;height:12px;"></i> GCash</span>';
        case 'bank_transfer':
        case 'bank':
            return '<span class="gym-badge gym-badge-pending"><i data-lucide="building-2" style="width:12px;height:12px;"></i> Bank</span>';
        case 'card':
            return '<span class="gym-badge" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.3);"><i data-lucide="credit-card" style="width:12px;height:12px;"></i> Card</span>';
        default:
            return '<span class="gym-badge gym-badge-warning"><i data-lucide="globe" style="width:12px;height:12px;"></i> ' . htmlspecialchars(ucfirst($method)) . '</span>';
    }
}

// REGULAR PAGE LOAD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Database connection for regular page load

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include chat functions if the file exists
$unread_count = 0;
if (file_exists('chat_functions.php')) {
    require_once 'chat_functions.php';
    $unread_count = getUnreadCount($_SESSION['user_id'], $conn);
}

// Get unread notifications count
$unread_notifications = 0;
$notification_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE (user_id = ? OR role = ?) AND read_status = 0";
$notification_stmt = $conn->prepare($notification_query);
if ($notification_stmt) {
    $notification_stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['role']);
    $notification_stmt->execute();
    $notification_result = $notification_stmt->get_result();
    if ($notification_result) {            
        $unread_notifications = $notification_result->fetch_assoc()['unread_count'] ?? 0;
    }
    $notification_stmt->close();
}

// Handle form submissions with validation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token invalid. Please try again.";
        header("Location: revenue.php?tab=" . ($_POST['tab'] ?? 'revenue'));
        exit();
    }

    // Revenue entries
    if (isset($_POST['add_revenue'])) {
        $category_id = (int)$_POST['category_id'];
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $description = trim($_POST['description']);
        $payment_method = $_POST['payment_method'];
        $reference_id = !empty($_POST['reference_id']) ? (int)$_POST['reference_id'] : NULL;
        $reference_name = !empty($_POST['reference_name']) ? trim($_POST['reference_name']) : NULL;
        $revenue_date = $_POST['revenue_date'];
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : NULL;
        $recorded_by = $_SESSION['user_id'];

        // Validate inputs
        if ($amount === false || $amount <= 0) {
            $_SESSION['error'] = "Please enter a valid amount greater than 0.";
        } elseif (empty($description)) {
            $_SESSION['error'] = "Please enter a description.";
        } elseif (empty($category_id)) {
            $_SESSION['error'] = "Please select a category.";
        } elseif (!in_array($category_id, [1, 4])) {
            $_SESSION['error'] = "Please select a valid category.";
        } else {
            $sql = "INSERT INTO revenue_entries (category_id, amount, description, payment_method, reference_id, reference_name, revenue_date, recorded_by, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("idssisiss", $category_id, $amount, $description, $payment_method, $reference_id, $reference_name, $revenue_date, $recorded_by, $notes);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Revenue entry added successfully!";
                } else {
                    $_SESSION['error'] = "Error adding revenue entry: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Error preparing statement: " . $conn->error;
            }
        }
        
        header("Location: revenue.php?tab=revenue");
        exit();
    }
    
    if (isset($_POST['update_revenue'])) {
        $id = (int)$_POST['entry_id'];
        $category_id = (int)$_POST['category_id'];
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $description = trim($_POST['description']);
        $payment_method = $_POST['payment_method'];
        $reference_id = !empty($_POST['reference_id']) ? (int)$_POST['reference_id'] : NULL;
        $reference_name = !empty($_POST['reference_name']) ? trim($_POST['reference_name']) : NULL;
        $revenue_date = $_POST['revenue_date'];
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : NULL;

        // Validate inputs
        if ($amount === false || $amount <= 0) {
            $_SESSION['error'] = "Please enter a valid amount greater than 0.";
        } elseif (empty($description)) {
            $_SESSION['error'] = "Please enter a description.";
        } elseif (!in_array($category_id, [1, 4])) {
            $_SESSION['error'] = "Please select a valid category.";
        } else {
            $sql = "UPDATE revenue_entries SET category_id=?, amount=?, description=?, payment_method=?, reference_id=?, reference_name=?, revenue_date=?, notes=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idssisisi", $category_id, $amount, $description, $payment_method, $reference_id, $reference_name, $revenue_date, $notes, $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Revenue entry updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating revenue entry: " . $conn->error;
            }
            $stmt->close();
        }
        header("Location: revenue.php?tab=revenue");
        exit();
    }
    
    if (isset($_POST['delete_revenue'])) {
        $id = (int)$_POST['entry_id'];
        
        // Verify the entry exists and belongs to current user
        $check_sql = "SELECT id FROM revenue_entries WHERE id = ? AND recorded_by = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $id, $_SESSION['user_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $sql = "DELETE FROM revenue_entries WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Revenue entry deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting revenue entry: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Revenue entry not found or you don't have permission to delete it.";
        }
        $check_stmt->close();
        header("Location: revenue.php?tab=revenue");
        exit();
    }
    
    // Expense entries
    if (isset($_POST['add_expense'])) {
        $category_id = (int)$_POST['expense_category_id'];
        $amount = filter_var($_POST['expense_amount'], FILTER_VALIDATE_FLOAT);
        $description = trim($_POST['expense_description']);
        $payment_method = $_POST['expense_payment_method'];
        $expense_date = $_POST['expense_date'];
        $notes = !empty($_POST['expense_notes']) ? trim($_POST['expense_notes']) : NULL;
        $recorded_by = $_SESSION['user_id'];

        // Validate inputs
        if ($amount === false || $amount <= 0) {
            $_SESSION['error'] = "Please enter a valid amount greater than 0.";
        } elseif (empty($description)) {
            $_SESSION['error'] = "Please enter a description.";
        } elseif (empty($category_id)) {
            $_SESSION['error'] = "Please select a category.";
        } else {
            $sql = "INSERT INTO expenses (category_id, amount, description, payment_method, expense_date, recorded_by, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idsssis", $category_id, $amount, $description, $payment_method, $expense_date, $recorded_by, $notes);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Expense entry added successfully!";
            } else {
                $_SESSION['error'] = "Error adding expense entry: " . $stmt->error;
            }
            $stmt->close();
        }
        header("Location: revenue.php?tab=expenses");
        exit();
    }
    
    if (isset($_POST['update_expense'])) {
        $id = (int)$_POST['expense_id'];
        $category_id = (int)$_POST['expense_category_id'];
        $amount = filter_var($_POST['expense_amount'], FILTER_VALIDATE_FLOAT);
        $description = trim($_POST['expense_description']);
        $payment_method = $_POST['expense_payment_method'];
        $expense_date = $_POST['expense_date'];
        $notes = !empty($_POST['expense_notes']) ? trim($_POST['expense_notes']) : NULL;

        // Validate inputs
        if ($amount === false || $amount <= 0) {
            $_SESSION['error'] = "Please enter a valid amount greater than 0.";
        } elseif (empty($description)) {
            $_SESSION['error'] = "Please enter a description.";
        } else {
            $sql = "UPDATE expenses SET category_id=?, amount=?, description=?, payment_method=?, expense_date=?, notes=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idssssi", $category_id, $amount, $description, $payment_method, $expense_date, $notes, $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Expense entry updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating expense entry: " . $conn->error;
            }
            $stmt->close();
        }
        header("Location: revenue.php?tab=expenses");
        exit();
    }
    
    if (isset($_POST['delete_expense'])) {
        $id = (int)$_POST['expense_id'];
        
        // Verify the entry exists and belongs to current user
        $check_sql = "SELECT id FROM expenses WHERE id = ? AND recorded_by = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $id, $_SESSION['user_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $sql = "DELETE FROM expenses WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Expense entry deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting expense entry: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Expense entry not found or you don't have permission to delete it.";
        }
        $check_stmt->close();
        header("Location: revenue.php?tab=expenses");
        exit();
    }
}

// Handle exports - redirect to export script
if (isset($_GET['export'])) {
    $export_params = http_build_query($_GET);
    header("Location: revenue_export.php?$export_params");
    exit();
}

// Get filter parameters with proper defaults
$time_filter = $_GET['time_filter'] ?? 'month';
$category_filter = $_GET['category'] ?? '';
$payment_filter = $_GET['payment_method'] ?? '';
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$tab = $_GET['tab'] ?? 'revenue';

// Calculate date ranges
$base_date = date('Y-m-d');
switch ($time_filter) {
    case 'week':
        $default_start_date = date('Y-m-d', strtotime('-1 week'));
        break;
    case 'year':
        $default_start_date = date('Y-m-d', strtotime('-1 year'));
        break;
    default:
        $default_start_date = date('Y-m-d', strtotime('-1 month'));
        break;
}

$start_date = $start_date ?: $default_start_date;
$end_date = $end_date ?: $base_date;

// Cache categories to avoid multiple queries
$revenue_categories = [];
$revenue_categories_result = $conn->query("SELECT * FROM revenue_categories WHERE id IN (1, 4) AND is_active = TRUE ORDER BY name");
while($cat = $revenue_categories_result->fetch_assoc()) {
    $revenue_categories[] = $cat;
}

$expense_categories = [];
$expense_categories_result = $conn->query("SELECT * FROM expense_categories WHERE is_active = TRUE ORDER BY name");
while($cat = $expense_categories_result->fetch_assoc()) {
    $expense_categories[] = $cat;
}

// Build filter queries for revenue and expenses
$revenue_where_conditions = ["re.revenue_date BETWEEN ? AND ?"];
$expense_where_conditions = ["e.expense_date BETWEEN ? AND ?"];
$revenue_params = [$start_date, $end_date];
$expense_params = [$start_date, $end_date];
$revenue_types = "ss";
$expense_types = "ss";

if ($category_filter) {
    if ($tab == 'revenue') {
        $revenue_where_conditions[] = "re.category_id = ?";
        $revenue_params[] = $category_filter;
        $revenue_types .= "i";
    } elseif ($tab == 'expenses') {
        $expense_where_conditions[] = "e.category_id = ?";
        $expense_params[] = $category_filter;
        $expense_types .= "i";
    }
}

if ($payment_filter) {
    if ($tab == 'revenue') {
        $revenue_where_conditions[] = "re.payment_method = ?";
        $revenue_params[] = $payment_filter;
        $revenue_types .= "s";
    } elseif ($tab == 'expenses') {
        $expense_where_conditions[] = "e.payment_method = ?";
        $expense_params[] = $payment_filter;
        $expense_types .= "s";
    }
}

if ($search) {
    if ($tab == 'revenue') {
        $revenue_where_conditions[] = "(re.description LIKE ? OR re.reference_name LIKE ? OR rc.name LIKE ?)";
        $revenue_params[] = "%$search%";
        $revenue_params[] = "%$search%";
        $revenue_params[] = "%$search%";
        $revenue_types .= "sss";
    } elseif ($tab == 'expenses') {
        $expense_where_conditions[] = "(e.description LIKE ? OR ec.name LIKE ?)";
        $expense_params[] = "%$search%";
        $expense_params[] = "%$search%";
        $expense_types .= "ss";
    }
}

$revenue_where_sql = implode(" AND ", $revenue_where_conditions);
$expense_where_sql = implode(" AND ", $expense_where_conditions);

// Get revenue entries
$revenue_sql = "SELECT re.*, rc.name as category_name, rc.color as category_color, u.username as recorded_by_name
        FROM revenue_entries re
        JOIN revenue_categories rc ON re.category_id = rc.id
        JOIN users u ON re.recorded_by = u.id
        WHERE $revenue_where_sql
        ORDER BY re.revenue_date DESC, re.created_at DESC";

$revenue_stmt = $conn->prepare($revenue_sql);
if ($revenue_stmt && !empty($revenue_params)) {
    $revenue_stmt->bind_param($revenue_types, ...$revenue_params);
    $revenue_stmt->execute();
    $revenue_result = $revenue_stmt->get_result();
} else {
    $revenue_result = false;
}

// Get expense entries
$expense_sql = "SELECT e.*, ec.name as category_name, ec.color as category_color, u.username as recorded_by_name
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        JOIN users u ON e.recorded_by = u.id
        WHERE $expense_where_sql
        ORDER BY e.expense_date DESC, e.created_at DESC";

$expense_stmt = $conn->prepare($expense_sql);
if ($expense_stmt && !empty($expense_params)) {
    $expense_stmt->bind_param($expense_types, ...$expense_params);
    $expense_stmt->execute();
    $expense_result = $expense_stmt->get_result();
} else {
    $expense_result = false;
}

// Calculate financial metrics - IMPROVED VERSION
$total_revenue = 0;
$total_transactions = 0;

// Get ALL revenue including membership payments within date range - IMPROVED QUERY
$revenue_stats_sql = "SELECT 
                'revenue_entries' as source,
                COUNT(re.id) as transaction_count,
                COALESCE(SUM(re.amount), 0) as total_amount
              FROM revenue_entries re
              WHERE re.revenue_date BETWEEN ? AND ?
              
              UNION ALL
              
              SELECT 
                'membership_payments' as source,
                COUNT(mp.id) as transaction_count,
                COALESCE(SUM(mp.amount), 0) as total_amount
              FROM membership_payments mp
              WHERE mp.payment_date BETWEEN ? AND ?
              AND mp.status = 'completed'";

$revenue_stats_stmt = $conn->prepare($revenue_stats_sql);
if ($revenue_stats_stmt) {
    $revenue_stats_stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
    $revenue_stats_stmt->execute();
    $revenue_stats_result = $revenue_stats_stmt->get_result();
    
    if ($revenue_stats_result) {
        while($row = $revenue_stats_result->fetch_assoc()) {
            $total_revenue += $row['total_amount'] ?? 0;
            $total_transactions += $row['transaction_count'] ?? 0;
        }
    }
    $revenue_stats_stmt->close();
}

// Get detailed membership revenue breakdown by plan type
$membership_breakdown_sql = "SELECT 
                mp.plan_type,
                COUNT(mp.id) as transaction_count,
                COALESCE(SUM(mp.amount), 0) as total_amount,
                COALESCE(AVG(mp.amount), 0) as average_amount
              FROM membership_payments mp
              WHERE mp.payment_date BETWEEN ? AND ?
              AND mp.status = 'completed'
              GROUP BY mp.plan_type
              ORDER BY total_amount DESC";

$membership_breakdown_stmt = $conn->prepare($membership_breakdown_sql);
$membership_breakdown = [];
if ($membership_breakdown_stmt) {
    $membership_breakdown_stmt->bind_param("ss", $start_date, $end_date);
    $membership_breakdown_stmt->execute();
    $membership_breakdown_result = $membership_breakdown_stmt->get_result();
    while($row = $membership_breakdown_result->fetch_assoc()) {
        $membership_breakdown[] = $row;
    }
    $membership_breakdown_stmt->close();
}

$total_expenses = 0;
$total_expense_transactions = 0;

$expense_stats_sql = "SELECT 
                COUNT(e.id) as transaction_count,
                COALESCE(SUM(e.amount), 0) as total_amount
              FROM expenses e
              WHERE e.expense_date BETWEEN ? AND ?";

$expense_stats_stmt = $conn->prepare($expense_stats_sql);
if ($expense_stats_stmt) {
    $expense_stats_stmt->bind_param("ss", $start_date, $end_date);
    $expense_stats_stmt->execute();
    $expense_stats_result = $expense_stats_stmt->get_result();
    if ($expense_stats_result) {
        $expense_stats = $expense_stats_result->fetch_assoc();
        $total_expenses = $expense_stats['total_amount'] ?? 0;
        $total_expense_transactions = $expense_stats['transaction_count'] ?? 0;
    }
    $expense_stats_stmt->close();
}

$net_profit = $total_revenue - $total_expenses;
$expense_ratio = $total_revenue > 0 ? ($total_expenses / $total_revenue) * 100 : 0;

// Get revenue statistics by category - IMPROVED VERSION
$revenue_stats_sql = "SELECT 
                'Membership Fees' as category_name,
                'var(--blue)' as category_color,
                COUNT(mp.id) as transaction_count,
                COALESCE(SUM(mp.amount), 0) as total_amount,
                COALESCE(AVG(mp.amount), 0) as average_amount
              FROM membership_payments mp
              WHERE mp.payment_date BETWEEN ? AND ?
              AND mp.status = 'completed'
              
              UNION ALL
              
              SELECT 
                rc.name as category_name,
                rc.color as category_color,
                COUNT(re.id) as transaction_count,
                COALESCE(SUM(re.amount), 0) as total_amount,
                COALESCE(AVG(re.amount), 0) as average_amount
              FROM revenue_categories rc
              LEFT JOIN revenue_entries re ON rc.id = re.category_id AND re.revenue_date BETWEEN ? AND ?
              WHERE rc.id IN (1, 4)
              GROUP BY rc.id, rc.name, rc.color
              ORDER BY total_amount DESC";

$revenue_stats_stmt = $conn->prepare($revenue_stats_sql);
if ($revenue_stats_stmt) {
    $revenue_stats_stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
    $revenue_stats_stmt->execute();
    $revenue_stats_result = $revenue_stats_stmt->get_result();
} else {
    $revenue_stats_result = false;
}

// Get expense statistics by category
$expense_stats_sql = "SELECT 
                ec.name as category_name,
                ec.color as category_color,
                COUNT(e.id) as transaction_count,
                COALESCE(SUM(e.amount), 0) as total_amount,
                COALESCE(AVG(e.amount), 0) as average_amount
              FROM expense_categories ec
              LEFT JOIN expenses e ON ec.id = e.category_id AND e.expense_date BETWEEN ? AND ?
              GROUP BY ec.id, ec.name, ec.color
              ORDER BY total_amount DESC";

if ($payment_filter) {
    if ($tab == 'revenue') {
        $revenue_where_conditions[] = "re.payment_method = ?";
        $revenue_params[] = $payment_filter;
        $revenue_types .= "s";
    } elseif ($tab == 'expenses') {
        $expense_where_conditions[] = "e.payment_method = ?";
        $expense_params[] = $payment_filter;
        $expense_types .= "s";
    }
}

if ($search) {
    if ($tab == 'revenue') {
        $revenue_where_conditions[] = "(re.description LIKE ? OR re.reference_name LIKE ? OR rc.name LIKE ?)";
        $revenue_params[] = "%$search%";
        $revenue_params[] = "%$search%";
        $revenue_params[] = "%$search%";
        $revenue_types .= "sss";
    } elseif ($tab == 'expenses') {
        $expense_where_conditions[] = "(e.description LIKE ? OR ec.name LIKE ?)";
        $expense_params[] = "%$search%";
        $expense_params[] = "%$search%";
        $expense_types .= "ss";
    }
}

$revenue_where_sql = implode(" AND ", $revenue_where_conditions);
$expense_where_sql = implode(" AND ", $expense_where_conditions);

// Get revenue entries
$revenue_sql = "SELECT re.*, rc.name as category_name, rc.color as category_color, u.username as recorded_by_name
        FROM revenue_entries re
        JOIN revenue_categories rc ON re.category_id = rc.id
        JOIN users u ON re.recorded_by = u.id
        WHERE $revenue_where_sql
        ORDER BY re.revenue_date DESC, re.created_at DESC";

$revenue_stmt = $conn->prepare($revenue_sql);
if ($revenue_stmt && !empty($revenue_params)) {
    $revenue_stmt->bind_param($revenue_types, ...$revenue_params);
    $revenue_stmt->execute();
    $revenue_result = $revenue_stmt->get_result();
} else {
    $revenue_result = false;
}

// Get expense entries
$expense_sql = "SELECT e.*, ec.name as category_name, ec.color as category_color, u.username as recorded_by_name
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        JOIN users u ON e.recorded_by = u.id
        WHERE $expense_where_sql
        ORDER BY e.expense_date DESC, e.created_at DESC";

$expense_stmt = $conn->prepare($expense_sql);
if ($expense_stmt && !empty($expense_params)) {
    $expense_stmt->bind_param($expense_types, ...$expense_params);
    $expense_stmt->execute();
    $expense_result = $expense_stmt->get_result();
} else {
    $expense_result = false;
}

// Calculate financial metrics - IMPROVED VERSION
$total_revenue = 0;
$total_transactions = 0;

// Get ALL revenue including membership payments within date range - IMPROVED QUERY
$revenue_stats_sql = "SELECT 
                'revenue_entries' as source,
                COUNT(re.id) as transaction_count,
                COALESCE(SUM(re.amount), 0) as total_amount
              FROM revenue_entries re
              WHERE re.revenue_date BETWEEN ? AND ?
              
              UNION ALL
              
              SELECT 
                'membership_payments' as source,
                COUNT(mp.id) as transaction_count,
                COALESCE(SUM(mp.amount), 0) as total_amount
              FROM membership_payments mp
              WHERE mp.payment_date BETWEEN ? AND ?
              AND mp.status = 'completed'";

$revenue_stats_stmt = $conn->prepare($revenue_stats_sql);
if ($revenue_stats_stmt) {
    $revenue_stats_stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
    $revenue_stats_stmt->execute();
    $revenue_stats_result = $revenue_stats_stmt->get_result();
    
    if ($revenue_stats_result) {
        while($row = $revenue_stats_result->fetch_assoc()) {
            $total_revenue += $row['total_amount'] ?? 0;
            $total_transactions += $row['transaction_count'] ?? 0;
        }
    }
    $revenue_stats_stmt->close();
}

// Get detailed membership revenue breakdown by plan type
$membership_breakdown_sql = "SELECT 
                mp.plan_type,
                COUNT(mp.id) as transaction_count,
                COALESCE(SUM(mp.amount), 0) as total_amount,
                COALESCE(AVG(mp.amount), 0) as average_amount
              FROM membership_payments mp
              WHERE mp.payment_date BETWEEN ? AND ?
              AND mp.status = 'completed'
              GROUP BY mp.plan_type
              ORDER BY total_amount DESC";

$membership_breakdown_stmt = $conn->prepare($membership_breakdown_sql);
$membership_breakdown = [];
if ($membership_breakdown_stmt) {
    $membership_breakdown_stmt->bind_param("ss", $start_date, $end_date);
    $membership_breakdown_stmt->execute();
    $membership_breakdown_result = $membership_breakdown_stmt->get_result();
    while($row = $membership_breakdown_result->fetch_assoc()) {
        $membership_breakdown[] = $row;
    }
    $membership_breakdown_stmt->close();
}

$total_expenses = 0;
$total_expense_transactions = 0;

$expense_stats_sql = "SELECT 
                COUNT(e.id) as transaction_count,
                COALESCE(SUM(e.amount), 0) as total_amount
              FROM expenses e
              WHERE e.expense_date BETWEEN ? AND ?";

$expense_stats_stmt = $conn->prepare($expense_stats_sql);
if ($expense_stats_stmt) {
    $expense_stats_stmt->bind_param("ss", $start_date, $end_date);
    $expense_stats_stmt->execute();
    $expense_stats_result = $expense_stats_stmt->get_result();
    if ($expense_stats_result) {
        $expense_stats = $expense_stats_result->fetch_assoc();
        $total_expenses = $expense_stats['total_amount'] ?? 0;
        $total_expense_transactions = $expense_stats['transaction_count'] ?? 0;
    }
    $expense_stats_stmt->close();
}

$net_profit = $total_revenue - $total_expenses;
$expense_ratio = $total_revenue > 0 ? ($total_expenses / $total_revenue) * 100 : 0;

// Get revenue statistics by category - IMPROVED VERSION
$revenue_stats_sql = "SELECT 
                'Membership Fees' as category_name,
                'var(--blue)' as category_color,
                COUNT(mp.id) as transaction_count,
                COALESCE(SUM(mp.amount), 0) as total_amount,
                COALESCE(AVG(mp.amount), 0) as average_amount
              FROM membership_payments mp
              WHERE mp.payment_date BETWEEN ? AND ?
              AND mp.status = 'completed'
              
              UNION ALL
              
              SELECT 
                rc.name as category_name,
                rc.color as category_color,
                COUNT(re.id) as transaction_count,
                COALESCE(SUM(re.amount), 0) as total_amount,
                COALESCE(AVG(re.amount), 0) as average_amount
              FROM revenue_categories rc
              LEFT JOIN revenue_entries re ON rc.id = re.category_id AND re.revenue_date BETWEEN ? AND ?
              WHERE rc.id IN (1, 4)
              GROUP BY rc.id, rc.name, rc.color
              ORDER BY total_amount DESC";

$revenue_stats_stmt = $conn->prepare($revenue_stats_sql);
if ($revenue_stats_stmt) {
    $revenue_stats_stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
    $revenue_stats_stmt->execute();
    $revenue_stats_result = $revenue_stats_stmt->get_result();
} else {
    $revenue_stats_result = false;
}

// Get expense statistics by category
$expense_stats_sql = "SELECT 
                ec.name as category_name,
                ec.color as category_color,
                COUNT(e.id) as transaction_count,
                COALESCE(SUM(e.amount), 0) as total_amount,
                COALESCE(AVG(e.amount), 0) as average_amount
              FROM expense_categories ec
              LEFT JOIN expenses e ON ec.id = e.category_id AND e.expense_date BETWEEN ? AND ?
              GROUP BY ec.id, ec.name, ec.color
              ORDER BY total_amount DESC";

$expense_stats_stmt = $conn->prepare($expense_stats_sql);
if ($expense_stats_stmt) {
    $expense_stats_stmt->bind_param("ss", $start_date, $end_date);
    $expense_stats_stmt->execute();
    $expense_stats_result = $expense_stats_stmt->get_result();
} else {
    $expense_stats_result = false;
}

$username = $_SESSION['username'] ?? 'Admin';
?>

<?php
$page_title = "Revenue Tracking — Boiyets Fitness Gym";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
  <!-- Hero Page Header -->
  <div class="gym-page-header">
    <div>
      <h1 class="gym-page-title" style="display: flex; align-items: center; gap: 10px;">
        <i data-lucide="wallet" style="color: var(--accent);"></i>
        Financial Management & Revenue Tracking
      </h1>
      <p class="gym-page-subtitle">Welcome back, <strong><?php echo htmlspecialchars($username); ?></strong>! Real-time financial analytics, revenue tracking, and operating expense overview.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
      <div id="actionButtonContainer">
        <?php if ($tab == 'revenue'): ?>
          <button onclick="openAddRevenueModal()" class="gym-btn gym-btn-yellow">
            <i data-lucide="plus"></i> Add Revenue Entry
          </button>
        <?php elseif ($tab == 'expenses'): ?>
          <button onclick="openAddExpenseModal()" class="gym-btn gym-btn-danger">
            <i data-lucide="plus"></i> Add Expense Entry
          </button>
        <?php endif; ?>
      </div>
      
      <!-- Export Dropdown -->
      <div class="export-container" style="position: relative;">
        <button id="exportButton" class="gym-btn gym-btn-outline">
          <i data-lucide="download"></i> Export Data <i data-lucide="chevron-down" style="width: 14px; height: 14px;"></i>
        </button>
        <div id="exportDropdown" class="export-dropdown">
          <div style="padding: 8px 12px 10px; border-bottom: 1px solid var(--border-light); margin-bottom: 8px;">
            <div style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 0.9rem; color: var(--text-primary);">Export Reports</div>
            <div style="font-size: 0.75rem; color: var(--text-secondary);">Select format & document type</div>
          </div>
          <div style="padding: 4px;">
            <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; padding-left: 6px;">File Format</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 10px;">
              <button class="export-option" data-format="excel">
                <i data-lucide="file-spreadsheet" style="color: #22c55e;"></i> Excel
              </button>
              <button class="export-option" data-format="csv">
                <i data-lucide="file-text" style="color: #3b82f6;"></i> CSV
              </button>
            </div>
            <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; padding-left: 6px;">Report Type</div>
            <div style="display: flex; flex-direction: column; gap: 4px;">
              <button class="export-option" data-report="detailed">
                <i data-lucide="list"></i> Detailed Line Items
              </button>
              <button class="export-option" data-report="summary">
                <i data-lucide="pie-chart"></i> Category Summary
              </button>
              <?php if ($tab == 'profit'): ?>
              <button class="export-option" data-report="financial_statement">
                <i data-lucide="bar-chart-3"></i> Financial Statement
              </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Flash Notifications -->
  <?php if (isset($_SESSION['success'])): ?>
    <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="check-circle-2" style="width: 18px; height: 18px; color: #22c55e;"></i>
      <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['error'])): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 12px 18px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500;">
      <i data-lucide="alert-triangle" style="width: 18px; height: 18px; color: #ef4444;"></i>
      <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
    </div>
  <?php endif; ?>

  <!-- Navigation Tabs -->
  <div class="gym-tabs-container">
    <button class="gym-tab-btn <?php echo $tab == 'revenue' ? 'active' : ''; ?>" onclick="switchTab('revenue')">
      <i data-lucide="trending-up"></i> Revenue Streams
    </button>
    <button class="gym-tab-btn <?php echo $tab == 'expenses' ? 'active' : ''; ?>" onclick="switchTab('expenses')">
      <i data-lucide="trending-down"></i> Operating Expenses
    </button>
    <button class="gym-tab-btn <?php echo $tab == 'profit' ? 'active' : ''; ?>" onclick="switchTab('profit')">
      <i data-lucide="bar-chart-3"></i> Profit & Loss Overview
    </button>
  </div>

  <!-- Filter Bar Card -->
  <div class="gym-card" style="padding: 20px 24px !important; margin-bottom: 24px !important;">
    <form method="GET" id="filterForm">
      <input type="hidden" name="tab" value="<?php echo $tab; ?>">
      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <div style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1rem; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
          <i data-lucide="sliders" style="color: var(--accent);"></i> Filter Analytics
        </div>
        <div style="display: flex; gap: 6px;">
          <a href="?time_filter=week&tab=<?php echo $tab; ?>" class="gym-btn <?php echo $time_filter == 'week' ? 'gym-btn-yellow' : 'gym-btn-outline'; ?>" style="min-height: 32px !important; padding: 4px 14px !important; font-size: 0.8rem !important;">
            Week
          </a>
          <a href="?time_filter=month&tab=<?php echo $tab; ?>" class="gym-btn <?php echo $time_filter == 'month' ? 'gym-btn-yellow' : 'gym-btn-outline'; ?>" style="min-height: 32px !important; padding: 4px 14px !important; font-size: 0.8rem !important;">
            Month
          </a>
          <a href="?time_filter=year&tab=<?php echo $tab; ?>" class="gym-btn <?php echo $time_filter == 'year' ? 'gym-btn-yellow' : 'gym-btn-outline'; ?>" style="min-height: 32px !important; padding: 4px 14px !important; font-size: 0.8rem !important;">
            Year
          </a>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: end;">
        <div>
          <label class="gym-label">Start Date</label>
          <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-input" id="startDate" style="margin-bottom: 0 !important;">
        </div>
        <div>
          <label class="gym-label">End Date</label>
          <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-input" id="endDate" style="margin-bottom: 0 !important;">
        </div>

        <?php if ($tab == 'revenue' || $tab == 'expenses'): ?>
        <div>
          <label class="gym-label">Category</label>
          <select name="category" class="form-input" style="margin-bottom: 0 !important;">
            <option value="">All Categories</option>
            <?php if ($tab == 'revenue'): ?>
              <?php foreach($revenue_categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cat['name']); ?>
                </option>
              <?php endforeach; ?>
            <?php else: ?>
              <?php foreach($expense_categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($cat['name']); ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div>
          <label class="gym-label">Payment Method</label>
          <select name="payment_method" class="form-input" style="margin-bottom: 0 !important;">
            <option value="">All Payment Methods</option>
            <option value="cash" <?php echo $payment_filter == 'cash' ? 'selected' : ''; ?>>Cash</option>
            <option value="gcash" <?php echo $payment_filter == 'gcash' ? 'selected' : ''; ?>>GCash</option>
            <option value="bank_transfer" <?php echo $payment_filter == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
            <option value="card" <?php echo $payment_filter == 'card' ? 'selected' : ''; ?>>Card</option>
            <option value="online" <?php echo $payment_filter == 'online' ? 'selected' : ''; ?>>Online</option>
          </select>
        </div>
        <?php endif; ?>

        <div style="grid-column: span 1;">
          <label class="gym-label">Search Keyword</label>
          <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search descriptions..." class="form-input" style="margin-bottom: 0 !important;">
        </div>

        <div>
          <button type="submit" class="gym-btn gym-btn-yellow" style="width: 100%;">
            <i data-lucide="filter"></i> Apply Filters
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- REVENUE TAB CONTENT -->
  <div id="revenue-tab" class="tab-content <?php echo $tab == 'revenue' ? 'active' : ''; ?>">
    <!-- Revenue KPI Stats Grid -->
    <div class="gym-stats-grid">
      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Total Revenue</div>
          <div class="gym-stat-number" style="color: var(--accent-light);">₱<?php echo number_format($total_revenue, 2); ?></div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">All income categories</div>
        </div>
        <div class="gym-stat-icon">
          <i data-lucide="dollar-sign"></i>
        </div>
      </div>

      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Total Payments</div>
          <div class="gym-stat-number"><?php echo number_format($total_transactions); ?></div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Completed transactions</div>
        </div>
        <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3);">
          <i data-lucide="receipt"></i>
        </div>
      </div>

      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Reporting Period</div>
          <div class="gym-stat-number" style="font-size: 1.3rem; margin-top: 6px;"><?php echo date('M j', strtotime($start_date)); ?> – <?php echo date('M j, Y', strtotime($end_date)); ?></div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;"><?php echo ucfirst($time_filter); ?>ly date range</div>
        </div>
        <div class="gym-stat-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border-color: rgba(139, 92, 246, 0.3);">
          <i data-lucide="calendar"></i>
        </div>
      </div>

      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Avg. Transaction</div>
          <div class="gym-stat-number" style="color: #22c55e;">₱<?php echo $total_transactions > 0 ? number_format($total_revenue / $total_transactions, 2) : '0.00'; ?></div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Average size per payment</div>
        </div>
        <div class="gym-stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #22c55e; border-color: rgba(34, 197, 94, 0.3);">
          <i data-lucide="trending-up"></i>
        </div>
      </div>
    </div>

    <!-- Membership Revenue Breakdown -->
    <?php if (!empty($membership_breakdown)): ?>
    <div class="gym-card">
      <h2 class="gym-card-title">
        <i data-lucide="users" style="color: var(--blue);"></i>
        Membership Revenue Breakdown
      </h2>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
        <?php foreach($membership_breakdown as $plan): 
            $plan_pct = $total_revenue > 0 ? round(($plan['total_amount'] / $total_revenue) * 100, 1) : 0;
        ?>
          <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
              <span style="font-family: 'Outfit', sans-serif; font-weight: 700; color: var(--blue); text-transform: capitalize; font-size: 0.95rem;">
                <?php echo htmlspecialchars($plan['plan_type']); ?> Plan
              </span>
              <span class="gym-badge gym-badge-info"><?php echo $plan_pct; ?>% share</span>
            </div>
            <div style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.6rem; color: var(--text-primary);">
              ₱<?php echo number_format($plan['total_amount'], 2); ?>
            </div>
            <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;">
              <?php echo $plan['transaction_count']; ?> payments • Avg ₱<?php echo number_format($plan['average_amount'], 2); ?>
            </div>
            <div class="gym-progress-bg">
              <div class="gym-progress-fill" style="width: <?php echo min(100, $plan_pct); ?>%; background: var(--blue);"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Revenue Charts Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 24px;">
      <div class="gym-card" style="margin-bottom: 0 !important;">
        <h2 class="gym-card-title">
          <i data-lucide="trending-up" style="color: var(--accent);"></i>
          Revenue Trend Over Time
        </h2>
        <div class="gym-chart-wrapper">
          <canvas id="revenueChart"></canvas>
          <div id="revenueChartFallback" class="chart-fallback hidden">
            <div>
              <i data-lucide="bar-chart-3" style="width: 48px; height: 48px; color: var(--text-dim); margin-bottom: 8px;"></i>
              <p style="color: var(--text-secondary);">Loading chart visualization...</p>
            </div>
          </div>
        </div>
      </div>

      <div class="gym-card" style="margin-bottom: 0 !important;">
        <h2 class="gym-card-title">
          <i data-lucide="pie-chart" style="color: var(--accent);"></i>
          Revenue Distribution by Category
        </h2>
        <div class="gym-chart-wrapper">
          <canvas id="categoryChart"></canvas>
          <div id="categoryChartFallback" class="chart-fallback hidden">
            <div>
              <i data-lucide="pie-chart" style="width: 48px; height: 48px; color: var(--text-dim); margin-bottom: 8px;"></i>
              <p style="color: var(--text-secondary);">Loading category breakdown...</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Category Breakdown Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
      <?php 
      if ($revenue_stats_result) {
          $revenue_stats_result->data_seek(0);
          while($stat = $revenue_stats_result->fetch_assoc()): 
            if ($stat['total_amount'] > 0):
                $cat_share = $total_revenue > 0 ? round(($stat['total_amount'] / $total_revenue) * 100, 1) : 0;
      ?>
        <div class="gym-card" style="border-left: 4px solid <?php echo $stat['category_color']; ?> !important; margin-bottom: 0 !important; padding: 18px 20px !important;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <span style="font-family: 'Outfit', sans-serif; font-weight: 700; color: <?php echo $stat['category_color']; ?>; font-size: 0.95rem;">
              <?php echo htmlspecialchars($stat['category_name']); ?>
            </span>
            <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary);"><?php echo $cat_share; ?>%</span>
          </div>
          <div style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.5rem; color: var(--text-primary);">
            ₱<?php echo number_format($stat['total_amount'] ?? 0, 2); ?>
          </div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">
            <?php echo $stat['transaction_count'] ?? 0; ?> transactions • Avg ₱<?php echo number_format($stat['average_amount'] ?? 0, 2); ?>
          </div>
          <div class="gym-progress-bg">
            <div class="gym-progress-fill" style="width: <?php echo min(100, $cat_share); ?>%; background: <?php echo $stat['category_color']; ?>;"></div>
          </div>
        </div>
      <?php 
            endif;
          endwhile; 
      }
      ?>
    </div>

    <!-- Revenue Data Table -->
    <div class="gym-card">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
        <h2 class="gym-card-title" style="margin: 0 !important;">
          <i data-lucide="list" style="color: var(--accent);"></i>
          Revenue Entries Log
        </h2>
        <span class="gym-badge gym-badge-warning"><?php echo $revenue_result ? $revenue_result->num_rows : 0; ?> Records Found</span>
      </div>

      <?php if ($revenue_result && $revenue_result->num_rows > 0): ?>
        <div class="gym-table-wrapper" style="margin-bottom: 0 !important;">
          <table class="gym-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Description / Reference</th>
                <th>Payment Method</th>
                <th style="text-align: right;">Amount</th>
                <th style="text-align: center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while($entry = $revenue_result->fetch_assoc()): ?>
              <tr>
                <td style="white-space: nowrap; font-weight: 600; color: var(--text-primary);">
                  <i data-lucide="calendar" style="width: 14px; height: 14px; color: var(--text-dim); margin-right: 4px;"></i>
                  <?php echo date('M j, Y', strtotime($entry['revenue_date'])); ?>
                </td>
                <td>
                  <span class="gym-badge" style="background: <?php echo $entry['category_color']; ?>20; color: <?php echo $entry['category_color']; ?>; border: 1px solid <?php echo $entry['category_color']; ?>40;">
                    <?php echo htmlspecialchars($entry['category_name']); ?>
                  </span>
                </td>
                <td>
                  <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($entry['description']); ?></div>
                  <?php if (!empty($entry['reference_name'])): ?>
                    <div style="font-size: 0.75rem; color: var(--text-dim);">Ref: <?php echo htmlspecialchars($entry['reference_name']); ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php echo render_payment_badge($entry['payment_method']); ?>
                </td>
                <td style="text-align: right; font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.05rem; color: var(--accent-light); white-space: nowrap;">
                  ₱<?php echo number_format($entry['amount'], 2); ?>
                </td>
                <td style="text-align: center;">
                  <div style="display: inline-flex; gap: 8px;">
                    <button onclick="openEditRevenueModal(<?php echo htmlspecialchars(json_encode($entry)); ?>)" class="gym-btn gym-btn-outline" style="min-height: 30px !important; padding: 4px 8px !important;" title="Edit Entry">
                      <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                    </button>
                    <button onclick="openDeleteRevenueModal(<?php echo $entry['id']; ?>)" class="gym-btn gym-btn-danger" style="min-height: 30px !important; padding: 4px 8px !important;" title="Delete Entry">
                      <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div style="text-align: center; padding: 48px 24px; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border);">
          <i data-lucide="inbox" style="width: 48px; height: 48px; color: var(--text-dim); margin-bottom: 12px;"></i>
          <div style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.1rem; color: var(--text-primary);">No Revenue Entries Recorded</div>
          <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">Adjust your filter date range or add a new revenue transaction.</p>
          <button onclick="openAddRevenueModal()" class="gym-btn gym-btn-yellow" style="margin-top: 16px;">
            <i data-lucide="plus"></i> Add First Entry
          </button>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- EXPENSES TAB CONTENT -->
  <div id="expenses-tab" class="tab-content <?php echo $tab == 'expenses' ? 'active' : ''; ?>">
    <!-- Expenses KPI Stats Grid -->
    <div class="gym-stats-grid">
      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Total Expenses</div>
          <div class="gym-stat-number" style="color: var(--red);">₱<?php echo number_format($total_expenses, 2); ?></div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">All cost categories</div>
        </div>
        <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
          <i data-lucide="trending-down"></i>
        </div>
      </div>

      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Expense Claims</div>
          <div class="gym-stat-number"><?php echo number_format($total_expense_transactions); ?></div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Outflow transactions</div>
        </div>
        <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
          <i data-lucide="credit-card"></i>
        </div>
      </div>

      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Reporting Period</div>
          <div class="gym-stat-number" style="font-size: 1.3rem; margin-top: 6px;"><?php echo date('M j', strtotime($start_date)); ?> – <?php echo date('M j, Y', strtotime($end_date)); ?></div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;"><?php echo ucfirst($time_filter); ?>ly date range</div>
        </div>
        <div class="gym-stat-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border-color: rgba(139, 92, 246, 0.3);">
          <i data-lucide="calendar"></i>
        </div>
      </div>

      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Avg. Expense</div>
          <div class="gym-stat-number" style="color: var(--text-primary);">₱<?php echo $total_expense_transactions > 0 ? number_format($total_expenses / $total_expense_transactions, 2) : '0.00'; ?></div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Per transaction average</div>
        </div>
        <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3);">
          <i data-lucide="calculator"></i>
        </div>
      </div>
    </div>

    <!-- Expenses Charts Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 24px;">
      <div class="gym-card" style="margin-bottom: 0 !important;">
        <h2 class="gym-card-title">
          <i data-lucide="trending-down" style="color: var(--red);"></i>
          Expenses Trend Over Time
        </h2>
        <div class="gym-chart-wrapper">
          <canvas id="expensesChart"></canvas>
          <div id="expensesChartFallback" class="chart-fallback hidden">
            <div>
              <i data-lucide="bar-chart-3" style="width: 48px; height: 48px; color: var(--text-dim); margin-bottom: 8px;"></i>
              <p style="color: var(--text-secondary);">Loading expenses trend...</p>
            </div>
          </div>
        </div>
      </div>

      <div class="gym-card" style="margin-bottom: 0 !important;">
        <h2 class="gym-card-title">
          <i data-lucide="pie-chart" style="color: var(--red);"></i>
          Expenses Distribution by Category
        </h2>
        <div class="gym-chart-wrapper">
          <canvas id="expensesCategoryChart"></canvas>
          <div id="expensesCategoryChartFallback" class="chart-fallback hidden">
            <div>
              <i data-lucide="pie-chart" style="width: 48px; height: 48px; color: var(--text-dim); margin-bottom: 8px;"></i>
              <p style="color: var(--text-secondary);">Loading expense distribution...</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Expense Category Breakdown Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
      <?php 
      if ($expense_stats_result) {
          $expense_stats_result->data_seek(0);
          while($stat = $expense_stats_result->fetch_assoc()): 
            if ($stat['total_amount'] > 0):
                $exp_share = $total_expenses > 0 ? round(($stat['total_amount'] / $total_expenses) * 100, 1) : 0;
      ?>
        <div class="gym-card" style="border-left: 4px solid <?php echo $stat['category_color']; ?> !important; margin-bottom: 0 !important; padding: 18px 20px !important;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <span style="font-family: 'Outfit', sans-serif; font-weight: 700; color: <?php echo $stat['category_color']; ?>; font-size: 0.95rem;">
              <?php echo htmlspecialchars($stat['category_name']); ?>
            </span>
            <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary);"><?php echo $exp_share; ?>%</span>
          </div>
          <div style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.5rem; color: var(--red);">
            ₱<?php echo number_format($stat['total_amount'] ?? 0, 2); ?>
          </div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">
            <?php echo $stat['transaction_count'] ?? 0; ?> claims • Avg ₱<?php echo number_format($stat['average_amount'] ?? 0, 2); ?>
          </div>
          <div class="gym-progress-bg">
            <div class="gym-progress-fill" style="width: <?php echo min(100, $exp_share); ?>%; background: <?php echo $stat['category_color']; ?>;"></div>
          </div>
        </div>
      <?php 
            endif;
          endwhile; 
      }
      ?>
    </div>

    <!-- Expense Data Table -->
    <div class="gym-card">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
        <h2 class="gym-card-title" style="margin: 0 !important;">
          <i data-lucide="list" style="color: var(--red);"></i>
          Expense Entries Log
        </h2>
        <span class="gym-badge gym-badge-expired"><?php echo $expense_result ? $expense_result->num_rows : 0; ?> Claims Found</span>
      </div>

      <?php if ($expense_result && $expense_result->num_rows > 0): ?>
        <div class="gym-table-wrapper" style="margin-bottom: 0 !important;">
          <table class="gym-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Description</th>
                <th>Payment Method</th>
                <th style="text-align: right;">Amount</th>
                <th style="text-align: center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while($entry = $expense_result->fetch_assoc()): ?>
              <tr>
                <td style="white-space: nowrap; font-weight: 600; color: var(--text-primary);">
                  <i data-lucide="calendar" style="width: 14px; height: 14px; color: var(--text-dim); margin-right: 4px;"></i>
                  <?php echo date('M j, Y', strtotime($entry['expense_date'])); ?>
                </td>
                <td>
                  <span class="gym-badge" style="background: <?php echo $entry['category_color']; ?>20; color: <?php echo $entry['category_color']; ?>; border: 1px solid <?php echo $entry['category_color']; ?>40;">
                    <?php echo htmlspecialchars($entry['category_name']); ?>
                  </span>
                </td>
                <td style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($entry['description']); ?></td>
                <td>
                  <?php echo render_payment_badge($entry['payment_method']); ?>
                </td>
                <td style="text-align: right; font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.05rem; color: var(--red); white-space: nowrap;">
                  ₱<?php echo number_format($entry['amount'], 2); ?>
                </td>
                <td style="text-align: center;">
                  <div style="display: inline-flex; gap: 8px;">
                    <button onclick="openEditExpenseModal(<?php echo htmlspecialchars(json_encode($entry)); ?>)" class="gym-btn gym-btn-outline" style="min-height: 30px !important; padding: 4px 8px !important;" title="Edit Entry">
                      <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                    </button>
                    <button onclick="openDeleteExpenseModal(<?php echo $entry['id']; ?>)" class="gym-btn gym-btn-danger" style="min-height: 30px !important; padding: 4px 8px !important;" title="Delete Entry">
                      <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div style="text-align: center; padding: 48px 24px; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border);">
          <i data-lucide="inbox" style="width: 48px; height: 48px; color: var(--text-dim); margin-bottom: 12px;"></i>
          <div style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.1rem; color: var(--text-primary);">No Expense Claims Found</div>
          <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">Adjust your filter settings or record a new expense claim.</p>
          <button onclick="openAddExpenseModal()" class="gym-btn gym-btn-danger" style="margin-top: 16px;">
            <i data-lucide="plus"></i> Add First Expense
          </button>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- PROFIT & LOSS TAB CONTENT -->
  <div id="profit-tab" class="tab-content <?php echo $tab == 'profit' ? 'active' : ''; ?>">
    <!-- P&L KPI Stats Grid -->
    <div class="gym-stats-grid">
      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Total Revenue</div>
          <div class="gym-stat-number" style="color: var(--accent-light);">₱<?php echo number_format($total_revenue, 2); ?></div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Gross earnings</div>
        </div>
        <div class="gym-stat-icon">
          <i data-lucide="dollar-sign"></i>
        </div>
      </div>

      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Total Expenses</div>
          <div class="gym-stat-number" style="color: var(--red);">₱<?php echo number_format($total_expenses, 2); ?></div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Operating costs</div>
        </div>
        <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
          <i data-lucide="trending-down"></i>
        </div>
      </div>

      <div class="gym-stat-card" style="border-top: 3px solid <?php echo $net_profit >= 0 ? '#22c55e' : '#ef4444'; ?> !important;">
        <div>
          <div class="gym-stat-label">Net Profit / Loss</div>
          <div class="gym-stat-number" style="color: <?php echo $net_profit >= 0 ? '#22c55e' : '#ef4444'; ?>;">
            ₱<?php echo number_format($net_profit, 2); ?>
          </div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Revenue minus Expenses</div>
        </div>
        <div class="gym-stat-icon" style="background: <?php echo $net_profit >= 0 ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; color: <?php echo $net_profit >= 0 ? '#22c55e' : '#ef4444'; ?>; border-color: <?php echo $net_profit >= 0 ? 'rgba(34, 197, 94, 0.3)' : 'rgba(239, 68, 68, 0.3)'; ?>;">
          <i data-lucide="scale"></i>
        </div>
      </div>

      <div class="gym-stat-card">
        <div>
          <div class="gym-stat-label">Expense Ratio</div>
          <div class="gym-stat-number"><?php echo number_format($expense_ratio, 1); ?>%</div>
          <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Costs % of total revenue</div>
        </div>
        <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3);">
          <i data-lucide="pie-chart"></i>
        </div>
      </div>
    </div>

    <!-- P&L Chart -->
    <div class="gym-card">
      <h2 class="gym-card-title">
        <i data-lucide="bar-chart-3" style="color: var(--accent);"></i>
        Comparative Financial Performance (Revenue vs. Expenses)
      </h2>
      <div class="gym-chart-wrapper" style="height: 360px;">
        <canvas id="profitChart"></canvas>
        <div id="profitChartFallback" class="chart-fallback hidden">
          <div>
            <i data-lucide="line-chart" style="width: 48px; height: 48px; color: var(--text-dim); margin-bottom: 8px;"></i>
            <p style="color: var(--text-secondary);">Loading financial trend line...</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Financial Statement Summary -->
    <div class="gym-card">
      <h2 class="gym-card-title">
        <i data-lucide="file-text" style="color: var(--accent);"></i>
        Financial Summary Statement
      </h2>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
        <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px;">
          <div style="font-family: 'Outfit', sans-serif; font-weight: 700; color: var(--accent-light); font-size: 1.05rem; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
            <span>Revenue Inflow</span>
            <i data-lucide="arrow-up-right" style="color: #22c55e;"></i>
          </div>
          <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem;">
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Gross Revenue:</span>
              <span style="font-weight: 700; color: var(--accent-light);">₱<?php echo number_format($total_revenue, 2); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Total Transactions:</span>
              <span style="font-weight: 600; color: var(--text-primary);"><?php echo $total_transactions; ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Average Transaction:</span>
              <span style="font-weight: 600; color: var(--text-primary);">₱<?php echo $total_transactions > 0 ? number_format($total_revenue / $total_transactions, 2) : '0.00'; ?></span>
            </div>
          </div>
        </div>

        <div style="background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px;">
          <div style="font-family: 'Outfit', sans-serif; font-weight: 700; color: var(--red); font-size: 1.05rem; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
            <span>Expense Outflow</span>
            <i data-lucide="arrow-down-right" style="color: var(--red);"></i>
          </div>
          <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem;">
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Operating Costs:</span>
              <span style="font-weight: 700; color: var(--red);">₱<?php echo number_format($total_expenses, 2); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Expense Claims:</span>
              <span style="font-weight: 600; color: var(--text-primary);"><?php echo $total_expense_transactions; ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-secondary);">Average Expense:</span>
              <span style="font-weight: 600; color: var(--text-primary);">₱<?php echo $total_expense_transactions > 0 ? number_format($total_expenses / $total_expense_transactions, 2) : '0.00'; ?></span>
            </div>
          </div>
        </div>
      </div>

      <div style="border-top: 1px solid var(--border); margin-top: 20px; padding-top: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
          <span style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.1rem; color: var(--text-primary);">
            Net Operating Result:
          </span>
          <div style="font-size: 0.78rem; color: var(--text-dim);">Revenue minus operating costs for selected range</div>
        </div>
        <div style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.8rem; color: <?php echo $net_profit >= 0 ? '#22c55e' : '#ef4444'; ?>;">
          ₱<?php echo number_format($net_profit, 2); ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Revenue Modal -->
  <div id="revenueModal" class="modal">
    <div class="modal-content">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.2rem; color: var(--accent-light); margin: 0;" id="revenueModalTitle">Add Revenue Entry</h3>
        <button onclick="closeRevenueModal()" style="background: transparent; border: none; color: var(--text-secondary); cursor: pointer;" title="Close">
          <i data-lucide="x" style="width: 20px; height: 20px;"></i>
        </button>
      </div>
      
      <form method="POST" id="revenueForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="entry_id" id="revenue_entry_id">
        <div style="display: flex; flex-direction: column; gap: 14px;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <div>
              <label class="gym-label">Category *</label>
              <select name="category_id" id="revenue_category_id" required class="form-input">
                <option value="">Select Category</option>
                <option value="1">Product Sales</option>
                <option value="4">Service (Treadmill)</option>
              </select>
            </div>
            <div>
              <label class="gym-label">Amount (₱) *</label>
              <input type="number" name="amount" id="revenue_amount" step="0.01" min="0.01" required class="form-input" placeholder="0.00">
            </div>
          </div>
          
          <div>
            <label class="gym-label">Description *</label>
            <input type="text" name="description" id="revenue_description" required class="form-input" placeholder="Enter revenue description">
          </div>
          
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <div>
              <label class="gym-label">Payment Method *</label>
              <select name="payment_method" id="revenue_payment_method" required class="form-input">
                <option value="cash">Cash</option>
                <option value="gcash">GCash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="card">Card</option>
                <option value="online">Online</option>
              </select>
            </div>
            <div>
              <label class="gym-label">Revenue Date *</label>
              <input type="date" name="revenue_date" id="revenue_date" required class="form-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>
          
          <div>
            <label class="gym-label">Reference Name (Optional)</label>
            <input type="text" name="reference_name" id="revenue_reference_name" class="form-input" placeholder="Enter reference name if applicable">
          </div>
          
          <div>
            <label class="gym-label">Notes</label>
            <textarea name="notes" id="revenue_notes" rows="3" class="form-input" placeholder="Additional notes (optional)"></textarea>
          </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 24px;">
          <button type="button" onclick="closeRevenueModal()" class="gym-btn gym-btn-outline" style="flex: 1;">Cancel</button>
          <button type="submit" name="add_revenue" id="revenueSubmitButton" class="gym-btn gym-btn-yellow" style="flex: 1;">Add Revenue</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Expense Modal -->
  <div id="expenseModal" class="modal">
    <div class="modal-content">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.2rem; color: var(--red); margin: 0;" id="expenseModalTitle">Add Expense Entry</h3>
        <button onclick="closeExpenseModal()" style="background: transparent; border: none; color: var(--text-secondary); cursor: pointer;" title="Close">
          <i data-lucide="x" style="width: 20px; height: 20px;"></i>
        </button>
      </div>
      
      <form method="POST" id="expenseForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="expense_id" id="expense_id">
        <div style="display: flex; flex-direction: column; gap: 14px;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <div>
              <label class="gym-label">Category *</label>
              <select name="expense_category_id" id="expense_category_id" required class="form-input">
                <option value="">Select Category</option>
                <?php foreach($expense_categories as $cat): ?>
                  <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="gym-label">Amount (₱) *</label>
              <input type="number" name="expense_amount" id="expense_amount" step="0.01" min="0.01" required class="form-input" placeholder="0.00">
            </div>
          </div>
          
          <div>
            <label class="gym-label">Description *</label>
            <input type="text" name="expense_description" id="expense_description" required class="form-input" placeholder="Enter expense description">
          </div>
          
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <div>
              <label class="gym-label">Payment Method *</label>
              <select name="expense_payment_method" id="expense_payment_method" required class="form-input">
                <option value="cash">Cash</option>
                <option value="gcash">GCash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="card">Card</option>
                <option value="online">Online</option>
              </select>
            </div>
            <div>
              <label class="gym-label">Expense Date *</label>
              <input type="date" name="expense_date" id="expense_date" required class="form-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>
          
          <div>
            <label class="gym-label">Notes</label>
            <textarea name="expense_notes" id="expense_notes" rows="3" class="form-input" placeholder="Additional notes (optional)"></textarea>
          </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 24px;">
          <button type="button" onclick="closeExpenseModal()" class="gym-btn gym-btn-outline" style="flex: 1;">Cancel</button>
          <button type="submit" name="add_expense" id="expenseSubmitButton" class="gym-btn gym-btn-danger" style="flex: 1;">Add Expense</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Revenue Modal -->
  <div id="deleteRevenueModal" class="modal">
    <div class="modal-content">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.15rem; color: var(--red); margin: 0;">Delete Revenue Entry</h3>
        <button onclick="closeDeleteRevenueModal()" style="background: transparent; border: none; color: var(--text-secondary); cursor: pointer;" title="Close">
          <i data-lucide="x" style="width: 20px; height: 20px;"></i>
        </button>
      </div>
      
      <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 24px;">Are you sure you want to permanently delete this revenue entry? This operation cannot be reversed.</p>
      
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="entry_id" id="delete_revenue_entry_id">
        <div style="display: flex; gap: 10px;">
          <button type="button" onclick="closeDeleteRevenueModal()" class="gym-btn gym-btn-outline" style="flex: 1;">Cancel</button>
          <button type="submit" name="delete_revenue" class="gym-btn gym-btn-danger" style="flex: 1;">Confirm Delete</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Expense Modal -->
  <div id="deleteExpenseModal" class="modal">
    <div class="modal-content">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
        <h3 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.15rem; color: var(--red); margin: 0;">Delete Expense Entry</h3>
        <button onclick="closeDeleteExpenseModal()" style="background: transparent; border: none; color: var(--text-secondary); cursor: pointer;" title="Close">
          <i data-lucide="x" style="width: 20px; height: 20px;"></i>
        </button>
      </div>
      
      <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 24px;">Are you sure you want to permanently delete this expense claim? This operation cannot be reversed.</p>
      
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="expense_id" id="delete_expense_id">
        <div style="display: flex; gap: 10px;">
          <button type="button" onclick="closeDeleteExpenseModal()" class="gym-btn gym-btn-outline" style="flex: 1;">Cancel</button>
          <button type="submit" name="delete_expense" class="gym-btn gym-btn-danger" style="flex: 1;">Confirm Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

  <script>
    // Global chart variables
    let revenueChart = null;
    let categoryChart = null;
    let expensesChart = null;
    let expensesCategoryChart = null;
    let profitChart = null;

    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();
        
        // Initialize charts based on current tab
        initializeCharts();
        
        // Load notifications
        loadNotifications();
        
        // Dropdown functionality
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');
        const notificationBell = document.getElementById('notificationBell');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const exportButton = document.getElementById('exportButton');
        const exportDropdown = document.getElementById('exportDropdown');

        // User dropdown toggle
        if (userMenuButton && userDropdown) {
            userMenuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
                if (notificationDropdown) notificationDropdown.classList.remove('show');
                if (exportDropdown) exportDropdown.classList.remove('show');
            });
        }

        // Notification dropdown toggle
        if (notificationBell && notificationDropdown) {
            notificationBell.addEventListener('click', (e) => {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
                if (userDropdown) userDropdown.classList.remove('show');
                if (exportDropdown) exportDropdown.classList.remove('show');
                
                if (notificationDropdown.classList.contains('show')) {
                    loadNotifications();
                }
            });
        }

        let exportDropdownMoved = false;

        exportButton.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            
            // Close other dropdowns
            userDropdown.classList.remove('show');
            notificationDropdown.classList.remove('show');
            
            // Move dropdown to body if not already moved
            if (!exportDropdownMoved) {
                document.body.appendChild(exportDropdown);
                exportDropdownMoved = true;
            }
            
            // Toggle export dropdown
            const isShowing = exportDropdown.classList.contains('show');
            
            if (!isShowing) {
                // Get button position
                const rect = exportButton.getBoundingClientRect();
                
                // Position dropdown absolutely
                exportDropdown.style.position = 'fixed';
                exportDropdown.style.top = `${rect.bottom + window.scrollY + 5}px`;
                exportDropdown.style.left = `${rect.left + window.scrollX}px`;
                exportDropdown.style.zIndex = '10000';
                
                // Ensure it doesn't go off screen
                const viewportWidth = window.innerWidth;
                const dropdownWidth = exportDropdown.offsetWidth;
                
                if (rect.left + dropdownWidth > viewportWidth - 20) {
                    exportDropdown.style.left = `${viewportWidth - dropdownWidth - 20}px`;
                }
                
                exportDropdown.classList.add('show');
            } else {
                exportDropdown.classList.remove('show');
            }
        });

        // Export option handlers
        document.querySelectorAll('.export-option').forEach(option => {
            option.addEventListener('click', (e) => {
                e.preventDefault();
                const format = option.getAttribute('data-format');
                const report = option.getAttribute('data-report') || 'detailed';
                
                // Get current form data
                const formData = new FormData(document.getElementById('filterForm'));
                const params = new URLSearchParams();
                
                // Add all form data
                for (let [key, value] of formData) {
                    if (value) params.append(key, value);
                }
                
                // Add export parameters
                params.append('export', format);
                params.append('report_type', report);
                
                // Show loading state
                const originalText = exportButton.innerHTML;
                exportButton.innerHTML = '<div class="loading-spinner"></div> Exporting...';
                exportButton.disabled = true;
                
                // Open in new window for download
                setTimeout(() => {
                    window.open(`revenue_export.php?${params.toString()}`, '_blank');
                    
                    // Restore button state
                    exportButton.innerHTML = originalText;
                    exportButton.disabled = false;
                    
                    // Close dropdown
                    exportDropdown.classList.remove('show');
                }, 500);
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
            if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        });

        // Sidebar toggle
                if (fallbacks[tab]) {
            fallbacks[tab].forEach(fallbackId => {
                const fallback = document.getElementById(fallbackId);
                if (fallback) fallback.classList.remove('hidden');
            });
        }
    }

    function hideChartFallbacks(tab) {
        const fallbacks = {
            'revenue': ['revenueChartFallback', 'categoryChartFallback'],
            'expenses': ['expensesChartFallback', 'expensesCategoryChartFallback'],
            'profit': ['profitChartFallback']
        };
        
        if (fallbacks[tab]) {
            fallbacks[tab].forEach(fallbackId => {
                const fallback = document.getElementById(fallbackId);
                if (fallback) fallback.classList.add('hidden');
            });
        }
    }

    function showChartErrors(tab, errorMessage = '') {
        const fallbacks = {
            'revenue': ['revenueChartFallback', 'categoryChartFallback'],
            'expenses': ['expensesChartFallback', 'expensesCategoryChartFallback'],
            'profit': ['profitChartFallback']
        };
        
        if (fallbacks[tab]) {
            fallbacks[tab].forEach(fallbackId => {
                const fallback = document.getElementById(fallbackId);
                if (fallback) {
                    fallback.innerHTML = `
                        <div>
                            <i data-lucide="alert-circle" class="w-12 h-12 mx-auto text-red-400"></i>
                            <p>Failed to load chart data</p>
                            ${errorMessage ? `<p class="text-xs mt-1">${errorMessage}</p>` : ''}
                            <button onclick="initializeCharts('${tab}')" class="button-sm gym-btn gym-btn-yellow mt-2">
                                <i data-lucide="refresh-cw"></i> Retry
                            </button>
                        </div>
                    `;
                    lucide.createIcons();
                    fallback.classList.remove('hidden');
                }
            });
        }
    }

    // Helper function for no data messages
    function showNoDataMessage(fallbackId, message) {
        const fallback = document.getElementById(fallbackId);
        if (fallback) {
            fallback.innerHTML = `
                <div>
                    <i data-lucide="bar-chart-3" class="w-12 h-12 mx-auto text-gray-500"></i>
                    <p class="text-gray-400">${message}</p>
                </div>
            `;
            lucide.createIcons();
            fallback.classList.remove('hidden');
        }
    }

    function createRevenueChart(chartData) {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) {
            console.error('Revenue chart canvas not found');
            return;
        }
        
        // Destroy existing chart
        if (revenueChart) {
            revenueChart.destroy();
        }
        
        // Check if we have data
        if (!chartData || !chartData.labels || chartData.labels.length === 0) {
            showNoDataMessage('revenueChartFallback', 'No revenue data available for the selected period');
            return;
        }
        
        revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: chartData.data,
                    borderColor: 'var(--accent-light)',
                    backgroundColor: 'rgba(251, 191, 36, 0.15)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointBackgroundColor: 'var(--accent-light)',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Revenue: ₱${context.parsed.y.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: { 
                        ticks: { color: 'var(--text-secondary)' }, 
                        grid: { color: 'rgba(255, 255, 255, 0.05)' } 
                    },
                    y: { 
                        ticks: { 
                            color: 'var(--text-secondary)',
                            callback: value => '₱' + value
                        }, 
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }, 
                        beginAtZero: true 
                    }
                }
            }
        });
    }

    function createCategoryChart(chartData) {
        const ctx = document.getElementById('categoryChart');
        if (!ctx) return;
        
        // Destroy existing chart
        if (categoryChart) {
            categoryChart.destroy();
        }
        
        if (chartData.labels.length > 0) {
            categoryChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: chartData.colors,
                        borderWidth: 2,
                        borderColor: '#1a1a1a'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { 
                                color: 'var(--text-secondary)',
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ₱${value.toFixed(2)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        } else {
            // Show no data message
            const fallback = document.getElementById('categoryChartFallback');
            if (fallback) {
                fallback.innerHTML = `
                    <div>
                        <i data-lucide="pie-chart" class="w-12 h-12 mx-auto"></i>
                        <p>No revenue data available</p>
                    </div>
                `;
                lucide.createIcons();
                fallback.classList.remove('hidden');
            }
        }
    }

    function createExpensesChart(chartData) {
        const ctx = document.getElementById('expensesChart');
        if (!ctx) return;
        
        // Destroy existing chart
        if (expensesChart) {
            expensesChart.destroy();
        }
        
        // Check if we have data
        if (!chartData || !chartData.labels || chartData.labels.length === 0) {
            showNoDataMessage('expensesChartFallback', 'No expense data available for the selected period');
            return;
        }
        
        expensesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Expenses (₱)',
                    data: chartData.data,
                    borderColor: 'var(--red)',
                    backgroundColor: 'rgba(239, 68, 68, 0.15)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointBackgroundColor: 'var(--red)',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Expenses: ₱${context.parsed.y.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: { 
                        ticks: { color: 'var(--text-secondary)' }, 
                        grid: { color: 'rgba(255, 255, 255, 0.05)' } 
                    },
                    y: { 
                        ticks: { 
                            color: 'var(--text-secondary)',
                            callback: value => '₱' + value
                        }, 
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }, 
                        beginAtZero: true 
                    }
                }
            }
        });
    }

    function createExpensesCategoryChart(chartData) {
        const ctx = document.getElementById('expensesCategoryChart');
        if (!ctx) return;
        
        // Destroy existing chart
        if (expensesCategoryChart) {
            expensesCategoryChart.destroy();
        }
        
        if (chartData.labels.length > 0) {
            expensesCategoryChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: chartData.colors,
                        borderWidth: 2,
                        borderColor: '#1a1a1a'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { 
                                color: 'var(--text-secondary)',
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ₱${value.toFixed(2)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        } else {
            // Show no data message
            const fallback = document.getElementById('expensesCategoryChartFallback');
            if (fallback) {
                fallback.innerHTML = `
                    <div>
                        <i data-lucide="pie-chart" class="w-12 h-12 mx-auto"></i>
                        <p>No expense data available</p>
                    </div>
                `;
                lucide.createIcons();
                fallback.classList.remove('hidden');
            }
        }
    }

    function createProfitChart(chartData) {
        const ctx = document.getElementById('profitChart');
        if (!ctx) return;
        
        // Destroy existing chart
        if (profitChart) {
            profitChart.destroy();
        }
        
        // Check if we have data
        if (!chartData || !chartData.labels || chartData.labels.length === 0) {
            showNoDataMessage('profitChartFallback', 'No profit data available for the selected period');
            return;
        }
        
        profitChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: chartData.revenue,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: false,
                        tension: 0.3,
                        borderWidth: 2,
                        pointBackgroundColor: '#22c55e'
                    },
                    {
                        label: 'Expenses',
                        data: chartData.expenses,
                        borderColor: 'var(--red)',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: false,
                        tension: 0.3,
                        borderWidth: 2,
                        pointBackgroundColor: 'var(--red)'
                    },
                    {
                        label: 'Profit/Loss',
                        data: chartData.profit,
                        borderColor: 'var(--accent-light)',
                        backgroundColor: 'rgba(251, 191, 36, 0.1)',
                        fill: false,
                        tension: 0.3,
                        borderWidth: 3,
                        pointBackgroundColor: 'var(--accent-light)',
                        borderDash: [5, 5]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ₱${context.parsed.y.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: { 
                        ticks: { color: 'var(--text-secondary)' }, 
                        grid: { color: 'rgba(255, 255, 255, 0.05)' } 
                    },
                    y: { 
        const ctx = document.getElementById('expensesChart');
        if (!ctx) return;
        
        // Destroy existing chart
        if (expensesChart) {
            expensesChart.destroy();
        }
        
        // Check if we have data
        if (!chartData || !chartData.labels || chartData.labels.length === 0) {
            showNoDataMessage('expensesChartFallback', 'No expense data available for the selected period');
            return;
        }
        
        expensesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Expenses (₱)',
                    data: chartData.data,
                    borderColor: 'var(--red)',
                    backgroundColor: 'rgba(239, 68, 68, 0.15)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointBackgroundColor: 'var(--red)',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
    // Tab switching - IMPROVED VERSION
    function switchTab(tabName) {
        // Update URL without reloading page
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url);
        
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab content
        document.getElementById(tabName + '-tab').classList.add('active');
        
        // Update tab active states
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.currentTarget.classList.add('active');
        
        // Update the action button based on active tab
        updateActionButton(tabName);
        
        // Update the hidden tab field in filter form
        document.querySelector('input[name="tab"]').value = tabName;
        
        // Destroy existing charts first
        destroyAllCharts();
        
        // Reinitialize charts for the new tab with a small delay to ensure DOM is ready
        setTimeout(() => {
            initializeCharts(tabName);
        }, 100);
    }

    // Function to update the action button based on active tab
    function updateActionButton(activeTab) {
        const actionButtonContainer = document.getElementById('actionButtonContainer');
        if (!actionButtonContainer) return;
        
        actionButtonContainer.innerHTML = '';
        
        if (activeTab === 'revenue') {
            actionButtonContainer.innerHTML = `
                <button onclick="openAddRevenueModal()" class="button-sm gym-btn gym-btn-yellow">
                    <i data-lucide="plus"></i> Add Revenue
                </button>
            `;
        } else if (activeTab === 'expenses') {
            actionButtonContainer.innerHTML = `
                <button onclick="openAddExpenseModal()" class="button-sm btn-danger">
                    <i data-lucide="plus"></i> Add Expense
                </button>
            `;
        }
        
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    }

    // Modal functions for Revenue
    function openAddRevenueModal() {
        document.getElementById('revenueModalTitle').textContent = 'Add Revenue Entry';
        document.getElementById('revenueSubmitButton').name = 'add_revenue';
        document.getElementById('revenueSubmitButton').textContent = 'Add Revenue';
        document.getElementById('revenueForm').reset();
        document.getElementById('revenue_date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('revenueModal').style.display = 'flex';
    }

    function openEditRevenueModal(entry) {
        document.getElementById('revenueModalTitle').textContent = 'Edit Revenue Entry';
        document.getElementById('revenueSubmitButton').name = 'update_revenue';
        document.getElementById('revenueSubmitButton').textContent = 'Update Revenue';
        
        document.getElementById('revenue_entry_id').value = entry.id;
        document.getElementById('revenue_category_id').value = entry.category_id;
        document.getElementById('revenue_amount').value = entry.amount;
        document.getElementById('revenue_description').value = entry.description;
        document.getElementById('revenue_payment_method').value = entry.payment_method;
        document.getElementById('revenue_date').value = entry.revenue_date;
        document.getElementById('revenue_reference_name').value = entry.reference_name || '';
        document.getElementById('revenue_notes').value = entry.notes || '';
        
        document.getElementById('revenueModal').style.display = 'flex';
    }

    function closeRevenueModal() {
        document.getElementById('revenueModal').style.display = 'none';
    }

    function openDeleteRevenueModal(entryId) {
        document.getElementById('delete_revenue_entry_id').value = entryId;
        document.getElementById('deleteRevenueModal').style.display = 'flex';
    }

    function closeDeleteRevenueModal() {
        document.getElementById('deleteRevenueModal').style.display = 'none';
    }

    // Modal functions for Expenses
    function openAddExpenseModal() {
        document.getElementById('expenseModalTitle').textContent = 'Add Expense Entry';
        document.getElementById('expenseSubmitButton').name = 'add_expense';
        document.getElementById('expenseSubmitButton').textContent = 'Add Expense';
        document.getElementById('expenseForm').reset();
        document.getElementById('expense_date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('expenseModal').style.display = 'flex';
    }

    function openEditExpenseModal(entry) {
        document.getElementById('expenseModalTitle').textContent = 'Edit Expense Entry';
        document.getElementById('expenseSubmitButton').name = 'update_expense';
        document.getElementById('expenseSubmitButton').textContent = 'Update Expense';
        
        document.getElementById('expense_id').value = entry.id;
        document.getElementById('expense_category_id').value = entry.category_id;
        document.getElementById('expense_amount').value = entry.amount;
        document.getElementById('expense_description').value = entry.description;
        document.getElementById('expense_payment_method').value = entry.payment_method;
        document.getElementById('expense_date').value = entry.expense_date;
        document.getElementById('expense_notes').value = entry.notes || '';
        
        document.getElementById('expenseModal').style.display = 'flex';
    }

    function closeExpenseModal() {
        document.getElementById('expenseModal').style.display = 'none';
    }

    function openDeleteExpenseModal(entryId) {
        document.getElementById('delete_expense_id').value = entryId;
        document.getElementById('deleteExpenseModal').style.display = 'flex';
    }

    function closeDeleteExpenseModal() {
        document.getElementById('deleteExpenseModal').style.display = 'none';
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = ['revenueModal', 'expenseModal', 'deleteRevenueModal', 'deleteExpenseModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && event.target == modal) {
                modal.style.display = 'none';
            }
        });
    }
  </script>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
