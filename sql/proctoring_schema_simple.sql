-- Database schema for proctoring system

-- Table for storing proctoring session information
CREATE TABLE IF NOT EXISTS exam_sessions_proctoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_attempt_id INT NOT NULL,
    student_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    video_recording_path VARCHAR(255),
    audio_recording_path VARCHAR(255),
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    suspicious_activity_count INT DEFAULT 0,
    proctoring_status ENUM('active', 'completed', 'flagged', 'violated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for logging security events during exams
CREATE TABLE IF NOT EXISTS exam_security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    activity_type VARCHAR(100) NOT NULL, -- tab_switch, window_blur, print_attempt, etc.
    description TEXT NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    ip_address VARCHAR(45),
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    reviewed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_exam_sessions_attempt ON exam_sessions_proctoring(exam_attempt_id);
CREATE INDEX IF NOT EXISTS idx_security_logs_attempt ON exam_security_logs(exam_attempt_id);
CREATE INDEX IF NOT EXISTS idx_security_logs_type ON exam_security_logs(activity_type);
CREATE INDEX IF NOT EXISTS idx_security_logs_timestamp ON exam_security_logs(timestamp);