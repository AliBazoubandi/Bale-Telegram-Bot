<?php

/**
 * Shared validation + storage for message attachment uploads.
 *
 * Used by BOTH api_send.php and admin/messages.php so every upload is
 * checked (extension, MIME via finfo, size) BEFORE move_uploaded_file()
 * runs. Files are stored OUTSIDE the served docroot — one level above the
 * project root — and are streamed back to admins exclusively through
 * admin/attachment.php, never served directly by Apache.
 */
class AttachmentValidator
{
    /** Maximum accepted upload size: 10 MB. */
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** extension => expected real MIME type */
    private const ALLOWED_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'pdf'  => 'application/pdf',
    ];

    // Error keys returned by validate()/store() so each entry point can
    // render the message in its own language/style.
    public const ERR_UPLOAD_ERROR   = 'upload_error';    // non-OK $_FILES error code
    public const ERR_NOT_UPLOADED   = 'not_uploaded';    // not a genuine POST upload
    public const ERR_TOO_LARGE      = 'too_large';
    public const ERR_BAD_EXTENSION  = 'bad_extension';
    public const ERR_MIME_MISMATCH  = 'mime_mismatch';
    public const ERR_MOVE_FAILED    = 'move_failed';

    /**
     * Absolute path of the storage directory, created on demand with 0755.
     *
     * Resolves to the same directory as __DIR__ . '/../storage/attachments/'
     * computed from api_send.php, or __DIR__ . '/../../storage/attachments/'
     * computed from admin/messages.php — i.e. <parent-of-project>/storage/
     * which sits outside the web root.
     */
    public static function storageDir(): string
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'attachments';

        // Create on demand with 0755. Suppressed on purpose: if the directory
        // cannot be created (e.g. read-only volume, ownership problems) we
        // fail gracefully through ERR_MOVE_FAILED instead of leaking PHP
        // warnings into the response body.
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * Validates an uploaded file BEFORE it is moved anywhere.
     *
     * @return string|null null when the upload is acceptable, otherwise one
     *                     of the ERR_* constants describing the problem.
     */
    public static function validate(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return self::ERR_UPLOAD_ERROR;
        }

        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            return self::ERR_NOT_UPLOADED;
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return self::ERR_TOO_LARGE;
        }

        $ext = strtolower(pathinfo(basename((string) ($file['name'] ?? '')), PATHINFO_EXTENSION));

        if (!isset(self::ALLOWED_TYPES[$ext])) {
            return self::ERR_BAD_EXTENSION;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        if ($mime !== self::ALLOWED_TYPES[$ext]) {
            return self::ERR_MIME_MISMATCH;
        }

        return null;
    }

    /**
     * Validates then moves the upload into the storage directory,
     * keeping the existing filename scheme:
     * date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ext
     *
     * @return array{ok: bool, path: ?string, ext: ?string, error_key: ?string}
     */
    public static function store(array $file): array
    {
        $errorKey = self::validate($file);

        if ($errorKey !== null) {
            return ['ok' => false, 'path' => null, 'ext' => null, 'error_key' => $errorKey];
        }

        $ext = strtolower(pathinfo(basename((string) $file['name']), PATHINFO_EXTENSION));
        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ".{$ext}";
        $targetPath = self::storageDir() . DIRECTORY_SEPARATOR . $safeName;

        // Fail loudly but CLEANLY (structured error, no raw warnings) when
        // the storage location is missing or not writable.
        if (!is_dir(self::storageDir()) || !is_writable(self::storageDir())) {
            return ['ok' => false, 'path' => null, 'ext' => $ext, 'error_key' => self::ERR_MOVE_FAILED];
        }

        if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['ok' => false, 'path' => null, 'ext' => $ext, 'error_key' => self::ERR_MOVE_FAILED];
        }

        return ['ok' => true, 'path' => $targetPath, 'ext' => $ext, 'error_key' => null];
    }

    /**
     * English API error text for an error key (matches the existing
     * api_send.php validation style).
     */
    public static function apiErrorMessage(string $errorKey): string
    {
        switch ($errorKey) {
            case self::ERR_UPLOAD_ERROR:   return 'File upload failed.';
            case self::ERR_NOT_UPLOADED:   return 'Invalid uploaded file.';
            case self::ERR_TOO_LARGE:      return 'File exceeds the maximum allowed size of 10 MB.';
            case self::ERR_BAD_EXTENSION:  return 'File type not allowed. Allowed types: jpg, jpeg, png, webp, gif, pdf.';
            case self::ERR_MIME_MISMATCH:  return 'File content does not match its extension.';
            default:                       return 'Failed to store the uploaded file.';
        }
    }

    /**
     * Persian flash text for an error key (admin panel tone).
     */
    public static function adminErrorMessage(string $errorKey): string
    {
        switch ($errorKey) {
            case self::ERR_UPLOAD_ERROR:   return 'خطا در بارگذاری فایل؛ لطفاً دوباره تلاش کنید.';
            case self::ERR_NOT_UPLOADED:   return 'فایل بارگذاری‌شده معتبر نیست.';
            case self::ERR_TOO_LARGE:      return 'حجم فایل نباید بیشتر از ۱۰ مگابایت باشد.';
            case self::ERR_BAD_EXTENSION:  return 'فرمت فایل مجاز نیست؛ فقط JPG، PNG، WEBP، GIF و PDF پذیرفته می‌شود.';
            case self::ERR_MIME_MISMATCH:  return 'محتوای فایل با فرمت انتخاب‌شده مطابقت ندارد.';
            default:                       return 'ذخیره فایل روی سرور ناموفق بود؛ لطفاً دوباره تلاش کنید.';
        }
    }

    /**
     * True only for syntactically valid URLs with an http/https scheme.
     * Rejects javascript:, data:, file:, etc.
     */
    public static function isAllowedRemoteUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'http' || $scheme === 'https';
    }

    /**
     * True when $path resolves to a real file inside the storage directory.
     */
    public static function isStoredLocalPath(string $path): bool
    {
        $real = realpath($path);
        $base = realpath(self::storageDir());

        return $real !== false
            && $base !== false
            && str_starts_with($real, $base . DIRECTORY_SEPARATOR)
            && is_file($real);
    }
}
