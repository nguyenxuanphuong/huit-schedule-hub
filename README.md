# HUIT Schedule Hub

Ứng dụng web **Quản lý và phân lịch giảng dạy đa cơ sở thông minh** dành cho Phòng Đào tạo và Giảng viên.

## Tính năng chính
- **Phân lịch & Chống trùng lịch**: Kiểm tra trùng lịch giảng viên, trùng phòng học và cảnh báo tối ưu di chuyển khi giảng viên phải dạy nhiều cơ sở trong cùng một ngày.
- **Gợi ý giảng viên phù hợp**: Tự động lọc danh sách giảng viên đúng chuyên môn và đang trống lịch tại khung giờ được chọn.
- **Cổng thông tin Giảng viên**: Tra cứu thời khóa biểu cá nhân theo tuần, hiển thị rõ phòng học và địa chỉ cơ sở cần đến.
- **Tổng quan & Thống kê**: Lọc nhanh theo cơ sở hoặc giảng viên, kèm biểu đồ phân bổ số buổi giảng dạy (Chart.js).

## Hướng dẫn cài đặt (Laragon)

1. **Giải nén vào thư mục web của Laragon**:
   - Đặt mã nguồn tại `C:\laragon\www\huit-schedule`.
   - Khởi động / Reload Laragon để truy cập qua tên miền `http://huit-schedule.test`.

2. **Cài đặt thư viện Composer**:
   ```bash
   composer install
   ```

3. **Cấu hình biến môi trường `.env`**:
   ```bash
   cp .env.example .env
   ```
   Cập nhật thông số MySQL trong `.env`:
   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=huit_db
   DB_USER=root
   DB_PASS=
   ```

4. **Khởi tạo cơ sở dữ liệu**:
   - Mở HeidiSQL (hoặc phpMyAdmin) và import file `huit_db.sql`.
   - Hoặc chạy script sinh dữ liệu mẫu bằng Faker:
     ```bash
     php seed.php
     ```

5. **Truy cập ứng dụng**:
   - Trang Quản trị & Phân lịch: `http://huit-schedule.test/index.php`
   - Cổng Giảng viên: `http://huit-schedule.test/lecturer.php`

## Giấy phép
Dự án phát hành theo giấy phép [MIT](LICENSE).
