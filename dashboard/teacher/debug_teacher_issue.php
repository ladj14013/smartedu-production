<?php
/**
 * Debug Teacher Code Update Issue
 * تشخيص مشكلة تحديث كود المعلم
 */

session_start();
require_once '../../config/database.php';
global $pdo;

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head><meta charset='UTF-8'><title>تشخيص المشكلة</title>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th { background: #667eea; color: white; padding: 10px; text-align: right; }
    td { padding: 8px; border-bottom: 1px solid #ddd; }
    .error { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 10px 0; }
    .success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 10px 0; }
    .warning { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 10px 0; }
    code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
</style></head><body>";

echo "<h1>🔍 تشخيص مشكلة ارتباطات المعلم-الطالب</h1>";

// 1. التحقق من بنية جدول users
echo "<div class='section'>";
echo "<h2>1. التحقق من أعمدة جدول users</h2>";
$columns = $pdo->query("DESCRIBE users")->fetchAll();
$has_teacher_code = false;
$has_connected_teacher_code = false;

echo "<table><tr><th>اسم العمود</th><th>النوع</th><th>يقبل NULL</th></tr>";
foreach ($columns as $col) {
    echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td></tr>";
    if ($col['Field'] === 'teacher_code') $has_teacher_code = true;
    if ($col['Field'] === 'connected_teacher_code') $has_connected_teacher_code = true;
}
echo "</table>";

if (!$has_teacher_code) {
    echo "<div class='error'>❌ العمود teacher_code غير موجود!</div>";
} else {
    echo "<div class='success'>✅ العمود teacher_code موجود</div>";
}

if (!$has_connected_teacher_code) {
    echo "<div class='error'>❌ العمود connected_teacher_code غير موجود!</div>";
} else {
    echo "<div class='success'>✅ العمود connected_teacher_code موجود</div>";
}
echo "</div>";

// 2. عرض المعلمين مع تفاصيلهم
echo "<div class='section'>";
echo "<h2>2. المعلمون الحاليون</h2>";
$teachers = $pdo->query("
    SELECT 
        u.id,
        CONCAT(u.nom, ' ', u.prenom) as name,
        u.teacher_code,
        u.role,
        COUNT(DISTINCT s.id) as student_count
    FROM users u
    LEFT JOIN users s ON s.connected_teacher_code = u.teacher_code AND s.role = 'etudiant'
    WHERE u.role IN ('enseignant', 'supervisor_subject')
    GROUP BY u.id, u.nom, u.prenom, u.teacher_code, u.role
    ORDER BY u.id
")->fetchAll();

echo "<table>";
echo "<tr><th>ID</th><th>الاسم</th><th>الدور</th><th>teacher_code</th><th>عدد الطلاب</th><th>إجراء</th></tr>";
foreach ($teachers as $t) {
    $badge_color = $t['student_count'] > 0 ? '#4caf50' : '#999';
    echo "<tr>";
    echo "<td>{$t['id']}</td>";
    echo "<td><strong>{$t['name']}</strong></td>";
    echo "<td>{$t['role']}</td>";
    echo "<td><code style='font-weight: bold; color: #667eea;'>" . ($t['teacher_code'] ?? 'NULL') . "</code></td>";
    echo "<td><span style='background: {$badge_color}; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px;'>{$t['student_count']}</span></td>";
    echo "<td><a href='?debug_teacher={$t['id']}' class='btn' style='font-size: 12px; padding: 5px 10px;'>تفاصيل</a></td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 3. عرض الطلاب مع ارتباطاتهم
echo "<div class='section'>";
echo "<h2>3. الطلاب وارتباطاتهم</h2>";
$students = $pdo->query("
    SELECT 
        s.id,
        CONCAT(s.nom, ' ', s.prenom) as name,
        s.connected_teacher_code,
        CONCAT(t.nom, ' ', t.prenom) as teacher_name,
        t.id as teacher_id,
        t.teacher_code as teacher_current_code,
        CASE 
            WHEN s.connected_teacher_code IS NULL THEN 'no_connection'
            WHEN s.connected_teacher_code = t.teacher_code THEN 'valid'
            ELSE 'invalid'
        END as status
    FROM users s
    LEFT JOIN users t ON s.connected_teacher_code = t.teacher_code
    WHERE s.role = 'etudiant'
    ORDER BY status, s.id
")->fetchAll();

echo "<table>";
echo "<tr><th>ID الطالب</th><th>اسم الطالب</th><th>connected_teacher_code</th><th>المعلم</th><th>كود المعلم الحالي</th><th>الحالة</th></tr>";

$valid_count = 0;
$invalid_count = 0;
$no_connection_count = 0;

foreach ($students as $s) {
    $status_bg = '#f5f5f5';
    $status_text = '';
    
    switch($s['status']) {
        case 'valid':
            $status_bg = '#c8e6c9';
            $status_text = '✅ صحيح';
            $valid_count++;
            break;
        case 'invalid':
            $status_bg = '#ffcdd2';
            $status_text = '❌ غير صحيح';
            $invalid_count++;
            break;
        case 'no_connection':
            $status_bg = '#fff9c4';
            $status_text = '⚪ غير مرتبط';
            $no_connection_count++;
            break;
    }
    
    echo "<tr style='background: {$status_bg};'>";
    echo "<td>{$s['id']}</td>";
    echo "<td><strong>{$s['name']}</strong></td>";
    echo "<td><code>" . ($s['connected_teacher_code'] ?? 'NULL') . "</code></td>";
    echo "<td>" . ($s['teacher_name'] ?? '<em style="color: #999;">لا يوجد</em>') . " " . ($s['teacher_id'] ? "(#{$s['teacher_id']})" : "") . "</td>";
    echo "<td><code>" . ($s['teacher_current_code'] ?? 'N/A') . "</code></td>";
    echo "<td><strong>{$status_text}</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<div class='warning'>";
echo "<h3>📊 ملخص:</h3>";
echo "<ul>";
echo "<li>✅ ارتباطات صحيحة: <strong>{$valid_count}</strong></li>";
echo "<li>❌ ارتباطات غير صحيحة (الكود لا يطابق): <strong>{$invalid_count}</strong></li>";
echo "<li>⚪ طلاب غير مرتبطين: <strong>{$no_connection_count}</strong></li>";
echo "</ul>";

if ($invalid_count > 0) {
    echo "<div class='error' style='margin-top: 10px;'>";
    echo "<strong>⚠️ تنبيه:</strong> هناك {$invalid_count} طالب بارتباطات غير صحيحة. هذا يعني أن الكود في connected_teacher_code لا يطابق أي teacher_code موجود.";
    echo "</div>";
}
echo "</div>";
echo "</div>";

// 4. تفاصيل معلم محدد
if (isset($_GET['debug_teacher'])) {
    $teacher_id = intval($_GET['debug_teacher']);
    
    echo "<div class='section'>";
    echo "<h2>4. تفاصيل معلم محدد</h2>";
    
    $teacher = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $teacher->execute([$teacher_id]);
    $t = $teacher->fetch();
    
    if ($t) {
        echo "<h3>المعلم: {$t['nom']} {$t['prenom']}</h3>";
        echo "<p><strong>ID:</strong> {$t['id']}</p>";
        echo "<p><strong>الكود الحالي:</strong> <code style='font-size: 16px; background: #e8f5e9; padding: 5px 10px;'>" . ($t['teacher_code'] ?? 'NULL') . "</code></p>";
        echo "<p><strong>الدور:</strong> {$t['role']}</p>";
        
        // جلب طلاب هذا المعلم
        $students_of_teacher = $pdo->prepare("
            SELECT id, CONCAT(nom, ' ', prenom) as name, connected_teacher_code
            FROM users
            WHERE connected_teacher_code = ? AND role = 'etudiant'
        ");
        $students_of_teacher->execute([$t['teacher_code']]);
        $teacher_students = $students_of_teacher->fetchAll();
        
        echo "<h4>الطلاب المرتبطون:</h4>";
        if (!empty($teacher_students)) {
            echo "<table>";
            echo "<tr><th>ID</th><th>الاسم</th><th>connected_teacher_code</th><th>يطابق؟</th></tr>";
            foreach ($teacher_students as $st) {
                $match = $st['connected_teacher_code'] === $t['teacher_code'];
                $match_icon = $match ? '✅' : '❌';
                $match_bg = $match ? '#c8e6c9' : '#ffcdd2';
                echo "<tr style='background: {$match_bg};'>";
                echo "<td>{$st['id']}</td>";
                echo "<td>{$st['name']}</td>";
                echo "<td><code>{$st['connected_teacher_code']}</code></td>";
                echo "<td>{$match_icon}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: #999;'><em>لا يوجد طلاب مرتبطون حالياً</em></p>";
        }
        
        // اختبار فصل الطلاب
        if (!empty($teacher_students) && isset($_GET['test_disconnect'])) {
            echo "<div class='warning'>";
            echo "<h4>🧪 اختبار فصل الطلاب</h4>";
            
            if (isset($_GET['confirm_disconnect'])) {
                try {
                    $pdo->beginTransaction();
                    
                    $disconnect = $pdo->prepare("
                        UPDATE users 
                        SET connected_teacher_code = NULL 
                        WHERE connected_teacher_code = ? AND role = 'etudiant'
                    ");
                    $disconnect->execute([$t['teacher_code']]);
                    $count = $disconnect->rowCount();
                    
                    $pdo->commit();
                    
                    echo "<div class='success'>";
                    echo "✅ تم فصل <strong>{$count}</strong> طالب بنجاح!";
                    echo "<br><a href='?debug_teacher={$teacher_id}' class='btn' style='margin-top: 10px;'>تحديث الصفحة</a>";
                    echo "</div>";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo "<div class='error'>❌ خطأ: {$e->getMessage()}</div>";
                }
            } else {
                echo "<p>هل تريد فصل <strong>" . count($teacher_students) . "</strong> طالب من هذا المعلم؟</p>";
                echo "<a href='?debug_teacher={$teacher_id}&test_disconnect=1&confirm_disconnect=1' class='btn' style='background: #f44336;'>⚠️ تأكيد الفصل</a>";
                echo "<a href='?debug_teacher={$teacher_id}' class='btn' style='background: #999;'>إلغاء</a>";
            }
            echo "</div>";
        } elseif (!empty($teacher_students)) {
            echo "<a href='?debug_teacher={$teacher_id}&test_disconnect=1' class='btn' style='background: #ff9800;'>🧪 اختبار فصل الطلاب</a>";
        }
    } else {
        echo "<p style='color: red;'>المعلم غير موجود!</p>";
    }
    
    echo "</div>";
}

// 5. اختبار الاستعلام المباشر
echo "<div class='section'>";
echo "<h2>5. اختبار استعلام الفصل</h2>";
echo "<p>اختبر استعلام فصل الطلاب لكود معين:</p>";

if (isset($_GET['test_code'])) {
    $test_code = $_GET['test_code'];
    
    echo "<div class='warning'>";
    echo "<h4>اختبار الكود: <code>{$test_code}</code></h4>";
    
    // عرض الطلاب الذين سيتأثرون
    $affected = $pdo->prepare("
        SELECT id, CONCAT(nom, ' ', prenom) as name, connected_teacher_code
        FROM users
        WHERE connected_teacher_code = ? AND role = 'etudiant'
    ");
    $affected->execute([$test_code]);
    $affected_students = $affected->fetchAll();
    
    echo "<p>عدد الطلاب المتأثرين: <strong>" . count($affected_students) . "</strong></p>";
    
    if (!empty($affected_students)) {
        echo "<ul>";
        foreach ($affected_students as $st) {
            echo "<li>{$st['name']} (ID: {$st['id']}) - <code>{$st['connected_teacher_code']}</code></li>";
        }
        echo "</ul>";
    }
    echo "</div>";
}

// نموذج اختبار
echo "<form method='GET' style='margin: 15px 0;'>";
echo "<input type='text' name='test_code' placeholder='أدخل كود المعلم' style='padding: 8px; width: 200px; border: 1px solid #ddd; border-radius: 4px;'>";
echo "<button type='submit' style='padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;'>اختبار</button>";
echo "</form>";

echo "</div>";

echo "<div class='section'>";
echo "<h2>🔧 روابط مفيدة</h2>";
echo "<a href='my-code.php' class='btn'>صفحة المعلم</a>";
echo "<a href='verify_links.php' class='btn'>التحقق من الارتباطات</a>";
echo "<a href='test_teacher_code_disconnect.php' class='btn'>اختبار متقدم</a>";
echo "<a href='?' class='btn' style='background: #999;'>تحديث الصفحة</a>";
echo "</div>";

echo "</body></html>";
