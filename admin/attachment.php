<?php

/**
 * Authenticated attachment streaming endpoint.
 *
 * Local attachments live OUTSIDE the web root (<parent-of-project>/storage/),
 * so this is the only way admins can view/download them. Requires an admin
 * session; refuses any stored path that resolves outside the storage
 * directory. Images stream inline, everything else (PDFs) as a download.
 */

require_once __DIR__ . '/auth.php';

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    exit;
}

$db   = Db::conn();
$stmt = $db->prepare('SELECT attachment_url FROM message_logs WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$storedPath = trim((string) ($row['attachment_url'] ?? ''));

if ($storedPath === '' || preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $storedPath)) {
    // Nothing stored, or a remote URL (not served from here).
    http_response_code(404);
    exit;
}

// Refuse anything that does not resolve INSIDE the storage directory —
// this also neutralizes javascript:, data:, file: and traversal attempts.
if (!AttachmentValidator::isStoredLocalPath($storedPath)) {
    http_response_code(404);
    exit;
}

$fileReal = realpath($storedPath);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($fileReal) ?: 'application/octet-stream';

$disposition = str_starts_with($mime, 'image/') ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($fileReal) . '"');
header('Content-Length: ' . (string) filesize($fileReal));
header('X-Content-Type-Options: nosniff');
readfile($fileReal);
exit;
