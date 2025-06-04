<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

// Get all user's study plans with progress data
try {
    $stmt = $conn->prepare("
        SELECT 
            p.plan_id, 
            p.plan_name, 
            p.created_at,
            p.total_hours,
            COUNT(d.detail_id) as total_topics,
            SUM(CASE WHEN d.is_completed = 1 THEN 1 ELSE 0 END) as completed_topics,
            MAX(d.completed_at) as last_studied
        FROM study_plans p
        LEFT JOIN plan_details d ON p.plan_id = d.plan_id
        WHERE p.user_id = ?
        GROUP BY p.plan_id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error fetching plans: " . $e->getMessage();
}

// Get achievement data for gamification
try {
    $achievement_stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT p.plan_id) as total_plans,
            SUM(CASE WHEN d.is_completed = 1 THEN 1 ELSE 0 END) as total_completed_topics,
            COUNT(d.detail_id) as total_topics,
            MAX(p.created_at) as last_created_plan,
            MIN(d.completed_at) as first_completion,
            MAX(d.completed_at) as last_completion
        FROM study_plans p
        LEFT JOIN plan_details d ON p.plan_id = d.plan_id
        WHERE p.user_id = ?
    ");
    $achievement_stmt->execute([$_SESSION['user_id']]);
    $achievements = $achievement_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate study streak
    $streak_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT DATE(completed_at)) as current_streak
        FROM plan_details d
        JOIN study_plans p ON d.plan_id = p.plan_id
        WHERE p.user_id = ?
        AND d.is_completed = 1
        AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(completed_at)
        ORDER BY DATE(completed_at) DESC
        LIMIT 1
    ");
    $streak_stmt->execute([$_SESSION['user_id']]);
    $streak = $streak_stmt->fetch(PDO::FETCH_ASSOC);
    $current_streak = $streak ? $streak['current_streak'] : 0;
    
    // Calculate level based on completed topics (10 topics = 1 level)
    $level = floor(($achievements['total_completed_topics'] ?? 0) / 10);
    $level_progress = ($achievements['total_completed_topics'] ?? 0) % 10;
    
} catch(PDOException $e) {
    $error = "Error fetching achievement data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study History | AI Study Planner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6cf7;
            --secondary-color: #6e8efb;
            --accent-color: #a777e3;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7ff;
            margin: 0;
            padding: 0;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar styles (matching dashboard.php) */
        .sidebar {
            width: 250px;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px 0;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
            display: block;
            margin-bottom: 20px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            display: block;
            padding: 12px 20px;
            color: var(--dark-color);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-item:hover, .nav-item.active {
            background: #f0f4ff;
            color: var(--primary-color);
            border-left: 3px solid var(--primary-color);
        }
        
        .nav-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main content styles */
        .main-content {
            flex: 1;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 1.8rem;
            color: var(--dark-color);
            margin: 0;
        }
        
        .btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #3a5bd9;
        }
        
        .logout-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        /* Stats cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.level-card {
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-card.points-card {
            border-top: 4px solid var(--accent-color);
        }
        
        .stat-card.streak-card {
            border-top: 4px solid var(--success-color);
        }
        
        .stat-card.quote-card {
            border-top: 4px solid var(--warning-color);
            grid-column: span 2;
        }
        
        .stat-title {
            font-size: 14px;
            color: #777;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark-color);
            margin: 0;
        }
        
        .level-progress {
            height: 8px;
            background: #eee;
            border-radius: 4px;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .level-progress-bar {
            height: 100%;
            background: var(--primary-color);
            width: <?= ($level_progress / 10) * 100 ?>%;
        }
        
        /* Plans Section */
        .plans-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: var(--dark-color);
            margin-bottom: 20px;
        }
        
        .plans-list {
            display: grid;
            gap: 20px;
        }
        
        .plan-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .plan-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .plan-title {
            font-size: 1.2rem;
            margin: 0;
            color: var(--primary-color);
        }
        
        .plan-date {
            font-size: 14px;
            color: #777;
        }
        
        .plan-stats {
            display: flex;
            gap: 30px;
            margin: 15px 0;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .stat-label {
            font-size: 14px;
            color: #777;
        }
        
        .progress-container {
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            margin: 15px 0;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }
        
        .plan-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background: #f0f4ff;
        }
        
        .btn-secondary {
            background: var(--accent-color);
        }
        
        .btn-secondary:hover {
            background: #8e5ecf;
        }
        
        /* Delete button styles */
        .btn-delete {
            background: transparent;
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
            padding: 8px 12px;
            font-size: 14px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-delete:hover {
            background: #f8d7da;
        }
        
        .last-studied {
            font-size: 14px;
            color: #777;
            margin-top: 10px;
            text-align: right;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-icon {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #777;
            margin-bottom: 20px;
        }
        
        /* Alert styles */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            
            .stat-card.quote-card {
                grid-column: span 1;
            }
            
            .plan-stats {
                flex-direction: column;
                gap: 15px;
            }
            
            .plan-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar (matching dashboard.php) -->
        <div class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">AI Study Planner</a>
                <div class="user-profile">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                </div>
            </div>
            
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
                <a href="planner.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Study Planner</a>
                <a href="history.php" class="nav-item active"><i class="fas fa-history"></i> Study History</a>
                <a href="profile.php" class="nav-item"><i class="fas fa-user"></i> Profile</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1 class="page-title">Study History</h1>
                <div>
                    <form action="logout.php" method="POST">
                        <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
                    </form>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card level-card">
                    <div class="stat-title">Your Level</div>
                    <p class="stat-value"><?= $level ?></p>
                    <div class="level-progress">
                        <div class="level-progress-bar"></div>
                    </div>
                    <small>Progress to next level: <?= $level_progress ?>/10</small>
                </div>
                
                <div class="stat-card points-card">
                    <div class="stat-title">Topics Mastered</div>
                    <p class="stat-value"><?= $achievements['total_completed_topics'] ?? 0 ?></p>
                    <small>Out of <?= $achievements['total_topics'] ?? 0 ?> total topics</small>
                </div>
                
                <div class="stat-card streak-card">
                    <div class="stat-title">Current Streak</div>
                    <p class="stat-value"><?= $current_streak ?> days</p>
                    <small>Keep it up!</small>
                </div>
                
                <div class="stat-card quote-card">
                    <div class="stat-title">Achievement Badges</div>
                    <div style="display: flex; gap: 15px;">
                        <?php if (($achievements['total_completed_topics'] ?? 0) >= 5): ?>
                            <span title="Completed 5 topics" style="font-size: 1.5rem;">🎯</span>
                        <?php endif; ?>
                        
                        <?php if (($achievements['total_completed_topics'] ?? 0) >= 20): ?>
                            <span title="Completed 20 topics" style="font-size: 1.5rem;">🏆</span>
                        <?php endif; ?>
                        
                        <?php if ($current_streak >= 3): ?>
                            <span title="3-day streak" style="font-size: 1.5rem;">🔥</span>
                        <?php endif; ?>
                        
                        <?php if (($achievements['total_plans'] ?? 0) >= 3): ?>
                            <span title="Created 3 plans" style="font-size: 1.5rem;">📚</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Plans List -->
            <div class="plans-section">
                <h2 class="section-title">Your Study Plans</h2>
                
                <?php if (!empty($plans)): ?>
                    <div class="plans-list">
                        <?php foreach ($plans as $plan): 
                            $progress = ($plan['total_topics'] > 0) 
                                ? round(($plan['completed_topics'] / $plan['total_topics']) * 100) 
                                : 0;
                        ?>
                            <div class="plan-card">
                                <div class="plan-header">
                                    <h3 class="plan-title"><?= htmlspecialchars($plan['plan_name']) ?></h3>
                                    <span class="plan-date">Created: <?= date('M j, Y', strtotime($plan['created_at'])) ?></span>
                                </div>
                                
                                <div class="plan-stats">
                                    <div class="stat">
                                        <div class="stat-number"><?= $plan['completed_topics'] ?>/<?= $plan['total_topics'] ?></div>
                                        <div class="stat-label">Topics Completed</div>
                                    </div>
                                    
                                    <div class="stat">
                                        <div class="stat-number"><?= $plan['total_hours'] ?> hrs</div>
                                        <div class="stat-label">Total Study Time</div>
                                    </div>
                                    
                                    <div class="stat">
                                        <div class="stat-number"><?= $progress ?>%</div>
                                        <div class="stat-label">Completion</div>
                                    </div>
                                </div>
                                
                                <div class="progress-container">
                                    <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                                </div>
                                
                                <div class="plan-actions">
                                    <form action="delete_plan.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="plan_id" value="<?= $plan['plan_id'] ?>">
                                        <button type="submit" class="btn-delete" 
                                                onclick="return confirm('Are you sure you want to delete this plan?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                    <a href="view_plan.php?id=<?= $plan['plan_id'] ?>" class="btn btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <a href="visualize_plan.php?plan_id=<?= $plan['plan_id'] ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-chart-bar"></i> Visualize
                                    </a>
                                </div>
                                
                                <?php if ($plan['last_studied']): ?>
                                    <div class="last-studied">
                                        Last studied: <?= date('M j, Y', strtotime($plan['last_studied'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-book-open"></i></div>
                        <h3>No Study Plans Yet</h3>
                        <p>You haven't created any study plans. Start by creating your first plan!</p>
                        <a href="planner.php" class="btn"><i class="fas fa-plus"></i> Create New Plan</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>