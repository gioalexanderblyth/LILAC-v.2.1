-- MOU/MOA Database Schema
-- SQLite database for storing Memorandum of Understanding/Agreement data

CREATE TABLE IF NOT EXISTS mou_moa (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    institution VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    term VARCHAR(100) NOT NULL,
    sign_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(50) NOT NULL,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_institution ON mou_moa(institution);
CREATE INDEX IF NOT EXISTS idx_status ON mou_moa(status);
CREATE INDEX IF NOT EXISTS idx_end_date ON mou_moa(end_date);
CREATE INDEX IF NOT EXISTS idx_created_at ON mou_moa(created_at);

-- Insert some sample data
INSERT OR IGNORE INTO mou_moa (institution, location, contact_email, term, sign_date, end_date, status, file_name, file_path) VALUES
('Tech Innovators Inc.', 'Silicon Valley, CA', 'jane.doe@techinc.com', '3 Years', '2021-12-31', '2024-12-31', 'Active', 'tech_innovators_mou.pdf', '/uploads/tech_innovators_mou.pdf'),
('Global Solutions Ltd.', 'London, UK', 'john.smith@globalsol.com', '5 Years', '2020-06-15', '2025-06-15', 'Active', 'global_solutions_mou.pdf', '/uploads/global_solutions_mou.pdf'),
('EduTech Solutions', 'Singapore', 'info@edutech.sg', '2 Years', '2021-11-20', '2023-11-20', 'Expired', 'edutech_mou.pdf', '/uploads/edutech_mou.pdf'),
('Community Dev Corp.', 'New York, NY', 'contact@commdev.org', '1 Year', '2023-09-30', '2024-09-30', 'Expires Soon', 'community_dev_mou.pdf', '/uploads/community_dev_mou.pdf'),
('Research Institute of Manila', 'Manila, PH', 'admin@rim.edu.ph', '4 Years', '2021-03-01', '2025-03-01', 'Active', 'rim_mou.pdf', '/uploads/rim_mou.pdf'); 

-- Events & Activities schema
CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(20) NOT NULL CHECK(type IN ('event','activity')),
    location VARCHAR(255) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    thumbnail_url VARCHAR(500),
    image_url VARCHAR(500),
    eligible_for_awards INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_events_date ON events(date);
CREATE INDEX IF NOT EXISTS idx_events_eligible ON events(eligible_for_awards);

-- Awards schema
CREATE TABLE IF NOT EXISTS awards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    date DATE,
    description TEXT,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    ocr_text TEXT,
    created_by VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS award_analysis (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    award_id INTEGER NOT NULL,
    predicted_category VARCHAR(100) NOT NULL,
    confidence REAL NOT NULL,
    matched_categories_json TEXT NOT NULL, -- JSON array of category ids/names the award fits
    checklist_json TEXT NOT NULL, -- JSON object of criteria -> met/partial/missing
    recommendations_text TEXT,
    evidence_json TEXT, -- snippets or fields used
    manual_overridden INTEGER DEFAULT 0,
    final_category VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(award_id) REFERENCES awards(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS award_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    award_id INTEGER NOT NULL,
    event_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(award_id) REFERENCES awards(id) ON DELETE CASCADE,
    FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_awards_date ON awards(date);
CREATE INDEX IF NOT EXISTS idx_award_analysis_pred ON award_analysis(predicted_category);
CREATE INDEX IF NOT EXISTS idx_award_analysis_final ON award_analysis(final_category);

-- Award counters table for tracking counts per category
CREATE TABLE IF NOT EXISTS award_counters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    award_name VARCHAR(255) NOT NULL UNIQUE,
    count INTEGER DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Initialize award counters with ICONS 2025 categories
INSERT OR IGNORE INTO award_counters (award_name, count) VALUES
('Global Citizenship Award', 0),
('Outstanding International Education Program Award', 0),
('Emerging Leadership Award', 0),
('Internationalization Leadership Award', 0),
('Best Regional Office for Internationalization Award', 0);

CREATE INDEX IF NOT EXISTS idx_award_counters_name ON award_counters(award_name);