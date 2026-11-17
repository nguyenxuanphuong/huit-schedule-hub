CREATE DATABASE IF NOT EXISTS `huit_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `huit_db`;

DROP TABLE IF EXISTS `schedules`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `branches`;

CREATE TABLE `branches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `phone` VARCHAR(20) DEFAULT NULL,
    `expertise` VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `subject_name` VARCHAR(150) NOT NULL,
    `room` VARCHAR(50) NOT NULL,
    `day_of_week` TINYINT NOT NULL COMMENT '2=Thu 2 ... 7=Thu 7',
    `shift_or_periods` VARCHAR(50) NOT NULL,
    CONSTRAINT `fk_schedules_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_schedules_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `branches` (`branch_name`, `address`) VALUES
('Cơ sở chính Tân Phú', '140 Lê Trọng Tấn, P. Tây Thạnh, Q. Tân Phú, TP.HCM'),
('Trung tâm Thực hành Phòng máy', '93 Tân Kỳ Tân Quý, P. Tân Sơn Nhì, Q. Tân Phú, TP.HCM'),
('Cơ sở Liên kết Tân Bình', '245 Cộng Hòa, P. 13, Q. Tân Bình, TP.HCM');

INSERT INTO `teachers` (`full_name`, `email`, `phone`, `expertise`) VALUES
('ThS. Nguyễn Minh Tuấn', 'tuan.nm@huit.edu.vn', '0901234567', 'Lập trình Web PHP'),
('TS. Trần Thị Lan Anh', 'anh.ttl@huit.edu.vn', '0902345678', 'Cơ sở dữ liệu'),
('ThS. Lê Hoàng Phúc', 'phuc.lh@huit.edu.vn', '0903456789', 'Mạng máy tính'),
('ThS. Phạm Ngọc Hân', 'han.pn@huit.edu.vn', '0904567890', 'Trí tuệ nhân tạo'),
('TS. Võ Quốc Bảo', 'bao.vq@huit.edu.vn', '0905678901', 'Kiểm thử phần mềm');

INSERT INTO `schedules` (`teacher_id`, `branch_id`, `subject_name`, `room`, `day_of_week`, `shift_or_periods`) VALUES
(1, 1, 'Lập trình Web PHP nâng cao', 'A201', 2, 'Sáng (Tiết 1-5)'),
(2, 2, 'Hệ quản trị Cơ sở dữ liệu', 'PM04', 2, 'Chiều (Tiết 7-11)'),
(3, 1, 'Quản trị mạng Linux', 'B105', 3, 'Sáng (Tiết 1-5)'),
(4, 2, 'Học máy ứng dụng', 'PM02', 4, 'Sáng (Tiết 1-5)'),
(5, 3, 'Kiểm thử tự động Selenium', 'C302', 5, 'Chiều (Tiết 7-11)'),
(1, 2, 'Phát triển ứng dụng Laravel', 'PM01', 6, 'Sáng (Tiết 1-5)');
