<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// التأكد من تسجيل الدخول
if (!is_logged_in()) {
    header("Location: ../public/login.php");
    exit();
}

// إعادة توجيه حسب الدور
$role = get_user_role();

// إضافة رسالة debug
error_log("Dashboard redirect - Role: " . $role);

switch ($role) {
    case 'directeur': // المدير
    case 'director':
    case 'admin':
        header("Location: ../dashboard/directeur/index.php");
        exit();
        
    case 'superviseur_general': // المشرف العام
    case 'supervisor_general':
        header("Location: ../dashboard/supervisor_general/index.php");
        exit();
        
    case 'superviseur_matiere': // مشرف المادة
    case 'supervisor_subject':
    case 'subject_supervisor':
        header("Location: ../dashboard/subject-supervisor/index.php");
        exit();
        
    case 'enseignant': // المعلم
    case 'teacher':
        header("Location: ../dashboard/teacher/index.php");
        exit();
        
    case 'etudiant': // الطالب
    case 'student':
        header("Location: ../dashboard/student/index.php");
        exit();
        
    case 'parent': // ولي الأمر
        header("Location: ../dashboard/parent/index.php");
        exit();
        
    default:
        // دور غير معروف - عرض رسالة
        echo "<!DOCTYPE html><html lang='ar' dir='rtl'><head><meta charset='UTF-8'></head><body style='font-family: Arial; padding: 40px; text-align: center;'>";
        echo "<h2 style='color: #ef4444;'>❌ خطأ: دور غير معروف</h2>";
        echo "<p>الدور المسجل: <strong>" . htmlspecialchars($role) . "</strong></p>";
        echo "<p>الأدوار المتاحة: directeur, superviseur_general, superviseur_matiere, enseignant, etudiant, parent</p>";
        echo "<p><a href='../public/logout.php' style='color: #3b82f6; text-decoration: underline;'>تسجيل الخروج</a></p>";
        echo "</body></html>";
        exit();
}
?>
