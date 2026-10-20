<?php
// สคริปต์ตรวจชั่วคราว: อัปโหลดไปรันแล้วลบทิ้งทันที
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

$ok = function ($label, $pass, $extra = '') {
    echo str_pad($label, 34) . ($pass ? 'OK' : '*** ขาด/ผิดปกติ ***') . ($extra !== '' ? "  $extra" : '') . "\n";
};

echo "PHP version: " . PHP_VERSION . "\n\n";
foreach (['gd', 'mbstring', 'zip', 'pdo_mysql'] as $ext) {
    $ok("extension $ext", extension_loaded($ext));
}
echo "\n";

$base = __DIR__ . '/lib/';
foreach (['mpdf/autoload.php', 'mpdf/src/Mpdf.php', 'mpdf/src/Otl.php', 'mpdf/data/mpdf.css',
          'mpdf-deps/psr-log/Psr/Log/LoggerInterface.php', 'mpdf-deps/fpdi/src/FpdiTrait.php',
          'mpdf-deps/deep-copy/src/DeepCopy/deep_copy.php',
          'mpdf-fonts/Sarabun-Regular.ttf', 'mpdf-fonts/Sarabun-Bold.ttf'] as $f) {
    $ok("ไฟล์ lib/$f", is_file($base . $f));
}
$tmp = $base . 'mpdf/tmp';
$ok('โฟลเดอร์ lib/mpdf/tmp', is_dir($tmp));
$ok('เขียนไฟล์ใน tmp ได้', is_dir($tmp) && is_writable($tmp));
$ok('ระบบไฟล์ temp (zip) เขียนได้', is_writable(sys_get_temp_dir()), sys_get_temp_dir());
echo "\n--- ทดลองสร้าง PDF ---\n";

try {
    require_once $base . 'mpdf/autoload.php';
    $c = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $f = (new \Mpdf\Config\FontVariables())->getDefaults();
    $m = new \Mpdf\Mpdf([
        'tempDir' => $tmp,
        'fontDir' => array_merge($c['fontDir'], [$base . 'mpdf-fonts']),
        'fontdata' => $f['fontdata'] + ['sarabun' => ['R' => 'Sarabun-Regular.ttf', 'B' => 'Sarabun-Bold.ttf', 'useOTL' => 0xFF]],
        'default_font' => 'sarabun',
        'useDictionaryLBR' => false,
    ]);
    $m->WriteHTML('<p>ทดสอบ นั่ง ที่มา</p>');
    $pdf = $m->Output('', 'S');
    echo "สร้าง PDF สำเร็จ ขนาด " . strlen($pdf) . " ไบต์\n";
} catch (\Throwable $e) {
    echo "*** พัง: " . get_class($e) . "\n" . $e->getMessage() . "\nที่ไฟล์: " . $e->getFile() . ':' . $e->getLine() . "\n";
}
echo "\nเสร็จแล้ว — ลบไฟล์ _mpdfcheck.php ออกจากเซิร์ฟเวอร์ทันที\n";
