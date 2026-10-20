<?php
$appId = $_GET['app_id'] ?? '';
$fromSearch = ($_GET['from'] ?? '') === 'search';
$isEdit = !empty($_GET['edit']);
$searchQ = $_GET['q'] ?? '';
$cssVersion = filemtime(__DIR__ . '/../assets/css/style.css');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ส่งใบสมัครสำเร็จ - โรงเรียนสาธิตมหาวิทยาลัยทักษิณ ฝ่ายมัธยม</title>
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
    <?php if ($fromSearch): ?>
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

    <div class="apply-success-panel">
        <p class="apply-success-message"><?php echo $isEdit ? 'ท่านแก้ไขข้อมูลการสมัครงานสำเร็จแล้ว' : 'ท่านกรอกใบสมัครงานสำเร็จแล้ว'; ?><br>กรุณารอผลการยืนยัน !!</p>
        <?php if ($fromSearch): ?>
        <a href="../index.php?page=applicants&q=<?php echo urlencode($searchQ); ?>" class="apply-success-home-btn">กลับสู่หน้าค้นหา <i class="fa-solid fa-reply"></i></a>
        <?php else: ?>
        <a href="../index.php?page=news" class="apply-success-home-btn">กลับสู่หน้าหลัก <i class="fa-solid fa-reply"></i></a>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
