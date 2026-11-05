<?php
/**
 * iTech Seed Grand Challenge 2027
 * Database Configuration & PDO Connection
 * Supports XAMPP MySQL / MariaDB
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'itech_seed_2027');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDatabaseConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Ensure required tables exist even if DB already existed
        ensureTablesExist($pdo);
        
        return $pdo;
    } catch (PDOException $e) {
        // If database does not exist, attempt to auto-create it
        if ($e->getCode() === 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
            try {
                $rootDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
                $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS, $options);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Reconnect to newly created database
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                ensureTablesExist($pdo);
                return $pdo;
            } catch (PDOException $inner) {
                error_log("Database initialization error: " . $inner->getMessage());
                return null;
            }
        }
        error_log("MySQL Connection error: " . $e->getMessage());
        return null;
    }
}

/**
 * Automatically applies schema.sql if key tables like 'teams' are missing
 */
function ensureTablesExist(PDO $pdo) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'teams'");
        if ($check->rowCount() === 0) {
            $schemaFile = __DIR__ . '/../database/schema.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);
            }
        } else {
            // Check for new auth columns in team_members
            $colCheck = $pdo->query("SHOW COLUMNS FROM `team_members` LIKE 'password_hash'");
            if ($colCheck->rowCount() === 0) {
                $pdo->exec("
                    ALTER TABLE `team_members` 
                    ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL AFTER `gender`,
                    ADD COLUMN `is_dummy_password` TINYINT(1) NOT NULL DEFAULT 1 AFTER `password_hash`,
                    ADD COLUMN `reset_token` VARCHAR(64) DEFAULT NULL AFTER `is_dummy_password`,
                    ADD COLUMN `reset_token_expires` DATETIME DEFAULT NULL AFTER `reset_token`
                ");
            }

            // Check for new auth columns in team_mentors
            $mColCheck = $pdo->query("SHOW COLUMNS FROM `team_mentors` LIKE 'password_hash'");
            if ($mColCheck->rowCount() === 0) {
                $pdo->exec("
                    ALTER TABLE `team_mentors` 
                    ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL AFTER `consent_original_idea`,
                    ADD COLUMN `is_dummy_password` TINYINT(1) NOT NULL DEFAULT 1 AFTER `password_hash`,
                    ADD COLUMN `reset_token` VARCHAR(64) DEFAULT NULL AFTER `is_dummy_password`,
                    ADD COLUMN `reset_token_expires` DATETIME DEFAULT NULL AFTER `reset_token`
                ");
            }
        }
    } catch (PDOException $e) {
        error_log("Error checking/creating tables: " . $e->getMessage());
    }
}
