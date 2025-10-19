<?php
/**
 * Verify Teacher-Student Links After Code Update
 * التحقق من ارتباطات المعلم-الطالب بعد تحديث الكود
 */

require_once '../../config/database.php';
global $pdo;

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>التحقق من الارتباطات</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .success { background: #c8e6c9; padding: 20px; border-radius: 10px; margin: 20px 0; border: 2px solid #4caf50; }
    .info { background: #e3f2fd; padding: 20px; border-radius: 10px; margin: 20px 0; border: 2px solid #2196f3; }
    table { width: 100%; border-collapse: collapse; background: white; margin: 10px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    th { background: #667eea; color: white; padding: 12px; text-align: right; }
    td { padding: 10px; border-bottom: 1px solid #ddd; }
    tr:hover { background: #f5f5f5; }
    .match { background: #c8e6c9; font-weight: bold; }
    .no-match { background: #ffcdd2; }
    h1 { color: #333; }
    h2 { color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
    .badge { display: inline-block; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold; }
    .badge-success { background: #4caf50; color: white; }
    .badge-warning { background: #ff9800; color: white; }
</style>";
echo "</head>";
echo "<body>";

echo "<h1>✅ التحقق من نجاح تحديث كود المعلم</h1>";

// 1. عرض جميع المعلمين مع أكوادهم الحالية
echo "<h2>1️⃣ المعلمون وأكوادهم الحالية</h2>";
$teachers_stmt = $pdo->query("
    SELECT 
        u.id, 
        CONCAT(u.nom, ' ', u.prenom) as name,
        u.teacher_code,
        u.role,
        s.name as subject_name,
        COUNT(DISTINCT students.id) as student_count
    FROM users u
    LEFT JOIN subjects s ON u.subject_id = s.id
    LEFT JOIN users students ON students.connected_teacher_code = u.teacher_code AND students.role = 'etudiant'
    WHERE u.role IN ('enseignant', 'supervisor_subject')
    GROUP BY u.id, u.nom, u.prenom, u.teacher_code, u.role, s.name
    ORDER BY u.id
");

echo "<table>";
echo "<tr><th>ID المعلم</th><th>الاسم</th><th>الدور</th><th>المادة</th><th>الكود الحالي</th><th>عدد الطلاب</th></tr>";

$teachers_data = [];
while ($teacher = $teachers_stmt->fetch()) {
    $teachers_data[$teacher['id']] = $teacher;
    
    $badge_class = $teacher['student_count'] > 0 ? 'badge-success' : 'badge-warning';
    
    echo "<tr>";
    echo "<td>{$teacher['id']}</td>";
    echo "<td><strong>{$teacher['name']}</strong></td>";
    echo "<td>{$teacher['role']}</td>";
    echo "<td>" . ($teacher['subject_name'] ?? 'N/A') . "</td>";
    echo "<td><code style='background: #f5f5f5; padding: 5px 10px; border-radius: 5px; font-weight: bold;'>" . ($teacher['teacher_code'] ?? 'NULL') . "</code></td>";
    echo "<td><span class='badge {$badge_class}'>{$teacher['student_count']} طالب</span></td>";
    echo "</tr>";
}
echo "</table>";

// 2. عرض الطلاب وارتباطاتهم مع التحقق
echo "<h2>2️⃣ الطلاب وارتباطاتهم (مع التحقق من التطابق)</h2>";
$students_stmt = $pdo->query("
    SELECT 
        s.id,
        CONCAT(s.nom, ' ', s.prenom) as student_name,
        s.connected_teacher_code,
        s.stage_id,
        s.level_id,
        t.id as teacher_id,
        CONCAT(t.nom, ' ', t.prenom) as teacher_name,
        t.teacher_code as teacher_current_code,
        CASE 
            WHEN s.connected_teacher_code = t.teacher_code THEN 'match'
            WHEN s.connected_teacher_code IS NULL THEN 'no_code'
            ELSE 'mismatch'
        END as status
    FROM users s
    LEFT JOIN users t ON s.connected_teacher_code = t.teacher_code
    WHERE s.role = 'etudiant'
    ORDER BY status, s.id
");

echo "<table>";
echo "<tr><th>ID الطالب</th><th>اسم الطالب</th><th>كود المعلم المحفوظ</th><th>المعلم المرتبط</th><th>كود المعلم الحالي</th><th>الحالة</th></tr>";

$match_count = 0;
$mismatch_count = 0;
$no_code_count = 0;

while ($student = $students_stmt->fetch()) {
    $status_class = '';
    $status_text = '';
    
    switch ($student['status']) {
        case 'match':
            $status_class = 'match';
            $status_text = '✅ مرتبط بنجاح';
            $match_count++;
            break;
        case 'mismatch':
            $status_class = 'no-match';
            $status_text = '❌ عدم تطابق';
            $mismatch_count++;
            break;
        case 'no_code':
            $status_class = '';
            $status_text = '⚪ غير مرتبط';
            $no_code_count++;
            break;
    }
    
    echo "<tr class='{$status_class}'>";
    echo "<td>{$student['id']}</td>";
    echo "<td><strong>{$student['student_name']}</strong></td>";
    echo "<td><code style='background: #fff3e0; padding: 3px 8px; border-radius: 3px;'>" . ($student['connected_teacher_code'] ?? 'NULL') . "</code></td>";
    echo "<td>" . ($student['teacher_name'] ?? '<em style="color: #999;">لا يوجد</em>') . "</td>";
    echo "<td><code style='background: #e8f5e9; padding: 3px 8px; border-radius: 3px;'>" . ($student['teacher_current_code'] ?? 'N/A') . "</code></td>";
    echo "<td><strong>{$status_text}</strong></td>";
    echo "</tr>";
}
echo "</table>";

// 3. ملخص الإحصائيات
echo "<div class='success'>";
echo "<h2>📊 ملخص الإحصائيات</h2>";
echo "<ul style='font-size: 18px; line-height: 2;'>";
echo "<li>✅ <strong>طلاب مرتبطون بنجاح:</strong> {$match_count} طالب</li>";
echo "<li>❌ <strong>طلاب بأكواد غير متطابقة:</strong> {$mismatch_count} طالب</li>";
echo "<li>⚪ <strong>طلاب غير مرتبطين:</strong> {$no_code_count} طالب</li>";
echo "</ul>";

if ($mismatch_count > 0) {
    echo "<div style='background: #ffebee; padding: 15px; border-radius: 5px; margin-top: 15px; border-left: 4px solid #f44336;'>";
    echo "<strong>⚠️ تحذير:</strong> هناك {$mismatch_count} طالب بأكواد غير متطابقة. قد يكون هناك خطأ في التحديث.";
    echo "</div>";
} else {
    echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 5px; margin-top: 15px; border-left: 4px solid #4caf50;'>";
    echo "<strong>✅ ممتاز!</strong> جميع الارتباطات صحيحة ولا توجد مشاكل.";
    echo "</div>";
}
echo "</div>";

// 4. تفاصيل كل معلم مع طلابه
echo "<h2>3️⃣ تفاصيل كل معلم مع طلابه</h2>";

foreach ($teachers_data as $teacher) {
    if ($teacher['teacher_code']) {
        echo "<div class='info'>";
        echo "<h3>👨‍🏫 {$teacher['name']} ({$teacher['role']})</h3>";
        echo "<p><strong>الكود الحالي:</strong> <code style='background: #fff; padding: 5px 10px; border-radius: 5px; font-size: 16px;'>{$teacher['teacher_code']}</code></p>";
        echo "<p><strong>المادة:</strong> " . ($teacher['subject_name'] ?? 'N/A') . "</p>";
        
        // جلب طلاب هذا المعلم
        $students_of_teacher = $pdo->prepare("
            SELECT id, CONCAT(nom, ' ', prenom) as name, connected_teacher_code
            FROM users
            WHERE connected_teacher_code = ? AND role = 'etudiant'
            ORDER BY nom
        ");
        $students_of_teacher->execute([$teacher['teacher_code']]);
        $teacher_students = $students_of_teacher->fetchAll();
        
        if (!empty($teacher_students)) {
            echo "<p><strong>الطلاب المرتبطون ({$teacher['student_count']}):</strong></p>";
            echo "<ul>";
            foreach ($teacher_students as $student) {
                $match_icon = $student['connected_teacher_code'] === $teacher['teacher_code'] ? '✅' : '❌';
                echo "<li>{$match_icon} {$student['name']} (ID: {$student['id']}) - الكود: <code>{$student['connected_teacher_code']}</code></li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: #999;'><em>لا يوجد طلاب مرتبطون حالياً</em></p>";
        }
        echo "</div>";
    }
}

// 5. اختبار سريع
echo "<div class='info'>";
echo "<h2>🧪 اختبار سريع</h2>";
echo "<p>لاختبار تحديث كود معلم محدد:</p>";
echo "<a href='test_teacher_code_update.php' style='display: inline-block; padding: 12px 25px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>افتح صفحة الاختبار</a>";
echo "</div>";

echo "</body>";
echo "</html>";
