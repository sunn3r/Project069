# รันโปรเจกต์ในเครื่องด้วย Docker

ใช้แทน XAMPP ได้ — ได้ PHP 8.2 + Apache + MariaDB 11 + phpMyAdmin ครบในคำสั่งเดียว
โค้ดถูก mount เข้า container แก้ไฟล์แล้วรีเฟรชเบราว์เซอร์เห็นผลทันที (ไม่ต้อง build ใหม่)

## สิ่งที่ต้องมี

- Docker Desktop (เครื่องนี้มีแล้ว — Docker 28, Compose v2)

## ไฟล์ที่เกี่ยวข้อง

| ไฟล์ | หน้าที่ |
|------|---------|
| `docker-compose.yml` | นิยาม 3 service: `web`, `db`, `phpmyadmin` |
| `docker/Dockerfile` | PHP 8.2 + Apache + `pdo_mysql` + `zip` + `mod_rewrite` + `AllowOverride All` |
| `docker/php.ini` | ตั้งค่า PHP สำหรับ dev (แสดง error, upload 20M, timezone) |
| `docker/db-init/recruitment_db.sql` | ฐานข้อมูลที่ export จาก XAMPP — MariaDB import อัตโนมัติตอนรันครั้งแรก |

> `recruitment_db.sql` ถูก export ให้แล้ว (7 ตาราง: admins, applicants, jobs, job_applications, preapplications, exam_documents, applications_and_payment)
> ไฟล์นี้อยู่ใน `.gitignore` เพราะมีข้อมูลส่วนบุคคล

## เริ่มใช้งาน

```bash
docker compose up -d --build
```

รอ ~30 วินาที (ครั้งแรก MariaDB import ฐานข้อมูล) แล้วเปิด:

| บริการ | URL | หมายเหตุ |
|--------|-----|----------|
| เว็บแอป | http://localhost:8000 | หน้าแรก = ข่าวสารการรับสมัคร |
| หน้าแอดมิน | http://localhost:8000/admin/login.php | ล็อกอินด้วยบัญชีในตาราง `admins` |
| phpMyAdmin | http://localhost:8081 | server: `db` / user: `root` / pass: `root` |

ฐานข้อมูลจากภายนอก (เช่น HeidiSQL / MySQL Workbench): `127.0.0.1:3307` user `root` pass `root`

## คำสั่งที่ใช้บ่อย

```bash
docker compose up -d              # เปิด (หลัง build แล้ว)
docker compose down               # ปิด (ข้อมูลฐานข้อมูลยังอยู่)
docker compose logs -f web        # ดู log ของเว็บ (เช็ค error PHP)
docker compose logs -f db         # ดู log ฐานข้อมูล
docker compose exec web bash      # เข้าไปใน container เว็บ
docker compose up -d --build      # build ใหม่ (หลังแก้ Dockerfile / php.ini)
```

## รีเซ็ตฐานข้อมูล (import `recruitment_db.sql` ใหม่)

MariaDB รัน `docker/db-init/*.sql` **เฉพาะตอนสร้าง data volume ครั้งแรก** ถ้าอัปเดตไฟล์ `.sql`:

```bash
docker compose down -v            # -v = ลบ volume ฐานข้อมูลด้วย
docker compose up -d
```

## Export ฐานข้อมูลจาก Docker (เอาไปขึ้นเซิร์ฟเวอร์มหาลัย)

```bash
docker compose exec db sh -c 'exec mariadb-dump -uroot -proot --add-drop-table --skip-dump-date recruitment_db' > recruitment_db.sql
```

หรือใช้ phpMyAdmin (http://localhost:8081) → เลือก `recruitment_db` → Export

## แก้ปัญหา

| อาการ | สาเหตุ / วิธีแก้ |
|-------|------------------|
| `docker compose up` แล้ว port ชน | มีโปรแกรมอื่นใช้ 8000 / 8081 / 3307 อยู่ — แก้เลขพอร์ตซ้ายใน `docker-compose.yml` (เช่น `"8001:80"`) |
| เว็บขึ้น "ระบบขัดข้องชั่วคราว" | ฐานข้อมูลยังไม่พร้อม — รอสักครู่ / ดู `docker compose logs db` / ลอง `docker compose down -v && docker compose up -d` |
| แก้โค้ดแล้วไม่เปลี่ยน | โค้ด mount แบบ live อยู่แล้ว ลอง hard refresh (Ctrl+F5) — style.css ใช้ query `?v=filemtime` |
| อัปโหลดไฟล์ใน container ไม่ได้ | สิทธิ์โฟลเดอร์ `assets/uploads/` — รัน `docker compose exec web chmod -R 777 assets/uploads` (เฉพาะตอน dev) |
| XAMPP MySQL เปิดอยู่ด้วย | ไม่ชนกัน — Docker ใช้พอร์ต host 3307 ส่วน XAMPP ใช้ 3306 |

## ความสัมพันธ์กับการ deploy จริง

- Docker นี้ใช้ **เฉพาะรันในเครื่อง** — เซิร์ฟเวอร์มหาลัยเป็น FTP/SFTP + PHP + MySQL อยู่แล้ว ไม่ได้รัน Docker
- `config/db.php` อ่านค่าตามลำดับ: ค่า default (XAMPP) → ตัวแปร env `DB_*` (Docker ตั้งให้ใน compose) → `config/credentials.php` (เซิร์ฟเวอร์จริง)
- ตอนขึ้นเซิร์ฟเวอร์: **ไม่ต้องอัป** `docker-compose.yml`, `docker/`, `DOCKER.md` (ดู [DEPLOY.md](DEPLOY.md))
