<?php
/**
 * ตัวกลางส่งไฟล์เอกสารผู้สมัคร (assets/uploads/applications/) แบบเช็คสิทธิ์
 *
 * เดิม: ไฟล์ในโฟลเดอร์นี้เปิดได้ตรงจาก URL ใครก็โหลดสำเนาบัตร ปชช. / ใบรับรองแพทย์
 *       ของคนอื่นได้ถ้าเดาชื่อไฟล์ถูก (ชื่อไฟล์ = เลขบัตร + ประเภท + เวลา ซึ่งเดาง่าย)
 * ใหม่: โฟลเดอร์ applications/ ถูก .htaccess บล็อกการเข้าถึงตรงทั้งหมด
 *       ทุกการขอไฟล์ต้องผ่านไฟล์นี้ ซึ่งตรวจสิทธิ์ก่อนส่ง
 *
 * สิทธิ์:
 *   - แอดมินที่ล็อกอินอยู่ (มี $_SESSION['admin_id'])  -> ดูได้ทุกไฟล์
 *   - ผู้สมัครที่กำลังอยู่ในขั้นตอนสมัคร              -> ดูได้เฉพาะ QR ชำระเงินของตัวเอง
 *
 * เรียกใช้:  file.php?f=<ชื่อไฟล์>
 */

session_start();

// ---------- ตรวจชื่อไฟล์ กัน path traversal ----------
$requested = $_GET['f'] ?? '';
$filename = basename($requested);

if ($filename === '' || $filename !== $requested || strpos($requested, "\0") !== false) {
    http_response_code(400);
    exit('คำขอไม่ถูกต้อง');
}

// ---------- เช็คสิทธิ์ก่อน (อย่าเพิ่งบอกว่าไฟล์มีอยู่จริงหรือไม่ กัน enumerate ชื่อไฟล์) ----------
$isAdmin = !empty($_SESSION['admin_id']);

// QR ชำระเงิน (ไฟล์ *_payment_qr_*) แสดงให้ผู้สมัครที่กำลังทำรายการอยู่
$isPaymentQr = strpos($filename, '_payment_qr_') !== false;
$sessionQr = $_SESSION['apply']['payment_qr_file'] ?? null;
$applicantCanSeeQr = $isPaymentQr
    && is_string($sessionQr)
    && hash_equals($sessionQr, $filename);

if (!$isAdmin && !$applicantCanSeeQr) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึงไฟล์นี้');
}

// ---------- ผ่านสิทธิ์แล้ว ค่อยเช็คว่ามีไฟล์จริง ----------
$baseDir = __DIR__ . '/assets/uploads/applications/';
$realBase = realpath($baseDir);
$path = realpath($baseDir . $filename);

if ($realBase === false || $path === false || !is_file($path)
    || strpos($path, $realBase . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('ไม่พบไฟล์');
}

// ---------- ส่งไฟล์ ----------
$mimeByExt = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

header('Content-Type: ' . ($mimeByExt[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($path);
