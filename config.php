<?php
// JSON File Storage Configuration
define('DATA_DIR', __DIR__ . '/data');
define('LAPTOPS_FILE', DATA_DIR . '/laptops.json');
define('USERS_FILE', DATA_DIR . '/users.json');
define('CONTACTS_FILE', DATA_DIR . '/contacts.json');
define('NEWSLETTER_FILE', DATA_DIR . '/newsletter.json');

// File Upload Configuration
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('LAPTOP_IMAGES_DIR', UPLOADS_DIR . '/laptops');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

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

// File Upload Functions
function ensureUploadDirExists($dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function validateImageFile($file) {
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'No file was uploaded'];
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['valid' => false, 'error' => 'File size exceeds maximum allowed size (5MB)'];
    }

    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return ['valid' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed'];
    }

    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['valid' => false, 'error' => 'Invalid file extension'];
    }

    return ['valid' => true, 'extension' => $extension];
}

function saveUploadedImage($file, $directory, $prefix = 'img') {
    $validation = validateImageFile($file);

    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['error']];
    }

    ensureUploadDirExists($directory);

    // Generate unique filename
    $extension = $validation['extension'];
    $filename = $prefix . '_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $directory . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file'];
    }

    // Return relative path from web root
    $relativePath = str_replace(__DIR__ . '/', '', $filepath);

    return [
        'success' => true,
        'path' => $relativePath,
        'filename' => $filename
    ];
}

function saveBase64Image($base64Data, $directory, $prefix = 'img') {
    // Check if it's a data URI
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
        $imageType = strtolower($matches[1]);
        $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);

        // Validate image type
        if (!in_array($imageType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return ['success' => false, 'error' => 'Invalid image type'];
        }

        $extension = $imageType === 'jpg' ? 'jpeg' : $imageType;
    } else {
        return ['success' => false, 'error' => 'Invalid base64 image format'];
    }

    $imageData = base64_decode($base64Data);

    if ($imageData === false) {
        return ['success' => false, 'error' => 'Failed to decode base64 image'];
    }

    // Check file size
    if (strlen($imageData) > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'Image size exceeds maximum allowed size (5MB)'];
    }

    ensureUploadDirExists($directory);

    // Generate unique filename
    $filename = $prefix . '_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $directory . '/' . $filename;

    // Save file
    if (file_put_contents($filepath, $imageData) === false) {
        return ['success' => false, 'error' => 'Failed to save image file'];
    }

    // Return relative path from web root
    $relativePath = str_replace(__DIR__ . '/', '', $filepath);

    return [
        'success' => true,
        'path' => $relativePath,
        'filename' => $filename
    ];
}

function deleteImageFile($path) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath) && is_file($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}
?>
