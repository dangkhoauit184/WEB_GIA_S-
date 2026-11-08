<?php
// Read-only server readiness check. Does not print configuration values or send mail.
require dirname(__DIR__) . '/app/Config/EduCenterEnv.php';
define('FCPATH', dirname(__DIR__) . '/public/');
define('ENVIRONMENT', 'production');
require dirname(__DIR__) . '/app/Config/Paths.php';
$paths = new \Config\Paths();
require $paths->systemDirectory . '/Boot.php';
\CodeIgniter\Boot::bootConsole($paths);
$failed = false;
$check = static function (bool $ok, string $name) use (&$failed): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
    if (!$ok) $failed = true;
};
$check(PHP_VERSION_ID >= 80200, 'PHP version');
$check(getenv('CI_ENVIRONMENT') === 'production', 'production environment');
$check(!filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN) && !filter_var(ini_get('expose_php'), FILTER_VALIDATE_BOOLEAN), 'PHP error/version disclosure disabled');
foreach (['intl', 'mbstring', 'mysqli', 'zip'] as $extension) $check(extension_loaded($extension), 'extension ' . $extension);
$check(strlen(getenv('JWT_SECRET') ?: '') >= 32, 'private JWT secret configured');
$check((getenv('DB_USER') ?: '') !== 'root', 'application uses a non-root database account');
$check(is_writable(WRITEPATH), 'writable storage');
$check(date_default_timezone_get() === 'Asia/Ho_Chi_Minh', 'PHP timezone');
try {
    $session = \App\Shared\Store::one('SELECT @@session.time_zone AS tz,@@session.transaction_isolation AS isolation');
    $check($session['tz'] === '+07:00' && $session['isolation'] === 'READ-COMMITTED', 'MySQL session timezone/isolation');
    $row = \App\Shared\Store::one("SELECT COUNT(*) AS n FROM schema_migrations WHERE version='005_operations_ready' AND status='applied'");
    $check((int) $row['n'] === 1, 'database migration 005');
    $row = \App\Shared\Store::one("SELECT COUNT(*) AS n FROM schema_migrations WHERE status<>'applied'");
    $check((int) $row['n'] === 0, 'no incomplete database migrations');
} catch (\Throwable) {
    $check(false, 'database readiness (details suppressed; check private server configuration)');
}
$email = config('Email');
$socket = @stream_socket_client('tcp://' . $email->SMTPHost . ':' . $email->SMTPPort, $errno, $error, 3);
$check(is_resource($socket), 'SMTP connection (no message sent)');
if (is_resource($socket)) fclose($socket);
exit($failed ? 1 : 0);
