<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * Employee directory (ID, name, department, email) in the employees table.
 * Device attendance exports carry no email, so this is where emails live —
 * HR manages it directly (add / edit / delete / search by ID), and every
 * weekly upload also auto-adds any new employee ID it sees.
 */
class EmployeeDirectory
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** All employees, keyed by emp_id: ['name' => .., 'dept' => .., 'email' => ..] */
    public function all(): array
    {
        return $this->keyed(
            $this->db->query('SELECT emp_id, name, dept, email FROM employees ORDER BY LENGTH(emp_id), emp_id')->fetchAll()
        );
    }

    public function find(string $id): ?array
    {
        $st = $this->db->prepare('SELECT emp_id, name, dept, email FROM employees WHERE emp_id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? ['name' => $row['name'], 'dept' => $row['dept'], 'email' => $row['email']] : null;
    }

    /** Search by ID or name (case-insensitive substring match). */
    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->all();
        }
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $st = $this->db->prepare(
            'SELECT emp_id, name, dept, email FROM employees
             WHERE emp_id LIKE ? OR name LIKE ? ORDER BY LENGTH(emp_id), emp_id'
        );
        $st->execute([$like, $like]);
        return $this->keyed($st->fetchAll());
    }

    /**
     * Adds an employee. When an email AND a password are given, their login is created
     * automatically (they sign in with that email + password) — no self-registration.
     */
    public function add(string $id, string $name, string $dept, string $email, ?string $password = null): void
    {
        if ($this->find($id) !== null) {
            throw new \RuntimeException("Employee ID \"{$id}\" already exists.");
        }
        $this->inTransaction(function () use ($id, $name, $dept, $email, $password): void {
            $this->db->prepare('INSERT INTO employees (emp_id, name, dept, email) VALUES (?, ?, ?, ?)')
                ->execute([$id, $name, $dept, $email]);
            $this->syncLogin($id, $email, $password);
        });
    }

    /** Updates an employee; keeps their login's email in step, and sets a new password only if one is typed. */
    public function update(string $id, string $name, string $dept, string $email, ?string $password = null): void
    {
        if ($this->find($id) === null) {
            throw new \RuntimeException("Employee ID \"{$id}\" was not found.");
        }
        $this->inTransaction(function () use ($id, $name, $dept, $email, $password): void {
            $this->db->prepare('UPDATE employees SET name = ?, dept = ?, email = ? WHERE emp_id = ?')
                ->execute([$name, $dept, $email, $id]);
            $this->syncLogin($id, $email, $password);
        });
    }

    /** emp_id => true for every employee who can sign in (has a password set). */
    public function loginStatus(): array
    {
        $rows = $this->db->query(
            "SELECT emp_id FROM users WHERE password_hash IS NOT NULL AND password_hash <> '' AND email <> ''"
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_fill_keys(array_map('strval', $rows), true);
    }

    private function syncLogin(string $id, string $email, ?string $password): void
    {
        $email = trim($email);
        $hasPw = $password !== null && $password !== '';
        if ($hasPw && strlen($password) < 8) {
            throw new \RuntimeException('The login password must be at least 8 characters.');
        }
        if ($email !== '') {
            $st = $this->db->prepare('SELECT 1 FROM users WHERE email = ? AND emp_id <> ? LIMIT 1');
            $st->execute([$email, $id]);
            if ($st->fetchColumn()) {
                throw new \RuntimeException('That email is already used by another employee login.');
            }
        }

        $st = $this->db->prepare('SELECT id FROM users WHERE emp_id = ?');
        $st->execute([$id]);
        $exists = $st->fetchColumn() !== false;

        if (!$exists) {
            if ($email === '' || !$hasPw) {
                return; // no login until the employee has both an email and a password
            }
            $this->db->prepare('INSERT INTO users (emp_id, email, password_hash) VALUES (?, ?, ?)')
                ->execute([$id, $email, password_hash($password, PASSWORD_DEFAULT)]);
            return;
        }
        if ($hasPw) {
            $this->db->prepare('UPDATE users SET email = ?, password_hash = ? WHERE emp_id = ?')
                ->execute([$email, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $this->db->prepare('UPDATE users SET email = ? WHERE emp_id = ?')->execute([$email, $id]);
        }
    }

    private function inTransaction(callable $fn): void
    {
        $this->db->beginTransaction();
        try {
            $fn();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(string $id): void
    {
        $this->db->prepare('DELETE FROM users WHERE emp_id = ?')->execute([$id]); // their login goes too
        $this->db->prepare('DELETE FROM employees WHERE emp_id = ?')->execute([$id]);
    }

    /**
     * Add/refresh name & department for employees seen in a new upload. A saved
     * email is never overwritten; the file's own email is only used to fill a blank one.
     */
    public function syncFromUpload(array $employees): void
    {
        $st = $this->db->prepare(
            "INSERT INTO employees (emp_id, name, dept, email) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               name  = IF(VALUES(name) <> '', VALUES(name), name),
               dept  = IF(VALUES(dept) <> '', VALUES(dept), dept),
               email = IF(email = '', VALUES(email), email)"
        );
        $this->db->beginTransaction();
        try {
            foreach ($employees as $id => $emp) {
                $st->execute([(string) $id, (string) ($emp['name'] ?? ''), (string) ($emp['dept'] ?? ''), (string) ($emp['email'] ?? '')]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Fill each employee's 'email' field from the directory (empty string if not on file). */
    public function attachEmails(array $employees): array
    {
        if (!$employees) {
            return $employees;
        }
        $ids = array_map('strval', array_keys($employees));
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $this->db->prepare("SELECT emp_id, email FROM employees WHERE emp_id IN ({$in})");
        $st->execute($ids);
        $emails = $st->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($employees as $id => &$emp) {
            $emp['email'] = trim((string) ($emails[(string) $id] ?? $emp['email'] ?? ''));
        }
        unset($emp);
        return $employees;
    }

    private function keyed(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[$r['emp_id']] = ['name' => $r['name'], 'dept' => $r['dept'], 'email' => $r['email']];
        }
        return $out;
    }
}
