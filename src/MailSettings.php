<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * SMTP settings, configured by the admin on the Mail Settings page instead of
 * being edited by hand in config.php. Stored as a single row (id = 1).
 */
class MailSettings
{
    private PDO $db;

    /** Sender details from config.php, used until/unless the Mail Settings page saves its own. */
    private static array $fallback = [];

    public static function setFallback(array $smtp): void
    {
        $user = trim((string) ($smtp['username'] ?? ''));
        self::$fallback = [
            'host'       => trim((string) ($smtp['host'] ?? '')) ?: 'smtp.gmail.com',
            'port'       => (int) ($smtp['port'] ?? 587) ?: 587,
            'encryption' => ($smtp['encryption'] ?? 'tls') === 'ssl' ? 'ssl' : 'tls',
            'username'   => $user,
            'password'   => str_replace(' ', '', trim((string) ($smtp['password'] ?? ''))), // Gmail shows app passwords with spaces
            'from_email' => trim((string) ($smtp['from_email'] ?? '')) ?: $user,
            'from_name'  => trim((string) ($smtp['from_name'] ?? '')),
        ];
    }

    private static function complete(array $s): bool
    {
        return ($s['username'] ?? '') !== '' && ($s['password'] ?? '') !== '' && ($s['from_email'] ?? '') !== '';
    }

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** ['host','port','encryption','username','password','from_email','from_name'] */
    public function get(): array
    {
        $row   = $this->db->query('SELECT * FROM mail_settings WHERE id = 1')->fetch();
        $saved = $row ? [
            'host'       => $row['host'],
            'port'       => (int) $row['port'],
            'encryption' => $row['encryption'],
            'username'   => $row['username'],
            'password'   => $row['password'],
            'from_email' => $row['from_email'],
            'from_name'  => $row['from_name'],
        ] : [
            'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls',
            'username' => '', 'password' => '', 'from_email' => '', 'from_name' => '',
        ];

        // Nothing usable saved in the database? Use the sender from config.php.
        if (!self::complete($saved)) {
            $fb = self::$fallback;
            // No sender address in config.php? Send from the admin's own email account.
            if (($fb['password'] ?? '') !== '' && ($fb['username'] ?? '') === '') {
                $admin = $this->db->query('SELECT email, username FROM admin_users ORDER BY id LIMIT 1')->fetch() ?: [];
                foreach ([$admin['email'] ?? '', $admin['username'] ?? ''] as $candidate) {
                    if (filter_var(trim((string) $candidate), FILTER_VALIDATE_EMAIL)) {
                        $fb['username'] = trim((string) $candidate);
                        if (($fb['from_email'] ?? '') === '') {
                            $fb['from_email'] = $fb['username'];
                        }
                        break;
                    }
                }
            }
            if (self::complete($fb)) {
                return $fb;
            }
        }
        return $saved;
    }

    /** True once the admin has entered enough to actually send mail. */
    public function isConfigured(): bool
    {
        $s = $this->get();
        return $s['username'] !== '' && $s['password'] !== '' && $s['from_email'] !== '';
    }

    /** A blank password means "keep the one already saved" — the form never shows it back. */
    public function save(array $data): void
    {
        $current  = $this->get();
        $password = $data['password'] !== '' ? $data['password'] : $current['password'];

        $this->db->prepare(
            'INSERT INTO mail_settings (id, host, port, encryption, username, password, from_email, from_name)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               host = VALUES(host), port = VALUES(port), encryption = VALUES(encryption),
               username = VALUES(username), password = VALUES(password),
               from_email = VALUES(from_email), from_name = VALUES(from_name)'
        )->execute([
            $data['host'], (int) $data['port'], $data['encryption'],
            $data['username'], $password, $data['from_email'], $data['from_name'],
        ]);
    }
}
