# Hướng dẫn deploy lên Hostinger

Áp dụng cho gói **Shared Hosting** hoặc **Business Hosting** của Hostinger (có hPanel, hỗ trợ PHP + MySQL).

## Bước 1 — Tạo cơ sở dữ liệu MySQL

1. Đăng nhập **hPanel** → chọn website/hosting.
2. Vào **Databases → MySQL Databases**.
3. Tạo mới: đặt tên CSDL, tạo user và mật khẩu, gán user vào CSDL (quyền ALL PRIVILEGES).
4. Ghi lại: tên CSDL, tên user, mật khẩu, host (thường là `localhost`).

## Bước 2 — Import schema

1. Vào **Databases → phpMyAdmin**, chọn đúng CSDL vừa tạo.
2. Vào tab **Import**, chọn file `database/schema.sql` trong repo này, nhấn **Go**.
3. Kiểm tra đã có đủ các bảng: `users, rooms, bank_accounts, contracts, deals, deal_periods, expenses, cleaning_logs, reminders, settings`.

## Bước 3 — Upload code

**Cách A — Upload qua File Manager / FTP (đơn giản nhất):**
1. Nén toàn bộ code (trừ thư mục `.git`) thành file `.zip`.
2. hPanel → **Files → File Manager**, vào thư mục `public_html` (hoặc thư mục con của domain/subdomain bạn muốn dùng).
3. Upload file `.zip` rồi bấm **Extract**.

**Cách B — Qua Git (nếu gói Hostinger hỗ trợ Git Deploy):**
1. hPanel → **Advanced → Git**, kết nối tới repository GitHub public của bạn.
2. Chọn branch `main`, nhấn **Deploy**.

## Bước 4 — Cấu hình kết nối CSDL

1. Trong File Manager, vào thư mục `config/`.
2. Sao chép `db_credentials.sample.php` thành `db_credentials.php` (đổi tên hoặc dùng chức năng "Copy").
3. Sửa nội dung `db_credentials.php`:
   ```php
   define('DB_DRIVER', 'mysql');
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'ten_csdl_that');
   define('DB_USER', 'user_that');
   define('DB_PASS', 'mat_khau_that');
   ```

## Bước 5 — Phân quyền thư mục upload

- Đảm bảo thư mục `uploads/contracts/` có quyền ghi (thường mặc định đã đủ trên Hostinger, nếu lỗi hãy đặt quyền `755`).

## Bước 6 — Kiểm tra

1. Truy cập domain của bạn (VD: `https://tenmien.com`).
2. Đăng nhập bằng `admin` / `admin123`.
3. **Đổi mật khẩu ngay** tại **Tài khoản → Sửa** tài khoản admin.
4. Xóa dữ liệu mẫu (nếu có) và bắt đầu nhập dữ liệu thật: phòng, khách thuê, hợp đồng...

## Dùng domain phụ / subdomain

Nếu bạn deploy vào thư mục con (VD: `public_html/quanlyphong`), ứng dụng tự nhận diện đường dẫn gốc (`BASE_URL`) nên **không cần sửa code**.

## Bật HTTPS

Hostinger cấp SSL miễn phí (Let's Encrypt) — vào hPanel → **SSL** → bật cho domain. Nên bật để bảo vệ phiên đăng nhập.

## Sao lưu định kỳ

Vào hPanel → **Backups** để bật sao lưu tự động CSDL + file, hoặc tự export CSDL qua phpMyAdmin định kỳ.
