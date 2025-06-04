<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

// Get user data and study plans
try {
    // Get user's active plans
    $stmt = $conn->prepare("
        SELECT p.plan_id, p.plan_name, p.total_hours, p.created_at, 
               COUNT(d.detail_id) as total_topics,
               SUM(CASE WHEN d.is_completed = 1 THEN 1 ELSE 0 END) as completed_topics
        FROM study_plans p
        LEFT JOIN plan_details d ON p.plan_id = d.plan_id
        WHERE p.user_id = ?
        GROUP BY p.plan_id
        ORDER BY p.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $studyPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get progress stats
    $progress_stmt = $conn->prepare("
        SELECT 
            SUM(hours_allocated) as total_hours,
            SUM(CASE WHEN is_completed = 1 THEN hours_allocated ELSE 0 END) as completed_hours
        FROM plan_details d
        JOIN study_plans p ON d.plan_id = p.plan_id
        WHERE p.user_id = ?
    ");
    $progress_stmt->execute([$_SESSION['user_id']]);
    $progress = $progress_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate completion percentage
    $completionPercentage = ($progress['total_hours'] > 0) 
        ? round(($progress['completed_hours'] / $progress['total_hours']) * 100) 
        : 0;
    
    // Simple gamification data
    $gameData = [
        'level' => floor($progress['completed_hours'] / 10) + 1,
        'points' => $progress['completed_hours'] * 10,
        'streak_days' => 0
    ];
    
    // Motivational quotes
    $quotes = [
        "The secret of getting ahead is getting started.",
        "Don't watch the clock; do what it does. Keep going.",
        "Success is the sum of small efforts, repeated day in and day out.",
        "The expert in anything was once a beginner.",
        "You don't have to be great to start, but you have to start to be great."
    ];
    $motivationalQuote = $quotes[array_rand($quotes)];
    
} catch(PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | AI Study Planner</title>
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
        
        /* Sidebar styles (matching planner.php) */
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
            width: <?= ($gameData['points'] % 100) ?>%;
        }
        
        .quote-text {
            font-style: italic;
            color: #555;
        }
        
        /* Study plans */
        .plans-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: var(--dark-color);
        }
        
        .plans-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .plan-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        
        .plan-card:hover {
            transform: translateY(-5px);
        }
        
        .plan-title {
            font-size: 1.2rem;
            margin: 0 0 10px;
            color: var(--dark-color);
        }
        
        .plan-meta {
            font-size: 14px;
            color: #777;
            margin-bottom: 15px;
        }
        
        .progress-container {
            margin-bottom: 15px;
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .progress-bar {
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--success-color);
            width: <?= $completionPercentage ?>%;
        }
        
        .plan-actions {
            display: flex;
            justify-content: space-between;
        }
        
        .btn-sm {
            padding: 6px 12px;
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

        /* Delete button styles */
        .btn-delete {
            background: transparent;
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-delete:hover {
            background: #f8d7da;
        }
        
        /* Alert styles (matching planner.php) */
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
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar (matching planner.php) -->
        <div class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">AI Study Planner</a>
                <div class="user-profile">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                </div>
            </div>
            
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="planner.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Study Planner</a>
                <a href="history.php" class="nav-item"><i class="fas fa-history"></i> Study History</a>
                <a href="profile.php" class="nav-item"><i class="fas fa-user"></i> Profile</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1 class="page-title">Dashboard</h1>
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
                    <p class="stat-value"><?= $gameData['level'] ?></p>
                    <div class="level-progress">
                        <div class="level-progress-bar"></div>
                    </div>
                </div>
                
                <div class="stat-card points-card">
                    <div class="stat-title">Points</div>
                    <p class="stat-value"><?= $gameData['points'] ?></p>
                    <small>Next level at <?= ($gameData['level'] * 100) ?> points</small>
                </div>
                
                <div class="stat-card streak-card">
                    <div class="stat-title">Current Streak</div>
                    <p class="stat-value"><?= $gameData['streak_days'] ?> days</p>
                    <small>Keep it up!</small>
                </div>
                
                <div class="stat-card quote-card">
                    <div class="stat-title">Motivational Quote</div>
                    <p class="quote-text">"<?= $motivationalQuote ?>"</p>
                </div>
            </div>
            
            <!-- Study Plans -->
            <div class="plans-section">
                <div class="plans-header">
                    <h2 class="section-title">Your Study Plans</h2>
                    <a href="planner.php" class="btn"><i class="fas fa-plus"></i> Create New Plan</a>
                </div>
                
                <div class="plans-container">
                    <?php if (empty($studyPlans)): ?>
                        <div class="plan-card">
                            <p>You don't have any study plans yet. Create your first plan to get started!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($studyPlans as $plan): 
                            $progress = ($plan['total_topics'] > 0) 
                                ? round(($plan['completed_topics'] / $plan['total_topics']) * 100) 
                                : 0;
                        ?>
                            <div class="plan-card">
                                <h3 class="plan-title"><?= htmlspecialchars($plan['plan_name']) ?></h3>
                                <div class="plan-meta">
                                    Created on <?= date('M d, Y', strtotime($plan['created_at'])) ?> | 
                                    <?= $plan['total_hours'] ?> study hours
                                </div>
                                
                                <div class="progress-container">
                                    <div class="progress-text">
                                        <span>Progress</span>
                                        <span><?= $plan['completed_topics'] ?> of <?= $plan['total_topics'] ?> completed</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                                    </div>
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
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>