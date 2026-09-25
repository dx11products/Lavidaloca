USE lavidaloca;

CREATE TABLE IF NOT EXISTS cron_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_name VARCHAR(64) NOT NULL,
    message VARCHAR(255),
    affected INT DEFAULT 0,
    ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (ran_at)
);