<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['plan_name']) || !isset($input['total_hours']) || !isset($input['subjects'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

// Sanitize inputs
$plan_name = sanitizeInput($input['plan_name']);
$total_hours = sanitizeInput($input['total_hours']);
$subjects = $input['subjects'];

// Validate inputs
if (empty($plan_name) {
    echo json_encode(['success' => false, 'message' => 'Plan name is required']);
    exit();
}

if (!is_numeric($total_hours) || $total_hours <= 0) {
    echo json_encode(['success' => false, 'message' => 'Total hours must be a positive number']);
    exit();
}

if (count($subjects) === 0) {
    echo json_encode(['success' => false, 'message' => 'At least one subject is required']);
    exit();
}

// Begin transaction
$conn->beginTransaction();

try {
    // Save the main plan
    $stmt = $conn->prepare("INSERT INTO study_plans (user_id, plan_name, total_hours) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $plan_name, $total_hours]);
    $plan_id = $conn->lastInsertId();
    
    // Save subjects and subtopics
    foreach ($subjects as $subject) {
        $subject_name = sanitizeInput($subject['name']);
        $exam_date = sanitizeInput($subject['exam_date']);
        
        if (empty($subject_name)) continue;
        
        foreach ($subject['subtopics'] as $subtopic) {
            $subtopic_name = sanitizeInput($subtopic['name']);
            $difficulty = intval($subtopic['difficulty']);
            $hours_allocated = sanitizeInput($subtopic['hours_allocated']);
            
            if (empty($subtopic_name)) continue;
            
            // Validate difficulty
            if ($difficulty < 1 || $difficulty > 10) {
                $difficulty = 5; // Default value
            }
            
            $detail_stmt = $conn->prepare("
                INSERT INTO plan_details 
                (plan_id, subject, subtopic, exam_date, difficulty, hours_allocated) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $detail_stmt->execute([
                $plan_id,
                $subject_name,
                $subtopic_name,
                $exam_date,
                $difficulty,
                $hours_allocated
            ]);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Create progress record
    $progress_stmt = $conn->prepare("
        INSERT INTO progress 
        (user_id, plan_id, total_subtopics, completed_subtopics) 
        VALUES (?, ?, ?, 0)
    ");
    $progress_stmt->execute([
        $_SESSION['user_id'],
        $plan_id,
        $conn->query("SELECT COUNT(*) FROM plan_details WHERE plan_id = $plan_id")->fetchColumn()
    ]);
    
    echo json_encode([
        'success' => true,
        'plan_id' => $plan_id,
        'message' => 'Study plan saved successfully!'
    ]);
    
} catch(PDOException $e) {
    $conn->rollBack();
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Error saving plan: ' . $e->getMessage()
    ]);
}
?>