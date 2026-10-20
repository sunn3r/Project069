<?php
// ให้ PHP ใช้เวลาไทยเสมอ (กันวันที่ใน PHP ไม่ตรงกับ CURDATE() ของ MySQL เวลาเซิร์ฟเวอร์ตั้ง timezone อื่น)
date_default_timezone_set('Asia/Bangkok');

// ค่าเชื่อมต่อฐานข้อมูล เรียงลำดับความสำคัญ (ทีหลังทับก่อนหน้า):
//   1. ค่า default สำหรับ XAMPP ในเครื่อง (localhost / root / ไม่มีรหัส)
//   2. ไฟล์ config/credentials.php ถ้ามี (ใช้บนเซิร์ฟเวอร์จริง — ดู credentials.example.php)
//   3. ตัวแปร environment DB_HOST / DB_NAME / DB_USER / DB_PASS
//      (Docker ตั้งค่านี้ให้ — ทับ credentials.php เสมอ เพื่อให้รันในเครื่องได้แม้มีไฟล์ credentials.php ของเซิร์ฟเวอร์ค้างอยู่)
$dbConfig = [
    'host' => 'localhost',
    'name' => 'recruitment_db',
    'user' => 'root',
    'pass' => '',
];

$credentialsFile = __DIR__ . '/credentials.php';
if (is_file($credentialsFile)) {
    $dbConfig = array_merge($dbConfig, require $credentialsFile);
}

foreach (['host' => 'DB_HOST', 'name' => 'DB_NAME', 'user' => 'DB_USER', 'pass' => 'DB_PASS'] as $key => $envName) {
    $envValue = getenv($envName);
    if ($envValue !== false) {
        $dbConfig[$key] = $envValue;
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('ระบบขัดข้องชั่วคราว กรุณาลองใหม่ภายหลัง');
}

function isAllowedUploadFile($filename)
{
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowedExtensions, true);
}

/**
 * true ถ้า $data มีทุก key ใน $required และไม่ว่าง (ใช้เช็คว่ากรอกข้อมูลครบทุกช่อง)
 */
function hasAllFields($data, array $required)
{
    foreach ($required as $key) {
        if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
            return false;
        }
    }
    return true;
}

/**
 * CSRF token — ต้องเรียกหลัง session_start() แล้ว
 * csrfField()  : พิมพ์ <input hidden> ใส่ในทุกฟอร์ม method="post"
 * csrfCheck()  : เรียกต้นสุดของทุก handler ที่รับ POST ถ้าไม่ผ่านจะหยุดทันที
 */
function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField()
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrfCheck()
{
    $sent = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !is_string($sent) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(400);
        die('คำขอไม่ถูกต้องหรือเซสชันหมดอายุ กรุณากลับไปโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}

/**
 * สร้างเลขรันนิ่ง 5 หลักถัดไป จากค่าสูงสุดที่มีอยู่ (ไม่ใช่ COUNT(*) — กันเลขชนกันเมื่อมีการลบแถว
 * หรือมีการสมัครพร้อมกัน) $table/$column เป็นค่าคงที่จากโค้ดเท่านั้น จึงมี allowlist กันพลาด
 */
function nextSequentialId(PDO $pdo, $table, $column)
{
    $allowed = ['job_applications' => 'app_id', 'jobs' => 'job_id', 'preapplications' => 'preapp_id'];
    if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
        throw new InvalidArgumentException('nextSequentialId: table/column ไม่อยู่ใน allowlist');
    }
    $max = (int) $pdo->query("SELECT COALESCE(MAX(`$column` + 0), 0) FROM `$table`")->fetchColumn();
    return str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
}

/**
 * ย้ายไฟล์อัปโหลดไปปลายทาง ถ้าล้มเหลว (เช่นเขียนโฟลเดอร์ไม่ได้ / ดิสก์เต็ม) ให้หยุดทันที
 * ไม่ปล่อยให้โค้ดวิ่งต่อจนขึ้น "สำเร็จ" ทั้งที่ไฟล์ไม่ได้บันทึก
 */
function storeUploadedFile(array $file, $destPath)
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])
        || !move_uploaded_file($file['tmp_name'], $destPath)) {
        error_log('storeUploadedFile failed -> ' . $destPath);
        http_response_code(500);
        die('อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง หากยังไม่ได้โปรดติดต่อผู้ดูแลระบบ');
    }
}

/**
 * ตรวจไฟล์อัปโหลด: นามสกุล + ขนาด + ชนิดไฟล์จริง (magic bytes / MIME)
 * คืน null ถ้าผ่าน ไม่งั้นคืนข้อความ error
 */
function uploadValidationError(array $file, $maxBytes = 10485760)
{
    if (!isAllowedUploadFile($file['name'] ?? '')) {
        return 'อนุญาตเฉพาะไฟล์ PDF, JPG, PNG';
    }
    $size = $file['size'] ?? 0;
    if ($size <= 0 || $size > $maxBytes) {
        return 'ขนาดไฟล์ต้องมากกว่า 0 และไม่เกิน ' . round($maxBytes / 1048576) . ' MB';
    }
    if (!empty($file['tmp_name']) && is_readable($file['tmp_name']) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
        if (is_string($mime) && !in_array($mime, $allowedMime, true)) {
            return 'ชนิดไฟล์ไม่ถูกต้อง (ระบบตรวจพบ ' . htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') . ')';
        }
    }
    return null;
}

// จำนวนตำแหน่งสูงสุดที่ผู้สมัคร 1 คน (เลขบัตร ปชช.) สมัครได้
const MAX_JOB_APPLICATIONS_PER_PERSON = 3;

/**
 * เงื่อนไขการสมัครงานของผู้สมัคร 1 คน:
 *  - สมัครตำแหน่งเดิมซ้ำไม่ได้
 *  - สมัครได้ไม่เกิน MAX_JOB_APPLICATIONS_PER_PERSON ตำแหน่ง
 * คืน null ถ้าสมัครตำแหน่งนี้ได้ ไม่งั้นคืนข้อความ error
 * (การแก้ไขใบสมัครเดิมไม่ต้องเรียกฟังก์ชันนี้)
 */
function jobApplicationLimitError(PDO $pdo, $nationalId, $jobId)
{
    $dup = $pdo->prepare('SELECT COUNT(*) FROM job_applications WHERE national_id = ? AND job_id = ?');
    $dup->execute([$nationalId, $jobId]);
    if ((int) $dup->fetchColumn() > 0) {
        return 'ท่านได้สมัครตำแหน่งนี้ไว้แล้ว ไม่สามารถสมัครซ้ำได้ (หากต้องการแก้ไขข้อมูล ให้ใช้เมนู "ค้นหาผู้สมัครและตารางสอบ")';
    }
    $total = $pdo->prepare('SELECT COUNT(*) FROM job_applications WHERE national_id = ?');
    $total->execute([$nationalId]);
    if ((int) $total->fetchColumn() >= MAX_JOB_APPLICATIONS_PER_PERSON) {
        return 'ท่านสมัครงานครบ ' . MAX_JOB_APPLICATIONS_PER_PERSON . ' ตำแหน่งแล้ว ไม่สามารถสมัครเพิ่มได้';
    }
    return null;
}

/**
 * (เดิม ข้อ 3 — ปัจจุบันไม่ได้เรียกใช้ เก็บไว้เผื่อเปิดกลับ)
 * ถ้าเลขบัตรนี้มีในตาราง applicants แล้ว วันเดือนปีเกิดที่กรอกมาต้องตรงกัน
 */
function applicantIdentityError(PDO $pdo, $nationalId, $birthDate)
{
    $stmt = $pdo->prepare('SELECT birth_date FROM applicants WHERE national_id = ?');
    $stmt->execute([$nationalId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if (empty($row['birth_date'])) {
        return 'เลขบัตรประชาชนนี้มีข้อมูลในระบบแล้ว แต่ไม่มีวันเดือนปีเกิดบันทึกไว้ กรุณาติดต่อผู้ดูแลระบบ';
    }
    if ($birthDate === '' || $birthDate !== (string) $row['birth_date']) {
        return 'เลขบัตรประชาชนนี้มีข้อมูลในระบบแล้ว กรุณากรอกวันเดือนปีเกิดให้ตรงกับที่เคยลงทะเบียนไว้';
    }
    return null;
}

function syncPreapplicationMatches(PDO $pdo)
{
    $matchStmt = $pdo->query('SELECT pa.national_id, pa.id_card_copy_file, pa.resume_file, pa.email, pa.edu_degree_file, pa.medical_cert_file, pa.marital_status, pa.military_status, pa.profile_photo_file, j.job_id
        FROM preapplications pa
        JOIN jobs j ON j.job_field = pa.job_field AND j.position_level = pa.position_level
        WHERE (j.opening_date IS NULL OR j.opening_date <= CURDATE())
            AND (j.closing_date IS NULL OR j.closing_date >= CURDATE())
            AND NOT EXISTS (SELECT 1 FROM job_applications ja WHERE ja.national_id = pa.national_id AND ja.job_id = j.job_id)
        ORDER BY pa.preapp_id');

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM job_applications WHERE national_id = ?');

    foreach ($matchStmt->fetchAll(PDO::FETCH_ASSOC) as $match) {
        // ไม่สร้างใบสมัครอัตโนมัติเกินโควตาต่อคน
        $countStmt->execute([$match['national_id']]);
        if ((int) $countStmt->fetchColumn() >= MAX_JOB_APPLICATIONS_PER_PERSON) {
            continue;
        }
        $newAppId = nextSequentialId($pdo, 'job_applications', 'app_id');
        $insert = $pdo->prepare("INSERT INTO job_applications (app_id, job_id, national_id, source, id_card_copy_file, resume_file, email, edu_degree_file, medical_cert_file, marital_status, military_status, profile_photo_file)
            VALUES (?, ?, ?, 'preapply', ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([
            $newAppId,
            $match['job_id'],
            $match['national_id'],
            $match['id_card_copy_file'],
            $match['resume_file'],
            $match['email'],
            $match['edu_degree_file'],
            $match['medical_cert_file'],
            $match['marital_status'],
            $match['military_status'],
            $match['profile_photo_file'],
        ]);
    }
}
