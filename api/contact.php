<?php
require_once __DIR__ . '/../config.php';

setJSONHeaders();
startSecureSession();

$method = $_SERVER['REQUEST_METHOD'];

// POST - Submit contact form
if ($method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            sendJSON(['success' => false, 'error' => 'Invalid JSON data'], 400);
        }

        // Validate required fields
        $required = ['name', 'email', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendJSON(['success' => false, 'error' => "Missing required field: $field"], 400);
            }
        }

        // Validate email
        if (!isValidEmail($data['email'])) {
            sendJSON(['success' => false, 'error' => 'Invalid email address'], 400);
        }

        // Read existing contacts
        $contacts = readJSONFile(CONTACTS_FILE);

        // Create new contact
        $contactId = getNextId($contacts);
        $newContact = [
            'id' => $contactId,
            'name' => sanitizeInput($data['name']),
            'email' => sanitizeInput($data['email']),
            'phone' => sanitizeInput($data['phone'] ?? ''),
            'message' => sanitizeInput($data['message']),
            'created_at' => date('Y-m-d H:i:s'),
            'read' => false
        ];

        // Add to contacts array
        $contacts[] = $newContact;

        // Save to file
        if (!writeJSONFile(CONTACTS_FILE, $contacts)) {
            sendJSON(['success' => false, 'error' => 'Failed to save contact message'], 500);
        }

        sendJSON([
            'success' => true,
            'message' => 'Contact message sent successfully',
            'id' => $contactId
        ], 201);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// GET - Get all contacts (admin only)
elseif ($method === 'GET') {
    requireAdmin();

    try {
        $contacts = readJSONFile(CONTACTS_FILE);

        // Sort by created_at descending
        usort($contacts, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        sendJSON([
            'success' => true,
            'contacts' => $contacts
        ]);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// PUT - Mark contact as read (admin only)
elseif ($method === 'PUT') {
    requireAdmin();

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['id'])) {
            sendJSON(['success' => false, 'error' => 'Missing contact ID'], 400);
        }

        $id = (int)$data['id'];

        // Read existing contacts
        $contacts = readJSONFile(CONTACTS_FILE);

        // Find and update contact
        $found = false;
        foreach ($contacts as &$contact) {
            if ($contact['id'] == $id) {
                $contact['read'] = true;
                $found = true;
                break;
            }
        }

        if (!$found) {
            sendJSON(['success' => false, 'error' => 'Contact not found'], 404);
        }

        // Save to file
        if (!writeJSONFile(CONTACTS_FILE, $contacts)) {
            sendJSON(['success' => false, 'error' => 'Failed to update contact'], 500);
        }

        sendJSON([
            'success' => true,
            'message' => 'Contact marked as read'
        ]);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// DELETE - Delete contact (admin only)
elseif ($method === 'DELETE') {
    requireAdmin();

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['id'])) {
            sendJSON(['success' => false, 'error' => 'Missing contact ID'], 400);
        }

        $id = (int)$data['id'];

        // Read existing contacts
        $contacts = readJSONFile(CONTACTS_FILE);

        // Find and remove contact
        $found = false;
        foreach ($contacts as $index => $contact) {
            if ($contact['id'] == $id) {
                array_splice($contacts, $index, 1);
                $found = true;
                break;
            }
        }

        if (!$found) {
            sendJSON(['success' => false, 'error' => 'Contact not found'], 404);
        }

        // Save to file
        if (!writeJSONFile(CONTACTS_FILE, $contacts)) {
            sendJSON(['success' => false, 'error' => 'Failed to delete contact'], 500);
        }

        sendJSON([
            'success' => true,
            'message' => 'Contact deleted successfully'
        ]);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

else {
    sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
}
?>
