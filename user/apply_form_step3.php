<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$jobId = $_GET['job_id'] ?? '';
$stmt = $pdo->prepare('SELECT job_id, title, payment_slip_file FROM jobs WHERE job_id = ?');
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

if (empty($_SESSION['apply']['app_id'])) {
    header('Location: apply_form_step2.php?job_id=' . urlencode($job['job_id']) . $returnParams);
    exit;
}

$uploadErrorMessage = '';
$showIncompleteModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = __DIR__ . '/../assets/uploads/applications/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!empty($_FILES['payment_proof']['name']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $uploadError = uploadValidationError($_FILES['payment_proof']);
        if ($uploadError !== null) {
            // ข้ามไฟล์ที่ไม่รองรับ ข้อมูล/สลิปเดิมยังอยู่
            $uploadErrorMessage = $uploadError;
        } else {
            $existingFile = $_SESSION['apply']['payment_proof_file'] ?? null;
            $ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
            $filename = $_SESSION['apply']['national_id'] . '_payment_proof_' . time() . ($ext !== '' ? '.' . $ext : '');
            storeUploadedFile($_FILES['payment_proof'], $uploadDir . $filename);
            $_SESSION['apply']['payment_proof_file'] = $filename;

            if (!empty($existingFile) && $existingFile !== $filename && file_exists($uploadDir . $existingFile)) {
                unlink($uploadDir . $existingFile);
            }
        }
    }

    if (!empty($_POST['go_back'])) {
        header('Location: apply_form_step2.php?job_id=' . urlencode($job['job_id']) . $returnParams);
        exit;
    }

    // ต้องแนบสลิปแล้ว (มีใน session)
    $step3Complete = !empty($_SESSION['apply']['payment_proof_file']);
    $showIncompleteModal = ($uploadErrorMessage === '' && !$step3Complete);

    if ($uploadErrorMessage === '' && $step3Complete) {
        $appId = $_SESSION['apply']['app_id'];
        $wasEdit = !empty($_SESSION['apply']['is_edit']);
        $newSlip = $_SESSION['apply']['payment_proof_file'] ?? null;

        // ถ้าเป็นการแก้ไข และสลิปเปลี่ยนไปจากเดิม -> รีเซ็ตสถานะกลับเป็น "รอการยืนยัน"
        $resetStatus = false;
        if ($wasEdit) {
            $prevStmt = $pdo->prepare('SELECT payment_proof_file FROM job_applications WHERE app_id = ?');
            $prevStmt->execute([$appId]);
            $prevSlip = $prevStmt->fetchColumn();
            $resetStatus = ((string) ($newSlip ?? '') !== (string) ($prevSlip !== false ? $prevSlip : ''));
        }

        if ($resetStatus) {
            $pdo->prepare('UPDATE job_applications SET payment_proof_file = ?, application_status = NULL, reject_reason = NULL WHERE app_id = ?')
                ->execute([$newSlip, $appId]);
        } else {
            $pdo->prepare('UPDATE job_applications SET payment_proof_file = ? WHERE app_id = ?')
                ->execute([$newSlip, $appId]);
        }

        unset($_SESSION['apply']);

        header('Location: apply_success.php?app_id=' . urlencode($appId) . ($wasEdit ? '&edit=1' : '') . $returnParams);
        exit;
    }
}

$saved = $_SESSION['apply'];
$isEdit = !empty($saved['is_edit']);
$cssVersion = filemtime(__DIR__ . '/../assets/css/style.css');

function uploadLabel3($saved, $key, $default)
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
        <button type="submit" form="applyStep3Form" name="go_back" value="1" class="detail-back-inline" aria-label="กลับหน้าก่อนหน้า">
            <i class="fa-solid fa-reply"></i>
        </button>
        <?php else: ?>
        <a href="../index.php?page=news" class="detail-breadcrumb">
            <i class="fa-solid fa-newspaper"></i>
            <span>ข่าวสารการรับสมัคร</span>
        </a>
        <button type="submit" form="applyStep3Form" name="go_back" value="1" class="detail-back-inline" aria-label="กลับหน้าก่อนหน้า">
            <i class="fa-solid fa-reply"></i>
        </button>
        <?php endif; ?>
    </div>

    <div class="job-detail-panel apply-form-panel">
        <div class="job-detail-title"><?php echo htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'); ?></div>

        <form method="post" enctype="multipart/form-data" id="applyStep3Form">
            <div class="apply-section apply-section-qr">
                <span class="apply-section-label apply-qr-label">ข้อมูลการสมัคร</span>

                <div class="apply-qr-frame">
                    <?php if (!empty($saved['payment_qr_file'])): ?>
                    <img src="../file.php?f=<?php echo rawurlencode($saved['payment_qr_file']); ?>" alt="QR Code ชำระเงินค่าสมัคร" class="apply-qr-image">
                    <?php elseif (!empty($job['payment_slip_file'])): ?>
                    <img src="../assets/uploads/job_postings/<?php echo htmlspecialchars($job['payment_slip_file'], ENT_QUOTES, 'UTF-8'); ?>" alt="QR Code ชำระเงินค่าสมัคร" class="apply-qr-image">
                    <?php else: ?>
                    <span class="apply-qr-placeholder">ยังไม่มี QR Code สำหรับชำระเงิน</span>
                    <?php endif; ?>
                </div>
                <p class="apply-qr-caption">ชำระเงินค่าสมัคร</p>

                <div class="apply-step-nav">
                    <button type="submit" name="go_back" value="1" class="apply-next-btn"><i class="fa-solid fa-arrow-left"></i> ย้อนกลับ</button>

                    <div class="field-group apply-slip-upload">
                        <label class="field-label">สลิปการโอนเงิน</label>
                        <label class="announce-input announce-upload-field">
                            <span class="announce-upload-label"><?php echo uploadLabel3($saved, 'payment_proof_file', 'แนบไฟล์'); ?></span>
                            <input type="file" name="payment_proof" class="announce-upload-native" hidden>
                            <span class="announce-upload-icon"><i class="fa-solid fa-arrow-up"></i></span>
                        </label>
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
    var form = document.getElementById('applyStep3Form');

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
