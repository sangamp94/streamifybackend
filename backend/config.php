<?php
/**
 * config.php — shared bootstrap for every endpoint.
 *
 * Render + Neon (Postgres) version. Nothing in this file needs editing —
 * set DATABASE_URL as an environment variable on your Render service
 * (Render dashboard -> your service -> Environment) instead.
 */

// Standardize PHP's clock. Belt-and-suspenders: even though every
// expiry below is computed with Postgres's own NOW(), this keeps PHP's
// clock (used for a couple of pure-PHP DateTime comparisons) aligned
// to UTC rather than whatever the container's default happens to be.
date_default_timezone_set('UTC');

// ---------------------------------------------------------------
// 1. Database credentials — read from environment variables, not
//    hardcoded here. This is the Render + Neon (Postgres) version.
//
//    Set ONE of these as an env var on your Render service:
//      DATABASE_URL = postgres://user:pass@host/dbname?sslmode=require
//      (this is exactly the connection string Neon gives you on its
//      dashboard — copy it as-is into Render's environment tab)
//
//    Never hardcode real credentials in this file again: env vars mean
//    re-deploying or re-uploading this file can't ever wipe out your
//    real database connection like it could before.
// ---------------------------------------------------------------
$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    define('DB_HOST', $parts['host']);
    define('DB_PORT', $parts['port'] ?? 5432);
    define('DB_NAME', ltrim($parts['path'] ?? '', '/'));
    define('DB_USER', $parts['user'] ?? '');
    define('DB_PASS', $parts['pass'] ?? '');
} else {
    // Fallback: discrete PG* env vars, for local testing without a full URL.
    define('DB_HOST', getenv('PGHOST') ?: 'localhost');
    define('DB_PORT', getenv('PGPORT') ?: 5432);
    define('DB_NAME', getenv('PGDATABASE') ?: 'appguard');
    define('DB_USER', getenv('PGUSER') ?: 'postgres');
    define('DB_PASS', getenv('PGPASSWORD') ?: '');
}

// ---------------------------------------------------------------
// 1b. Self-service token extension (frontend/extend.html)
//     SHORTENER_URL: the ONE shortener link you give to every user
//     (e.g. from GPLinks, ShrinkMe, Linkvertise, etc). Point that
//     shortener's "final destination" at:
//       https://yourdomain.com/backend/extend_gateway.php
//     — same shortener link works for all users; the site itself
//     tracks which logged-in user is completing it.
// ---------------------------------------------------------------
define('SHORTENER_URL', getenv('SHORTENER_URL') ?: 'https://example.com/your-shortener-link-here');
define('SELF_EXTEND_DAYS', 7);          // days added per successful completion
define('SELF_EXTEND_LINK_LIFETIME_MIN', 60); // minutes a pending request stays valid
define('SELF_EXTEND_ELIGIBLE_WITHIN_DAYS', 5); // can only extend once 5 or fewer days remain (already-expired users can always extend)

// ---------------------------------------------------------------
// 1c. Self-service NEW token signup ("New user? Generate a token" on
//     frontend/extend.html). Reuses the same SHORTENER_URL above.
//
//     Anti-abuse: one token per device is enforced using a random
//     device_id the browser saves to localStorage, PLUS the visitor's
//     IP address — a new signup is blocked if either one already has a
//     token on record. This is a browser-level check, not a hardware
//     one (there's no native app SDK reporting a real device ID here),
//     so clearing localStorage on a different network can still get
//     around it. Checking IP too makes that harder, at the cost of
//     occasionally blocking a second real user on the same shared
//     Wi-Fi/NAT — a deliberate trade-off given no app SDK exists yet.
// ---------------------------------------------------------------
define('SIGNUP_TOKEN_DAYS', 7);          // validity of a freshly self-generated token
define('SIGNUP_LINK_LIFETIME_MIN', 60);  // minutes a pending signup stays valid

// ---------------------------------------------------------------
// 2. CORS — restrict this to your actual frontend URL once you know it.
//    If frontend and backend are on the same InfinityFree site, CORS
//    doesn't matter, but leaving this open doesn't hurt either.
// ---------------------------------------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------
// 3. DB connection (PDO)
// ---------------------------------------------------------------
try {
    $pdo = new PDO(
        'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';sslmode=require',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Neon's pooled connection string routes through PgBouncer in
            // transaction mode, which doesn't reliably support native
            // server-side prepared statements (a statement prepared on one
            // physical backend can vanish when the pool hands the next
            // query to a different one). Emulating prepares client-side
            // avoids that class of failure entirely.
            PDO::ATTR_EMULATE_PREPARES => true,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed. Check the DATABASE_URL environment variable on Render.']);
    exit;
}

// ---------------------------------------------------------------
// 3a. Belt-and-suspenders — if any statement inside a transaction throws
//     and nothing downstream calls rollBack(), Postgres leaves the
//     connection in "current transaction is aborted" state (SQLSTATE
//     25P02), which then poisons the *next* query on that connection
//     too. Roll back automatically before reporting the real error.
// ---------------------------------------------------------------
set_exception_handler(function ($e) use ($pdo) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    $msg = $e->getMessage();
    $hint = '';
    if (stripos($msg, 'does not exist') !== false || stripos($msg, 'relation') !== false) {
        $hint = ' -- this means the database tables were never created. Run schema-postgres.sql in the Neon SQL Editor, then try again.';
    }
    echo json_encode(['error' => 'Unexpected server error: ' . $msg . $hint]);
    exit;
});

// ---------------------------------------------------------------
// 4. Helpers
// ---------------------------------------------------------------
function json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function fail($message, $code = 400) {
    respond(['error' => $message], $code);
}

/**
 * Require a valid admin session. Reads the token from the
 * "Authorization: Bearer <token>" header. Exits with 401 if invalid.
 * Returns the admin_id on success.
 */
function require_auth(PDO $pdo) {
    $authHeader = '';

    // getallheaders() is the normal path.
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') { $authHeader = $value; break; }
        }
    }
    // Some hosts only expose it via apache_request_headers().
    if ($authHeader === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $name => $value) {
            if (strtolower($name) === 'authorization') { $authHeader = $value; break; }
        }
    }
    // Fallback to $_SERVER — REDIRECT_HTTP_AUTHORIZATION shows up when the
    // .htaccess rewrite rule had to forward it through an internal redirect.
    if ($authHeader === '') {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }

    if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        fail('Not authenticated', 401);
    }
    $token = $m[1];
    $stmt = $pdo->prepare('SELECT admin_id FROM admin_sessions WHERE token = ? AND expires_at > NOW()');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        fail('Session expired, please log in again', 401);
    }
    return $row['admin_id'];
}

function gen_token($bytes = 32) {
    return bin2hex(random_bytes($bytes));
}

/**
 * Best-effort real visitor IP, accounting for common proxy/CDN headers.
 * Falls back to REMOTE_ADDR (always present) if nothing else validates.
 */
function client_ip() {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

/**
 * Generates a TKN-XXXX-XXXX-XXXX style token (same shape as the seeded
 * demo tokens) and guarantees it isn't already taken in the users table.
 */
function gen_app_token(PDO $pdo) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    for ($try = 0; $try < 8; $try++) {
        $groups = [];
        for ($g = 0; $g < 3; $g++) {
            $part = '';
            for ($i = 0; $i < 4; $i++) $part .= $chars[random_int(0, strlen($chars) - 1)];
            $groups[] = $part;
        }
        $token = 'TKN-' . implode('-', $groups);
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE token = ?');
        $stmt->execute([$token]);
        if (!$stmt->fetch()) return $token;
    }
    fail('Could not generate a unique token — please try again.', 500);
}

/**
 * Generates a short unique user id for self-signups (e.g. SU-3F9A21BC),
 * kept separate from the admin-assigned u1/u2/... scheme.
 */
function gen_user_id(PDO $pdo) {
    for ($try = 0; $try < 8; $try++) {
        $id = 'SU-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) return $id;
    }
    fail('Could not generate a unique user id — please try again.', 500);
}

/**
 * Signed days between today and a Y-m-d date string: positive if the
 * date is in the future, 0 if today, negative if already past.
 */
function days_until($dateStr) {
    $today = new DateTime('today');
    $target = DateTime::createFromFormat('Y-m-d', $dateStr);
    return (int) floor(($target->getTimestamp() - $today->getTimestamp()) / 86400);
}

/**
 * Look up an end-user (app user, NOT an admin) by their app token.
 * Used by the self-service extension endpoints. Exits with fail()
 * if the token is missing or unknown. Does not check block status —
 * callers decide whether blocked users are allowed to proceed.
 */
function find_user_by_token(PDO $pdo, $token) {
    $token = trim((string)$token);
    if ($token === '') {
        fail('Token is required');
    }
    $stmt = $pdo->prepare('SELECT id, name, email, token, status, expiry FROM users WHERE token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        fail('Token not found. Double-check it and try again.', 404);
    }
    return $user;
}
