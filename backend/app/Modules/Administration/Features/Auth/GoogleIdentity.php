<?php
namespace App\Modules\Administration\Features\Auth;

use App\Shared\ApiException;
use Firebase\JWT\{JWK, JWT};

/**
 * Verifies Google Identity Services (GIS) / One Tap ID tokens cryptographically
 * against Google's official JWKS endpoint (RS256). Never trusts client-supplied
 * email or role claims without signature, issuer, audience, expiry and verification checks.
 */
final class GoogleIdentity
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const ALLOWED_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    /** @var callable|null Test-only override returning a JWKS array; null uses Google HTTPS certs. */
    private static $jwksLoader = null;

    public static function clientId(): string
    {
        return trim(getenv('GOOGLE_CLIENT_ID') ?: '');
    }

    public static function config(): array
    {
        $clientId = self::clientId();
        return [
            'enabled' => $clientId !== '',
            'clientId' => $clientId !== '' ? $clientId : null,
        ];
    }

    /**
     * Verifies a Google ID token and returns normalized verified identity claims.
     *
     * @return array{sub:string,email:string,name:?string,emailVerified:bool}
     */
    public static function verify(string $idToken): array
    {
        $clientId = self::clientId();
        if ($clientId === '') {
            throw new ApiException(
                503,
                'Đăng nhập bằng Google chưa được cấu hình trên máy chủ. Vui lòng đăng nhập bằng tên tài khoản và mật khẩu.',
                'GOOGLE_NOT_CONFIGURED'
            );
        }

        $idToken = trim($idToken);
        if ($idToken === '' || strlen($idToken) > 16384) {
            throw new ApiException(400, 'Mã xác thực Google không hợp lệ.', 'INVALID_GOOGLE_TOKEN');
        }

        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new ApiException(401, 'Token xác thực Google không đúng định dạng.', 'INVALID_GOOGLE_TOKEN');
        }

        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'RS256') {
            throw new ApiException(401, 'Thuật toán chữ ký của token Google không được hỗ trợ.', 'INVALID_GOOGLE_TOKEN');
        }
        $kid = is_string($header['kid'] ?? null) ? $header['kid'] : '';

        $jwks = self::loadJwks(false);
        if ($kid !== '' && !self::hasKeyId($jwks, $kid)) {
            // Google rotates signing keys periodically; refresh once on unknown kid.
            $jwks = self::loadJwks(true);
        }

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = 30;
        try {
            $keys = JWK::parseKeySet($jwks, 'RS256');
            $claims = JWT::decode($idToken, $keys);
        } catch (ApiException $error) {
            throw $error;
        } catch (\Firebase\JWT\ExpiredException) {
            throw new ApiException(401, 'Phiên xác thực Google đã hết hạn. Vui lòng thử đăng nhập lại.', 'GOOGLE_TOKEN_EXPIRED');
        } catch (\Throwable) {
            throw new ApiException(401, 'Không thể xác minh chữ ký của token Google.', 'INVALID_GOOGLE_TOKEN');
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $issuer = is_string($claims->iss ?? null) ? $claims->iss : '';
        if (!in_array($issuer, self::ALLOWED_ISSUERS, true)) {
            throw new ApiException(401, 'Nguồn phát hành token Google không hợp lệ.', 'INVALID_GOOGLE_TOKEN');
        }

        $audience = is_string($claims->aud ?? null) ? $claims->aud : '';
        if ($audience === '' || !hash_equals($clientId, $audience)) {
            throw new ApiException(401, 'Token Google không dành cho ứng dụng EduCenter này.', 'INVALID_GOOGLE_AUDIENCE');
        }

        $exp = (int)($claims->exp ?? 0);
        if ($exp <= time() - 30) {
            throw new ApiException(401, 'Phiên xác thực Google đã hết hạn. Vui lòng thử đăng nhập lại.', 'GOOGLE_TOKEN_EXPIRED');
        }

        $sub = is_string($claims->sub ?? null) ? trim($claims->sub) : '';
        $email = is_string($claims->email ?? null) ? strtolower(trim($claims->email)) : '';
        if ($sub === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException(401, 'Token Google không chứa thông tin email hợp lệ.', 'INVALID_GOOGLE_TOKEN');
        }

        if (($claims->email_verified ?? false) !== true) {
            throw new ApiException(
                403,
                'Email trên tài khoản Google chưa được Google xác minh. Vui lòng dùng email đã xác minh hoặc đăng nhập bằng mật khẩu.',
                'GOOGLE_EMAIL_UNVERIFIED'
            );
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'name' => is_string($claims->name ?? null) ? trim($claims->name) : null,
            'emailVerified' => true,
        ];
    }

    private static function hasKeyId(array $jwks, string $kid): bool
    {
        foreach ($jwks['keys'] ?? [] as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid) return true;
        }
        return false;
    }

    private static function loadJwks(bool $forceRefresh): array
    {
        if (self::$jwksLoader !== null) {
            $loaded = (self::$jwksLoader)($forceRefresh);
            if (!is_array($loaded) || empty($loaded['keys']) || !is_array($loaded['keys'])) {
                throw new ApiException(503, 'Khóa xác thực Google không hợp lệ.', 'GOOGLE_UNAVAILABLE');
            }
            return $loaded;
        }

        $cacheFile = defined('WRITEPATH') ? WRITEPATH . 'cache/google_jwks.json' : null;
        if (!$forceRefresh && $cacheFile && is_file($cacheFile)) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && (int)($cached['expiresAt'] ?? 0) > time() && !empty($cached['jwks']['keys'])) {
                return $cached['jwks'];
            }
        }

        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 5,
                'connect_timeout' => 3,
                'allow_redirects' => false,
            ]);
            $response = $client->get(self::CERTS_URL);
            $jwks = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($jwks) || empty($jwks['keys']) || !is_array($jwks['keys'])) {
                throw new \RuntimeException('Empty JWKS');
            }
            $ttl = 3600;
            $cc = $response->getHeaderLine('Cache-Control');
            if (preg_match('/max-age=(\d+)/i', $cc, $m)) {
                $ttl = max(300, min(86400, (int)$m[1]));
            }
            if ($cacheFile) {
                if (!is_dir(dirname($cacheFile))) @mkdir(dirname($cacheFile), 0775, true);
                @file_put_contents(
                    $cacheFile,
                    json_encode(['expiresAt' => time() + $ttl, 'jwks' => $jwks], JSON_THROW_ON_ERROR),
                    LOCK_EX
                );
            }
            return $jwks;
        } catch (\Throwable) {
            // Fall back to stale cached certs only if Google endpoint is temporarily unreachable.
            if ($cacheFile && is_file($cacheFile)) {
                $cached = json_decode((string)@file_get_contents($cacheFile), true);
                if (is_array($cached) && !empty($cached['jwks']['keys'])) {
                    return $cached['jwks'];
                }
            }
            throw new ApiException(
                503,
                'Không thể kết nối máy chủ xác thực Google. Vui lòng kiểm tra mạng hoặc đăng nhập bằng tên tài khoản và mật khẩu.',
                'GOOGLE_UNAVAILABLE'
            );
        }
    }
}
