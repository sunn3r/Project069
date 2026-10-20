<?php
require_once __DIR__ . '/../config/db.php';

$preappId = $_GET['preapp_id'] ?? '';
$jobField = '';
$positionLevel = '';
if ($preappId !== '') {
    $stmt = $pdo->prepare('SELECT job_field, position_level FROM preapplications WHERE preapp_id = ?');
    $stmt->execute([$preappId]);
    $preapp = $stmt->fetch();
    if ($preapp) {
        $jobField = $preapp['job_field'] ?? '';
        $positionLevel = $preapp['position_level'] ?? '';
    }
}

$cssVersion = filemtime(__DIR__ . '/../assets/css/style.css');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ลงสมัครล่วงหน้าสำเร็จ - โรงเรียนสาธิตมหาวิทยาลัยทักษิณ ฝ่ายมัธยม</title>
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
    </div>

    <div class="apply-success-panel">
        <p class="apply-success-message">ท่านลงสมัครล่วงหน้า &nbsp;<?php echo htmlspecialchars($jobField, ENT_QUOTES, 'UTF-8'); ?>&nbsp; ตำแหน่ง &nbsp;<?php echo htmlspecialchars($positionLevel, ENT_QUOTES, 'UTF-8'); ?>&nbsp; สำเร็จแล้ว<br>กรุณารอผลการยืนยัน !!</p>
        <a href="../index.php?page=news" class="apply-success-home-btn">กลับสู่หน้าหลัก <i class="fa-solid fa-reply"></i></a>
    </div>
</main>

</body>
</html>
