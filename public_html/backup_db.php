<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    echo "Unauthorized access.";
    exit();
}

require_once __DIR__ . '/../config/config.php';

$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

$sql_dump = "-- Boiyets Fitness Gym Management System Database Backup\n";
$sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
$sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $sql_dump .= "-- --------------------------------------------------------\n";
    $sql_dump .= "-- Table structure for table `$table`\n";
    $sql_dump .= "-- --------------------------------------------------------\n\n";
    $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
    
    $create_table = $conn->query("SHOW CREATE TABLE `$table`")->fetch_array();
    $sql_dump .= $create_table[1] . ";\n\n";
    
    $rows = $conn->query("SELECT * FROM `$table`");
    if ($rows && $rows->num_rows > 0) {
        $sql_dump .= "-- Dumping data for table `$table`\n\n";
        while ($row = $rows->fetch_assoc()) {
            $sql_dump .= "INSERT INTO `$table` (";
            $keys = array_keys($row);
            $sql_dump .= "`" . implode("`, `", $keys) . "`) VALUES (";
            
            $values = [];
            foreach ($row as $val) {
                if (is_null($val)) {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . $conn->real_escape_string($val) . "'";
                }
            }
            $sql_dump .= implode(", ", $values) . ");\n";
        }
        $sql_dump .= "\n";
    }
}

$sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

$filename = "gym_db_backup_" . date('Y_m_d_His') . ".sql";
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($sql_dump));
echo $sql_dump;
exit();
?>
