<?php
/**
 * ============================================================================
 * GET LEAD & INTERACTION HISTORY API
 * Endpoint: GET /api/get-lead.php?session_id=SM-SESSION-XXXX
 * ============================================================================
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/response.php';
require_once dirname(__DIR__) . '/helpers/validation.php';

setStandardHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, ['success' => false, 'message' => 'Method Not Allowed. Use GET.']);
}

try {
    $sessionId = cleanString($_GET['session_id'] ?? '');
    if (!isValidSessionId($sessionId)) {
        sendJsonResponse(400, ['success' => false, 'message' => 'Invalid or missing session_id.']);
    }

    $pdo = getDBConnection();

    // Fetch lead details
    $leadsTable = TABLE_LEADS;
    $interactionsTable = TABLE_INTERACTIONS;

    $stmt = $pdo->prepare("SELECT * FROM `{$leadsTable}` WHERE session_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $lead = $stmt->fetch();

    if (!$lead) {
        sendJsonResponse(404, ['success' => false, 'message' => 'Lead session not found.']);
    }

    // Fetch all interaction click history
    $intStmt = $pdo->prepare("
        SELECT id, step_number, question, selected_answer, field_name, action_type, created_at
        FROM `{$interactionsTable}`
        WHERE session_id = ?
        ORDER BY id ASC
    ");
    $intStmt->execute([$sessionId]);
    $interactions = $intStmt->fetchAll();

    sendJsonResponse(200, [
        'success' => true,
        'lead' => $lead,
        'interactions' => $interactions
    ]);

} catch (Exception $e) {
    error_log("[get-lead API Error] " . $e->getMessage());
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
