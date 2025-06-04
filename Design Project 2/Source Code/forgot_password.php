<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $dob = $_POST['dob'] ?? '';
    
    // Validate inputs
    if (empty($email) || empty($dob)) {
        $error = "Please fill in all fields";
    } else {
        try {
            // Check if user exists with this email and DOB
            $stmt = $conn->prepare("SELECT user_id, email FROM users WHERE email = ? AND dob = ?");
            $stmt->execute([$email, $dob]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['reset_user_id'] = $user['user_id'];
                $_SESSION['reset_email'] = $user['email'];
                header("Location: reset_password.php");
                exit();
            } else {
                $error = "No account found with that email and date of birth";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | AI Study Planner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6e8efb, #a777e3);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #333;
        }
        .forgot-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 350px;
            padding: 40px;
            text-align: center;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #4a6cf7;
        }
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .input-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .input-group input:focus {
            border-color: #4a6cf7;
            outline: none;
        }
        .btn {
            background-color: #4a6cf7;
            color: white;
            border: none;
            padding: 12px 20px;
            width: 100%;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #3a5bd9;
        }
        .links {
            margin-top: 20px;
            text-align: center;
        }
        .links a {
            color: #4a6cf7;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        .links a:hover {
            color: #3a5bd9;
            text-decoration: underline;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .success {
            color: #2ecc71;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .instructions {
            background-color: #f8f9fa;
            border-left: 4px solid #4a6cf7;
            padding: 10px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="logo">AI Study Planner</div>
        <h2>Forgot Password</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="instructions">
            <p>Enter your email and date of birth to reset your password.</p>
        </div>
        
        <form action="forgot_password.php" method="POST">
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="input-group">
                <label for="dob">Date of Birth</label>
                <input type="date" id="dob" name="dob" required>
            </div>
            <button type="submit" class="btn">Reset Password</button>
            <div class="links">
                <a href="index.php">Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>