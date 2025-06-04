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
$detail_id = isset($_POST['detail_id']) ? intval($_POST['detail_id']) : 0;
$completed = isset($_POST['completed']) ? intval($_POST['completed']) : 0;

if ($detail_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid detail ID']);
    exit();
}

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Check if the user owns this topic
    $check_stmt = $conn->prepare("
        SELECT d.detail_id 
        FROM plan_details d
        JOIN study_plans p ON d.plan_id = p.plan_id
        WHERE d.detail_id = ? AND p.user_id = ?
    ");
    $check_stmt->execute([$detail_id, $_SESSION['user_id']]);
    
    if ($check_stmt->rowCount() === 0) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'message' => 'Not authorized to update this topic']);
        exit();
    }
    
    // Update completion status
    $update_stmt = $conn->prepare("
        UPDATE plan_details 
        SET is_completed = ?, completed_at = ?
        WHERE detail_id = ?
    ");
    $completed_at = $completed ? date('Y-m-d H:i:s') : null;
    $update_stmt->execute([$completed, $completed_at, $detail_id]);
    
    // Update progress record
    $plan_id = $conn->query("
        SELECT plan_id FROM plan_details WHERE detail_id = $detail_id
    ")->fetchColumn();
    
    $progress_stmt = $conn->prepare("
        UPDATE progress 
        SET completed_subtopics = (
            SELECT COUNT(*) FROM plan_details 
            WHERE plan_id = ? AND is_completed = 1
        ),
        last_updated = CURRENT_TIMESTAMP
        WHERE plan_id = ? AND user_id = ?
    ");
    $progress_stmt->execute([$plan_id, $plan_id, $_SESSION['user_id']]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Progress updated successfully'
    ]);
    
} catch(PDOException $e) {
    $conn->rollBack();
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Error updating progress: ' . $e->getMessage()
    ]);
}
?>