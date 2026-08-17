# Đồng bộ dữ liệu với Google Sheets

Phần mềm hỗ trợ đẩy/kéo dữ liệu qua lại với 1 file Google Sheet, dùng cơ chế **Service Account** của Google (không cần bạn tự đăng nhập Google mỗi lần, chạy được cả trên Hostinger).

Đây là đồng bộ **theo yêu cầu** (bấm nút để đồng bộ), không phải đồng bộ real-time tự động liên tục — mỗi bảng dữ liệu (phòng, khách thuê, hợp đồng, hóa đơn, đặt phòng, chi phí, ticket, nhắc nhở, tài khoản ngân hàng) tương ứng với 1 tab trong Google Sheet.

## Chuẩn bị (làm 1 lần, trên tài khoản Google của bạn)

1. Vào [Google Cloud Console](https://console.cloud.google.com/), tạo một **Project** mới (hoặc dùng project có sẵn).
2. Vào **APIs & Services → Library**, tìm **Google Sheets API** và bấm **Enable**.
3. Vào **APIs & Services → Credentials → Create Credentials → Service Account**. Đặt tên tùy ý, bấm **Create and Continue** → **Done**.
4. Mở service account vừa tạo → tab **Keys → Add Key → Create new key** → chọn **JSON** → tải file JSON về máy.
5. Mở file JSON vừa tải, copy toàn bộ nội dung.
6. Tạo (hoặc mở) Google Sheet bạn muốn dùng để đồng bộ. Bấm **Share**, thêm địa chỉ email của service account (dạng `ten-service-account@ten-project.iam.gserviceaccount.com`, xem trong file JSON ở trường `client_email`) với quyền **Editor**.
7. Copy **Spreadsheet ID**: nằm trong URL của Google Sheet, giữa `/d/` và `/edit`, ví dụ:
   `https://docs.google.com/spreadsheets/d/1AbCDefGhIjKLmnoPQRsTUvWxyz1234567890/edit` → ID là `1AbCDefGhIjKLmnoPQRsTUvWxyz1234567890`.

## Cấu hình trong phần mềm

1. Đăng nhập bằng tài khoản **Quản trị**.
2. Vào menu **Cài đặt**.
3. Dán toàn bộ nội dung file JSON vào ô "Service Account JSON".
4. Dán Spreadsheet ID vào ô tương ứng.
5. Bấm **Lưu cấu hình**.

## Sử dụng

- **Đẩy toàn bộ dữ liệu lên Sheet**: ghi đè toàn bộ các tab trên Google Sheet bằng dữ liệu hiện tại trong phần mềm. Dùng khi muốn Sheet phản ánh đúng dữ liệu mới nhất.
- **Kéo toàn bộ dữ liệu từ Sheet**: đọc từng tab trên Sheet và cập nhật ngược lại vào phần mềm — dòng nào có cột `id` khớp với dữ liệu sẵn có sẽ được **cập nhật**, dòng không có `id` khớp sẽ được **thêm mới**.
- Có thể đồng bộ riêng từng bảng (phòng, khách thuê, hợp đồng...) thay vì tất cả cùng lúc.

## Lưu ý quan trọng

- **Không xóa cột `id` và không đổi thứ tự dòng tiêu đề** (dòng 1) trên Sheet nếu muốn dùng chức năng "Kéo về" — hệ thống dựa vào cột `id` để biết dòng nào là cập nhật, dòng nào là thêm mới.
- Trước khi "Kéo về" lần đầu, nên **sao lưu CSDL** (export qua phpMyAdmin) vì thao tác này có thể ghi đè dữ liệu.
- File Service Account JSON có quyền truy cập Google Sheet đã share — bảo mật như một mật khẩu, không chia sẻ cho người không tin cậy.
- Đây không phải đồng bộ tức thời (real-time). Muốn dữ liệu trên Sheet luôn mới nhất, cần chủ động bấm "Đẩy lên" sau khi có thay đổi quan trọng.
