<?php
// Small helper functions used across pages.

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, "UTF-8");
}

function fmt_minutes(int $m): string
{
    return sprintf("%dh %02dm", intdiv($m, 60), $m % 60);
}

function csrf_token(): string
{
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(16));
    }
    return $_SESSION["csrf"];
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION["csrf"] ?? "", $_POST["csrf"] ?? "")) {
        http_response_code(419);
        exit("Session expired. Go back and try again.");
    }
}

function redirect(string $url): never
{
    header("Location: " . $url);
    exit;
}

function fail_and_back(string $message): never
{
    $_SESSION["error"] = $message;
    redirect("index.php");
}

/** Column key + short label for a saved daily row (uses the real calendar date when known). */
function day_key(array $d): string
{
    return !empty($d['date_key']) ? (string) $d['date_key'] : (string) $d['date'];
}

function day_label(array $d): string
{
    return !empty($d['date_key']) ? date('D d M', strtotime((string) $d['date_key'])) : (string) $d['date'];
}

/** All days that appear in a set of employees, in date order: [key => label]. */
function day_columns(array $employees): array
{
    $cols = [];
    foreach ($employees as $emp) {
        foreach ($emp['daily'] as $d) {
            $cols[day_key($d)] = day_label($d);
        }
    }
    ksort($cols);
    return $cols;
}

/** Hours one working day must reach: weekly requirement split over the working days (default 5). */
function daily_required(int $weeklyMinutes): int
{
    $days = max(1, (int) ($GLOBALS['config']['working_days_per_week'] ?? 5));
    return intdiv($weeklyMinutes, $days);
}

/**
 * Colour class for one day. Grace time is PER DAY:
 *   hours >= daily target                    -> 'day-ok'    (green)
 *   hours >= daily target - grace, but below -> 'day-grace' (orange, passes thanks to grace time)
 *   hours below (daily target - grace)       -> 'day-low'   (red)
 * 0-minute days (leave/absent/off) get no colour.
 */
function day_status(int $minutes, int $dailyRequired, int $graceMinutes = 0): string
{
    if ($minutes <= 0) {
        return '';
    }
    if ($minutes >= $dailyRequired) {
        return 'day-ok';
    }
    return $minutes >= $dailyRequired - max(0, $graceMinutes) ? 'day-grace' : 'day-low';
}

/**
 * Grace allowance over the whole period: grace per day x number of days in the file.
 * $days = 0 (old reports that never stored it) falls back to working_days_per_week from config.php.
 */
function weekly_grace(int $graceMinutesPerDay, int $days = 0): int
{
    if ($days <= 0) {
        $days = max(1, (int) ($GLOBALS['config']['working_days_per_week'] ?? 5));
    }
    return max(0, $graceMinutesPerDay) * $days;
}

/**
 * The per-day target and per-day grace for an employee: [dailyRequiredMinutes, dailyGraceMinutes].
 * New reports store the real daily hours that were typed on the upload page; old reports (before that
 * existed) fall back to weekly hours / working_days_per_week.
 */
function day_targets(array $emp): array
{
    $daily = (int) ($emp['daily_required'] ?? 0);
    if ($daily > 0) {
        return [$daily, (int) ($emp['grace_minutes'] ?? 0)];
    }
    return [daily_required((int) ($emp['required'] ?? 0)), (int) ($emp['grace_minutes'] ?? 0)];
}

/** Overall week status of one employee: 'low' (red), 'grace' (orange: met only thanks to grace time) or 'ok' (green). */
function emp_status(array $emp): string
{
    if (!empty($emp['low'])) {
        return 'low';
    }
    return !empty($emp['graced']) ? 'grace' : 'ok';
}
