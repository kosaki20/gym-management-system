<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Authentication guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page_title = "Admin Dashboard - Boiyets Fitness Gym";

// Fetch Stats
$total_members = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'")->fetch_assoc()['count'] ?? 0;
$attendance_today = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE DATE(check_in) = CURDATE()")->fetch_assoc()['count'] ?? 0;

$rev_entry = $conn->query("SELECT SUM(amount) as total FROM revenue_entries WHERE DATE(revenue_date) = CURDATE()")->fetch_assoc()['total'] ?? 0;
$mp_entry = $conn->query("SELECT SUM(amount) as total FROM membership_payments WHERE DATE(payment_date) = CURDATE() AND status = 'completed'")->fetch_assoc()['total'] ?? 0;
$revenue_today = floatval($rev_entry) + floatval($mp_entry);

$expiring_members = $conn->query("SELECT COUNT(*) as count FROM members WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND status = 'active'")->fetch_assoc()['count'] ?? 0;

// Fetch Recent Members
$recent_members = $conn->query("SELECT id, full_name, member_type, status, created_at FROM members ORDER BY created_at DESC LIMIT 6");

// Fetch Maintenance Stats
$broken = $conn->query("SELECT COUNT(*) as c FROM equipment WHERE status = 'Broken'")->fetch_assoc()['c'] ?? 0;
$repairing = $conn->query("SELECT COUNT(*) as c FROM equipment WHERE status = 'Under Repair'")->fetch_assoc()['c'] ?? 0;
$maintenance = $conn->query("SELECT COUNT(*) as c FROM equipment WHERE status = 'Needs Maintenance'")->fetch_assoc()['c'] ?? 0;

// Recent Maintenance List
$recent_maintenance = $conn->query("SELECT id, name, status, last_updated FROM equipment WHERE status IN ('Broken', 'Under Repair', 'Needs Maintenance') ORDER BY last_updated DESC LIMIT 4");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<div class="gym-main-container">
    <!-- Hero Greeting Banner -->
    <div class="gym-page-header">
        <div>
            <h1 class="gym-page-title">Gym Administrator Dashboard</h1>
            <p class="gym-page-subtitle">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></strong>! Here is today's overview.</p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="member_registration.php" class="gym-btn gym-btn-yellow"><i class="fa-solid fa-user-plus"></i> Add Member</a>
            <a href="countersales.php" class="gym-btn gym-btn-outline"><i class="fa-solid fa-cash-register"></i> POS Sale</a>
        </div>
    </div>

    <!-- 4 High-Impact KPI Stat Cards -->
    <div class="gym-stats-grid">
        <div class="gym-stat-card">
            <div>
                <div class="gym-stat-label">Active Members</div>
                <div class="gym-stat-number"><?php echo number_format($total_members); ?></div>
            </div>
            <div class="gym-stat-icon"><i class="fa-solid fa-users"></i></div>
        </div>

        <div class="gym-stat-card">
            <div>
                <div class="gym-stat-label">Today's Attendance</div>
                <div class="gym-stat-number"><?php echo number_format($attendance_today); ?></div>
            </div>
            <div class="gym-stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3);"><i class="fa-solid fa-user-check"></i></div>
        </div>

        <div class="gym-stat-card">
            <div>
                <div class="gym-stat-label">Today's Revenue</div>
                <div class="gym-stat-number">₱<?php echo number_format($revenue_today, 2); ?></div>
            </div>
            <div class="gym-stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border-color: rgba(16, 185, 129, 0.3);"><i class="fa-solid fa-money-bill-wave"></i></div>
        </div>

        <div class="gym-stat-card" style="border-top-color: #ef4444;">
            <div>
                <div class="gym-stat-label">Expiring Soon</div>
                <div class="gym-stat-number" style="color: #f87171;"><?php echo number_format($expiring_members); ?></div>
            </div>
            <div class="gym-stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
    </div>

    <!-- Asymmetric 2-Column Workspace -->
    <div class="asymmetric-dashboard-grid">
        <!-- Left Column: Member Directory & Attendance Stream -->
        <div>
            <div class="gym-card">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                    <h2 class="gym-card-title" style="margin: 0;"><i class="fa-solid fa-users"></i> Recent Member Registrations</h2>
                    <a href="all_members.php" style="color: #f59e0b; text-decoration: none; font-weight: 700; font-size: 0.9rem;">View All Members <i class="fa-solid fa-arrow-right"></i></a>
                </div>

                <div class="gym-table-wrapper" style="margin-bottom: 0;">
                    <table class="gym-table gym-table">
                        <thead>
                            <tr>
                                <th>Member Name</th>
                                <th>Membership Type</th>
                                <th>Status</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_members && $recent_members->num_rows > 0): ?>
                                <?php while ($m = $recent_members->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight: 700;"><?php echo htmlspecialchars($m['full_name']); ?></td>
                                        <td><span style="text-transform: capitalize;"><?php echo htmlspecialchars($m['member_type']); ?></span></td>
                                        <td>
                                            <span class="gym-badge <?php echo $m['status'] == 'active' ? 'gym-badge-active' : 'gym-badge-expired'; ?>">
                                                <?php echo strtoupper($m['status']); ?>
                                            </span>
                                        </td>
                                        <td style="color: #94a3b8; font-size: 0.88rem;"><?php echo date('M j, Y', strtotime($m['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 2rem;">No member records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Management Tools -->
            <div class="gym-card">
                <h2 class="gym-card-title"><i class="fa-solid fa-bolt"></i> Quick Management Tools</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                    <a href="attendance_logs.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start;">
                        <i class="fa-solid fa-qrcode" style="color: #f59e0b;"></i> Log Attendance
                    </a>
                    <a href="equipment_monitoring.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start;">
                        <i class="fa-solid fa-toolbox" style="color: #3b82f6;"></i> Equipment
                    </a>
                    <a href="chat.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start;">
                        <i class="fa-solid fa-comments" style="color: #10b981;"></i> Messages
                    </a>
                    <a href="settings.php" class="gym-btn gym-btn-outline" style="justify-content: flex-start;">
                        <i class="fa-solid fa-gear" style="color: #8b5cf6;"></i> Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Column: Equipment Maintenance & Status Feeds -->
        <div>
            <div class="gym-card">
                <h2 class="gym-card-title"><i class="fa-solid fa-wrench"></i> Equipment Alerts</h2>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; text-align: center; margin-bottom: 1.25rem;">
                    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 0.75rem;">
                        <div style="font-size: 1.5rem; font-weight: 800; color: #f87171; font-family: 'Outfit', sans-serif;"><?php echo $broken; ?></div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: #ef4444; text-transform: uppercase;">Broken</div>
                    </div>
                    <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 10px; padding: 0.75rem;">
                        <div style="font-size: 1.5rem; font-weight: 800; color: #fbbf24; font-family: 'Outfit', sans-serif;"><?php echo $repairing; ?></div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: #f59e0b; text-transform: uppercase;">Repairing</div>
                    </div>
                    <div style="background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 10px; padding: 0.75rem;">
                        <div style="font-size: 1.5rem; font-weight: 800; color: #60a5fa; font-family: 'Outfit', sans-serif;"><?php echo $maintenance; ?></div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: #3b82f6; text-transform: uppercase;">Maintenance</div>
                    </div>
                </div>

                <?php if ($recent_maintenance && $recent_maintenance->num_rows > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                        <?php while ($item = $recent_maintenance->fetch_assoc()): ?>
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #0c111c; border-radius: 8px; border: 1px solid #232f48;">
                                <span style="font-weight: 700; font-size: 0.92rem;"><?php echo htmlspecialchars($item['name']); ?></span>
                                <span class="gym-badge <?php echo $item['status'] == 'Broken' ? 'gym-badge-expired' : 'gym-badge-warning'; ?>">
                                    <?php echo htmlspecialchars($item['status']); ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: #10b981; padding: 1.5rem 0;">
                        <i class="fa-solid fa-circle-check" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                        <p style="margin: 0; font-weight: 700;">All Equipment Operational!</p>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 1.25rem;">
                    <a href="equipment_monitoring.php" class="gym-btn gym-btn-outline" style="width: 100%; box-sizing: border-box;">View Full Equipment Status</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
