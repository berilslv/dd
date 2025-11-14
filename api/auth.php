<?php
require_once __DIR__ . '/../config.php';

setJSONHeaders();
startSecureSession();

$method = $_SERVER['REQUEST_METHOD'];

// POST - Login
if ($method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['username']) || !isset($data['password'])) {
            sendJSON(['success' => false, 'error' => 'Missing username or password'], 400);
        }

        $username = sanitizeInput($data['username']);
        $password = $data['password'];

        // Read users from JSON file
        $users = readJSONFile(USERS_FILE);

        // Find user
        $user = null;
        foreach ($users as $u) {
            if ($u['username'] === $username) {
                $user = $u;
                break;
            }
        }

        if (!$user) {
            sendJSON(['success' => false, 'error' => 'Invalid credentials'], 401);
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            sendJSON(['success' => false, 'error' => 'Invalid credentials'], 401);
        }

        // Set session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['login_time'] = time();

        sendJSON([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ]
        ]);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// GET - Check session status
elseif ($method === 'GET') {
    if (isAdminLoggedIn()) {
        sendJSON([
            'success' => true,
            'loggedIn' => true,
            'user' => [
                'id' => $_SESSION['admin_id'] ?? null,
                'username' => $_SESSION['admin_username'] ?? null
            ]
        ]);
    } else {
        sendJSON([
            'success' => true,
            'loggedIn' => false
        ]);
    }
}

// DELETE - Logout
elseif ($method === 'DELETE') {
    session_unset();
    session_destroy();

    sendJSON([
        'success' => true,
        'message' => 'Logout successful'
    ]);
}

else {
    sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
}
?>
