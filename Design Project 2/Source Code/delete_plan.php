<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

// Check if the request is POST and has plan_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'])) {
    try {
        // Begin transaction for atomic operations
        $conn->beginTransaction();

        // 1. Verify the plan belongs to the current user
        $verify_stmt = $conn->prepare("
            SELECT user_id FROM study_plans 
            WHERE plan_id = ? AND user_id = ?
        ");
        $verify_stmt->execute([$_POST['plan_id'], $_SESSION['user_id']]);
        $plan = $verify_stmt->fetch();

        if (!$plan) {
            $_SESSION['error'] = "Plan not found or you don't have permission to delete it";
            $conn->rollBack();
            header("Location: dashboard.php");
            exit();
        }

        // 2. Get progress data before deletion for updating stats
        $progress_stmt = $conn->prepare("
            SELECT total_subtopics, completed_subtopics 
            FROM progress 
            WHERE plan_id = ? AND user_id = ?
        ");
        $progress_stmt->execute([$_POST['plan_id'], $_SESSION['user_id']]);
        $progress_data = $progress_stmt->fetch();

        // 3. Delete the plan (cascade will handle plan_details and progress)
        $delete_stmt = $conn->prepare("DELETE FROM study_plans WHERE plan_id = ?");
        $delete_stmt->execute([$_POST['plan_id']]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success'] = "Study plan deleted successfully";
        
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting plan: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "Invalid request";
}

header("Location: dashboard.php");
exit();
?>