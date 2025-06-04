<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

// Get user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Handle form submission
    $error = '';
    $success = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $dob = trim($_POST['dob']);
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($name)) {
            $error = 'Name is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            // Check if email is already taken
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'Email already in use by another account';
            } else {
                // Update basic info
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, dob = ? WHERE user_id = ?");
                $stmt->execute([$name, $email, $dob, $_SESSION['user_id']]);
                
                // Update password if provided
                if (!empty($newPassword)) {
                    if (empty($currentPassword)) {
                        $error = 'Current password is required to change password';
                    } elseif (!password_verify($currentPassword, $user['password'])) {
                        $error = 'Current password is incorrect';
                    } elseif ($newPassword !== $confirmPassword) {
                        $error = 'New passwords do not match';
                    } elseif (strlen($newPassword) < 8) {
                        $error = 'Password must be at least 8 characters';
                    } else {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                        $success = 'Profile and password updated successfully';
                    }
                } else {
                    $success = 'Profile updated successfully';
                }
                
                // Update session
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
    
    // Get gamification data
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
    
    // Calculate gamification stats
    $completed_hours = (float)($progress['completed_hours'] ?? 0);
    $level = (int)floor($completed_hours / 10) + 1;
    $points = (int)round($completed_hours * 10);
    $level_progress = (int)round(fmod($completed_hours, 10) * 10);
    
    $gameData = [
        'level' => $level,
        'points' => $points,
        'level_progress' => $level_progress,
        'streak_days' => 0
    ];
    
} catch(PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | AI Study Planner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6cf7;
            --secondary-color: #6e8efb;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
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
        
        /* Sidebar styles */
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
            background: #dc3545;
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
        
        /* Profile specific styles */
        .profile-container {
            display: flex;
            gap: 30px;
        }
        
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin-right: 20px;
        }
        
        .profile-info h2 {
            margin: 0;
            color: var(--dark-color);
        }
        
        .profile-info p {
            margin: 5px 0 0;
            color: #777;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .stats-title {
            font-size: 14px;
            color: #777;
            margin-bottom: 10px;
        }
        
        .stats-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--dark-color);
            margin: 0;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #777;
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
        }
        
        /* Message styles - simple text instead of boxes */
        .message {
            margin-bottom: 20px;
            padding: 0;
        }
        
        .error-message {
            color: #dc3545;
        }
        
        .success-message {
            color: #28a745;
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
            
            .profile-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
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
                <a href="history.php" class="nav-item"><i class="fas fa-history"></i> Study History</a>
                <a href="profile.php" class="nav-item active"><i class="fas fa-user"></i> Profile</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1 class="page-title">Profile</h1>
                <div>
                    <form action="logout.php" method="POST">
                        <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
                    </form>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <p class="message error-message"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <p class="message success-message"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>
            
            <div class="profile-container">
                <div class="profile-card" style="flex: 2;">
                    <div class="profile-header">
                        <div class="profile-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                        <div class="profile-info">
                            <h2><?= htmlspecialchars($user['name']) ?></h2>
                            <p><?= htmlspecialchars($user['email']) ?></p>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob" value="<?= htmlspecialchars($user['dob']) ?>">
                        </div>
                        
                        <h3>Change Password</h3>
                        <p class="stats-title">Leave blank to keep current password</p>
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <div class="password-container">
                                <input type="password" id="current_password" name="current_password">
                                <span class="toggle-password" onclick="togglePassword('current_password')"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="password-container">
                                <input type="password" id="new_password" name="new_password">
                                <span class="toggle-password" onclick="togglePassword('new_password')"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="password-container">
                                <input type="password" id="confirm_password" name="confirm_password">
                                <span class="toggle-password" onclick="togglePassword('confirm_password')"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">Update Profile</button>
                    </form>
                </div>
                
                <div class="profile-card">
                    <h2>Your Stats</h2>
                    
                    <div class="stats-card">
                        <div class="stats-title">Current Level</div>
                        <p class="stats-value"><?= $gameData['level'] ?></p>
                        <div class="level-progress">
                            <div class="level-progress-bar" style="width: <?= $gameData['level_progress'] ?>%"></div>
                        </div>
                        <small><?= $gameData['level_progress'] ?>% to next level</small>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-title">Total Points</div>
                        <p class="stats-value"><?= $gameData['points'] ?></p>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-title">Current Streak</div>
                        <p class="stats-value"><?= $gameData['streak_days'] ?> days</p>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-title">Account Created</div>
                        <p class="stats-value"><?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>