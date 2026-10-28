<?php
/**
 * ============================================================================
 * FINAL LEAD SUBMISSION API (SUBMIT ACTION)
 * Endpoint: POST /api/submit-lead.php (or /api/submit-lead)
 * Finalizes assessment, marks status as completed, logs submit action
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

    // Step 5 Validation
    $validation = validateStepData(5, $body);
    if (!$validation['valid']) {
        sendJsonResponse(400, ['success' => false, 'message' => $validation['error']]);
    }

    $sanitized = $validation['sanitized'];
    $whatsappNumber = $sanitized['whatsapp_number'];
    $state = $sanitized['state'];

    $ipAddress = getClientIp();
    $userAgent = getUserAgent();

    $pdo = getDBConnection();

    $leadsTable = TABLE_LEADS;
    $interactionsTable = TABLE_INTERACTIONS;

    // Fetch existing lead
    $stmt = $pdo->prepare("SELECT * FROM `{$leadsTable}` WHERE session_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $lead = $stmt->fetch();

    $leadRef = '';

    if (!$lead) {
        $leadRef = generateLeadReference();

        // Fallback insert if user directly reached submit
        $insertSql = "
            INSERT INTO `{$leadsTable}` (
                session_id, lead_reference, status, current_step,
                whatsapp_number, state,
                ip_address, user_agent, created_at, updated_at, completed_at
            ) VALUES (
                :session_id, :lead_reference, 'completed', 5,
                :whatsapp_number, :state,
                :ip_address, :user_agent, NOW(), NOW(), NOW()
            )
        ";
        $insStmt = $pdo->prepare($insertSql);
        $insStmt->execute([
            ':session_id' => $sessionId,
            ':lead_reference' => $leadRef,
            ':whatsapp_number' => $whatsappNumber,
            ':state' => $state,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent
        ]);
    } else {
        $leadRef = $lead['lead_reference'];

        $updateSql = "
            UPDATE `{$leadsTable}` SET
                whatsapp_number = :whatsapp_number,
                state = :state,
                status = 'completed',
                current_step = 5,
                completed_at = COALESCE(completed_at, NOW()),
                updated_at = NOW()
            WHERE session_id = :session_id
        ";
        $upStmt = $pdo->prepare($updateSql);
        $upStmt->execute([
            ':whatsapp_number' => $whatsappNumber,
            ':state' => $state,
            ':session_id' => $sessionId
        ]);
    }

    // Log the Submit click in assessment_interactions
    $intSql = "
        INSERT INTO `{$interactionsTable}` (
            session_id, step_number, question, selected_answer, field_name,
            action_type, ip_address, user_agent, created_at
        ) VALUES (
            :session_id, 5, 'Final Details (WhatsApp & State)', :selected_answer, 'final_details',
            'submit_click', :ip_address, :user_agent, NOW()
        )
    ";
    $intStmt = $pdo->prepare($intSql);
    $intStmt->execute([
        ':session_id' => $sessionId,
        ':selected_answer' => json_encode(['whatsapp_number' => $whatsappNumber, 'state' => $state], JSON_UNESCAPED_UNICODE),
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent
    ]);

    // Retrieve full final lead record for client confirmation
    $fetchStmt = $pdo->prepare("SELECT * FROM `{$leadsTable}` WHERE session_id = ? LIMIT 1");
    $fetchStmt->execute([$sessionId]);
    $finalLead = $fetchStmt->fetch();

    sendJsonResponse(200, [
        'success' => true,
        'message' => 'Assessment completed and submitted successfully.',
        'lead_reference' => $leadRef,
        'status' => 'completed',
        'data' => [
            'lead_reference' => $leadRef,
            'challenge' => $finalLead['challenge'] ?? '',
            'full_name' => $finalLead['full_name'] ?? '',
            'mobile_number' => $finalLead['mobile_number'] ?? '',
            'trading_segment' => $finalLead['trading_segment'] ?? '',
            'trading_capital' => $finalLead['trading_capital'] ?? '',
            'whatsapp_number' => $finalLead['whatsapp_number'] ?? '',
            'state' => $finalLead['state'] ?? '',
            'completed_at' => $finalLead['completed_at'] ?? date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    error_log("[submit-lead API Error] " . $e->getMessage());
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error while submitting assessment: ' . $e->getMessage()
    ]);
}
