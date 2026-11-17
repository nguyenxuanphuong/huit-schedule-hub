<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/db.php';

$dayLabels = [
    2 => 'Thứ 2',
    3 => 'Thứ 3',
    4 => 'Thứ 4',
    5 => 'Thứ 5',
    6 => 'Thứ 6',
    7 => 'Thứ 7',
];

if (isset($_GET['logout'])) {
    unset($_SESSION['lecturer_id']);
    header('Location: lecturer.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $stmt  = $pdo->prepare('SELECT * FROM teachers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $teacher = $stmt->fetch();
    if ($teacher) {
        $_SESSION['lecturer_id'] = (int) $teacher['id'];
        header('Location: lecturer.php');
        exit;
    }
    $loginError = 'Không tìm thấy giảng viên với email này.';
}

// Hỗ trợ truy cập nhanh qua ?teacher_id= hoặc qua session
$currentTeacherId = (int) ($_GET['teacher_id'] ?? ($_SESSION['lecturer_id'] ?? 0));
$currentTeacher   = null;
$mySchedules      = [];

if ($currentTeacherId > 0) {
    $stmtT = $pdo->prepare('SELECT * FROM teachers WHERE id = ?');
    $stmtT->execute([$currentTeacherId]);
    $currentTeacher = $stmtT->fetch() ?: null;

    if ($currentTeacher) {
        $stmtS = $pdo->prepare("
            SELECT s.*, b.branch_name, b.address
            FROM schedules s
            JOIN branches b ON b.id = s.branch_id
            WHERE s.teacher_id = ?
            ORDER BY s.day_of_week ASC, s.shift_or_periods ASC
        ");
        $stmtS->execute([$currentTeacherId]);
        $mySchedules = $stmtS->fetchAll();
    }
}

$allTeachers = $pdo->query('SELECT id, full_name, email FROM teachers ORDER BY full_name ASC')->fetchAll();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cổng Giảng viên - HUIT Schedule Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">HUIT Schedule Hub</a>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-light btn-sm">Quản trị & Phân lịch</a>
            <a href="lecturer.php" class="btn btn-light btn-sm fw-semibold">Cổng Giảng viên</a>
        </div>
    </div>
</nav>

<div class="container py-4">
<?php if (!$currentTeacher): ?>
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">Đăng nhập Cổng thông tin Giảng viên</div>
                <div class="card-body">
                    <?php if ($loginError !== ''): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($loginError) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Giảng viên</label>
                            <input type="email" name="email" class="form-control" required placeholder="vd: tuan.nm@huit.edu.vn">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-semibold">Xem lịch dạy cá nhân</button>
                    </form>
                    <hr>
                    <div class="small text-muted mb-2">Hoặc chọn nhanh tài khoản mẫu để kiểm thử:</div>
                    <div class="list-group list-group-flush small">
                        <?php foreach (array_slice($allTeachers, 0, 5) as $t): ?>
                            <a href="lecturer.php?teacher_id=<?= (int) $t['id'] ?>" class="list-group-item list-group-item-action">
                                <?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['email']) ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-bold">Lịch giảng dạy tuần: <?= htmlspecialchars($currentTeacher['full_name']) ?></h4>
            <small class="text-muted">Chuyên môn: <?= htmlspecialchars($currentTeacher['expertise']) ?> | <?= htmlspecialchars($currentTeacher['email']) ?></small>
        </div>
        <a href="lecturer.php?logout=1" class="btn btn-outline-danger btn-sm">Đổi giảng viên / Đăng xuất</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Thứ</th>
                        <th>Buổi / Tiết</th>
                        <th>Học phần</th>
                        <th>Phòng</th>
                        <th>Cơ sở & Địa chỉ cần đến</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($mySchedules)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Tuần này bạn chưa có lịch giảng dạy nào.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($mySchedules as $s): ?>
                        <tr>
                            <td><span class="badge bg-primary"><?= $dayLabels[(int) $s['day_of_week']] ?? ('Thứ ' . $s['day_of_week']) ?></span></td>
                            <td><?= htmlspecialchars($s['shift_or_periods']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($s['subject_name']) ?></td>
                            <td><span class="badge bg-dark">Phòng <?= htmlspecialchars($s['room']) ?></span></td>
                            <td>
                                <div class="fw-semibold text-primary"><?= htmlspecialchars($s['branch_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($s['address']) ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
</div>
</body>
</html>
