-- ============================================
-- Donation Management System - MySQL Schema
-- ============================================

-- Drop existing database if exists (CAUTION: This deletes all data)
DROP DATABASE IF EXISTS donation_management;

-- Create database with UTF-8 support
CREATE DATABASE donation_management 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE donation_management;

-- ============================================
-- CORE USER TABLES
-- ============================================

-- User base table (Abstract)
CREATE TABLE User (
    userId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    email VARCHAR(255) NOT NULL UNIQUE,
    passwordHash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    userType ENUM('donor', 'organization', 'admin') NOT NULL,
    phoneNumber VARCHAR(20),
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lastLoginAt DATETIME,
    isActive BOOLEAN NOT NULL DEFAULT TRUE,
    
    INDEX idx_email (email),
    INDEX idx_user_type (userType),
    INDEX idx_created_at (createdAt)
) ENGINE=InnoDB;

-- Donor table
CREATE TABLE Donor (
    donorId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    userId CHAR(36) NOT NULL UNIQUE,
    isAnonymous BOOLEAN NOT NULL DEFAULT FALSE,
    totalDonated DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    donationCount INT NOT NULL DEFAULT 0,
    lastDonationAt DATETIME,
    
    FOREIGN KEY (userId) REFERENCES User(userId) ON DELETE CASCADE,
    INDEX idx_total_donated (totalDonated DESC),
    INDEX idx_last_donation (lastDonationAt DESC),
    
    CONSTRAINT chk_donor_total CHECK (totalDonated >= 0),
    CONSTRAINT chk_donor_count CHECK (donationCount >= 0)
) ENGINE=InnoDB;

-- Organization table
CREATE TABLE Organization (
    organizationId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    userId CHAR(36) NOT NULL UNIQUE,
    verificationStatus ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    registrationNumber VARCHAR(100) UNIQUE,
    address TEXT,
    website VARCHAR(255),
    description TEXT,
    totalReceived DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    totalSpent DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    availableBalance DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    verifiedAt DATETIME,
    registeredAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (userId) REFERENCES User(userId) ON DELETE CASCADE,
    INDEX idx_verification_status (verificationStatus),
    INDEX idx_total_spent (totalSpent DESC),
    INDEX idx_available_balance (availableBalance),
    INDEX idx_registered_at (registeredAt),
    
    CONSTRAINT chk_org_received CHECK (totalReceived >= 0),
    CONSTRAINT chk_org_spent CHECK (totalSpent >= 0),
    CONSTRAINT chk_org_balance CHECK (availableBalance >= 0),
    CONSTRAINT chk_org_balance_calc CHECK (availableBalance = totalReceived - totalSpent)
) ENGINE=InnoDB;

-- Admin table
CREATE TABLE Admin (
    adminId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    userId CHAR(36) NOT NULL UNIQUE,
    role ENUM('super_admin', 'finance_admin', 'support_admin') NOT NULL DEFAULT 'support_admin',
    assignedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (userId) REFERENCES User(userId) ON DELETE CASCADE,
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ============================================
-- DONATION & TRANSACTION TABLES
-- ============================================

-- Donation table
CREATE TABLE Donation (
    donationId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    donorId CHAR(36) NOT NULL,
    organizationId CHAR(36) NOT NULL,
    amountTotal DECIMAL(15, 2) NOT NULL,
    amountAllocated DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    status ENUM('allocated', 'partially_spent', 'fully_spent') NOT NULL DEFAULT 'allocated',
    paymentMethod ENUM('card', 'bank', 'mobile_money', 'crypto', 'cash') NOT NULL,
    transactionReference VARCHAR(255) UNIQUE,
    donatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    isRecurring BOOLEAN NOT NULL DEFAULT FALSE,
    
    FOREIGN KEY (donorId) REFERENCES Donor(donorId) ON DELETE RESTRICT,
    FOREIGN KEY (organizationId) REFERENCES Organization(organizationId) ON DELETE RESTRICT,
    
    INDEX idx_donor_date (donorId, donatedAt DESC),
    INDEX idx_org_date (organizationId, donatedAt DESC),
    INDEX idx_status (status),
    INDEX idx_donated_at (donatedAt DESC),
    INDEX idx_transaction_ref (transactionReference),
    
    CONSTRAINT chk_donation_amount CHECK (amountTotal > 0),
    CONSTRAINT chk_donation_allocated CHECK (amountAllocated >= 0 AND amountAllocated <= amountTotal)
) ENGINE=InnoDB;

-- Withdrawal table
CREATE TABLE Withdrawal (
    withdrawalId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    organizationId CHAR(36) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    bankAccountNumber VARCHAR(50),
    purpose TEXT NOT NULL,
    requestedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processedAt DATETIME,
    processedBy CHAR(36),
    remarks TEXT,
    
    FOREIGN KEY (organizationId) REFERENCES Organization(organizationId) ON DELETE RESTRICT,
    FOREIGN KEY (processedBy) REFERENCES Admin(adminId) ON DELETE SET NULL,
    
    INDEX idx_org_withdrawal (organizationId, requestedAt DESC),
    INDEX idx_status (status),
    INDEX idx_processed_by (processedBy),
    INDEX idx_requested_at (requestedAt DESC),
    
    CONSTRAINT chk_withdrawal_amount CHECK (amount > 0)
) ENGINE=InnoDB;

-- ============================================
-- BENEFICIARY & IMPACT TABLES
-- ============================================

-- Beneficiary table
CREATE TABLE Beneficiary (
    beneficiaryId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    organizationId CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('individual', 'group', 'community', 'project') NOT NULL,
    location VARCHAR(255) NOT NULL,
    description TEXT,
    geoCoordinates VARCHAR(50), -- Format: "latitude,longitude"
    allocationCount INT NOT NULL DEFAULT 0,
    totalReceived DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    isActive BOOLEAN NOT NULL DEFAULT TRUE,
    
    FOREIGN KEY (organizationId) REFERENCES Organization(organizationId) ON DELETE CASCADE,
    
    INDEX idx_org_beneficiary (organizationId),
    INDEX idx_type (type),
    INDEX idx_location (location),
    INDEX idx_total_received (totalReceived DESC),
    INDEX idx_created_at (createdAt),
    
    CONSTRAINT chk_beneficiary_received CHECK (totalReceived >= 0),
    CONSTRAINT chk_beneficiary_count CHECK (allocationCount >= 0)
) ENGINE=InnoDB;

-- Impact Story table
CREATE TABLE ImpactStory (
    storyId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    organizationId CHAR(36) NOT NULL,
    title VARCHAR(500) NOT NULL,
    content TEXT NOT NULL,
    mediaUrl VARCHAR(500),
    mediaType ENUM('image', 'video', 'document') DEFAULT 'image',
    geoTag VARCHAR(255),
    totalFunding DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    allocationCount INT NOT NULL DEFAULT 0,
    publishedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    isPublished BOOLEAN NOT NULL DEFAULT FALSE,
    viewCount INT NOT NULL DEFAULT 0,
    
    FOREIGN KEY (organizationId) REFERENCES Organization(organizationId) ON DELETE CASCADE,
    
    INDEX idx_org_story (organizationId),
    INDEX idx_published (isPublished, publishedAt DESC),
    INDEX idx_view_count (viewCount DESC),
    INDEX idx_total_funding (totalFunding DESC),
    
    CONSTRAINT chk_story_funding CHECK (totalFunding >= 0),
    CONSTRAINT chk_story_allocation_count CHECK (allocationCount >= 0),
    CONSTRAINT chk_story_view_count CHECK (viewCount >= 0),
    
    FULLTEXT INDEX idx_story_search (title, content)
) ENGINE=InnoDB;

-- Donation Allocation table
CREATE TABLE DonationAllocation (
    allocationId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    donationId CHAR(36) NOT NULL,
    beneficiaryId CHAR(36) NOT NULL,
    impactStoryId CHAR(36),
    withdrawalId CHAR(36),
    amountSpent DECIMAL(15, 2) NOT NULL,
    purpose VARCHAR(500) NOT NULL,
    spentAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    proofUrl VARCHAR(500),
    status ENUM('pending', 'completed', 'verified') NOT NULL DEFAULT 'pending',
    
    FOREIGN KEY (donationId) REFERENCES Donation(donationId) ON DELETE RESTRICT,
    FOREIGN KEY (beneficiaryId) REFERENCES Beneficiary(beneficiaryId) ON DELETE RESTRICT,
    FOREIGN KEY (impactStoryId) REFERENCES ImpactStory(storyId) ON DELETE SET NULL,
    FOREIGN KEY (withdrawalId) REFERENCES Withdrawal(withdrawalId) ON DELETE SET NULL,
    
    INDEX idx_donation (donationId),
    INDEX idx_beneficiary (beneficiaryId),
    INDEX idx_impact_story (impactStoryId),
    INDEX idx_withdrawal (withdrawalId),
    INDEX idx_spent_at (spentAt DESC),
    INDEX idx_status (status),
    
    CONSTRAINT chk_allocation_amount CHECK (amountSpent > 0)
) ENGINE=InnoDB;

-- ============================================
-- AUDIT & TRACKING TABLES
-- ============================================

-- Verification History table
CREATE TABLE VerificationHistory (
    verificationId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    organizationId CHAR(36) NOT NULL,
    reviewedBy CHAR(36),
    previousStatus ENUM('pending', 'approved', 'rejected'),
    newStatus ENUM('pending', 'approved', 'rejected') NOT NULL,
    remarks TEXT,
    reviewedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (organizationId) REFERENCES Organization(organizationId) ON DELETE CASCADE,
    FOREIGN KEY (reviewedBy) REFERENCES Admin(adminId) ON DELETE SET NULL,
    
    INDEX idx_organization (organizationId, reviewedAt DESC),
    INDEX idx_reviewer (reviewedBy),
    INDEX idx_reviewed_at (reviewedAt DESC)
) ENGINE=InnoDB;

-- Audit Log table
CREATE TABLE AuditLog (
    logId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    adminId CHAR(36),
    actionType ENUM('create', 'update', 'delete', 'approve', 'reject', 'login', 'logout') NOT NULL,
    entityType ENUM('donor', 'organization', 'donation', 'withdrawal', 'beneficiary', 'impact_story', 'user') NOT NULL,
    entityId CHAR(36),
    description TEXT NOT NULL,
    changeDetails JSON,
    ipAddress VARCHAR(45),
    userAgent TEXT,
    performedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (adminId) REFERENCES Admin(adminId) ON DELETE SET NULL,
    
    INDEX idx_admin (adminId, performedAt DESC),
    INDEX idx_entity (entityType, entityId),
    INDEX idx_action_type (actionType),
    INDEX idx_performed_at (performedAt DESC)
) ENGINE=InnoDB;

-- ============================================
-- RANKING & ANALYTICS TABLES
-- ============================================

-- Donor Ranking table
CREATE TABLE DonorRanking (
    rankingId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    donorId CHAR(36) NOT NULL,
    `rank` INT NOT NULL,
    totalAmount DECIMAL(15, 2) NOT NULL,
    period ENUM('daily', 'weekly', 'monthly', 'yearly', 'all_time') NOT NULL,
    periodStart DATE NOT NULL,
    periodEnd DATE NOT NULL,
    calculatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donorId) REFERENCES Donor(donorId) ON DELETE CASCADE,
    
    UNIQUE KEY unique_donor_period (donorId, period, periodEnd),
    INDEX idx_period_rank (period, periodEnd DESC, `rank`),
    INDEX idx_calculated_at (calculatedAt),
    
    CONSTRAINT chk_donor_rank CHECK (`rank` > 0),
    CONSTRAINT chk_donor_rank_amount CHECK (totalAmount >= 0)
) ENGINE=InnoDB;

-- Organization Ranking table
CREATE TABLE OrganizationRanking (
    rankingId CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    organizationId CHAR(36) NOT NULL,
    `rank` INT NOT NULL,
    totalSpent DECIMAL(15, 2) NOT NULL,
    impactScore DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    period ENUM('daily', 'weekly', 'monthly', 'yearly', 'all_time') NOT NULL,
    periodStart DATE NOT NULL,
    periodEnd DATE NOT NULL,
    calculatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (organizationId) REFERENCES Organization(organizationId) ON DELETE CASCADE,
    
    UNIQUE KEY unique_org_period (organizationId, period, periodEnd),
    INDEX idx_period_rank (period, periodEnd DESC, `rank`),
    INDEX idx_impact_score (impactScore DESC),
    INDEX idx_calculated_at (calculatedAt),
    
    CONSTRAINT chk_org_rank CHECK (`rank` > 0),
    CONSTRAINT chk_org_rank_spent CHECK (totalSpent >= 0),
    CONSTRAINT chk_org_impact_score CHECK (impactScore >= 0)
) ENGINE=InnoDB;

-- ============================================
-- TRIGGERS FOR AUTOMATIC UPDATES
-- ============================================

-- Update donor statistics when donation is created
DELIMITER $$

CREATE TRIGGER after_donation_insert
AFTER INSERT ON Donation
FOR EACH ROW
BEGIN
    UPDATE Donor 
    SET 
        totalDonated = totalDonated + NEW.amountTotal,
        donationCount = donationCount + 1,
        lastDonationAt = NEW.donatedAt
    WHERE donorId = NEW.donorId;
    
    UPDATE Organization
    SET 
        totalReceived = totalReceived + NEW.amountTotal,
        availableBalance = availableBalance + NEW.amountTotal
    WHERE organizationId = NEW.organizationId;
END$$

-- Update donation status based on allocations
CREATE TRIGGER after_allocation_insert
AFTER INSERT ON DonationAllocation
FOR EACH ROW
BEGIN
    DECLARE total_allocated DECIMAL(15,2);
    DECLARE donation_total DECIMAL(15,2);
    
    SELECT SUM(amountSpent) INTO total_allocated
    FROM DonationAllocation
    WHERE donationId = NEW.donationId;
    
    SELECT amountTotal INTO donation_total
    FROM Donation
    WHERE donationId = NEW.donationId;
    
    UPDATE Donation
    SET 
        amountAllocated = total_allocated,
        status = CASE
            WHEN total_allocated = 0 THEN 'allocated'
            WHEN total_allocated < donation_total THEN 'partially_spent'
            ELSE 'fully_spent'
        END
    WHERE donationId = NEW.donationId;
    
    UPDATE Organization
    SET 
        totalSpent = totalSpent + NEW.amountSpent,
        availableBalance = availableBalance - NEW.amountSpent
    WHERE organizationId = (
        SELECT organizationId FROM Donation WHERE donationId = NEW.donationId
    );
    
    UPDATE Beneficiary
    SET 
        allocationCount = allocationCount + 1,
        totalReceived = totalReceived + NEW.amountSpent
    WHERE beneficiaryId = NEW.beneficiaryId;
    
    IF NEW.impactStoryId IS NOT NULL THEN
        UPDATE ImpactStory
        SET 
            allocationCount = allocationCount + 1,
            totalFunding = totalFunding + NEW.amountSpent
        WHERE storyId = NEW.impactStoryId;
    END IF;
END$$

-- Track organization verification changes
CREATE TRIGGER after_organization_verification_update
AFTER UPDATE ON Organization
FOR EACH ROW
BEGIN
    IF OLD.verificationStatus != NEW.verificationStatus THEN
        INSERT INTO VerificationHistory (
            verificationId,
            organizationId,
            previousStatus,
            newStatus,
            reviewedAt
        ) VALUES (
            UUID(),
            NEW.organizationId,
            OLD.verificationStatus,
            NEW.verificationStatus,
            CURRENT_TIMESTAMP
        );
    END IF;
END$$

-- Increment impact story view count
CREATE TRIGGER after_impact_story_view
AFTER UPDATE ON ImpactStory
FOR EACH ROW
BEGIN
    IF NEW.viewCount > OLD.viewCount THEN
        -- Just ensuring the trigger executes
        SET @dummy = 0;
    END IF;
END$$

DELIMITER ;

-- ============================================
-- VIEWS FOR COMMON QUERIES
-- ============================================

-- Top Donors View (All Time)
CREATE VIEW TopDonorsAllTime AS
SELECT 
    d.donorId,
    u.name,
    u.email,
    d.isAnonymous,
    d.totalDonated,
    d.donationCount,
    d.lastDonationAt,
    RANK() OVER (ORDER BY d.totalDonated DESC) as ranking
FROM Donor d
JOIN User u ON d.userId = u.userId
WHERE u.isActive = TRUE
ORDER BY d.totalDonated DESC;

-- Top Organizations View (All Time)
CREATE VIEW TopOrganizationsAllTime AS
SELECT 
    o.organizationId,
    u.name,
    u.email,
    o.verificationStatus,
    o.totalReceived,
    o.totalSpent,
    o.availableBalance,
    RANK() OVER (ORDER BY o.totalSpent DESC) as ranking,
    CASE 
        WHEN o.totalReceived > 0 THEN (o.totalSpent / o.totalReceived) * 100
        ELSE 0
    END as efficiencyPercentage
FROM Organization o
JOIN User u ON o.userId = u.userId
WHERE u.isActive = TRUE AND o.verificationStatus = 'approved'
ORDER BY o.totalSpent DESC;

-- Recent Impact Stories View
CREATE VIEW RecentImpactStories AS
SELECT 
    i.storyId,
    i.title,
    i.content,
    i.mediaUrl,
    i.mediaType,
    i.totalFunding,
    i.viewCount,
    i.publishedAt,
    o.organizationId,
    u.name as organizationName
FROM ImpactStory i
JOIN Organization o ON i.organizationId = o.organizationId
JOIN User u ON o.userId = u.userId
WHERE i.isPublished = TRUE
ORDER BY i.publishedAt DESC;

-- Donor Dashboard View
CREATE VIEW DonorDashboard AS
SELECT 
    d.donorId,
    don.donationId,
    don.amountTotal,
    don.donatedAt,
    don.status,
    u.name as organizationName,
    COUNT(da.allocationId) as allocationCount,
    COALESCE(SUM(da.amountSpent), 0) as amountSpent
FROM Donor d
JOIN Donation don ON d.donorId = don.donorId
JOIN Organization o ON don.organizationId = o.organizationId
JOIN User u ON o.userId = u.userId
LEFT JOIN DonationAllocation da ON don.donationId = da.donationId
GROUP BY d.donorId, don.donationId, don.amountTotal, don.donatedAt, don.status, u.name;

-- Organization Dashboard View
CREATE VIEW OrganizationDashboard AS
SELECT 
    o.organizationId,
    COUNT(DISTINCT don.donationId) as totalDonations,
    COUNT(DISTINCT d.donorId) as uniqueDonors,
    o.totalReceived,
    o.totalSpent,
    o.availableBalance,
    COUNT(DISTINCT w.withdrawalId) as totalWithdrawals,
    COUNT(DISTINCT i.storyId) as totalStories,
    COUNT(DISTINCT b.beneficiaryId) as totalBeneficiaries
FROM Organization o
LEFT JOIN Donation don ON o.organizationId = don.organizationId
LEFT JOIN Donor d ON don.donorId = d.donorId
LEFT JOIN Withdrawal w ON o.organizationId = w.organizationId
LEFT JOIN ImpactStory i ON o.organizationId = i.organizationId
LEFT JOIN Beneficiary b ON o.organizationId = b.organizationId
GROUP BY o.organizationId;

-- ============================================
-- STORED PROCEDURES
-- ============================================

-- Procedure to process withdrawal
DELIMITER $$

CREATE PROCEDURE ProcessWithdrawal(
    IN p_withdrawalId CHAR(36),
    IN p_adminId CHAR(36),
    IN p_newStatus ENUM('approved', 'rejected'),
    IN p_remarks TEXT
)
BEGIN
    DECLARE v_amount DECIMAL(15,2);
    DECLARE v_orgId CHAR(36);
    DECLARE v_availableBalance DECIMAL(15,2);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error processing withdrawal';
    END;
    
    START TRANSACTION;
    
    SELECT amount, organizationId INTO v_amount, v_orgId
    FROM Withdrawal
    WHERE withdrawalId = p_withdrawalId AND status = 'pending';
    
    IF v_amount IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Withdrawal not found or already processed';
    END IF;
    
    SELECT availableBalance INTO v_availableBalance
    FROM Organization
    WHERE organizationId = v_orgId;
    
    IF p_newStatus = 'approved' AND v_availableBalance < v_amount THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient balance';
    END IF;
    
    UPDATE Withdrawal
    SET 
        status = p_newStatus,
        processedAt = CURRENT_TIMESTAMP,
        processedBy = p_adminId,
        remarks = p_remarks
    WHERE withdrawalId = p_withdrawalId;
    
    INSERT INTO AuditLog (
        logId, adminId, actionType, entityType, entityId, description, performedAt
    ) VALUES (
        UUID(), p_adminId, p_newStatus, 'withdrawal', p_withdrawalId,
        CONCAT('Withdrawal ', p_newStatus, ' for amount ', v_amount),
        CURRENT_TIMESTAMP
    );
    
    COMMIT;
END$$

-- Procedure to calculate rankings
CREATE PROCEDURE CalculateRankings(
    IN p_period ENUM('daily', 'weekly', 'monthly', 'yearly', 'all_time')
)
BEGIN
    DECLARE v_periodStart DATE;
    DECLARE v_periodEnd DATE;
    
    SET v_periodEnd = CURDATE();
    
    SET v_periodStart = CASE p_period
        WHEN 'daily' THEN CURDATE()
        WHEN 'weekly' THEN DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        WHEN 'monthly' THEN DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        WHEN 'yearly' THEN DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
        WHEN 'all_time' THEN '1970-01-01'
    END;
    
    -- Calculate Donor Rankings
    INSERT INTO DonorRanking (rankingId, donorId, `rank`, totalAmount, period, periodStart, periodEnd)
    SELECT 
        UUID(),
        d.donorId,
        RANK() OVER (ORDER BY SUM(don.amountTotal) DESC) as `rank`,
        SUM(don.amountTotal) as totalAmount,
        p_period,
        v_periodStart,
        v_periodEnd
    FROM Donor d
    JOIN Donation don ON d.donorId = don.donorId
    WHERE don.donatedAt BETWEEN v_periodStart AND v_periodEnd
    GROUP BY d.donorId
    ON DUPLICATE KEY UPDATE
        `rank` = VALUES(`rank`),
        totalAmount = VALUES(totalAmount),
        calculatedAt = CURRENT_TIMESTAMP;
    
    -- Calculate Organization Rankings
    INSERT INTO OrganizationRanking (
        rankingId, organizationId, `rank`, totalSpent, impactScore, period, periodStart, periodEnd
    )
    SELECT 
        UUID(),
        o.organizationId,
        RANK() OVER (ORDER BY SUM(da.amountSpent) DESC) as `rank`,
        SUM(da.amountSpent) as totalSpent,
        COUNT(DISTINCT da.beneficiaryId) * 10 + COUNT(DISTINCT i.storyId) * 5 as impactScore,
        p_period,
        v_periodStart,
        v_periodEnd
    FROM Organization o
    JOIN Donation don ON o.organizationId = don.organizationId
    LEFT JOIN DonationAllocation da ON don.donationId = da.donationId
    LEFT JOIN ImpactStory i ON o.organizationId = i.organizationId
    WHERE da.spentAt BETWEEN v_periodStart AND v_periodEnd
    GROUP BY o.organizationId
    ON DUPLICATE KEY UPDATE
        `rank` = VALUES(`rank`),
        totalSpent = VALUES(totalSpent),
        impactScore = VALUES(impactScore),
        calculatedAt = CURRENT_TIMESTAMP;
END$$

DELIMITER ;

-- ============================================
-- INITIAL DATA & ADMIN SETUP
-- ============================================

-- Insert default super admin (Change password immediately!)
INSERT INTO User (userId, email, passwordHash, name, userType, createdAt, isActive)
VALUES (
    UUID(),
    'admin@donationmanagement.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Password: "password"
    'System Administrator',
    'admin',
    CURRENT_TIMESTAMP,
    TRUE
);

INSERT INTO Admin (adminId, userId, role, assignedAt)
SELECT UUID(), userId, 'super_admin', CURRENT_TIMESTAMP
FROM User
WHERE email = 'admin@donationmanagement.com';



INSERT INTO User (userId, email, passwordHash, name, userType, phoneNumber, createdAt, isActive)
VALUES (
    UUID(),
    'hasan@donationmanagement.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Password: "password"
    'Hasan Siddiki',
    'donor',
    '+880123456789',
    CURRENT_TIMESTAMP,
    TRUE
);

INSERT INTO Donor (donorId, userId, isAnonymous)
SELECT UUID(), userId, FALSE
FROM User
WHERE email = 'hasan@donationmanagement.com';


-- ============================================
-- GRANT PERMISSIONS (Adjust as needed)
-- ============================================

-- Create application user (replace with your actual username)
-- CREATE USER 'donation_app'@'localhost' IDENTIFIED BY 'your_secure_password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON donation_management.* TO 'donation_app'@'localhost';
-- FLUSH PRIVILEGES;

-- ============================================
-- END OF SCHEMA
-- ============================================

SELECT 'Database schema created successfully!' as Status;