<?php
/**
 * Universal Sidebar Component
 * شريط جانبي موحد لجميع الأدوار
 */

// تحميل إعدادات المنصة إذا لم تكن محملة
if (!function_exists('get_role_color')) {
    require_once __DIR__ . '/../config/platform.php';
}

// التأكد من وجود session (لا نبدأ session جديدة إذا كانت موجودة)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_role = $_SESSION['user_role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['user_name'] ?? 'مستخدم';
$user_email = $_SESSION['user_email'] ?? '';

// تحديد ألوان كل دور من ملف الإعدادات
$role_colors = [
    'directeur' => ['primary' => get_role_color('directeur'), 'secondary' => '#764ba2', 'name' => 'مدير النظام'],
    'supervisor_general' => ['primary' => get_role_color('supervisor_general'), 'secondary' => '#0369a1', 'name' => 'مشرف عام'],
    'supervisor_subject' => ['primary' => get_role_color('supervisor_subject'), 'secondary' => '#0284c7', 'name' => 'مشرف مادة'],
    'teacher' => ['primary' => '#4285F4', 'secondary' => '#0066cc', 'name' => 'معلم'],
    'student' => ['primary' => '#22c55e', 'secondary' => '#16a34a', 'name' => 'طالب'],
    'parent' => ['primary' => '#a855f7', 'secondary' => '#ec4899', 'name' => 'ولي أمر']
];

$colors = $role_colors[$current_role] ?? $role_colors['student'];

// قوائم التنقل لكل دور
$navigation = [
    'directeur' => [
        ['icon' => '🏠', 'title' => 'الرئيسية', 'url' => '../dashboard/directeur/index.php', 'file' => 'index.php'],
        ['icon' => '👥', 'title' => 'المستخدمون', 'url' => '../dashboard/directeur/users.php', 'file' => 'users.php'],
        ['icon' => '📖', 'title' => 'المواد', 'url' => '../dashboard/directeur/subjects.php', 'file' => 'subjects.php'],
        ['icon' => '🎯', 'title' => 'المراحل', 'url' => '../dashboard/directeur/stages.php', 'file' => 'stages.php'],
        ['icon' => '📚', 'title' => 'الدروس', 'url' => '../dashboard/directeur/edit-lesson.php', 'file' => 'edit-lesson.php'],
        ['icon' => '💬', 'title' => 'الرسائل', 'url' => '../dashboard/directeur/messages.php', 'file' => 'messages.php'],
    ],
    'supervisor_general' => [
        ['icon' => '🏠', 'title' => 'الرئيسية', 'url' => '../dashboard/supervisor_general/index.php', 'file' => 'index.php'],
        ['icon' => '📚', 'title' => 'مراجعة الدروس', 'url' => '../dashboard/supervisor_general/lessons.php', 'file' => 'lessons.php'],
        ['icon' => '💬', 'title' => 'الرسائل', 'url' => '../dashboard/supervisor_general/messages.php', 'file' => 'messages.php'],
    ],
    'supervisor_subject' => [
        ['icon' => '🏠', 'title' => 'الرئيسية', 'url' => '../dashboard/supervisor_subject/index.php', 'file' => 'index.php'],
        ['icon' => '📚', 'title' => 'دروس المادة', 'url' => '../dashboard/supervisor_subject/lessons.php', 'file' => 'lessons.php'],
        ['icon' => '💬', 'title' => 'الرسائل', 'url' => '../dashboard/supervisor_subject/messages.php', 'file' => 'messages.php'],
    ],
    'teacher' => [
        ['icon' => '🏠', 'title' => 'الرئيسية', 'url' => '../dashboard/teacher/index.php', 'file' => 'index.php'],
        ['icon' => '📚', 'title' => 'دروسي', 'url' => '../dashboard/teacher/lessons.php', 'file' => 'lessons.php'],
        ['icon' => '➕', 'title' => 'درس جديد', 'url' => '../dashboard/teacher/lesson-form.php', 'file' => 'lesson-form.php'],
        ['icon' => '💪', 'title' => 'التمارين', 'url' => '../dashboard/teacher/exercises.php', 'file' => 'exercises.php'],
        ['icon' => '🎓', 'title' => 'الطلاب', 'url' => '../dashboard/teacher/students.php', 'file' => 'students.php'],
        ['icon' => '💬', 'title' => 'الرسائل', 'url' => '../dashboard/teacher/messages.php', 'file' => 'messages.php'],
    ],
    'student' => [
        ['icon' => '🏠', 'title' => 'الرئيسية', 'url' => '../dashboard/student/index.php', 'file' => 'index.php'],
        ['icon' => '📚', 'title' => 'الدروس', 'url' => '../dashboard/student/lessons.php', 'file' => 'lessons.php'],
        ['icon' => '💪', 'title' => 'التمارين', 'url' => '../dashboard/student/exercises.php', 'file' => 'exercises.php'],
        ['icon' => '📊', 'title' => 'النتائج', 'url' => '../dashboard/student/results.php', 'file' => 'results.php'],
    ],
    'parent' => [
        ['icon' => '🏠', 'title' => 'الرئيسية', 'url' => '../dashboard/parent/index.php', 'file' => 'index.php'],
        ['icon' => '👶', 'title' => 'الأطفال', 'url' => '../dashboard/parent/children.php', 'file' => 'children.php'],
        ['icon' => '💬', 'title' => 'الرسائل', 'url' => '../dashboard/parent/messages.php', 'file' => 'messages.php'],
        ['icon' => '🔔', 'title' => 'الإشعارات', 'url' => '../dashboard/parent/notifications.php', 'file' => 'notifications.php'],
    ]
];

$nav_items = $navigation[$current_role] ?? [];

// عدادات/شارات للتنبيهات (يمكن تخصيصها لاحقاً)
$badges = [
    'directeur' => [],
    'supervisor_general' => [],
    'supervisor_subject' => [],
    'teacher' => [],
    'student' => [],
    'parent' => []
];
?>

<!-- تحميل خط Amiri الرسمي -->
<link href="<?php echo PLATFORM_FONT_URL; ?>" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/amiri-font.css">

<style>
* {
    font-family: '<?php echo PLATFORM_FONT_FAMILY; ?>', serif !important;
}

.sidebar {
    width: 280px;
    height: 100vh;
    background: linear-gradient(135deg, <?php echo $colors['primary']; ?> 0%, <?php echo $colors['secondary']; ?> 100%);
    color: white;
    padding: 0;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    z-index: 1000;
    font-family: '<?php echo PLATFORM_FONT_FAMILY; ?>', serif !important;
}

.sidebar-header {
    padding: 30px 20px 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header h2 {
    font-size: 1.5rem;
    margin-bottom: 5px;
    font-weight: 700;
}

.sidebar-header .role-badge {
    background: rgba(255,255,255,0.2);
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.85rem;
    display: inline-block;
    margin-top: 5px;
}

.sidebar-nav {
    padding: 20px 0;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 12px 25px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    background: none;
    margin-bottom: 2px;
    position: relative;
}

.nav-item:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(-5px);
}

.nav-item.active {
    background: rgba(255,255,255,0.15);
    color: white;
    border-left: 4px solid white;
}

.nav-item .icon {
    font-size: 1.2rem;
    margin-left: 15px;
    width: 25px;
    text-align: center;
}

.nav-item .title {
    font-weight: 500;
    flex: 1;
}

.nav-item .badge {
    background: #ef4444;
    color: white;
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 12px;
    margin-right: 10px;
    min-width: 18px;
    text-align: center;
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.1);
}

.user-info {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px;
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-left: 12px;
    flex-shrink: 0;
}

.user-details {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-email {
    font-size: 0.75rem;
    opacity: 0.8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.logout-btn {
    width: 100%;
    background: rgba(255,255,255,0.1);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 15px;
    border-radius: 8px;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
    font-size: 0.85rem;
}

.logout-btn:hover {
    background: rgba(255,255,255,0.2);
    border-color: rgba(255,255,255,0.3);
    color: white;
}

.main-content {
    margin-right: 280px;
    min-height: 100vh;
    background: #f8fafc;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
    
    .main-content {
        margin-right: 0;
    }
    
    .mobile-menu-btn {
        display: block;
        position: fixed;
        top: 20px;
        right: 20px;
        background: <?php echo $colors['primary']; ?>;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 8px;
        z-index: 1001;
        font-size: 1.2rem;
    }
}

.mobile-menu-btn {
    display: none;
}
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2>🎓 SmartEdu</h2>
        <div class="role-badge"><?php echo $colors['name']; ?></div>
    </div>
    
    <nav class="sidebar-nav">
        <?php foreach ($nav_items as $item): ?>
            <a href="<?php echo $item['url']; ?>" 
               class="nav-item <?php echo $current_page == $item['file'] ? 'active' : ''; ?>">
                <span class="icon"><?php echo $item['icon']; ?></span>
                <span class="title"><?php echo $item['title']; ?></span>
                <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                    <span class="badge"><?php echo $item['badge']; ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
            </div>
        </div>
        <a href="../../public/logout.php" class="logout-btn">
            🚪 <span>تسجيل الخروج</span>
        </a>
    </div>
</aside>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
}

// Close sidebar on mobile when clicking outside
document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) {
        const sidebar = document.querySelector('.sidebar');
        const menuBtn = document.querySelector('.mobile-menu-btn');
        
        if (!sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    }
});
</script>