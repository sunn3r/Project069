<?php
$validPages = ['news', 'jobs', 'apply', 'applicants', 'howto'];
$activeMenu = $_GET['page'] ?? 'news';
if (!in_array($activeMenu, $validPages, true)) {
    $activeMenu = 'news';
}
$pageTitle = 'หน้าหลัก';

$cssVersion = filemtime(__DIR__ . '/assets/css/style.css');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>โรงเรียนสาธิตมหาวิทยาลัยทักษิณ ฝ่ายมัธยม - ระบบรับสมัครงาน</title>
<link rel="stylesheet" href="assets/css/style.css?v=<?php echo $cssVersion; ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <img src="assets/images/logo.png" alt="โลโก้โรงเรียนสาธิตมหาวิทยาลัยทักษิณ" class="site-logo">
        <h1 class="site-title">
            โรงเรียนสาธิตมหาวิทยาลัยทักษิณ<br>
            ฝ่ายมัธยม
        </h1>
    </div>
    <a href="admin/login.php" class="menu-toggle" aria-label="เข้าสู่ระบบผู้ดูแลระบบ">
        <span></span>
        <span></span>
        <span></span>
    </a>
</header>

<nav class="main-nav" id="mainNav">
    <a href="?page=news" class="nav-item <?php echo $activeMenu === 'news' ? 'active' : ''; ?>">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>ข่าวสารการรับสมัคร</span>
    </a>
    <a href="?page=jobs" class="nav-item <?php echo $activeMenu === 'jobs' ? 'active' : ''; ?>">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>ค้นหาตำแหน่งงาน</span>
    </a>
    <a href="?page=apply" class="nav-item <?php echo $activeMenu === 'apply' ? 'active' : ''; ?>">
        <i class="fa-solid fa-file-shield"></i>
        <span>สมัครงานล่วงหน้า</span>
    </a>
    <a href="?page=applicants" class="nav-item <?php echo $activeMenu === 'applicants' ? 'active' : ''; ?>">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>ค้นหาผู้สมัครและตารางสอบ</span>
    </a>
    <a href="?page=howto" class="nav-item <?php echo $activeMenu === 'howto' ? 'active' : ''; ?>">
        <i class="fa-solid fa-book-open"></i>
        <span>วิธีการสมัคร</span>
    </a>
</nav>

<main class="page-content">
    <?php if ($activeMenu === 'news'): ?>
    <?php
    require_once __DIR__ . '/config/db.php';
    $newsJobs = $pdo->query("SELECT job_id, title FROM jobs WHERE (opening_date IS NULL OR opening_date <= CURDATE()) AND (closing_date IS NULL OR closing_date >= CURDATE()) ORDER BY created_at DESC")->fetchAll();
    ?>
    <?php if (count($newsJobs) > 0): ?>
    <div class="job-listing">
        <?php foreach ($newsJobs as $job): ?>
        <div class="job-listing-card">
            <span class="job-listing-title"><?php echo htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="user/job_detail.php?id=<?php echo urlencode($job['job_id']); ?>" class="job-listing-detail-btn">รายละเอียด</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php elseif ($activeMenu === 'jobs'): ?>
    <form class="job-search-bar" action="?page=jobs" method="get">
        <input type="hidden" name="page" value="jobs">
        <div class="field-group">
            <label class="field-label" for="jobsSearchQ">ค้นหาตำแหน่งที่ท่านต้องการ</label>
            <div class="job-search-input">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="jobsSearchQ" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <button type="submit" class="job-search-btn">ค้นหา <i class="fa-solid fa-magnifying-glass"></i></button>
    </form>

    <?php
    require_once __DIR__ . '/config/db.php';
    $jobSearch = trim($_GET['q'] ?? '');
    if ($jobSearch !== '') {
        $jobsStmt = $pdo->prepare("SELECT job_id, title FROM jobs WHERE title LIKE ? AND (opening_date IS NULL OR opening_date <= CURDATE()) AND (closing_date IS NULL OR closing_date >= CURDATE()) ORDER BY created_at DESC");
        $jobsStmt->execute(['%' . $jobSearch . '%']);
    } else {
        $jobsStmt = $pdo->query("SELECT job_id, title FROM jobs WHERE (opening_date IS NULL OR opening_date <= CURDATE()) AND (closing_date IS NULL OR closing_date >= CURDATE()) ORDER BY created_at DESC");
    }
    $openJobs = $jobsStmt->fetchAll();
    ?>
    <?php if (count($openJobs) > 0): ?>
    <div class="job-listing">
        <?php foreach ($openJobs as $job): ?>
        <div class="job-listing-card">
            <span class="job-listing-title"><?php echo htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="user/job_detail.php?id=<?php echo urlencode($job['job_id']); ?>&from=jobs&q=<?php echo urlencode($jobSearch); ?>" class="job-listing-detail-btn">รายละเอียด</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php elseif ($activeMenu === 'apply'): ?>
    <div class="apply-category-bar">ประเภทงาน</div>
    <div class="apply-category-list">
        <a href="user/preapply_form.php?job_field=<?php echo urlencode('งานการสอน'); ?>" class="apply-category-btn">งานการสอน</a>
        <a href="user/preapply_form.php?job_field=<?php echo urlencode('บุคลากรสนับสนุนการศึกษา'); ?>" class="apply-category-btn">บุคลากรสนับสนุนการศึกษา</a>
    </div>
    <?php elseif ($activeMenu === 'applicants'): ?>
    <form class="job-search-bar" action="?page=applicants" method="get">
        <input type="hidden" name="page" value="applicants">
        <div class="field-group">
            <label class="field-label" for="applicantsSearchQ">เลขบัตรประจำตัวผู้สมัคร</label>
            <div class="job-search-input">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="applicantsSearchQ" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <button type="submit" class="job-search-btn applicant-search-btn">ค้นหา <i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
    <div class="applicant-table-header">
        <span>ชื่อ-สกุล</span>
        <span>ตำแหน่งที่ยื่นสมัคร</span>
        <span>ผลการสมัครงาน</span>
        <span>ตารางสอบ</span>
        <span>ผลสอบ</span>
    </div>

    <?php
    require_once __DIR__ . '/config/db.php';
    syncPreapplicationMatches($pdo);
    $applicantSearch = trim($_GET['q'] ?? '');
    $applicantRows = [];
    if ($applicantSearch !== '') {
        $stmt = $pdo->prepare('SELECT a.first_name, a.last_name, j.title, ja.app_id, ja.application_status, ja.reject_reason, ed.schedule_file, ed.score_file, ed.schedule_published, ed.score_published
            FROM applicants a
            JOIN job_applications ja ON ja.national_id = a.national_id
            JOIN jobs j ON j.job_id = ja.job_id
            LEFT JOIN exam_documents ed ON ed.job_id = j.job_id
            WHERE a.national_id = ?
            ORDER BY ja.created_at DESC');
        $stmt->execute([$applicantSearch]);
        $applicantRows = $stmt->fetchAll();
    }

    function applicantStatusBadge($status, $rejectReason = null)
    {
        if ($status === 'ผ่าน') {
            return '<span class="applicant-status-pill applicant-status-pass">ผ่าน</span>';
        }
        if ($status === 'ไม่ผ่าน') {
            $pill = '<span class="applicant-status-pill applicant-status-fail">ไม่ผ่าน</span>';
            if (!empty($rejectReason)) {
                $pill .= '<span class="applicant-reason-text">เหตุผล: ' . htmlspecialchars($rejectReason, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            return $pill;
        }
        if ($status === 'เอกสารไม่ครบถ้วน') {
            $pill = '<span class="applicant-status-pill applicant-status-incomplete">เอกสารไม่ครบถ้วน</span>';
            if (!empty($rejectReason)) {
                $pill .= '<span class="applicant-reason-text">เหตุผล: ' . htmlspecialchars($rejectReason, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            return $pill;
        }
        return '<span class="applicant-status-pill applicant-status-wait">รอการยืนยัน</span>';
    }
    ?>
    <?php if ($applicantSearch !== '' && count($applicantRows) === 0): ?>
    <p class="announce-message announce-message-error">ไม่พบข้อมูลผู้สมัครตามเลขบัตรประชาชนนี้</p>
    <?php endif; ?>
    <?php foreach ($applicantRows as $row): ?>
    <div class="applicant-table-row">
        <span><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8'); ?></span>
        <span>
            <?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?>
            <a href="user/edit_application.php?app_id=<?php echo urlencode($row['app_id']); ?>&q=<?php echo urlencode($applicantSearch); ?>" class="applicant-edit-btn" aria-label="แก้ไขใบสมัคร"><i class="fa-solid fa-pencil"></i></a>
        </span>
        <span>
            <?php echo applicantStatusBadge($row['application_status'], $row['reject_reason']); ?>
        </span>
        <span>
            <?php if (!empty($row['schedule_file']) && !empty($row['schedule_published'])): ?>
            <a href="assets/uploads/exam_documents/<?php echo rawurlencode($row['schedule_file']); ?>" target="_blank" class="exam-status-badge exam-status-scheduled">ประกาศแล้ว <i class="fa-solid fa-eye"></i></a>
            <?php else: ?>
            <span class="exam-status-badge exam-status-pending">ยังไม่ประกาศ</span>
            <?php endif; ?>
        </span>
        <span>
            <?php if (!empty($row['score_file']) && !empty($row['score_published'])): ?>
            <a href="assets/uploads/exam_documents/<?php echo rawurlencode($row['score_file']); ?>" target="_blank" class="exam-status-badge exam-status-done">ผลสอบ <i class="fa-solid fa-eye"></i></a>
            <?php else: ?>
            <span class="exam-status-badge exam-status-none">ยังไม่มีผลสอบ</span>
            <?php endif; ?>
        </span>
    </div>
    <?php endforeach; ?>

    <p class="applicant-search-note">หากผลสมัครงานของท่านอยู่ในสถานะ <b>เอกสารไม่ครบถ้วน</b> ให้ท่านกดแก้ไขผ่านสัญลักษณ์ <i class="fa-solid fa-pencil"></i> ได้ด้านหลังชื่อตำแหน่งที่ยื่นสมัคร</p>

    <?php elseif ($activeMenu === 'howto'): ?>
    <div class="howto-columns">
        <div class="howto-column">
            <h2>วิธีการสมัคร</h2>
            <p>1.กดสมัครงานผ่านหน้าข่าวสารการรับสมัครหรือค้นหางานที่ต้องการที่สมัคร</p>
            <p>2.กรอกข้อมูลและอัปโหลดเอกสารตามที่ประกาศรับ</p>
            <p>3.จ่ายเงินค่าสมัครงาน</p>
            <p>4.กดยืนยัน และรอผลการยืนยัน2-3วัน (หากสมัครในวันหยุดราชการอาจจะนานกว่านี้)</p>
            <p>5.ค้นหาผู้สมัครและตารางสอบเพื่อติดตามตารางสอบและผลการสัมภาษณ์</p>
        </div>
        <div class="howto-column">
            <h2>เอกสารที่ต้องการ</h2>
            <p>1.สำเนาบัตรประชาชน</p>
            <p>2.เรซูเม่(ประวัติการทำงาน,ประวัติการศึกษา,ทักษะความสามารถ)</p>
            <p>3.ข้อมูลการศึกษา(ปริญญา)</p>
            <p>4.ใบรับรองแพทย์</p>
            <p>5.ไฟล์รูปถ่ายหน้าผู้สมัคร</p>
        </div>
    </div>
    <?php else: ?>
    <!-- เนื้อหาของแต่ละหน้าจะถูกใส่ตรงนี้ -->
    <?php endif; ?>
</main>

<script src="assets/js/main.js"></script>
</body>
</html>
