# ระบบติดตามสถานะงานติดตั้งลูกค้า

Web Application ภายในองค์กร เขียนด้วย PHP 8.1+ และ MySQL ใช้แนวทาง MVC ขนาดเล็ก, PDO prepared statements, Session, CSRF และ Role-Based Access Control โดยเชื่อมต่อข้อมูลลูกค้าจากตารางเดิมผ่านไฟล์ mapping และไม่สร้างข้อมูล Mock

## สถาปัตยกรรม

```text
Browser
  -> public/index.php
  -> Router
  -> Auth / Permission Middleware
  -> Controller
  -> Service / Repository
  -> PDO -> MySQL
```

```text
cap-plus/
├── app/
│   ├── Controllers/       # รับ request, validation และเลือก view
│   ├── Core/              # Router, PDO, Session/Auth, CSRF, View, Env
│   ├── Middleware/        # บังคับ Login และ Permission
│   ├── Repositories/      # SQL และ data access
│   ├── Services/          # Login และ Activity Logger
│   └── Support/           # helper: escape, URL, config, permission
├── config/
│   ├── app.php
│   ├── database.php       # PDO options และค่าจาก .env
│   └── customer.php       # mapping ตาราง/คอลัมน์เดิม
├── database/
│   ├── schema.sql         # ตาราง Login, RBAC และ Audit
│   └── create_admin.php   # สร้าง Admin โดยใช้ password_hash
├── public/
│   ├── assets/css/app.css
│   ├── assets/js/
│   ├── .htaccess
│   └── index.php          # web root
├── resources/views/       # Login, Dashboard, Customer, Admin, Layout
├── routes/web.php         # Route พร้อม Permission ของแต่ละ Action
├── storage/logs/          # PHP error log ภายใน ไม่แสดงต่อผู้ใช้
├── .env.example
└── README.md
```

## ตารางระบบ

- `roles`, `permissions`, `role_permissions`: Role และ Permission แยกจากกัน
- `users`: บัญชีผู้ใช้และ password hash
- `user_permissions`: จุดต่อขยายสำหรับ grant/deny รายผู้ใช้
- `activity_logs`: ประวัติ Login, Logout, เพิ่ม, แก้ไข, ปิดบัญชี, Reset Password, Permission และ Export
- `login_logs`: Login History ทั้งสำเร็จและไม่สำเร็จ

`database/schema.sql` ไม่สร้าง ไม่แก้ และไม่ลบตารางลูกค้าเดิม

## วิธีติดตั้งบน XAMPP

1. ต้องมี PHP 8.1+ พร้อม extensions `pdo_mysql` และ `mbstring`, MySQL 8+ และ Apache `mod_rewrite`
2. ตั้ง Apache DocumentRoot/VirtualHost ให้ชี้ที่ `C:/xampp/htdocs/cap-plus/public` (แนะนำ) หรือเข้าผ่าน `http://localhost/cap-plus/public`
3. คัดลอก `.env.example` เป็น `.env` แล้วแก้ข้อมูลฐานข้อมูลและ `APP_URL`
4. Import `database/schema.sql` ในฐานข้อมูลเดียวกับระบบ
5. สร้าง Admin คนแรกจาก Command Prompt/PowerShell:

```bash
php database/create_admin.php admin "System Administrator" "ChangeThis-Password-123!"
```

6. ตั้ง mapping ตารางลูกค้าตามหัวข้อถัดไป แล้วเปิดหน้า `/login`
7. Production ต้องเปิด HTTPS, ตั้ง `APP_DEBUG=false`, จำกัดสิทธิ์ MySQL เฉพาะ schema/operation ที่ใช้ และไม่ commit `.env`

## รันด้วย `php artisan serve`

โปรเจกต์นี้ไม่ใช่ Laravel แต่มีไฟล์ `artisan` compatibility launcher ให้ใช้คำสั่งเดียวกันได้เฉพาะการเปิด development server:

```powershell
php artisan serve
```

ให้เครื่องอื่นในเครือข่ายเข้าถึงได้:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

จากเครื่องอื่นเปิด `http://IP-ของเครื่องที่รัน:8000` และตั้ง `APP_URL` ใน `.env` ให้ตรงกับ URL ที่ใช้งาน เช่น `http://192.168.1.20:8000` คำสั่งนี้เปิดเฉพาะเว็บแอป ไม่ได้ให้สิทธิ์แก้ Source Code และควรใช้เฉพาะเครือข่ายภายใน/Development เท่านั้น

## การเชื่อมต่อฐานข้อมูลเดิม

การติดตั้งปัจจุบันใช้ View `cap_plus.customer_installation_view` เชื่อมข้อมูลแบบอ่านอย่างเดียวดังนี้:

- ชื่อลูกค้า: `landyhome.customers.customer_name`
- Zone: `landyhome.worksheets.zone`
- คีย์เชื่อม: `worksheets.customer_id = customers.customer_id`
- รหัสแถว: `worksheets.worksheet_id`
- Status เริ่มต้น: `landyhome.worksheet_status.status_worksheet`
- วันที่/สถานะ/หมายเหตุติดตามเพิ่มเติม: `cap_plus.customer_installations`

ไฟล์ SQL ที่ใช้สร้างการเชื่อมคือ `database/link_landyhome_customers.sql` การแก้ไขจากหน้า Detail จะเขียนเฉพาะตารางติดตามใน `cap_plus` และไม่แก้ `landyhome.customers` หรือ `landyhome.worksheets`

แก้ชื่อ table ใน `.env`:

```dotenv
CUSTOMER_TABLE=ชื่อ_table_เดิม
CUSTOMER_WRITES_ENABLED=false
```

จากนั้นแก้เฉพาะค่าด้านขวาใน `config/customer.php` ให้ตรงกับคอลัมน์จริง:

```php
'columns' => [
    'id' => 'customer_id',              // คีย์ภายในระบบ => คอลัมน์จริง
    'name' => 'customer_company_name',
    'zone' => 'sales_zone',
    'machine_size' => 'machine_capacity',
    // ...
],
```

คีย์ด้านซ้ายห้ามเปลี่ยน เพราะ Repository และ View ใช้เป็นชื่อมาตรฐาน ส่วนค่าด้านขวาเปลี่ยนได้ทั้งหมด ระบบยอมรับเฉพาะ identifier รูปแบบ `A-Z`, `a-z`, `0-9`, `_` เพื่อป้องกันการแทรก SQL ผ่าน config

ถ้าฐานข้อมูลเดิมไม่มีคอลัมน์ `notes`, `updated_by`, `updated_at` ให้เพิ่มคอลัมน์ในตารางเดิมหลังได้รับอนุญาต หรือ map ไปยังคอลัมน์เทียบเท่าที่มีอยู่ ห้ามเปิด `CUSTOMER_WRITES_ENABLED=true` จนกว่าจะ:

- สำรองฐานข้อมูล
- ตรวจ mapping ทุกคอลัมน์บน staging
- ให้เจ้าของข้อมูลอนุมัติการเขียน
- จำกัด DB user ให้มีสิทธิ์เท่าที่จำเป็น

ค่าเริ่มต้นเป็น read-only แต่ Dashboard, ตาราง, Detail, filter และ Export ใช้งานได้

## Login และ Permission Flow

Login ใช้ CSRF -> ค้นหา Username ด้วย prepared statement -> ตรวจบัญชี active -> `password_verify()` -> regenerate Session ID -> โหลด Role/Permission -> บันทึก Login History -> Dashboard

ทุกหน้าที่ป้องกันจะผ่าน Middleware ที่ `routes/web.php` เช่น:

```php
$router->get('/customers', [CustomerController::class, 'index'], ['view_customer']);
$router->post('/customers/{id}', [CustomerController::class, 'update'], ['edit_customer']);
```

Sidebar ใช้ permission helper เดียวกันเพื่อซ่อนเมนู แต่การซ่อน UI ไม่ใช่การป้องกันหลัก—backend ตรวจซ้ำทุก request เสมอ หลังเปลี่ยนสิทธิ์ ผู้ใช้ที่ออนไลน์ต้อง Login ใหม่เพื่อ refresh session permission

## Role เริ่มต้น

- Admin: ทุก Permission โดยอัตโนมัติ
- Manager: Dashboard, Customer, Export
- Staff: Dashboard, Customer, Edit Customer
- Viewer: Dashboard, Customer แบบอ่านอย่างเดียว
- Auditor: Dashboard, Customer, Activity/Login Log

Admin ปรับ permission ของแต่ละ Role ได้ที่ `Admin > Role & Permission`

Staff ถูกจำกัดระดับฟิลด์เพิ่มเติมใน `config/customer.php`: แก้ได้เฉพาะ `installation_date_1`, `installation_date_2`, `installation_date_3` และ `status` เท่านั้น โดย Controller จะบังคับซ้ำ แม้เรียก API โดยตรงก็ไม่สามารถแก้ฟิลด์อื่นได้

ผู้ใช้ที่ Admin เพิ่มใหม่จะมี `must_change_password=1` และถูกส่งไปหน้า `change-password` ทันทีหลัง Login ครั้งแรก ระบบจะไม่ให้ข้ามไป Dashboard จนกว่าจะตั้ง Password ใหม่อย่างน้อย 12 ตัวอักษร

## UI และ Frontend

- Bootstrap 5, Chart.js และ Noto Sans Thai ผ่าน CDN
- ใช้ Font Awesome Free webfont icons ผ่านแพ็กเกจ `@fortawesome/fontawesome-free`; แนวทางการใช้งานอ้างอิง [Font Awesome Web Documentation](https://docs.fontawesome.com/web/)
- ตารางมี real-time search (debounce), filters, server-side sort/pagination, per-page, sticky header, responsive horizontal scroll, keyboard-accessible row และ CSV/Excel-compatible export
- สีสถานะ: แดงเมื่อเลยกำหนด, เหลืองเมื่อเหลือไม่เกินจำนวนวันที่ตั้งใน `near_due_days`, เขียวเมื่อสำเร็จ

CDN เหมาะสำหรับเริ่มต้นในเครือข่ายที่ออกอินเทอร์เน็ตได้ หากระบบ intranet ปิดอินเทอร์เน็ต ให้ดาวน์โหลด Bootstrap, Chart.js, Font Awesome Free และฟอนต์มาเก็บใน `public/assets/vendor/` แล้วเปลี่ยน `<link>/<script>` ใน layout ตามแนวทาง self-host ของผู้ผลิต

## Security Checklist

- PDO native prepared statements และ identifier allow-list
- Output escape ด้วย `htmlspecialchars` ป้องกัน XSS
- CSRF token ทุก POST รวม Login/Logout
- `password_hash(PASSWORD_DEFAULT)` / `password_verify()` / rehash อัตโนมัติ
- จำกัด Login ที่ล้มเหลว 5 ครั้งต่อ Username/IP ภายใน 15 นาที
- Session ID regeneration, strict mode, HttpOnly, SameSite=Lax และ inactivity timeout 2 ชั่วโมง
- Permission middleware ทุก protected route/action
- CSV formula injection protection
- Database exception ถูก log ที่ `storage/logs/php-error.log` และไม่แสดงรายละเอียดเมื่อ `APP_DEBUG=false`
- ปิด directory listing ด้วย `.htaccess`

## ก่อนใช้งานจริง

เนื่องจาก repository นี้ไม่มี schema/credential ของฐานข้อมูลลูกค้า จึงตรวจได้ถึงระดับ syntax และโครงสร้างเท่านั้น หลังตั้ง `.env` และ mapping แล้วควรทดสอบกับสำเนาฐานข้อมูล staging ก่อน โดยเฉพาะชนิดวันที่, ค่า Status จริง, NULL/ค่าว่าง และจำนวนข้อมูลขนาดใหญ่ ควรเพิ่ม index ที่คอลัมน์ Zone, Status, วันที่ส่ง PO และครบกำหนด 60 วันหลังวิเคราะห์ query plan และได้รับอนุญาตจากเจ้าของฐานข้อมูล
