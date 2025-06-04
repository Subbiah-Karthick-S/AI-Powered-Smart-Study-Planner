<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $plan_name = sanitizeInput($_POST['plan_name']);
    $total_hours = floatval($_POST['total_hours']);
    $subjects = $_POST['subjects'] ?? [];
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Save the main plan
        $stmt = $conn->prepare("INSERT INTO study_plans (user_id, plan_name, total_hours) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $plan_name, $total_hours]);
        $plan_id = $conn->lastInsertId();
        
        // Calculate total difficulty
        $total_difficulty = 0;
        foreach ($subjects as $subject) {
            if (!empty($subject['name'])) {
                $total_difficulty += intval($subject['difficulty']);
            }
        }
        
        // Save subjects and subtopics with calculated hours
        $subtopic_count = 0;
        foreach ($subjects as $subject) {
            if (!empty($subject['name'])) {
                $subject_difficulty = intval($subject['difficulty']);
                $subject_hours = ($subject_difficulty / $total_difficulty) * $total_hours;
                
                // Calculate subtopic total difficulty
                $subtopic_total_difficulty = 0;
                foreach ($subject['subtopics'] as $subtopic) {
                    if (!empty($subtopic['name'])) {
                        $subtopic_total_difficulty += intval($subtopic['difficulty']);
                    }
                }
                
                foreach ($subject['subtopics'] as $subtopic) {
                    if (!empty($subtopic['name'])) {
                        $subtopic_difficulty = intval($subtopic['difficulty']);
                        $subtopic_hours = ($subtopic_difficulty / $subtopic_total_difficulty) * $subject_hours;
                        
                        $detail_stmt = $conn->prepare("
                            INSERT INTO plan_details 
                            (plan_id, subject, subtopic, exam_date, difficulty, hours_allocated) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $detail_stmt->execute([
                            $plan_id,
                            $subject['name'],
                            $subtopic['name'],
                            $subject['exam_date'],
                            $subtopic_difficulty,
                            round($subtopic_hours, 2)
                        ]);
                        $subtopic_count++;
                    }
                }
            }
        }
        
        // Save progress tracking
        $progress_stmt = $conn->prepare("
            INSERT INTO progress 
            (user_id, plan_id, total_subtopics, completed_subtopics) 
            VALUES (?, ?, ?, 0)
        ");
        $progress_stmt->execute([
            $_SESSION['user_id'],
            $plan_id,
            $subtopic_count
        ]);
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Study plan saved successfully!";
        header("Location: dashboard.php");
        exit();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "Error saving plan: " . $e->getMessage();
    }
}

// Rest of your HTML and JavaScript remains the same...
// Helper function to count subtopics in a plan
function countPlanSubtopics($conn, $plan_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM plan_details WHERE plan_id = ?");
    $stmt->execute([$plan_id]);
    return $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($_GET['plan_id']) ? 'Edit' : 'Create' ?> Study Plan | AI Study Planner</title>
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
        
        .btn-danger {
            background: var(--danger-color);
        }
        
        .btn-danger:hover {
            background: #c82333;
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
        
        /* Planner specific styles */
        .planner-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
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
        
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .input-row {
            display: flex;
            gap: 15px;
        }
        
        .input-row .form-group {
            flex: 1;
        }
        
        /* Subject and subtopic styles */
        .subject-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .subject-title {
            font-size: 1.2rem;
            margin: 0;
            color: var(--dark-color);
        }
        
        .subtopic-list {
            margin-top: 15px;
        }
        
        .subtopic-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .subtopic-item input {
            flex: 1;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        /* Generated plan preview */
        #plan-preview {
            margin-top: 40px;
            display: none;
        }
        
        .generated-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .generated-table th, 
        .generated-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .generated-table th {
            background-color: var(--primary-color);
            color: white;
        }
        
        .generated-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
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
                <a href="planner.php" class="nav-item active"><i class="fas fa-calendar-alt"></i> Study Planner</a>
                <a href="history.php" class="nav-item"><i class="fas fa-history"></i> Study History</a>
                <a href="profile.php" class="nav-item"><i class="fas fa-user"></i> Profile</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1 class="page-title"><?= isset($_GET['plan_id']) ? 'Edit' : 'Create' ?> Study Plan</h1>
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
            
            <div class="planner-form">
                <form id="study-planner-form" method="POST">
                    <div class="input-row">
                        <div class="form-group">
                            <label for="plan_name">Plan Name</label>
                            <input type="text" id="plan_name" name="plan_name" value="<?= htmlspecialchars($plan_name ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="total_hours">Total Study Hours Available</label>
                            <input type="number" id="total_hours" name="total_hours" min="0" step="0.5" 
                                   value="<?= htmlspecialchars($total_hours ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div id="subjects-container">
                        <div class="subject-card" data-subject-id="0">
                            <div class="subject-header">
                                <h3 class="subject-title">Subject 1</h3>
                                <button type="button" class="btn btn-danger" onclick="removeSubject(this)">Remove Subject</button>
                            </div>
                            
                            <div class="input-row">
                                <div class="form-group">
                                    <label>Subject Name</label>
                                    <input type="text" name="subjects[0][name]" required>
                                </div>
                                <div class="form-group">
                                    <label>Exam Date</label>
                                    <input type="date" name="subjects[0][exam_date]" required>
                                </div>
                                <div class="form-group">
                                    <label>Difficulty (1-10)</label>
                                    <input type="number" min="1" max="10" name="subjects[0][difficulty]" required>
                                </div>
                            </div>
                            
                            <button type="button" class="btn" onclick="addSubtopic(this)">Add Subtopic</button>
                            
                            <div class="subtopic-list">
                                <div class="subtopic-item">
                                    <input type="text" name="subjects[0][subtopics][0][name]" placeholder="Subtopic Name" required>
                                    <input type="number" min="1" max="10" name="subjects[0][subtopics][0][difficulty]" placeholder="Difficulty" required>
                                    <button type="button" class="btn btn-danger" onclick="removeSubtopic(this)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="button" class="btn" onclick="addSubject()">Add Subject</button>
                        <div>
                            <button type="button" class="btn" onclick="generatePlan()">Generate Plan</button>
                            <button type="submit" class="btn">Save Plan</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Generated Plan Preview -->
            <div id="plan-preview">
                <h2>Generated Study Plan</h2>
                <table id="study-table" class="generated-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Subtopic</th>
                            <th>Exam Date</th>
                            <th>Days Left</th>
                            <th>Difficulty</th>
                            <th>Study Hours Allocated</th>
                        </tr>
                    </thead>
                    <tbody id="study-plan"></tbody>
                </table>
                <button type="button" class="btn" onclick="downloadPDF()" style="margin-top: 20px;">Download as PDF</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script>
        // Add new subject
        function addSubject() {
            const container = document.getElementById('subjects-container');
            const subjectId = Date.now(); // Temporary ID
            
            const subjectHTML = `
                <div class="subject-card" data-subject-id="${subjectId}">
                    <div class="subject-header">
                        <h3 class="subject-title">New Subject</h3>
                        <button type="button" class="btn btn-danger" onclick="removeSubject(this)">Remove Subject</button>
                    </div>
                    
                    <div class="input-row">
                        <div class="form-group">
                            <label>Subject Name</label>
                            <input type="text" name="subjects[${subjectId}][name]" required>
                        </div>
                        <div class="form-group">
                            <label>Exam Date</label>
                            <input type="date" name="subjects[${subjectId}][exam_date]" required>
                        </div>
                        <div class="form-group">
                            <label>Difficulty (1-10)</label>
                            <input type="number" min="1" max="10" name="subjects[${subjectId}][difficulty]" required>
                        </div>
                    </div>
                    
                    <button type="button" class="btn" onclick="addSubtopic(this)">Add Subtopic</button>
                    <div class="subtopic-list"></div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', subjectHTML);
        }
        
        // Add new subtopic
        function addSubtopic(button) {
            const subtopicList = button.nextElementSibling;
            const subjectId = button.closest('.subject-card').dataset.subjectId;
            const subtopicId = Date.now(); // Temporary ID
            
            const subtopicHTML = `
                <div class="subtopic-item">
                    <input type="text" name="subjects[${subjectId}][subtopics][${subtopicId}][name]" placeholder="Subtopic Name" required>
                    <input type="number" min="1" max="10" name="subjects[${subjectId}][subtopics][${subtopicId}][difficulty]" placeholder="Difficulty" required>
                    <button type="button" class="btn btn-danger" onclick="removeSubtopic(this)">Remove</button>
                </div>
            `;
            
            subtopicList.insertAdjacentHTML('beforeend', subtopicHTML);
        }
        
        // Remove subject
        function removeSubject(button) {
            if (confirm('Are you sure you want to remove this subject and all its subtopics?')) {
                button.closest('.subject-card').remove();
            }
        }
        
        // Remove subtopic
        function removeSubtopic(button) {
            button.closest('.subtopic-item').remove();
        }
        
        // Format hours to readable time
        function formatHoursToTime(hours) {
            const h = Math.floor(hours);
            const m = Math.round((hours - h) * 60);
            if (m === 60) {
                h += 1;
                m = 0;
            }
            return m === 0 ? `${h} hrs` : `${h} hrs ${m} mins`;
        }
        
        // Generate study plan
        function generatePlan() {
            const totalHours = parseFloat(document.getElementById('total_hours').value) || 0;
            const studyPlanTbody = document.getElementById('study-plan');
            studyPlanTbody.innerHTML = '';
            
            const today = new Date();
            const subjects = document.querySelectorAll('.subject-card');
            let totalDifficulty = 0;
            
            // Calculate total difficulty
            subjects.forEach(subject => {
                const difficulty = parseFloat(subject.querySelector('input[name$="[difficulty]"]').value);
                if (!isNaN(difficulty)) {
                    totalDifficulty += difficulty;
                }
            });
            
            if (totalDifficulty === 0) {
                alert('Please add at least one subject with valid difficulty');
                return;
            }
            
            // Process each subject
            subjects.forEach(subject => {
                const name = subject.querySelector('input[name$="[name]"]').value;
                const examDate = new Date(subject.querySelector('input[name$="[exam_date]"]').value);
                const difficulty = parseFloat(subject.querySelector('input[name$="[difficulty]"]').value);
                const daysLeft = Math.ceil((examDate - today) / (1000 * 60 * 60 * 24));
                
                if (!isNaN(difficulty)) {
                    const subtopics = subject.querySelectorAll('.subtopic-item');
                    let subtopicTotalDifficulty = 0;
                    
                    // Calculate subtopic total difficulty
                    subtopics.forEach(subtopic => {
                        const subtopicDifficulty = parseFloat(subtopic.querySelector('input[name$="[difficulty]"]').value);
                        if (!isNaN(subtopicDifficulty)) {
                            subtopicTotalDifficulty += subtopicDifficulty;
                        }
                    });
                    
                    if (subtopicTotalDifficulty > 0) {
                        const subjectAllocatedHours = (difficulty / totalDifficulty) * totalHours;
                        
                        subtopics.forEach(subtopic => {
                            const subtopicName = subtopic.querySelector('input[name$="[name]"]').value;
                            const subtopicDifficulty = parseFloat(subtopic.querySelector('input[name$="[difficulty]"]').value);
                            
                            if (!isNaN(subtopicDifficulty)) {
                                const subtopicAllocatedHours = (subtopicDifficulty / subtopicTotalDifficulty) * subjectAllocatedHours;
                                const timeString = formatHoursToTime(subtopicAllocatedHours);
                                
                                // Add to preview table
                                const row = `
                                    <tr>
                                        <td>${name}</td>
                                        <td>${subtopicName}</td>
                                        <td>${examDate.toISOString().split('T')[0]}</td>
                                        <td>${daysLeft}</td>
                                        <td>${subtopicDifficulty}</td>
                                        <td>${timeString}</td>
                                    </tr>
                                `;
                                studyPlanTbody.innerHTML += row;
                            }
                        });
                    }
                }
            });
            
            // Show the preview section
            document.getElementById('plan-preview').style.display = 'block';
        }
        
        // Download as PDF
        function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            const currentDate = new Date().toLocaleDateString();
            doc.setFontSize(18);
            doc.text("Study Plan", 80, 10);
            doc.setFontSize(12);
            doc.text(`Date: ${currentDate}`, 150, 15);
            doc.text(`Plan: ${document.getElementById('plan_name').value}`, 150, 20);
            
            const headers = [
                ["Subject", "Subtopic", "Exam Date", "Days Left", "Difficulty", "Hours Allocated"]
            ];
            
            const rows = [];
            document.querySelectorAll('#study-plan tr').forEach(row => {
                const cells = Array.from(row.cells).map(cell => cell.textContent);
                rows.push(cells);
            });
            
            doc.autoTable({
                head: headers,
                body: rows,
                startY: 30,
                theme: "grid",
                headStyles: { fillColor: [74, 108, 247] },
                styles: { fontSize: 10, cellPadding: 3 }
            });
            
            doc.save(`Study_Plan_${document.getElementById('plan_name').value}_${currentDate.replace(/\//g, '-')}.pdf`);
        }
        
        // Add initial subject if empty
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelectorAll('.subject-card').length === 0) {
                addSubject();
            }
        });
    </script>
</body>
</html>