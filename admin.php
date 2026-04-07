<?php
/**
 * VBS 2026 — Admin Panel
 * https://pypaonline.org/vbs/admin.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', 'db5019985220.hosting-data.io');
define('DB_PORT', '3306');
define('DB_NAME', 'dbs15421479');
define('DB_USER', 'dbu1115673');
define('DB_PASS', 'Lord@20222024');
define('ADMIN_PASSWORD', 'VBS2026@ICANJ');

session_start();

function getDB() {
    return new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function logAction($action, $detail = '') {
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS admin_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            detail TEXT,
            logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->prepare("INSERT INTO admin_log (action, detail) VALUES (?, ?)")
           ->execute([$action, $detail]);
    } catch (Exception $e) {}
}

function bumpDataVersion() {
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS settings (`key` VARCHAR(100) PRIMARY KEY, `value` VARCHAR(255) NOT NULL)");
        $ts = (string) time();
        $db->prepare("INSERT INTO settings (`key`, `value`) VALUES ('data_version', ?) ON DUPLICATE KEY UPDATE `value` = ?")
           ->execute([$ts, $ts]);
    } catch (Exception $e) {}
}

// ── LOGIN ──
if (($_POST['action'] ?? '') === 'login') {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['vbs_admin'] = true;
        logAction('login', 'Admin logged in');
    } else {
        $loginError = 'Wrong password';
    }
}

// ── LOGOUT ──
if (($_GET['logout'] ?? '') === '1') {
    logAction('logout', 'Admin logged out');
    session_destroy();
    header('Location: admin.php');
    exit;
}

$msg = '';
$msgType = 'ok';

$getMessages = [
    'backed_up'        => ['✅ Backup created successfully!', 'ok'],
    'log_cleared'      => ['✅ Activity log cleared.', 'ok'],
    'pin_updated'      => ['✅ PIN updated successfully!', 'ok'],
    'settings_saved'   => ['✅ Day settings saved!', 'ok'],
    'record_added'     => ['✅ Record added successfully!', 'ok'],
    'record_deleted'   => ['✅ Record deleted.', 'ok'],
    'checkout_done'    => ['✅ Checkout recorded.', 'ok'],
    'restore_json_ok'  => ['✅ Records restored from backup!', 'ok'],
    'restore_sheet_ok' => ['✅ Records restored from Google Sheet!', 'ok'],
    'reset_ok'         => ['✅ Database reset! All records deleted and ID counter restarted from 1.', 'ok'],
    'reset_err'        => ['❌ Wrong confirmation text. Database NOT reset.', 'err'],
];
if (isset($_GET['msg']) && array_key_exists($_GET['msg'], $getMessages)) {
    [$msg, $msgType] = $getMessages[$_GET['msg']];
    $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
    $qs = $_GET;
    unset($qs['msg']);
    $cleanUrl .= $qs ? '?' . http_build_query($qs) : '';
    echo '<script>history.replaceState(null,"","' . htmlspecialchars($cleanUrl, ENT_QUOTES) . '");</script>';
}
if (isset($_SESSION['vbs_flash'])) {
    [$msg, $msgType] = $_SESSION['vbs_flash'];
    unset($_SESSION['vbs_flash']);
}

if (isset($_SESSION['vbs_admin'])) {

    // ── CREATE USER ──
    if (($_POST['action'] ?? '') === 'createUser') {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS vbs_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) NOT NULL DEFAULT '',
            role ENUM('checkin','checkout','both') NOT NULL DEFAULT 'checkin',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME NULL,
            last_logout DATETIME NULL
        )");
        $uname = trim($_POST['username'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $dname = trim($_POST['display_name'] ?? $uname);
        $role  = $_POST['role'] ?? 'checkin';
        if ($uname && $pass) {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $db->prepare('INSERT INTO vbs_users (username, password_hash, display_name, role) VALUES (?, ?, ?, ?)')
                   ->execute([$uname, $hash, $dname, $role]);
                logAction('create_user', 'Username: ' . $uname . ', Role: ' . $role);
            } catch (PDOException $e) {}
        }
        header('Location: admin.php?msg=user_created');
        exit;
    }

    // ── UPDATE PIN ──
    if (($_POST['action'] ?? '') === 'updatePin') {
        $db = getDB();
        $db->prepare('UPDATE passwords SET pin = ? WHERE role = ?')->execute([$_POST['pin'], $_POST['role']]);
        logAction('update_pin', 'Role: ' . $_POST['role']);
        header('Location: admin.php?msg=pin_updated');
        exit;
    }

    // ── SAVE DAY SETTINGS ──
    if (($_POST['action'] ?? '') === 'saveSettings') {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(100) PRIMARY KEY,
            `value` VARCHAR(255) NOT NULL
        )");
        $stmt = $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        foreach (['day1_enabled','day2_enabled','day3_enabled'] as $k) {
            $v = ($_POST[$k] ?? '') === '1' ? '1' : '0';
            $stmt->execute([$k, $v]);
        }
        $vals = implode(', ', array_map(fn($k) => $k . '=' . ($_POST[$k] ?? '0'), ['day1_enabled','day2_enabled','day3_enabled']));
        logAction('save_settings', $vals);
        header('Location: admin.php?msg=settings_saved');
        exit;
    }

    // ── MANUAL ADD CHECKIN ──
    if (($_POST['action'] ?? '') === 'addRecord') {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO checkins
            (day, child_name, class_name, grade, parent, phone, allergies, checkin_time, checkin_by, checkout_time, checkout_by, lanyard_back)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            intval($_POST['day']),
            trim($_POST['child_name']),
            trim($_POST['class_name']),
            trim($_POST['grade']),
            trim($_POST['parent']),
            trim($_POST['phone']),
            trim($_POST['allergies']) ?: 'None',
            trim($_POST['checkin_time']),
            trim($_POST['checkin_by']) ?: 'Admin',
            trim($_POST['checkout_time']) ?: null,
            trim($_POST['checkout_by']) ?: null,
            isset($_POST['lanyard_back']) ? 1 : 0
        ]);
        logAction('add_record', 'Child: ' . trim($_POST['child_name']) . ', Day ' . intval($_POST['day']));
        header('Location: admin.php?msg=record_added');
        exit;
    }

    // ── DELETE RECORD ──
    if (($_POST['action'] ?? '') === 'deleteRecord') {
        $db = getDB();
        $rid = intval($_POST['id']);
        $rrow = $db->prepare('SELECT child_name, day FROM checkins WHERE id = ?');
        $rrow->execute([$rid]);
        $rdata = $rrow->fetch();
        $db->prepare('DELETE FROM checkins WHERE id = ?')->execute([$rid]);
        logAction('delete_record', 'ID #' . $rid . ' · ' . ($rdata['child_name'] ?? '?') . ' · Day ' . ($rdata['day'] ?? '?'));
        header('Location: admin.php?msg=record_deleted');
        exit;
    }

    // ── RESET DATABASE ──
    if (($_POST['action'] ?? '') === 'resetDB') {
        $confirm = $_POST['confirm_reset'] ?? '';
        if ($confirm !== 'delete data completely') {
            header('Location: admin.php?msg=reset_err');
            exit;
        } else {
            $db = getDB();
            $db->exec('DELETE FROM checkins');
            $db->exec('ALTER TABLE checkins AUTO_INCREMENT = 1');
            try { $db->exec('DELETE FROM vol_attendance'); $db->exec('ALTER TABLE vol_attendance AUTO_INCREMENT = 1'); } catch(Exception $e) {}
            try { $db->exec('DELETE FROM tshirt_distribution'); $db->exec('ALTER TABLE tshirt_distribution AUTO_INCREMENT = 1'); } catch(Exception $e) {}
            logAction('reset_database', 'All checkin + volunteer attendance + tshirt distribution records deleted, AUTO_INCREMENT reset to 1');
            bumpDataVersion();
            header('Location: admin.php?msg=reset_ok');
            exit;
        }
    }

    // ── RESTORE FROM GOOGLE SHEET ──
    if (($_POST['action'] ?? '') === 'restoreFromSheet') {
        ob_start(); // buffer any warnings so headers() still work
        try {
            $inputUrl = trim($_POST['sheet_url'] ?? '');
            if (!$inputUrl) throw new Exception('No URL provided');
            if (strpos($inputUrl, 'docs.google.com/spreadsheets') === false) {
                throw new Exception('Please paste a Google Sheets share URL (from File → Share → Share with others)');
            }

            // Extract spreadsheet ID from any Google Sheets URL format:
            // /spreadsheets/d/ID/edit, /spreadsheets/d/ID/pub, etc.
            if (!preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $inputUrl, $m)) {
                throw new Exception('Could not extract spreadsheet ID. Please paste the share link from File → Share → Share with others → Anyone with link → Copy link.');
            }
            $sheetId = $m[1];

            // Use gid-based export URLs — tab names are unreliable, gids always work.
            $tabGids = [
                'ALL_RECORDS'    => '1521734256',
                'Vol_Attendance' => '1347065185',
                'Merch_Tshirts'  => '1803629113',
            ];
            $tabUrl = function($tabName) use ($sheetId, $tabGids) {
                $gid = $tabGids[$tabName] ?? null;
                if (!$gid) throw new Exception('Unknown tab: ' . $tabName);
                return 'https://docs.google.com/spreadsheets/d/' . $sheetId
                     . '/export?format=csv&gid=' . $gid;
            };

            $context = stream_context_create(['http' => [
                'timeout'         => 30,
                'follow_location' => true,
                'ignore_errors'   => true,
                'user_agent'      => 'Mozilla/5.0 (compatible; VBS-Restore/1.0)',
            ]]);

            // Columns to ignore from the sheet (metadata added by Apps Script)
            $skipColumns = ['last_synced'];

            $fetchTab = function($tabName) use ($tabUrl, $context, $skipColumns) {
                $url = $tabUrl($tabName);
                $csv = @file_get_contents($url, false, $context);
                if ($csv === false || strlen(trim($csv)) < 10) return null;
                // Detect HTML error response (e.g. login page or 404)
                if (stripos(trim($csv), '<!doctype') === 0 || stripos(trim($csv), '<html') === 0) return null;
                $csv   = str_replace(["\r\n", "\r"], "\n", $csv);
                $lines = array_values(array_filter(explode("\n", trim($csv)), function($l){ return trim($l) !== ''; }));
                if (count($lines) < 2) return null;
                $headers = array_map('trim', str_getcsv($lines[0], ',', '"', '\\'));
                if (empty($headers[0]) || stripos($headers[0], '<!') !== false || stripos($headers[0], 'error') !== false) return null;
                $rows = [];
                for ($i = 1; $i < count($lines); $i++) {
                    $vals = str_getcsv($lines[$i], ',', '"', '\\');
                    $row  = [];
                    foreach ($headers as $hi => $h) {
                        if (in_array($h, $skipColumns)) continue; // skip last_synced etc.
                        $row[$h] = trim($vals[$hi] ?? '');
                    }
                    if (!empty($row['id'])) $rows[] = $row;
                }
                $cleanHeaders = array_values(array_diff($headers, $skipColumns));
                return ['headers' => $cleanHeaders, 'rows' => $rows, 'count' => count($rows)];
            };

            $db = getDB();
            $totalLog = [];
            $debugLog = []; // temporary debug — remove after testing

            // Helper to log fetch results for debugging
            $debugFetch = function($tabName) use ($fetchTab, &$debugLog, $tabUrl, $sheetId) {
                $url  = 'https://docs.google.com/spreadsheets/d/' . $sheetId . '/export?format=csv&sheet=' . urlencode($tabName);
                $data = $fetchTab($tabName);
                if ($data === null) {
                    $debugLog[] = '❌ ' . $tabName . ': returned null (tab missing, empty, or not accessible) — URL: ' . $url;
                } else {
                    $debugLog[] = '✅ ' . $tabName . ': ' . $data['count'] . ' rows | headers: ' . implode(', ', $data['headers']);
                }
                return $data;
            };

            // ── FETCH & RESTORE CHECKINS from ALL_RECORDS tab ──
            $checkinData = $debugFetch('ALL_RECORDS');
            if (!$checkinData) throw new Exception(
                'Could not fetch the ALL_RECORDS tab. Make sure the sheet is shared as "Anyone with the link can view" ' .
                '(File → Share → Share with others → change to Anyone with the link → Viewer → Copy link).'
            );
            $required = ['id','day','child_name','class_name','grade','parent','phone','allergies',
                         'checkin_time','checkin_by','checkout_time','checkout_by','lanyard_back','created_at'];
            foreach ($required as $col) {
                if (!in_array($col, $checkinData['headers'])) {
                    throw new Exception('ALL_RECORDS tab is missing required column: "' . $col . '". Headers found: ' . implode(', ', $checkinData['headers']));
                }
            }
            $hasEarlyReason = in_array('early_checkout_reason', $checkinData['headers']);
            try { $db->exec("ALTER TABLE checkins ADD COLUMN early_checkout_reason VARCHAR(255) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
            $db->exec('DELETE FROM checkins');
            $cstmt = $db->prepare('INSERT INTO checkins
                (id, day, child_name, class_name, grade, parent, phone, allergies,
                 checkin_time, checkin_by, checkout_time, checkout_by, lanyard_back,
                 early_checkout_reason, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($checkinData['rows'] as $row) {
                $cstmt->execute([
                    intval($row['id']),
                    intval($row['day']),
                    $row['child_name'] ?? '',
                    $row['class_name'] ?? '',
                    $row['grade'] ?? '',
                    $row['parent'] ?? '',
                    $row['phone'] ?? '',
                    ($row['allergies'] ?? '') ?: 'None',
                    $row['checkin_time'] ?? '',
                    $row['checkin_by'] ?? '',
                    ($row['checkout_time'] ?? '') ?: null,
                    ($row['checkout_by'] ?? '') ?: null,
                    intval($row['lanyard_back'] ?? 0),
                    $hasEarlyReason ? ($row['early_checkout_reason'] ?? '') : '',
                    ($row['created_at'] ?? '') ?: date('Y-m-d H:i:s')
                ]);
            }
            $maxId = $db->query('SELECT MAX(id) FROM checkins')->fetchColumn();
            if ($maxId) $db->exec('ALTER TABLE checkins AUTO_INCREMENT = ' . ($maxId + 1));
            $totalLog[] = $checkinData['count'] . ' check-ins';

            // ── FETCH & RESTORE VOL_ATTENDANCE tab (optional) ──
            $volData = $debugFetch('Vol_Attendance');
            if ($volData && $volData['count'] > 0) {
                $db->exec("CREATE TABLE IF NOT EXISTS vol_attendance (
                    id INT AUTO_INCREMENT PRIMARY KEY, day TINYINT NOT NULL,
                    vol_name VARCHAR(150) NOT NULL, checkin_time VARCHAR(20) NOT NULL,
                    checkout_time VARCHAR(20) NOT NULL DEFAULT '',
                    checkin_by VARCHAR(100) NOT NULL DEFAULT 'Self',
                    checkout_by VARCHAR(100) NOT NULL DEFAULT '',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec('DELETE FROM vol_attendance');
                $vstmt = $db->prepare('INSERT INTO vol_attendance
                    (id, day, vol_name, checkin_time, checkin_by, checkout_time, checkout_by, created_at)
                    VALUES (?,?,?,?,?,?,?,?)');
                foreach ($volData['rows'] as $row) {
                    if (empty($row['vol_name'])) continue; // skip malformed rows
                    $vstmt->execute([
                        intval($row['id']),
                        intval($row['day']),
                        $row['vol_name'],
                        $row['checkin_time'] ?? '',
                        ($row['checkin_by'] ?? '') ?: 'Self',
                        ($row['checkout_time'] ?? '') ?: '',
                        ($row['checkout_by'] ?? '') ?: '',
                        ($row['created_at'] ?? '') ?: date('Y-m-d H:i:s')
                    ]);
                }
                $maxVolId = $db->query('SELECT MAX(id) FROM vol_attendance')->fetchColumn();
                if ($maxVolId) $db->exec('ALTER TABLE vol_attendance AUTO_INCREMENT = ' . ($maxVolId + 1));
                $totalLog[] = $volData['count'] . ' volunteer attendance';
            }

            // ── FETCH & RESTORE MERCH_TSHIRTS tab (optional) ──
            $merchData = $debugFetch('Merch_Tshirts');
            if ($merchData && $merchData['count'] > 0) {
                $db->exec("CREATE TABLE IF NOT EXISTS tshirt_distribution (
                    id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL,
                    tshirt_size VARCHAR(50) NOT NULL DEFAULT '',
                    received TINYINT(1) NOT NULL DEFAULT 0,
                    is_walkin TINYINT(1) NOT NULL DEFAULT 0,
                    given_at VARCHAR(20) NOT NULL DEFAULT '',
                    given_by VARCHAR(100) NOT NULL DEFAULT '',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec('DELETE FROM tshirt_distribution');
                $tstmt = $db->prepare('INSERT INTO tshirt_distribution
                    (id, name, tshirt_size, received, is_walkin, given_at, given_by, created_at)
                    VALUES (?,?,?,?,?,?,?,?)');
                foreach ($merchData['rows'] as $row) {
                    if (empty($row['name'])) continue; // skip malformed rows
                    $tstmt->execute([
                        intval($row['id']),
                        $row['name'],
                        ($row['tshirt_size'] ?? '') ?: '',
                        intval($row['received'] ?? 0),
                        intval($row['is_walkin'] ?? 0),
                        ($row['given_at'] ?? '') ?: '',
                        ($row['given_by'] ?? '') ?: '',
                        ($row['created_at'] ?? '') ?: date('Y-m-d H:i:s')
                    ]);
                }
                $maxMerchId = $db->query('SELECT MAX(id) FROM tshirt_distribution')->fetchColumn();
                if ($maxMerchId) $db->exec('ALTER TABLE tshirt_distribution AUTO_INCREMENT = ' . ($maxMerchId + 1));
                $totalLog[] = $merchData['count'] . ' merch records';
            }

            logAction('restore_from_sheet', 'Restored from Google Sheet: ' . implode(', ', $totalLog));
            bumpDataVersion();
            ob_end_clean();
            // Show debug info in flash message temporarily
            $debugInfo = implode(' | ', $debugLog);
            $_SESSION['vbs_flash'] = ['✅ Restored: ' . implode(', ', $totalLog) . ' — DEBUG: ' . $debugInfo, 'ok'];
            header('Location: admin.php');
            exit;
        } catch (Exception $e) {
            ob_end_clean();
            $_SESSION['vbs_flash'] = ['❌ Restore failed: ' . $e->getMessage(), 'err'];
            header('Location: admin.php');
            exit;
        }
    }

    // ── MANUAL CHECKOUT ──
    if (($_POST['action'] ?? '') === 'manualCheckout') {
        $db = getDB();
        $db->prepare('UPDATE checkins SET checkout_time = ?, checkout_by = ?, lanyard_back = 1 WHERE id = ?')
           ->execute([trim($_POST['checkout_time']), trim($_POST['checkout_by']) ?: 'Admin', intval($_POST['id'])]);
        logAction('manual_checkout', 'Record ID: ' . intval($_POST['id']) . ', Time: ' . trim($_POST['checkout_time']));
        header('Location: admin.php?msg=checkout_done');
        exit;
    }

    // ── CLEAR LOG ──
    if (($_POST['action'] ?? '') === 'clearLog') {
        try {
            $db = getDB();
            $db->exec('TRUNCATE TABLE admin_log');
            logAction('clear_log', 'Admin log cleared');
        } catch (Exception $e) {}
        header('Location: admin.php?msg=log_cleared');
        exit;
    }

    // ── RESTORE FROM JSON ──
    if (($_POST['action'] ?? '') === 'restoreJSON') {
        try {
            if (!empty($_POST['restore_server_file'])) {
                $sf   = basename($_POST['restore_server_file']);
                $path = __DIR__ . '/backups/' . $sf;
                if (!preg_match('/^vbs2026-backup-[\d-]+\.json$/', $sf) || !file_exists($path)) {
                    throw new Exception('Backup file not found on server');
                }
                $json = file_get_contents($path);
            } else {
                $json = file_get_contents($_FILES['backup_file']['tmp_name']);
            }
            $data = json_decode($json, true);
            if (!$data || !isset($data['checkins'])) throw new Exception('Invalid backup file');
            $db = getDB();

            // ── Restore checkins ──
            try { $db->exec("ALTER TABLE checkins ADD COLUMN early_checkout_reason VARCHAR(255) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
            $db->exec('DELETE FROM checkins');
            $stmt = $db->prepare('INSERT INTO checkins
                (id, day, child_name, class_name, grade, parent, phone, allergies,
                 checkin_time, checkin_by, checkout_time, checkout_by, lanyard_back,
                 early_checkout_reason, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($data['checkins'] as $r) {
                $stmt->execute([
                    $r['id'], $r['day'], $r['child_name'], $r['class_name'], $r['grade'],
                    $r['parent'], $r['phone'], $r['allergies'],
                    $r['checkin_time'], $r['checkin_by'],
                    $r['checkout_time'] ?: null, $r['checkout_by'] ?: null,
                    $r['lanyard_back'],
                    $r['early_checkout_reason'] ?? '',
                    $r['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }

            // ── Restore vol_attendance (if present) ──
            if (!empty($data['vol_attendance'])) {
                try {
                    $db->exec("CREATE TABLE IF NOT EXISTS vol_attendance (
                        id INT AUTO_INCREMENT PRIMARY KEY, day TINYINT NOT NULL,
                        vol_name VARCHAR(150) NOT NULL, checkin_time VARCHAR(20) NOT NULL,
                        checkout_time VARCHAR(20) NOT NULL DEFAULT '',
                        checkin_by VARCHAR(100) NOT NULL DEFAULT 'Self',
                        checkout_by VARCHAR(100) NOT NULL DEFAULT '',
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )");
                    $db->exec('DELETE FROM vol_attendance');
                    $vstmt = $db->prepare('INSERT INTO vol_attendance
                        (id, day, vol_name, checkin_time, checkin_by, checkout_time, checkout_by, created_at)
                        VALUES (?,?,?,?,?,?,?,?)');
                    foreach ($data['vol_attendance'] as $r) {
                        $vstmt->execute([
                            $r['id'], $r['day'], $r['vol_name'],
                            $r['checkin_time'], $r['checkin_by'] ?? 'Self',
                            $r['checkout_time'] ?? '', $r['checkout_by'] ?? '',
                            $r['created_at'] ?? date('Y-m-d H:i:s')
                        ]);
                    }
                } catch(Exception $e) {}
            }

            // ── Restore tshirt_distribution (if present) ──
            if (!empty($data['tshirt_distribution'])) {
                try {
                    $db->exec("CREATE TABLE IF NOT EXISTS tshirt_distribution (
                        id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL,
                        tshirt_size VARCHAR(50) NOT NULL DEFAULT '',
                        received TINYINT(1) NOT NULL DEFAULT 0,
                        is_walkin TINYINT(1) NOT NULL DEFAULT 0,
                        given_at VARCHAR(20) NOT NULL DEFAULT '',
                        given_by VARCHAR(100) NOT NULL DEFAULT '',
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )");
                    $db->exec('DELETE FROM tshirt_distribution');
                    $tstmt = $db->prepare('INSERT INTO tshirt_distribution
                        (id, name, tshirt_size, received, is_walkin, given_at, given_by, created_at)
                        VALUES (?,?,?,?,?,?,?,?)');
                    foreach ($data['tshirt_distribution'] as $r) {
                        $tstmt->execute([
                            $r['id'], $r['name'], $r['tshirt_size'] ?? '',
                            $r['received'] ?? 0, $r['is_walkin'] ?? 0,
                            $r['given_at'] ?? '', $r['given_by'] ?? '',
                            $r['created_at'] ?? date('Y-m-d H:i:s')
                        ]);
                    }
                } catch(Exception $e) {}
            }

            $totalRestored = count($data['checkins'])
                . ' check-ins'
                . (!empty($data['vol_attendance']) ? ', ' . count($data['vol_attendance']) . ' vol attendance' : '')
                . (!empty($data['tshirt_distribution']) ? ', ' . count($data['tshirt_distribution']) . ' merch records' : '');
            logAction('restore_json', 'Restored: ' . $totalRestored);
            bumpDataVersion();
            header('Location: admin.php?msg=restore_json_ok');
            exit;
        } catch (Exception $e) {
            $_SESSION['vbs_flash'] = ['❌ Restore failed: ' . $e->getMessage(), 'err'];
            header('Location: admin.php');
            exit;
        }
    }

    // ── EXPORT JSON BACKUP ──
    if (($_GET['export'] ?? '') === 'json') {
        $db = getDB();
        $checkins = $db->query('SELECT * FROM checkins ORDER BY day ASC, id ASC')->fetchAll();
        $volAtt = [];
        try { $volAtt = $db->query('SELECT * FROM vol_attendance ORDER BY day ASC, id ASC')->fetchAll(); } catch(Exception $e) {}
        $tshirts = [];
        try { $tshirts = $db->query('SELECT * FROM tshirt_distribution ORDER BY id ASC')->fetchAll(); } catch(Exception $e) {}
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="vbs2026-backup-' . date('Ymd-His') . '.json"');
        echo json_encode([
            'exported_at'         => date('Y-m-d H:i:s'),
            'checkins'            => $checkins,
            'vol_attendance'      => $volAtt,
            'tshirt_distribution' => $tshirts,
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ── DOWNLOAD SPECIFIC AUTO-BACKUP FILE ──
    if (($_GET['export'] ?? '') === 'autobackup') {
        $file = basename($_GET['file'] ?? '');
        $path = __DIR__ . '/backups/' . $file;
        if ($file && preg_match('/^vbs2026-backup-[\d-]+\.json$/', $file) && file_exists($path)) {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            readfile($path);
        } else {
            http_response_code(404); echo 'File not found';
        }
        exit;
    }

    // ── DEBUG: TEST SHEET FETCH ──
    if (($_GET['action'] ?? '') === 'debugSheet') {
        $sheetUrl = trim($_GET['url'] ?? '');
        header('Content-Type: text/plain; charset=utf-8');
        if (!$sheetUrl || !preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $sheetUrl, $m)) {
            echo "ERROR: No valid sheet URL provided.\n"; exit;
        }
        $sheetId = $m[1];
        $skipColumns = ['last_synced'];
        $tabs = [
            'ALL_RECORDS'    => '1521734256',
            'Vol_Attendance' => '1347065185',
            'Merch_Tshirts'  => '1803629113',
        ];
        $context = stream_context_create(['http' => [
            'timeout' => 30, 'follow_location' => true,
            'ignore_errors' => true,
            'user_agent' => 'Mozilla/5.0 (compatible; VBS-Debug/1.0)',
        ]]);
        foreach ($tabs as $tab => $gid) {
            $url = 'https://docs.google.com/spreadsheets/d/' . $sheetId . '/export?format=csv&gid=' . $gid;
            echo "=== TAB: $tab ===\n";
            echo "URL: $url\n";
            $csv = @file_get_contents($url, false, $context);
            if ($csv === false) { echo "RESULT: file_get_contents returned FALSE (network error or blocked)\n\n"; continue; }
            if (stripos(trim($csv), '<!doctype') === 0 || stripos(trim($csv), '<html') === 0) {
                echo "RESULT: Got HTML instead of CSV (sheet not public or tab doesn't exist)\n";
                echo "First 300 chars: " . substr($csv, 0, 300) . "\n\n"; continue;
            }
            $lines = array_values(array_filter(explode("\n", str_replace(["\r\n","\r"],"\n",trim($csv))), fn($l) => trim($l) !== ''));
            echo "ROW COUNT: " . (count($lines) - 1) . " data rows\n";
            echo "HEADERS: " . $lines[0] . "\n";
            if (isset($lines[1])) echo "ROW 1: " . $lines[1] . "\n";
            if (isset($lines[2])) echo "ROW 2: " . $lines[2] . "\n";
            echo "\n";
        }
        exit;
    }

    // ── RUN MANUAL BACKUP NOW ──
    if (($_GET['action'] ?? '') === 'runBackup') {
        include_once __DIR__ . '/backup.php';
        logAction('run_backup', 'Manual backup triggered');
        header('Location: admin.php?msg=backed_up');
        exit;
    }

    // ── EXPORT CSV ──
    if (($_GET['export'] ?? '') === 'csv') {
        $db  = getDB();
        $day = intval($_GET['day'] ?? 0);
        $stmt = $day > 0
            ? $db->prepare('SELECT * FROM checkins WHERE day = ? ORDER BY checkin_time ASC')
            : $db->prepare('SELECT * FROM checkins ORDER BY day ASC, checkin_time ASC');
        $day > 0 ? $stmt->execute([$day]) : $stmt->execute();
        $rows = $stmt->fetchAll();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="vbs2026-checkins-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Day','Child Name','Class','Grade','Parent','Phone','Allergies','Check-In Time','Check-In By','Check-Out Time','Check-Out By','Lanyard Back','Early Checkout Reason']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'],$r['day'],$r['child_name'],$r['class_name'],$r['grade'],
                $r['parent'],$r['phone'],$r['allergies'],$r['checkin_time'],$r['checkin_by'],
                $r['checkout_time'],$r['checkout_by'],$r['lanyard_back'] ? 'Yes' : 'No',
                $r['early_checkout_reason'] ?? '']);
        }
        fclose($out);
        exit;
    }

    // ── FETCH DATA ──
    $db = getDB();
    $filterDay = intval($_GET['day'] ?? 0);
    $stmt = $filterDay > 0
        ? $db->prepare('SELECT * FROM checkins WHERE day = ? ORDER BY checkin_time ASC')
        : $db->prepare('SELECT * FROM checkins ORDER BY day ASC, checkin_time ASC');
    $filterDay > 0 ? $stmt->execute([$filterDay]) : $stmt->execute();
    $rows = $stmt->fetchAll();

    for ($d = 1; $d <= 3; $d++) {
        $s = $db->prepare('SELECT COUNT(*) as total, SUM(checkout_time IS NOT NULL) as checked_out FROM checkins WHERE day = ?');
        $s->execute([$d]);
        $stats[$d] = $s->fetch();
    }
    $pins = $db->query('SELECT role, pin FROM passwords')->fetchAll();
    $roles = array_column($pins, 'role');
    if (!in_array('dashboard', $roles)) {
        $db->prepare("INSERT IGNORE INTO passwords (role, pin) VALUES ('dashboard', '1234')")->execute();
        $pins = $db->query('SELECT role, pin FROM passwords')->fetchAll();
    }
    $db->exec("CREATE TABLE IF NOT EXISTS settings (`key` VARCHAR(100) PRIMARY KEY, `value` VARCHAR(255) NOT NULL)");
    $sRows = $db->query("SELECT `key`, `value` FROM settings")->fetchAll();
    $daySettings = ['day1_enabled'=>'0','day2_enabled'=>'0','day3_enabled'=>'0'];
    foreach ($sRows as $sr) $daySettings[$sr['key']] = $sr['value'];
    $totalAll = $db->query('SELECT COUNT(*) FROM checkins')->fetchColumn();

    // Fetch volunteer attendance
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
        $volFilterDay = intval($_GET['vday'] ?? 0);
        $vstmt = $volFilterDay > 0
            ? $db->prepare('SELECT * FROM vol_attendance WHERE day = ? ORDER BY checkin_time ASC')
            : $db->prepare('SELECT * FROM vol_attendance ORDER BY day ASC, checkin_time ASC');
        $volFilterDay > 0 ? $vstmt->execute([$volFilterDay]) : $vstmt->execute();
        $volRows = $vstmt->fetchAll();
        $volStats = [];
        for ($d = 1; $d <= 3; $d++) {
            $s = $db->prepare('SELECT COUNT(*) as total, SUM(checkout_time != "" AND checkout_time IS NOT NULL) as checked_out FROM vol_attendance WHERE day = ?');
            $s->execute([$d]);
            $volStats[$d] = $s->fetch();
        }
        $activeDay = 0;
        for ($d = 3; $d >= 1; $d--) {
            if (($daySettings['day'.$d.'_enabled'] ?? '0') === '1') { $activeDay = $d; break; }
        }
        $liveTotal   = 0;
        $liveOut     = 0;
        $livePresent = 0;
        if ($activeDay) {
            $liveTotal   = intval($volStats[$activeDay]['total'] ?? 0);
            $liveOut     = intval($volStats[$activeDay]['checked_out'] ?? 0);
            $livePresent = $liveTotal - $liveOut;
        }
        $presentNames = [];
        if ($activeDay) {
            $pstmt = $db->prepare('SELECT vol_name FROM vol_attendance WHERE day = ? AND (checkout_time IS NULL OR checkout_time = "") ORDER BY checkin_time ASC');
            $pstmt->execute([$activeDay]);
            $presentNames = array_column($pstmt->fetchAll(), 'vol_name');
        }
        // T-shirt distribution data
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
            $tshirtRows     = $db->query('SELECT * FROM tshirt_distribution ORDER BY received ASC, name ASC')->fetchAll();
            $tshirtReceived = intval($db->query('SELECT COUNT(*) FROM tshirt_distribution WHERE received = 1')->fetchColumn());
            $tshirtTotal    = intval($db->query('SELECT COUNT(*) FROM tshirt_distribution')->fetchColumn());
            $tshirtWalkins  = intval($db->query('SELECT COUNT(*) FROM tshirt_distribution WHERE is_walkin = 1 AND received = 1')->fetchColumn());
        } catch(Exception $e) { $tshirtRows = []; $tshirtReceived = 0; $tshirtTotal = 0; $tshirtWalkins = 0; }
    } catch(Exception $e) {
        $volRows  = [];
        $volStats = [1=>['total'=>0,'checked_out'=>0],2=>['total'=>0,'checked_out'=>0],3=>['total'=>0,'checked_out'=>0]];
        $liveTotal = $liveOut = $livePresent = 0;
        $presentNames = [];
        $activeDay = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>VBS 2026 Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy:#1A2744; --orange:#F97316; --orange-dk:#EA580C;
    --green:#16A34A; --grass:#DCFCE7; --red:#EF4444; --red-lt:#FEE2E2;
    --amber:#D97706; --sun:#FEF3C7; --blue:#1D4ED8; --sky:#DBEAFE;
    --purple:#7C3AED; --lavender:#EDE9FE;
    --border:#E7E5E4; --muted:#78716C; --page:#F0EFEE;
    --sw:210px;
  }
  body { font-family:'Inter',sans-serif; background:var(--page); color:#1C1917; min-height:100vh; }

  /* ── SHELL ── */
  .shell { display:flex; height:100vh; overflow:hidden; }

  /* ── SIDEBAR ── */
  .sidebar {
    width:var(--sw); flex-shrink:0; background:var(--navy);
    display:flex; flex-direction:column; height:100vh;
    overflow-y:auto; scrollbar-width:none;
  }
  .sidebar::-webkit-scrollbar { display:none; }
  .sb-brand { padding:16px 14px 12px; border-bottom:1px solid rgba(255,255,255,.08); }
  .sb-logo  { font-family:'Fredoka One',cursive; font-size:1.05rem; color:#FCD34D; line-height:1.2; }
  .sb-sub   { font-size:.62rem; color:rgba(255,255,255,.35); margin-top:2px; }
  .sb-nav   { flex:1; padding:8px 6px; display:flex; flex-direction:column; gap:1px; }
  .sb-group { font-size:.57rem; font-weight:800; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,.28); padding:10px 10px 3px; }
  .sb-item  {
    display:flex; align-items:center; gap:8px; padding:7px 10px;
    border-radius:7px; cursor:pointer; font-size:.76rem; font-weight:600;
    color:rgba(255,255,255,.55); border:none; background:transparent;
    width:100%; text-align:left; font-family:inherit; transition:all .15s;
  }
  .sb-item:hover { background:rgba(255,255,255,.07); color:white; }
  .sb-item.on { background:rgba(249,115,22,.18); color:#FCD34D; font-weight:700; }
  .sb-item .ic { font-size:.9rem; flex-shrink:0; width:18px; text-align:center; }
  .sb-item .lbl { flex:1; }
  .sb-item .cnt { background:rgba(255,255,255,.12); border-radius:99px; font-size:.58rem; font-weight:800; padding:1px 6px; color:rgba(255,255,255,.6); }
  .sb-item.on .cnt { background:rgba(249,115,22,.3); color:#FCD34D; }
  .sb-foot { padding:10px 14px; border-top:1px solid rgba(255,255,255,.07); }
  .sb-foot a { color:rgba(255,255,255,.35); font-size:.7rem; text-decoration:none; display:flex; align-items:center; gap:5px; transition:color .15s; }
  .sb-foot a:hover { color:white; }

  /* ── MAIN ── */
  .main { flex:1; min-width:0; display:flex; flex-direction:column; overflow:hidden; }
  .topbar { background:white; border-bottom:1px solid var(--border); padding:11px 20px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
  .topbar-title { font-family:'Fredoka One',cursive; font-size:1.05rem; color:var(--navy); }
  .topbar a { color:var(--muted); font-size:.72rem; font-weight:600; text-decoration:none; padding:5px 11px; border:1.5px solid var(--border); border-radius:7px; transition:all .15s; }
  .topbar a:hover { border-color:var(--red); color:var(--red); }

  /* ── CONTENT AREA ── */
  .content { flex:1; overflow-y:auto; }
  .tab-panel { display:none; padding:18px 20px; }
  .tab-panel.on { display:block; }
  .panel-title { font-family:'Fredoka One',cursive; font-size:1.15rem; color:var(--navy); margin-bottom:14px; display:flex; align-items:center; gap:8px; }

  /* ── Login ── */
  .login-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,var(--navy) 0%,#2A3F7A 100%); }
  .login-card { background:white; border-radius:18px; padding:38px 32px; width:100%; max-width:360px; box-shadow:0 20px 60px rgba(0,0,0,.25); text-align:center; }
  .login-icon  { font-size:2.4rem; margin-bottom:10px; }
  .login-title { font-family:'Fredoka One',cursive; font-size:1.5rem; color:var(--navy); margin-bottom:4px; }
  .login-sub   { color:var(--muted); font-size:.82rem; margin-bottom:22px; }
  .login-input { width:100%; border:2px solid var(--border); border-radius:10px; padding:11px 14px; font-size:.95rem; outline:none; margin-bottom:12px; font-family:inherit; transition:border-color .2s; }
  .login-input:focus { border-color:var(--orange); }
  .login-btn   { width:100%; background:var(--orange); color:white; border:none; border-radius:10px; padding:12px; font-size:.95rem; font-weight:700; cursor:pointer; font-family:inherit; }
  .login-btn:hover { background:var(--orange-dk); }
  .login-err   { color:var(--red); font-size:.82rem; margin-top:8px; }

  /* ── Stats ── */
  .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:14px; }
  .stat-card  { background:white; border-radius:10px; padding:14px 16px; box-shadow:0 1px 4px rgba(0,0,0,.06); border:1px solid rgba(0,0,0,.04); }
  .stat-day   { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin-bottom:6px; }
  .stat-nums  { display:flex; gap:5px; flex-wrap:wrap; }
  .stat-pill  { font-size:.72rem; font-weight:700; padding:3px 8px; border-radius:99px; }
  .pill-green { background:var(--grass); color:#166534; }
  .pill-amber { background:var(--sun); color:#92400E; }
  .pill-blue  { background:var(--sky); color:#1E40AF; }

  /* ── Section card ── */
  .section { background:white; border-radius:10px; padding:16px 18px; box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:12px; border:1px solid rgba(0,0,0,.04); }
  .section-title { font-family:'Fredoka One',cursive; font-size:.95rem; margin-bottom:12px; display:flex; align-items:center; gap:8px; }

  /* ── Messages ── */
  .msg { padding:10px 14px; border-radius:8px; font-size:.82rem; font-weight:600; margin-bottom:14px; }
  .msg-ok  { background:var(--grass); color:#166534; }
  .msg-err { background:var(--red-lt); color:#991B1B; }

  /* ── Form ── */
  .pin-grid  { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .pin-card  { border:1.5px solid var(--border); border-radius:10px; padding:14px; }
  .pin-label { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin-bottom:6px; }
  .pin-row   { display:flex; gap:8px; }
  .field { flex:1; border:1.5px solid var(--border); border-radius:7px; padding:7px 10px; font-size:.84rem; outline:none; font-family:inherit; background:white; transition:border-color .15s; }
  .field:focus { border-color:var(--orange); }
  select.field { background:white; }
  .form-grid  { display:grid; grid-template-columns:1fr 1fr; gap:7px; margin-bottom:8px; }
  .form-group { display:flex; flex-direction:column; gap:3px; }
  .form-label { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }

  /* ── Buttons ── */
  .btn { border:none; border-radius:7px; padding:7px 14px; font-size:.78rem; font-weight:700; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; gap:5px; transition:opacity .15s; }
  .btn:hover { opacity:.85; }
  .btn-primary { background:var(--navy); color:white; }
  .btn-orange  { background:var(--orange); color:white; }
  .btn-orange:hover { background:var(--orange-dk); opacity:1; }
  .btn-dark    { background:#292524; color:white; }
  .btn-green   { background:var(--green); color:white; }
  .btn-red     { background:var(--red); color:white; font-size:.7rem; padding:4px 9px; }
  .btn-cancel  { background:#F5F5F4; color:var(--muted); border:none; border-radius:7px; padding:7px 14px; font-size:.78rem; font-weight:700; cursor:pointer; font-family:inherit; }

  /* ── Backup ── */
  .backup-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .backup-card { border:1.5px solid var(--border); border-radius:10px; padding:14px; }
  .backup-card-title { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin-bottom:5px; }
  .backup-card-desc  { font-size:.74rem; color:var(--muted); margin-bottom:10px; line-height:1.5; }
  .file-input { font-size:.76rem; width:100%; margin-bottom:8px; }

  /* ── Table ── */
  .controls   { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
  .filter-btn { border:1.5px solid var(--border); background:white; border-radius:7px; padding:5px 12px; font-size:.76rem; font-weight:600; cursor:pointer; text-decoration:none; color:#1C1917; transition:all .15s; }
  .filter-btn.active, .filter-btn:hover { border-color:var(--orange); color:var(--orange); }
  .table-wrap { background:white; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,.06); overflow:auto; margin-bottom:12px; border:1px solid rgba(0,0,0,.04); }
  table { width:100%; border-collapse:collapse; }
  th { background:var(--navy); color:white; padding:8px 10px; text-align:left; font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; }
  th:first-child { border-radius:10px 0 0 0; }
  th:last-child  { border-radius:0 10px 0 0; }
  td { padding:7px 10px; border-bottom:1px solid #F5F5F4; vertical-align:middle; font-size:.74rem; }
  tr:last-child td { border-bottom:none; }
  tr.checked-out td { background:#F0FDF4; }
  tr:hover td { background:#FAFAFA; }
  .badge     { font-size:.65rem; font-weight:700; padding:2px 7px; border-radius:99px; display:inline-block; white-space:nowrap; }
  .badge-in  { background:var(--grass); color:#166534; }
  .badge-out { background:var(--sun); color:#92400E; }
  .allergy   { background:var(--red-lt); color:#991B1B; font-size:.65rem; padding:2px 6px; border-radius:99px; }
  .empty     { padding:32px; text-align:center; color:var(--muted); }

  /* ── Modal ── */
  .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; }
  .modal-overlay.open { display:flex; }
  .modal { background:white; border-radius:16px; padding:24px 26px; width:100%; max-width:480px; margin:16px; box-shadow:0 20px 60px rgba(0,0,0,.2); }
  .modal-title { font-family:'Fredoka One',cursive; font-size:1.1rem; margin-bottom:14px; color:var(--navy); }
  .modal-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px; }
  .modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:14px; }

  /* ── Responsive ── */
  @media (max-width:768px) {
    :root { --sw:52px; }
    .sb-brand, .sb-group, .sb-item .lbl, .sb-item .cnt, .sb-sub { display:none; }
    .sb-item { justify-content:center; padding:9px; }
    .tab-panel { padding:12px 14px; }
  }
  @media (max-width:520px) {
    .stats-grid, .pin-grid, .backup-grid, .form-grid, .modal-form-grid { grid-template-columns:1fr; }
    table { font-size:.7rem; }
    th, td { padding:6px 7px; }
  }
</style>
</head>
<body>

<?php if (!isset($_SESSION['vbs_admin'])): ?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-icon">🔐</div>
    <div class="login-title">Admin Login</div>
    <div class="login-sub">VBS 2026 · India Christian Assembly NJ</div>
    <form method="POST">
      <input type="hidden" name="action" value="login">
      <input class="login-input" type="password" name="password" placeholder="Admin password" autofocus>
      <button class="login-btn" type="submit">Sign In →</button>
      <?php if (!empty($loginError)): ?>
        <div class="login-err">❌ <?= htmlspecialchars($loginError) ?></div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php else: ?>
<div class="shell">

<!-- ══ SIDEBAR ══ -->
<nav class="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">⛪ VBS 2026</div>
    <div class="sb-sub">Admin Panel · ICANJ</div>
  </div>
  <div class="sb-nav">
    <div class="sb-group">Overview</div>
    <button class="sb-item on" onclick="showTab('tab-dashboard')">
      <span class="ic">📊</span><span class="lbl">Dashboard</span>
    </button>
    <button class="sb-item" onclick="showTab('tab-records')">
      <span class="ic">📋</span><span class="lbl">Check-In Records</span>
    </button>

    <div class="sb-group">People</div>
    <button class="sb-item" onclick="showTab('tab-users')">
      <span class="ic">👤</span><span class="lbl">Users</span>
    </button>
    <button class="sb-item" onclick="showTab('tab-vol-sync')">
      <span class="ic">🔄</span><span class="lbl">Vol. Sync</span>
    </button>
    <button class="sb-item" onclick="showTab('tab-vol-att')">
      <span class="ic">🙋</span><span class="lbl">Vol. Attendance</span>
    </button>
    <button class="sb-item" onclick="showTab('tab-merch')">
      <span class="ic">🎽</span><span class="lbl">Merch Station</span>
    </button>

    <div class="sb-group">Settings</div>
    <button class="sb-item" onclick="showTab('tab-days')">
      <span class="ic">📅</span><span class="lbl">Day Settings</span>
    </button>
    <button class="sb-item" onclick="showTab('tab-manual')">
      <span class="ic">✏️</span><span class="lbl">Manual Entry</span>
    </button>
    <button class="sb-item" onclick="showTab('tab-backup')">
      <span class="ic">💾</span><span class="lbl">Backup</span>
    </button>

    <div class="sb-group">Danger</div>
    <button class="sb-item" onclick="showTab('tab-reset')" style="color:#F87171">
      <span class="ic">🔴</span><span class="lbl">Reset DB</span>
    </button>

    <div class="sb-group">Logs</div>
    <button class="sb-item" onclick="showTab('tab-logins')">
      <span class="ic">🔐</span><span class="lbl">Login History</span>
    </button>
    <button class="sb-item" onclick="showTab('tab-log')">
      <span class="ic">📋</span><span class="lbl">Activity Log</span>
    </button>
  </div>
  <div class="sb-foot">
    <a href="?logout=1">↩ Sign Out</a>
  </div>
</nav>
<!-- ══ END SIDEBAR ══ -->

<!-- ══ MAIN ══ -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title" id="topbar-heading">📊 Dashboard</div>
    <span style="font-size:.72rem;color:#78716C;font-weight:600">VBS 2026 · India Christian Assembly NJ</span>
  </div>

  <div class="content">
    <?php if ($msg): ?>
    <div style="padding:12px 20px 0"><div class="msg msg-<?= $msgType ?>"><?= $msg ?></div></div>
    <?php endif; ?>

<!-- ══ DASHBOARD ══ -->
<div class="tab-panel on" id="tab-dashboard">
  <div class="stats-grid">
    <?php
    $days = ['', 'Thu Jul 9', 'Fri Jul 10', 'Sat Jul 11'];
    for ($d = 1; $d <= 3; $d++):
      $total  = $stats[$d]['total'] ?? 0;
      $out    = $stats[$d]['checked_out'] ?? 0;
      $inside = $total - $out;
    ?>
    <div class="stat-card">
      <div class="stat-day">Day <?= $d ?> · <?= $days[$d] ?></div>
      <div class="stat-nums">
        <span class="stat-pill pill-green">✅ <?= $total ?> arrived</span>
        <span class="stat-pill pill-amber">🏁 <?= $out ?> out</span>
        <span class="stat-pill pill-blue">🏠 <?= $inside ?> inside</span>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- ══ USERS ══ -->
<div class="tab-panel" id="tab-users">
  <div class="section">
    <div class="section-title">👤 User Management</div>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:18px">Create accounts for Check-in and Check-out volunteers. Users log in with username &amp; password.</p>

    <div style="background:#F8FAFC;border:1.5px solid var(--border);border-radius:12px;padding:20px;margin-bottom:24px">
      <div style="font-weight:800;font-size:.9rem;color:var(--navy);margin-bottom:14px">➕ Create New User</div>
      <form id="create-user-form">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:14px">
          <div>
            <label style="font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.3px">Username</label>
            <input type="text" name="username" required placeholder="e.g. john.smith"
              style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;margin-top:4px">
          </div>
          <div>
            <label style="font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.3px">Display Name</label>
            <input type="text" name="display_name" placeholder="e.g. John Smith"
              style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;margin-top:4px">
          </div>
          <div>
            <label style="font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.3px">Password</label>
            <input type="password" name="password" required placeholder="Set a password"
              style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;margin-top:4px">
          </div>
        </div>
        <p style="font-size:.75rem;color:var(--muted);margin-bottom:12px">💡 After creating the user, use the <strong>Assign Panels</strong> and <strong>Assign Groups</strong> buttons in the table below to configure their access.</p>
        <button type="submit" class="btn btn-primary" style="padding:9px 24px">➕ Create User</button>
      </form>
    </div>

    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.84rem">
        <thead>
          <tr style="background:var(--navy);color:white">
            <th style="padding:10px 14px;text-align:left;border-radius:10px 0 0 0">Username</th>
            <th style="padding:10px 14px;text-align:left">Display Name</th>
            <th style="padding:10px 14px;text-align:left">Password</th>
            <th style="padding:10px 14px;text-align:left">Panels</th>
            <th style="padding:10px 14px;text-align:left">Status</th>
            <th style="padding:10px 14px;text-align:left">Groups</th>
            <th style="padding:10px 14px;text-align:left">Last Login</th>
            <th style="padding:10px 14px;text-align:left;border-radius:0 10px 0 0">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        try {
            $db = getDB();
            $db->exec("CREATE TABLE IF NOT EXISTS vbs_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(100) NOT NULL DEFAULT '',
                role ENUM('checkin','checkout','both') NOT NULL DEFAULT 'checkin',
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME NULL
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS vbs_user_groups (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, group_name VARCHAR(50) NOT NULL, UNIQUE KEY unique_user_group (user_id, group_name))");
            $db->exec("CREATE TABLE IF NOT EXISTS vbs_user_panels (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, panel_name VARCHAR(50) NOT NULL, UNIQUE KEY unique_user_panel (user_id, panel_name))");
            try { $db->exec("ALTER TABLE vbs_users ADD COLUMN plain_password VARCHAR(50) NOT NULL DEFAULT ''"); } catch(Exception $e) {}

            $users = $db->query('SELECT * FROM vbs_users ORDER BY created_at DESC')->fetchAll();
            if (empty($users)): ?>
            <tr><td colspan="8" style="padding:20px;text-align:center;color:var(--muted)">No users yet — create one above.</td></tr>
            <?php else:
            foreach ($users as $u):
                $statusBadge = $u['active']
                    ? '<span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:800">Active</span>'
                    : '<span style="background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:800">Disabled</span>';
                $lastLogin = $u['last_login'] ? date('M j, Y g:i A', strtotime($u['last_login'])) : 'Never';

                $pnStmt = $db->prepare('SELECT panel_name FROM vbs_user_panels WHERE user_id = ?');
                $pnStmt->execute([$u['id']]);
                $userPanels = array_column($pnStmt->fetchAll(), 'panel_name');
                $panelLabels = [
                    'checkin'      =>['✅','Check-in','#D1FAE5','#065F46'],
                    'checkout'     =>['🏁','Check-out','#FEF3C7','#92400E'],
                    'kids'         =>['🧒','Kids','#DBEAFE','#1E40AF'],
                    'volunteers'   =>['🙋','Volunteers','#D1FAE5','#065F46'],
                    'attendance'   =>['📊','Attendance','#FEF3C7','#92400E'],
                    'schedule'     =>['📅','Schedule','#EDE9FE','#5B21B6'],
                    'vol_attendance'=>['🙋','Vol Kiosk','#D1FAE5','#065F46']
                ];
                $panelBadges = empty($userPanels)
                    ? '<span style="color:#A8A29E;font-size:.7rem;font-style:italic">None</span>'
                    : implode('', array_map(function($p) use ($panelLabels) {
                        $l = $panelLabels[$p] ?? ['📄',$p,'#F1F5F9','#475569'];
                        return '<span style="background:'.$l[2].';color:'.$l[3].';padding:1px 8px;border-radius:99px;font-size:.65rem;font-weight:800;display:inline-block;margin:1px 2px 2px 0">'.$l[0].' '.$l[1].'</span>';
                      }, $userPanels));
                $panelsJson = htmlspecialchars(json_encode($userPanels), ENT_QUOTES);

                $gStmt = $db->prepare('SELECT group_name FROM vbs_user_groups WHERE user_id = ?');
                $gStmt->execute([$u['id']]);
                $userGroups = array_column($gStmt->fetchAll(), 'group_name');
                $groupColors = [
                    'Pre-K'       =>['#FEE2E2','#991B1B'],
                    'Pre-Primary' =>['#DBEAFE','#1E40AF'],
                    'Primary'     =>['#D1FAE5','#065F46'],
                    'Junior'      =>['#EDE9FE','#5B21B6']
                ];
                $groupBadges = empty($userGroups)
                    ? '<span style="color:#A8A29E;font-size:.7rem;font-style:italic">None</span>'
                    : implode('', array_map(function($g) use ($groupColors) {
                        $bg = $groupColors[$g][0] ?? '#F1F5F9';
                        $cl = $groupColors[$g][1] ?? '#475569';
                        return '<span style="background:'.$bg.';color:'.$cl.';padding:1px 8px;border-radius:99px;font-size:.65rem;font-weight:800;display:inline-block;margin:1px 2px 2px 0">'.$g.'</span>';
                      }, $userGroups));
                $groupsJson = htmlspecialchars(json_encode($userGroups), ENT_QUOTES);
            ?>
            <tr style="border-bottom:1px solid var(--border)" id="user-row-<?= $u['id'] ?>">
              <td style="padding:10px 14px;font-weight:800;color:var(--navy)"><?= htmlspecialchars($u['username']) ?></td>
              <td style="padding:10px 14px"><?= htmlspecialchars($u['display_name']) ?></td>
              <td style="padding:10px 14px">
                <?php $pw = $u['plain_password'] ?? ''; ?>
                <?php if ($pw): ?>
                  <span style="font-family:monospace;font-size:.8rem;background:#F1F5F9;border:1px solid var(--border);border-radius:6px;padding:3px 8px;color:#1C1917;user-select:all;cursor:text"><?= htmlspecialchars($pw) ?></span>
                <?php else: ?>
                  <span style="color:#A8A29E;font-size:.7rem;font-style:italic">—</span>
                <?php endif; ?>
              </td>
              <td style="padding:8px 14px;min-width:130px;max-width:170px">
                <div style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:5px"><?= $panelBadges ?></div>
                <button onclick="assignPanels(<?= $u['id'] ?>, '<?= htmlspecialchars($u['display_name'], ENT_QUOTES) ?>', <?= $panelsJson ?>)"
                  style="background:#EEF2FF;color:#4F46E5;border:1px solid #A5B4FC;border-radius:6px;padding:3px 9px;font-size:.68rem;font-weight:800;cursor:pointer;white-space:nowrap">🖥️ Assign Panels</button>
              </td>
              <td style="padding:10px 14px"><?= $statusBadge ?></td>
              <td style="padding:8px 14px;min-width:140px;max-width:180px">
                <div style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:5px"><?= $groupBadges ?></div>
                <button onclick="assignGroups(<?= $u['id'] ?>, '<?= htmlspecialchars($u['display_name'], ENT_QUOTES) ?>', <?= $groupsJson ?>)"
                  style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;border-radius:6px;padding:3px 9px;font-size:.68rem;font-weight:800;cursor:pointer;white-space:nowrap">🏷️ Assign Groups</button>
              </td>
              <td style="padding:10px 14px;color:var(--muted);font-size:.78rem"><?= $lastLogin ?></td>
              <td style="padding:10px 14px">
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <button onclick="editUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['display_name'], ENT_QUOTES) ?>', '<?= $u['role'] ?>', <?= $u['active'] ?>)"
                    style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;border-radius:6px;padding:4px 10px;font-size:.72rem;font-weight:800;cursor:pointer">✏️ Edit</button>
                  <button onclick="resetPassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                    style="background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;border-radius:6px;padding:4px 10px;font-size:.72rem;font-weight:800;cursor:pointer">🔑 Reset PW</button>
                  <button onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                    style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;border-radius:6px;padding:4px 10px;font-size:.72rem;font-weight:800;cursor:pointer">🗑️ Delete</button>
                </div>
              </td>
            </tr>
            <?php endforeach;
            endif;
        } catch (Exception $e) {
            echo '<tr><td colspan="8" style="padding:20px;color:#EF4444">Error loading users: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div id="edit-user-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
    <div style="background:white;border-radius:16px;padding:28px;width:100%;max-width:420px;margin:20px">
      <div style="font-family:'Fredoka One',cursive;font-size:1.3rem;color:var(--navy);margin-bottom:18px">✏️ Edit User</div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <label style="font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase">Username</label>
          <div id="edit-username-display" style="padding:8px 12px;background:#F8FAFC;border-radius:8px;font-weight:700;font-size:.9rem;color:var(--navy);margin-top:4px"></div>
        </div>
        <div>
          <label style="font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase">Display Name</label>
          <input type="text" id="edit-display-name" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;margin-top:4px">
        </div>
        <div>
          <label style="font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase">Access</label>
          <select id="edit-role" style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;margin-top:4px">
            <option value="checkin">✅ Check-in only</option>
            <option value="checkout">🏁 Check-out only</option>
            <option value="both">✅🏁 Both</option>
          </select>
        </div>
        <div style="display:flex;align-items:center;gap:10px;margin-top:4px">
          <input type="checkbox" id="edit-active" style="width:18px;height:18px">
          <label for="edit-active" style="font-weight:700;font-size:.85rem">Account Active</label>
        </div>
      </div>
      <input type="hidden" id="edit-user-id">
      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="button" onclick="saveEditUser()" class="btn btn-primary" style="flex:1;padding:10px">Save Changes</button>
        <button type="button" onclick="closeEditModal()" style="flex:1;padding:10px;background:#F1F5F9;color:var(--navy);border:none;border-radius:8px;font-weight:800;cursor:pointer">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Reset Password Modal -->
  <div id="reset-pw-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
    <div style="background:white;border-radius:16px;padding:28px;width:100%;max-width:380px;margin:20px">
      <div style="font-family:'Fredoka One',cursive;font-size:1.3rem;color:var(--navy);margin-bottom:6px">🔑 Reset Password</div>
      <div id="reset-pw-username" style="color:var(--muted);font-size:.85rem;margin-bottom:18px"></div>
      <input type="hidden" id="reset-pw-id">
      <div>
        <label style="font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase">New Password</label>
        <input type="password" id="reset-pw-input" placeholder="Enter new password"
          style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.9rem;margin-top:6px">
      </div>
      <div style="display:flex;gap:10px;margin-top:20px">
        <button onclick="saveResetPassword()" class="btn btn-primary" style="flex:1;padding:10px">Reset Password</button>
        <button onclick="document.getElementById('reset-pw-modal').style.display='none'" style="flex:1;padding:10px;background:#F1F5F9;color:var(--navy);border:none;border-radius:8px;font-weight:800;cursor:pointer">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Assign Groups Modal -->
  <div id="assign-groups-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
    <div style="background:white;border-radius:16px;padding:28px;width:100%;max-width:380px;margin:20px">
      <div id="assign-groups-title" style="font-family:'Fredoka One',cursive;font-size:1.2rem;color:var(--navy);margin-bottom:6px"></div>
      <p style="font-size:.78rem;color:var(--muted);margin-bottom:18px">Select which class groups this user can see on the checkout page and Live Attendance.</p>
      <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
        <?php foreach(['Pre-K'=>'🎈','Pre-Primary'=>'🎠','Primary'=>'📘','Junior'=>'🎓'] as $grp => $emoji): ?>
        <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:2px solid var(--border);border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem">
          <input type="checkbox" id="grp-<?= strtolower(preg_replace('/[^a-z]/i','', $grp)) ?>"
            style="width:18px;height:18px;accent-color:#15803D;cursor:pointer">
          <?= $emoji ?> <?= $grp ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:10px">
        <button onclick="saveAssignGroups()" class="btn btn-primary" style="flex:1;padding:10px">Save Groups</button>
        <button onclick="document.getElementById('assign-groups-modal').style.display='none'" style="flex:1;padding:10px;background:#F1F5F9;color:var(--navy);border:none;border-radius:8px;font-weight:800;cursor:pointer">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Assign Panels Modal -->
  <div id="assign-panels-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
    <div style="background:white;border-radius:16px;padding:28px;width:100%;max-width:400px;margin:20px">
      <div id="assign-panels-title" style="font-family:'Fredoka One',cursive;font-size:1.2rem;color:var(--navy);margin-bottom:6px"></div>
      <p style="font-size:.78rem;color:var(--muted);margin-bottom:18px">Select which Command Center tabs this user can see.</p>
      <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
        <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:2px solid var(--border);border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem">
          <input type="checkbox" id="panel-checkin" style="width:18px;height:18px;accent-color:#059669;cursor:pointer"> ✅ Check-in
        </label>
        <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:2px solid var(--border);border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem">
          <input type="checkbox" id="panel-checkout" style="width:18px;height:18px;accent-color:#D97706;cursor:pointer"> 🏁 Check-out
        </label>
        <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:2px solid var(--border);border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem">
          <input type="checkbox" id="panel-kids" style="width:18px;height:18px;accent-color:#1D4ED8;cursor:pointer"> 🧒 Kids Registration
        </label>
        <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:2px solid var(--border);border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem">
          <input type="checkbox" id="panel-volunteers" style="width:18px;height:18px;accent-color:#059669;cursor:pointer"> 🙋 Volunteers
        </label>
        <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:2px solid var(--border);border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem">
          <input type="checkbox" id="panel-attendance" style="width:18px;height:18px;accent-color:#D97706;cursor:pointer"> 📊 Live Attendance
        </label>
        <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:2px solid var(--border);border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem">
          <input type="checkbox" id="panel-schedule" style="width:18px;height:18px;accent-color:#7C3AED;cursor:pointer"> 📅 Schedule
        </label>
        <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:2px solid var(--border);border-radius:10px;cursor:pointer;font-weight:700;font-size:.88rem">
          <input type="checkbox" id="panel-vol_attendance" style="width:18px;height:18px;accent-color:#15803D;cursor:pointer"> 🙋 Vol Kiosk
        </label>
      </div>
      <div style="display:flex;gap:10px">
        <button onclick="saveAssignPanels()" class="btn btn-primary" style="flex:1;padding:10px">Save Panels</button>
        <button onclick="document.getElementById('assign-panels-modal').style.display='none'" style="flex:1;padding:10px;background:#F1F5F9;color:var(--navy);border:none;border-radius:8px;font-weight:800;cursor:pointer">Cancel</button>
      </div>
    </div>
  </div>
</div><!-- /tab-users -->

<!-- ══ VOL SYNC ══ -->
<div class="tab-panel" id="tab-vol-sync">
  <div class="section" style="border:1.5px solid #EDE9FE">
    <div class="section-title" style="color:#7C3AED">🔄 Auto-Create Volunteer Accounts</div>
    <p style="font-size:.8rem;color:#78716C;margin-bottom:14px;line-height:1.6">
      Reads the volunteer registration sheet and creates a <strong>username + random password</strong> for each volunteer who doesn't have an account yet.<br>
      Username format: <code style="background:#F1F5F9;padding:1px 5px;border-radius:4px;font-size:.78rem">first initial + last name</code> e.g. Anit Mathew → <code style="background:#F1F5F9;padding:1px 5px;border-radius:4px;font-size:.78rem">amathew</code>.<br>
      Each new account gets <strong>Schedule</strong> and <strong>Vol Kiosk</strong> panels automatically.
    </p>
    <div id="sync-result" style="display:none;margin-bottom:14px"></div>
    <button class="btn" onclick="runVolSync()" id="sync-btn" style="background:#7C3AED;color:white">
      🔄 Sync Volunteers Now
    </button>
  </div>
</div>

<!-- ══ DAY SETTINGS ══ -->
<div class="tab-panel" id="tab-days">
  <div class="section">
    <div class="section-title">📅 Day Settings <span style="font-size:.75rem;font-weight:500;color:#78716C;font-family:Inter,sans-serif">— enable days on checkin &amp; checkout pages</span></div>
    <p style="font-size:.82rem;color:#78716C;margin-bottom:16px;line-height:1.6">
      Only enabled days will appear as selectable options on the check-in and check-out pages. Enable each day when it starts.
    </p>
    <form method="POST">
      <input type="hidden" name="action" value="saveSettings">
      <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px">
        <?php
        $dayLabels = [1 => 'Day 1 — Thu Jul 9', 2 => 'Day 2 — Fri Jul 10', 3 => 'Day 3 — Sat Jul 11'];
        for ($d = 1; $d <= 3; $d++):
          $key     = 'day'.$d.'_enabled';
          $enabled = ($daySettings[$key] ?? '0') === '1';
        ?>
        <label style="display:flex;align-items:center;gap:10px;background:<?= $enabled ? '#DCFCE7' : '#F5F5F4' ?>;border:2px solid <?= $enabled ? '#22C55E' : '#E7E5E4' ?>;border-radius:12px;padding:12px 18px;cursor:pointer;font-weight:600;font-size:.88rem">
          <input type="hidden" name="<?= $key ?>" value="0">
          <input type="checkbox" name="<?= $key ?>" value="1" <?= $enabled ? 'checked' : '' ?>
            style="width:18px;height:18px;accent-color:#22C55E;cursor:pointer">
          <?= $dayLabels[$d] ?>
          <span style="font-size:.72rem;font-weight:700;padding:3px 8px;border-radius:100px;background:<?= $enabled ? '#22C55E' : '#D4D4D4' ?>;color:white;margin-left:4px">
            <?= $enabled ? 'ON' : 'OFF' ?>
          </span>
        </label>
        <?php endfor; ?>
      </div>
      <button class="btn btn-orange" type="submit">💾 Save Day Settings</button>
    </form>
  </div>
</div>

<!-- ══ VOLUNTEER ATTENDANCE ══ -->
<div class="tab-panel" id="tab-vol-att">
  <div class="section">
    <div class="section-title">🙋 Volunteer Attendance
      <span style="font-size:.75rem;font-weight:500;color:#78716C;font-family:Inter,sans-serif">— assign the <strong>Vol Kiosk</strong> panel to users in User Management</span>
    </div>

    <?php $activeDayLabels = [1=>'Day 1 · Thu Jul 9', 2=>'Day 2 · Fri Jul 10', 3=>'Day 3 · Sat Jul 11']; ?>
    <div style="background:<?= $livePresent > 0 ? 'linear-gradient(135deg,#15803D,#065F46)' : '#F1F5F9' ?>;border-radius:16px;padding:20px 24px;margin-bottom:20px;color:<?= $livePresent > 0 ? 'white' : '#64748B' ?>">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;opacity:.8;margin-bottom:4px">
            🍽️ Food Order Count
            <?php if ($activeDay): ?>· <?= $activeDayLabels[$activeDay] ?><?php else: ?>· No active day<?php endif; ?>
          </div>
          <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
            <div style="font-family:'Fredoka One',cursive;font-size:3.5rem;line-height:1;color:<?= $livePresent > 0 ? '#FCD34D' : '#94A3B8' ?>">
              <?= $livePresent ?>
            </div>
            <div>
              <div style="font-weight:800;font-size:1.1rem">volunteers in building</div>
              <div style="font-size:.78rem;opacity:.75;margin-top:2px"><?= $liveTotal ?> arrived · <?= $liveOut ?> checked out</div>
            </div>
          </div>
        </div>
        <?php if ($livePresent > 0 && !empty($presentNames)): ?>
        <div style="background:rgba(255,255,255,.12);border-radius:12px;padding:12px 16px;max-width:320px">
          <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;opacity:.8;margin-bottom:8px">Still inside</div>
          <div style="display:flex;flex-wrap:wrap;gap:5px">
            <?php foreach ($presentNames as $pn): ?>
            <span style="background:rgba(255,255,255,.2);border-radius:20px;padding:3px 10px;font-size:.75rem;font-weight:700;white-space:nowrap"><?= htmlspecialchars($pn) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php elseif (!$activeDay): ?>
        <div style="font-size:.82rem;opacity:.6">Enable a day in Day Settings to start tracking</div>
        <?php else: ?>
        <div style="font-size:.82rem;opacity:.6">No volunteers currently checked in</div>
        <?php endif; ?>
      </div>
      <div style="margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.15);font-size:.72rem;opacity:.65">🔄 Refresh the page to update this count</div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px">
      <?php
      $volDayLabels = ['','Thu Jul 9','Fri Jul 10','Sat Jul 11'];
      for ($d = 1; $d <= 3; $d++):
        $vt  = $volStats[$d]['total'] ?? 0;
        $vo  = $volStats[$d]['checked_out'] ?? 0;
        $vpr = $vt - $vo;
      ?>
      <div class="stat-card">
        <div class="stat-day">Day <?= $d ?> · <?= $volDayLabels[$d] ?><?= $d === $activeDay ? ' 🟢' : '' ?></div>
        <div class="stat-nums">
          <span class="stat-pill pill-green">✅ <?= $vt ?> arrived</span>
          <span class="stat-pill pill-amber">🏁 <?= $vo ?> out</span>
          <span class="stat-pill pill-blue">🏠 <?= $vpr ?> present</span>
        </div>
      </div>
      <?php endfor; ?>
    </div>

    <div class="controls" style="margin-bottom:14px">
      <a class="filter-btn <?= !$volFilterDay ? 'active' : '' ?>" href="admin.php#vol-attendance">All Days (<?= count($volRows) ?>)</a>
      <?php for ($d = 1; $d <= 3; $d++): ?>
      <a class="filter-btn <?= $volFilterDay===$d ? 'active' : '' ?>" href="?vday=<?= $d ?>#vol-attendance">Day <?= $d ?></a>
      <?php endfor; ?>
    </div>

    <div id="vol-attendance" class="table-wrap">
      <?php if (empty($volRows)): ?>
        <div class="empty">No volunteer check-ins recorded yet.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th><th>Day</th><th>Volunteer Name</th>
            <th>Check In</th><th>Check In By</th>
            <th>Check Out</th><th>Check Out By</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($volRows as $vr):
            $dayDates = [1=>'Jul 9', 2=>'Jul 10', 3=>'Jul 11'];
            $vDate = $dayDates[intval($vr['day'])] ?? '';
          ?>
          <tr class="<?= $vr['checkout_time'] ? 'checked-out' : '' ?>">
            <td><?= $vr['id'] ?></td>
            <td>Day <?= $vr['day'] ?></td>
            <td><strong><?= htmlspecialchars($vr['vol_name']) ?></strong></td>
            <td>
              <span style="font-weight:800"><?= htmlspecialchars($vr['checkin_time']) ?></span>
              <?php if ($vDate): ?><br><span style="font-size:.68rem;color:#A8A29E;font-weight:600"><?= $vDate ?></span><?php endif; ?>
            </td>
            <td><?php $vcb = $vr['checkin_by'] ?? ''; echo $vcb ? '<span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:800">'.htmlspecialchars($vcb).'</span>' : '<span style="color:#A8A29E">—</span>'; ?></td>
            <td>
              <?php if ($vr['checkout_time']): ?>
                <span style="font-weight:800"><?= htmlspecialchars($vr['checkout_time']) ?></span>
                <?php if ($vDate): ?><br><span style="font-size:.68rem;color:#A8A29E;font-weight:600"><?= $vDate ?></span><?php endif; ?>
              <?php else: ?><span style="color:#A8A29E">—</span><?php endif; ?>
            </td>
            <td><?php $vob = $vr['checkout_by'] ?? ''; echo $vob ? '<span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:800">'.htmlspecialchars($vob).'</span>' : '<span style="color:#A8A29E">—</span>'; ?></td>
            <td><?= $vr['checkout_time'] ? '<span class="badge badge-out">Gone Home</span>' : '<span class="badge badge-in">Present</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══ MERCH ══ -->
<div class="tab-panel" id="tab-merch">
  <div class="section">
    <div class="section-title">🎽 Merch Station — T-Shirt Distribution
      <span style="font-size:.75rem;font-weight:500;color:#78716C;font-family:Inter,sans-serif">— admin can undo markings and add walk-ins here</span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px">
      <div class="stat-card" style="border-top-color:#7C3AED">
        <div class="stat-day">Total Logged</div>
        <div class="stat-nums"><span class="stat-pill pill-blue" style="background:#EDE9FE;color:#5B21B6">👕 <?= $tshirtTotal ?> people</span></div>
      </div>
      <div class="stat-card" style="border-top-color:#15803D">
        <div class="stat-day">Received</div>
        <div class="stat-nums"><span class="stat-pill pill-green">✅ <?= $tshirtReceived ?> received</span></div>
      </div>
      <div class="stat-card" style="border-top-color:#D97706">
        <div class="stat-day">Pending / Walk-ins</div>
        <div class="stat-nums">
          <span class="stat-pill pill-amber">⏳ <?= $tshirtTotal - $tshirtReceived ?> pending</span>
          <span class="stat-pill" style="background:#EDE9FE;color:#5B21B6;padding:4px 10px;border-radius:100px;font-size:.72rem;font-weight:700">🚶 <?= $tshirtWalkins ?> walk-ins</span>
        </div>
      </div>
    </div>

    <div style="background:#F5F3FF;border:1.5px solid #C4B5FD;border-radius:12px;padding:16px 20px;margin-bottom:20px">
      <div style="font-weight:800;font-size:.88rem;color:#7C3AED;margin-bottom:12px">➕ Add Walk-in</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:2;min-width:160px">
          <label style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:#78716C;display:block;margin-bottom:4px">Full Name *</label>
          <input class="field" type="text" id="admin-walkin-name" placeholder="e.g. John Thomas" style="width:100%">
        </div>
        <div style="flex:1;min-width:120px">
          <label style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:#78716C;display:block;margin-bottom:4px">T-Shirt Size</label>
          <select class="field" id="admin-walkin-size" style="width:100%;background:white">
            <option value="">— Select —</option>
            <option>XS</option><option>S</option><option>M</option><option>L</option>
            <option>XL</option><option>XXL</option><option>3XL</option>
            <option>S Youth</option><option>M Youth</option><option>L Youth</option>
          </select>
        </div>
        <button type="button" onclick="adminAddWalkin()" class="btn btn-dark" style="background:#7C3AED;border-color:#7C3AED;white-space:nowrap">➕ Add &amp; Mark Received</button>
      </div>
    </div>

    <div class="table-wrap">
      <?php if (empty($tshirtRows)): ?>
        <div class="empty">No t-shirt records yet.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>T-Shirt Size</th><th>Type</th>
            <th>Status</th><th>Received At</th><th>Given By</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tshirtRows as $tr): ?>
          <tr class="<?= $tr['received'] ? 'checked-out' : '' ?>" id="merch-row-<?= $tr['id'] ?>">
            <td><?= $tr['id'] ?></td>
            <td><strong><?= htmlspecialchars($tr['name']) ?></strong></td>
            <td>
              <?php if ($tr['tshirt_size']): ?>
                <span style="background:#EDE9FE;color:#5B21B6;padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:800">👕 <?= htmlspecialchars($tr['tshirt_size']) ?></span>
              <?php else: ?><span style="color:#A8A29E;font-size:.75rem">—</span><?php endif; ?>
            </td>
            <td>
              <?php if ($tr['is_walkin']): ?>
                <span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:99px;font-size:.7rem;font-weight:800">🚶 Walk-in</span>
              <?php else: ?>
                <span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:99px;font-size:.7rem;font-weight:800">🙋 Volunteer</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($tr['received']): ?>
                <span class="badge badge-in" style="background:#DCFCE7;color:#166534">✅ Received</span>
              <?php else: ?>
                <span class="badge" style="background:#FEF3C7;color:#92400E">⏳ Pending</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.78rem;color:var(--muted)"><?= $tr['given_at'] ?: '—' ?></td>
            <td><?php $gb = $tr['given_by'] ?? ''; echo $gb ? '<span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:99px;font-size:.7rem;font-weight:800">'.htmlspecialchars($gb).'</span>' : '<span style="color:#A8A29E">—</span>'; ?></td>
            <td>
              <?php if ($tr['received']): ?>
              <button onclick="adminUndoMerch(<?= $tr['id'] ?>, '<?= htmlspecialchars(addslashes($tr['name']), ENT_QUOTES) ?>', <?= $tr['is_walkin'] ?>)"
                style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;border-radius:6px;padding:4px 10px;font-size:.72rem;font-weight:800;cursor:pointer">↩ Undo</button>
              <?php else: ?><span style="color:#A8A29E;font-size:.75rem">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══ RESET DB ══ -->
<div class="tab-panel" id="tab-reset">
  <div class="section" style="border:2px solid #FEE2E2">
    <div class="section-title" style="color:#991B1B">🔴 Reset Database <span style="font-size:.75rem;font-weight:500;color:#78716C;font-family:Inter,sans-serif">— run once before VBS Day 1 to clear all test data</span></div>
    <p style="font-size:.82rem;color:#78716C;margin-bottom:16px;line-height:1.6">
      This will <strong style="color:#991B1B">permanently delete all check-in records and volunteer attendance records</strong> and reset the ID counters to 1. Cannot be undone.
    </p>
    <form method="POST" id="reset-form" onsubmit="return confirmReset()">
      <input type="hidden" name="action" value="resetDB">
      <div style="display:flex;flex-direction:column;gap:12px;max-width:480px">
        <div class="form-group">
          <label class="form-label">Step 1 — Type <strong>RESET VBS 2026</strong></label>
          <input class="field" type="text" id="reset-step1" placeholder="RESET VBS 2026" autocomplete="off" oninput="checkResetStep1()">
        </div>
        <div class="form-group" id="reset-step2-wrap" style="display:none">
          <label class="form-label">Step 2 — Type <strong>delete data completely</strong></label>
          <input class="field" type="text" name="confirm_reset" id="reset-step2" placeholder="delete data completely" autocomplete="off" oninput="checkResetStep2()">
        </div>
        <div id="reset-btn-wrap" style="display:none">
          <button class="btn btn-red" type="submit" style="padding:10px 20px;font-size:.85rem">🗑 Reset Database</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ══ BACKUP ══ -->
<div class="tab-panel" id="tab-backup">
  <div class="section">
    <div class="section-title">💾 Backup &amp; Restore</div>
    <div class="backup-grid">
      <div class="backup-card">
        <div class="backup-card-title">⬇ Download Backup</div>
        <div class="backup-card-desc">Full backup includes check-ins, volunteer attendance, and merch/t-shirt records.</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn btn-dark" href="?export=json">📦 JSON Backup (all tables)</a>
          <a class="btn btn-dark" href="?export=csv">📄 CSV (check-ins only)</a>
        </div>
      </div>
      <div class="backup-card">
        <div class="backup-card-title">⬆ Restore from Backup</div>
        <div class="backup-card-desc">⚠️ This will <strong>replace all records</strong> with the backup file. Only use if data was lost.</div>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="restoreJSON">
          <input class="file-input" type="file" name="backup_file" accept=".json" required>
          <button class="btn btn-red" type="submit" onclick="return confirm('⚠️ This will DELETE all current records and replace with backup. Are you sure?')">Restore Now</button>
        </form>
      </div>
    </div>

    <div style="margin-top:16px;border:2px solid #DBEAFE;border-radius:12px;padding:20px">
      <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#1E40AF;margin-bottom:10px">🔗 Restore from Google Sheet</div>
      <div style="background:#EFF6FF;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:.77rem;color:#1E40AF;line-height:1.7">
        <strong>One URL restores everything automatically.</strong><br>
        1. Open your VBS backup Google Sheet<br>
        2. Go to <strong>File → Share → Share with others</strong><br>
        3. Change access to <strong>Anyone with the link → Viewer</strong><br>
        4. Click <strong>Copy link</strong> and paste below — no "Publish to web" needed.<br>
        <span style="color:#1D4ED8;font-weight:700">The system will automatically fetch ALL_RECORDS, Vol_Attendance and Merch_Tshirts tabs.</span>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
        <span style="background:#1A2744;color:white;padding:4px 10px;border-radius:99px;font-size:.7rem;font-weight:700">📋 Check-Ins (ALL_RECORDS)</span>
        <span style="background:#15803D;color:white;padding:4px 10px;border-radius:99px;font-size:.7rem;font-weight:700">🙋 Vol Attendance</span>
        <span style="background:#7C3AED;color:white;padding:4px 10px;border-radius:99px;font-size:.7rem;font-weight:700">🎽 Merch / T-Shirts</span>
        <span style="background:#F5F5F4;color:#78716C;padding:4px 10px;border-radius:99px;font-size:.7rem;font-weight:600">Users &amp; logs not restored</span>
      </div>
      <form method="POST" onsubmit="return confirm('⚠️ This will DELETE and replace check-ins, volunteer attendance, and merch records. Are you sure?')">
        <input type="hidden" name="action" value="restoreFromSheet">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <input class="field" type="url" name="sheet_url"
            placeholder="https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit?usp=sharing"
            style="flex:1;min-width:280px;font-size:.78rem" required>
          <button class="btn btn-dark" type="submit" style="white-space:nowrap">🔗 Restore from Sheet</button>
        </div>
        <div style="font-size:.7rem;color:#A8A29E;margin-top:6px">Vol Attendance and Merch tabs are optional — if the tab doesn't exist or is empty it's skipped automatically.</div>
      </form>
    </div>

    <?php
    $backupFiles = is_dir(__DIR__ . '/backups') ? array_reverse(glob(__DIR__ . '/backups/vbs2026-backup-*.json') ?: []) : [];
    if (!empty($backupFiles)):
    ?>
    <div style="margin-top:16px">
      <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#78716C;margin-bottom:10px">🕐 Auto-Saved Backups (<?= count($backupFiles) ?> files)</div>
      <div style="max-height:220px;overflow-y:auto;display:flex;flex-direction:column;gap:6px">
        <?php foreach (array_slice($backupFiles, 0, 20) as $bf):
          $bname    = basename($bf);
          $bsize    = round(filesize($bf) / 1024, 1);
          $bmtime   = date('M j, Y · g:i A', filemtime($bf));
          $bdata    = json_decode(file_get_contents($bf), true);
          $bcount   = $bdata['record_count'] ?? count($bdata['checkins'] ?? []);
          $bvolCount    = count($bdata['vol_attendance'] ?? []);
          $btshirtCount = count($bdata['tshirt_distribution'] ?? []);
          $bsummary = $bcount . ' check-ins' . ($bvolCount ? ' · ' . $bvolCount . ' vol' : '') . ($btshirtCount ? ' · ' . $btshirtCount . ' merch' : '');
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;background:#F5F5F4;border-radius:10px;padding:10px 14px;gap:10px;flex-wrap:wrap">
          <div>
            <div style="font-size:.8rem;font-weight:700;color:#1C1917"><?= $bmtime ?></div>
            <div style="font-size:.7rem;color:#78716C"><?= $bsummary ?> · <?= $bsize ?> KB</div>
          </div>
          <div style="display:flex;gap:6px">
            <a class="btn btn-dark" style="font-size:.72rem;padding:5px 12px;text-decoration:none" href="?export=autobackup&file=<?= urlencode($bname) ?>">⬇ Download</a>
            <form method="POST" onsubmit="return confirm('Restore from this backup? All current records will be replaced.')">
              <input type="hidden" name="action" value="restoreJSON">
              <input type="hidden" name="restore_server_file" value="<?= htmlspecialchars($bname) ?>">
              <button class="btn btn-red" style="font-size:.72rem;padding:5px 12px" type="submit">↩ Restore</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ MANUAL ENTRY ══ -->
<div class="tab-panel" id="tab-manual">
  <div class="section">
    <div class="section-title">✏️ Manual Entry <span style="font-size:.75rem;font-weight:500;color:#78716C;font-family:Inter,sans-serif">— add a record if check-in failed</span></div>
    <form method="POST">
      <input type="hidden" name="action" value="addRecord">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Day *</label>
          <select class="field" name="day" required>
            <option value="1">Day 1 — Thu Jul 9</option>
            <option value="2">Day 2 — Fri Jul 10</option>
            <option value="3">Day 3 — Sat Jul 11</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Child Name *</label>
          <input class="field" type="text" name="child_name" placeholder="Full name" required>
        </div>
        <div class="form-group">
          <label class="form-label">Class *</label>
          <select class="field" name="class_name" required>
            <option>Pre-K</option><option>Kindergarten</option>
            <option>1st Grade</option><option>2nd Grade</option>
            <option>3rd Grade</option><option>4th Grade</option>
            <option>5th Grade</option><option>6th Grade</option>
            <option>7th Grade</option><option>8th Grade</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Grade</label>
          <input class="field" type="text" name="grade" placeholder="e.g. 5th">
        </div>
        <div class="form-group">
          <label class="form-label">Parent Name</label>
          <input class="field" type="text" name="parent" placeholder="Parent full name">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input class="field" type="text" name="phone" placeholder="Phone number">
        </div>
        <div class="form-group">
          <label class="form-label">Allergies</label>
          <input class="field" type="text" name="allergies" placeholder="None">
        </div>
        <div class="form-group">
          <label class="form-label">Check-In Time *</label>
          <input class="field" type="text" name="checkin_time" placeholder="e.g. 9:00 AM" required>
        </div>
        <div class="form-group">
          <label class="form-label">Check-In By</label>
          <input class="field" type="text" name="checkin_by" placeholder="Registration Desk">
        </div>
        <div class="form-group">
          <label class="form-label">Check-Out Time</label>
          <input class="field" type="text" name="checkout_time" placeholder="Leave blank if still inside">
        </div>
        <div class="form-group">
          <label class="form-label">Check-Out By</label>
          <input class="field" type="text" name="checkout_by" placeholder="Teacher name">
        </div>
        <div class="form-group" style="justify-content:flex-end;padding-top:20px">
          <button class="btn btn-green" type="submit">➕ Add Record</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ══ CHECK-IN RECORDS ══ -->
<div class="tab-panel" id="tab-records">
  <div class="controls">
    <a class="filter-btn <?= !$filterDay ? 'active' : '' ?>" href="admin.php">All Days (<?= $totalAll ?>)</a>
    <?php for ($d = 1; $d <= 3; $d++): ?>
    <a class="filter-btn <?= $filterDay===$d ? 'active' : '' ?>" href="?day=<?= $d ?>">Day <?= $d ?></a>
    <?php endfor; ?>
    <a class="btn btn-dark" href="?export=csv<?= $filterDay ? '&day='.$filterDay : '' ?>" style="margin-left:auto;text-decoration:none;padding:8px 16px;border-radius:10px">⬇ Export CSV</a>
    <a class="btn btn-dark" href="?export=json" style="text-decoration:none;padding:8px 16px;border-radius:10px">📦 Backup JSON</a>
    <button id="refresh-btn" onclick="refreshPage()" type="button" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:10px;border:2px solid #E7E5E4;background:white;font-size:.82rem;font-weight:700;cursor:pointer;color:#1C1917;font-family:inherit">🔄 Refresh</button>
  </div>

  <div class="table-wrap" style="font-size:.8rem">
    <?php if (empty($rows)): ?>
      <div class="empty">No check-ins recorded yet.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th><th>Day</th><th>Child</th><th>Class</th>
          <th>Parent</th><th>Allergies</th>
          <th>Check In</th><th>By</th><th>Check Out</th><th>By</th><th>Early Reason</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        try { getDB()->exec("ALTER TABLE checkins ADD COLUMN early_checkout_reason VARCHAR(255) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
        foreach ($rows as $r):
          $earlyReason = $r['early_checkout_reason'] ?? '';
          $dayDates    = [1 => 'Jul 9', 2 => 'Jul 10', 3 => 'Jul 11'];
          $dateLabel   = $dayDates[intval($r['day'])] ?? '';
        ?>
        <tr class="<?= $r['checkout_time'] ? 'checked-out' : '' ?>">
          <td><?= $r['id'] ?></td>
          <td>Day <?= $r['day'] ?></td>
          <td><strong><?= htmlspecialchars($r['child_name']) ?></strong></td>
          <td><?= htmlspecialchars($r['class_name']) ?></td>
          <td><?= htmlspecialchars($r['parent']) ?></td>
          <td><?= $r['allergies'] && $r['allergies'] !== 'None'
            ? '<span class="allergy">⚠️ '.htmlspecialchars($r['allergies']).'</span>'
            : '<span style="color:#A8A29E">None</span>' ?></td>
          <td>
            <span style="font-weight:800"><?= htmlspecialchars($r['checkin_time']) ?></span>
            <?php if ($dateLabel): ?><br><span style="font-size:.68rem;color:#A8A29E;font-weight:600"><?= $dateLabel ?></span><?php endif; ?>
          </td>
          <td><?php $ciBy = $r['checkin_by'] ?? ''; echo $ciBy ? '<span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:800">'.htmlspecialchars($ciBy).'</span>' : '<span style="color:#A8A29E">—</span>'; ?></td>
          <td>
            <?php if ($r['checkout_time']): ?>
              <span style="font-weight:800"><?= htmlspecialchars($r['checkout_time']) ?></span>
              <?php if ($dateLabel): ?><br><span style="font-size:.68rem;color:#A8A29E;font-weight:600"><?= $dateLabel ?></span><?php endif; ?>
            <?php else: ?><span style="color:#A8A29E">—</span><?php endif; ?>
          </td>
          <td><?php $coBy = $r['checkout_by'] ?? ''; echo $coBy ? '<span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:800">'.htmlspecialchars($coBy).'</span>' : '<span style="color:#A8A29E">—</span>'; ?></td>
          <td>
            <?php if ($earlyReason): ?>
              <span style="background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;border-radius:6px;padding:2px 8px;font-size:.7rem;font-weight:800;white-space:nowrap">⏰ <?= htmlspecialchars($earlyReason) ?></span>
            <?php else: ?><span style="color:#A8A29E">—</span><?php endif; ?>
          </td>
          <td><?= $r['checkout_time'] ? '<span class="badge badge-out">Gone Home</span>' : '<span class="badge badge-in">Inside</span>' ?></td>
          <td style="white-space:nowrap;display:flex;gap:6px">
            <?php if (!$r['checkout_time']): ?>
            <button class="btn btn-orange" style="font-size:.65rem;padding:3px 8px"
              onclick="openCheckout(<?= $r['id'] ?>, '<?= htmlspecialchars($r['child_name']) ?>')">🏁 Checkout</button>
            <?php endif; ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record?')">
              <input type="hidden" name="action" value="deleteRecord">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-red" type="submit">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Manual Checkout Modal -->
<div class="modal-overlay" id="checkout-modal">
  <div class="modal">
    <div class="modal-title">🏁 Manual Checkout</div>
    <p style="font-size:.85rem;color:#78716C;margin-bottom:16px">Recording checkout for: <strong id="modal-child-name"></strong></p>
    <form method="POST">
      <input type="hidden" name="action" value="manualCheckout">
      <input type="hidden" name="id" id="modal-record-id">
      <div class="modal-form-grid">
        <div class="form-group">
          <label class="form-label">Check-Out Time *</label>
          <input class="field" type="text" name="checkout_time" id="modal-time" placeholder="e.g. 12:30 PM" required>
        </div>
        <div class="form-group">
          <label class="form-label">Checked Out By</label>
          <input class="field" type="text" name="checkout_by" placeholder="Teacher name">
        </div>
      </div>
      <div class="modal-btns">
        <button type="button" class="btn-cancel" onclick="closeCheckout()">Cancel</button>
        <button type="submit" class="btn btn-orange">Confirm Checkout</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ LOGIN HISTORY ══ -->
<div class="tab-panel" id="tab-logins">
  <div class="section">
    <div class="section-title">🔐 Login History</div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.82rem">
        <thead>
          <tr style="background:var(--navy);color:white">
            <th style="padding:10px 14px;text-align:left;border-radius:10px 0 0 0">Username</th>
            <th style="padding:10px 14px;text-align:left">Display Name</th>
            <th style="padding:10px 14px;text-align:left">Role</th>
            <th style="padding:10px 14px;text-align:left">Last Login</th>
            <th style="padding:10px 14px;text-align:left">Last Logout</th>
            <th style="padding:10px 14px;text-align:left">IP Address</th>
            <th style="padding:10px 14px;text-align:left;border-radius:0 10px 0 0">Location</th>
          </tr>
        </thead>
        <tbody>
        <?php
        try {
            $db = getDB();
            try { $db->exec("ALTER TABLE vbs_users ADD COLUMN last_logout DATETIME NULL"); } catch(Exception $e) {}
            try { $db->exec("ALTER TABLE vbs_users ADD COLUMN last_login_ip VARCHAR(64) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
            try { $db->exec("ALTER TABLE vbs_users ADD COLUMN last_login_location VARCHAR(255) NOT NULL DEFAULT ''"); } catch(Exception $e) {}
            $users = $db->query('SELECT username, display_name, role, active, last_login, last_logout, last_login_ip, last_login_location FROM vbs_users ORDER BY last_login DESC')->fetchAll();
            if (empty($users)) {
                echo '<tr><td colspan="7" style="padding:20px;text-align:center;color:var(--muted)">No users yet.</td></tr>';
            } else {
                foreach ($users as $u):
                    $roleLabel   = $u['role'] === 'both' ? '✅🏁 Both' : ($u['role'] === 'checkin' ? '✅ Check-in' : '🏁 Check-out');
                    $ll          = $u['last_login'] ? date('M j, Y g:i A', strtotime($u['last_login'])) : 'Never logged in';
                    $activeBadge = $u['active'] ? '' : ' <span style="background:#FEE2E2;color:#991B1B;padding:1px 6px;border-radius:99px;font-size:.68rem;font-weight:800">Disabled</span>';
                    $ip          = htmlspecialchars($u['last_login_ip'] ?? '');
                    $loc         = htmlspecialchars($u['last_login_location'] ?? '');
        ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:9px 14px;font-weight:800;color:var(--navy)"><?= htmlspecialchars($u['username']) ?><?= $activeBadge ?></td>
          <td style="padding:9px 14px"><?= htmlspecialchars($u['display_name']) ?></td>
          <td style="padding:9px 14px;font-size:.72rem;font-weight:800"><?= $roleLabel ?></td>
          <td style="padding:9px 14px;color:var(--muted);font-size:.8rem"><?= $ll ?></td>
          <td style="padding:9px 14px;color:var(--muted);font-size:.8rem"><?= $u['last_logout'] ? date('M j, Y g:i A', strtotime($u['last_logout'])) : '—' ?></td>
          <td style="padding:9px 14px;font-size:.78rem;font-family:monospace;color:var(--navy)"><?= $ip ?: '<span style="color:var(--muted)">—</span>' ?></td>
          <td style="padding:9px 14px;font-size:.78rem;color:var(--muted)"><?= $loc ?: '<span style="color:var(--muted)">—</span>' ?></td>
        </tr>
        <?php endforeach; }
        } catch(Exception $e) { echo '<tr><td colspan="7" style="color:#EF4444;padding:16px">Error: ' . htmlspecialchars($e->getMessage()) . '</td></tr>'; } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══ ACTIVITY LOG ══ -->
<?php
$logRows = [];
try {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS admin_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(100) NOT NULL,
        detail TEXT,
        logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $logRows = $db->query("SELECT * FROM admin_log ORDER BY id DESC LIMIT 200")->fetchAll();
} catch (Exception $e) {}

$actionLabels = [
    'login'              => ['🔐', 'Admin Login',         '#EEF2FF', '#4F46E5'],
    'logout'             => ['🚪', 'Admin Logout',        '#F1F5F9', '#475569'],
    'create_user'        => ['👤', 'User Created',        '#F0FDF4', '#15803D'],
    'update_user'        => ['✏️', 'User Updated',        '#EFF6FF', '#1D4ED8'],
    'delete_user'        => ['🗑️', 'User Deleted',        '#FEF2F2', '#DC2626'],
    'reset_password'     => ['🔑', 'Password Reset',      '#FFF7ED', '#C2410C'],
    'assign_groups'      => ['🏷️', 'Groups Assigned',     '#F0FDF4', '#15803D'],
    'assign_panels'      => ['🖥️', 'Panels Assigned',     '#EEF2FF', '#4F46E5'],
    'update_pin'         => ['🔑', 'PIN Updated',         '#FFF7ED', '#C2410C'],
    'save_settings'      => ['📅', 'Day Settings Saved',  '#F0FDF4', '#15803D'],
    'add_record'         => ['➕', 'Record Added',        '#F0FDF4', '#15803D'],
    'delete_record'      => ['🗑️', 'Record Deleted',      '#FEF2F2', '#DC2626'],
    'reset_database'     => ['🔴', 'Database Reset',      '#FEF2F2', '#DC2626'],
    'restore_from_sheet' => ['📊', 'Restored from Sheet', '#FFFBEB', '#D97706'],
    'restore_json'       => ['📦', 'Restored from JSON',  '#FFFBEB', '#D97706'],
    'run_backup'         => ['💾', 'Manual Backup Run',   '#EEF2FF', '#4F46E5'],
    'manual_checkout'    => ['🏁', 'Manual Checkout',     '#F0FDF4', '#15803D'],
    'clear_log'          => ['🗑️', 'Log Cleared',         '#FEF2F2', '#DC2626'],
];
?>
<div class="tab-panel" id="tab-log">
  <div class="section">
    <div class="section-title" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      📋 Admin Activity Log
      <span style="font-size:.75rem;font-weight:500;color:#78716C;font-family:Inter,sans-serif">— last 200 actions, newest first</span>
      <?php if (!empty($logRows)): ?>
      <form method="POST" style="margin-left:auto" onsubmit="return confirm('Clear all log entries? This cannot be undone.')">
        <input type="hidden" name="action" value="clearLog">
        <button type="submit" class="btn" style="background:#FEE2E2;color:#991B1B;border:none;font-size:.75rem;padding:5px 12px">🗑 Clear Log</button>
      </form>
      <?php endif; ?>
    </div>

    <?php if (empty($logRows)): ?>
      <div style="text-align:center;padding:32px;color:#A8A29E;font-weight:700">No activity logged yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.82rem">
      <thead>
        <tr style="background:#F5F5F4">
          <th style="padding:10px 12px;text-align:left;font-weight:700;color:#78716C;white-space:nowrap">#</th>
          <th style="padding:10px 12px;text-align:left;font-weight:700;color:#78716C;white-space:nowrap">Action</th>
          <th style="padding:10px 12px;text-align:left;font-weight:700;color:#78716C">Detail</th>
          <th style="padding:10px 12px;text-align:left;font-weight:700;color:#78716C;white-space:nowrap">Date &amp; Time</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($logRows as $i => $row):
          $lbl = $actionLabels[$row['action']] ?? ['📌', ucfirst(str_replace('_',' ',$row['action'])), '#F5F5F4', '#57534E'];
          $bg  = ($i % 2 === 0) ? 'white' : '#FAFAF9';
      ?>
        <tr style="background:<?= $bg ?>;border-bottom:1px solid #E7E5E4">
          <td style="padding:10px 12px;color:#A8A29E;font-weight:700"><?= $row['id'] ?></td>
          <td style="padding:10px 12px;white-space:nowrap">
            <span style="display:inline-flex;align-items:center;gap:6px;background:<?= $lbl[2] ?>;color:<?= $lbl[3] ?>;padding:3px 10px;border-radius:20px;font-weight:800;font-size:.75rem">
              <?= $lbl[0] ?> <?= htmlspecialchars($lbl[1]) ?>
            </span>
          </td>
          <td style="padding:10px 12px;color:#44403C"><?= htmlspecialchars($row['detail'] ?? '—') ?></td>
          <td style="padding:10px 12px;color:#78716C;white-space:nowrap;font-size:.78rem"><?= htmlspecialchars($row['logged_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
</div>

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /shell -->

<!-- ══ ALL JAVASCRIPT (single unified block) ══ -->
<script>
const ADMIN_SECRET = 'VBS2026@ICANJ';
const API_URL      = 'api.php';

const CSV_VOLS_URL = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT1qao6oa4ze6Hzmy6q6DeltkBWYzgr8Dtp29zYROsFbjpxpOqNveYjU2cNbSbIVAfduJEYsrXh1v83/pub?output=csv';

// ── Shared admin API helper ──
async function adminPost(data) {
  const res = await fetch(API_URL, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({...data, adminSecret: ADMIN_SECRET})
  });
  return res.json();
}

// ── Tab navigation ──
var TAB_TITLES = {
  'tab-dashboard': '📊 Dashboard',
  'tab-users':     '👤 User Management',
  'tab-vol-sync':  '🔄 Volunteer Sync',
  'tab-vol-att':   '🙋 Volunteer Attendance',
  'tab-merch':     '🎽 Merch Station',
  'tab-days':      '📅 Day Settings',
  'tab-manual':    '✏️ Manual Entry',
  'tab-backup':    '💾 Backup & Restore',
  'tab-reset':     '🔴 Reset Database',
  'tab-records':   '📋 Check-In Records',
  'tab-logins':    '🔐 Login History',
  'tab-log':       '📋 Activity Log',
};
function showTab(id) {
  document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('on'); });
  document.querySelectorAll('.sb-item').forEach(function(b) { b.classList.remove('on'); });
  var panel = document.getElementById(id);
  if (panel) panel.classList.add('on');
  document.querySelectorAll('.sb-item').forEach(function(b) {
    if (b.getAttribute('onclick') && b.getAttribute('onclick').includes("'" + id + "'")) b.classList.add('on');
  });
  var h = document.getElementById('topbar-heading');
  if (h) h.textContent = TAB_TITLES[id] || '';
}
(function() {
  var hash = window.location.hash.replace('#','');
  if (hash && document.getElementById(hash)) showTab(hash);
})();

// ── Refresh ──
function refreshPage() {
  var btn = document.getElementById('refresh-btn');
  btn.innerHTML = '⏳ Refreshing…';
  btn.disabled = true;
  var url = new URL(window.location.href);
  url.search = '';
  window.location.replace(url.toString());
}

// ── Checkout modal ──
function openCheckout(id, name) {
  document.getElementById('modal-record-id').value = id;
  document.getElementById('modal-child-name').textContent = name;
  var now = new Date();
  var h = now.getHours(), m = String(now.getMinutes()).padStart(2,'0');
  document.getElementById('modal-time').value = (h%12||12) + ':' + m + ' ' + (h>=12?'PM':'AM');
  document.getElementById('checkout-modal').classList.add('open');
}
function closeCheckout() {
  document.getElementById('checkout-modal').classList.remove('open');
}
document.getElementById('checkout-modal').addEventListener('click', function(e) {
  if (e.target === this) closeCheckout();
});

// ── Reset DB steps ──
function checkResetStep1() {
  var val = document.getElementById('reset-step1').value;
  var step2 = document.getElementById('reset-step2-wrap');
  var step2Input = document.getElementById('reset-step2');
  if (val === 'RESET VBS 2026') {
    step2.style.display = 'block';
    step2Input.focus();
  } else {
    step2.style.display = 'none';
    document.getElementById('reset-btn-wrap').style.display = 'none';
    if (step2Input) step2Input.value = '';
  }
}
function checkResetStep2() {
  var val = document.getElementById('reset-step2').value;
  document.getElementById('reset-btn-wrap').style.display = val === 'delete data completely' ? 'block' : 'none';
}
function confirmReset() {
  return confirm('⚠️ Are you sure? This will PERMANENTLY DELETE all check-in records. This cannot be undone.');
}

// ── Create user form ──
document.getElementById('create-user-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  var fd = new FormData(this);
  var result = await adminPost({
    action: 'createUser',
    username: fd.get('username'),
    display_name: fd.get('display_name') || fd.get('username'),
    password: fd.get('password'),
    role: 'both'
  });
  if (result.status === 'ok') { alert('✅ User created! Now assign their panels and groups.'); location.reload(); }
  else alert('❌ ' + result.message);
});

// ── Edit user ──
function editUser(id, username, displayName, role, active) {
  document.getElementById('edit-user-id').value = id;
  document.getElementById('edit-username-display').textContent = username;
  document.getElementById('edit-display-name').value = displayName;
  document.getElementById('edit-role').value = role;
  document.getElementById('edit-active').checked = active == 1;
  document.getElementById('edit-user-modal').style.display = 'flex';
}
function closeEditModal() { document.getElementById('edit-user-modal').style.display = 'none'; }
async function saveEditUser() {
  var id = document.getElementById('edit-user-id').value;
  var result = await adminPost({
    action: 'updateUser',
    id: parseInt(id),
    display_name: document.getElementById('edit-display-name').value,
    role: document.getElementById('edit-role').value,
    active: document.getElementById('edit-active').checked ? 1 : 0
  });
  if (result.status === 'ok') { alert('✅ User updated!'); location.reload(); }
  else alert('❌ ' + result.message);
}

// ── Reset password ──
function resetPassword(id, username) {
  document.getElementById('reset-pw-id').value = id;
  document.getElementById('reset-pw-username').textContent = 'User: ' + username;
  document.getElementById('reset-pw-input').value = '';
  document.getElementById('reset-pw-modal').style.display = 'flex';
}
async function saveResetPassword() {
  var id = document.getElementById('reset-pw-id').value;
  var pw = document.getElementById('reset-pw-input').value;
  if (!pw) { alert('Please enter a new password'); return; }
  var result = await adminPost({ action: 'resetPassword', id: parseInt(id), password: pw });
  if (result.status === 'ok') { alert('✅ Password reset!'); document.getElementById('reset-pw-modal').style.display = 'none'; }
  else alert('❌ ' + result.message);
}

// ── Delete user ──
async function deleteUser(id, username) {
  if (!confirm('Delete user "' + username + '"? This cannot be undone.')) return;
  var result = await adminPost({ action: 'deleteUser', id: parseInt(id) });
  if (result.status === 'ok') { alert('✅ User deleted'); location.reload(); }
  else alert('❌ ' + result.message);
}

// ── Assign groups ──
var ALL_GROUPS = ['Pre-K', 'Pre-Primary', 'Primary', 'Junior'];
var _assignUserId = null;
function assignGroups(id, displayName, currentGroups) {
  _assignUserId = id;
  document.getElementById('assign-groups-title').textContent = '🏷️ Assign Groups — ' + displayName;
  ALL_GROUPS.forEach(function(g) {
    document.getElementById('grp-' + g.replace(/[^a-z]/gi,'').toLowerCase()).checked = currentGroups.includes(g);
  });
  document.getElementById('assign-groups-modal').style.display = 'flex';
}
async function saveAssignGroups() {
  var selected = ALL_GROUPS.filter(function(g) {
    return document.getElementById('grp-' + g.replace(/[^a-z]/gi,'').toLowerCase()).checked;
  });
  var result = await adminPost({ action: 'updateUserGroups', id: _assignUserId, groups: selected });
  if (result.status === 'ok') { alert('✅ Groups updated!'); location.reload(); }
  else alert('❌ ' + result.message);
}

// ── Assign panels ──
var ALL_PANELS = [
  { key:'checkin',        label:'✅ Check-in' },
  { key:'checkout',       label:'🏁 Check-out' },
  { key:'kids',           label:'🧒 Kids Registration' },
  { key:'volunteers',     label:'🙋 Volunteers' },
  { key:'attendance',     label:'📊 Live Attendance' },
  { key:'schedule',       label:'📅 Schedule' },
  { key:'vol_attendance', label:'🙋 Vol Kiosk' }
];
var _assignPanelsUserId = null;
function assignPanels(id, displayName, currentPanels) {
  _assignPanelsUserId = id;
  document.getElementById('assign-panels-title').textContent = '🖥️ Assign Panels — ' + displayName;
  ALL_PANELS.forEach(function(p) {
    document.getElementById('panel-' + p.key).checked = currentPanels.includes(p.key);
  });
  document.getElementById('assign-panels-modal').style.display = 'flex';
}
async function saveAssignPanels() {
  var selected = ALL_PANELS.filter(function(p) {
    return document.getElementById('panel-' + p.key).checked;
  }).map(function(p) { return p.key; });
  var result = await adminPost({ action: 'updateUserPanels', id: _assignPanelsUserId, panels: selected });
  if (result.status === 'ok') { alert('✅ Panel access updated!'); location.reload(); }
  else alert('❌ ' + result.message);
}

// ── Volunteer Sync ──
async function runVolSync() {
  var btn = document.getElementById('sync-btn');
  var res = document.getElementById('sync-result');
  btn.disabled = true; btn.textContent = '⏳ Fetching…';
  res.style.display = 'none';
  try {
    var csvRes  = await fetch(CSV_VOLS_URL + '&t=' + Date.now());
    var csvText = await csvRes.text();
    var lines   = csvText.trim().split('\n');
    var headers = lines[0].split(',').map(function(h){ return h.trim().replace(/^"|"$/g,'').toLowerCase(); });
    var nameIdx = headers.findIndex(function(h){ return h.includes("what's your name") || h === 'name'; });
    if (nameIdx === -1) throw new Error('Name column not found in volunteer sheet');
    var names = [];
    for (var i = 1; i < lines.length; i++) {
      var cols = lines[i].split(',');
      var name = (cols[nameIdx]||'').trim().replace(/^"|"$/g,'');
      if (name) names.push(name);
    }
    if (!names.length) throw new Error('No names found');
    btn.textContent = '⏳ Creating accounts…';
    var result = await adminPost({ action: 'syncVolunteers', names: names });
    if (result.status !== 'ok') throw new Error(result.message || 'Sync failed');
    var created = result.created || [], skipped = result.skipped || [];
    var html = '';
    if (created.length) {
      html += '<div style="background:#F5F3FF;border:1.5px solid #C4B5FD;border-radius:10px;padding:14px;margin-bottom:8px">' +
        '<div style="font-weight:800;font-size:.84rem;color:#7C3AED;margin-bottom:10px">✅ ' + created.length + ' account(s) created</div>' +
        '<table style="width:100%;border-collapse:collapse;font-size:.76rem"><thead>' +
        '<tr style="background:#EDE9FE"><th style="padding:5px 8px;text-align:left">Name</th><th style="padding:5px 8px;text-align:left">Username</th><th style="padding:5px 8px;text-align:left">Password</th></tr>' +
        '</thead><tbody>' +
        created.map(function(c){ return '<tr style="border-top:1px solid #EDE9FE"><td style="padding:5px 8px;font-weight:700">'+c.name+'</td><td style="padding:5px 8px;font-family:monospace;color:#4F46E5">'+c.username+'</td><td style="padding:5px 8px;font-family:monospace;font-weight:800;color:#92400E">'+c.password+'</td></tr>'; }).join('') +
        '</tbody></table></div>';
    }
    if (skipped.length) {
      html += '<div style="background:#F8FAFC;border:1.5px solid #E2E8F0;border-radius:8px;padding:10px 14px;font-size:.76rem;color:#64748B"><strong>' + skipped.length + ' already have accounts:</strong> ' + skipped.join(', ') + '</div>';
    }
    if (!html) html = '<div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:8px;padding:10px 14px;font-size:.8rem;color:#15803D;font-weight:700">✅ All volunteers already have accounts.</div>';
    res.innerHTML = html; res.style.display = 'block';
    if (created.length) setTimeout(function(){ location.reload(); }, 2000);
  } catch(e) {
    res.innerHTML = '<div style="background:#FEE2E2;border:1.5px solid #FCA5A5;border-radius:8px;padding:10px 14px;font-size:.8rem;color:#991B1B;font-weight:700">❌ ' + e.message + '</div>';
    res.style.display = 'block';
  }
  btn.disabled = false; btn.textContent = '🔄 Sync Volunteers Now';
}

// ── Merch ──
async function adminUndoMerch(id, name, isWalkin) {
  if (!confirm('Undo t-shirt receipt for "' + name + '"?' + (isWalkin ? '\n\n(Walk-in — this will remove them from the list entirely.)' : ''))) return;
  var result = await adminPost({ action: 'tshirtUnmark', name: name });
  if (result.status === 'ok') {
    var row = document.getElementById('merch-row-' + id);
    if (row) {
      if (isWalkin) {
        row.remove();
      } else {
        row.classList.remove('checked-out');
        var cells = row.querySelectorAll('td');
        cells[4].innerHTML = '<span class="badge" style="background:#FEF3C7;color:#92400E">⏳ Pending</span>';
        cells[5].textContent = '—';
        cells[6].innerHTML  = '<span style="color:#A8A29E">—</span>';
        cells[7].innerHTML  = '<span style="color:#A8A29E;font-size:.75rem">—</span>';
      }
    }
    alert('✅ Undone — ' + name + ' marked as not received.');
  } else {
    alert('❌ ' + (result.message || 'Something went wrong.'));
  }
}

async function adminAddWalkin() {
  var nameEl = document.getElementById('admin-walkin-name');
  var sizeEl = document.getElementById('admin-walkin-size');
  var name   = nameEl.value.trim();
  var size   = sizeEl.value.trim();
  if (!name) { nameEl.focus(); nameEl.style.borderColor = '#EF4444'; return; }
  nameEl.style.borderColor = '';
  var result = await adminPost({
    action: 'tshirtMark', name: name, tshirtSize: size,
    givenBy: 'Admin', isWalkin: 1
  });
  if (result.status === 'ok') {
    nameEl.value = ''; sizeEl.value = '';
    alert('✅ Walk-in added and marked as received: ' + name);
    location.reload();
  } else {
    alert('❌ ' + (result.message || 'Something went wrong.'));
  }
}

// Clean ?msg= from URL after displaying flash message
if (window.location.search.includes('msg=')) {
  window.history.replaceState(null, '', window.location.pathname);
}
</script>

<?php endif; ?>
</body>
</html>