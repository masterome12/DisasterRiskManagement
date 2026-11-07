<?php
// DRRMS South Caloocan — Database Config (WAMP/XAMPP)
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'drrms_caloocan');
define('DB_USER',    'root');   // WAMP default
define('DB_PASS',    '');       // WAMP default blank password
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',      'DRRMS South Caloocan');
define('BASE_URL',      'http://localhost/drrms');
define('BASE_PATH',     dirname(__DIR__));
define('UPLOAD_PATH',   BASE_PATH . '/uploads');
define('BCRYPT_COST',   10);
define('SESSION_NAME',  'DRRMS_SESS');
define('SESSION_TTL',   1800);
define('MAX_UPLOAD_MB', 5);
define('MAX_ATTEMPTS',  5);
define('LOCKOUT_MINS',  15);

date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

class DB {
    private static ?PDO $pdo = null;
    private function __construct() {}

    public static function get(): PDO {
        if (self::$pdo === null) {
            $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=".DB_CHARSET;
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone='+08:00'",
                ]);
            } catch (PDOException $e) {
                http_response_code(503);
                die(json_encode(['success'=>false,'message'=>'DB connection failed: '.$e->getMessage()]));
            }
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $p = []): PDOStatement {
        $s = self::get()->prepare($sql);
        $s->execute($p);
        return $s;
    }

    public static function lastId(): string { return self::get()->lastInsertId(); }
    public static function begin():  void   { self::get()->beginTransaction(); }
    public static function commit(): void   { self::get()->commit(); }
    public static function back():   void   { self::get()->rollBack(); }
}
