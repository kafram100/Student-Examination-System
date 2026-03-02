-- Database schema for examination activity monitoring

-- Table for storing detailed activity logs during exams
CREATE TABLE IF NOT EXISTS exam_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL, -- 'tab_switch', 'window_blur', 'screenshot_attempt', 'copy_attempt', 'paste_attempt', 'print_attempt', 'dev_tools', 'multiple_device', 'other'
    description TEXT,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    additional_data JSON,
    reviewed BOOLEAN DEFAULT FALSE,
    reviewed_by INT NULL,
    review_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Table for storing examination summary reports
CREATE TABLE IF NOT EXISTS exam_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    report_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_attempts INT DEFAULT 0,
    flagged_attempts INT DEFAULT 0,
    high_risk_attempts INT DEFAULT 0,
    average_severity DECIMAL(3,2) DEFAULT 0.00,
    summary_report TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table for storing detected cheating incidents
CREATE TABLE IF NOT EXISTS cheating_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    incident_type VARCHAR(50) NOT NULL, -- 'multiple_device', 'screen_capture', 'tab_switching', 'suspicious_movement', 'audio_anomaly'
    evidence_path VARCHAR(255),
    confidence_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'reviewed', 'confirmed', 'dismissed') DEFAULT 'pending',
    incident_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Indexes for performance
CREATE INDEX idx_activity_logs_attempt ON exam_activity_logs(exam_attempt_id);
CREATE INDEX idx_activity_logs_type ON exam_activity_logs(activity_type);
CREATE INDEX idx_activity_logs_timestamp ON exam_activity_logs(timestamp);
CREATE INDEX idx_activity_logs_severity ON exam_activity_logs(severity);

CREATE INDEX idx_reports_exam ON exam_reports(exam_id);
CREATE INDEX idx_reports_lecturer ON exam_reports(lecturer_id);

CREATE INDEX idx_incidents_attempt ON cheating_incidents(exam_attempt_id);
CREATE INDEX idx_incidents_type ON cheating_incidents(incident_type);
CREATE INDEX idx_incidents_status ON cheating_incidents(status);