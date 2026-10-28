<?php
/**
 * ============================================================================
 * REAL-TIME CLICK TRACKING API
 * Endpoint: POST /api/track-click.php (or /api/track-click)
 * Stores every question and answer click immediately into MySQL / TiDB
 * ============================================================================
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/response.php';
require_once dirname(__DIR__) . '/helpers/validation.php';

setStandardHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
}

try {
    $body = getJsonInput();
    if (empty($body)) {
        sendJsonResponse(400, ['success' => false, 'message' => 'Invalid or empty JSON payload.']);
    }

    $sessionId = cleanString($body['session_id'] ?? ($body['sessionId'] ?? ''));
    if (!isValidSessionId($sessionId)) {
        sendJsonResponse(400, ['success' => false, 'message' => 'Invalid or missing session_id.']);
    }

    $stepNum = (int)($body['step'] ?? ($body['current_step'] ?? 1));
    if ($stepNum < 1 || $stepNum > 5) {
        $stepNum = 1;
    }

    $qMap = getQuestionsMap();
    $defaultQuestion = $qMap[$stepNum]['question'] ?? "Question Step {$stepNum}";
    $question = cleanString($body['question'] ?? $defaultQuestion);
    $answer = cleanString($body['answer'] ?? ($body['value'] ?? ''));
    $field = cleanString($body['field'] ?? ($qMap[$stepNum]['field'] ?? 'answer'));
    $actionType = cleanString($body['action_type'] ?? 'option_click');

    if (empty($answer)) {
        sendJsonResponse(400, ['success' => false, 'message' => 'Answer value cannot be empty.']);
    }

    $ipAddress = getClientIp();
    $userAgent = getUserAgent();

    $utmSource = cleanString($body['utm_source'] ?? '');
    $utmMedium = cleanString($body['utm_medium'] ?? '');
    $utmCampaign = cleanString($body['utm_campaign'] ?? '');
    $utmContent = cleanString($body['utm_content'] ?? '');
    $utmTerm = cleanString($body['utm_term'] ?? '');
    $fbclid = cleanString($body['fbclid'] ?? '');

    $pdo = getDBConnection();
    $leadsTable = TABLE_LEADS;
    $interactionsTable = TABLE_INTERACTIONS;

    // 1. Check if session exists in `leads`
    $stmt = $pdo->prepare("SELECT id, session_id, lead_reference, current_step, status FROM `{$leadsTable}` WHERE session_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $existing = $stmt->fetch();

    $leadRef = '';

    if (!$existing) {
        // Create initial partial lead
        $leadRef = generateLeadReference();

        // Whitelist allowed fields on leads table
        $allowedFields = ['challenge', 'trading_segment', 'trading_capital', 'whatsapp_number', 'state', 'full_name', 'mobile_number'];
        $fieldCol = in_array($field, $allowedFields, true) ? $field : 'challenge';

        $insertSql = "
            INSERT INTO `{$leadsTable}` (
                session_id, lead_reference, status, current_step,
                `{$fieldCol}`,
                utm_source, utm_medium, utm_campaign, utm_content, utm_term, fbclid,
                ip_address, user_agent, created_at, updated_at
            ) VALUES (
                :session_id, :lead_reference, 'partial', :current_step,
                :field_val,
                :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :fbclid,
                :ip_address, :user_agent, NOW(), NOW()
            )
        ";

        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':session_id' => $sessionId,
            ':lead_reference' => $leadRef,
            ':current_step' => $stepNum,
            ':field_val' => $answer,
            ':utm_source' => $utmSource ?: null,
            ':utm_medium' => $utmMedium ?: null,
            ':utm_campaign' => $utmCampaign ?: null,
            ':utm_content' => $utmContent ?: null,
            ':utm_term' => $utmTerm ?: null,
            ':fbclid' => $fbclid ?: null,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent
        ]);
    } else {
        $leadRef = $existing['lead_reference'];
        $allowedFields = ['challenge', 'trading_segment', 'trading_capital', 'whatsapp_number', 'state', 'full_name', 'mobile_number'];

        if (in_array($field, $allowedFields, true)) {
            $updateSql = "
                UPDATE `{$leadsTable}` SET
                    `{$field}` = :field_val,
                    current_step = GREATEST(current_step, :step_num),
                    updated_at = NOW()
                WHERE session_id = :session_id
            ";
            $upStmt = $pdo->prepare($updateSql);
            $upStmt->execute([
                ':field_val' => $answer,
                ':step_num' => $stepNum,
                ':session_id' => $sessionId
            ]);
        }
    }

    // 2. Insert into `assessment_interactions` to log the exact click with Question and Answer
    $interactionSql = "
        INSERT INTO `{$interactionsTable}` (
            session_id, step_number, question, selected_answer, field_name,
            action_type, ip_address, user_agent, created_at
        ) VALUES (
            :session_id, :step_number, :question, :selected_answer, :field_name,
            :action_type, :ip_address, :user_agent, NOW()
        )
    ";

    $intStmt = $pdo->prepare($interactionSql);
    $intStmt->execute([
        ':session_id' => $sessionId,
        ':step_number' => $stepNum,
        ':question' => $question,
        ':selected_answer' => $answer,
        ':field_name' => $field,
        ':action_type' => $actionType,
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent
    ]);

    sendJsonResponse(200, [
        'success' => true,
        'message' => 'Click tracked and saved to database successfully',
        'lead_reference' => $leadRef,
        'step' => $stepNum,
        'question' => $question,
        'answer' => $answer
    ]);

} catch (Exception $e) {
    error_log("[track-click API Error] " . $e->getMessage());
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error while tracking click: ' . $e->getMessage()
    ]);
}
