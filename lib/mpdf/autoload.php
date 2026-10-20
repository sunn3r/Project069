<?php
/**
 * Autoloader มือ (ไม่มี Composer ในโปรเจกต์นี้) สำหรับ mPDF + dependency ที่จำเป็น
 * แผนที่ namespace -> โฟลเดอร์ ตรงกับ "autoload.psr-4" ในไฟล์ composer.json ของแต่ละไลบรารี
 */

$__mpdfPsr4Map = [
    'Mpdf\\'                => __DIR__ . '/src/',
    'Psr\\Log\\'            => __DIR__ . '/../mpdf-deps/psr-log/Psr/Log/',
    'Psr\\Http\\Message\\'  => __DIR__ . '/../mpdf-deps/psr-http-message/src/',
    'setasign\\Fpdi\\'      => __DIR__ . '/../mpdf-deps/fpdi/src/',
    'DeepCopy\\'            => __DIR__ . '/../mpdf-deps/deep-copy/src/DeepCopy/',
];

spl_autoload_register(function ($class) use ($__mpdfPsr4Map) {
    foreach ($__mpdfPsr4Map as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
});

// DeepCopy ประกาศฟังก์ชัน global (deep_copy()) ไว้ในไฟล์แยก ไม่ใช่คลาส ต้อง require ตรงๆ
require_once __DIR__ . '/../mpdf-deps/deep-copy/src/DeepCopy/deep_copy.php';
