<?php
/**
 * إعدادات المنصة العامة
 * SmartEdu Hub Platform Settings
 */

// إعدادات اللغة
define('PLATFORM_LANGUAGE', 'ar');
define('PLATFORM_DIRECTION', 'rtl');
define('PLATFORM_CHARSET', 'UTF-8');

// إعدادات الخط
define('PLATFORM_FONT_FAMILY', 'Amiri');
define('PLATFORM_FONT_URL', 'https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&display=swap');

// إعدادات التصميم
define('PLATFORM_NAME', 'SmartEdu Hub');
define('PLATFORM_NAME_AR', 'منصة سمارت التعليمية');

// ألوان الأدوار
$ROLE_COLORS = [
    'directeur' => '#667eea',
    'supervisor_general' => '#6366f1',
    'supervisor_subject' => '#8b5cf6',
    'teacher' => '#4285F4',
    'student' => '#22c55e',
    'parent' => '#a855f7'
];

// رؤوس HTML المعيارية
function get_standard_html_head($title = 'SmartEdu Hub', $additional_css = []) {
    $html = '<!DOCTYPE html>' . "\n";
    $html .= '<html lang="' . PLATFORM_LANGUAGE . '" dir="' . PLATFORM_DIRECTION . '">' . "\n";
    $html .= '<head>' . "\n";
    $html .= '    <meta charset="' . PLATFORM_CHARSET . '">' . "\n";
    $html .= '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    $html .= '    <title>' . htmlspecialchars($title) . '</title>' . "\n";
    $html .= '    <link href="' . PLATFORM_FONT_URL . '" rel="stylesheet">' . "\n";
    $html .= '    <link rel="stylesheet" href="../assets/css/amiri-font.css">' . "\n";
    $html .= '    <link rel="stylesheet" href="../assets/css/style.css">' . "\n";
    
    // إضافة ملفات CSS إضافية
    foreach ($additional_css as $css_file) {
        $html .= '    <link rel="stylesheet" href="' . $css_file . '">' . "\n";
    }
    
    $html .= '</head>' . "\n";
    
    return $html;
}

// الحصول على لون الدور
function get_role_color($role) {
    global $ROLE_COLORS;
    return $ROLE_COLORS[$role] ?? '#22c55e';
}

// تطبيق CSS للدور المحدد
function get_role_css($role) {
    $color = get_role_color($role);
    return "
    <style>
        :root {
            --role-color: {$color};
        }
        body {
            font-family: '" . PLATFORM_FONT_FAMILY . "', serif !important;
            direction: " . PLATFORM_DIRECTION . ";
        }
        .sidebar {
            background: {$color} !important;
            font-family: '" . PLATFORM_FONT_FAMILY . "', serif !important;
        }
        .role-bg {
            background: {$color} !important;
        }
        .role-text {
            color: {$color} !important;
        }
        .role-border {
            border-color: {$color} !important;
        }
    </style>";
}

// رسائل النظام بالعربية
$SYSTEM_MESSAGES = [
    'welcome' => 'مرحباً بك في منصة سمارت التعليمية',
    'login_required' => 'يجب تسجيل الدخول للوصول إلى هذه الصفحة',
    'access_denied' => 'ليس لديك صلاحية للوصول إلى هذه الصفحة',
    'success' => 'تمت العملية بنجاح',
    'error' => 'حدث خطأ أثناء تنفيذ العملية',
    'loading' => 'جاري التحميل...',
    'save' => 'حفظ',
    'cancel' => 'إلغاء',
    'delete' => 'حذف',
    'edit' => 'تعديل',
    'add' => 'إضافة',
    'back' => 'رجوع',
    'next' => 'التالي',
    'previous' => 'السابق'
];

// الحصول على رسالة النظام
function get_system_message($key) {
    global $SYSTEM_MESSAGES;
    return $SYSTEM_MESSAGES[$key] ?? $key;
}
?>