<?php
require_once __DIR__ . '/../config.php';

setJSONHeaders();
startSecureSession();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// POST - Upload image(s)
if ($method === 'POST') {
    try {
        $uploadedPaths = [];

        // Handle multipart/form-data file uploads
        if (isset($_FILES['images'])) {
            $files = $_FILES['images'];

            // Handle multiple files
            if (is_array($files['name'])) {
                $fileCount = count($files['name']);

                for ($i = 0; $i < $fileCount; $i++) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        sendJSON([
                            'success' => false,
                            'error' => 'Upload error: ' . $file['name']
                        ], 400);
                    }

                    $result = saveUploadedImage($file, LAPTOP_IMAGES_DIR, 'laptop');

                    if (!$result['success']) {
                        sendJSON([
                            'success' => false,
                            'error' => $result['error']
                        ], 400);
                    }

                    $uploadedPaths[] = $result['path'];
                }
            } else {
                // Handle single file
                $result = saveUploadedImage($files, LAPTOP_IMAGES_DIR, 'laptop');

                if (!$result['success']) {
                    sendJSON([
                        'success' => false,
                        'error' => $result['error']
                    ], 400);
                }

                $uploadedPaths[] = $result['path'];
            }
        }
        // Handle JSON base64 encoded images
        elseif ($_SERVER['CONTENT_TYPE'] === 'application/json' ||
                 strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['images'])) {
                sendJSON([
                    'success' => false,
                    'error' => 'No images provided'
                ], 400);
            }

            $images = is_array($data['images']) ? $data['images'] : [$data['images']];

            foreach ($images as $imageData) {
                $result = saveBase64Image($imageData, LAPTOP_IMAGES_DIR, 'laptop');

                if (!$result['success']) {
                    sendJSON([
                        'success' => false,
                        'error' => $result['error']
                    ], 400);
                }

                $uploadedPaths[] = $result['path'];
            }
        } else {
            sendJSON([
                'success' => false,
                'error' => 'No images provided'
            ], 400);
        }

        sendJSON([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'paths' => $uploadedPaths
        ], 201);

    } catch (Exception $e) {
        sendJSON([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

// DELETE - Delete image
elseif ($method === 'DELETE') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['path'])) {
            sendJSON([
                'success' => false,
                'error' => 'No image path provided'
            ], 400);
        }

        $deleted = deleteImageFile($data['path']);

        if (!$deleted) {
            sendJSON([
                'success' => false,
                'error' => 'Failed to delete image file'
            ], 500);
        }

        sendJSON([
            'success' => true,
            'message' => 'Image deleted successfully'
        ]);

    } catch (Exception $e) {
        sendJSON([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

else {
    sendJSON([
        'success' => false,
        'error' => 'Method not allowed'
    ], 405);
}
?>
