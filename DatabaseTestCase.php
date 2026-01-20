<?php
/**
 * Base Test Case with Database Support
 */

namespace MyShop\Tests\TestHelpers;

use PHPUnit\Framework\TestCase;
use mysqli;

abstract class DatabaseTestCase extends TestCase
{
    protected ?mysqli $conn = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDatabase();
    }
    
    protected function tearDown(): void
    {
        $this->cleanupDatabase();
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
        parent::tearDown();
    }
    
    protected function setupDatabase(): void
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $dbName = getenv('DB_NAME') ?: 'myshop_test';
        
        $this->conn = new mysqli($host, $user, $pass);
        
        if ($this->conn->connect_error) {
            $this->markTestSkipped('Database connection failed: ' . $this->conn->connect_error);
            return;
        }
        
        // Create test database if not exists
        $this->conn->query("CREATE DATABASE IF NOT EXISTS `$dbName`");
        $this->conn->select_db($dbName);
        $this->conn->set_charset('utf8mb4');
        
        // Create required tables
        $this->createTables();
    }

    protected function createTables(): void
    {
        // Create admins table for foreign key
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS admins (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Create users table for foreign key
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Create notifications table
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                type ENUM('general', 'custom', 'individual') NOT NULL DEFAULT 'general',
                category ENUM('offer', 'new_product', 'cart_reminder', 'promotional') DEFAULT 'promotional',
                image VARCHAR(500) NULL,
                link VARCHAR(500) NULL,
                status ENUM('draft', 'scheduled', 'sent') DEFAULT 'draft',
                scheduled_at DATETIME NULL,
                sent_at DATETIME NULL,
                targeting_criteria JSON NULL,
                target_user_id INT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Create user_notifications table
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS user_notifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                notification_id INT NOT NULL,
                user_id INT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                read_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_notification (notification_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Insert test admin
        $this->conn->query("INSERT IGNORE INTO admins (id, username, email, password) VALUES (1, 'admin', 'admin@test.com', 'test')");
    }
    
    protected function cleanupDatabase(): void
    {
        if (!$this->conn) return;
        
        $this->conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $this->conn->query("TRUNCATE TABLE user_notifications");
        $this->conn->query("TRUNCATE TABLE notifications");
        $this->conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }
    
    protected function getConnection(): mysqli
    {
        return $this->conn;
    }
}
