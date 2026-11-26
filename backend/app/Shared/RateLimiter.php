<?php
namespace App\Shared;

/**
 * Lightweight, atomic file-locked sliding-window rate limiter (zero external service dependency).
 * Protects authentication and public endpoints across multi-worker Apache/PHP-FPM environments.
 */
final class RateLimiter
{
    private static function dir(): string
    {
        $base = rtrim(WRITEPATH, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'ratelimit';
        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }
        return $base;
    }

    private static function path(string $bucket): string
    {
        return self::dir() . DIRECTORY_SEPARATOR . hash('sha256', $bucket) . '.json';
    }

    public static function clientIp(): string
    {
        return Access::clientIp();
    }

    /**
     * Check whether the bucket has already reached its limit without recording a new hit.
     */
    public static function assertAllowed(string $bucket, int $maxHits, int $windowSeconds, string $message): void
    {
        if (self::count($bucket, $windowSeconds) >= $maxHits) {
            throw new ApiException(429, $message, 'RATE_LIMITED');
        }
    }

    /**
     * Record a hit and throw 429 if the bucket exceeds maxHits within windowSeconds.
     */
    public static function hit(string $bucket, int $maxHits, int $windowSeconds, string $message): void
    {
        $path = self::path($bucket);
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            $now = time();
            $cutoff = $now - $windowSeconds;
            $raw = stream_get_contents($fp);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            $hits = is_array($decoded) ? array_values(array_filter($decoded, static fn($ts) => is_int($ts) && $ts > $cutoff)) : [];
            if (count($hits) >= $maxHits) {
                self::writeLocked($fp, $hits);
                throw new ApiException(429, $message, 'RATE_LIMITED');
            }
            $hits[] = $now;
            self::writeLocked($fp, $hits);
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    /**
     * Record a failure event (e.g. wrong password) without throwing immediately.
     */
    public static function record(string $bucket, int $windowSeconds): void
    {
        $path = self::path($bucket);
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            $now = time();
            $cutoff = $now - $windowSeconds;
            $raw = stream_get_contents($fp);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            $hits = is_array($decoded) ? array_values(array_filter($decoded, static fn($ts) => is_int($ts) && $ts > $cutoff)) : [];
            $hits[] = $now;
            self::writeLocked($fp, $hits);
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    public static function clear(string $bucket): void
    {
        $path = self::path($bucket);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function count(string $bucket, int $windowSeconds): int
    {
        $path = self::path($bucket);
        if (!is_file($path)) {
            return 0;
        }
        $fp = @fopen($path, 'r');
        if ($fp === false) {
            return 0;
        }
        try {
            if (!flock($fp, LOCK_SH)) {
                return 0;
            }
            $cutoff = time() - $windowSeconds;
            $raw = stream_get_contents($fp);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($decoded)) {
                return 0;
            }
            return count(array_filter($decoded, static fn($ts) => is_int($ts) && $ts > $cutoff));
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    private static function writeLocked($fp, array $hits): void
    {
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($hits, JSON_THROW_ON_ERROR));
        fflush($fp);
    }
}
