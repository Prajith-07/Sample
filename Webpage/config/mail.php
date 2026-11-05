<?php
/**
 * iTech Seed Grand Challenge 2027
 * Email & SMTP Configuration for Gmail / Launchpad Notifications
 * PSG iTech Launchpad: Innozone & Startupzone
 */

declare(strict_types=1);

return [
    // SMTP Server Settings (Configured for Gmail)
    'smtp_enabled'   => true,
    'smtp_host'      => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'smtp_port'      => (int)(getenv('SMTP_PORT') ?: 587),
    'smtp_secure'    => 'tls', // 'tls' (port 587) or 'ssl' (port 465)
    
    // Gmail Credentials (Use a Google App Password, not personal password)
    // You can set environment variables or fill them directly here
    'smtp_username'  => getenv('GMAIL_USERNAME') ?: 'prajithprabhu22@gmail.com',
    'smtp_password'  => getenv('GMAIL_APP_PASSWORD') ?: 'hdzw qvdo ifny ehyd', // 16-character Google App Password
    
    // Sender Information
    'from_email'     => getenv('MAIL_FROM_EMAIL') ?: 'prajithprabhu22@gmail.com',
    'from_name'      => 'PSG iTech Launchpad: Innozone & Startupzone',
    'reply_to'       => 'prajithprabhu22@gmail.com',

    // Log directory for fallback & local auditing
    'log_path'       => __DIR__ . '/../mail_logs',

    // Dynamic Base URL Resolver for Approval Links
    'get_base_url'   => function (): string {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Check if running in subfolder like /itech_seed/ or /Webpage/
        $dir = dirname($script);
        if ($dir === '/' || $dir === '\\') {
            $dir = '';
        }
        // Normalize slashes
        $dir = str_replace('\\', '/', $dir);
        // If script is in /api/ or similar subfolder, go up to webroot
        if (basename($dir) === 'api') {
            $dir = dirname($dir);
        }
        return rtrim("{$scheme}://{$host}{$dir}", '/');
    }
];
