<?php
session_start();
$_SESSION['initialized'] = true;
/**
 * Weekly Course Breakdown API
 *
 * RESTful API for CRUD operations on weekly course content and discussion
 * comments. Uses PDO to interact with the MySQL database defined in
 * schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: weeks
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   title       VARCHAR(200)  NOT NULL
 *   start_date  DATE          NOT NULL
 *   description TEXT
 *   links       TEXT          — JSON-encoded array of URL strings
 *   created_at  TIMESTAMP
 *   updated_at  TIMESTAMP
 *
 * Table: comments_week
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   week_id     INT UNSIGNED  NOT NULL   — FK → weeks.id (ON DELETE CASCADE)
 *   author      VARCHAR(100)  NOT NULL
 *   text        TEXT          NOT NULL
 *   created_at  TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve week(s) or comments
 *   POST   — Create a new week or comment
 *   PUT    — Update an existing week
 *   DELETE — Delete a week (cascade removes its comments) or a single comment
 *
 * URL scheme (all requests go to index.php):
 *
 *   Weeks:
 *     GET    ./api/index.php                  — list all weeks
 *     GET    ./api/index.php?id={id}           — get one week by integer id
 *     POST   ./api/index.php                  — create a new week
 *     PUT    ./api/index.php                  — update a week (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete a week
 *
 *   Comments (action parameter selects the comments sub-resource):
 *     GET    ./api/index.php?action=comments&week_id={id}
 *                                             — list comments for a week
 *     POST   ./api/index.php?action=comment   — create a comment
 *     DELETE ./api/index.php?action=delete_comment&comment_id={id}
 *                                             — delete a single comment
 *
 * Query parameters for GET all weeks:
 *   search — filter rows where title LIKE or description LIKE the term
 *   sort   — column to sort by; allowed: title, start_date (default: start_date)
 *   order  — sort direction; allowed: asc, desc (default: asc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// TODO: Set headers for JSON response and CORS.
// Set Content-Type to application/json.
// Allow cross-origin requests (CORS) if needed.
// Allow HTTP methods: GET, POST, PUT, DELETE, OPTIONS.
// Allow headers: Content-Type, Authorization.
header('Content-Type: application/json; charset=utf-8');

$allowedMethods = 'GET, POST, PUT, DELETE, OPTIONS';
$allowedHeaders = 'Content-Type, Authorization';

// TODO: Handle preflight OPTIONS request.
// If the request method is OPTIONS, return HTTP 200 and exit.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// TODO: Include the shared database connection file.
// require_once __DIR__ . '/../../common/db.php';
require_once __DIR__ . '/../../common/db.php';

$database = new Database();
$db = $database->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// TODO: Get the PDO database connection.
// $db = getDBConnection();


// TODO: Read the HTTP request method.
// $method = $_SERVER['REQUEST_METHOD'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// TODO: Read and decode the request body for POST and PUT requests.
// $rawData = file_get_contents('php://input');
// $data    = json_decode($rawData, true) ?? [];


// TODO: Read query parameters.
// $action    = $_GET['action']     ?? null;  // 'comments', 'comment', 'delete_comment'
// $id        = $_GET['id']         ?? null;  // integer week id
// $weekId    = $_GET['week_id']    ?? null;  // integer week id for comments queries
// $commentId = $_GET['comment_id'] ?? null;  // integer comment id
$rawBody = file_get_contents('php://input');
$data = [];
if ($rawBody !== false && $rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $bodyData = $decoded;
    }
}
$resource = isset($_GET['resource']) ? strtolower(trim($_GET['resource'])) : 'weeks';

// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================

/**
 * Get all weeks (with optional search and sort).
 * Method: GET (no ?id or ?action parameter).
 *
 * Query parameters handled inside:
 *   search — filter by title LIKE or description LIKE
 *   sort   — allowed: title, start_date   (default: start_date)
 *   order  — allowed: asc, desc           (default: asc)
 *
 * Each week row in the response has links decoded from its JSON string
 * to a PHP array before encoding the final JSON output.
 */
function getAllWeeks(PDO $db): void
{
    // TODO: Build the base SELECT query.
    // SELECT id, title, start_date, description, links, created_at FROM weeks

    // TODO: If $_GET['search'] is provided and non-empty, append:
    // WHERE title LIKE :search OR description LIKE :search
    // Bind '%' . $search . '%' to :search.

    // TODO: Validate $_GET['sort'] against the whitelist [title, start_date].
    // Default to 'start_date' if missing or invalid.

    // TODO: Validate $_GET['order'] against [asc, desc].
    // Default to 'asc' if missing or invalid.

    // TODO: Append ORDER BY {sort} {order} to the query.

    // TODO: Prepare, bind (if searching), and execute the statement.

    // TODO: Fetch all rows as an associative array.

    // TODO: For each row, decode the links column:
    // $row['links'] = json_decode($row['links'], true) ?? [];

    // TODO: Call sendResponse(['success' => true, 'data' => $weeks]);
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sort   = isset($_GET['sort']) ? strtolower($_GET['sort']) : 'start_date';
    $order  = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';

     $allowedSortFields = ['title', 'start_date', 'created_at'];
    if (!in_array($sort, $allowedSortFields)) {
        $sort = 'start_date';
    }
    $order = ($order === 'desc') ? 'DESC' : 'ASC';

    $query = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];

    if ($search !== '') {
        $query .= " WHERE title LIKE ? OR description LIKE ?";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    $query .= " ORDER BY $sort $order";

    try {
        // Prepare and execute
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        // Fetch results
        $weeks = $stmt->fetchAll();

        // Decode links JSON
        foreach ($weeks as &$week) {
            $week['links'] = json_decode($week['links'], true) ?? [];
        }

        // Return response
        sendResponse(200, ['success' => true, 'data' => $weeks]);
    } catch (PDOException $e) {
        sendResponse(500, ['success' => false, 'error' => 'Failed to retrieve weeks']);
    }
}



/**
 * Get a single week by its integer primary key.
 * Method: GET with ?id={id}.
 *
 * Response (found):
 *   { "success": true, "data": { id, title, start_date, description,
 *                                 links, created_at } }
 * Response (not found): HTTP 404.
 */
function getWeekById(PDO $db, $id): void
{
    // TODO: Validate that $id is provided and numeric.
    // If not, call sendResponse with HTTP 400.
     if (!isset($id) || !is_numeric($id)) {
        sendResponse(400, ['success' => false, 'error' => 'Missing or invalid id']);
         return;
    }

    // TODO: SELECT id, title, start_date, description, links, created_at
    //       FROM weeks WHERE id = ?
    $query = "SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?";

    // TODO: Fetch one row. Decode the links JSON:
    // $week['links'] = json_decode($week['links'], true) ?? [];
  try {
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);  

        $week = $stmt->fetch();
    // TODO: If found, sendResponse success with the week.
    // If not found, sendResponse error with HTTP 404.
    if ($week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
        sendResponse(200, ['success' => true, 'data' => $week]);
         } else {
            sendResponse(404, ['success' => false, 'error' => 'Week not found']);
         }
         } catch (PDOException $e) {
            sendResponse(500, ['success' => false, 'error' => 'Failed to retrieve week']);
         }
}


/**
 * Create a new week.
 * Method: POST (no ?action parameter).
 *
 * Required JSON body fields:
 *   title       — string (required)
 *   start_date  — string "YYYY-MM-DD" (required)
 *   description — string (optional, defaults to "")
 *   links       — array of URL strings (optional, defaults to [])
 *
 * Response (success): HTTP 201 — { success, message, id }
 * Response (invalid start_date): HTTP 400.
 */
function createWeek(PDO $db, array $data): void
{
    // TODO: Validate that title and start_date are present and non-empty.
    // If missing, sendResponse HTTP 400.

    // TODO: Trim title, start_date, and description.

    // TODO: Validate start_date format using DateTime::createFromFormat('Y-m-d', $start_date).
    // If invalid, sendResponse HTTP 400.

    // TODO: Default description to "" if not provided.

    // TODO: Handle links: if provided and is an array, json_encode it.
    // Otherwise use json_encode([]).

    // TODO: INSERT INTO weeks (title, start_date, description, links)
    //       VALUES (?, ?, ?, ?)
    // Note: id, created_at, and updated_at are handled by MySQL automatically.

    // TODO: If rowCount() > 0, sendResponse HTTP 201 with the new id.
    // Otherwise sendResponse HTTP 500.
     $required = ['week_id', 'title', 'start_date'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            sendResponse(400, ['success' => false, 'error' => "Missing or invalid field: $field"]);
            return;
        }
    }

     $weekId     = trim($data['week_id']);
    $title      = trim($data['title']);
    $startDate  = trim($data['start_date']);
    $description = isset($data['description']) ? trim($data['description']) : "";

     $dateObj = DateTime::createFromFormat('Y-m-d', $startDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $startDate) {
        sendResponse(400, ['success' => false, 'error' => 'Invalid start_date format. Use YYYY-MM-DD']);
        return;
    }

     try {
        $checkQuery = "SELECT id FROM weeks WHERE week_id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$weekId]);
        if ($checkStmt->fetch()) {
            sendResponse(409, ['success' => false, 'error' => 'Week ID already exists']);
            return;
        }
    } catch (PDOException $e) {
        sendResponse(500, ['success' => false, 'error' => 'Database error during duplicate check']);
        return;
    }

     $links = isset($data['links']) && is_array($data['links']) ? json_encode($data['links']) : json_encode([]);

     $insertQuery = "INSERT INTO weeks (week_id, title, start_date, description, links) VALUES (?, ?, ?, ?, ?)";

     try {
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->execute([$weekId, $title, $startDate, $description, $links]);

        // Return success response
        $newWeek = [
            'week_id'    => $weekId,
            'title'      => $title,
            'start_date' => $startDate,
            'description'=> $description,
            'links'      => json_decode($links, true),
        ];

        sendResponse(201, ['success' => true, 'data' => $newWeek]);
    } catch (PDOException $e) {
        sendResponse(500, ['success' => false, 'error' => 'Failed to create week']);
    }
}


/**
 * Update an existing week.
 * Method: PUT.
 *
 * Required JSON body:
 *   id — integer primary key of the week to update (required).
 * Optional JSON body fields (at least one must be present):
 *   title, start_date, description, links.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 * Response (invalid start_date): HTTP 400.
 */
function updateWeek(PDO $db, array $data): void
{
    // TODO: Validate that $data['id'] is present.
    // If not, sendResponse HTTP 400.

    // TODO: Check that a week with this id exists.
    // If not, sendResponse HTTP 404.

    // TODO: Dynamically build the SET clause for whichever of
    // title, start_date, description, links are present in $data.
    // - If start_date is included, validate its format.
    // - If links is included, json_encode it.

    // TODO: If no updatable fields are present, sendResponse HTTP 400.

    // TODO: updated_at is updated automatically by MySQL
    //       (ON UPDATE CURRENT_TIMESTAMP), so no need to set it manually.

    // TODO: Build: UPDATE weeks SET {clauses} WHERE id = ?
    // Prepare, bind all SET values, then bind id, and execute.

    // TODO: sendResponse HTTP 200 on success, HTTP 500 on failure.
    if (!isset($data['id']) || trim($data['id']) === '') {
        sendResponse(400, ['success' => false, 'error' => 'Missing or invalid week_id']);
        return;
    }

    $id = trim($data['id']);

     try {
        $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ? = ?");
        $checkStmt->execute([$weekId]);
        if (!$checkStmt->fetch()) {
            sendResponse(404, ['success' => false, 'error' => 'Week not found']);
            return;
        }
    } catch (PDOException $e) {
        sendResponse(500, ['success' => false, 'error' => 'Database error during lookup']);
        return;
    }

    $setClauses = [];
    $values = [];

    if (isset($data['title'])) {
        $setClauses[] = "title = ?";
        $values[] = trim($data['title']);
    }

        if (isset($data['start_date'])) {
        $startDate = trim($data['start_date']);
        $dateObj = DateTime::createFromFormat('Y-m-d', $startDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $startDate) {
            sendResponse(400, ['success' => false, 'error' => 'Invalid start_date format. Use YYYY-MM-DD']);
            return;
        }
        $setClauses[] = "start_date = ?";
        $values[] = $startDate;
    }

     if (isset($data['description'])) {
        $setClauses[] = "description = ?";
        $values[] = trim($data['description']);
    }

    if (isset($data['links'])) {
        $encodedLinks = is_array($data['links']) ? json_encode($data['links']) : json_encode([]);
        $setClauses[] = "links = ?";
        $values[] = $encodedLinks;
    }

    if (empty($setClauses)) {
        sendResponse(400, ['success' => false, 'error' => 'No fields provided for update']);
        return;
    }

    $setClauses[] = "updated_at = CURRENT_TIMESTAMP";

    $query = "UPDATE weeks SET " . implode(', ', $setClauses) . " WHERE id = ? ";
    $values[] = $Id;

     try {
        $stmt = $db->prepare($query);
        $stmt->execute($values);

        // Return updated data
        $getStmt = $db->prepare("SELECT week_id, title, start_date, description, links, created_at, updated_at FROM weeks WHERE id = ?");
        $getStmt->execute([$Id]);
        $updatedWeek = $getStmt->fetch();

        if ($updatedWeek) {
            $updatedWeek['links'] = json_decode($updatedWeek['links'], true) ?? [];
            sendResponse(200, ['success' => true, 'data' => $updatedWeek]);
        } else {
            sendResponse(500, ['success' => false, 'error' => 'Failed to retrieve updated week']);
        }
    } catch (PDOException $e) {
        sendResponse(500, ['success' => false, 'error' => 'Failed to update week']);
    }
}



/**
 * Delete a week by integer id.
 * Method: DELETE with ?id={id}.
 *
 * The ON DELETE CASCADE constraint on comments_week.week_id
 * automatically removes all comments for this week — no manual
 * deletion of comments is needed.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteWeek(PDO $db, $id): void
{
    // TODO: Validate that $id is provided and numeric.
    // If not, sendResponse HTTP 400.

    // TODO: Check that a week with this id exists.
    // If not, sendResponse HTTP 404.

    // TODO: DELETE FROM weeks WHERE id = ?
    // (comments_week rows are removed automatically by ON DELETE CASCADE.)

    // TODO: If rowCount() > 0, sendResponse HTTP 200.
    // Otherwise sendResponse HTTP 500.
    if (!isset($id) || trim($id) === '' || !is_numeric($id)) {
    sendResponse(400, ['success' => false, 'error' => 'Missing or invalid week_id']);
    return;
}

$weekID = (int)$id;

    try {
         $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
        $checkStmt->execute([$weekId]);
        if (!$checkStmt->fetch()) {
            sendResponse(404, ['success' => false, 'error' => 'Week not found']);
            return;
        }
    } catch (PDOException $e) {
        sendResponse(500, ['success' => false, 'error' => 'Database error during lookup']);
        return;
    }

    try {

         $deleteWeekStmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
        $deleteWeekStmt->execute([$weekID]);

        if ($deleteWeekStmt->rowCount() > 0) {
            sendResponse(200, ['success' => true, 'message' => 'Week and associated comments deleted']);
        } else {
            sendResponse(500, ['success' => false, 'error' => 'Failed to delete week']);
        }
    } catch (PDOException $e) {
        sendResponse(500, ['success' => false, 'error' => 'Database error during deletion']);
    }

}


// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

/**
 * Get all comments for a specific week.
 * Method: GET with ?action=comments&week_id={id}.
 *
 * Reads from the comments_week table.
 * Returns an empty data array if no comments exist — not an error.
 *
 * Each comment object: { id, week_id, author, text, created_at }
 */
function getCommentsByWeek(PDO $db, $weekId): void
{
    // TODO: Validate that $weekId is provided and numeric.
    // If not, sendResponse HTTP 400.

    // TODO: SELECT id, week_id, author, text, created_at
    //       FROM comments_week
    //       WHERE week_id = ?
    //       ORDER BY created_at ASC

    // TODO: Fetch all rows. Return sendResponse with the array
    //       (empty array is valid).

     if (!isset($weekID) || trim($weekID) === '' || !is_numeric($weekID)) {
    sendResponse(400, ['success' => false, 'error' => 'Missing or invalid week_id']);
    return;
}

     $query = "SELECT id, week_id, author, text, created_at FROM comments_week WHERE week_id = ? ORDER BY created_at ASC";

     try {
         $stmt = $db->prepare($query);
        $stmt->execute([$weekId]);

        $comments = $stmt->fetchAll();

         sendResponse(200, ['success' => true, 'data' => $comments]);
    } catch (PDOException $e) {
        sendResponse(500, ['success' => false, 'error' => 'Failed to retrieve comments']);
    }
}


/**
 * Create a new comment.
 * Method: POST with ?action=comment.
 *
 * Required JSON body:
 *   week_id — integer FK into weeks.id (required)
 *   author  — string (required)
 *   text    — string (required, must be non-empty after trim)
 *
 * Response (success): HTTP 201 — { success, message, id, data: comment }
 * Response (week not found): HTTP 404.
 * Response (missing fields): HTTP 400.
 */
function createComment(PDO $db, array $data): void
{
    // TODO: Validate that week_id, author, and text are all present and
    // non-empty after trimming. If any are missing, sendResponse HTTP 400.

    // TODO: Validate that week_id is numeric.

    // TODO: Check that a week with this id exists in the weeks table.
    // If not, sendResponse HTTP 404.

    // TODO: INSERT INTO comments_week (week_id, author, text)
    //       VALUES (?, ?, ?)

    // TODO: If rowCount() > 0, sendResponse HTTP 201 with the new id
    //       and the full new comment object.
    // Otherwise sendResponse HTTP 500.
}


/**
 * Delete a single comment.
 * Method: DELETE with ?action=delete_comment&comment_id={id}.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteComment(PDO $db, $commentId): void
{
    // TODO: Validate that $commentId is provided and numeric.
    // If not, sendResponse HTTP 400.

    // TODO: Check that the comment exists in comments_week.
    // If not, sendResponse HTTP 404.

    // TODO: DELETE FROM comments_week WHERE id = ?

    // TODO: If rowCount() > 0, sendResponse HTTP 200.
    // Otherwise sendResponse HTTP 500.
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        // ?action=comments&week_id={id} → list comments for a week
        // TODO: if $action === 'comments', call getCommentsByWeek($db, $weekId)

        // ?id={id} → single week
        // TODO: elseif $id is set, call getWeekById($db, $id)

        // no parameters → all weeks (supports ?search, ?sort, ?order)
        // TODO: else call getAllWeeks($db)

    } elseif ($method === 'POST') {

        // ?action=comment → create a comment in comments_week
        // TODO: if $action === 'comment', call createComment($db, $data)

        // no action → create a new week
        // TODO: else call createWeek($db, $data)

    } elseif ($method === 'PUT') {

        // Update a week; id comes from the JSON body
        // TODO: call updateWeek($db, $data)

    } elseif ($method === 'DELETE') {

        // ?action=delete_comment&comment_id={id} → delete one comment
        // TODO: if $action === 'delete_comment', call deleteComment($db, $commentId)

        // ?id={id} → delete a week (and its comments via CASCADE)
        // TODO: else call deleteWeek($db, $id)

    } else {
        // TODO: sendResponse HTTP 405 Method Not Allowed.
    }

} catch (PDOException $e) {
    // TODO: Log the error with error_log().
    // Return a generic HTTP 500 — do NOT expose $e->getMessage() to clients.

} catch (Exception $e) {
    // TODO: Log the error with error_log().
    // Return HTTP 500 using sendResponse().
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Send a JSON response and stop execution.
 *
 * @param array $data        Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default 200).
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    // TODO: http_response_code($statusCode);
    // TODO: echo json_encode($data, JSON_PRETTY_PRINT);
    // TODO: exit;
}


/**
 * Validate a date string against the "YYYY-MM-DD" format.
 *
 * @param  string $date
 * @return bool  True if valid, false otherwise.
 */
function validateDate(string $date): bool
{
    // TODO: $d = DateTime::createFromFormat('Y-m-d', $date);
    // TODO: return $d && $d->format('Y-m-d') === $date;
}


/**
 * Sanitize a string input.
 *
 * @param  string $data
 * @return string  Trimmed, tag-stripped, HTML-encoded string.
 */
function sanitizeInput(string $data): string
{
    // TODO: return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
