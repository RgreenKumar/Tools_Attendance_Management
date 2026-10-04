<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * Two kinds of account:
 *  - admin: one account only (registered once). Signs in with username/email + password.
 *  - user:  normal employee accounts, each tied to one employee ID. They are created
 *           automatically when the admin saves an employee with an email + password
 *           (Employees page); employees sign in with that email + password. No self-registration.
 */
class Auth
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    // ------------------------------------------------------------ admin

    public function isRegistered(): bool
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;
    }

    /** The admin signs in with their email, so it is stored as both username and email. */
    public function register(string $email, string $password): void
    {
        if ($this->isRegistered()) {
            throw new \RuntimeException('An account is already registered.');
        }
        $this->db->prepare('INSERT INTO admin_users (username, email, password_hash) VALUES (?, ?, ?)')
            ->execute([$email, $email, password_hash($password, PASSWORD_DEFAULT)]);
    }

    /** Username-or-email + password. Returns the admin's username, or null. */
    public function attemptAdmin(string $login, string $password): ?string
    {
        $st = $this->db->prepare('SELECT username, password_hash FROM admin_users WHERE BINARY username = ? OR email = ? LIMIT 1');
        $st->execute([$login, $login]);
        $row = $st->fetch();
        if ($row && password_verify($password, (string) $row['password_hash'])) {
            return (string) $row['username'];
        }
        return null;
    }

    // ------------------------------------------------------------ normal users

    /**
     * Employee sign-in: email + password. Returns ['emp_id','email'] or null.
     * Always runs one password check so a wrong email and a wrong password take the same time.
     */
    public function attemptUser(string $email, string $password): ?array
    {
        $st = $this->db->prepare(
            'SELECT u.emp_id, u.email, u.password_hash
             FROM users u JOIN employees e ON e.emp_id = u.emp_id
             WHERE u.email = ? AND u.email <> \'\' LIMIT 1'
        );
        $st->execute([trim($email)]);
        $row  = $st->fetch();
        $hash = $row && (string) $row['password_hash'] !== '' ? (string) $row['password_hash'] : password_hash('no-such-user', PASSWORD_DEFAULT);
        $ok   = password_verify($password, $hash);
        if ($row && $ok && (string) $row['password_hash'] !== '') {
            return ['emp_id' => (string) $row['emp_id'], 'email' => (string) $row['email']];
        }
        return null;
    }

    /**
     * Employee changes their own password. Needs the current password first.
     *
     * @throws \RuntimeException with a message that is safe to show
     */
    public function changeUserPassword(string $empId, string $current, string $new, string $confirm): void
    {
        $st = $this->db->prepare('SELECT password_hash FROM users WHERE emp_id = ? LIMIT 1');
        $st->execute([$empId]);
        $hash = (string) $st->fetchColumn();

        if ($hash === '' || !password_verify($current, $hash)) {
            throw new \RuntimeException('Your current password is not correct.');
        }
        if (strlen($new) < 8) {
            throw new \RuntimeException('The new password must be at least 8 characters.');
        }
        if ($new !== $confirm) {
            throw new \RuntimeException('The new password and its confirmation do not match.');
        }
        if (hash_equals($current, $new)) {
            throw new \RuntimeException('Choose a new password that is different from the current one.');
        }
        $this->db->prepare('UPDATE users SET password_hash = ? WHERE emp_id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $empId]);
    }

    /** Registered users with their directory details, newest first (admin's Users page). */
    public function listUsers(): array
    {
        return $this->db->query(
            'SELECT u.id, u.emp_id, u.email, u.created_at, e.name, e.dept
             FROM users u LEFT JOIN employees e ON e.emp_id = u.emp_id
             ORDER BY u.created_at DESC, u.id DESC'
        )->fetchAll();
    }

    public function deleteUser(int $id): void
    {
        $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    // ------------------------------------------------------------ session

    public static function currentUser(): ?string
    {
        return $_SESSION['user'] ?? null;
    }

    /** Sessions made before roles existed have no role, so they must sign in again. */
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user']) && !empty($_SESSION['role']);
    }

    public static function isAdmin(): bool
    {
        return self::isLoggedIn() && $_SESSION['role'] === 'admin';
    }

    public static function home(): string
    {
        return self::isAdmin() ? 'index.php' : 'my_report.php';
    }

    public static function login(string $username, string $role = 'admin', ?string $empId = null): void
    {
        session_regenerate_id(true);
        self::cancelPending();
        $_SESSION['user']   = $username;
        $_SESSION['role']   = $role;
        $_SESSION['emp_id'] = $empId;
    }

    public static function cancelPending(): void
    {
        unset($_SESSION['pending_login'], $_SESSION['otp']);
    }

    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['role'], $_SESSION['emp_id'], $_SESSION['report_id']);
        self::cancelPending();
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            redirect('login.php');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            redirect('my_report.php');
        }
    }

    public static function requireUser(): void
    {
        self::requireLogin();
        if (self::isAdmin()) {
            redirect('index.php');
        }
    }
}
