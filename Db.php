<?php

/**
 * Thin mysqli wrapper. Native driver, no Composer, no PDO abstraction layer.
 */
class Db
{
    private static ?mysqli $conn = null;

    public static function conn(): mysqli
    {
        if (self::$conn === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $user = getenv('DB_USER') ?: 'notifier';
            $pass = getenv('DB_PASS') ?: '';
            $name = getenv('DB_NAME') ?: 'notifier';
            $port = (int) (getenv('DB_PORT') ?: 3306);

            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            $maxRetries = 5;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    self::$conn = new mysqli($host, $user, $pass, $name, $port);
                    self::$conn->set_charset('utf8mb4');
                    break;
                } catch (mysqli_sql_exception $e) {
                    if ($attempt === $maxRetries) throw $e;
                    sleep(1); // Wait for DB to boot
                }
            }
        }

        return self::$conn;
    }
}
