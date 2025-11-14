<?php
// JSON File Storage Configuration
define('DATA_DIR', __DIR__ . '/data');
define('LAPTOPS_FILE', DATA_DIR . '/laptops.json');
define('USERS_FILE', DATA_DIR . '/users.json');
define('CONTACTS_FILE', DATA_DIR . '/contacts.json');
define('NEWSLETTER_FILE', DATA_DIR . '/newsletter.json');

// Security settings
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('ADMIN_SESSION_NAME', 'laptophub_admin');

// CORS settings (adjust for production)
define('ALLOWED_ORIGIN', '*'); // Change to your domain in production

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// JSON File handling functions
function readJSONFile($filepath) {
    if (!file_exists($filepath)) {
        return [];
    }

    $content = file_get_contents($filepath);
    if ($content === false) {
        logError("Failed to read file: $filepath");
        return [];
    }

    $data = json_decode($content, true);
    if ($data === null && $content !== 'null') {
        logError("Failed to decode JSON from: $filepath");
        return [];
    }

    return $data ?? [];
}

function writeJSONFile($filepath, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        logError("Failed to encode JSON for: $filepath");
        return false;
    }

    // Create directory if it doesn't exist
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $result = file_put_contents($filepath, $json, LOCK_EX);

    if ($result === false) {
        logError("Failed to write file: $filepath");
        return false;
    }

    return true;
}

function getNextId($data) {
    if (empty($data)) {
        return 1;
    }

    $maxId = max(array_column($data, 'id'));
    return $maxId + 1;
}

// Set JSON response headers
function setJSONHeaders() {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Start secure session
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
        ini_set('session.cookie_samesite', 'Strict');
        session_name(ADMIN_SESSION_NAME);
        session_start();
    }
}

// Check if admin is logged in
function isAdminLoggedIn() {
    startSecureSession();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Require admin authentication
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized. Admin login required.'
        ]);
        exit;
    }
}

// Sanitize input
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Send JSON response
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Log error (for production)
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, __DIR__ . '/logs/errors.log');
}
?>
