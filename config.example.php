<?php
/**
 * NotResume.com Configuration
 * Copy this file to config.php and update the values for your environment.
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'notresume');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Site
define('SITE_URL', 'https://notresume.com');
define('SITE_NAME', 'NotResume');

// Resend API (for email verification)
define('RESEND_API_KEY', 'YOUR_RESEND_API_KEY_HERE');
define('FROM_EMAIL', 'noreply@notresume.com');
define('FROM_NAME', 'NotResume');

// Anthropic API (for AI generation)
define('ANTHROPIC_API_KEY', 'YOUR_ANTHROPIC_API_KEY_HERE');
define('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514');

// Session
define('SESSION_LIFETIME', 86400 * 7); // 7 days

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('BCRYPT_COST', 12);

// Limits
define('MAX_PAGES_PER_USER', 10);
define('MAX_RESUME_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_HTML_SIZE', 500000); // 500KB

// Allowed resume file types
define('ALLOWED_RESUME_TYPES', serialize([
    'application/pdf',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]));

// Reserved slugs (cannot be used by users)
define('RESERVED_SLUGS', serialize([
    'index', 'login', 'register', 'logout', 'dashboard',
    'create', 'edit', 'delete', 'verify', 'api_generate',
    'resend-verification', 'forgot', 'reset', 'admin',
    'api', 'app', 'static', 'assets', 'css', 'js', 'img',
    'page', 'config', 'db', 'auth', 'about', 'contact',
    'terms', 'privacy', 'help', 'support', 'settings'
]));
