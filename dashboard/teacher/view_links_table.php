<?php
/**
 * Test Student-Teacher Links Table
 * اختبار جدول الروابط بين الطلاب والمعلمين
 */

require_once '../../config/database.php';
global $pdo;

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head><meta charset='UTF-8'><title>جدول الروابط</title>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th { background: #667eea; color: white; padding: 10px; text-align: right; }
    td { padding: 8px; border-bottom: 1px solid #ddd; }
    .success { background: #c8e6c9; border-left: 4px solid #4caf50; padding: 15px; margin: 10px 0; }
    .warning { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 10px 0; }
    .error { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 10px 0; }
    .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px; cursor: pointer; border: none; }
    .btn-danger { background: #f44336; }
    code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style></head><body>";

echo "<h1>🔗 جدول الروابط: student_teacher_links</h1>";

// 1. التحقق من وجود الجدول
echo "<div class='section'>";
echo "<h2>1. التحقق من وجود الجدول</h2>";

try {
    $check_table = $pdo->query("SHOW TABLES LIKE 'student_teacher_links'");
    if ($check_table->rowCount() > 0) {
        echo "<div class='success'>✅ جدول student_teacher_links موجود</div>";
        
        // عرض بنية الجدول
        $structure = $pdo->query("DESCRIBE student_teacher_links")->fetchAll();
        echo "<h3>بنية الجدول:</h3>";
        echo "<table><tr><th>العمود</th><th>النوع</th><th>يقبل NULL</th><th>Key</th></tr>";
        foreach ($structure as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>❌ جدول student_teacher_links غير موجود!</div>";
        exit;
    }
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: {$e->getMessage()}</div>";
    exit;
}
echo "</div>";

// 2. عرض جميع الروابط
echo "<div class='section'>";
echo "<h2>2. جميع الروابط الحالية</h2>";

$links = $pdo->query("
    SELECT 
        l.id,
        l.student_id,
        CONCAT(s.nom, ' ', s.prenom) as student_name,
        l.teacher_id,
        CONCAT(t.nom, ' ', t.prenom) as teacher_name,
        t.teacher_code,
        l.subject_id,
        sub.name as subject_name,
        l.status,
        l.linked_at,
        l.created_at
    FROM student_teacher_links l
    LEFT JOIN users s ON l.student_id = s.id
    LEFT JOIN users t ON l.teacher_id = t.id
    LEFT JOIN subjects sub ON l.subject_id = sub.id
    ORDER BY l.created_at DESC
")->fetchAll();

$total_links = count($links);
$active_links = count(array_filter($links, fn($l) => $l['status'] === 'active'));

echo "<div class='warning'>";
echo "<strong>📊 الإحصائيات:</strong><br>";
echo "- إجمالي الروابط: <strong>{$total_links}</strong><br>";
echo "- الروابط النشطة: <strong>{$active_links}</strong>";
echo "</div>";

if (!empty($links)) {
    echo "<table>";
    echo "<tr><th>ID</th><th>الطالب</th><th>المعلم</th><th>كود المعلم</th><th>المادة</th><th>الحالة</th><th>تاريخ الربط</th><th>إجراء</th></tr>";
    foreach ($links as $link) {
        $status_color = $link['status'] === 'active' ? '#4caf50' : '#f44336';
        echo "<tr>";
        echo "<td>{$link['id']}</td>";
        echo "<td><strong>{$link['student_name']}</strong> (#{$link['student_id']})</td>";
        echo "<td><strong>{$link['teacher_name']}</strong> (#{$link['teacher_id']})</td>";
        echo "<td><code>{$link['teacher_code']}</code></td>";
        echo "<td>{$link['subject_name']}</td>";
        echo "<td><span style='color: {$status_color}; font-weight: bold;'>{$link['status']}</span></td>";
        echo "<td>" . date('Y-m-d H:i', strtotime($link['linked_at'])) . "</td>";
        echo "<td><a href='?delete_link={$link['id']}' class='btn btn-danger' onclick='return confirm(\"حذف هذا الرابط؟\")' style='padding: 5px 10px; font-size: 12px;'>حذف</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: #999; text-align: center; padding: 20px;'>لا توجد روابط حالياً</p>";
}
echo "</div>";

// 3. عرض الروابط حسب المعلم
echo "<div class='section'>";
echo "<h2>3. الروابط حسب المعلم</h2>";

$teachers_with_links = $pdo->query("
    SELECT 
        t.id,
        CONCAT(t.nom, ' ', t.prenom) as name,
        t.teacher_code,
        COUNT(l.id) as link_count
    FROM users t
    LEFT JOIN student_teacher_links l ON t.id = l.teacher_id AND l.status = 'active'
    WHERE t.role IN ('enseignant', 'supervisor_subject')
    GROUP BY t.id, t.nom, t.prenom, t.teacher_code
    HAVING link_count > 0
    ORDER BY link_count DESC
")->fetchAll();

if (!empty($teachers_with_links)) {
    echo "<table>";
    echo "<tr><th>ID المعلم</th><th>الاسم</th><th>الكود</th><th>عدد الطلاب</th><th>إجراء</th></tr>";
    foreach ($teachers_with_links as $t) {
        echo "<tr>";
        echo "<td>{$t['id']}</td>";
        echo "<td><strong>{$t['name']}</strong></td>";
        echo "<td><code>{$t['teacher_code']}</code></td>";
        echo "<td><span style='background: #4caf50; color: white; padding: 3px 10px; border-radius: 12px;'>{$t['link_count']}</span></td>";
        echo "<td><a href='?delete_teacher_links={$t['id']}' class='btn btn-danger' style='padding: 5px 10px; font-size: 12px;'>حذف كل الروابط</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: #999;'>لا يوجد معلمون بروابط نشطة</p>";
}
echo "</div>";

// 4. معالجة حذف رابط محدد
if (isset($_GET['delete_link'])) {
    $link_id = intval($_GET['delete_link']);
    
    try {
        $delete = $pdo->prepare("DELETE FROM student_teacher_links WHERE id = ?");
        $delete->execute([$link_id]);
        
        echo "<div class='success'>✅ تم حذف الرابط #{$link_id} بنجاح! <a href='?' class='btn' style='margin: 5px;'>تحديث</a></div>";
    } catch (PDOException $e) {
        echo "<div class='error'>❌ خطأ: {$e->getMessage()}</div>";
    }
}

// 5. معالجة حذف جميع روابط معلم
if (isset($_GET['delete_teacher_links'])) {
    $teacher_id = intval($_GET['delete_teacher_links']);
    
    if (isset($_GET['confirm'])) {
        try {
            $delete = $pdo->prepare("DELETE FROM student_teacher_links WHERE teacher_id = ?");
            $delete->execute([$teacher_id]);
            $count = $delete->rowCount();
            
            echo "<div class='success'>✅ تم حذف {$count} رابط للمعلم #{$teacher_id} بنجاح! <a href='?' class='btn' style='margin: 5px;'>تحديث</a></div>";
        } catch (PDOException $e) {
            echo "<div class='error'>❌ خطأ: {$e->getMessage()}</div>";
        }
    } else {
        $teacher = $pdo->prepare("SELECT CONCAT(nom, ' ', prenom) as name FROM users WHERE id = ?");
        $teacher->execute([$teacher_id]);
        $t = $teacher->fetch();
        
        $count = $pdo->prepare("SELECT COUNT(*) as count FROM student_teacher_links WHERE teacher_id = ?");
        $count->execute([$teacher_id]);
        $link_count = $count->fetch()['count'];
        
        echo "<div class='warning'>";
        echo "<h3>⚠️ تأكيد الحذف</h3>";
        echo "<p>هل تريد حذف <strong>{$link_count}</strong> رابط للمعلم <strong>{$t['name']}</strong>؟</p>";
        echo "<a href='?delete_teacher_links={$teacher_id}&confirm=1' class='btn btn-danger'>تأكيد الحذف</a>";
        echo "<a href='?' class='btn' style='background: #999;'>إلغاء</a>";
        echo "</div>";
    }
}

// 6. أدوات مفيدة
echo "<div class='section'>";
echo "<h2>🔧 أدوات</h2>";
echo "<a href='my-code.php' class='btn'>صفحة المعلم</a>";
echo "<a href='debug_teacher_issue.php' class='btn'>تشخيص المشاكل</a>";
echo "<a href='?' class='btn' style='background: #999;'>تحديث</a>";
echo "</div>";

echo "</body></html>";
