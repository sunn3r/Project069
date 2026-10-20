<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT admin_id, Name, password_hash FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true); // กัน session fixation
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['Name'];
        header('Location: index.php');
        exit;
    }

    $loginError = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}

$cssVersion = filemtime(__DIR__ . '/../assets/css/style.css');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบผู้ดูแลระบบ - โรงเรียนสาธิตมหาวิทยาลัยทักษิณ ฝ่ายมัธยม</title>
<link rel="stylesheet" href="../assets/css/style.css?v=<?php echo $cssVersion; ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="admin-login-page">
    <div class="admin-login-card">
        <h1 class="admin-login-title">การเข้าสู่ระบบสำหรับผู้ดูแลระบบเท่านั้น</h1>

        <?php if ($loginError !== ''): ?>
        <p class="admin-login-error"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form class="admin-login-form" action="login.php" method="post">
            <?php csrfField(); ?>
            <div class="field-group">
                <label class="field-label" for="loginUsername">ชื่อ</label>
                <input type="text" id="loginUsername" name="username" class="admin-login-input" required>
            </div>
            <div class="field-group">
                <label class="field-label" for="loginPassword">รหัสผ่าน</label>
                <input type="password" id="loginPassword" name="password" class="admin-login-input" required>
            </div>
            <button type="submit" class="admin-login-btn">เข้าสู่ระบบ</button>
        </form>

        <a href="../index.php" class="admin-login-back" aria-label="กลับหน้าหลัก">
            <i class="fa-solid fa-reply"></i>
        </a>
    </div>
</div>

</body>
</html>
