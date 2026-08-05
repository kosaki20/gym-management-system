<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    header("Location: index.php");
    exit();
}

// Database connection

require_once __DIR__ . '/../config/config.php';

// Check connection
// ADD THIS SECTION FOR CHAT FUNCTIONALITY
require_once 'chat_functions.php';
$unread_count = getUnreadCount($_SESSION['user_id'], $conn);
$logged_in_user_id = $_SESSION['user_id'];

// Add mobile-specific caching headers if needed
if (strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false) {
    header("Cache-Control: max-age=300"); // 5 minutes for mobile
}

// Function to get client details
function getClientDetails($conn, $user_id) {
    try {
        $sql = "SELECT m.* FROM members m 
                INNER JOIN users u ON m.user_id = u.id 
                WHERE u.id = ? AND m.member_type = 'client'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } catch (Exception $e) {
        error_log("Error fetching client details: " . $e->getMessage());
        return null;
    }
}

// Function to get nutrition progress statistics
function getNutritionProgressStats($conn, $user_id) {
    $stats = [];
    
    try {
        // Get member_id
        $member_sql = "SELECT m.id FROM members m 
                       INNER JOIN users u ON m.user_id = u.id 
                       WHERE u.id = ?";
        $stmt = $conn->prepare($member_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $member_result = $stmt->get_result();
        $member = $member_result->fetch_assoc();
        $member_id = $member['id'];
        
        // Total nutrition sessions
        $total_sessions_sql = "SELECT COUNT(*) as total FROM nutrition_sessions WHERE member_id = ?";
        $stmt = $conn->prepare($total_sessions_sql);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_sessions'] = $result->fetch_assoc()['total'];
        
        // This week's sessions
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_sql = "SELECT COUNT(*) as count FROM nutrition_sessions 
                     WHERE member_id = ? AND session_date >= ?";
        $stmt = $conn->prepare($week_sql);
        $stmt->bind_param("is", $member_id, $week_start);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['this_week'] = $result->fetch_assoc()['count'];
        
        // This month's sessions
        $month_start = date('Y-m-01');
        $month_sql = "SELECT COUNT(*) as count FROM nutrition_sessions 
                      WHERE member_id = ? AND session_date >= ?";
        $stmt = $conn->prepare($month_sql);
        $stmt->bind_param("is", $member_id, $month_start);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['this_month'] = $result->fetch_assoc()['count'];
        
        // Last nutrition session date
        $last_session_sql = "SELECT session_date FROM nutrition_sessions 
                             WHERE member_id = ? 
                             ORDER BY session_date DESC LIMIT 1";
        $stmt = $conn->prepare($last_session_sql);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $last_session = $result->fetch_assoc();
        $stats['last_session'] = $last_session ? $last_session['session_date'] : null;
        
        // Weekly completion rate (last 4 weeks)
        $four_weeks_ago = date('Y-m-d', strtotime('-4 weeks'));
        $weekly_sql = "SELECT 
                        YEARWEEK(session_date) as week,
                        COUNT(*) as sessions,
                        GROUP_CONCAT(DISTINCT session_date) as dates
                       FROM nutrition_sessions 
                       WHERE member_id = ? AND session_date >= ?
                       GROUP BY YEARWEEK(session_date)
                       ORDER BY week DESC
                       LIMIT 4";
        $stmt = $conn->prepare($weekly_sql);
        $stmt->bind_param("is", $member_id, $four_weeks_ago);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['weekly_data'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['weekly_data'][] = $row;
        }
    } catch (Exception $e) {
        error_log("Error fetching nutrition progress stats: " . $e->getMessage());
        $stats = [
            'total_sessions' => 0,
            'this_week' => 0,
            'this_month' => 0,
            'last_session' => null,
            'weekly_data' => []
        ];
    }
    
    return $stats;
}

// Function to get nutrition sessions with details
function getNutritionSessions($conn, $user_id, $limit = 20) {
    $sessions = [];
    
    try {
        $sql = "SELECT 
                    ns.*,
                    mp.plan_name,
                    mp.meals as plan_meals
                FROM nutrition_sessions ns
                LEFT JOIN meal_plans mp ON ns.meal_plan_id = mp.id
                INNER JOIN members m ON ns.member_id = m.id
                INNER JOIN users u ON m.user_id = u.id
                WHERE u.id = ?
                ORDER BY ns.session_date DESC, ns.created_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $row['completed_meals'] = json_decode($row['completed_meals'], true) ?: [];
            $row['plan_meals'] = json_decode($row['plan_meals'], true) ?: [];
            $sessions[] = $row;
        }
    } catch (Exception $e) {
        error_log("Error fetching nutrition sessions: " . $e->getMessage());
        $sessions = [];
    }
    
    return $sessions;
}

// Function to get nutrition streak
function getNutritionStreak($conn, $user_id) {
    $streak = 0;
    
    try {
        $member_sql = "SELECT m.id FROM members m 
                       INNER JOIN users u ON m.user_id = u.id 
                       WHERE u.id = ?";
        $stmt = $conn->prepare($member_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $member_result = $stmt->get_result();
        $member = $member_result->fetch_assoc();
        $member_id = $member['id'];
        
        $current_date = date('Y-m-d');
        $check_date = $current_date;
        
        while (true) {
            $check_sql = "SELECT 1 FROM nutrition_sessions 
                          WHERE member_id = ? AND session_date = ? 
                          LIMIT 1";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("is", $member_id, $check_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $streak++;
                $check_date = date('Y-m-d', strtotime($check_date . ' -1 day'));
            } else {
                break;
            }
        }
    } catch (Exception $e) {
        error_log("Error calculating nutrition streak: " . $e->getMessage());
        $streak = 0;
    }
    
    return $streak;
}

$client = getClientDetails($conn, $logged_in_user_id);
$progressStats = getNutritionProgressStats($conn, $logged_in_user_id);
$nutritionSessions = getNutritionSessions($conn, $logged_in_user_id);
$currentStreak = getNutritionStreak($conn, $logged_in_user_id);

// If client not found
if (!$client) {
    $username = $_SESSION['username'];
    try {
        $sql = "SELECT m.* FROM members m 
                INNER JOIN users u ON m.user_id = u.id 
                WHERE u.username = ? AND m.member_type = 'client'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $client = $result->fetch_assoc();
        
        if (!$client) {
            $client = [
                'full_name' => $_SESSION['username'],
                'member_type' => 'client'
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching client fallback: " . $e->getMessage());
        $client = [
            'full_name' => $_SESSION['username'],
            'member_type' => 'client'
        ];
    }
}
?>
<?php
$page_title = "Nutrition Tracking - BOIYETS FITNESS GYM";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<div class="gym-main-container">
    <!-- Loading Skeleton -->
    <div id="loadingSkeleton" class="hidden">
        <div class="card">
            <div class="animate-pulse">
                <div class="h-4 bg-gray-700 rounded w-1/4 mb-2 skeleton"></div>
                <div class="h-6 bg-gray-700 rounded w-1/2 skeleton"></div>
            </div>
        </div>
    </div>

    

     


    
        

        <!-- Mobile Sidebar -->
        

        
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center space-x-3">
                    <a href="client_dashboard.php" class="text-gray-300 hover:text-yellow-400 transition-colors p-2 rounded-lg hover:bg-white/5">
                        <i data-lucide="arrow-left" class="w-5 h-5"></i>
                    </a>
                    <h2 class="text-2xl font-bold text-yellow-400 flex items-center gap-3">
                        <i data-lucide="chart-bar" class="w-8 h-8"></i>
                        Nutrition Tracking
                    </h2>
                </div>
                <div class="flex items-center gap-4">
                    <?php if ($currentStreak > 0): ?>
                        <span class="streak-badge">
                            <i data-lucide="flame" class="w-4 h-4"></i>
                            <?php echo $currentStreak; ?> Day Streak
                        </span>
                    <?php endif; ?>
                    <span class="bg-green-500 px-4 py-2 rounded-full text-sm font-semibold">
                        Total Days: <?php echo $progressStats['total_sessions']; ?>
                    </span>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stats-card">
                    <div class="text-3xl font-bold text-green-400 mb-2"><?php echo $progressStats['total_sessions']; ?></div>
                    <div class="text-gray-400">Total Days</div>
                    <div class="text-sm text-gray-500 mt-2">Following nutrition plan</div>
                </div>
                
                <div class="stats-card">
                    <div class="text-3xl font-bold text-yellow-400 mb-2"><?php echo $progressStats['this_week']; ?></div>
                    <div class="text-gray-400">This Week</div>
                    <div class="text-sm text-gray-500 mt-2">Weekly consistency</div>
                </div>
                
                <div class="stats-card">
                    <div class="text-3xl font-bold text-blue-400 mb-2"><?php echo $progressStats['this_month']; ?></div>
                    <div class="text-gray-400">This Month</div>
                    <div class="text-sm text-gray-500 mt-2">Monthly progress</div>
                </div>
                
                <div class="stats-card">
                    <div class="text-2xl font-bold text-purple-400 mb-2">
                        <?php echo $progressStats['last_session'] ? date('M j', strtotime($progressStats['last_session'])) : 'Never'; ?>
                    </div>
                    <div class="text-gray-400">Last Tracked</div>
                    <div class="text-sm text-gray-500 mt-2">Most recent meal tracking</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Nutrition Sessions -->
                <div class="card">
                    <h3 class="text-xl font-bold text-yellow-400 mb-4 flex items-center gap-2">
                        <i data-lucide="history" class="w-5 h-5"></i>
                        Recent Meal Tracking
                    </h3>
                    
                    <div class="space-y-4">
                        <?php if (!empty($nutritionSessions)): ?>
                            <?php foreach ($nutritionSessions as $session): ?>
                                <div class="session-item">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h4 class="font-semibold text-lg">
                                                <?php echo date('l, F j, Y', strtotime($session['session_date'])); ?>
                                            </h4>
                                            <?php if ($session['plan_name']): ?>
                                                <p class="text-gray-400 text-sm">
                                                    Plan: <?php echo htmlspecialchars($session['plan_name']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="bg-green-500 px-3 py-1 rounded-full text-sm font-semibold">
                                            <?php echo count($session['completed_meals']); ?> meals
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($session['completed_meals'])): ?>
                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($session['completed_meals'] as $meal): ?>
                                                <span class="meal-badge">
                                                    <i data-lucide="check" class="w-3 h-3"></i>
                                                    <?php echo htmlspecialchars($meal); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-gray-500 text-sm">No meals recorded for this day.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <i data-lucide="utensils" class="w-16 h-16 mx-auto mb-4"></i>
                                <p class="text-lg">No nutrition tracking yet.</p>
                                <p class="text-sm">Start marking meals as done to track your nutrition!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Progress Charts -->
                <div class="card">
                    <h3 class="text-xl font-bold text-yellow-400 mb-4 flex items-center gap-2">
                        <i data-lucide="trending-up" class="w-5 h-5"></i>
                        Nutrition Consistency
                    </h3>
                    
                    <div class="space-y-6">
                        <!-- Weekly Activity -->
                        <div>
                            <h4 class="font-semibold mb-3">Last 4 Weeks</h4>
                            <div class="space-y-3">
                                <?php if (!empty($progressStats['weekly_data'])): ?>
                                    <?php foreach ($progressStats['weekly_data'] as $week): ?>
                                        <div>
                                            <div class="flex justify-between text-sm text-gray-400 mb-1">
                                                <span>Week <?php echo substr($week['week'], 4); ?></span>
                                                <span><?php echo $week['sessions']; ?> day<?php echo $week['sessions'] > 1 ? 's' : ''; ?></span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo min($week['sessions'] * 14.28, 100); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-gray-500 text-sm text-center py-4">No weekly data available yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Consistency Meter -->
                        <div>
                            <h4 class="font-semibold mb-3">Nutrition Consistency</h4>
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div class="p-4   rounded-lg" style="background: var(--bg-card);">
                                    <div class="text-2xl font-bold text-green-400"><?php echo $progressStats['this_week']; ?>/7</div>
                                    <div class="text-xs text-gray-400">This Week</div>
                                </div>
                                <div class="p-4   rounded-lg" style="background: var(--bg-card);">
                                    <div class="text-2xl font-bold text-yellow-400"><?php echo $currentStreak; ?></div>
                                    <div class="text-xs text-gray-400">Day Streak</div>
                                </div>
                                <div class="p-4   rounded-lg" style="background: var(--bg-card);">
                                    <div class="text-2xl font-bold text-purple-400">
                                        <?php echo $progressStats['total_sessions'] > 0 ? round(($progressStats['this_month'] / date('j')) * 100) : 0; ?>%
                                    </div>
                                    <div class="text-xs text-gray-400">Month Goal</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tips Section -->
                        <div class="p-4 bg-green-500/10 border border-green-500/30 rounded-lg">
                            <h5 class="font-semibold text-green-400 mb-2 flex items-center gap-2">
                                <i data-lucide="lightbulb" class="w-4 h-4"></i>
                                Nutrition Tip
                            </h5>
                            <p class="text-green-300 text-sm">
                                <?php
                                if ($currentStreak >= 7) {
                                    echo "Excellent nutrition consistency! Your body is getting the fuel it needs for optimal performance.";
                                } elseif ($progressStats['this_week'] >= 5) {
                                    echo "Great week! Consistent nutrition is key to reaching your fitness goals.";
                                } elseif ($progressStats['this_week'] >= 3) {
                                    echo "Good progress! Try to follow your meal plan more consistently this week.";
                                } else {
                                    echo "Start building healthy eating habits! Follow your meal plan to see better results.";
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        
    </div>

    <!-- Mobile Bottom Navigation -->
    

    <noscript>
        
    </noscript>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize icons
            lucide.createIcons();

            // Mobile sidebar functionality
            const mobileSidebar = document.getElementById('mobileSidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const toggleSidebar = document.getElementById('toggleSidebar');
            const closeMobileSidebar = document.getElementById('closeMobileSidebar');
            const mobileMenuButton = document.getElementById('mobileMenuButton');
            const desktopSidebar = document.getElementById('desktopSidebar');

            function openMobileSidebar() {
                mobileSidebar.classList.add('open');
                mobileOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeMobileSidebarFunc() {
                mobileSidebar.classList.remove('open');
                mobileOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            // Smart toggle function that detects screen size
            function smartToggleSidebar() {
                if (window.innerWidth <= 768) {
                    // Mobile: Toggle mobile sidebar
                    if (mobileSidebar.classList.contains('open')) {
                        closeMobileSidebarFunc();
                    } else {
                        openMobileSidebar();
                    }
                } else {
                    // Desktop: Toggle desktop sidebar
                    if (desktopSidebar.classList.contains('w-60')) {
                        desktopSidebar.classList.remove('w-60');
                        desktopSidebar.classList.add('w-16', 'sidebar-collapsed');
                    } else {
                        desktopSidebar.classList.remove('w-16', 'sidebar-collapsed');
                        desktopSidebar.classList.add('w-60');
                    }
                }
            }

            // Event listeners
            if (toggleSidebar) {
                toggleSidebar.addEventListener('click', smartToggleSidebar);
            }
            
            if (mobileMenuButton) {
                mobileMenuButton.addEventListener('click', openMobileSidebar);
            }
            
            if (closeMobileSidebar) {
                closeMobileSidebar.addEventListener('click', closeMobileSidebarFunc);
            }
            
            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', closeMobileSidebarFunc);
            }

            // Workout submenu toggle (Desktop)
            const workoutToggle = document.getElementById('workoutToggle');
            const workoutSubmenu = document.getElementById('workoutSubmenu');
            const workoutChevron = document.getElementById('workoutChevron');
            
            if (workoutToggle) {
                workoutToggle.addEventListener('click', () => {
                    workoutSubmenu.classList.toggle('open');
                    workoutChevron.classList.toggle('rotate');
                });
            }

            // Nutrition submenu toggle (Desktop)
            const nutritionToggle = document.getElementById('nutritionToggle');
            const nutritionSubmenu = document.getElementById('nutritionSubmenu');
            const nutritionChevron = document.getElementById('nutritionChevron');
            
            if (nutritionToggle) {
                nutritionToggle.addEventListener('click', () => {
                    nutritionSubmenu.classList.toggle('open');
                    nutritionChevron.classList.toggle('rotate');
                });
            }

            // Mobile submenu toggles
            const mobileWorkoutToggle = document.getElementById('mobileWorkoutToggle');
            const mobileWorkoutSubmenu = document.getElementById('mobileWorkoutSubmenu');
            const mobileWorkoutChevron = document.getElementById('mobileWorkoutChevron');
            
            if (mobileWorkoutToggle) {
                mobileWorkoutToggle.addEventListener('click', () => {
                    mobileWorkoutSubmenu.classList.toggle('open');
                    mobileWorkoutChevron.classList.toggle('rotate');
                });
            }

            const mobileNutritionToggle = document.getElementById('mobileNutritionToggle');
            const mobileNutritionSubmenu = document.getElementById('mobileNutritionSubmenu');
            const mobileNutritionChevron = document.getElementById('mobileNutritionChevron');
            
            if (mobileNutritionToggle) {
                mobileNutritionToggle.addEventListener('click', () => {
                    mobileNutritionSubmenu.classList.toggle('open');
                    mobileNutritionChevron.classList.toggle('rotate');
                });
            }

            // Hover to open sidebar (for desktop collapsed state) - Only on desktop
            if (desktopSidebar && window.innerWidth > 768) {
                desktopSidebar.addEventListener('mouseenter', () => {
                    if (desktopSidebar.classList.contains('sidebar-collapsed')) {
                        desktopSidebar.classList.remove('w-16', 'sidebar-collapsed');
                        desktopSidebar.classList.add('w-60');
                    }
                });
                
                desktopSidebar.addEventListener('mouseleave', () => {
                    if (!desktopSidebar.classList.contains('sidebar-collapsed')) {
                        desktopSidebar.classList.remove('w-60');
                        desktopSidebar.classList.add('w-16', 'sidebar-collapsed');
                    }
                });
            }

            // Update mobile nav active state
            updateMobileNavActive();
        });

        // Update mobile navigation active state
        function updateMobileNavActive() {
            const currentPage = window.location.pathname.split('/').pop();
            const mobileNavItems = document.querySelectorAll('.mobile-nav-item');
            
            mobileNavItems.forEach(item => {
                item.classList.remove('active');
                // Simple page matching logic
                if (item.getAttribute('href') === currentPage) {
                    item.classList.add('active');
                }
            });
        }

        // Global function to close mobile sidebar (used in onclick events)
        function closeMobileSidebar() {
            const mobileSidebar = document.getElementById('mobileSidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            if (mobileSidebar) {
                mobileSidebar.classList.remove('open');
            }
            if (mobileOverlay) {
                mobileOverlay.classList.remove('active');
            }
            document.body.style.overflow = '';
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            const mobileSidebar = document.getElementById('mobileSidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            // Close mobile sidebar when resizing to desktop
            if (window.innerWidth > 768 && mobileSidebar && mobileSidebar.classList.contains('open')) {
                closeMobileSidebar();
            }

            // Update mobile nav on resize
            updateMobileNavActive();
        });
    </script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php $conn->close(); ?>
