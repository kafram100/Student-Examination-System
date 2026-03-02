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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_exam_sessions_attempt ON exam_sessions_proctoring(exam_attempt_id);
CREATE INDEX idx_security_logs_attempt ON exam_security_logs(exam_attempt_id);
CREATE INDEX idx_security_logs_type ON exam_security_logs(activity_type);
CREATE INDEX idx_security_logs_timestamp ON exam_security_logs(timestamp);

-- View to summarize proctoring data for admins
CREATE VIEW proctoring_summary AS
SELECT 
    esp.id,
    esp.exam_attempt_id,
    u.username AS student_name,
    e.title AS exam_title,
    esp.start_time,
    esp.end_time,
    esp.suspicious_activity_count,
    esp.proctoring_status,
    COUNT(esl.id) AS total_security_events
FROM exam_sessions_proctoring esp
JOIN users u ON esp.student_id = u.id
JOIN exam_attempts ea ON esp.exam_attempt_id = ea.id
JOIN exams e ON ea.exam_id = e.id
LEFT JOIN exam_security_logs esl ON esp.exam_attempt_id = esl.exam_attempt_id
GROUP BY esp.id;