<?php
// ไฟล์ตรวจสภาพเซิร์ฟเวอร์ชั่วคราว — อัปทับของเดิม เปิดผ่านเบราว์เซอร์ แล้ว "ลบทิ้งทันที"
header('Content-Type: text/plain; charset=utf-8');

function w($dir) {
    if (!is_dir($dir)) return "(ไม่มีโฟลเดอร์)";
    $p = $dir . '/.__wtest_' . getmypid();
    if (@file_put_contents($p, 'x') !== false) { @unlink($p); return "เขียนได้ ✅"; }
    $owner = function_exists('posix_getpwuid') && function_exists('fileowner')
        ? @posix_getpwuid(@fileowner($dir))['name'] : '?';
    return "เขียนไม่ได้ ❌  (perms=" . substr(sprintf('%o', @fileperms($dir)), -3) . ", owner=" . $owner . ")";
}

echo "PHP " . PHP_VERSION . "  |  runs as: "
   . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "\n";
echo "โฟลเดอร์นี้ : " . __DIR__ . "\n\n";

echo "== ทดสอบเขียนแต่ละที่ ==\n";
echo str_pad("โฟลเดอร์โปรเจกต์", 30) . " : " . w(__DIR__) . "\n";
echo str_pad("wtest (สร้างผ่าน WebDAV)", 30) . " : " . w(__DIR__ . '/wtest') . "\n";
echo str_pad("assets/uploads", 30) . " : " . w(__DIR__ . '/assets/uploads') . "\n";
echo str_pad("assets/uploads/applications", 30) . " : " . w(__DIR__ . '/assets/uploads/applications') . "\n";

echo "\n== โฟลเดอร์ย่อยทั้งหมดใน project069 ==\n";
foreach (glob(__DIR__ . '/*', GLOB_ONLYDIR) as $d) {
    echo str_pad(basename($d), 30) . " : " . w($d) . "\n";
}

echo "\n== PHP session ทำงานไหม (จำเป็นสำหรับ login) ==\n";
@session_start();
$_SESSION['t'] = 1;
echo "session save_path : " . (ini_get('session.save_path') ?: '(default)') . "\n";
echo "session เขียนได้  : " . (isset($_SESSION['t']) ? 'น่าจะได้ (ต้องเช็คหน้า login จริงอีกที)' : 'ไม่ได้') . "\n";

echo "\n!!! ลบไฟล์ _check.php และโฟลเดอร์ wtest ทิ้งหลังดูเสร็จ !!!\n";
