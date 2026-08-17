# Quản Lý Phòng

Phần mềm quản lý cho thuê phòng / chung cư mini / căn hộ dịch vụ, viết bằng **PHP thuần + MySQL**, giao diện **Bootstrap 5**. Chạy tốt trên mọi gói hosting hỗ trợ PHP + MySQL (kể cả Shared Hosting rẻ nhất của Hostinger).

## Tính năng

- **Dashboard**: tổng quan số phòng, doanh thu/chi phí tháng, lợi nhuận, hợp đồng sắp hết hạn, hóa đơn chưa thu, biểu đồ 6 tháng gần nhất.
- **Danh sách phòng**: quản lý phòng theo **khu vực** (VD: Vinhomes Central Park, Vinhomes Grand Park), số phòng ngủ, diện tích, giá thuê tháng/ngày, trạng thái (trống / đang thuê / bảo trì).
- **Khách thuê & Hợp đồng**: hồ sơ khách thuê, hợp đồng dài hạn gắn với phòng, tiền cọc, đơn giá điện/nước, người ở cùng, đính kèm file hợp đồng, tự động cập nhật trạng thái phòng.
- **Doanh thu dài hạn**: lập hóa đơn hàng tháng theo hợp đồng (tiền phòng + điện nước theo chỉ số + phí dịch vụ), theo dõi thanh toán.
- **Doanh thu ngắn hạn**: quản lý đặt phòng theo ngày/đêm (check-in/check-out), tính tiền tự động.
- **Chi phí**: ghi nhận chi phí theo danh mục (điện, nước, sửa chữa, lương, ...), lọc theo tháng.
- **Báo cáo**: doanh thu - chi phí - lợi nhuận theo tháng (6/12/24 tháng), cơ cấu chi phí, tỷ lệ lấp đầy phòng.
- **Tài khoản**: phân quyền Quản trị / Nhân viên.

## Cấu trúc thư mục

```
config/       Cấu hình kết nối CSDL, session
includes/     Header/footer, hàm dùng chung
assets/       CSS, JS
database/     schema.sql (MySQL - dùng để deploy), schema_sqlite.sql (chỉ để test local)
rooms/ tenants/ contracts/ invoices/ bookings/ expenses/ reports/ users/
uploads/      File hợp đồng tải lên
```

## Chạy thử trên máy local (Windows)

1. Cài PHP (khuyến nghị PHP 8.1+): `winget install PHP.PHP.8.4`
2. Bật extension `pdo_sqlite` (hoặc `pdo_mysql` nếu dùng MySQL) trong `php.ini`.
3. Sao chép `config/db_credentials.sample.php` thành `config/db_credentials.php`, để `DB_DRIVER = 'sqlite'`.
4. Tạo CSDL SQLite từ schema:
   ```bash
   php -r "$pdo = new PDO('sqlite:database/local.sqlite'); $pdo->exec(file_get_contents('database/schema_sqlite.sql'));"
   ```
5. Chạy server thử: `php -S localhost:8899`
6. Truy cập `http://localhost:8899` — đăng nhập với `admin` / `admin123` (**đổi mật khẩu ngay sau khi đăng nhập lần đầu**).

## Deploy lên Hostinger (Shared/Business Hosting)

Xem hướng dẫn chi tiết trong [`DEPLOY.md`](DEPLOY.md).

## Bảo mật

- Đổi mật khẩu tài khoản `admin` ngay sau khi cài đặt.
- File `config/db_credentials.php` chứa thông tin CSDL thật, **không** được commit lên Git (đã có trong `.gitignore`).
- Toàn bộ form đều có CSRF token và dùng PDO prepared statements để chống SQL injection.
