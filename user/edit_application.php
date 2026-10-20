<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// ยืนยันตัวตนด้วยวันเดือนปีเกิดก่อนให้แก้ไขใบสมัคร
// ตั้งเป็น false เพื่อปิดการยืนยันชั่วคราว (กลับไปใช้แค่เลขบัตรประชาชนเหมือนเดิม)
$requireBirthDateVerification = false;

$appId = $_GET['app_id'] ?? $_POST['app_id'] ?? '';
$claimedNationalId = trim($_GET['q'] ?? $_POST['q'] ?? '');

$stmt = $pdo->prepare('SELECT ja.*, a.first_name, a.last_name, a.birth_date, a.national_id, a.age, a.gender, a.ethnicity, a.nationality, a.religion, a.phone
    FROM job_applications ja
    JOIN applicants a ON a.national_id = ja.national_id
    WHERE ja.app_id = ?');
$stmt->execute([$appId]);
$app = $stmt->fetch();

// ต้องรู้เลขบัตรประชาชนของใบสมัครนั้นก่อน (มาจากช่องค้นหา)
if (!$app || $claimedNationalId === '' || $claimedNationalId !== $app['national_id']) {
    header('Location: ../index.php?page=applicants');
    exit;
}

function startApplicationEditSession(array $app)
{
    $_SESSION['apply'] = [
        'app_id' => $app['app_id'],
        'is_edit' => true,
        'job_id' => $app['job_id'],
        'source' => $app['source'],
        'first_name' => $app['first_name'],
        'last_name' => $app['last_name'],
        'birth_date' => $app['birth_date'],
        'national_id' => $app['national_id'],
        'age' => $app['age'],
        'gender' => $app['gender'],
        'ethnicity' => $app['ethnicity'],
        'nationality' => $app['nationality'],
        'religion' => $app['religion'],
        'phone' => $app['phone'],
        'id_card_copy_file' => $app['id_card_copy_file'],
        'resume_file' => $app['resume_file'],
        'email' => $app['email'],
        'edu_degree_file' => $app['edu_degree_file'],
        'medical_cert_file' => $app['medical_cert_file'],
        'marital_status' => $app['marital_status'],
        'military_status' => $app['military_status'],
        'profile_photo_file' => $app['profile_photo_file'],
        'payment_proof_file' => $app['payment_proof_file'],
        'payment_qr_file' => $app['payment_qr_file'],
    ];
}

// ปิดการยืนยันวันเกิด: เข้าหน้าแก้ไขได้เลย (เหมือนพฤติกรรมเดิม)
if (!$requireBirthDateVerification) {
    startApplicationEditSession($app);
    header('Location: apply_form.php?job_id=' . urlencode($app['job_id']) . '&from=search&q=' . urlencode($claimedNationalId));
    exit;
}

// ข้อ 1: ยืนยันตัวตนด้วยวันเดือนปีเกิด ก่อนจะให้แก้ไขใบสมัคร
$verifyError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $birthDate = trim($_POST['birth_date'] ?? '');
    if (empty($app['birth_date'])) {
        $verifyError = 'ใบสมัครนี้ไม่มีวันเดือนปีเกิดบันทึกไว้ในระบบ กรุณาติดต่อผู้ดูแลระบบเพื่อแก้ไข';
    } elseif ($birthDate === '' || $birthDate !== (string) $app['birth_date']) {
        $verifyError = 'วันเดือนปีเกิดไม่ตรงกับข้อมูลในระบบ';
    } else {
        startApplicationEditSession($app);
        header('Location: apply_form.php?job_id=' . urlencode($app['job_id']) . '&from=search&q=' . urlencode($claimedNationalId));
        exit;
    }
}

$cssVersion = filemtime(__DIR__ . '/../assets/css/style.css');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ยืนยันตัวตนก่อนแก้ไขใบสมัคร - โรงเรียนสาธิตมหาวิทยาลัยทักษิณ ฝ่ายมัธยม</title>
<link rel="stylesheet" href="../assets/css/style.css?v=<?php echo $cssVersion; ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="detail-topbar">
    <a href="../admin/login.php" class="menu-toggle" aria-label="เข้าสู่ระบบผู้ดูแลระบบ">
        <span></span>
        <span></span>
        <span></span>
    </a>
</div>

<main class="page-content">
    <a href="../index.php?page=applicants&q=<?php echo urlencode($claimedNationalId); ?>" class="detail-breadcrumb">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>ค้นหาผู้สมัครและตารางสอบ</span>
    </a>

    <div class="job-detail-panel apply-form-panel">
        <div class="job-detail-title">ยืนยันตัวตนก่อนแก้ไขใบสมัคร</div>
        <p class="applicant-search-note">เพื่อความปลอดภัยของข้อมูล กรุณากรอกวันเดือนปีเกิดของผู้สมัคร (เลขที่ใบสมัคร <?php echo htmlspecialchars($app['app_id'], ENT_QUOTES, 'UTF-8'); ?>) ให้ตรงกับที่ลงทะเบียนไว้</p>

        <?php if ($verifyError !== ''): ?>
        <p class="announce-message announce-message-error"><?php echo htmlspecialchars($verifyError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="app_id" value="<?php echo htmlspecialchars($app['app_id'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($claimedNationalId, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="apply-section">
                <div class="apply-section-fields">
                    <div class="field-group">
                        <label class="field-label" for="verifyBirthDate">วัน เดือน ปีเกิด</label>
                        <input type="date" id="verifyBirthDate" name="birth_date" class="announce-input" required>
                    </div>
                </div>
                <div class="apply-next-wrap">
                    <button type="submit" class="apply-next-btn">ยืนยัน <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>
        </form>
    </div>
</main>

</body>
</html>
