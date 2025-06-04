<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

// Get plan ID from URL
$plan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($plan_id <= 0) {
    header('Location: history.php');
    exit();
}

// Get plan details
try {
    // Get basic plan info
    $plan_stmt = $conn->prepare("
        SELECT p.plan_id, p.plan_name, p.total_hours, p.created_at,
               COUNT(d.detail_id) as total_topics,
               SUM(CASE WHEN d.is_completed = 1 THEN 1 ELSE 0 END) as completed_topics
        FROM study_plans p
        LEFT JOIN plan_details d ON p.plan_id = d.plan_id
        WHERE p.plan_id = ? AND p.user_id = ?
        GROUP BY p.plan_id
    ");
    $plan_stmt->execute([$plan_id, $_SESSION['user_id']]);
    $plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        $_SESSION['error'] = "Plan not found or you don't have permission to view it.";
        header('Location: history.php');
        exit();
    }

    // Get all topics for this plan
    $topics_stmt = $conn->prepare("
        SELECT d.detail_id, d.subject, d.subtopic, d.exam_date, 
               d.difficulty, d.hours_allocated, d.is_completed, d.completed_at
        FROM plan_details d
        WHERE d.plan_id = ?
        ORDER BY d.subject, d.subtopic
    ");
    $topics_stmt->execute([$plan_id]);
    $topics = $topics_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group topics by subject
    $subjects = [];
    foreach ($topics as $topic) {
        $subject = $topic['subject'];
        if (!isset($subjects[$subject])) {
            $subjects[$subject] = [
                'total_topics' => 0,
                'completed_topics' => 0,
                'total_hours' => 0,
                'topics' => []
            ];
        }
        
        $subjects[$subject]['total_topics']++;
        if ($topic['is_completed']) {
            $subjects[$subject]['completed_topics']++;
        }
        
        $subjects[$subject]['total_hours'] += $topic['hours_allocated'];
        $subjects[$subject]['topics'][] = $topic;
    }

} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching plan details: " . $e->getMessage();
    header('Location: history.php');
    exit();
}

// Check if we're coming from a completion celebration
$celebrate = isset($_GET['celebrate']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($plan['plan_name']) ?> | AI Study Planner</title>
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
        
        /* Sidebar styles (matching history.php) */
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
        
        /* Plan Header */
        .plan-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-radius: 12px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .plan-header h1 {
            margin: 0 0 15px 0;
            font-size: 1.8rem;
        }
        
        .plan-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .plan-meta span {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .progress-container {
            height: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: white;
            width: <?= ($plan['total_topics'] > 0) ? round(($plan['completed_topics'] / $plan['total_topics']) * 100) : 0 ?>%;
        }
        
        /* Celebration Banner */
        .celebration-banner {
            background: var(--success-color);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        /* Subjects Container */
        .subjects-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            font-size: 1.5rem;
            color: var(--dark-color);
            margin-bottom: 20px;
        }
        
        /* Subject Card */
        .subject-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .subject-header h2 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.3rem;
        }
        
        .subject-stats {
            display: flex;
            gap: 15px;
        }
        
        .subject-stats span {
            background: #f0f4ff;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            color: var(--primary-color);
        }
        
        .subject-progress {
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            margin: 15px 0;
            overflow: hidden;
        }
        
        .subject-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            width: <?= ($subjectData['total_topics'] > 0) ? round(($subjectData['completed_topics'] / $subjectData['total_topics']) * 100) : 0 ?>%;
        }
        
        /* Topics List */
        .topics-list {
            margin-top: 20px;
        }
        
        .topic-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .topic-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .topic-item.completed {
            background: #f5fff7;
            border-color: var(--success-color);
        }
        
        .topic-info h3 {
            margin: 0 0 10px 0;
            color: var(--dark-color);
            font-size: 1.1rem;
        }
        
        .topic-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 14px;
            color: #777;
        }
        
        .completed-date {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .topic-actions {
            margin-top: 15px;
        }
        
        .complete-btn {
            background: var(--success-color);
            color: white;
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .complete-btn:hover {
            background: #218838;
        }
        
        .undo-btn {
            background: var(--danger-color);
            color: white;
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .undo-btn:hover {
            background: #c82333;
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
            
            .plan-meta, .subject-stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .topic-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .subject-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar (matching history.php) -->
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
                <h1 class="page-title"><?= htmlspecialchars($plan['plan_name']) ?></h1>
                <div>
                    <form action="logout.php" method="POST">
                        <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
                    </form>
                </div>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <!-- Plan Header -->
            <div class="plan-header">
                <h1><?= htmlspecialchars($plan['plan_name']) ?></h1>
                <div class="plan-meta">
                    <span>Created: <?= date('F j, Y', strtotime($plan['created_at'])) ?></span>
                    <span>Total Hours: <?= $plan['total_hours'] ?> hrs</span>
                    <span>Progress: <?= $plan['completed_topics'] ?>/<?= $plan['total_topics'] ?> topics</span>
                </div>
                
                <div class="progress-container">
                    <div class="progress-bar"></div>
                </div>
            </div>
            
            <?php if ($celebrate): ?>
                <div class="celebration-banner">
                    🎉 Congratulations! You completed a topic! Keep up the good work!
                </div>
            <?php endif; ?>
            
            <!-- Subjects Container -->
            <div class="subjects-container">
                <h2 class="section-title">Plan Details</h2>
                
                <?php foreach ($subjects as $subject => $subjectData): ?>
                    <div class="subject-card">
                        <div class="subject-header">
                            <h2><?= htmlspecialchars($subject) ?></h2>
                            <div class="subject-stats">
                                <span><?= $subjectData['completed_topics'] ?>/<?= $subjectData['total_topics'] ?> completed</span>
                                <span><?= number_format($subjectData['total_hours'], 1) ?> hrs</span>
                            </div>
                        </div>
                        
                        <div class="subject-progress">
                            <div class="subject-progress-bar"></div>
                        </div>
                        
                        <div class="topics-list">
                            <?php foreach ($subjectData['topics'] as $topic): ?>
                                <div class="topic-item <?= $topic['is_completed'] ? 'completed' : '' ?>">
                                    <div class="topic-info">
                                        <h3><?= htmlspecialchars($topic['subtopic']) ?></h3>
                                        <div class="topic-meta">
                                            <span>Difficulty: <?= $topic['difficulty'] ?>/10</span>
                                            <span>Time: <?= $topic['hours_allocated'] ?> hrs</span>
                                            <span>Exam Date: <?= date('M j, Y', strtotime($topic['exam_date'])) ?></span>
                                            <?php if ($topic['is_completed']): ?>
                                                <span class="completed-date">Completed: <?= date('M j, Y', strtotime($topic['completed_at'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="topic-actions">
                                        <?php if (!$topic['is_completed']): ?>
                                            <button class="btn complete-btn" onclick="markAsCompleted(<?= $topic['detail_id'] ?>)">
                                                <i class="fas fa-check"></i> Mark as Completed
                                            </button>
                                        <?php else: ?>
                                            <button class="btn undo-btn" onclick="markAsIncomplete(<?= $topic['detail_id'] ?>)">
                                                <i class="fas fa-undo"></i> Undo Completion
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/js-confetti@latest/dist/js-confetti.browser.js"></script>
    <script>
        // Initialize JSConfetti
        const jsConfetti = new JSConfetti();
        
        // Show celebration if needed
        <?php if ($celebrate): ?>
            celebrateCompletion();
        <?php endif; ?>
        
        function celebrateCompletion() {
            jsConfetti.addConfetti({
                emojis: ['🎓', '📚', '🧠', '🏆', '✨'],
                emojiSize: 50,
                confettiNumber: 30,
            });
        }
        
        // Mark topic as completed
        function markAsCompleted(detailId) {
            fetch('update_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `detail_id=${detailId}&completed=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload with celebration
                    window.location.href = `view_plan.php?id=<?= $plan_id ?>&celebrate=1`;
                } else {
                    alert('Error: ' + (data.message || 'Failed to update'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }
        
        // Mark topic as incomplete
        function markAsIncomplete(detailId) {
            fetch('update_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `detail_id=${detailId}&completed=0`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }
    </script>
</body>
</html>