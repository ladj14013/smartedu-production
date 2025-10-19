<?php
/**
 * Subject Supervisor Sidebar Navigation
 * الشريط الجانبي لمشرف المادة
 */

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Get supervisor ID from session
$supervisor_id = $_SESSION['user_id'] ?? null;
$subject_id = $_SESSION['subject_id'] ?? null;

// Get database connection
if (!isset($pdo)) {
    global $pdo;
}

// Get supervisor and subject info
try {
    $stmt = $pdo->prepare("
        SELECT CONCAT(u.nom, ' ', u.prenom) as name, s.name as subject_name 
        FROM users u
        LEFT JOIN subjects s ON u.subject_id = s.id
        WHERE u.id = ?
    ");
    $stmt->execute([$supervisor_id]);
    $supervisor_info = $stmt->fetch();
    
    // Get pending lessons count for badge
    if ($subject_id) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM lessons 
            WHERE subject_id = ? AND status = 'pending'
        ");
        $stmt->execute([$subject_id]);
        $pending_count = $stmt->fetchColumn();
    } else {
        $pending_count = 0;
    }
} catch (PDOException $e) {
    $supervisor_info = ['name' => 'المشرف', 'subject_name' => 'المادة'];
    $pending_count = 0;
}
?>

<style>
    .sidebar {
        width: 280px;
        background: linear-gradient(180deg, #9C27B0 0%, #7B1FA2 100%);
        color: white;
        height: 100vh;
        position: fixed;
        right: 0 !important;
        left: auto !important;
        top: 0;
        overflow-y: auto;
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        z-index: 1000;
        direction: rtl;
    }
    
    .sidebar-header {
        padding: 30px 25px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-header h2 {
        font-size: 1.5rem;
        margin-bottom: 8px;
        font-weight: 700;
    }
    
    .user-role {
        font-size: 0.9rem;
        opacity: 0.9;
        background: rgba(255,255,255,0.15);
        padding: 4px 12px;
        border-radius: 20px;
        display: inline-block;
    }
    
    .sidebar-nav {
        padding: 20px 0;
    }
    
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 25px;
        color: white;
        text-decoration: none;
        transition: all 0.3s ease;
        border-right: 4px solid transparent;
    }
    
    .nav-item:hover {
        background: rgba(255,255,255,0.1);
        border-right-color: white;
    }
    
    .nav-item.active {
        background: rgba(255,255,255,0.15);
        border-right-color: white;
        font-weight: 600;
    }
    
    .nav-icon {
        font-size: 1.3rem;
    }
    
    .nav-text {
        flex: 1;
    }
    
    .nav-badge {
        background: #FF9800;
        color: white;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
        margin-right: auto;
    }
    
    .nav-item-danger {
        margin-top: 10px;
        border-top: 1px solid rgba(255,255,255,0.1);
        padding-top: 20px;
    }
    
    .nav-item-danger:hover {
        background: rgba(255,0,0,0.1);
    }
    
    .sidebar-footer {
        position: absolute;
        bottom: 0;
        width: 100%;
        padding: 20px 25px;
        background: rgba(0,0,0,0.1);
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .supervisor-info {
        font-size: 0.85rem;
    }
    
    .supervisor-name {
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .subject-name {
        opacity: 0.9;
        color: #fce4ec;
    }
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2>📊 SmartEdu</h2>
        <p class="user-role">مشرف مادة</p>
    </div>
    
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-text">الرئيسية</span>
        </a>
        
        <a href="pending-lessons.php" class="nav-item <?php echo $current_page == 'pending-lessons.php' ? 'active' : ''; ?>">
            <span class="nav-icon">⏳</span>
            <span class="nav-text">الدروس المعلقة</span>
            <?php if ($pending_count > 0): ?>
                <span class="nav-badge"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="approved-lessons.php" class="nav-item <?php echo $current_page == 'approved-lessons.php' ? 'active' : ''; ?>">
            <span class="nav-icon">✅</span>
            <span class="nav-text">الدروس المعتمدة</span>
        </a>
        
        <a href="rejected-lessons.php" class="nav-item <?php echo $current_page == 'rejected-lessons.php' ? 'active' : ''; ?>">
            <span class="nav-icon">❌</span>
            <span class="nav-text">الدروس المرفوضة</span>
        </a>
        
        <a href="all-lessons.php" class="nav-item <?php echo $current_page == 'all-lessons.php' ? 'active' : ''; ?>">
            <span class="nav-icon">�</span>
            <span class="nav-text">كل الدروس</span>
        </a>
        
        <a href="lessons.php" class="nav-item <?php echo $current_page == 'lessons.php' ? 'active' : ''; ?>">
            <span class="nav-icon">�📚</span>
            <span class="nav-text">إدارة الدروس</span>
        </a>
        
        <a href="teachers.php" class="nav-item <?php echo $current_page == 'teachers.php' ? 'active' : ''; ?>">
            <span class="nav-icon">👨‍🏫</span>
            <span class="nav-text">الأساتذة</span>
        </a>
        
        <a href="students.php" class="nav-item <?php echo $current_page == 'students.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🎓</span>
            <span class="nav-text">الطلاب</span>
        </a>
        
        <a href="statistics.php" class="nav-item <?php echo $current_page == 'statistics.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-text">الإحصائيات</span>
        </a>
        
        <a href="messages.php" class="nav-item <?php echo $current_page == 'messages.php' ? 'active' : ''; ?>">
            <span class="nav-icon">�</span>
            <span class="nav-text">الرسائل</span>
        </a>
        
        <a href="../../public/logout.php" class="nav-item nav-item-danger">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">تسجيل الخروج</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="supervisor-info">
            <div class="supervisor-name">
                <?php echo htmlspecialchars($supervisor_info['name'] ?? 'المشرف'); ?>
            </div>
            <div class="subject-name">
                📚 <?php echo htmlspecialchars($supervisor_info['subject_name'] ?? 'المادة'); ?>
            </div>
        </div>
    </div>
</aside>
