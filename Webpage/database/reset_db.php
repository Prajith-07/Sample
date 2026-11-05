<?php
/**
 * iTech Seed Grand Challenge 2027
 * Database Reset Utility
 * Clears all challenge data and restores a pristine empty database.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$pdo = getDatabaseConnection();

if (!$pdo) {
    if (php_sapi_name() === 'cli') {
        echo "Error: Database connection unavailable. Please check that MySQL is running in XAMPP.\n";
    } else {
        echo "<h3>Database connection unavailable. Please ensure MySQL is running in XAMPP.</h3>";
    }
    exit(1);
}

try {
    $sqlFile = __DIR__ . '/reset_database.sql';
    if (!file_exists($sqlFile)) {
        throw new RuntimeException("Reset script {$sqlFile} not found.");
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);

    $msg = "Database successfully reset. All tables truncated.";
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
    } else {
        echo "<div style='font-family:sans-serif; padding:20px; color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;'>";
        echo "<h2>✓ Database Reset Completed</h2>";
        echo "<p>{$msg}</p>";
        echo "<p><a href='../signup.php' style='color:#15803d; font-weight:600;'>Return to Create Account</a> | <a href='../login.php' style='color:#15803d; font-weight:600;'>Go to Login</a></p>";
        echo "</div>";
    }
} catch (Exception $e) {
    $err = "Reset error: " . $e->getMessage();
    if (php_sapi_name() === 'cli') {
        echo $err . "\n";
    } else {
        echo "<div style='font-family:sans-serif; padding:20px; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:8px;'>";
        echo "<h2>✗ Database Reset Failed</h2>";
        echo "<p>{$err}</p>";
        echo "</div>";
    }
    exit(1);
}
