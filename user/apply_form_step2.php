<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$jobId = $_GET['job_id'] ?? '';
$stmt = $pdo->prepare('SELECT job_id, title FROM jobs WHERE job_id = ?');
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    header('Location: ../index.php?page=jobs');
    exit;
}

$fromSearch = ($_GET['from'] ?? '') === 'search';
$searchQ = $_GET['q'] ?? '';
$returnParams = $fromSearch ? '&from=search&q=' . urlencode($searchQ) : '';

if (empty($_SESSION['apply']['national_id']) || ($_SESSION['apply']['job_id'] ?? '') !== $job['job_id']) {
    header('Location: apply_form.php?job_id=' . urlencode($job['job_id']) . $returnParams);
    exit;
}

$uploadFieldMap = [
    'id_card_copy' => 'id_card_copy_file',
    'resume' => 'resume_file',
    'edu_degree' => 'edu_degree_file',
    'medical_cert' => 'medical_cert_file',
    'profile_photo' => 'profile_photo_file',
];
$textFields = ['email', 'marital_status', 'military_status'];
$uploadErrorMessage = '';
$showIncompleteModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // เช็คกติกา (สมัครซ้ำ / ครบ 3 ตำแหน่ง) ตั้งแต่ก่อนอัปไฟล์ กันไฟล์ลอยบนเซิร์ฟเวอร์
    // ข้ามถ้าเป็นการแก้ไขใบสมัครเดิม
    if (empty($_SESSION['apply']['is_edit']) && empty($_SESSION['apply']['app_id'])) {
        $limitError = jobApplicationLimitError($pdo, $_SESSION['apply']['national_id'], $job['job_id']);
        if ($limitError !== null) {
            http_response_code(409);
            die($limitError);
        }
    }

    $uploadDir = __DIR__ . '/../assets/uploads/applications/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    foreach ($textFields as $field) {
        $_SESSION['apply'][$field] = trim($_POST[$field] ?? '');
    }

    foreach ($uploadFieldMap as $inputName => $sessionKey) {
        if (!empty($_FILES[$inputName]['name']) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
            $uploadError = uploadValidationError($_FILES[$inputName]);
            if ($uploadError !== null) {
                // ข้ามเฉพาะไฟล์ที่ไม่รองรับ ข้อมูลอื่น + ไฟล์ที่ถูกต้องยังอยู่ครบ
                $uploadErrorMessage = $uploadError;
                continue;
            }
            $existingFile = $_SESSION['apply'][$sessionKey] ?? null;
            $ext = pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION);
            $filename = $_SESSION['apply']['national_id'] . '_' . $inputName . '_' . time() . ($ext !== '' ? '.' . $ext : '');
            storeUploadedFile($_FILES[$inputName], $uploadDir . $filename);
            $_SESSION['apply'][$sessionKey] = $filename;

            if (!empty($existingFile) && $existingFile !== $filename && file_exists($uploadDir . $existingFile)) {
                unlink($uploadDir . $existingFile);
            }
        }
    }

    if (!empty($_POST['go_back'])) {
        header('Location: apply_form.php?job_id=' . urlencode($job['job_id']) . $returnParams);
        exit;
    }

    // กรอกข้อมูล + แนบไฟล์ครบทุกช่องหรือยัง (ไฟล์ถือว่าครบถ้ามีใน session แล้ว)
    $step2Complete = hasAllFields($_SESSION['apply'], ['email', 'marital_status', 'military_status'])
        && !empty($_SESSION['apply']['id_card_copy_file'])
        && !empty($_SESSION['apply']['resume_file'])
        && !empty($_SESSION['apply']['edu_degree_file'])
        && !empty($_SESSION['apply']['medical_cert_file'])
        && !empty($_SESSION['apply']['profile_photo_file']);
    $showIncompleteModal = ($uploadErrorMessage === '' && !$step2Complete);

    // ไฟล์ผิด/กรอกไม่ครบ -> ไม่บันทึก ไม่ไปหน้าถัดไป กลับไปแสดงฟอร์มพร้อม pop-up แจ้งเตือน
    if ($uploadErrorMessage === '' && $step2Complete):

    $applyData = $_SESSION['apply'];

    // อ่านค่าเดิมของใบสมัคร "ก่อน" อัปเดตตาราง applicants (ไม่งั้นข้อมูลส่วนตัวจะถูกทับก่อนเทียบ)
    $orig = null;
    if (!empty($applyData['app_id'])) {
        $origStmt = $pdo->prepare('SELECT ja.id_card_copy_file, ja.resume_file, ja.email, ja.edu_degree_file, ja.medical_cert_file, ja.marital_status, ja.military_status, ja.profile_photo_file, ja.application_status, ja.reject_reason,
            a.first_name, a.last_name, a.birth_date, a.age, a.gender, a.ethnicity, a.nationality, a.religion, a.phone
            FROM job_applications ja JOIN applicants a ON a.national_id = ja.national_id WHERE ja.app_id = ?');
        $origStmt->execute([$applyData['app_id']]);
        $orig = $origStmt->fetch();
    }

    $upsert = $pdo->prepare('INSERT INTO applicants (national_id, first_name, last_name, birth_date, age, gender, ethnicity, nationality, religion, phone)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), birth_date = VALUES(birth_date), age = VALUES(age), gender = VALUES(gender), ethnicity = VALUES(ethnicity), nationality = VALUES(nationality), religion = VALUES(religion), phone = VALUES(phone)');
    $upsert->execute([
        $applyData['national_id'],
        $applyData['first_name'],
        $applyData['last_name'],
        $applyData['birth_date'] !== '' ? $applyData['birth_date'] : null,
        is_numeric($applyData['age'] ?? '') ? (int) $applyData['age'] : null,
        $applyData['gender'] !== '' ? $applyData['gender'] : null,
        $applyData['ethnicity'] !== '' ? $applyData['ethnicity'] : null,
        $applyData['nationality'] !== '' ? $applyData['nationality'] : null,
        $applyData['religion'] !== '' ? $applyData['religion'] : null,
        $applyData['phone'] !== '' ? $applyData['phone'] : null,
    ]);

    if (!empty($applyData['app_id'])) {
        $compareFields = ['id_card_copy_file', 'resume_file', 'email', 'edu_degree_file', 'medical_cert_file', 'marital_status', 'military_status', 'profile_photo_file',
            'first_name', 'last_name', 'birth_date', 'age', 'gender', 'ethnicity', 'nationality', 'religion', 'phone'];
        $hasChanges = !$orig;
        foreach ($compareFields as $field) {
            if ((string) ($applyData[$field] ?? '') !== (string) ($orig[$field] ?? '')) {
                $hasChanges = true;
                break;
            }
        }

        $newAppStatus = $hasChanges ? null : $orig['application_status'];
        $newRejectReason = $hasChanges ? null : $orig['reject_reason'];

        $updateApp = $pdo->prepare('UPDATE job_applications SET id_card_copy_file = ?, resume_file = ?, email = ?, edu_degree_file = ?, medical_cert_file = ?, marital_status = ?, military_status = ?, profile_photo_file = ?, application_status = ?, reject_reason = ? WHERE app_id = ?');
        $updateApp->execute([
            $applyData['id_card_copy_file'] ?? null,
            $applyData['resume_file'] ?? null,
            $applyData['email'] ?? null,
            $applyData['edu_degree_file'] ?? null,
            $applyData['medical_cert_file'] ?? null,
            $applyData['marital_status'] ?? null,
            $applyData['military_status'] ?? null,
            $applyData['profile_photo_file'] ?? null,
            $newAppStatus,
            $newRejectReason,
            $applyData['app_id'],
        ]);
    } else {
        $newAppId = nextSequentialId($pdo, 'job_applications', 'app_id');
        $insertApp = $pdo->prepare('INSERT INTO job_applications (app_id, job_id, national_id, id_card_copy_file, resume_file, email, edu_degree_file, medical_cert_file, marital_status, military_status, profile_photo_file)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insertApp->execute([
            $newAppId,
            $job['job_id'],
            $applyData['national_id'],
            $applyData['id_card_copy_file'] ?? null,
            $applyData['resume_file'] ?? null,
            $applyData['email'] ?? null,
            $applyData['edu_degree_file'] ?? null,
            $applyData['medical_cert_file'] ?? null,
            $applyData['marital_status'] ?? null,
            $applyData['military_status'] ?? null,
            $applyData['profile_photo_file'] ?? null,
        ]);
        $_SESSION['apply']['app_id'] = $newAppId;
    }

    header('Location: apply_form_step3.php?job_id=' . urlencode($job['job_id']) . $returnParams);
    exit;

    endif; // $uploadErrorMessage === '' && $step2Complete
}

$saved = $_SESSION['apply'];
$isEdit = !empty($saved['is_edit']);
$nextAppId = $saved['app_id'] ?? nextSequentialId($pdo, 'job_applications', 'app_id');
$cssVersion = filemtime(__DIR__ . '/../assets/css/style.css');

function av2($saved, $key)
{
    return htmlspecialchars($saved[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

function uploadLabel($saved, $key, $default)
{
    return htmlspecialchars($saved[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ยื่นหลักฐานการสมัคร - <?php echo htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'); ?></title>
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
        <?php if ($fromSearch): ?>
        <a href="../index.php?page=applicants&q=<?php echo urlencode($searchQ); ?>" class="detail-breadcrumb">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span>ค้นหาผู้สมัครและตารางสอบ</span>
        </a>
        <button type="submit" form="applyStep2Form" name="go_back" value="1" class="detail-back-inline" aria-label="กลับหน้าก่อนหน้า">
            <i class="fa-solid fa-reply"></i>
        </button>
        <?php else: ?>
        <a href="../index.php?page=news" class="detail-breadcrumb">
            <i class="fa-solid fa-newspaper"></i>
            <span>ข่าวสารการรับสมัคร</span>
        </a>
        <button type="submit" form="applyStep2Form" name="go_back" value="1" class="detail-back-inline" aria-label="กลับหน้าก่อนหน้า">
            <i class="fa-solid fa-reply"></i>
        </button>
        <?php endif; ?>
    </div>

    <div class="job-detail-panel apply-form-panel">
        <div class="job-detail-title"><?php echo htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'); ?></div>

        <form method="post" enctype="multipart/form-data" id="applyStep2Form">
            <div class="apply-section apply-section-large">
                <div class="apply-section-label-col">
                    <span class="apply-section-label">ข้อมูลการสมัคร</span>
                    <div class="apply-code-badge">
                        <span>เลขที่ใบสมัคร</span>
                        <span><?php echo htmlspecialchars($nextAppId, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <div class="apply-section-body">
                    <div class="apply-grid">
                        <div class="field-group">
                            <label class="field-label">สำเนาบัตรประชาชน</label>
                            <label class="announce-input announce-upload-field">
                                <span class="announce-upload-label"><?php echo uploadLabel($saved, 'id_card_copy_file', 'แนบไฟล์'); ?></span>
                                <input type="file" name="id_card_copy" class="announce-upload-native" hidden>
                                <span class="announce-upload-icon"><i class="fa-solid fa-arrow-up"></i></span>
                            </label>
                        </div>

                        <div class="field-group">
                            <label class="field-label">เรซูเม่</label>
                            <label class="announce-input announce-upload-field">
                                <span class="announce-upload-label"><?php echo uploadLabel($saved, 'resume_file', 'แนบไฟล์'); ?></span>
                                <input type="file" name="resume" class="announce-upload-native" hidden>
                                <span class="announce-upload-icon"><i class="fa-solid fa-arrow-up"></i></span>
                            </label>
                        </div>

                        <div class="field-group">
                            <label class="field-label" for="email">อีเมล</label>
                            <input type="text" id="email" name="email" class="announce-input" value="<?php echo av2($saved, 'email'); ?>">
                        </div>

                        <div class="field-group">
                            <label class="field-label">ข้อมูลการศึกษา(ปริญญา)</label>
                            <label class="announce-input announce-upload-field">
                                <span class="announce-upload-label"><?php echo uploadLabel($saved, 'edu_degree_file', 'แนบไฟล์'); ?></span>
                                <input type="file" name="edu_degree" class="announce-upload-native" hidden>
                                <span class="announce-upload-icon"><i class="fa-solid fa-arrow-up"></i></span>
                            </label>
                        </div>

                        <div class="field-group">
                            <label class="field-label">ใบรับรองแพทย์</label>
                            <label class="announce-input announce-upload-field">
                                <span class="announce-upload-label"><?php echo uploadLabel($saved, 'medical_cert_file', 'แนบไฟล์'); ?></span>
                                <input type="file" name="medical_cert" class="announce-upload-native" hidden>
                                <span class="announce-upload-icon"><i class="fa-solid fa-arrow-up"></i></span>
                            </label>
                        </div>

                        <div class="field-group">
                            <label class="field-label" for="maritalStatus">สถานะภาพ</label>
                            <select id="maritalStatus" name="marital_status" class="announce-input">
                                <option value="">เลือกสถานะภาพ</option>
                                <?php foreach (['โสด', 'สมรส', 'หม้าย', 'แยกกันอยู่'] as $maritalOption): ?>
                                <option value="<?php echo $maritalOption; ?>" <?php echo av2($saved, 'marital_status') === $maritalOption ? 'selected' : ''; ?>><?php echo $maritalOption; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field-group">
                            <label class="field-label" for="militaryStatus">สถานะทางทหาร(ถ้ามี)</label>
                            <input type="text" id="militaryStatus" name="military_status" class="announce-input" value="<?php echo av2($saved, 'military_status'); ?>">
                        </div>

                        <div class="field-group">
                            <label class="field-label">ไฟล์รูปถ่ายหน้าผู้สมัคร</label>
                            <label class="announce-input announce-upload-field">
                                <span class="announce-upload-label"><?php echo uploadLabel($saved, 'profile_photo_file', 'แนบไฟล์'); ?></span>
                                <input type="file" name="profile_photo" class="announce-upload-native" hidden>
                                <span class="announce-upload-icon"><i class="fa-solid fa-arrow-up"></i></span>
                            </label>
                        </div>
                    </div>

                    <div class="apply-step-nav">
                        <button type="submit" name="go_back" value="1" class="apply-next-btn"><i class="fa-solid fa-arrow-left"></i> ย้อนกลับ</button>
                        <button type="submit" class="apply-next-btn">หน้าต่อไป <i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>
        </form>

        <button type="button" class="job-detail-apply-btn" id="submitApplicationBtn"><?php echo $isEdit ? 'บันทึกการแก้ไข' : 'ส่งหลักฐานการสมัคร'; ?></button>
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
        <p class="incomplete-modal-message"><?php echo $isEdit ? 'ยืนยันการแก้ไขข้อมูล' : 'กรุณาตรวจสอบข้อมูลของท่าน<br>ก่อนที่จะกดยืนยัน'; ?></p>
        <div class="confirm-modal-actions">
            <button type="button" class="incomplete-modal-close-btn" id="confirmModalCancel">ย้อนกลับ</button>
            <button type="button" class="confirm-modal-confirm-btn" id="confirmModalConfirm">ยืนยันการส่ง</button>
        </div>
    </div>
</div>

<?php if ($uploadErrorMessage !== ''): ?>
<div class="incomplete-modal-overlay open" id="uploadErrorModal">
    <div class="incomplete-modal-box">
        <p class="incomplete-modal-message"><?php echo htmlspecialchars($uploadErrorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <button type="button" class="incomplete-modal-close-btn" id="uploadErrorModalClose">ย้อนกลับ</button>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.announce-upload-field').forEach(function (field) {
        var fileInput = field.querySelector('.announce-upload-native');
        var label = field.querySelector('.announce-upload-label');
        var defaultText = label.textContent;
        fileInput.addEventListener('change', function () {
            label.textContent = fileInput.files.length ? fileInput.files[0].name : defaultText;
        });
    });

    var submitBtn = document.getElementById('submitApplicationBtn');
    var incompleteModal = document.getElementById('incompleteModal');
    var incompleteModalClose = document.getElementById('incompleteModalClose');
    var confirmModal = document.getElementById('confirmModal');
    var confirmModalCancel = document.getElementById('confirmModalCancel');
    var confirmModalConfirm = document.getElementById('confirmModalConfirm');
    var form = document.getElementById('applyStep2Form');

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            var incomplete = false;
            document.querySelectorAll('.announce-input').forEach(function (field) {
                if (field.tagName === 'LABEL') {
                    var fileInput = field.querySelector('.announce-upload-native');
                    var labelSpan = field.querySelector('.announce-upload-label');
                    var hasExistingFile = labelSpan && labelSpan.textContent.trim() !== 'แนบไฟล์';
                    if (fileInput && fileInput.files.length === 0 && !hasExistingFile) {
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

    var uploadErrorModal = document.getElementById('uploadErrorModal');
    var uploadErrorModalClose = document.getElementById('uploadErrorModalClose');
    if (uploadErrorModalClose) {
        uploadErrorModalClose.addEventListener('click', function () {
            uploadErrorModal.classList.remove('open');
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
