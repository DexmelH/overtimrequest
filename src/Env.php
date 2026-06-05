<?php
namespace App;

/**
 * Layered .env loader: .env (base) → .env.{APP_ENV} (overrides).
 */
class Env
{
    private static bool $loaded = false;

    public static function load(?string $rootPath = null, bool $force = false): void
    {
        if (self::$loaded && !$force) {
            return;
        }

        $root = $rootPath ?? dirname(__DIR__);

        // OS / server env can override APP_ENV without editing .env
        $runtimeEnv = self::readRuntimeValue('APP_ENV');

        self::loadFile($root . DIRECTORY_SEPARATOR . '.env');

        if ($runtimeEnv !== null && $runtimeEnv !== '') {
            self::set('APP_ENV', $runtimeEnv);
        }

        $environment = self::get('APP_ENV', 'local');
        if ($environment !== '' && $environment !== 'local') {
            self::loadFile($root . DIRECTORY_SEPARATOR . '.env.' . $environment);
        }

        self::$loaded = true;
    }

    private static function loadFile(string $file): void
    {
        if (!is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            self::set(trim($key), self::parseValue(trim($value)));
        }
    }

    private static function parseValue(string $value): string
    {
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        $hash = strpos($value, ' #');
        if ($hash !== false) {
            return trim(substr($value, 0, $hash));
        }

        return $value;
    }

    private static function set(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }

    private static function readRuntimeValue(string $key): ?string
    {
        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }
        $value = getenv($key);
        return ($value === false || $value === '') ? null : (string) $value;
    }

    public static function environment(): string
    {
        return self::get('APP_ENV', 'local');
    }

    public static function isLocal(): bool
    {
        return self::environment() === 'local';
    }

    public static function isTesting(): bool
    {
        return self::environment() === 'testing';
    }

    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    public static function get(string $key, string $default = ''): string
    {
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        $value = getenv($key);
        return ($value === false) ? $default : (string) $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, (string) $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = strtolower(self::get($key, $default ? 'true' : 'false'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
