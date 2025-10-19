<?php
/**
 * Supervisor General Sidebar
 * القائمة الجانبية للمشرف العام
 */

$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="dashboard-sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <h2>📚 SmartEdu</h2>
            <span class="role-badge">مشرف عام</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="../../dashboard/supervisor_general/index.php" 
           class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-label">الرئيسية</span>
        </a>
        
        <a href="../../dashboard/supervisor_general/subjects.php" 
           class="nav-item <?php echo ($current_page == 'subjects.php') ? 'active' : ''; ?>">
            <span class="nav-icon">📚</span>
            <span class="nav-label">المواد الدراسية</span>
        </a>
        
        <a href="../../dashboard/supervisor_general/teachers.php" 
           class="nav-item <?php echo ($current_page == 'teachers.php') ? 'active' : ''; ?>">
            <span class="nav-icon">👨‍🏫</span>
            <span class="nav-label">المعلمون</span>
        </a>
        
        <a href="../../dashboard/supervisor_general/review.php" 
           class="nav-item <?php echo ($current_page == 'review.php') ? 'active' : ''; ?>">
            <span class="nav-icon">⏳</span>
            <span class="nav-label">مراجعة الدروس</span>
            <?php if (isset($pending_lessons) && $pending_lessons > 0): ?>
                <span class="badge-count"><?php echo $pending_lessons; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="../../dashboard/supervisor_general/messages.php" 
           class="nav-item <?php echo ($current_page == 'messages.php') ? 'active' : ''; ?>">
            <span class="nav-icon">📨</span>
            <span class="nav-label">الرسائل</span>
            <?php if (isset($unread_messages) && $unread_messages > 0): ?>
                <span class="badge-count"><?php echo $unread_messages; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="../../dashboard/supervisor_general/reports.php" 
           class="nav-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-label">التقارير</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="../../public/logout.php" class="nav-item logout">
            <span class="nav-icon">🚪</span>
            <span class="nav-label">تسجيل الخروج</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo isset($user['name']) ? mb_substr($user['name'], 0, 1) : 'س'; ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo isset($user['name']) ? htmlspecialchars($user['name']) : 'مشرف عام'; ?></div>
                <div class="user-role">مشرف عام</div>
            </div>
        </div>
    </div>
</aside>

<style>
    .dashboard-sidebar {
        width: 280px;
        background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        color: white;
        height: 100vh;
        position: fixed;
        right: 0;
        top: 0;
        display: flex;
        flex-direction: column;
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        z-index: 1000;
    }
    
    .sidebar-header {
        padding: 25px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .logo h2 {
        font-size: 1.5rem;
        margin-bottom: 8px;
        background: linear-gradient(135deg, #4285F4, #22c55e);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .role-badge {
        display: inline-block;
        background: rgba(66, 133, 244, 0.2);
        color: #4285F4;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .sidebar-nav {
        flex: 1;
        padding: 20px 0;
        overflow-y: auto;
    }
    
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .nav-item:hover {
        background: rgba(255,255,255,0.05);
        color: white;
    }
    
    .nav-item.active {
        background: linear-gradient(90deg, rgba(66, 133, 244, 0.2), transparent);
        color: white;
        border-right: 4px solid #4285F4;
    }
    
    .nav-item.active::before {
        content: '';
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #4285F4, #22c55e);
    }
    
    .nav-icon {
        font-size: 1.3rem;
        width: 30px;
        text-align: center;
    }
    
    .nav-label {
        flex: 1;
        font-size: 0.95rem;
        font-weight: 500;
    }
    
    .badge-count {
        background: #ef4444;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
        min-width: 20px;
        text-align: center;
    }
    
    .nav-divider {
        height: 1px;
        background: rgba(255,255,255,0.1);
        margin: 15px 20px;
    }
    
    .nav-item.logout {
        color: #ef4444;
    }
    
    .nav-item.logout:hover {
        background: rgba(239, 68, 68, 0.1);
    }
    
    .sidebar-footer {
        padding: 20px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .user-avatar {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, #4285F4, #22c55e);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.2rem;
    }
    
    .user-details {
        flex: 1;
    }
    
    .user-name {
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 2px;
    }
    
    .user-role {
        font-size: 0.8rem;
        color: rgba(255,255,255,0.6);
    }
    
    @media (max-width: 968px) {
        .dashboard-sidebar {
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .dashboard-sidebar.active {
            transform: translateX(0);
        }
    }
</style>
