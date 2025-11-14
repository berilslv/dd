<?php
require_once __DIR__ . '/../config.php';

setJSONHeaders();
startSecureSession();

$method = $_SERVER['REQUEST_METHOD'];

// GET - Fetch all laptops or single laptop
if ($method === 'GET') {
    try {
        $laptops = readJSONFile(LAPTOPS_FILE);

        if (isset($_GET['id'])) {
            // Get single laptop
            $id = (int)$_GET['id'];

            $laptop = null;
            foreach ($laptops as $item) {
                if ($item['id'] == $id) {
                    $laptop = $item;
                    break;
                }
            }

            if (!$laptop) {
                sendJSON(['success' => false, 'error' => 'Laptop not found'], 404);
            }

            // Ensure imageUrl is set for backwards compatibility
            if (!isset($laptop['imageUrl']) && isset($laptop['images'][0])) {
                $laptop['imageUrl'] = $laptop['images'][0];
            }

            sendJSON(['success' => true, 'laptop' => $laptop]);
        } else {
            // Get all laptops
            // Ensure each laptop has imageUrl set
            foreach ($laptops as &$laptop) {
                if (!isset($laptop['imageUrl']) && isset($laptop['images'][0])) {
                    $laptop['imageUrl'] = $laptop['images'][0];
                }
            }

            sendJSON(['success' => true, 'laptops' => $laptops]);
        }
    } catch (Exception $e) {
        sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// POST - Create new laptop
elseif ($method === 'POST') {
    requireAdmin();

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            sendJSON(['success' => false, 'error' => 'Invalid JSON data'], 400);
        }

        // Validate required fields
        $required = ['brand', 'model', 'processor', 'ram', 'storage', 'screen', 'price'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendJSON(['success' => false, 'error' => "Missing required field: $field"], 400);
            }
        }

        if (empty($data['images']) || !is_array($data['images'])) {
            sendJSON(['success' => false, 'error' => 'At least one image is required'], 400);
        }

        // Read existing laptops
        $laptops = readJSONFile(LAPTOPS_FILE);

        // Create new laptop
        $laptopId = getNextId($laptops);
        $newLaptop = [
            'id' => $laptopId,
            'brand' => sanitizeInput($data['brand']),
            'model' => sanitizeInput($data['model']),
            'processor' => sanitizeInput($data['processor']),
            'ram' => sanitizeInput($data['ram']),
            'storage' => sanitizeInput($data['storage']),
            'screen' => sanitizeInput($data['screen']),
            'price' => (float)$data['price'],
            'condition' => sanitizeInput($data['condition'] ?? 'good'),
            'description' => sanitizeInput($data['description'] ?? ''),
            'visualRating' => (int)($data['visualRating'] ?? 7),
            'technicalRating' => (int)($data['technicalRating'] ?? 8),
            'images' => $data['images'],
            'imageUrl' => $data['images'][0],
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Add to laptops array
        $laptops[] = $newLaptop;

        // Save to file
        if (!writeJSONFile(LAPTOPS_FILE, $laptops)) {
            sendJSON(['success' => false, 'error' => 'Failed to save laptop'], 500);
        }

        sendJSON([
            'success' => true,
            'message' => 'Laptop created successfully',
            'id' => $laptopId
        ], 201);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// PUT - Update laptop
elseif ($method === 'PUT') {
    requireAdmin();

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['id'])) {
            sendJSON(['success' => false, 'error' => 'Invalid data or missing ID'], 400);
        }

        $id = (int)$data['id'];

        // Read existing laptops
        $laptops = readJSONFile(LAPTOPS_FILE);

        // Find laptop index
        $laptopIndex = -1;
        foreach ($laptops as $index => $laptop) {
            if ($laptop['id'] == $id) {
                $laptopIndex = $index;
                break;
            }
        }

        if ($laptopIndex === -1) {
            sendJSON(['success' => false, 'error' => 'Laptop not found'], 404);
        }

        // Update laptop data
        $laptops[$laptopIndex]['brand'] = sanitizeInput($data['brand']);
        $laptops[$laptopIndex]['model'] = sanitizeInput($data['model']);
        $laptops[$laptopIndex]['processor'] = sanitizeInput($data['processor']);
        $laptops[$laptopIndex]['ram'] = sanitizeInput($data['ram']);
        $laptops[$laptopIndex]['storage'] = sanitizeInput($data['storage']);
        $laptops[$laptopIndex]['screen'] = sanitizeInput($data['screen']);
        $laptops[$laptopIndex]['price'] = (float)$data['price'];
        $laptops[$laptopIndex]['condition'] = sanitizeInput($data['condition'] ?? 'good');
        $laptops[$laptopIndex]['description'] = sanitizeInput($data['description'] ?? '');
        $laptops[$laptopIndex]['visualRating'] = (int)($data['visualRating'] ?? 7);
        $laptops[$laptopIndex]['technicalRating'] = (int)($data['technicalRating'] ?? 8);

        // Update images if provided
        if (isset($data['images']) && is_array($data['images'])) {
            $laptops[$laptopIndex]['images'] = $data['images'];
            $laptops[$laptopIndex]['imageUrl'] = $data['images'][0] ?? '';
        }

        $laptops[$laptopIndex]['updated_at'] = date('Y-m-d H:i:s');

        // Save to file
        if (!writeJSONFile(LAPTOPS_FILE, $laptops)) {
            sendJSON(['success' => false, 'error' => 'Failed to update laptop'], 500);
        }

        sendJSON([
            'success' => true,
            'message' => 'Laptop updated successfully'
        ]);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

// DELETE - Delete laptop
elseif ($method === 'DELETE') {
    requireAdmin();

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['id'])) {
            sendJSON(['success' => false, 'error' => 'Missing laptop ID'], 400);
        }

        $id = (int)$data['id'];

        // Read existing laptops
        $laptops = readJSONFile(LAPTOPS_FILE);

        // Find and remove laptop
        $found = false;
        foreach ($laptops as $index => $laptop) {
            if ($laptop['id'] == $id) {
                array_splice($laptops, $index, 1);
                $found = true;
                break;
            }
        }

        if (!$found) {
            sendJSON(['success' => false, 'error' => 'Laptop not found'], 404);
        }

        // Save to file
        if (!writeJSONFile(LAPTOPS_FILE, $laptops)) {
            sendJSON(['success' => false, 'error' => 'Failed to delete laptop'], 500);
        }

        sendJSON([
            'success' => true,
            'message' => 'Laptop deleted successfully'
        ]);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

else {
    sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
}
?>
