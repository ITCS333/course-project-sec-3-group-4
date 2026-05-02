<?php
/**
 * Discussion Board API - Final Corrected Version
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include shared database connection
require_once __DIR__ . '/../../common/db.php';

// Get the PDO connection
$db = getDBConnection();

// Read request method
$method = $_SERVER['REQUEST_METHOD'];

// Read and decode JSON body
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

// Read query parameters
$action  = $_GET['action']  ?? null;
$id      = $_GET['id']      ?? null;
$topicId = $_GET['topic_id'] ?? null;

// ============================================================================
// TOPICS FUNCTIONS
// ============================================================================

function getAllTopics(PDO $db): void
{
    $query = "SELECT id, subject, message, author, created_at FROM topics";
    $params = [];

    if (!empty($_GET['search'])) {
        $search = $_GET['search'];
        $query .= " WHERE subject LIKE :search OR message LIKE :search OR author LIKE :search";
        $params['search'] = '%' . $search . '%';
    }

    $allowedSort = ['subject', 'author', 'created_at'];
    $sort = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'created_at';
    $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

    $query .= " ORDER BY $sort $order";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $topics]);
}

function getTopicById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, subject, message, author, created_at FROM topics WHERE id = ?");
    $stmt->execute([(int)$id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($topic) {
        sendResponse(['success' => true, 'data' => $topic]);
    } else {
        sendResponse(['success' => false, 'message' => 'Topic not found'], 404);
    }
}

function createTopic(PDO $db, array $data): void
{
    if (empty($data['subject']) || empty($data['message']) || empty($data['author'])) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    $subject = sanitizeInput($data['subject']);
    $message = sanitizeInput($data['message']);
    $author  = sanitizeInput($data['author']);

    $stmt = $db->prepare("INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)");
    $stmt->execute([$subject, $message, $author]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Created', 'id' => (int)$db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function updateTopic(PDO $db, array $data): void
{
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Missing ID'], 400);
    }

    $id = (int)$data['id'];

    // CRITICAL FIX: Check if topic exists to return 404 if unknown ID
    $checkStmt = $db->prepare("SELECT id FROM topics WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found'], 404);
    }

    $fields = [];
    $params = [];

    if (isset($data['subject'])) {
        $fields[] = "subject = ?";
        $params[] = sanitizeInput($data['subject']);
    }
    if (isset($data['message'])) {
        $fields[] = "message = ?";
        $params[] = sanitizeInput($data['message']);
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'Nothing to update'], 400);
    }

    $params[] = $id;
    $stmt = $db->prepare("UPDATE topics SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($params);

    sendResponse(['success' => true, 'message' => 'Updated']);
}

function deleteTopic(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("DELETE FROM topics WHERE id = ?");
    $stmt->execute([(int)$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Topic not found'], 404);
    }
}

// ============================================================================
// REPLIES FUNCTIONS
// ============================================================================

function getRepliesByTopicId(PDO $db, $topicId): void
{
    if (!$topicId || !is_numeric($topicId)) {
        sendResponse(['success' => false, 'message' => 'Invalid Topic ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, topic_id, text, author, created_at FROM replies WHERE topic_id = ? ORDER BY created_at ASC");
    $stmt->execute([(int)$topicId]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $replies]);
}

function createReply(PDO $db, array $data): void
{
    if (empty($data['topic_id']) || empty($data['text']) || empty($data['author'])) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    $topicId = (int)$data['topic_id'];
    $text    = sanitizeInput($data['text']);
    $author  = sanitizeInput($data['author']);

    $check = $db->prepare("SELECT id FROM topics WHERE id = ?");
    $check->execute([$topicId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found'], 404);
    }

    $stmt = $db->prepare("INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)");
    $stmt->execute([$topicId, $text, $author]);

    if ($stmt->rowCount() > 0) {
        $newId = (int)$db->lastInsertId();
        $get = $db->prepare("SELECT * FROM replies WHERE id = ?");
        $get->execute([$newId]);
        sendResponse(['success' => true, 'message' => 'Reply added', 'id' => $newId, 'data' => $get->fetch(PDO::FETCH_ASSOC)], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function deleteReply(PDO $db, $replyId): void
{
    if (!$replyId || !is_numeric($replyId)) {
        sendResponse(['success' => false, 'message' => 'Invalid Reply ID'], 400);
    }

    $stmt = $db->prepare("DELETE FROM replies WHERE id = ?");
    $stmt->execute([(int)$replyId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Reply deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Reply not found'], 404);
    }
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'replies') {
            getRepliesByTopicId($db, $topicId);
        } elseif ($id) {
            getTopicById($db, $id);
        } else {
            getAllTopics($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'reply') {
            createReply($db, $data);
        } else {
            createTopic($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateTopic($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_reply') {
            deleteReply($db, $id);
        } else {
            deleteTopic($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
