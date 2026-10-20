<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$jobField = $_GET['job_field'] ?? '';

if ($jobField === '') {
    header('Location: ../index.php?page=apply');
    exit;
}

$step1Fields = ['first_name', 'last_name', 'birth_date', 'national_id', 'age', 'gender', 'ethnicity', 'nationality', 'religion', 'phone'];
$step1Required = ['first_name', 'last_name', 'birth_date', 'national_id', 'gender', 'ethnicity', 'nationality', 'religion', 'phone'];
$applyError = '';
$showIncompleteModal = false;
$minBirthDate = date('Y-m-d', strtotime('-60 years'));
$maxBirthDate = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    foreach ($step1Fields as $field) {
        $data[$field] = trim($_POST[$field] ?? '');
    }

    if (!hasAllFields($data, $step1Required)) {
        $showIncompleteModal = true;
    } elseif (!preg_match('/^\d{13}$/', $data['national_id'])) {
        $applyError = 'กรุณากรอกเลขบัตรประชาชนให้ถูกต้อง (ตัวเลข 13 หลัก)';
    } elseif ($data['birth_date'] !== '' && ($data['birth_date'] < $minBirthDate || $data['birth_date'] > $maxBirthDate)) {
        $applyError = 'กรุณากรอกวันเดือนปีเกิดย้อนหลังไม่เกิน 60 ปี และไม่เกินวันปัจจุบัน';
    } else {
        $_SESSION['preapply'] = $data;
        $_SESSION['preapply']['job_field'] = $jobField;
        header('Location: preapply_form_step2.php?job_field=' . urlencode($jobField));
        exit;
    }
}

$saved = ($_SESSION['preapply'] ?? []);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saved = array_merge($saved, $data); // คงค่าที่เพิ่งกรอกไว้ตอนแสดง pop-up
}
$cssVersion = filemtime(__DIR__ . '/../assets/css/style.css');

function pav($saved, $key)
{
    return htmlspecialchars($saved[$key] ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สมัครงานล่วงหน้า - <?php echo htmlspecialchars($jobField, ENT_QUOTES, 'UTF-8'); ?></title>
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
    <div class="detail-breadcrumb-row">
        <span class="preapply-badge">
            <i class="fa-solid fa-file-shield"></i>
            <span>ลงสมัครล่วงหน้า</span>
        </span>
        <a href="../index.php?page=apply" class="detail-back-inline" aria-label="กลับหน้าเลือกตำแหน่ง">
            <i class="fa-solid fa-reply"></i>
        </a>
    </div>

    <div class="job-detail-panel apply-form-panel">
        <div class="job-detail-title">สมัครงานล่วงหน้า ประเภทสายงาน <?php echo htmlspecialchars($jobField, ENT_QUOTES, 'UTF-8'); ?></div>

        <?php if ($applyError !== ''): ?>
        <p class="announce-message announce-message-error"><?php echo htmlspecialchars($applyError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form method="post" id="preapplyStep1Form">
            <div class="apply-section">
                <span class="apply-section-label">ชื่อ-สกุล</span>
                <div class="apply-section-fields">
                    <div class="field-group">
                        <label class="field-label" for="firstName">ชื่อ</label>
                        <input type="text" id="firstName" name="first_name" class="announce-input" value="<?php echo pav($saved, 'first_name'); ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="lastName">สกุล</label>
                        <input type="text" id="lastName" name="last_name" class="announce-input" value="<?php echo pav($saved, 'last_name'); ?>">
                    </div>
                </div>
            </div>

            <div class="apply-section apply-section-large">
                <span class="apply-section-label">ข้อมูลส่วนตัว</span>
                <div class="apply-section-body">
                    <div class="apply-grid">
                        <div class="field-group">
                            <label class="field-label" for="birthDate">วัน เดือน ปีเกิด</label>
                            <input type="date" id="birthDate" name="birth_date" class="announce-input" value="<?php echo pav($saved, 'birth_date'); ?>" min="<?php echo $minBirthDate; ?>" max="<?php echo $maxBirthDate; ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="nationalId">เลขบัตรประชาชน</label>
                            <input type="text" id="nationalId" name="national_id" class="announce-input" value="<?php echo pav($saved, 'national_id'); ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="age">อายุ</label>
                            <input type="text" id="age" name="age" class="announce-input" value="<?php echo pav($saved, 'age'); ?>" readonly>
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="gender">เพศ</label>
                            <input type="text" id="gender" name="gender" class="announce-input" value="<?php echo pav($saved, 'gender'); ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="ethnicity">เชื้อชาติ</label>
                            <input type="text" id="ethnicity" name="ethnicity" class="announce-input" value="<?php echo pav($saved, 'ethnicity'); ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="nationality">สัญชาติ</label>
                            <input type="text" id="nationality" name="nationality" class="announce-input" value="<?php echo pav($saved, 'nationality'); ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="religion">ศาสนา</label>
                            <input type="text" id="religion" name="religion" class="announce-input" value="<?php echo pav($saved, 'religion'); ?>">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="phone">เบอร์โทร</label>
                            <input type="text" id="phone" name="phone" class="announce-input" value="<?php echo pav($saved, 'phone'); ?>">
                        </div>
                    </div>
                    <div class="apply-next-wrap">
                        <button type="submit" class="apply-next-btn">หน้าต่อไป <i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>
        </form>

        <button type="button" class="job-detail-apply-btn" id="submitApplicationBtn">ส่งหลักฐานการสมัคร</button>
    </div>
</main>

<div class="incomplete-modal-overlay<?php echo $showIncompleteModal ? ' open' : ''; ?>" id="incompleteModal">
    <div class="incomplete-modal-box">
        <p class="incomplete-modal-message">ท่านยังกรอกข้อมูลไม่ครบ</p>
        <button type="button" class="incomplete-modal-close-btn" id="incompleteModalClose">ย้อนกลับ</button>
    </div>
</div>

<div class="incomplete-modal-overlay" id="confirmModal">
    <div class="incomplete-modal-box">
        <p class="incomplete-modal-message">กรุณาตรวจสอบข้อมูลของท่าน<br>ก่อนที่จะกดยืนยัน</p>
        <div class="confirm-modal-actions">
            <button type="button" class="incomplete-modal-close-btn" id="confirmModalCancel">ย้อนกลับ</button>
            <button type="button" class="confirm-modal-confirm-btn" id="confirmModalConfirm">ยืนยันการส่ง</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var submitBtn = document.getElementById('submitApplicationBtn');
    var incompleteModal = document.getElementById('incompleteModal');
    var incompleteModalClose = document.getElementById('incompleteModalClose');
    var confirmModal = document.getElementById('confirmModal');
    var confirmModalCancel = document.getElementById('confirmModalCancel');
    var confirmModalConfirm = document.getElementById('confirmModalConfirm');
    var form = document.getElementById('preapplyStep1Form');

    var birthDateInput = document.getElementById('birthDate');
    var ageInput = document.getElementById('age');
    function calcAge() {
        if (!birthDateInput.value) {
            return;
        }
        var birthDate = new Date(birthDateInput.value);
        var today = new Date();
        var age = today.getFullYear() - birthDate.getFullYear();
        var monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        ageInput.value = age >= 0 ? age : '';
    }
    if (birthDateInput && ageInput) {
        birthDateInput.addEventListener('change', calcAge);
        calcAge();
    }

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            var incomplete = false;
            document.querySelectorAll('.announce-input').forEach(function (field) {
                if (field.tagName === 'LABEL') {
                    var fileInput = field.querySelector('.announce-upload-native');
                    if (fileInput && fileInput.files.length === 0) {
                        incomplete = true;
                    }
                } else if (field.value.trim() === '') {
                    incomplete = true;
                }
            });
            if (incomplete) {
                incompleteModal.classList.add('open');
            } else {
                confirmModal.classList.add('open');
            }
        });
    }

    if (incompleteModalClose) {
        incompleteModalClose.addEventListener('click', function () {
            incompleteModal.classList.remove('open');
        });
    }

    if (confirmModalCancel) {
        confirmModalCancel.addEventListener('click', function () {
            confirmModal.classList.remove('open');
        });
    }

    if (confirmModalConfirm) {
        confirmModalConfirm.addEventListener('click', function () {
            confirmModal.classList.remove('open');
            form.submit();
        });
    }
});
</script>

</body>
</html>
