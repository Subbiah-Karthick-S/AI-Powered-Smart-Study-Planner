<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

// Verify plan_id is provided and valid
if (!isset($_GET['plan_id']) || !is_numeric($_GET['plan_id'])) {
    $_SESSION['error'] = 'Invalid Plan ID';
    header('Location: history.php');
    exit;
}

$plan_id = intval($_GET['plan_id']);
$user_id = $_SESSION['user_id'];

try {
    // Verify the plan belongs to the current user
    $stmt = $conn->prepare("SELECT plan_id, plan_name FROM study_plans WHERE plan_id = ? AND user_id = ?");
    $stmt->execute([$plan_id, $user_id]);
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['error'] = 'Plan not found or access denied';
        header('Location: history.php');
        exit;
    }

    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    $plan_name = htmlspecialchars($plan['plan_name']);
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error occurred';
    header('Location: history.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualize Plan | AI Study Planner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
        
        .back-btn {
            background: var(--warning-color);
            color: var(--dark-color);
        }
        
        .back-btn:hover {
            background: #e0a800;
        }
        
        /* Chart styles */
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .chart-title {
            text-align: center;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .chart-wrapper {
            height: 400px;
            margin-bottom: 20px;
        }
        
        .download-all-btn {
            background: var(--success-color);
            margin: 20px auto;
            display: block;
            width: 250px;
            text-align: center;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            
            .chart-wrapper {
                height: 300px;
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
                <h1 class="page-title">Visualizing: <?= $plan_name ?></h1>
                <div>
                    <a href="history.php" class="btn back-btn"><i class="fas fa-arrow-left"></i> Back to History</a>
                </div>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <!-- Completion Chart -->
            <div class="chart-container" id="completionChartContainer">
                <h2 class="chart-title">Topic Completion</h2>
                <div class="chart-wrapper">
                    <canvas id="completionChart"></canvas>
                </div>
            </div>
            
            <!-- Hours Allocation Chart -->
            <div class="chart-container" id="hoursChartContainer">
                <h2 class="chart-title">Hours Allocation by Subject</h2>
                <div class="chart-wrapper">
                    <canvas id="hoursChart"></canvas>
                </div>
            </div>
            
            <!-- Difficulty Distribution Chart -->
            <div class="chart-container" id="difficultyChartContainer">
                <h2 class="chart-title">Subject Difficulty (1-10)</h2>
                <div class="chart-wrapper">
                    <canvas id="difficultyChart"></canvas>
                </div>
            </div>
            
            <!-- Single Download Button for all charts -->
            <button class="btn download-all-btn" onclick="downloadAllCharts()">
                <i class="fas fa-file-pdf"></i> Download All Charts as PDF
            </button>
        </div>
    </div>
    
    <script>
    // Initialize jsPDF
    const { jsPDF } = window.jspdf;
    
    document.addEventListener('DOMContentLoaded', async function() {
        try {
            // Fetch plan data
            const response = await fetch(`get_plan_data.php?plan_id=<?= $plan_id ?>`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Verify we have the required data
            if (!data.subjects || !Array.isArray(data.subjects)) {
                throw new Error('Invalid data format received from server');
            }
            
            // Create charts with the fetched data
            createCharts(data);
        } catch (error) {
            console.error('Error:', error);
            alert('Error loading visualization: ' + error.message);
        }
    });
    
    // Function to create charts
    function createCharts(planData) {
        // Completion Chart (Pie)
        const completionCtx = document.getElementById('completionChart').getContext('2d');
        new Chart(completionCtx, {
            type: 'pie',
            data: {
                labels: ['Completed', 'Remaining'],
                datasets: [{
                    data: [
                        planData.completed_topics || 0,
                        (planData.total_topics || 0) - (planData.completed_topics || 0)
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#6c757d'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Topic Completion'
                    }
                }
            }
        });
        
        // Hours Allocation Chart (Bar)
        const hoursCtx = document.getElementById('hoursChart').getContext('2d');
        new Chart(hoursCtx, {
            type: 'bar',
            data: {
                labels: planData.subjects.map(s => s.subject),
                datasets: [{
                    label: 'Hours Allocated',
                    data: planData.subjects.map(s => parseFloat(s.hours_allocated) || 0),
                    backgroundColor: '#4a6cf7'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Hours Allocation by Subject'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
                    }
                }
            }
        });
        
        // Difficulty Distribution Chart (Line)
        const difficultyCtx = document.getElementById('difficultyChart').getContext('2d');
        new Chart(difficultyCtx, {
            type: 'line',
            data: {
                labels: planData.subjects.map(s => s.subject),
                datasets: [{
                    label: 'Difficulty Level',
                    data: planData.subjects.map(s => parseFloat(s.avg_difficulty) || 0),
                    backgroundColor: 'rgba(167, 119, 227, 0.2)',
                    borderColor: 'rgba(167, 119, 227, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Subject Difficulty (1-10)'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Difficulty: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        min: 1,
                        max: 10,
                        title: {
                            display: true,
                            text: 'Difficulty Level'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Subjects'
                        }
                    }
                }
            }
        });
    }
    
    // Function to download all charts as single PDF
    async function downloadAllCharts() {
        const doc = new jsPDF('p', 'mm', 'a4');
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = 10;
        
        // Add title
        doc.setFontSize(20);
        doc.text('Study Plan Visualization: <?= $plan_name ?>', pageWidth / 2, 15, { align: 'center' });
        doc.setFontSize(12);
        doc.text('Generated on: ' + new Date().toLocaleDateString(), pageWidth / 2, 22, { align: 'center' });
        
        let yPosition = 30;
        
        // Capture and add each chart
        const chartContainers = [
            'completionChartContainer',
            'hoursChartContainer',
            'difficultyChartContainer'
        ];
        
        for (const containerId of chartContainers) {
            const container = document.getElementById(containerId);
            
            // Create canvas image
            const canvas = await html2canvas(container, {
                scale: 2,
                logging: false,
                useCORS: true
            });
            
            const imgData = canvas.toDataURL('image/jpeg', 0.9);
            const imgWidth = pageWidth - margin * 2;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            
            // Add new page if needed
            if (yPosition + imgHeight > pageHeight - margin) {
                doc.addPage();
                yPosition = margin;
            }
            
            // Add image to PDF
            doc.addImage(imgData, 'JPEG', margin, yPosition, imgWidth, imgHeight);
            yPosition += imgHeight + 10;
        }
        
        // Save the PDF
        doc.save('<?= $plan_name ?>_Study_Visualization.pdf');
    }
    </script>
</body>
</html>