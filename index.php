<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$dayLabels = [
    2 => 'Thứ 2',
    3 => 'Thứ 3',
    4 => 'Thứ 4',
    5 => 'Thứ 5',
    6 => 'Thứ 6',
    7 => 'Thứ 7',
];
$shifts = ['Sáng (Tiết 1-5)', 'Chiều (Tiết 7-11)', 'Tối (Tiết 13-15)'];

$flashSuccess = '';
$flashError   = '';
$flashWarning = '';

// Xử lý xóa lịch
if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM schedules WHERE id = ?');
    $stmt->execute([$delId]);
    $flashSuccess = 'Đã xóa lịch giảng dạy thành công.';
}

// Xử lý thêm / cập nhật lịch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int) ($_POST['id'] ?? 0);
    $teacherId   = (int) ($_POST['teacher_id'] ?? 0);
    $branchId    = (int) ($_POST['branch_id'] ?? 0);
    $subjectName = trim($_POST['subject_name'] ?? '');
    $room        = trim($_POST['room'] ?? '');
    $dayOfWeek   = (int) ($_POST['day_of_week'] ?? 0);
    $shift       = trim($_POST['shift_or_periods'] ?? '');

    if ($teacherId <= 0 || $branchId <= 0 || $subjectName === '' || $room === '' || $dayOfWeek < 2 || $dayOfWeek > 7 || $shift === '') {
        $flashError = 'Vui lòng nhập đầy đủ tất cả thông tin lịch dạy.';
    } else {
        // 1. Kiểm tra trùng lịch giảng viên
        $chkTeacher = $pdo->prepare("
            SELECT COUNT(*) FROM schedules
            WHERE teacher_id = ? AND day_of_week = ? AND shift_or_periods = ? AND id != ?
        ");
        $chkTeacher->execute([$teacherId, $dayOfWeek, $shift, $id]);

        // 2. Kiểm tra trùng phòng học tại cơ sở
        $chkRoom = $pdo->prepare("
            SELECT COUNT(*) FROM schedules
            WHERE branch_id = ? AND room = ? AND day_of_week = ? AND shift_or_periods = ? AND id != ?
        ");
        $chkRoom->execute([$branchId, $room, $dayOfWeek, $shift, $id]);

        if ((int) $chkTeacher->fetchColumn() > 0) {
            $flashError = 'Xung đột lịch: Giảng viên này đã có lịch dạy trong khung giờ đã chọn!';
        } elseif ((int) $chkRoom->fetchColumn() > 0) {
            $flashError = "Xung đột phòng: Phòng {$room} tại cơ sở này đã có lớp học trong khung giờ đã chọn!";
        } else {
            // Kiểm tra tối ưu di chuyển cơ sở trong cùng ngày
            $chkTravel = $pdo->prepare("
                SELECT DISTINCT b.branch_name
                FROM schedules s
                JOIN branches b ON b.id = s.branch_id
                WHERE s.teacher_id = ? AND s.day_of_week = ? AND s.branch_id != ? AND s.id != ?
            ");
            $chkTravel->execute([$teacherId, $dayOfWeek, $branchId, $id]);
            $otherBranches = $chkTravel->fetchAll(PDO::FETCH_COLUMN);

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE schedules
                    SET teacher_id = ?, branch_id = ?, subject_name = ?, room = ?, day_of_week = ?, shift_or_periods = ?
                    WHERE id = ?
                ");
                $stmt->execute([$teacherId, $branchId, $subjectName, $room, $dayOfWeek, $shift, $id]);
                $flashSuccess = 'Đã cập nhật lịch giảng dạy.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO schedules (teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$teacherId, $branchId, $subjectName, $room, $dayOfWeek, $shift]);
                $flashSuccess = 'Đã phân lịch giảng dạy mới thành công.';
            }

            if (!empty($otherBranches)) {
                $flashWarning = 'Lưu ý tối ưu di chuyển: Giảng viên này cũng có tiết dạy trong cùng ngày tại: ' . implode(', ', $otherBranches) . '.';
            }
        }
    }
}

// Lấy thông tin sửa nếu có ?edit=
$editItem = null;
if (isset($_GET['edit'])) {
    $stmtEdit = $pdo->prepare('SELECT * FROM schedules WHERE id = ?');
    $stmtEdit->execute([(int) $_GET['edit']]);
    $editItem = $stmtEdit->fetch() ?: null;
}

// Danh sách dropdown
$branches = $pdo->query('SELECT * FROM branches ORDER BY id ASC')->fetchAll();
$teachers = $pdo->query('SELECT * FROM teachers ORDER BY full_name ASC')->fetchAll();

// Bộ lọc
$filterBranch  = (int) ($_GET['branch_id'] ?? 0);
$filterTeacher = (int) ($_GET['teacher_id'] ?? 0);

$where  = [];
$params = [];
if ($filterBranch > 0) {
    $where[]  = 's.branch_id = ?';
    $params[] = $filterBranch;
}
if ($filterTeacher > 0) {
    $where[]  = 's.teacher_id = ?';
    $params[] = $filterTeacher;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sqlList = "
    SELECT s.*, t.full_name AS teacher_name, t.expertise, b.branch_name, b.address
    FROM schedules s
    JOIN teachers t ON t.id = s.teacher_id
    JOIN branches b ON b.id = s.branch_id
    {$whereSql}
    ORDER BY s.day_of_week ASC, s.shift_or_periods ASC
";
$stmtList = $pdo->prepare($sqlList);
$stmtList->execute($params);
$schedules = $stmtList->fetchAll();

// Dữ liệu biểu đồ phân bổ số buổi theo giảng viên
$chartRows = $pdo->query("
    SELECT t.full_name, COUNT(s.id) AS total_sessions
    FROM teachers t
    LEFT JOIN schedules s ON s.teacher_id = t.id
    GROUP BY t.id, t.full_name
    ORDER BY total_sessions DESC, t.full_name ASC
")->fetchAll();

$chartLabels = array_column($chartRows, 'full_name');
$chartCounts = array_map('intval', array_column($chartRows, 'total_sessions'));
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HUIT Schedule Hub - Quản lý & Phân lịch Đa cơ sở</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">HUIT Schedule Hub</a>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-light btn-sm fw-semibold">Quản trị & Phân lịch</a>
            <a href="lecturer.php" class="btn btn-outline-light btn-sm">Cổng Giảng viên</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php if ($flashSuccess !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flashWarning !== ''): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <?= htmlspecialchars($flashWarning) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flashError !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form Phân lịch & Gợi ý thông minh -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">
                    <?= $editItem ? 'Cập nhật lịch giảng dạy #' . (int) $editItem['id'] : 'Phân lịch giảng dạy mới' ?>
                </div>
                <div class="card-body">
                    <form method="POST" id="scheduleForm">
                        <input type="hidden" name="id" value="<?= (int) ($editItem['id'] ?? 0) ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tên học phần</label>
                            <input type="text" name="subject_name" id="subjectInput" class="form-control" required
                                   placeholder="VD: Lập trình Web PHP, Cơ sở dữ liệu..."
                                   value="<?= htmlspecialchars($editItem['subject_name'] ?? '') ?>">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Thứ</label>
                                <select name="day_of_week" id="daySelect" class="form-select" required>
                                    <?php foreach ($dayLabels as $num => $label): ?>
                                        <option value="<?= $num ?>" <?= ((int) ($editItem['day_of_week'] ?? 2) === $num) ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-semibold">Buổi / Tiết học</label>
                                <select name="shift_or_periods" id="shiftSelect" class="form-select" required>
                                    <?php foreach ($shifts as $s): ?>
                                        <option value="<?= htmlspecialchars($s) ?>" <?= (($editItem['shift_or_periods'] ?? '') === $s) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Cơ sở giảng dạy</label>
                                <select name="branch_id" id="branchSelect" class="form-select" required>
                                    <?php foreach ($branches as $b): ?>
                                        <option value="<?= (int) $b['id'] ?>" <?= ((int) ($editItem['branch_id'] ?? 0) === (int) $b['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['branch_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Phòng</label>
                                <input type="text" name="room" class="form-control" required placeholder="A201"
                                       value="<?= htmlspecialchars($editItem['room'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold mb-0">Giảng viên</label>
                                <button type="button" id="btnSuggest" class="btn btn-outline-primary btn-sm">
                                    Gợi ý giảng viên trống lịch
                                </button>
                            </div>
                            <select name="teacher_id" id="teacherSelect" class="form-select" required>
                                <option value="">-- Chọn giảng viên --</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>" <?= ((int) ($editItem['teacher_id'] ?? 0) === (int) $t['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['expertise']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="suggestBox" class="mt-2"></div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100 fw-semibold">
                                <?= $editItem ? 'Lưu thay đổi' : 'Thêm lịch dạy' ?>
                            </button>
                            <?php if ($editItem): ?>
                                <a href="index.php" class="btn btn-secondary">Hủy</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Biểu đồ phân bổ thời gian -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span>Biểu đồ phân bổ số buổi giảng dạy theo Giảng viên</span>
                    <span class="badge bg-primary"><?= count($schedules) ?> lịch hiển thị</span>
                </div>
                <div class="card-body">
                    <canvas id="scheduleChart" height="170"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Bộ lọc & Danh sách lịch -->
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-bold">Thời khóa biểu toàn trường</span>
            <form method="GET" class="d-flex flex-wrap gap-2">
                <select name="branch_id" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="0">Tất cả Cơ sở</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= ($filterBranch === (int) $b['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['branch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="teacher_id" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="0">Tất cả Giảng viên</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= ($filterTeacher === (int) $t['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filterBranch > 0 || $filterTeacher > 0): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">Xóa lọc</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Thứ / Buổi</th>
                        <th>Học phần</th>
                        <th>Giảng viên</th>
                        <th>Cơ sở & Phòng</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Chưa có lịch giảng dạy nào phù hợp bộ lọc.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schedules as $row): ?>
                        <tr>
                            <td>
                                <span class="badge bg-info text-dark"><?= $dayLabels[(int) $row['day_of_week']] ?? ('Thứ ' . $row['day_of_week']) ?></span>
                                <div class="small text-muted mt-1"><?= htmlspecialchars($row['shift_or_periods']) ?></div>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars($row['subject_name']) ?></td>
                            <td>
                                <div><?= htmlspecialchars($row['teacher_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['expertise']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($row['branch_name']) ?></span>
                                <span class="fw-semibold ms-1">Phòng <?= htmlspecialchars($row['room']) ?></span>
                            </td>
                            <td class="text-end">
                                <a href="index.php?edit=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">Sửa</a>
                                <a href="index.php?delete=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Xác nhận xóa lịch dạy này?')">Xóa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Biểu đồ Chart.js
const ctx = document.getElementById('scheduleChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Số buổi giảng dạy',
            data: <?= json_encode($chartCounts) ?>,
            backgroundColor: '#0d6efd',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// Gợi ý giảng viên phù hợp & không trùng lịch
document.getElementById('btnSuggest').addEventListener('click', async () => {
    const subject  = document.getElementById('subjectInput').value;
    const day      = document.getElementById('daySelect').value;
    const shift    = document.getElementById('shiftSelect').value;
    const branchId = document.getElementById('branchSelect').value;
    const editId   = document.querySelector('input[name="id"]').value;

    const url = `api_suggest.php?subject=${encodeURIComponent(subject)}&day_of_week=${day}&shift_or_periods=${encodeURIComponent(shift)}&branch_id=${branchId}&exclude_id=${editId}`;
    const res = await fetch(url);
    const data = await res.json();

    const box = document.getElementById('suggestBox');
    if (!data.suggestions || data.suggestions.length === 0) {
        box.innerHTML = '<div class="alert alert-warning py-2 small mb-0">Không còn giảng viên trống lịch trong khung giờ này.</div>';
        return;
    }

    let html = '<div class="list-group list-group-flush border rounded small" style="max-height:180px;overflow:auto;">';
    data.suggestions.slice(0, 6).forEach(t => {
        const badge = t.expertise_match
            ? '<span class="badge bg-success ms-1">Đúng chuyên môn</span>'
            : '<span class="badge bg-secondary ms-1">' + t.expertise + '</span>';
        const travel = t.other_branches.length > 0
            ? `<div class="text-warning" style="font-size:0.75rem;">Lưu ý: Cùng ngày có dạy tại ${t.other_branches.join(', ')}</div>`
            : '<div class="text-success" style="font-size:0.75rem;">Không cần chuyển cơ sở trong ngày</div>';
        html += `<button type="button" class="list-group-item list-group-item-action py-2" onclick="selectTeacher(${t.id})">
                    <strong>${t.full_name}</strong> ${badge}
                    ${travel}
                 </button>`;
    });
    html += '</div>';
    box.innerHTML = html;
});

function selectTeacher(id) {
    document.getElementById('teacherSelect').value = id;
}
</script>
</body>
</html>
