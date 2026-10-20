<?php
/**
 * คัดลอกไฟล์นี้เป็น credentials.php แล้วใส่ค่าจริงของเซิร์ฟเวอร์
 *   - บนเครื่อง dev: ไม่ต้องมีไฟล์ credentials.php ก็ได้ (จะใช้ค่า default = root / ไม่มีรหัส)
 *   - บนเซิร์ฟเวอร์จริง: สร้าง credentials.php ตามค่าที่ทางมหาลัยให้มา
 *
 * credentials.php ถูกใส่ไว้ใน .gitignore แล้ว จะไม่ถูก commit ขึ้น git
 */

return [
    'host' => 'localhost',
    'name' => 'recruitment_db',
    'user' => 'db_user',
    'pass' => 'CHANGE_ME',
];
