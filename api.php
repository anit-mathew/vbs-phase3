<?php
/**
 * VBS 2026 — PHP/MySQL API
 * Upload to: pypaonline.org/vbs/api.php
 */

/* ── CORS ── */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

/* ── DB CONFIG ── */
define('DB_HOST', 'db5019985220.hosting-data.io');
define('DB_PORT', '3306');
define('DB_NAME', 'dbs15421479');
define('DB_USER', 'dbu1115673');
define('DB_PASS', 'Lord@20222024');

/* ── CONNECT ── */
function getDB() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        return $pdo;
    } catch (PDOException $e) {
        respond(['status' => 'error', 'message' => 'DB connection failed: ' . $e->getMessage()]);
        exit;
    }
}

/* ── RESPOND ── */
function respond($data) {
    echo json_encode($data);
    exit;
}

/* ── LOG ADMIN ACTION ── */
function logAdminAction($action, $detail = '') {
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS admin_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            detail TEXT,
            logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->prepare("INSERT INTO admin_log (action, detail) VALUES (?, ?)")->execute([$action, $detail]);
    } catch (Exception $e) {}
}

/* ── READ PIN FROM passwords TABLE ── */
function getSettings() {
    $db = getDB();
    // Ensure settings table exists
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(100) PRIMARY KEY,
        `value` VARCHAR(255) NOT NULL
    )");
    $rows = $db->query("SELECT `key`, `value` FROM settings")->fetchAll();
    $s = [];
    foreach ($rows as $r) $s[$r['key']] = $r['value'];
    // Defaults — all days disabled until admin enables
    return [
        'day1_enabled' => $s['day1_enabled'] ?? '0',
        'day2_enabled' => $s['day2_enabled'] ?? '0',
        'day3_enabled' => $s['day3_enabled'] ?? '0',
    ];
}

function getPin($role) {
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT pin FROM passwords WHERE role = ? LIMIT 1');
        $stmt->execute([$role]);
        $row  = $stmt->fetch();
        return $row ? trim($row['pin']) : null;
    } catch (Exception $e) {
        return null;
    }
}

/* ── ENSURE USERS TABLE EXISTS ── */
function ensureUsersTable() {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS vbs_users (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        username    VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        display_name  VARCHAR(100) NOT NULL DEFAULT '',
        role        ENUM('checkin','checkout','both') NOT NULL DEFAULT 'checkin',
        active      TINYINT(1) NOT NULL DEFAULT 1,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login  DATETIME NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS vbs_tokens (
        token       VARCHAR(64) PRIMARY KEY,
        user_id     INT NOT NULL,
        username    VARCHAR(100) NOT NULL,
        role        VARCHAR(20) NOT NULL,
        display_name VARCHAR(100) NOT NULL DEFAULT '',
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_address  VARCHAR(64) NOT NULL DEFAULT '',
        location    VARCHAR(255) NOT NULL DEFAULT ''
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS vbs_user_groups (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT NOT NULL,
        group_name  VARCHAR(50) NOT NULL,
        UNIQUE KEY unique_user_group (user_id, group_name)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS vbs_user_panels (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT NOT NULL,
        panel_name  VARCHAR(50) NOT NULL,
        UNIQUE KEY unique_user_panel (user_id, panel_name)
    )");
}

/* ── HELPER: Fetch user panels/groups ── */
function getUserAccess($userId) {
    $db = getDB();
    $pn = $db->prepare('SELECT panel_name FROM vbs_user_panels WHERE user_id = ?');
    $pn->execute([$userId]);
    $panels = array_column($pn->fetchAll(), 'panel_name');
    $gs = $db->prepare('SELECT group_name FROM vbs_user_groups WHERE user_id = ?');
    $gs->execute([$userId]);
    $groups = array_column($gs->fetchAll(), 'group_name');
    return ['panels' => $panels, 'groups' => $groups];
}

/* ── VALIDATE TOKEN — returns user array or null ── */
function validateToken($token) {
    if (!$token) return null;
    try {
        ensureUsersTable();
        $db   = getDB();
        $stmt = $db->prepare('SELECT t.*, u.active FROM vbs_tokens t JOIN vbs_users u ON t.user_id = u.id WHERE t.token = ? LIMIT 1');
        $stmt->execute([trim($token)]);
        $row  = $stmt->fetch();
        if (!$row || !$row['active']) return null;
        return $row;
    } catch (Exception $e) { return null; }
}

/* ── AUTH CHECK — token only ── */
function checkAuthToken($secret) {
    $user = validateToken($secret);
    if (!$user) {
        respond(['status' => 'error', 'message' => 'Unauthorized']);
    }
}

/* ── AUTH CHECK — token-based ── */
function checkAuth($secret) {
    $user = validateToken($secret);
    if (!$user) {
        respond(['status' => 'error', 'message' => 'Unauthorized']);
    }
}

/* ── ROUTE ── */
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    $secret = $_GET['secret'] ?? '';
    $day    = intval($_GET['day'] ?? 0);

    // ── GET SETTINGS (requires valid token or admin secret) ──
    if ($action === 'getSettings') {
        $adminSecret = $_GET['adminSecret'] ?? '';
        $token = $_GET['token'] ?? $secret;
        $user = validateToken($token);
        if ($adminSecret !== 'VBS2026@ICANJ' && !$user) respond(['status' => 'error', 'message' => 'Unauthorized']);
        respond(['status' => 'ok', 'settings' => getSettings()]);
    }

    // ── GET CHECKIN NAMES (requires valid token) ──
    if ($action === 'getCheckinNames') {
        $token = $_GET['token'] ?? $secret;
        $user = validateToken($token);
        if (!$user) respond(['status' => 'error', 'message' => 'Unauthorized']);
        $db   = getDB();
        $stmt = $db->prepare('SELECT child_name FROM checkins WHERE day = ? ORDER BY child_name ASC');
        $stmt->execute([$day]);
        $names = array_column($stmt->fetchAll(), 'child_name');
        respond(['status' => 'ok', 'names' => $names]);
    }

    // ── VERIFY DASHBOARD PIN (only dashboard role) ──
    if ($action === 'verifyDashboardPin') {
        $dashboardPin = getPin('dashboard');
        if (!$dashboardPin || trim($secret) !== $dashboardPin) {
            respond(['status' => 'error', 'message' => 'Unauthorized']);
        }
        respond(['status' => 'ok']);
    }

    // ── SAVE SETTINGS (admin only) ──
    if ($action === 'saveSettings') {
        $adminKey = $_POST['adminKey'] ?? '';
        if ($adminKey !== 'VBS2026@ICANJ') respond(['status' => 'error', 'message' => 'Unauthorized']);
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(100) PRIMARY KEY,
            `value` VARCHAR(255) NOT NULL
        )");
        $allowed = ['day1_enabled', 'day2_enabled', 'day3_enabled'];
        $stmt = $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
        foreach ($allowed as $k) {
            $v = isset($_POST[$k]) ? '1' : '0';
            $stmt->execute([$k, $v, $v]);
        }
        respond(['status' => 'ok', 'settings' => getSettings()]);
    }

    // ── VALIDATE TOKEN ──
    if ($action === 'validateToken') {
        ensureUsersTable();
        $token = $_GET['token'] ?? '';
        $user  = validateToken($token);
        if (!$user) respond(['status' => 'error', 'message' => 'Invalid or expired session']);
        // Fetch assigned access
        $access = getUserAccess($user['user_id']);
        respond(['status' => 'ok', 'user' => [
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
            'role'         => $user['role'],
            'panels'       => $access['panels'],
            'groups'       => $access['groups']
        ]]);
    }

    // ── GET USER ACCESS (groups, pages, panels) ──
    if ($action === 'getUserGroups' || $action === 'getUserAccess') {
        ensureUsersTable();
        $token = $_GET['token'] ?? '';
        $user  = validateToken($token);
        if (!$user) respond(['status' => 'error', 'message' => 'Unauthorized']);
        $access = getUserAccess($user['user_id']);
        respond(['status' => 'ok', 'groups' => $access['groups'], 'panels' => $access['panels']]);
    }

    // ── LOGOUT ──
    if ($action === 'logout') {
        ensureUsersTable();
        $token = $_GET['token'] ?? '';
        if ($token) {
            $db = getDB();
            try { $db->exec("ALTER TABLE vbs_users ADD COLUMN last_logout DATETIME NULL"); } catch(Exception $e) {}
            // Find the user first
            $row = $db->prepare('SELECT user_id FROM vbs_tokens WHERE token = ? LIMIT 1');
            $row->execute([$token]);
            $t = $row->fetch();
            if ($t) {
                // Record last_logout
                $db->prepare('UPDATE vbs_users SET last_logout = NOW() WHERE id = ?')->execute([$t['user_id']]);
                // Delete ALL tokens for this user — kills all open windows/sessions
                $db->prepare('DELETE FROM vbs_tokens WHERE user_id = ?')->execute([$t['user_id']]);
            }
        }
        respond(['status' => 'ok']);
    }


    if ($action === 'getpin') {
        $adminKey = $_GET['adminkey'] ?? '';
        if ($adminKey !== 'VBS2026GSYNC') {
            respond(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $pin = getPin('checkin');
        respond(['status' => 'ok', 'pin' => $pin]);
    }

    if ($action === 'read') {
        // Accept admin secret for Google Sheets backup sync
        $adminSecret = $_GET['adminSecret'] ?? '';
        $user = validateToken($secret);
        if ($adminSecret === 'VBS2026@ICANJ') {
            // Admin bypass — allowed
        } else if ($user) {
            // valid token — allowed
        } else {
            respond(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $db  = getDB();
        $all = isset($_GET['all']) && $_GET['all'] == '1';
        if ($all) {
            // Fetch all days — used by Google Sheets backup sync
            $stmt = $db->prepare('SELECT * FROM checkins ORDER BY day ASC, checkin_time ASC');
            $stmt->execute();
        } else {
            $stmt = $db->prepare('SELECT * FROM checkins WHERE day = ? ORDER BY checkin_time ASC');
            $stmt->execute([$day]);
        }
        $rows = $stmt->fetchAll();
        respond(['status' => 'ok', 'data' => $rows]);
    }

    // ── VOL READ via GET ──
    if ($action === 'volRead') {
        $adminSecret = $_GET['adminSecret'] ?? '';
        $user = validateToken($secret);
        if ($adminSecret !== 'VBS2026@ICANJ' && !$user) respond(['status' => 'error', 'message' => 'Unauthorized']);
        $db = getDB();
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS vol_attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                day TINYINT NOT NULL,
                vol_name VARCHAR(150) NOT NULL,
                checkin_time VARCHAR(20) NOT NULL,
                checkout_time VARCHAR(20) NOT NULL DEFAULT '',
                checkin_by VARCHAR(100) NOT NULL DEFAULT 'Self',
                checkout_by VARCHAR(100) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            if ($day > 0) {
                $stmt = $db->prepare('SELECT * FROM vol_attendance WHERE day = ? ORDER BY checkin_time ASC');
                $stmt->execute([$day]);
            } else {
                $stmt = $db->prepare('SELECT * FROM vol_attendance ORDER BY day ASC, checkin_time ASC');
                $stmt->execute();
            }
            respond(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            respond(['status' => 'ok', 'data' => []]);
        }
    }

    // ── TSHIRT READ via GET ──
    if ($action === 'tshirtRead') {
        $adminSecret = $_GET['adminSecret'] ?? '';
        $user = validateToken($secret);
        if ($adminSecret !== 'VBS2026@ICANJ' && !$user) respond(['status' => 'error', 'message' => 'Unauthorized']);
        $db = getDB();
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS tshirt_distribution (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(150) NOT NULL,
                tshirt_size VARCHAR(50)  NOT NULL DEFAULT '',
                received    TINYINT(1)   NOT NULL DEFAULT 0,
                is_walkin   TINYINT(1)   NOT NULL DEFAULT 0,
                given_at    VARCHAR(20)  NOT NULL DEFAULT '',
                given_by    VARCHAR(100) NOT NULL DEFAULT '',
                created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $rows = $db->query('SELECT * FROM tshirt_distribution ORDER BY name ASC')->fetchAll();
            respond(['status' => 'ok', 'data' => $rows]);
        } catch (Exception $e) {
            respond(['status' => 'ok', 'data' => []]);
        }
    }

    respond(['status' => 'error', 'message' => 'Unknown action']);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) respond(['status' => 'error', 'message' => 'Invalid JSON']);

    $action = $body['action'] ?? '';
    $secret = $body['secret'] ?? '';

    /* ── LOGIN ── */
/* ── GET CLIENT IP (respects common proxy headers) ── */
function getClientIp() {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
        $val = $_SERVER[$key] ?? '';
        if ($val) {
            // X-Forwarded-For can be a comma-separated list; take the first
            $ip = trim(explode(',', $val)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

/* ── GEO-LOOKUP via ip-api.com (free, no key, ~45 req/min) ── */
function getGeoLocation($ip) {
    if (!$ip || $ip === '0.0.0.0' || $ip === '127.0.0.1') return 'Local / Unknown';
    try {
        $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
        $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country", false, $ctx);
        if (!$json) return '';
        $data = json_decode($json, true);
        if (($data['status'] ?? '') !== 'success') return '';
        $parts = array_filter([$data['city'] ?? '', $data['regionName'] ?? '', $data['country'] ?? '']);
        return implode(', ', $parts);
    } catch (Exception $e) { return ''; }
}

    if ($action === 'login') {
        ensureUsersTable();
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        if (!$username || !$password) respond(['status' => 'error', 'message' => 'Username and password required']);
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM vbs_users WHERE username = ? AND active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            respond(['status' => 'error', 'message' => 'Incorrect username or password']);
        }
        $access = getUserAccess($user['id']);
        // Capture IP + geo
        $ip       = getClientIp();
        $location = getGeoLocation($ip);
        // Migrate table if columns don't exist yet
        try { $db->exec("ALTER TABLE vbs_tokens ADD COLUMN ip_address VARCHAR(64) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
        try { $db->exec("ALTER TABLE vbs_tokens ADD COLUMN location VARCHAR(255) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
        try { $db->exec("ALTER TABLE vbs_users ADD COLUMN last_login_ip VARCHAR(64) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
        try { $db->exec("ALTER TABLE vbs_users ADD COLUMN last_login_location VARCHAR(255) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
        // Generate token
        $token = bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO vbs_tokens (token, user_id, username, role, display_name, ip_address, location) VALUES (?, ?, ?, ?, ?, ?, ?)')
           ->execute([$token, $user['id'], $user['username'], $user['role'], $user['display_name'], $ip, $location]);
        // Update last login + IP/location on user record
        $db->prepare('UPDATE vbs_users SET last_login = NOW(), last_login_ip = ?, last_login_location = ? WHERE id = ?')
           ->execute([$ip, $location, $user['id']]);
        respond(['status' => 'ok', 'token' => $token, 'user' => [
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
            'role'         => $user['role'],
            'panels'       => $access['panels'],
            'groups'       => $access['groups']
        ]]);
    }

    /* ── USER MANAGEMENT (admin only) ── */
    if (in_array($action, ['createUser','updateUser','deleteUser','listUsers','resetPassword','updateUserGroups','updateUserPanels'])) {
        $adminSecret = $body['adminSecret'] ?? '';
        if ($adminSecret !== 'VBS2026@ICANJ') respond(['status' => 'error', 'message' => 'Unauthorized']);
        ensureUsersTable();
        $db = getDB();

        if ($action === 'listUsers') {
            try { $db->exec("ALTER TABLE vbs_users ADD COLUMN last_logout DATETIME NULL"); } catch(Exception $e2) {}
            try { $db->exec("ALTER TABLE vbs_users ADD COLUMN plain_password VARCHAR(50) NOT NULL DEFAULT ''"); } catch(Exception $e2) {}
            try { $db->exec("ALTER TABLE vbs_users ADD COLUMN last_login_ip VARCHAR(64) NOT NULL DEFAULT ''"); } catch(Exception $e2) {}
            try { $db->exec("ALTER TABLE vbs_users ADD COLUMN last_login_location VARCHAR(255) NOT NULL DEFAULT ''"); } catch(Exception $e2) {}
            $rows = $db->query('SELECT id, username, display_name, role, active, plain_password, last_login, last_logout, last_login_ip, last_login_location, created_at FROM vbs_users ORDER BY created_at DESC')->fetchAll();
            respond(['status' => 'ok', 'users' => $rows]);
        }

        if ($action === 'getAdminLog') {
            $rows = $db->query('SELECT * FROM admin_log ORDER BY id DESC LIMIT 500')->fetchAll();
            respond(['status' => 'ok', 'data' => $rows]);
        }

        if ($action === 'createUser') {
            $uname = trim($body['username'] ?? '');
            $pass  = $body['password'] ?? '';
            $dname = trim($body['display_name'] ?? $uname);
            $role  = $body['role'] ?? 'checkin';
            if (!$uname || !$pass) respond(['status' => 'error', 'message' => 'Username and password required']);
            if (!in_array($role, ['checkin','checkout','both'])) respond(['status' => 'error', 'message' => 'Invalid role']);
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $db->prepare('INSERT INTO vbs_users (username, password_hash, display_name, role) VALUES (?, ?, ?, ?)')
                   ->execute([$uname, $hash, $dname, $role]);
                logAdminAction('create_user', 'Username: '.$uname.', Role: '.$role);
            respond(['status' => 'ok', 'message' => 'User created']);
            } catch (PDOException $e) {
                respond(['status' => 'error', 'message' => 'Username already exists']);
            }
        }

        if ($action === 'updateUser') {
            $id    = intval($body['id'] ?? 0);
            $dname = trim($body['display_name'] ?? '');
            $role  = $body['role'] ?? 'checkin';
            $active = intval($body['active'] ?? 1);
            if (!$id) respond(['status' => 'error', 'message' => 'Invalid user ID']);
            $db->prepare('UPDATE vbs_users SET display_name = ?, role = ?, active = ? WHERE id = ?')
               ->execute([$dname, $role, $active, $id]);
            logAdminAction('update_user', 'User ID: '.$id.', Role: '.$role.', Active: '.$active);
            respond(['status' => 'ok', 'message' => 'User updated']);
        }

        if ($action === 'resetPassword') {
            $id   = intval($body['id'] ?? 0);
            $pass = $body['password'] ?? '';
            if (!$id || !$pass) respond(['status' => 'error', 'message' => 'Invalid request']);
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $db->prepare('UPDATE vbs_users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
            // Invalidate all tokens for this user
            $db->prepare('DELETE FROM vbs_tokens WHERE user_id = ?')->execute([$id]);
            logAdminAction('reset_password', 'User ID: '.$id);
            respond(['status' => 'ok', 'message' => 'Password reset']);
        }

        if ($action === 'updateUserGroups') {
            $id     = intval($body['id'] ?? 0);
            $groups = $body['groups'] ?? []; // array of group names
            if (!$id) respond(['status' => 'error', 'message' => 'Invalid user ID']);
            $validGroups = ['Pre-K', 'Pre-Primary', 'Primary', 'Junior'];
            $db->prepare('DELETE FROM vbs_user_groups WHERE user_id = ?')->execute([$id]);
            $stmt = $db->prepare('INSERT INTO vbs_user_groups (user_id, group_name) VALUES (?, ?)');
            foreach ($groups as $g) {
                if (in_array($g, $validGroups)) $stmt->execute([$id, $g]);
            }
            logAdminAction('assign_groups', 'User ID: '.$id.', Groups: '.implode(', ', $groups));
            respond(['status' => 'ok', 'message' => 'Groups updated']);
        }

        if ($action === 'updateUserPanels') {
            $id     = intval($body['id'] ?? 0);
            $panels = $body['panels'] ?? []; // array of panel names
            if (!$id) respond(['status' => 'error', 'message' => 'Invalid user ID']);
            $validPanels = ['kids', 'volunteers', 'attendance', 'schedule', 'checkin', 'checkout', 'vol_attendance'];
            $db->prepare('DELETE FROM vbs_user_panels WHERE user_id = ?')->execute([$id]);
            $stmt = $db->prepare('INSERT INTO vbs_user_panels (user_id, panel_name) VALUES (?, ?)');
            foreach ($panels as $p) {
                if (in_array($p, $validPanels)) $stmt->execute([$id, $p]);
            }
            logAdminAction('assign_panels', 'User ID: '.$id.', Panels: '.implode(', ', $panels));
            respond(['status' => 'ok', 'message' => 'Panel access updated']);
        }

        if ($action === 'deleteUser') {
            $id = intval($body['id'] ?? 0);
            if (!$id) respond(['status' => 'error', 'message' => 'Invalid user ID']);
            $db->prepare('DELETE FROM vbs_tokens WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM vbs_user_groups WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM vbs_user_panels WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM vbs_users WHERE id = ?')->execute([$id]);
            logAdminAction('delete_user', 'User ID: '.$id);
            respond(['status' => 'ok', 'message' => 'User deleted']);
        }
    }


    if ($action === 'checkin') {
        // Allow kiosk self check-in with fixed secret, otherwise require token
        $isKiosk = ($secret === 'SELFCHECKIN');
        if (!$isKiosk) {
            checkAuthToken($secret);
        }
        $db = getDB();

        // Check if a record already exists for this child today
        $stmt = $db->prepare('SELECT id, checkout_time FROM checkins WHERE day = ? AND child_name = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$body['day'], $body['childName']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $isOverride = !empty($body['override']) && $body['override'] === true;
            if (!$isOverride) {
                $checkedOut = !empty($existing['checkout_time']);
                respond([
                    'status'     => 'already_in',
                    'checkedOut' => $checkedOut,
                    'message'    => $checkedOut ? 'Already checked in and out today' : 'Already checked in today'
                ]);
            }
            // Override path — verify admin key
            $adminKey = $body['adminKey'] ?? '';
            if ($adminKey !== 'VBS2026@ICANJ') {
                respond(['status' => 'error', 'message' => 'Invalid admin PIN']);
            }
            // Admin confirmed — fall through to INSERT
        }

        $stmt = $db->prepare('INSERT INTO checkins
            (day, child_name, class_name, grade, parent, phone, allergies, checkin_time, checkin_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $body['day'],
            $body['childName'],
            $body['className'],
            $body['grade'],
            $body['parent'],
            $body['phone'],
            $body['allergies'],
            $body['checkinTime'],
            $body['checkinBy'] ?? 'Registration Desk'
        ]);
        respond(['status' => 'ok', 'message' => 'Checked in', 'id' => $db->lastInsertId()]);
    }

    /* ── CHECKOUT ── */
    if ($action === 'checkout') {
        checkAuthToken($secret);
        $db = getDB();
        // Migrate column if it doesn't exist yet
        try { $db->exec("ALTER TABLE checkins ADD COLUMN early_checkout_reason VARCHAR(255) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
        // Find the most recent active (not yet checked out) record for this child
        $find = $db->prepare(
            'SELECT id FROM checkins WHERE day = ? AND child_name = ?
             AND (checkout_time IS NULL OR checkout_time = "")
             ORDER BY id DESC LIMIT 1'
        );
        $find->execute([$body['day'], $body['childName']]);
        $row = $find->fetch();
        if ($row) {
            $earlyReason = trim($body['earlyCheckoutReason'] ?? '');
            $db->prepare('UPDATE checkins SET checkout_time = ?, checkout_by = ?, lanyard_back = 1, early_checkout_reason = ? WHERE id = ?')
               ->execute([$body['checkoutTime'], $body['checkoutBy'] ?? 'Teacher', $earlyReason, $row['id']]);
        }
        respond(['status' => 'ok', 'message' => 'Checked out']);
    }

    /* ── UPDATE PIN ── */
    if ($action === 'updatePin') {
        $adminSecret = $body['adminSecret'] ?? '';
        if ($adminSecret !== 'VBS2026@ICANJ') {
            respond(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $db   = getDB();
        $stmt = $db->prepare('UPDATE passwords SET pin = ? WHERE role = ?');
        $stmt->execute([$body['pin'], $body['role']]);
        respond(['status' => 'ok', 'message' => 'PIN updated']);
    }

    /* ── VOL CHECKIN ── */
    if ($action === 'volCheckin') {
        $user = validateToken($secret);
        if (!$user) respond(['status' => 'error', 'message' => 'Unauthorized']);
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS vol_attendance (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            day           TINYINT NOT NULL,
            vol_name      VARCHAR(150) NOT NULL,
            checkin_time  VARCHAR(20)  NOT NULL,
            checkout_time VARCHAR(20)  NOT NULL DEFAULT '',
            checkin_by    VARCHAR(100) NOT NULL DEFAULT 'Self',
            checkout_by   VARCHAR(100) NOT NULL DEFAULT '',
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $day     = intval($body['day']);
        $name    = trim($body['volName'] ?? '');
        if (!$day || !$name) respond(['status' => 'error', 'message' => 'Day and name required']);
        // Prevent duplicate check-in same day
        $existing = $db->prepare('SELECT id, checkout_time FROM vol_attendance WHERE day = ? AND vol_name = ? ORDER BY id DESC LIMIT 1');
        $existing->execute([$day, $name]);
        $row = $existing->fetch();
        if ($row && empty($row['checkout_time'])) {
            respond(['status' => 'already_in', 'message' => 'Already checked in today']);
        }
        $stmt = $db->prepare('INSERT INTO vol_attendance (day, vol_name, checkin_time, checkin_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$day, $name, trim($body['checkinTime']), trim($body['checkinBy'] ?? 'Self')]);
        respond(['status' => 'ok', 'message' => 'Volunteer checked in', 'id' => $db->lastInsertId()]);
    }

    /* ── VOL CHECKOUT ── */
    if ($action === 'volCheckout') {
        $user = validateToken($secret);
        if (!$user) respond(['status' => 'error', 'message' => 'Unauthorized']);
        $db  = getDB();
        $day = intval($body['day']);
        $name = trim($body['volName'] ?? '');
        $find = $db->prepare('SELECT id FROM vol_attendance WHERE day = ? AND vol_name = ? AND (checkout_time IS NULL OR checkout_time = "") ORDER BY id DESC LIMIT 1');
        $find->execute([$day, $name]);
        $row = $find->fetch();
        if (!$row) respond(['status' => 'error', 'message' => 'No active check-in found']);
        $db->prepare('UPDATE vol_attendance SET checkout_time = ?, checkout_by = ? WHERE id = ?')
           ->execute([trim($body['checkoutTime']), trim($body['checkoutBy'] ?? 'Self'), $row['id']]);
        respond(['status' => 'ok', 'message' => 'Volunteer checked out']);
    }

    /* ── VOL READ ── */
    if ($action === 'volRead') {
        $user = validateToken($secret);
        if (!$user) respond(['status' => 'error', 'message' => 'Unauthorized']);
        $db  = getDB();
        $day = intval($_GET['day'] ?? $body['day'] ?? 0);
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS vol_attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                day TINYINT NOT NULL,
                vol_name VARCHAR(150) NOT NULL,
                checkin_time VARCHAR(20) NOT NULL,
                checkout_time VARCHAR(20) NOT NULL DEFAULT '',
                checkin_by VARCHAR(100) NOT NULL DEFAULT 'Self',
                checkout_by VARCHAR(100) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            if ($day > 0) {
                $stmt = $db->prepare('SELECT * FROM vol_attendance WHERE day = ? ORDER BY checkin_time ASC');
                $stmt->execute([$day]);
            } else {
                $stmt = $db->prepare('SELECT * FROM vol_attendance ORDER BY day ASC, checkin_time ASC');
                $stmt->execute();
            }
            respond(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            respond(['status' => 'ok', 'data' => []]);
        }
    }

    /* ── TSHIRT MARK ── */
    if ($action === 'tshirtMark') {
        $user = validateToken($secret);
        if (!$user) respond(['status' => 'error', 'message' => 'Unauthorized']);
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS tshirt_distribution (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(150) NOT NULL,
            tshirt_size VARCHAR(50)  NOT NULL DEFAULT '',
            received    TINYINT(1)   NOT NULL DEFAULT 0,
            is_walkin   TINYINT(1)   NOT NULL DEFAULT 0,
            given_at    VARCHAR(20)  NOT NULL DEFAULT '',
            given_by    VARCHAR(100) NOT NULL DEFAULT '',
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $name     = trim($body['name'] ?? '');
        $size     = trim($body['tshirtSize'] ?? '');
        $givenBy  = trim($body['givenBy'] ?? '');
        $isWalkin = intval($body['isWalkin'] ?? 0);
        $now      = date('g:i A');
        if (!$name) respond(['status' => 'error', 'message' => 'Name required']);
        $existing = $db->prepare('SELECT id FROM tshirt_distribution WHERE LOWER(name) = LOWER(?)');
        $existing->execute([$name]);
        $row = $existing->fetch();
        if ($row) {
            $db->prepare('UPDATE tshirt_distribution SET received=1, tshirt_size=?, given_at=?, given_by=?, is_walkin=? WHERE id=?')
               ->execute([$size, $now, $givenBy, $isWalkin, $row['id']]);
            respond(['status' => 'ok', 'id' => $row['id']]);
        } else {
            $db->prepare('INSERT INTO tshirt_distribution (name, tshirt_size, received, is_walkin, given_at, given_by) VALUES (?,?,1,?,?,?)')
               ->execute([$name, $size, $isWalkin, $now, $givenBy]);
            respond(['status' => 'ok', 'id' => $db->lastInsertId()]);
        }
    }

    /* ── TSHIRT UNMARK (admin only) ── */
    if ($action === 'tshirtUnmark') {
        $adminSecret = $body['adminSecret'] ?? '';
        if ($adminSecret !== 'VBS2026@ICANJ') respond(['status' => 'error', 'message' => 'Unauthorized']);
        $db   = getDB();
        $name = trim($body['name'] ?? '');
        if (!$name) respond(['status' => 'error', 'message' => 'Name required']);
        $existing = $db->prepare('SELECT id, is_walkin FROM tshirt_distribution WHERE LOWER(name) = LOWER(?)');
        $existing->execute([$name]);
        $row = $existing->fetch();
        if ($row) {
            if ($row['is_walkin']) {
                $db->prepare('DELETE FROM tshirt_distribution WHERE id = ?')->execute([$row['id']]);
            } else {
                $db->prepare('UPDATE tshirt_distribution SET received=0, given_at="", given_by="" WHERE id=?')
                   ->execute([$row['id']]);
            }
        }
        respond(['status' => 'ok']);
    }

    /* ── SYNC VOLUNTEERS → AUTO-CREATE ACCOUNTS ── */
    if ($action === 'syncVolunteers') {
        $adminSecret = $body['adminSecret'] ?? '';
        if ($adminSecret !== 'VBS2026@ICANJ') respond(['status' => 'error', 'message' => 'Unauthorized']);

        $csvUrl  = $body['csvUrl'] ?? '';
        $names   = $body['names'] ?? []; // array of full names from JS CSV parse
        if (empty($names)) respond(['status' => 'error', 'message' => 'No volunteer names provided']);

        ensureUsersTable();
        $db = getDB();

        // Ensure plaintext password column exists
        try { $db->exec("ALTER TABLE vbs_users ADD COLUMN plain_password VARCHAR(50) NOT NULL DEFAULT ''"); } catch(Exception $e) {}

        $created = [];
        $skipped = [];

        foreach ($names as $fullName) {
            $fullName = trim($fullName);
            if (!$fullName) continue;

            // Build username: first initial + last name, lowercase, no spaces
            $parts     = preg_split('/\s+/', $fullName);
            $firstName = $parts[0] ?? '';
            $lastName  = $parts[count($parts) - 1] ?? '';
            $username  = strtolower(substr($firstName, 0, 1) . $lastName);
            $username  = preg_replace('/[^a-z0-9._-]/', '', $username);
            if (!$username) continue;

            // Check if user already exists by display_name ONLY (case-insensitive)
            // Do NOT check username here — duplicate usernames get a suffix (jmathew, jmathew2)
            $existing = $db->prepare('SELECT id FROM vbs_users WHERE LOWER(display_name) = LOWER(?) LIMIT 1');
            $existing->execute([$fullName]);
            if ($existing->fetch()) {
                $skipped[] = $fullName;
                continue;
            }

            // Handle duplicate usernames (e.g. two John Smiths → jsmith, jsmith2)
            $baseUsername = $username;
            $suffix = 2;
            while (true) {
                $check = $db->prepare('SELECT id FROM vbs_users WHERE username = ? LIMIT 1');
                $check->execute([$username]);
                if (!$check->fetch()) break;
                $username = $baseUsername . $suffix++;
            }

            // Generate random 8-char password: 4 letters + 4 digits
            $letters = 'abcdefghjkmnpqrstuvwxyz';
            $digits  = '23456789';
            $pass    = '';
            for ($i = 0; $i < 4; $i++) $pass .= $letters[random_int(0, strlen($letters)-1)];
            for ($i = 0; $i < 4; $i++) $pass .= $digits[random_int(0, strlen($digits)-1)];
            $pass = str_shuffle($pass);

            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $db->prepare('INSERT INTO vbs_users (username, password_hash, plain_password, display_name, role) VALUES (?,?,?,?,?)')
               ->execute([$username, $hash, $pass, $fullName, 'both']);

            $userId = $db->lastInsertId();

            // Assign schedule + vol_attendance panels by default
            $panelStmt = $db->prepare('INSERT IGNORE INTO vbs_user_panels (user_id, panel_name) VALUES (?,?)');
            $panelStmt->execute([$userId, 'schedule']);
            $panelStmt->execute([$userId, 'vol_attendance']);

            logAdminAction('create_user', 'Auto-synced volunteer: ' . $fullName . ' → @' . $username);
            $created[] = ['name' => $fullName, 'username' => $username, 'password' => $pass];
        }

        respond(['status' => 'ok', 'created' => $created, 'skipped' => $skipped]);
    }

    respond(['status' => 'error', 'message' => 'Unknown action']);
}
if ($method === 'GET') {
    // already handled above — but volRead can also arrive as GET
}

respond(['status' => 'error', 'message' => 'Invalid request']);