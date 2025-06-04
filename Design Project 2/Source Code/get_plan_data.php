<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

header('Content-Type: application/json');

// Verify plan_id is provided and valid
if (!isset($_GET['plan_id']) || !is_numeric($_GET['plan_id'])) {
    echo json_encode(['error' => 'Invalid Plan ID']);
    exit;
}

$plan_id = intval($_GET['plan_id']);
$user_id = $_SESSION['user_id'];

try {
    // Verify the plan belongs to the current user
    $stmt = $conn->prepare("SELECT plan_id FROM study_plans WHERE plan_id = ? AND user_id = ?");
    $stmt->execute([$plan_id, $user_id]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['error' => 'Plan not found or access denied']);
        exit;
    }

    // Get basic plan info
    $stmt = $conn->prepare("
        SELECT 
            p.plan_id, 
            p.plan_name, 
            p.total_hours,
            COUNT(d.detail_id) as total_topics,
            SUM(CASE WHEN d.is_completed = 1 THEN 1 ELSE 0 END) as completed_topics
        FROM study_plans p
        LEFT JOIN plan_details d ON p.plan_id = d.plan_id
        WHERE p.plan_id = ?
        GROUP BY p.plan_id
    ");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        echo json_encode(['error' => 'Plan data not found']);
        exit;
    }

    // Get subject-wise data
    $subject_stmt = $conn->prepare("
        SELECT 
            subject,
            SUM(hours_allocated) as hours_allocated,
            AVG(difficulty) as avg_difficulty,
            COUNT(detail_id) as topic_count,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_topics
        FROM plan_details
        WHERE plan_id = ?
        GROUP BY subject
    ");
    $subject_stmt->execute([$plan_id]);
    $subjects = $subject_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'plan_name' => $plan['plan_name'],
        'total_hours' => floatval($plan['total_hours']),
        'total_topics' => intval($plan['total_topics']),
        'completed_topics' => intval($plan['completed_topics']),
        'subjects' => array_map(function($subject) {
            return [
                'subject' => $subject['subject'],
                'hours_allocated' => floatval($subject['hours_allocated']),
                'avg_difficulty' => floatval($subject['avg_difficulty']),
                'topic_count' => intval($subject['topic_count']),
                'completed_topics' => intval($subject['completed_topics'])
            ];
        }, $subjects)
    ];

    echo json_encode($response);

} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}