# Quản Lý Phòng

Phần mềm quản lý cho thuê phòng / chung cư mini / căn hộ dịch vụ, viết bằng **PHP thuần + MySQL**, giao diện **Bootstrap 5**. Chạy tốt trên mọi gói hosting hỗ trợ PHP + MySQL (kể cả Shared Hosting rẻ nhất của Hostinger).

## Tính năng

- **Dashboard**: tổng quan số phòng, doanh thu/chi phí tháng, lợi nhuận, hợp đồng sắp hết hạn, nợ tồn đọng, biểu đồ 6 tháng gần nhất.
- **Khu & Phòng**: danh mục phòng tối giản theo **khu vực** (VD: Vinhomes Central Park), mã phòng, số phòng ngủ. Mã phòng có thể tự nhập tay ở các form khác, không bắt buộc phải có sẵn trong danh mục.
- **Hợp đồng**: hồ sơ pháp lý theo đúng mẫu hợp đồng thuê thực tế (bên thuê, bên cho thuê, tiền thuê/cọc, thời hạn, hình thức thanh toán), đính kèm file hợp đồng.
- **Doanh thu ngắn hạn & dài hạn (Deals)**: nhập **một lần duy nhất** (check-in/check-out, đơn giá...), hệ thống tự phân loại Ngắn hạn/Dài hạn theo quy ước **1 tháng = 30 ngày** (≥ 30 đêm là dài hạn) và **tự động sinh các kỳ công nợ 30 ngày** cho deal dài hạn (thuê/cọc/điện nước/đã thanh toán từng kỳ).
- **Chi phí**: ghi nhận chi phí theo danh mục (điện, nước, phí quản lý, lương, sửa chữa...), lọc theo tháng.
- **Tiền lương vệ sinh**: chấm công nhân viên vệ sinh theo ngày/phòng/hạng mục việc, lọc theo nhân viên + khoảng ngày ra tổng lương.
- **Báo cáo**: doanh thu - chi phí - lợi nhuận theo tháng (6/12/24 tháng), cơ cấu chi phí, tỷ lệ lấp đầy phòng.
- **Nhắc nhở**: danh sách việc cần nhắc tự tạo, đánh dấu hoàn thành.
- **Tài khoản ngân hàng & Đối soát**: gắn giao dịch thu/chi với tài khoản ngân hàng, đánh dấu đã đối soát với sao kê.
- **Đồng bộ Google Sheets**: đẩy/kéo dữ liệu qua lại với Google Sheet (xem [`GOOGLE_SHEETS.md`](GOOGLE_SHEETS.md)).
- **Tài khoản**: phân quyền Quản trị / Nhân viên.

## Cấu trúc thư mục

```
config/       Cấu hình kết nối CSDL, session
includes/     Header/footer, hàm dùng chung, GoogleSheets.php, deal_helpers.php
assets/       CSS, JS
database/     schema.sql (MySQL - dùng để deploy), schema_sqlite.sql (chỉ để test local)
rooms/ contracts/ deals/ expenses/ cleaning/ reminders/ bank_accounts/ reconciliation/ reports/ users/ settings/
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
