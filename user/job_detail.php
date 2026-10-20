<?php
require_once __DIR__ . '/../config/db.php';

$jobId = $_GET['id'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM jobs WHERE job_id = ?');
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    header('Location: ../index.php?page=jobs');
    exit;
}

$fromSearch = ($_GET['from'] ?? '') === 'search';
$fromExam = ($_GET['from'] ?? '') === 'examresults';
$fromJobs = ($_GET['from'] ?? '') === 'jobs';
$searchQ = $_GET['q'] ?? '';
$returnParams = $fromSearch ? '&from=search&q=' . urlencode($searchQ) : '';

$cssVersion = filemtime(__DIR__ . '/../assets/css/style.css');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'); ?> - โรงเรียนสาธิตมหาวิทยาลัยทักษิณ ฝ่ายมัธยม</title>
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
    <?php if ($fromExam): ?>
    <a href="../admin/index.php?page=examresults&q=<?php echo urlencode($searchQ); ?>" class="detail-breadcrumb">
        <i class="fa-solid fa-reply"></i>
        <span>ประกาศตารางสอบและผลสอบ</span>
    </a>
    <?php elseif ($fromJobs): ?>
    <a href="../index.php?page=jobs&q=<?php echo urlencode($searchQ); ?>" class="detail-breadcrumb">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>ค้นหาตำแหน่งงาน</span>
    </a>
    <?php elseif ($fromSearch): ?>
    <a href="../index.php?page=applicants&q=<?php echo urlencode($searchQ); ?>" class="detail-breadcrumb">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>ค้นหาผู้สมัครและตารางสอบ</span>
    </a>
    <?php else: ?>
    <a href="../index.php?page=news" class="detail-breadcrumb">
        <i class="fa-solid fa-newspaper"></i>
        <span>ข่าวสารการรับสมัคร</span>
    </a>
    <?php endif; ?>

    <div class="job-detail-panel">
        <div class="job-detail-title"><?php echo htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'); ?></div>

        <div class="job-detail-rows">
            <div class="job-detail-row">
                <span class="job-detail-label">รหัสตำแหน่ง</span>
                <span class="job-detail-value"><?php echo htmlspecialchars($job['job_id'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="job-detail-row">
                <span class="job-detail-label">ประกาศสมัครงาน</span>
                <span class="job-detail-value">
                    <?php if (!empty($job['posting_file'])): ?>
                    <a href="../assets/uploads/job_postings/<?php echo htmlspecialchars($job['posting_file'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">[ประกาศ]</a>
                    <?php else: ?>
                    -
                    <?php endif; ?>
                </span>
            </div>
            <div class="job-detail-row">
                <span class="job-detail-label">วันที่เปิดรับ</span>
                <span class="job-detail-value"><?php echo !empty($job['opening_date']) ? date('d-m-Y', strtotime($job['opening_date'])) : '-'; ?></span>
            </div>
            <div class="job-detail-row">
                <span class="job-detail-label">วันที่ปิดรับ</span>
                <span class="job-detail-value"><?php echo !empty($job['closing_date']) ? date('d-m-Y', strtotime($job['closing_date'])) : '-'; ?></span>
            </div>
            <div class="job-detail-row">
                <span class="job-detail-label">จำนวนที่เปิดรับ</span>
                <span class="job-detail-value"><?php echo (int) $job['openings']; ?> อัตรา</span>
            </div>
            <div class="job-detail-row">
                <span class="job-detail-label">ประเภทสายงาน</span>
                <span class="job-detail-value"><?php echo htmlspecialchars($job['job_field'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="job-detail-row">
                <span class="job-detail-label">ประเภทบุคลากร</span>
                <span class="job-detail-value"><?php echo htmlspecialchars($job['staff_type'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="job-detail-row">
                <span class="job-detail-label">เงินเดือน</span>
                <span class="job-detail-value"><?php echo htmlspecialchars($job['salary'], ENT_QUOTES, 'UTF-8'); ?> บาท</span>
            </div>
            <div class="job-detail-row">
                <span class="job-detail-label">ตำแหน่ง</span>
                <span class="job-detail-value"><?php echo htmlspecialchars($job['position_level'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="job-detail-row">
                <span class="job-detail-label">ระดับวุฒิการศึกษา</span>
                <span class="job-detail-value"><?php echo htmlspecialchars($job['education_level'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="job-detail-row">
                <span class="job-detail-label">คำอธิบายเพิ่มเติม</span>
                <span class="job-detail-value job-detail-about"><?php echo nl2br(htmlspecialchars($job['about'], ENT_QUOTES, 'UTF-8')); ?></span>
            </div>
        </div>

        <?php if ($fromExam): ?>
        <a href="../admin/index.php?page=examresults&q=<?php echo urlencode($searchQ); ?>" class="job-detail-back" aria-label="กลับหน้าประกาศตารางสอบและผลสอบ">
            <i class="fa-solid fa-reply"></i>
        </a>
        <?php elseif ($fromJobs): ?>
        <a href="../index.php?page=jobs&q=<?php echo urlencode($searchQ); ?>" class="job-detail-back" aria-label="กลับหน้าค้นหาตำแหน่งงาน">
            <i class="fa-solid fa-reply"></i>
        </a>
        <?php elseif ($fromSearch): ?>
        <a href="../index.php?page=applicants&q=<?php echo urlencode($searchQ); ?>" class="job-detail-back" aria-label="กลับหน้าค้นหาผู้สมัครและตารางสอบ">
            <i class="fa-solid fa-reply"></i>
        </a>
        <?php else: ?>
        <a href="../index.php?page=jobs" class="job-detail-back" aria-label="กลับหน้ารายการตำแหน่งงาน">
            <i class="fa-solid fa-reply"></i>
        </a>
        <?php endif; ?>
        <?php if (!$fromExam): ?>
        <a href="apply_form.php?job_id=<?php echo urlencode($job['job_id']) . $returnParams; ?>" class="job-detail-apply-btn">ยื่นหลักฐานการสมัคร</a>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
