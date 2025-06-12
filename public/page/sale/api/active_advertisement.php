<?php
ob_start();
session_start();
require_once __DIR__ . '../../../../config/database_server.php';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Set headers
header('Content-Type: application/json; charset=UTF-8');

// Set timezone
date_default_timezone_set('Asia/Bangkok');

// CSRF token validation function
function validate_csrf_token() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SERVER['HTTP_X_CSRF_TOKEN']) ||
        !hash_equals($_SESSION['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle file upload
function handle_file_upload($file, $upload_dir = 'uploads/ads/') {
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => 'error', 'message' => 'File upload error: ' . $file['error']];
    }

    if (!in_array($file['type'], $allowed_types) || !getimagesize($file['tmp_name'])) {
        return ['status' => 'error', 'message' => 'Invalid file type. Only JPEG/PNG allowed'];
    }

    if ($file['size'] > $max_size) {
        return ['status' => 'error', 'message' => 'File size exceeds 2MB'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'ad_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['status' => 'success', 'path' => $destination];
    }
    return ['status' => 'error', 'message' => 'Failed to move uploaded file'];
}

// Handle DELETE file
function delete_file($file_path) {
    if (file_exists($file_path) && is_file($file_path)) {
        unlink($file_path);
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    $endpoint = end($segments);
    $id = isset($segments[array_search('advertisements', $segments) + 1]) ? (int)$segments[array_search('advertisements', $segments) + 1] : null;

    switch ($method) {
        case 'GET':
            if ($endpoint === 'active_advertisements') {
                // Get active advertisements
                $query = "
                    SELECT sale_advertisement_id, sale_advertisement_image_path
                    FROM sale_advertisement
                    WHERE CURRENT_DATE BETWEEN sale_advertisement_start_date AND sale_advertisement_end_date
                    ORDER BY sale_advertisement_priority DESC
                    LIMIT 5
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute();
                $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['status' => 'success', 'data' => $ads]);
            } else {
                // Get all advertisements (for admin)
                $query = "
                    SELECT sale_advertisement_id, sale_advertisement_image_path,
                           sale_advertisement_start_date, sale_advertisement_end_date,
                           sale_advertisement_priority, sale_advertisement_created_at,
                           sale_advertisement_updated_at
                    FROM sale_advertisement
                    ORDER BY sale_advertisement_priority DESC, sale_advertisement_created_at DESC
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute();
                $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['status' => 'success', 'data' => $ads]);
            }
            break;

        case 'POST':
            if ($endpoint !== 'advertisements') {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
                exit;
            }

            validate_csrf_token();

            // Validate input
            if (!isset($_FILES['ad_image']) || !isset($_POST['sale_advertisement_start_date']) || !isset($_POST['sale_advertisement_end_date']) || !isset($_POST['sale_advertisement_priority'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
                exit;
            }

            $sale_advertisement_start_date = DateTime::createFromFormat('Y-m-d', $_POST['sale_advertisement_start_date']);
            $sale_advertisement_end_date = DateTime::createFromFormat('Y-m-d', $_POST['sale_advertisement_end_date']);
            $sale_advertisement_priority = (int)$_POST['sale_advertisement_priority'];

            if (!$sale_advertisement_start_date || !$sale_advertisement_end_date || $sale_advertisement_start_date > $sale_advertisement_end_date) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid date range']);
                exit;
            }

            if ($sale_advertisement_priority < 1 || $sale_advertisement_priority > 100) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Priority must be between 1 and 100']);
                exit;
            }

            // Handle file upload
            $upload_result = handle_file_upload($_FILES['ad_image']);
            if ($upload_result['status'] === 'error') {
                http_response_code(400);
                echo json_encode($upload_result);
                exit;
            }

            // Save to database
            $query = "
                INSERT INTO sale_advertisement (
                    sale_advertisement_image_path, sale_advertisement_start_date,
                    sale_advertisement_end_date, sale_advertisement_priority
                ) VALUES (:sale_advertisement_image_path, :sale_advertisement_start_date, :sale_advertisement_end_date, :sale_advertisement_priority)
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':sale_advertisement_image_path' => $upload_result['path'],
                ':sale_advertisement_start_date' => $sale_advertisement_start_date->format('Y-m-d'),
                ':sale_advertisement_end_date' => $sale_advertisement_end_date->format('Y-m-d'),
                ':sale_advertisement_priority' => $sale_advertisement_priority
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Advertisement created successfully']);
            break;

        case 'PUT':
            if ($endpoint !== 'advertisements' || !$id) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Invalid endpoint or ID']);
                exit;
            }

            validate_csrf_token();

            // Get existing advertisement
            $query = "SELECT sale_advertisement_image_path FROM sale_advertisement WHERE sale_advertisement_id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':id' => $id]);
            $sale_advertisement = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale_advertisement) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Advertisement not found']);
                exit;
            }

            // Parse input
            $input = json_decode(file_get_contents('php://input'), true);
            $sale_advertisement_start_date = isset($input['sale_advertisement_start_date']) ? DateTime::createFromFormat('Y-m-d', $input['sale_advertisement_start_date']) : null;
            $sale_advertisement_end_date = isset($input['sale_advertisement_end_date']) ? DateTime::createFromFormat('Y-m-d', $input['sale_advertisement_end_date']) : null;
            $sale_advertisement_priority = isset($input['sale_advertisement_priority']) ? (int)$input['sale_advertisement_priority'] : null;

            if (($sale_advertisement_start_date && $sale_advertisement_end_date && $sale_advertisement_start_date > $sale_advertisement_end_date) || 
                ($sale_advertisement_priority && ($sale_advertisement_priority < 1 || $sale_advertisement_priority > 100))) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
                exit;
            }

            $update_fields = [];
            $params = [':id' => $id];

            if ($sale_advertisement_start_date) {
                $update_fields[] = "sale_advertisement_start_date = :sale_advertisement_start_date";
                $params[':sale_advertisement_start_date'] = $sale_advertisement_start_date->format('Y-m-d');
            }
            if ($sale_advertisement_end_date) {
                $update_fields[] = "sale_advertisement_end_date = :sale_advertisement_end_date";
                $params[':sale_advertisement_end_date'] = $sale_advertisement_end_date->format('Y-m-d');
            }
            if ($sale_advertisement_priority) {
                $update_fields[] = "sale_advertisement_priority = :sale_advertisement_priority";
                $params[':sale_advertisement_priority'] = $sale_advertisement_priority;
            }

            // Handle file upload if provided
            if (isset($_FILES['ad_image'])) {
                $upload_result = handle_file_upload($_FILES['ad_image']);
                if ($upload_result['status'] === 'error') {
                    http_response_code(400);
                    echo json_encode($upload_result);
                    exit;
                }
                $update_fields[] = "sale_advertisement_image_path = :image_path";
                $params[':image_path'] = $upload_result['path'];
                delete_file($sale_advertisement['sale_advertisement_image_path']); // Delete old file
            }

            if (empty($update_fields)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No fields to update']);
                exit;
            }

            $query = "UPDATE sale_advertisement SET " . implode(', ', $update_fields) . " WHERE sale_advertisement_id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            echo json_encode(['status' => 'success', 'message' => 'Advertisement updated successfully']);
            break;

        case 'DELETE':
            if ($endpoint !== 'advertisements' || !$id) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Invalid endpoint or ID']);
                exit;
            }

            validate_csrf_token();

            // Get image path to delete file
            $query = "SELECT sale_advertisement_image_path FROM sale_advertisement WHERE sale_advertisement_id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':id' => $id]);
            $sale_advertisement = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale_advertisement) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Advertisement not found']);
                exit;
            }

            // Delete from database
            $query = "DELETE FROM sale_advertisement WHERE sale_advertisement_id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':id' => $id]);

            // Delete file
            delete_file($sale_advertisement['sale_advertisement_image_path']);

            echo json_encode(['status' => 'success', 'message' => 'Advertisement deleted successfully']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            break;
    }
} catch (PDOException $e) {
    error_log("PDOException in api_advertisements.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Exception in api_advertisements.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}

ob_end_flush();
?>