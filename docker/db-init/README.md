# โฟลเดอร์ init ฐานข้อมูล

วางไฟล์ **`recruitment_db.sql`** (ที่ export จาก XAMPP) ไว้ในโฟลเดอร์นี้

MariaDB จะ import ไฟล์ `.sql` / `.sh` ทุกไฟล์ในโฟลเดอร์นี้ **อัตโนมัติ เฉพาะตอนสร้าง data volume ครั้งแรก**
(คือตอน `docker compose up` ครั้งแรก หรือหลัง `docker compose down -v`)

ถ้าเพิ่ม/แก้ไฟล์ `.sql` ทีหลัง ต้องล้าง volume แล้วสร้างใหม่:

```
docker compose down -v
docker compose up -d
```

> ไฟล์ `*.sql` ถูกใส่ไว้ใน `.gitignore` แล้ว จะไม่ถูก commit (มีข้อมูลส่วนบุคคลของผู้สมัคร)
