<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

/**
 * One shared MySQL connection. On first use it creates the database and tables
 * (database/schema.sql) if they're missing, and imports any old data/*.json
 * files once, so nothing entered before the switch to MySQL is lost.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static array $cfg = [];
    private static array $legacy = [];

    public static function configure(array $db, array $legacyFiles = []): void
    {
        self::$cfg    = $db;
        self::$legacy = $legacyFiles;
        self::$pdo    = null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $c    = self::$cfg;
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $dsn = fn(bool $withDb) => sprintf(
            'mysql:host=%s;port=%d;%scharset=utf8mb4',
            $c['host'], (int) $c['port'], $withDb ? 'dbname=' . $c['name'] . ';' : ''
        );

        try {
            try {
                $pdo = new PDO($dsn(true), $c['user'], $c['pass'], $opts);
            } catch (PDOException $e) {
                $code = (int) ($e->errorInfo[1] ?? $e->getCode());
                if ($code !== 1049) { // 1049 = unknown database: create it below
                    throw $e;
                }
                $pdo  = new PDO($dsn(false), $c['user'], $c['pass'], $opts);
                $name = str_replace('`', '', (string) $c['name']);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$name}`");
            }
            self::install($pdo);
            self::migrate($pdo);
        } catch (PDOException $e) {
            throw new DatabaseUnavailable(
                'Could not connect to the MySQL database (' . $e->getMessage() . ').',
                0,
                $e
            );
        }

        return self::$pdo = $pdo;
    }

    private static function install(PDO $pdo): void
    {
        try {
            if ($pdo->query("SELECT v FROM meta WHERE k = 'installed'")->fetchColumn() !== false) {
                return;
            }
        } catch (PDOException $e) {
            // meta table doesn't exist yet: first run, carry on and create everything
        }

        self::runSchema($pdo);

        self::importLegacy($pdo);
        $pdo->exec("INSERT IGNORE INTO meta (k, v) VALUES ('installed', '1')");
    }

    /** Every statement in schema.sql is CREATE TABLE IF NOT EXISTS, so running it again is always safe. */
    private static function runSchema(PDO $pdo): void
    {
        $sql = (string) file_get_contents(__DIR__ . '/../database/schema.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        foreach (preg_split('/;\s*[\r\n]+/', $sql) as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        }
    }

    /**
     * Upgrades a database created by an older version. Runs once:
     * creates any missing table, adds admin_users.email, and turns the users table
     * into the email-based one (adds users.email, drops the password requirement).
     */
    private static function migrate(PDO $pdo): void
    {
        if ($pdo->query("SELECT v FROM meta WHERE k = 'schema_v7'")->fetchColumn() !== false) {
            return;
        }
        self::runSchema($pdo);

        if (!$pdo->query("SHOW COLUMNS FROM admin_users LIKE 'email'")->fetchAll()) {
            $pdo->exec('ALTER TABLE admin_users ADD COLUMN email VARCHAR(190) NULL');
        }

        $cols = array_column($pdo->query('SHOW COLUMNS FROM users')->fetchAll(), 'Field');
        if (!in_array('email', $cols, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL');
        }
        if (in_array('username', $cols, true)) {
            $pdo->exec('ALTER TABLE users MODIFY username VARCHAR(100) NULL');
        }
        if (in_array('password_hash', $cols, true)) {
            $pdo->exec('ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL');
        }
        if (!$pdo->query("SHOW COLUMNS FROM reports LIKE 'grace_minutes'")->fetchAll()) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN grace_minutes INT NOT NULL DEFAULT 0 AFTER required_minutes');
        }
        if (!$pdo->query("SHOW COLUMNS FROM reports LIKE 'daily_minutes'")->fetchAll()) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN daily_minutes INT NOT NULL DEFAULT 0 AFTER required_minutes');
        }
        if (!$pdo->query("SHOW COLUMNS FROM reports LIKE 'period_days'")->fetchAll()) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN period_days INT NOT NULL DEFAULT 0 AFTER daily_minutes');
        }
        // accounts made by an earlier version get their email from the Employees directory
        $pdo->exec("UPDATE users u JOIN employees e ON e.emp_id = u.emp_id
                    SET u.email = e.email WHERE u.email IS NULL OR u.email = ''");

        $pdo->exec("INSERT IGNORE INTO meta (k, v) VALUES ('schema_v7', '1')");
    }

    /** One-time copy of the old JSON files (admin login, employees) into MySQL. */
    private static function importLegacy(PDO $pdo): void
    {
        $read = function (string $key): ?array {
            $file = self::$legacy[$key] ?? null;
            if (!$file || !is_file($file)) {
                return null;
            }
            $data = json_decode((string) file_get_contents($file), true);
            return is_array($data) ? $data : null;
        };

        if ($auth = $read('auth')) {
            if (!empty($auth['username']) && !empty($auth['password_hash'])) {
                $pdo->prepare('INSERT IGNORE INTO admin_users (username, password_hash) VALUES (?, ?)')
                    ->execute([$auth['username'], $auth['password_hash']]);
            }
        }

        if ($employees = $read('employees')) {
            $st = $pdo->prepare('INSERT IGNORE INTO employees (emp_id, name, dept, email) VALUES (?, ?, ?, ?)');
            foreach ($employees as $id => $e) {
                $st->execute([(string) $id, (string) ($e['name'] ?? ''), (string) ($e['dept'] ?? ''), (string) ($e['email'] ?? '')]);
            }
        }
    }
}
