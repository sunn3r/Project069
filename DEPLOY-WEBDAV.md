# อัปเว็บขึ้นเซิร์ฟเวอร์มหาลัยผ่าน File Explorer (WebDAV)

วิธีนี้ใช้แทน FileZilla/SFTP — ลากไฟล์วางใน File Explorer ที่ต่อ `std.csit.scidi.tsu.ac.th`

> ข้อจำกัด: ตั้ง permission (chmod) ไม่ได้ และไฟล์ `.htaccess` อาจอัปไม่ขึ้น — มีวิธีเช็ค/แก้อยู่ในแต่ละขั้น

---

## ภาพรวม 4 เฟส

| เฟส | ทำอะไร | ต้องมีอะไร |
|-----|--------|-----------|
| A | หา URL เว็บ + เช็ค PHP | เข้า WebDAV ได้ (ทำได้แล้ว) |
| B | สร้าง + import ฐานข้อมูล | phpMyAdmin หรือ SSH เข้า `miscis` |
| C | อัปไฟล์โปรเจกต์ | เฟส A เสร็จ |
| D | ตั้งค่า + ทดสอบ | เฟส B, C เสร็จ |

---

## เฟส A — หา URL เว็บ และเช็ค PHP

### A1. เลือกโฟลเดอร์เดียว
ในหน้าต่าง WebDAV มี `069.V1`, `069CSS`, `IMG`, `project069` อยู่แล้ว
→ **ใช้โฟลเดอร์ `project069` โฟลเดอร์เดียว** (ลบ/ปล่อยอันอื่นไว้ก่อน อย่ากระจายไฟล์)

แอปต้องการโครงสร้างแบบนี้ **ข้างในโฟลเดอร์เดียว**:
```
project069/
  admin/  assets/  config/  user/
  index.php  file.php  .htaccess
```
CSS อยู่ที่ `project069/assets/css/style.css` — ไม่ใช่แยกเป็นโฟลเดอร์ `069CSS`

### A2. อัปไฟล์ทดสอบ
1. ในเครื่อง สร้างไฟล์ `test.php` เนื้อหา:
   ```php
   <?php phpinfo();
   ```
2. คัดลอกไปวางในโฟลเดอร์ `project069` (ในหน้าต่าง WebDAV)

### A3. หา URL ที่ใช้ได้
เปิดเบราว์เซอร์ ลองทีละอัน:
- `http://std.csit.scidi.tsu.ac.th/project069/test.php`
- `http://std.csit.scidi.tsu.ac.th/~662021069/project069/test.php`
- `http://std.csit.scidi.tsu.ac.th/662021069/project069/test.php`
- `http://miscis.scidi.tsu.ac.th/project069/test.php`

อันที่ขึ้น **ตาราง PHP info สีน้ำเงิน-ม่วง** = URL จริง — **จดไว้** (เรียกว่า `<BASE_URL>` ต่อจากนี้)

### A4. อ่านค่าจากหน้า phpinfo
- **PHP Version** (บนสุด) — ต้อง ≥ 7.4
- กด Ctrl+F หา `pdo_mysql` — ต้องเจอหัวข้อ "PDO drivers → mysql"
- หา `session` — ต้อง enabled
- ถ้าขาดอะไร → แจ้ง IT

### A5. ลบ test.php
ลบทิ้งจากหน้าต่าง WebDAV (มีข้อมูลเซิร์ฟเวอร์ ไม่ควรทิ้งไว้)

---

## เฟส B — ฐานข้อมูล

ไฟล์ที่จะ import: **`docker/db-init/recruitment_db.sql`** (อยู่ในเครื่องแล้ว)

### ทางที่ 1 — phpMyAdmin (ถ้ามี)
ลองเปิด:
- `http://std.csit.scidi.tsu.ac.th/phpmyadmin`
- `http://miscis.scidi.tsu.ac.th/phpmyadmin`

ถ้าเข้าได้:
1. ล็อกอิน (user/pass ที่ IT ให้ หรือรหัสนักศึกษา)
2. ถ้ายังไม่มี database ของตัวเอง → แท็บ **Databases** → สร้าง เช่น `s662021069_recruit` (charset `utf8mb4_general_ci`)
3. เลือก database นั้น → แท็บ **Import** → Choose File = `recruitment_db.sql` → **Go**
4. **จด:** db host (มักเป็น `localhost`), db name, db user, db password

### ทางที่ 2 — ผ่าน SSH (`miscis.scidi.tsu.ac.th`)
ถ้าล็อกอิน SSH ได้แล้ว (ยังติดปัญหารหัสผ่านอยู่ — แก้ก่อน):
```bash
# อัป recruitment_db.sql ขึ้นไปก่อน (วางในโฟลเดอร์ home ผ่าน WebDAV หรือ scp)
mysql -u 662021069 -p -e "CREATE DATABASE IF NOT EXISTS s662021069_recruit CHARACTER SET utf8mb4"
mysql -u 662021069 -p s662021069_recruit < recruitment_db.sql
```

### ทางที่ 3 — ถาม IT
"ขอ MySQL database 1 ตัว พร้อม username/password และ URL phpMyAdmin สำหรับ import ไฟล์ .sql"

---

## เฟส C — อัปไฟล์โปรเจกต์

### C1. เปิดให้เห็นไฟล์ .htaccess (ทั้ง 2 ฝั่ง)
File Explorer → เมนู **View → Show → Hidden items** (ติ๊กให้ขึ้น ✓)

### C2. สร้าง config/credentials.php ในเครื่องก่อน
คัดลอก `config/credentials.example.php` เป็น `config/credentials.php` ใส่ค่าจากเฟส B:
```php
<?php
return [
    'host' => 'localhost',
    'name' => 's662021069_recruit',
    'user' => '662021069',
    'pass' => 'รหัสจริง',
];
```

### C3. คัดลอกไฟล์
เปิด 2 หน้าต่าง: ซ้าย = `c:\Users\user\Project069`  ขวา = WebDAV `.../project069`

ลากของพวกนี้ไปวางฝั่งขวา:
```
admin\      assets\     config\     user\
index.php   file.php    .htaccess
```

**ห้ามอัป:** `docker\`, `docker-compose.yml`, `node_modules\`, `index.js`, `package.json`, `.claude\`, ไฟล์ `.md` ทุกไฟล์, `config\credentials.example.php`

รอจนคัดลอกเสร็จทุกไฟล์ (โปรเจกต์มี ~50 ไฟล์ ใช้เวลาสักครู่ ถ้าค้าง/error ให้ลองไฟล์นั้นซ้ำ)

### C4. เช็คว่า .htaccess ขึ้นครบ 4 ที่
เปิดเบราว์เซอร์:

| URL | ต้องได้ |
|-----|---------|
| `<BASE_URL>/config/db.php` | **403 Forbidden** |
| `<BASE_URL>/config/credentials.php` | **403 Forbidden** |
| `<BASE_URL>/DEPLOY.md` ... (ไม่มีไฟล์นี้ ข้าม) | — |

ถ้าเห็น **โค้ด PHP หรือดาวน์โหลดไฟล์** แทน 403 = `.htaccess` ไม่ขึ้น → **แจ้งผม จะเปลี่ยนวิธีป้องกันให้** (ย้ายโฟลเดอร์อัปโหลดออกนอก web root)

---

## เฟส D — ตั้งค่าและทดสอบ

### D1. เปิดเว็บ
`<BASE_URL>/index.php` → ต้องเห็นหน้า "ข่าวสารการรับสมัคร" พร้อมรายการงาน
- เห็น "ระบบขัดข้องชั่วคราว" = เชื่อมฐานข้อมูลไม่ได้ → เช็ค `config/credentials.php`
- หน้าเปล่า/ผิดเพี้ยน = CSS ไม่ขึ้น → เช็คว่า `assets/css/style.css` อัปแล้ว

### D2. ทดสอบแอดมิน
`<BASE_URL>/admin/login.php` → ล็อกอิน (บัญชี `admin` หรือ `sun` — รหัสในตาราง `admins`)

### D3. ทดสอบระบบไฟล์เอกสาร (สำคัญ)
1. เปิด `<BASE_URL>/assets/uploads/applications/` + ชื่อไฟล์รูปสักไฟล์ → ต้อง **403**
2. ล็อกอินแอดมิน → เข้าหน้าตรวจผู้สมัคร → กด "ดูไฟล์" → ต้องเปิดได้
3. ทดสอบสมัครงาน + อัปโหลดไฟล์ 1 รายการ → ไฟล์ต้องบันทึกสำเร็จ
   - ถ้าerror เขียนไฟล์ไม่ได้ → ปัญหา permission → ต้องใช้ SSH `chmod 755 assets/uploads` (หรือ 775) หรือขอ IT

### D4. บังคับ HTTPS (ถ้าเซิร์ฟเวอร์มี SSL)
แก้ `.htaccess` ที่ root เอา `#` ออกจากบล็อก RewriteRule HTTPS แล้วอัปทับ

---

## อัปเดตครั้งต่อไป
แก้ไฟล์ในเครื่อง → ลากไฟล์นั้นไปทับใน WebDAV
**อย่าทับ:** `config/credentials.php`, โฟลเดอร์ `assets/uploads/`
