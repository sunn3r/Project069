# คู่มือการอัปโหลดขึ้นเซิร์ฟเวอร์ (FTP/SFTP + MySQL)

ระบบรับสมัครงาน โรงเรียนสาธิตมหาวิทยาลัยทักษิณ ฝ่ายมัธยม
เขียนด้วย **PHP + MySQL** (ไม่ใช้ framework, ไม่ต้องใช้ Composer/Node)

---

## สิ่งที่ต้องขอจากทางมหาลัย / ฝ่าย IT

1. ข้อมูล FTP/SFTP: host, port, username, password
2. โฟลเดอร์ปลายทางของเว็บ (document root) เช่น `public_html/`, `www/`, `htdocs/`
3. ข้อมูล MySQL: host, ชื่อ database, username, password
   (ปกติสร้าง database + user ให้ผ่านหน้าจัดการของมหาลัย หรือ phpMyAdmin)
4. URL ของ phpMyAdmin (สำหรับ import ฐานข้อมูล) — เพราะเข้าผ่าน FTP อย่างเดียว รัน mysql ทาง command line ไม่ได้
5. PHP เวอร์ชันบนเซิร์ฟเวอร์ (ต้อง 7.4 ขึ้นไป แนะนำ 8.1+) และเปิด extension `pdo_mysql`

---

## ขั้นที่ 1 — Export ฐานข้อมูลจากเครื่อง

✅ **ทำให้แล้ว** — ไฟล์อยู่ที่ `docker/db-init/recruitment_db.sql` (export จาก XAMPP, 7 ตาราง พร้อมข้อมูล)

ใช้ไฟล์นี้ import ขึ้นเซิร์ฟเวอร์ในขั้นที่ 2 ได้เลย

<details>
<summary>ถ้าต้อง export ใหม่ (ข้อมูลเปลี่ยน)</summary>

- **จาก XAMPP:** เปิด phpMyAdmin (`http://localhost/phpmyadmin`) → เลือก `recruitment_db` → แท็บ **Export** → **Go**
- **จาก Docker:** ดู [DOCKER.md](DOCKER.md) หัวข้อ "Export ฐานข้อมูลจาก Docker"
- **command line:** `C:\xampp\mysql\bin\mysqldump.exe -u root --add-drop-table --skip-dump-date --result-file=docker\db-init\recruitment_db.sql recruitment_db` (ต้องเปิด MySQL ใน XAMPP ก่อน)

> ถ้าอยากได้เฉพาะโครงสร้าง + ผู้ดูแลระบบ ไม่เอาข้อมูลผู้สมัครทดสอบ ให้ลบบล็อก
> `INSERT INTO job_applications / applicants / preapplications ...` ออกจากไฟล์ทีหลัง
</details>

---

## ขั้นที่ 2 — สร้างและ import ฐานข้อมูลบนเซิร์ฟเวอร์

1. เข้า phpMyAdmin ของเซิร์ฟเวอร์มหาลัย
2. ถ้ายังไม่มี database ให้สร้างใหม่ (charset: `utf8mb4_general_ci` หรือ `utf8mb4_unicode_ci`)
3. เลือก database นั้น → แท็บ **Import** → เลือกไฟล์ `recruitment_db.sql` → **Go**
4. ถ้าไฟล์ใหญ่เกินลิมิต (มักจำกัด 2–50 MB) ให้บีบเป็น `.zip` แล้ว import หรือแบ่งไฟล์

จดค่าไว้: `db host`, `db name`, `db user`, `db password`

---

## ขั้นที่ 3 — ตั้งค่าการเชื่อมต่อฐานข้อมูล

1. คัดลอก `config/credentials.example.php` เป็น `config/credentials.php`
2. ใส่ค่าจริงจากขั้นที่ 2:

   ```php
   <?php
   return [
       'host' => 'localhost',        // หรือ host ที่มหาลัยให้
       'name' => 'satit_recruitment',
       'user' => 'satit_dbuser',
       'pass' => 'รหัสผ่านจริง',
   ];
   ```

3. ไฟล์นี้อยู่ใน `.gitignore` แล้ว จะอัปแยกผ่าน FTP เท่านั้น ไม่ commit

> `config/db.php` แก้ให้อ่านค่าจากไฟล์นี้อัตโนมัติ และถ้าเชื่อมต่อไม่ได้จะไม่โชว์รายละเอียด error
> ออกหน้าเว็บอีกต่อไป (เขียนลง error log แทน)

---

## ขั้นที่ 4 — อัปโหลดไฟล์ผ่าน FTP/SFTP ด้วย FileZilla

### 4.1 ติดตั้ง + เชื่อมต่อ

1. โหลด **FileZilla Client** จาก https://filezilla-project.org (เอา Client ไม่ใช่ Server)
2. เปิด → **File → Site Manager → New site** ตั้งค่า:
   - **Protocol:** `SFTP - SSH File Transfer Protocol` (ถ้ามหาลัยให้ SFTP) หรือ `FTP` ถ้าให้ FTP ธรรมดา
   - **Host:** ค่าที่ฝ่าย IT ให้ (เช่น `web.tsu.ac.th` หรือ IP) — **ไม่ใช่** `std.csit.sci.tsu.ac.th` ที่ยัง resolve ไม่ได้
   - **Port:** `22` (SFTP) หรือ `21` (FTP)
   - **Logon Type:** `Ask for password` (ไม่ให้ FileZilla จำรหัส) หรือ `Normal`
   - **User / Password:** ที่ฝ่าย IT ให้
   - ถ้าอยู่นอกมหาลัย: ต่อ VPN มหาลัยก่อน
3. กด **Connect** — ครั้งแรก SFTP จะถาม "Unknown host key" ให้กด **OK** (ติ๊ก "Always trust")

### 4.2 เปิดให้เห็นไฟล์ .htaccess

**Server → Force showing hidden files** (สำคัญมาก — ไม่งั้น `.htaccess` จะไม่ถูกอัป)

### 4.3 ไฟล์/โฟลเดอร์ที่ต้องอัป

ฝั่งซ้าย (Local) = `C:\Users\user\Project069` — ลากของพวกนี้ไปฝั่งขวา (document root เช่น `public_html/`):
```
admin/        assets/       config/       user/
index.php     file.php      .htaccess
```

### 4.4 ไม่ต้องอัป (ของ dev / เครื่องมือ)
```
node_modules/   index.js   package.json          docker/
.git/           .claude/    docker-compose.yml    .dockerignore
CLAUDE.md       DEPLOY.md   DOCKER.md
config/credentials.example.php   docker/db-init/*.sql   *.sql
```

### 4.5 ตั้งค่าฐานข้อมูล

อัป `config/credentials.php` (สร้างตามขั้นที่ 3 — ไฟล์นี้ไม่มีในเครื่อง dev ต้องสร้างเอง) ไปไว้ในโฟลเดอร์ `config/` บนเซิร์ฟเวอร์

### 4.6 ตรวจการอัป

- ฝั่งขวาต้องมีครบทุกโฟลเดอร์ + `.htaccess` ทั้ง 4 ตำแหน่ง (ดูขั้นที่ 5)
- ดูช่อง **Failed transfers** ด้านล่างของ FileZilla — ต้องว่าง

---

## ขั้นที่ 5 — ตั้ง permission โฟลเดอร์อัปโหลด

โฟลเดอร์ที่ PHP ต้องเขียนไฟล์ลงได้ (สำเนาบัตร, สลิป, เอกสารสอบ):
```
assets/uploads/
assets/uploads/applications/
assets/uploads/job_postings/
assets/uploads/exam_documents/
```

ใน FileZilla: คลิกขวาที่โฟลเดอร์ → **File permissions** → ใส่ `755`
(ถ้าอัปโหลดแล้วขึ้น error เขียนไฟล์ไม่ได้ ให้ลอง `775` หรือ `777` เป็นทางสุดท้าย
และสอบถามฝ่าย IT ว่า PHP รันด้วย user อะไร)

ตรวจว่าไฟล์ `.htaccess` เหล่านี้ถูกอัปขึ้นไปครบ (FileZilla บางทีซ่อนไฟล์ที่ขึ้นต้นด้วยจุด — เปิด "แสดงไฟล์ที่ซ่อน"):
- `assets/uploads/.htaccess` — กันการรันสคริปต์ในโฟลเดอร์อัปโหลด
- `assets/uploads/applications/.htaccess` — **บล็อกการเข้าถึงเอกสารผู้สมัครโดยตรง** (สำคัญ) ต้องเข้าผ่าน `/file.php` เท่านั้น
- `config/.htaccess` — กันการเปิดไฟล์ config ผ่านเว็บ
- `.htaccess` (ที่ root) — ปิด directory listing / กันไฟล์ .git .sql

---

## ขั้นที่ 6 — ทดสอบ

1. เปิด URL ของเว็บ → ต้องเห็นหน้า "ข่าวสารการรับสมัคร"
2. เข้า `/admin/login.php` → ล็อกอินด้วยบัญชีผู้ดูแลระบบ (จากตาราง `admins` ในฐานข้อมูล)
3. ทดลองสมัครงาน 1 รายการ + อัปโหลดไฟล์ → เช็คว่าไฟล์ไปโผล่ใน `assets/uploads/applications/`
4. **ทดสอบการเช็คสิทธิ์ไฟล์:**
   - เปิด `https://เว็บเรา/assets/uploads/applications/<ชื่อไฟล์จริง>` ตรง ๆ → ต้องได้ **403 Forbidden**
   - ตอนล็อกอินแอดมิน กด "ดูไฟล์" ในหน้าตรวจผู้สมัคร → ต้องเปิดไฟล์ได้ปกติ
   - ออกจากระบบแอดมินแล้วเปิด `https://เว็บเรา/file.php?f=<ชื่อไฟล์>` → ต้องได้ **403**
5. เช็ค error log ของเซิร์ฟเวอร์ถ้ามีอะไรผิดปกติ

---

## ควรทำเพิ่มก่อนเปิดใช้จริง (ความปลอดภัย)

| เรื่อง | สถานะปัจจุบัน | ควรแก้เป็น |
|--------|----------------|------------|
| ~~ไฟล์เอกสารผู้สมัคร เปิดตรงจาก URL~~ | ✅ แก้แล้ว: `file.php` เช็ค `$_SESSION['admin_id']` + `.htaccess` บล็อกโฟลเดอร์ `applications/` | (เพิ่มเติมถ้าต้องการ) ย้ายโฟลเดอร์ออกนอก document root |
| HTTPS | ยังไม่บังคับ | เปิดบล็อก RewriteRule ใน `.htaccess` เมื่อมี SSL |
| CSRF token ในฟอร์ม | ไม่มี | เพิ่ม token ทุกฟอร์ม POST |
| หน้า admin login | ไม่มี rate limit / session_regenerate_id | เพิ่มการจำกัดจำนวนครั้ง + regenerate session id หลังล็อกอิน |
| ตรวจไฟล์อัปโหลด | เช็คแค่นามสกุล | เช็ค MIME / ขนาดไฟล์ / สุ่มชื่อไฟล์ |
| `display_errors` | ควรปิดบนเซิร์ฟเวอร์จริง | ตั้ง `display_errors = Off`, `log_errors = On` (php.ini หรือ .htaccess) |

---

## อัปเดตเว็บครั้งต่อไป

แก้โค้ดในเครื่อง → อัปทับเฉพาะไฟล์ที่แก้ผ่าน FileZilla
**อย่า** อัปทับ `config/credentials.php` และโฟลเดอร์ `assets/uploads/`
