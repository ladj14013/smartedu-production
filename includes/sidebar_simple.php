<?php
// Simple sidebar for debugging
$current_role = $_SESSION['user_role'] ?? 'student';
$user_name = $_SESSION['user_name'] ?? 'مستخدم';

$role_colors = [
    'directeur' => '#667eea',
    'teacher' => '#4285F4',
    'student' => '#22c55e',
    'parent' => '#a855f7'
];

$color = $role_colors[$current_role] ?? '#22c55e';
?>

<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">

<style>
* {
    font-family: 'Amiri', serif;
}

.sidebar {
    width: 280px;
    height: 100vh;
    background: <?php echo $color; ?>;
    color: white;
    padding: 20px;
    position: fixed;
    left: 0;
    top: 0;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    font-family: 'Amiri', serif;
}

.main-content {
    margin-right: 280px;
    padding: 20px;
    background: #f8fafc;
    min-height: 100vh;
}

.sidebar h2 {
    margin-bottom: 30px;
    text-align: center;
}

.nav-item {
    display: block;
    color: white;
    text-decoration: none;
    padding: 12px 15px;
    margin-bottom: 5px;
    border-radius: 8px;
    transition: background 0.3s;
}

.nav-item:hover {
    background: rgba(255,255,255,0.1);
}

.user-info {
    position: absolute;
    bottom: 20px;
    left: 20px;
    right: 20px;
    padding: 15px;
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
    text-align: center;
}
</style>

<div class="sidebar">
    <h2>🎓 SmartEdu</h2>
    
    <nav>
        <?php if ($current_role == 'directeur'): ?>
            <a href="../dashboard/directeur/index.php" class="nav-item">🏠 الرئيسية</a>
            <a href="../dashboard/directeur/users.php" class="nav-item">👥 المستخدمون</a>
            <a href="../dashboard/directeur/subjects.php" class="nav-item">📖 المواد</a>
            <a href="../dashboard/directeur/messages.php" class="nav-item">💬 الرسائل</a>
        <?php elseif ($current_role == 'teacher'): ?>
            <a href="../dashboard/teacher/index.php" class="nav-item">🏠 الرئيسية</a>
            <a href="../dashboard/teacher/lessons.php" class="nav-item">📚 دروسي</a>
            <a href="../dashboard/teacher/exercises.php" class="nav-item">💪 التمارين</a>
            <a href="../dashboard/teacher/messages.php" class="nav-item">💬 الرسائل</a>
        <?php elseif ($current_role == 'student'): ?>
            <a href="../dashboard/student/index.php" class="nav-item">🏠 الرئيسية</a>
            <a href="../dashboard/student/lessons.php" class="nav-item">📚 الدروس</a>
            <a href="../dashboard/student/exercises.php" class="nav-item">💪 التمارين</a>
            <a href="../dashboard/student/results.php" class="nav-item">📊 النتائج</a>
        <?php else: ?>
            <a href="../dashboard/parent/index.php" class="nav-item">🏠 الرئيسية</a>
            <a href="../dashboard/parent/children.php" class="nav-item">👶 الأطفال</a>
            <a href="../dashboard/parent/messages.php" class="nav-item">💬 الرسائل</a>
        <?php endif; ?>
    </nav>
    
    <div class="user-info">
        <div><strong><?php echo htmlspecialchars($user_name); ?></strong></div>
        <div style="font-size: 0.8rem; margin-top: 5px;"><?php echo $current_role; ?></div>
        <a href="../public/logout.php" style="color: white; text-decoration: underline; margin-top: 10px; display: block;">تسجيل الخروج</a>
    </div>
</div>