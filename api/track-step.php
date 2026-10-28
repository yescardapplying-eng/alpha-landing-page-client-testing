<?php
/**
 * ============================================================================
 * PROGRESSIVE STEP TRACKING API (CONTINUE ACTION)
 * Endpoint: POST /api/track-step.php (or /api/track-step)
 * Saves all validated step inputs upon clicking "Continue"
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

    $stepNum = (int)($body['step'] ?? ($body['current_step'] ?? ($body['currentStep'] ?? 1)));
    if ($stepNum < 1 || $stepNum > 5) {
        sendJsonResponse(400, ['success' => false, 'message' => 'Invalid questionnaire step (Steps 1-5 allowed).']);
    }

    // Server-side field validation
    $validation = validateStepData($stepNum, $body);
    if (!$validation['valid']) {
        sendJsonResponse(400, ['success' => false, 'message' => $validation['error']]);
    }

    $sanitized = $validation['sanitized'];
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

    // Check if session record already exists
    $stmt = $pdo->prepare("SELECT id, session_id, lead_reference, current_step, status FROM `{$leadsTable}` WHERE session_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $existing = $stmt->fetch();

    $leadRef = '';
    $status = 'partial';

    if (!$existing) {
        $leadRef = generateLeadReference();

        $challengeVal = $sanitized['challenge'] ?? null;
        $fullNameVal = $sanitized['full_name'] ?? null;
        $mobileVal = $sanitized['mobile_number'] ?? null;
        $segmentVal = $sanitized['trading_segment'] ?? null;
        $capitalVal = $sanitized['trading_capital'] ?? null;
        $whatsappVal = $sanitized['whatsapp_number'] ?? null;
        $stateVal = $sanitized['state'] ?? null;

        $insertSql = "
            INSERT INTO `{$leadsTable}` (
                session_id, lead_reference, status, current_step,
                challenge, full_name, mobile_number, trading_segment, trading_capital,
                whatsapp_number, state,
                utm_source, utm_medium, utm_campaign, utm_content, utm_term, fbclid,
                ip_address, user_agent, created_at, updated_at
            ) VALUES (
                :session_id, :lead_reference, 'partial', :current_step,
                :challenge, :full_name, :mobile_number, :trading_segment, :trading_capital,
                :whatsapp_number, :state,
                :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :fbclid,
                :ip_address, :user_agent, NOW(), NOW()
            )
        ";

        $insStmt = $pdo->prepare($insertSql);
        $insStmt->execute([
            ':session_id' => $sessionId,
            ':lead_reference' => $leadRef,
            ':current_step' => $stepNum,
            ':challenge' => $challengeVal,
            ':full_name' => $fullNameVal,
            ':mobile_number' => $mobileVal,
            ':trading_segment' => $segmentVal,
            ':trading_capital' => $capitalVal,
            ':whatsapp_number' => $whatsappVal,
            ':state' => $stateVal,
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
        $status = $existing['status'];

        $setClauses = [];
        $params = [
            ':session_id' => $sessionId,
            ':step_num' => $stepNum
        ];

        // Append updated fields
        foreach ($sanitized as $key => $val) {
            $setClauses[] = "`{$key}` = :{$key}";
            $params[":{$key}"] = $val;
        }

        // Conditionally update UTM attribution if not previously stored
        if (!empty($utmSource)) {
            $setClauses[] = "utm_source = COALESCE(utm_source, :utm_source)";
            $params[':utm_source'] = $utmSource;
        }
        if (!empty($utmMedium)) {
            $setClauses[] = "utm_medium = COALESCE(utm_medium, :utm_medium)";
            $params[':utm_medium'] = $utmMedium;
        }
        if (!empty($utmCampaign)) {
            $setClauses[] = "utm_campaign = COALESCE(utm_campaign, :utm_campaign)";
            $params[':utm_campaign'] = $utmCampaign;
        }
        if (!empty($utmContent)) {
            $setClauses[] = "utm_content = COALESCE(utm_content, :utm_content)";
            $params[':utm_content'] = $utmContent;
        }
        if (!empty($utmTerm)) {
            $setClauses[] = "utm_term = COALESCE(utm_term, :utm_term)";
            $params[':utm_term'] = $utmTerm;
        }
        if (!empty($fbclid)) {
            $setClauses[] = "fbclid = COALESCE(fbclid, :fbclid)";
            $params[':fbclid'] = $fbclid;
        }

        $setSql = implode(', ', $setClauses);
        $updateSql = "
            UPDATE `{$leadsTable}` SET
                {$setSql},
                current_step = GREATEST(current_step, :step_num),
                updated_at = NOW()
            WHERE session_id = :session_id
        ";

        $upStmt = $pdo->prepare($updateSql);
        $upStmt->execute($params);
    }

    // Log the continue action with Question and Answer into assessment_interactions
    $qMap = getQuestionsMap();
    $questionTitle = $qMap[$stepNum]['question'] ?? "Step {$stepNum}";
    $answerSummary = json_encode($sanitized, JSON_UNESCAPED_UNICODE);

    $intSql = "
        INSERT INTO `{$interactionsTable}` (
            session_id, step_number, question, selected_answer, field_name,
            action_type, ip_address, user_agent, created_at
        ) VALUES (
            :session_id, :step_number, :question, :selected_answer, 'step_data',
            'continue_click', :ip_address, :user_agent, NOW()
        )
    ";
    $intStmt = $pdo->prepare($intSql);
    $intStmt->execute([
        ':session_id' => $sessionId,
        ':step_number' => $stepNum,
        ':question' => $questionTitle,
        ':selected_answer' => $answerSummary,
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent
    ]);

    sendJsonResponse(200, [
        'success' => true,
        'message' => "Step {$stepNum} progress saved successfully.",
        'lead_reference' => $leadRef,
        'current_step' => $stepNum,
        'status' => $status
    ]);

} catch (Exception $e) {
    error_log("[track-step API Error] " . $e->getMessage());
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Internal server error while saving progress: ' . $e->getMessage()
    ]);
}
