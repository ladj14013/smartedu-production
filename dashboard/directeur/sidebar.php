<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Smart Education</h2>
        <p class="user-role">مدير</p>
    </div>
    
    <nav class="sidebar-nav">
        <a href="../../dashboard/directeur/index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <span>🏠</span> الرئيسية
        </a>
        <a href="../../dashboard/directeur/content.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'content.php' ? 'active' : ''; ?>">
            <span>📚</span> إدارة المحتوى
        </a>
        <a href="../../dashboard/directeur/users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
            <span>👥</span> المستخدمون
        </a>
        <a href="../../dashboard/directeur/messages.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>">
            <span>📨</span> الرسائل
        </a>
        <a href="../../dashboard/directeur/subjects.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'subjects.php' ? 'active' : ''; ?>">
            <span>📖</span> المواد
        </a>
        <a href="../../dashboard/directeur/stages.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'stages.php' ? 'active' : ''; ?>">
            <span>🎯</span> المراحل والمستويات
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar">
                <img src="<?php echo $_SESSION['user_avatar']; ?>" alt="Avatar">
            </div>
            <div class="user-details">
                <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <p class="user-email"><?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
            </div>
        </div>
        <a href="../../public/logout.php" class="btn btn-secondary btn-sm">تسجيل الخروج</a>
    </div>
</aside>
