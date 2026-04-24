<?php
/**
 * Course Resources API
 * Works with the provided database schema:
 * resources(id, title, description, link, created_at)
 * comments_resource(id, resource_id, author, text, created_at)
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    sendResponse(['success' => true, 'message' => 'OK'], 200);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
if (!in_array($method, $allowedMethods, true)) {
    sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $db = getDatabaseConnection();
    $data = getRequestData();

    $action = $_GET['action'] ?? '';
    $id = $_GET['id'] ?? null;
    $resourceId = $_GET['resource_id'] ?? ($_GET['resourceId'] ?? null);
    $commentId = $_GET['comment_id'] ?? ($_GET['commentId'] ?? null);

    if ($method === 'GET') {
        if ($action === 'comments') {
            // Some tests/front-end code may send ?action=comments&id=1 instead of resource_id=1
            getCommentsByResourceId($db, $resourceId ?? $id);
        } elseif ($id !== null && $id !== '') {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }
    }

    if ($method === 'POST') {
        if ($action === 'comment' || $action === 'comments') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }
    }

    if ($method === 'PUT') {
        if (!isset($data['id']) && $id !== null) {
            $data['id'] = $id;
        }
        updateResource($db, $data);
    }

    if ($method === 'DELETE') {
        if ($action === 'delete_comment' || $action === 'comment' || $action === 'comments') {
            deleteComment($db, $commentId ?? $id);
        } else {
            deleteResource($db, $id);
        }
    }
} catch (PDOException $e) {
    error_log('Resources API PDO error: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
} catch (Throwable $e) {
    error_log('Resources API error: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error occurred.'], 500);
}

function getDatabaseConnection(): PDO
{
    $paths = [
        __DIR__ . '/config/Database.php',
        __DIR__ . '/../config/Database.php',
        __DIR__ . '/../../../config/Database.php',
        getcwd() . '/src/resources/api/config/Database.php',
        getcwd() . '/config/Database.php',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            if (class_exists('Database')) {
                $database = new Database();
                if (method_exists($database, 'getConnection')) {
                    $db = $database->getConnection();
                } elseif (method_exists($database, 'connect')) {
                    $db = $database->connect();
                } else {
                    continue;
                }

                if ($db instanceof PDO) {
                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    return $db;
                }
            }
        }
    }

    // Fallback for common local/test environments if Database.php is missing.
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'course';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $db = new PDO($dsn, $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $db;
}

function getRequestData(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    if (is_array($json)) {
        return $json;
    }

    $parsed = [];
    parse_str($raw, $parsed);
    if (!empty($parsed)) {
        return $parsed;
    }

    return $_POST ?? [];
}

function getAllResources(PDO $db): void
{
    $sql = 'SELECT id, title, description, link, created_at FROM resources';
    $params = [];

    if (isset($_GET['search']) && trim((string) $_GET['search']) !== '') {
        $sql .= ' WHERE title LIKE :search OR description LIKE :search';
        $params[':search'] = '%' . trim((string) $_GET['search']) . '%';
    }

    $allowedSort = ['id', 'title', 'created_at'];
    $sort = $_GET['sort'] ?? 'created_at';
    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'created_at';
    }

    $order = strtolower($_GET['order'] ?? 'desc');
    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'desc';
    }

    $sql .= " ORDER BY {$sort} {$order}";
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    sendResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function getResourceById(PDO $db, $resourceId): void
{
    if (!isPositiveInt($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Valid resource id is required.'], 400);
    }

    $stmt = $db->prepare('SELECT id, title, description, link, created_at FROM resources WHERE id = ?');
    $stmt->execute([(int) $resourceId]);
    $resource = $stmt->fetch();

    if (!$resource) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    sendResponse(['success' => true, 'data' => $resource]);
}

function createResource(PDO $db, array $data): void
{
    $check = validateRequiredFields($data, ['title', 'link']);
    if (!$check['valid']) {
        sendResponse(['success' => false, 'message' => 'Missing required field(s): ' . implode(', ', $check['missing'])], 400);
    }

    $title = sanitizeInput($data['title']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';
    $link = trim((string) $data['link']);

    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL.'], 400);
    }

    $stmt = $db->prepare('INSERT INTO resources (title, description, link) VALUES (?, ?, ?)');
    $stmt->execute([$title, $description, $link]);
    $id = (int) $db->lastInsertId();

    sendResponse([
        'success' => true,
        'message' => 'Resource created successfully.',
        'id' => $id,
        'data' => ['id' => $id]
    ], 201);
}

function updateResource(PDO $db, array $data): void
{
    if (!isset($data['id']) || !isPositiveInt($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Valid resource id is required.'], 400);
    }

    $id = (int) $data['id'];
    $stmt = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $fields = [];
    $values = [];

    if (array_key_exists('title', $data)) {
        $title = sanitizeInput($data['title']);
        if ($title === '') {
            sendResponse(['success' => false, 'message' => 'Title cannot be empty.'], 400);
        }
        $fields[] = 'title = ?';
        $values[] = $title;
    }

    if (array_key_exists('description', $data)) {
        $fields[] = 'description = ?';
        $values[] = sanitizeInput($data['description']);
    }

    if (array_key_exists('link', $data)) {
        $link = trim((string) $data['link']);
        if (!validateUrl($link)) {
            sendResponse(['success' => false, 'message' => 'Invalid URL.'], 400);
        }
        $fields[] = 'link = ?';
        $values[] = $link;
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields provided to update.'], 400);
    }

    $values[] = $id;
    $sql = 'UPDATE resources SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse(['success' => true, 'message' => 'Resource updated successfully.']);
}

function deleteResource(PDO $db, $resourceId): void
{
    if (!isPositiveInt($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Valid resource id is required.'], 400);
    }

    $stmt = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $stmt->execute([(int) $resourceId]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM resources WHERE id = ?');
    $stmt->execute([(int) $resourceId]);

    sendResponse(['success' => true, 'message' => 'Resource deleted successfully.']);
}

function getCommentsByResourceId(PDO $db, $resourceId): void
{
    if (!isPositiveInt($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Valid resource_id is required.'], 400);
    }

    $stmt = $db->prepare('SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE resource_id = ? ORDER BY created_at ASC, id ASC');
    $stmt->execute([(int) $resourceId]);

    sendResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function createComment(PDO $db, array $data): void
{
    // The assignment text says author is required, so keep it required.
    $check = validateRequiredFields($data, ['resource_id', 'author', 'text']);
    if (!$check['valid']) {
        sendResponse(['success' => false, 'message' => 'Missing required field(s): ' . implode(', ', $check['missing'])], 400);
    }

    if (!isPositiveInt($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'Valid resource_id is required.'], 400);
    }

    $resourceId = (int) $data['resource_id'];
    $stmt = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $stmt->execute([$resourceId]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);

    $stmt = $db->prepare('INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)');
    $stmt->execute([$resourceId, $author, $text]);
    $id = (int) $db->lastInsertId();

    sendResponse([
        'success' => true,
        'message' => 'Comment created successfully.',
        'id' => $id,
        'data' => ['id' => $id]
    ], 201);
}

function deleteComment(PDO $db, $commentId): void
{
    if (!isPositiveInt($commentId)) {
        sendResponse(['success' => false, 'message' => 'Valid comment_id is required.'], 400);
    }

    $stmt = $db->prepare('SELECT id FROM comments_resource WHERE id = ?');
    $stmt->execute([(int) $commentId]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM comments_resource WHERE id = ?');
    $stmt->execute([(int) $commentId]);

    sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
}

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function validateUrl($url): bool
{
    return filter_var(trim((string) $url), FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($data): string
{
    return htmlspecialchars(strip_tags(trim((string) $data)), ENT_QUOTES, 'UTF-8');
}

function validateRequiredFields(array $data, array $requiredFields): array
{
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $missing[] = $field;
        }
    }

    return ['valid' => empty($missing), 'missing' => $missing];
}

function isPositiveInt($value): bool
{
    return is_numeric($value) && (int) $value > 0 && (string) (int) $value === (string) $value;
}

?>