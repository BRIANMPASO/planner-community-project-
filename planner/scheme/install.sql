-- Database: planner_db

CREATE DATABASE IF NOT EXISTS planner_db;
USE planner_db;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    is_admin BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    daily_activity_count INT DEFAULT 0,
    last_activity_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- Tasks table
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    task_text VARCHAR(255) NOT NULL,
    task_day ENUM('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY') NOT NULL,
    category VARCHAR(50),
    priority ENUM('low','medium','high') DEFAULT 'medium',
    status ENUM('active','completed','expired','archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    expiry_date DATE NOT NULL,
    renewal_count INT DEFAULT 0,
    max_renewals INT DEFAULT 3,
    last_renewed_at TIMESTAMP NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_expiry (expiry_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity logs table
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_type ENUM('task_created','task_completed','task_deleted','task_renewed','task_edited','login','logout') NOT NULL,
    event_details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE DEFAULT (DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY)),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Settings table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Agent/referral table
CREATE TABLE referrals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    referrer_id INT NOT NULL,
    referred_email VARCHAR(100),
    referral_code VARCHAR(20) UNIQUE,
    status ENUM('pending','approved','paid') DEFAULT 'pending',
    commission DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin
INSERT INTO users (username, email, password_hash, full_name, is_admin)
VALUES ('admin', 'admin@pamodzi.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pamodzi Admin', 1);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Make Your Plan Today'),
('site_tagline', 'Plan. Execute. Achieve.'),
('max_daily_activities', '40'),
('task_expiry_days', '90'),
('max_renewals', '3'),
('maintenance_mode', '0');

-- Communities
CREATE TABLE communities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    cover_image VARCHAR(255),
    category VARCHAR(50),
    district VARCHAR(50),
    tags VARCHAR(255),
    type ENUM('public', 'private', 'district') DEFAULT 'public',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    invite_code VARCHAR(20) UNIQUE,
    invite_expiry TIMESTAMP,
    member_count INT DEFAULT 0,
    post_count INT DEFAULT 0,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_slug (slug),
    INDEX idx_type (type),
    INDEX idx_district (district)
);

-- Community Members
CREATE TABLE community_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin', 'moderator', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    points INT DEFAULT 0,
    is_banned BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member (community_id, user_id),
    INDEX idx_community (community_id),
    INDEX idx_user (user_id)
);

-- Community Posts
CREATE TABLE community_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    post_type ENUM('text', 'task', 'goal', 'resource', 'announcement') DEFAULT 'text',
    task_id INT NULL,
    image VARCHAR(255),
    is_pinned BOOLEAN DEFAULT FALSE,
    is_announcement BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    likes INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    INDEX idx_community (community_id),
    INDEX idx_created (created_at),
    INDEX idx_pinned (is_pinned)
);

-- Post Reactions (Likes)
CREATE TABLE post_reactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction_type ENUM('like', 'love', 'laugh', 'celebrate', 'support') DEFAULT 'like',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reaction (post_id, user_id)
);

-- Post Comments
CREATE TABLE post_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    parent_id INT NULL,
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES post_comments(id) ON DELETE CASCADE,
    INDEX idx_post (post_id),
    INDEX idx_created (created_at)
);

-- Invites
CREATE TABLE community_invites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    invited_by INT NOT NULL,
    invite_code VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100),
    max_uses INT DEFAULT 5,
    used_count INT DEFAULT 0,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_code (invite_code),
    INDEX idx_expiry (expires_at)
);

-- Community Tasks (Collaborative)
CREATE TABLE community_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    created_by INT NOT NULL,
    assigned_to INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    deadline DATE,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_community (community_id),
    INDEX idx_status (status)
);

-- Reports
CREATE TABLE community_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NULL,
    comment_id INT NULL,
    reported_by INT NOT NULL,
    reason ENUM('spam', 'inappropriate', 'harassment', 'misinformation', 'other') NOT NULL,
    details TEXT,
    status ENUM('pending', 'reviewed', 'dismissed', 'actioned') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES post_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status)
);

-- Notifications
CREATE TABLE community_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    community_id INT NULL,
    post_id INT NULL,
    comment_id INT NULL,
    type ENUM('post', 'comment', 'mention', 'invite', 'join', 'announcement') NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
);

ALTER TABLE communities ADD COLUMN fake_member_offset INT DEFAULT 0;
ALTER TABLE communities ADD COLUMN real_member_count INT DEFAULT 0;
