<?php
namespace Config;
class Database extends \CodeIgniter\Database\Config
{
    public string $filesPath = APPPATH . 'Database/';
    public string $defaultGroup = 'default';
    public array $default = [];
    public function __construct()
    {
        parent::__construct();
        $dbPass = getenv('DB_PASSWORD');
        if ($dbPass === false || $dbPass === '') {
            $dbPass = getenv('MYSQLPASSWORD') ?: '';
        }
        $this->default = [
            'DSN' => '', 'hostname' => getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1'),
            'username' => getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'educenter_api'), 'password' => $dbPass,
            'database' => getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'trung_tam_gia_su'),
            'DBDriver' => 'MySQLi', 'DBPrefix' => '', 'pConnect' => false, 'DBDebug' => true,
            'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_unicode_ci', 'swapPre' => '',
            'encrypt' => false, 'compress' => false, 'strictOn' => true, 'failover' => [],
            'port' => (int) (getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3307)), 'numberNative' => true, 'foundRows' => false,
            'dateFormat' => ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'time' => 'H:i:s'],
        ];
    }
}
