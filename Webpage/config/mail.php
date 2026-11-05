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
        $host   = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Normalize CLI or subfolder path
        if (preg_match('/^[a-zA-Z]:/i', $script)) {
            $dir = '';
        } else {
            $dir = dirname($script);
            if ($dir === '/' || $dir === '\\' || $dir === '.') {
                $dir = '';
            }
            $dir = str_replace('\\', '/', $dir);
            if (basename($dir) === 'api') {
                $dir = dirname($dir);
            }
            if ($dir === '/' || $dir === '.') {
                $dir = '';
            }
        }
        return rtrim("{$scheme}://{$host}{$dir}", '/');
    }
];
