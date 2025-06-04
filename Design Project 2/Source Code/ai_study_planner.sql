-- sql code:
-- Database: ai_study_planner

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    dob DATE,
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Study plans table
CREATE TABLE study_plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    total_hours DECIMAL(10,2) NOT NULL, -- Changed from VARCHAR to DECIMAL
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Plan details table
CREATE TABLE plan_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    subtopic VARCHAR(100) NOT NULL,
    exam_date DATE NOT NULL,
    difficulty INT NOT NULL,
    hours_allocated DECIMAL(10,2) NOT NULL, -- Changed from VARCHAR to DECIMAL
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (plan_id) REFERENCES study_plans(plan_id) ON DELETE CASCADE
);

-- Progress tracking table
CREATE TABLE progress (
    progress_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    total_subtopics INT NOT NULL,
    completed_subtopics INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES study_plans(plan_id) ON DELETE CASCADE
);


