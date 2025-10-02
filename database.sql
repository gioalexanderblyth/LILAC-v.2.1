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