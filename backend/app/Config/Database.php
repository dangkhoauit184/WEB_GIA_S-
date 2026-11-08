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
        $this->default = [
            'DSN' => '', 'hostname' => getenv('DB_HOST') ?: '127.0.0.1',
            'username' => getenv('DB_USER') ?: 'educenter_api', 'password' => getenv('DB_PASSWORD') ?: '',
            'database' => getenv('DB_NAME') ?: 'trung_tam_gia_su',
            'DBDriver' => 'MySQLi', 'DBPrefix' => '', 'pConnect' => false, 'DBDebug' => true,
            'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_unicode_ci', 'swapPre' => '',
            'encrypt' => false, 'compress' => false, 'strictOn' => true, 'failover' => [],
            'port' => (int) (getenv('DB_PORT') ?: 3307), 'numberNative' => true, 'foundRows' => false,
            'dateFormat' => ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'time' => 'H:i:s'],
        ];
    }
}
