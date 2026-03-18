<?php
/**
 * upload.php — Eid Salami Collection
 * Handles form submissions: validates, saves image, writes to data.json
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ── Config ──
define('UPLOAD_DIR',  __DIR__ . '/uploads/');
define('DATA_FILE',   __DIR__ . '/data.json');
define('MAX_SIZE',    3 * 1024 * 1024); // 3 MB
define('ALLOWED_EXT', ['jpg', 'jpeg', 'png']);
define('ALLOWED_MIME',['image/jpeg', 'image/jpg', 'image/png']);

// ── Helper: respond and exit ──
function respond(bool $success, string $msg, array $extra = []): void {
    echo json_encode(array_merge(
        ['success' => $success, $success ? 'message' : 'error' => $msg],
        $extra
    ));
    exit;
}

// ── Only accept POST ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

// ── Read & sanitize fields ──
$name    = trim(htmlspecialchars(strip_tags($_POST['name']   ?? ''), ENT_QUOTES, 'UTF-8'));
$amount  = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
$method  = trim(htmlspecialchars(strip_tags($_POST['method']  ?? ''), ENT_QUOTES, 'UTF-8'));
$message = trim(htmlspecialchars(strip_tags($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8'));

// ── Validate fields ──
if (empty($name) || strlen($name) > 60) {
    respond(false, 'Invalid name.');
}
if ($amount === false || $amount < 1 || $amount > 100000) {
    respond(false, 'Invalid amount.');
}
if (!in_array($method, ['bKash', 'Nagad'], true)) {
    respond(false, 'Invalid payment method.');
}
if (strlen($message) > 120) {
    respond(false, 'Message too long.');
}

// ── Validate screenshot ──
if (empty($_FILES['screenshot']['name'])) {
    respond(false, 'Screenshot is required.');
}

$file     = $_FILES['screenshot'];
$fileSize = $file['size'];
$tmpPath  = $file['tmp_name'];
$origName = basename($file['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, ALLOWED_EXT, true)) {
    respond(false, 'Only JPG, JPEG, PNG files are allowed.');
}
if ($fileSize > MAX_SIZE) {
    respond(false, 'File too large. Max 3MB.');
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    respond(false, 'Upload error. Please try again.');
}

// ── Verify MIME via getimagesize (safer than finfo) ──
$imgInfo = @getimagesize($tmpPath);
if (!$imgInfo || !in_array($imgInfo['mime'], ALLOWED_MIME, true)) {
    respond(false, 'Invalid image file.');
}

// ── Ensure uploads dir exists ──
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    // Prevent direct listing
    file_put_contents(UPLOAD_DIR . '.htaccess', "Options -Indexes\n");
}

// ── Generate unique filename ──
$filename  = uniqid('ss_', true) . '.' . $ext;
$destPath  = UPLOAD_DIR . $filename;

if (!move_uploaded_file($tmpPath, $destPath)) {
    respond(false, 'Could not save file. Check server permissions.');
}

// ── Read existing data ──
$entries = [];
if (file_exists(DATA_FILE)) {
    $raw = file_get_contents(DATA_FILE);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $entries = $decoded;
    }
}

// ── Build new entry ──
$entry = [
    'id'         => uniqid('', true),
    'name'       => $name,
    'amount'     => (float) $amount,
    'method'     => $method,
    'message'    => $message,
    'screenshot' => $filename,
    'status'     => 'pending',
    'timestamp'  => date('c'),      // ISO 8601
    'ip_hash'    => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
];

$entries[] = $entry;

// ── Write back ──
$written = file_put_contents(
    DATA_FILE,
    json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

if ($written === false) {
    respond(false, 'Could not save data. Check server permissions.');
}

respond(true, 'Submission received! Awaiting approval.');
