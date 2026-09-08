<?php

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/AttachmentValidator.php';
require_once __DIR__ . '/NotificationSender.php';

Env::load(__DIR__ . '/.env');

// ── Only accept POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// ── Authenticate via sender app key ─────────────────────────────────
$provided = $_SERVER['HTTP_X_API_KEY']
         ?? $_SERVER['HTTP_AUTHORIZATION']
         ?? $_POST['app_key']
         ?? '';

// Support "Bearer <token>" format.
$provided = preg_replace('/^Bearer\s+/i', '', trim($provided));

if ($provided === '') {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Missing API key. Pass via X-API-Key or Authorization header.']);
    exit;
}

// Look up the app in sender_apps.
$db   = Db::conn();
$stmt = $db->prepare('SELECT id, app_name, is_active FROM sender_apps WHERE app_key = ?');
$stmt->bind_param('s', $provided);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unknown API key.']);
    exit;
}

if (!$app['is_active']) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => "App \"{$app['app_name']}\" is deactivated."]);
    exit;
}

$appId = (int) $app['id'];

// ── Parse Input (JSON or Multipart / Form-Data) ──────────────────────
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

$phone          = null;
$message        = null;
$link           = null;
$attachment     = null;
$attachmentType = null;
$errors         = [];

if (stripos($contentType, 'application/json') !== false) {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Request body must be valid JSON.']);
        exit;
    }

    $phone          = $body['phone']           ?? null;
    $message        = $body['message']         ?? null;
    $link           = $body['link']            ?? null;
    $attachment     = $body['attachment']      ?? $body['attachment_url'] ?? $body['file_url'] ?? null;
    $attachmentType = $body['attachment_type'] ?? $body['type'] ?? null;
} else {
    // Form / Multipart POST
    $phone          = $_POST['phone']           ?? null;
    $message        = $_POST['message']         ?? null;
    $link           = $_POST['link']            ?? null;
    $attachment     = $_POST['attachment']      ?? $_POST['attachment_url'] ?? null;
    $attachmentType = $_POST['attachment_type'] ?? null;

    // Direct file upload handling — validated (extension, MIME, size) by the
    // shared AttachmentValidator BEFORE anything is moved into storage.
    $uploadedFile = $_FILES['file'] ?? $_FILES['attachment'] ?? $_FILES['photo'] ?? $_FILES['document'] ?? null;

    if ($uploadedFile && is_array($uploadedFile)) {
        $stored = AttachmentValidator::store($uploadedFile);

        if ($stored['ok']) {
            $attachment = $stored['path'];
            if (!$attachmentType) {
                if (isset($_FILES['photo']) || in_array($stored['ext'], ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $attachmentType = 'photo';
                } elseif (isset($_FILES['document']) || $stored['ext'] === 'pdf') {
                    $attachmentType = 'document';
                }
            }
        } elseif ($stored['error_key'] === AttachmentValidator::ERR_MOVE_FAILED) {
            // Loud failure — never silently drop the attachment.
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => AttachmentValidator::apiErrorMessage($stored['error_key'])]);
            exit;
        } else {
            $errors[] = AttachmentValidator::apiErrorMessage($stored['error_key']);
        }
    }
}

// ── Validate required fields ────────────────────────────────────────
if (empty($phone))   $errors[] = 'phone is required.';
if (empty($message)) $errors[] = 'message is required.';

if ($link !== null && $link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
    $errors[] = 'link must be a valid URL.';
}

// 'attachment' must be a clean http(s) URL or an already-stored local file
// under the storage directory. Rejects javascript:, data:, file:, etc.
if ($attachment !== null && trim((string) $attachment) !== '') {
    $attachment = (string) $attachment;

    if (!AttachmentValidator::isAllowedRemoteUrl($attachment)
        && !AttachmentValidator::isStoredLocalPath($attachment)
    ) {
        $errors[] = 'attachment must be a valid http(s) URL or an uploaded/stored file.';
    }
}

if (!empty($errors)) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

// ── Send ─────────────────────────────────────────────────────────────
try {
    $sender  = new NotificationSender();
    $results = $sender->sendToPhone(
        (string) $phone,
        $appId,
        $message,
        $link,
        $attachment,
        $attachmentType
    );

    // Consider the call successful if at least one channel was delivered.
    $anySent = false;
    foreach ($results as $r) {
        if (($r['status'] ?? '') === 'sent') {
            $anySent = true;
            break;
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'         => $anySent,
        'app'        => $app['app_name'],
        'attachment' => $attachment ? basename($attachment) : null,
        'results'    => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
    ]);
}
