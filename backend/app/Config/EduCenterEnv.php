<?php
// Existing private environment remains usable. Container variables take precedence.
$envPath = getenv('APP_ENV_FILE') ?: dirname(__DIR__, 3) . '/config/server.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/', $line, $match)) {
            $key = $match[1]; $value = $match[2];
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) === false) {
                putenv($key . '=' . $value); $_ENV[$key] = $value;
            }
        }
    }
}
if (getenv('CI_ENVIRONMENT') === false) {
    putenv('CI_ENVIRONMENT=production'); $_ENV['CI_ENVIRONMENT'] = 'production';
}
