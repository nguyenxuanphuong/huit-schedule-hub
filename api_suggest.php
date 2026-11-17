<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

$subject   = trim($_GET['subject'] ?? '');
$day       = (int) ($_GET['day_of_week'] ?? 0);
$shift     = trim($_GET['shift_or_periods'] ?? '');
$branchId  = (int) ($_GET['branch_id'] ?? 0);
$excludeId = (int) ($_GET['exclude_id'] ?? 0);

if ($day < 2 || $day > 7 || $shift === '') {
    echo json_encode(['suggestions' => []]);
    exit;
}

// Tìm giảng viên KHÔNG bị trùng lịch ở khung giờ (day_of_week, shift_or_periods)
$sql = "
    SELECT t.id, t.full_name, t.expertise, t.email
    FROM teachers t
    WHERE t.id NOT IN (
        SELECT s.teacher_id
        FROM schedules s
        WHERE s.day_of_week = :day
          AND s.shift_or_periods = :shift
          AND s.id != :exclude_id
    )
    ORDER BY t.full_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':day'        => $day,
    ':shift'      => $shift,
    ':exclude_id' => $excludeId,
]);
$availableTeachers = $stmt->fetchAll();

// Kiểm tra giảng viên nào có lịch dạy ở cơ sở khác trong cùng ngày (để tối ưu di chuyển)
$otherBranchStmt = $pdo->prepare("
    SELECT DISTINCT s.teacher_id, b.branch_name
    FROM schedules s
    JOIN branches b ON b.id = s.branch_id
    WHERE s.day_of_week = :day
      AND s.branch_id != :branch_id
      AND s.id != :exclude_id
");
$otherBranchStmt->execute([
    ':day'        => $day,
    ':branch_id'  => $branchId,
    ':exclude_id' => $excludeId,
]);
$otherBranches = [];
foreach ($otherBranchStmt->fetchAll() as $row) {
    $otherBranches[(int) $row['teacher_id']][] = $row['branch_name'];
}

$suggestions = [];
$subjectLower = mb_strtolower($subject, 'UTF-8');

foreach ($availableTeachers as $t) {
    $expLower = mb_strtolower($t['expertise'], 'UTF-8');
    $isMatch = false;
    if ($subjectLower !== '') {
        $words = preg_split('/\s+/', $expLower);
        $hitCount = 0;
        foreach ($words as $w) {
            if (mb_strlen($w, 'UTF-8') >= 3 && mb_strpos($subjectLower, $w) !== false) {
                $hitCount++;
            }
        }
        $isMatch = ($hitCount >= 2) || (mb_strpos($subjectLower, $expLower) !== false);
    }

    $tId = (int) $t['id'];
    $suggestions[] = [
        'id'             => $tId,
        'full_name'      => $t['full_name'],
        'expertise'      => $t['expertise'],
        'expertise_match'=> $isMatch,
        'other_branches' => $otherBranches[$tId] ?? [],
    ];
}

// Ưu tiên người đúng chuyên môn và không phải di chuyển nhiều cơ sở lên đầu
usort($suggestions, static function (array $a, array $b): int {
    if ($a['expertise_match'] !== $b['expertise_match']) {
        return $b['expertise_match'] <=> $a['expertise_match'];
    }
    return count($a['other_branches']) <=> count($b['other_branches']);
});

echo json_encode(['suggestions' => $suggestions], JSON_UNESCAPED_UNICODE);
