<?php

/**
 * Loads KEY=VALUE pairs from a .env file into the environment, so
 * getenv('DB_HOST') etc. work without any Composer dotenv package.
 *
 * Lines starting with # are ignored. Values can optionally be quoted.
 */
class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return; // fine in prod if you set real env vars another way
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }
}
