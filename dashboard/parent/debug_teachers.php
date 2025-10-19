<?php
session_start();
require_once '../../config/database.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

global $pdo;

echo "<h1>اختبار جلب المعلمين المرتبطين بالطلاب</h1>";

// 1. جلب جميع الطلاب وأكواد معلميهم
echo "<h2>1. الطلاب وأكواد معلميهم</h2>";
$stmt = $pdo->query("
    SELECT id, nom, prenom, connected_teacher_code, stage_id, level_id 
    FROM users 
    WHERE role = 'etudiant'
    ORDER BY id
");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>الاسم</th><th>teacher_code</th><th>stage_id</th><th>level_id</th></tr>";
while ($student = $stmt->fetch()) {
    echo "<tr>";
    echo "<td>{$student['id']}</td>";
    echo "<td>{$student['nom']} {$student['prenom']}</td>";
    echo "<td>" . ($student['connected_teacher_code'] ?? 'NULL') . "</td>";
    echo "<td>{$student['stage_id']}</td>";
    echo "<td>{$student['level_id']}</td>";
    echo "</tr>";
}
echo "</table>";

// 2. جلب جميع المعلمين وأكوادهم
echo "<h2>2. المعلمين وأكوادهم</h2>";
$stmt = $pdo->query("
    SELECT id, nom, prenom, role, teacher_code, subject_id, stage_id 
    FROM users 
    WHERE role IN ('enseignant', 'supervisor_subject')
    ORDER BY id
");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>الاسم</th><th>الدور</th><th>teacher_code</th><th>subject_id</th><th>stage_id</th></tr>";
while ($teacher = $stmt->fetch()) {
    echo "<tr>";
    echo "<td>{$teacher['id']}</td>";
    echo "<td>{$teacher['nom']} {$teacher['prenom']}</td>";
    echo "<td>{$teacher['role']}</td>";
    echo "<td>" . ($teacher['teacher_code'] ?? 'NULL') . "</td>";
    echo "<td>" . ($teacher['subject_id'] ?? 'NULL') . "</td>";
    echo "<td>" . ($teacher['stage_id'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// 3. اختبار الاستعلام الجديد لطالب محدد
echo "<h2>3. اختبار الاستعلام الجديد</h2>";

$test_child_id = 29; // غير هذا الرقم لطالب موجود

$child_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'etudiant'");
$child_stmt->execute([$test_child_id]);
$child = $child_stmt->fetch();

if ($child) {
    echo "<h3>الطالب: {$child['nom']} {$child['prenom']}</h3>";
    echo "<p>teacher_code: " . ($child['connected_teacher_code'] ?? 'NULL') . "</p>";
    echo "<p>level_id: {$child['level_id']}</p>";
    echo "<p>stage_id: {$child['stage_id']}</p>";

    // تنفيذ الاستعلام الجديد
    $subjects_progress_stmt = $pdo->prepare("
        SELECT 
            s.id as subject_id,
            s.name as subject_name,
            COUNT(DISTINCT l.id) as total_lessons,
            t.id as teacher_id,
            CONCAT(t.nom, ' ', t.prenom) as teacher_name,
            t.role as teacher_role
        FROM subjects s
        LEFT JOIN lessons l ON s.id = l.subject_id AND l.level_id = ?
        LEFT JOIN users t ON (s.id = t.subject_id AND t.teacher_code = ? AND t.role IN ('enseignant', 'supervisor_subject'))
        WHERE s.stage_id = ?
        GROUP BY s.id, s.name, t.id, t.nom, t.prenom, t.role
        ORDER BY s.name
    ");
    $subjects_progress_stmt->execute([$child['level_id'], $child['connected_teacher_code'], $child['stage_id']]);
    $subjects = $subjects_progress_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>النتيجة:</h3>";
    if (empty($subjects)) {
        echo "<p style='color: red;'>لا توجد نتائج!</p>";
        echo "<p>المشكلة المحتملة:</p>";
        echo "<ul>";
        echo "<li>لا توجد مواد للمرحلة stage_id = {$child['stage_id']}</li>";
        echo "<li>connected_teacher_code فارغ أو غير متطابق</li>";
        echo "</ul>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>المادة</th><th>عدد الدروس</th><th>المعلم ID</th><th>اسم المعلم</th><th>الدور</th></tr>";
        foreach ($subjects as $subj) {
            echo "<tr>";
            echo "<td>{$subj['subject_name']}</td>";
            echo "<td>{$subj['total_lessons']}</td>";
            echo "<td>" . ($subj['teacher_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($subj['teacher_name'] ?? 'لا يوجد معلم') . "</td>";
            echo "<td>" . ($subj['teacher_role'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>الطالب غير موجود!</p>";
}

// 4. جلب المواد
echo "<h2>4. جميع المواد</h2>";
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY stage_id, name");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>اسم المادة</th><th>stage_id</th></tr>";
while ($subject = $stmt->fetch()) {
    echo "<tr>";
    echo "<td>{$subject['id']}</td>";
    echo "<td>{$subject['name']}</td>";
    echo "<td>{$subject['stage_id']}</td>";
    echo "</tr>";
}
echo "</table>";
