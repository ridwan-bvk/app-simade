<?php

/**
 * Simple .env loader (no external package).
 * Supports plain KEY=VALUE pairs.
 */
function env_load(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $root = dirname(__DIR__);
    $envPath = $path ?: $root . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envPath)) {
        $loaded = true;
        return;
    }

    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        $loaded = true;
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))
        ) {
            $val = substr($val, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $val);
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
        }
        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $val;
        }
    }

    $loaded = true;
}

function env(string $key, ?string $default = null): ?string
{
    $val = getenv($key);
    if ($val !== false) {
        return $val;
    }
    if (isset($_ENV[$key])) {
        return (string)$_ENV[$key];
    }
    if (isset($_SERVER[$key])) {
        return (string)$_SERVER[$key];
    }
    return $default;
}

