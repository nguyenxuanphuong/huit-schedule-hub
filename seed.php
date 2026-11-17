<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

use Faker\Factory;

$faker = Factory::create('vi_VN');

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE schedules');
    $pdo->exec('TRUNCATE TABLE teachers');
    $pdo->exec('TRUNCATE TABLE branches');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // 1. Tạo dữ liệu cơ sở HUIT
    $branchesData = [
        ['Cơ sở chính Tân Phú', '140 Lê Trọng Tấn, P. Tây Thạnh, Q. Tân Phú, TP.HCM'],
        ['Trung tâm Thực hành Phòng máy', '93 Tân Kỳ Tân Quý, P. Tân Sơn Nhì, Q. Tân Phú, TP.HCM'],
        ['Cơ sở Liên kết Tân Bình', '245 Cộng Hòa, P. 13, Q. Tân Bình, TP.HCM'],
        ['Trung tâm Đào tạo Khu B', '12 Chế Lan Viên, P. Tây Thạnh, Q. Tân Phú, TP.HCM'],
    ];

    $stmtBranch = $pdo->prepare('INSERT INTO branches (branch_name, address) VALUES (?, ?)');
    $branchIds = [];
    foreach ($branchesData as $b) {
        $stmtBranch->execute($b);
        $branchIds[] = (int) $pdo->lastInsertId();
    }

    // 2. Tạo danh sách giảng viên mẫu bằng Faker
    $expertises = [
        'Lập trình Web PHP',
        'Cơ sở dữ liệu',
        'Mạng máy tính',
        'Trí tuệ nhân tạo',
        'Kiểm thử phần mềm',
        'Phát triển ứng dụng di động',
    ];

    $stmtTeacher = $pdo->prepare(
        'INSERT INTO teachers (full_name, email, phone, expertise) VALUES (?, ?, ?, ?)'
    );
    $teacherIds = [];
    for ($i = 1; $i <= 12; $i++) {
        $name = ($i % 3 === 0 ? 'TS. ' : 'ThS. ') . $faker->name();
        $email = 'gv' . $i . '_' . $faker->unique()->userName() . '@huit.edu.vn';
        $phone = '09' . $faker->numerify('########');
        $exp = $expertises[array_rand($expertises)];
        $stmtTeacher->execute([$name, $email, $phone, $exp]);
        $teacherIds[] = (int) $pdo->lastInsertId();
    }

    // 3. Sinh lịch dạy mẫu (đảm bảo không trùng giảng viên trong cùng khung giờ)
    $subjectsByExpertise = [
        'Lập trình Web PHP'           => ['Lập trình Web PHP & MySQL', 'Phát triển ứng dụng Laravel'],
        'Cơ sở dữ liệu'               => ['Hệ quản trị CSDL SQL Server', 'Cơ sở dữ liệu NoSQL'],
        'Mạng máy tính'               => ['Quản trị mạng Linux', 'An toàn hệ thống mạng'],
        'Trí tuệ nhân tạo'            => ['Học máy ứng dụng', 'Thị giác máy tính'],
        'Kiểm thử phần mềm'           => ['Kiểm thử phần mềm tự động', 'Đảm bảo chất lượng phần mềm'],
        'Phát triển ứng dụng di động' => ['Lập trình Flutter đa nền tảng', 'Phát triển ứng dụng Android'],
    ];

    $days   = [2, 3, 4, 5, 6, 7];
    $shifts = ['Sáng (Tiết 1-5)', 'Chiều (Tiết 7-11)', 'Tối (Tiết 13-15)'];
    $rooms  = ['A201', 'A305', 'B102', 'PM01', 'PM03', 'C204'];

    $teachersRows = $pdo->query('SELECT id, expertise FROM teachers')->fetchAll();
    $usedTeacherSlots = [];
    $usedRoomSlots    = [];

    $stmtSched = $pdo->prepare(
        'INSERT INTO schedules (teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $inserted = 0;
    for ($attempt = 0; $attempt < 150 && $inserted < 24; $attempt++) {
        $teacher = $teachersRows[array_rand($teachersRows)];
        $tId     = (int) $teacher['id'];
        $bId     = $branchIds[array_rand($branchIds)];
        $day     = $days[array_rand($days)];
        $shift   = $shifts[array_rand($shifts)];
        $room    = $rooms[array_rand($rooms)];

        $tKey = "{$tId}_{$day}_{$shift}";
        $rKey = "{$bId}_{$room}_{$day}_{$shift}";

        if (isset($usedTeacherSlots[$tKey]) || isset($usedRoomSlots[$rKey])) {
            continue;
        }

        $subList = $subjectsByExpertise[$teacher['expertise']] ?? ['Chuyên đề Công nghệ thông tin'];
        $subject = $subList[array_rand($subList)];

        $stmtSched->execute([$tId, $bId, $subject, $room, $day, $shift]);
        $usedTeacherSlots[$tKey] = true;
        $usedRoomSlots[$rKey]    = true;
        $inserted++;
    }

    echo "Đã khởi tạo dữ liệu mẫu thành công: " . count($branchIds) . " cơ sở, " . count($teacherIds) . " giảng viên, {$inserted} lịch dạy.\n";
} catch (Throwable $e) {
    echo "Lỗi khi seed dữ liệu: " . $e->getMessage() . "\n";
}
