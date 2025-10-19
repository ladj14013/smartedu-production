<?php
/**
 * Parent Sidebar Navigation
 * قائمة التنقل لولي الأمر
 */

// التحقق من وجود المتغيرات المطلوبة
if (!isset($user_id)) {
    $user_id = $_SESSION['user_id'] ?? 0;
}

if (!isset($parent)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $parent = $stmt->fetch();
    if (!$parent) {
        $parent = ['name' => $_SESSION['user_name'] ?? 'ولي الأمر'];
    }
}

// التحقق من وجود جدول parent_children قبل الاستعلام
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'parent_children'");
    $table_exists = $stmt->rowCount() > 0;
    
    if ($table_exists) {
        // Get children count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM parent_children WHERE parent_id = ?");
        $stmt->execute([$user_id]);
        $total_children = $stmt->fetchColumn();
    } else {
        $total_children = 0;
    }
} catch (PDOException $e) {
    $total_children = 0;
}

// Get unread messages count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_messages = $stmt->fetchColumn();
} catch (PDOException $e) {
    $unread_messages = 0;
}

// Get notifications count (unread)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages 
        WHERE receiver_id = ? AND is_read = 0 AND subject LIKE '%إشعار%'
    ");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetchColumn();
} catch (PDOException $e) {
    $unread_notifications = 0;
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <span class="logo-icon">👨‍👩‍👧‍👦</span>
            <span class="logo-text">ولي الأمر</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-text">الرئيسية</span>
        </a>
        
        <a href="children.php" class="nav-item <?php echo $current_page === 'children.php' ? 'active' : ''; ?>">
            <span class="nav-icon">👨‍👩‍👧‍👦</span>
            <span class="nav-text">أبنائي</span>
            <?php if ($total_children > 0): ?>
                <span class="badge badge-info"><?php echo $total_children; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="messages.php" class="nav-item <?php echo $current_page === 'messages.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📨</span>
            <span class="nav-text">الرسائل</span>
            <?php if ($unread_messages > 0): ?>
                <span class="badge badge-danger"><?php echo $unread_messages; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="notifications.php" class="nav-item <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🔔</span>
            <span class="nav-text">الإشعارات</span>
            <?php if ($unread_notifications > 0): ?>
                <span class="badge badge-warning"><?php echo $unread_notifications; ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="../../public/logout.php" class="nav-item nav-item-danger">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">تسجيل الخروج</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo mb_substr($parent['name'], 0, 1); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($parent['name']); ?></div>
                <div class="user-role">ولي أمر</div>
            </div>
        </div>
    </div>
</aside>

<style>
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, #a855f7 0%, #7c3aed 100%);
    height: 100vh;
    position: fixed;
    right: 0;
    top: 0;
    display: flex;
    flex-direction: column;
    box-shadow: -4px 0 20px rgba(168, 85, 247, 0.2);
    z-index: 1000;
    direction: rtl;
}

.sidebar-header {
    padding: 30px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
}

.logo-icon {
    font-size: 2.5rem;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
}

.logo-text {
    font-size: 1.5rem;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
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
    padding: 14px 20px;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    margin: 4px 10px;
    border-radius: 10px;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    transform: translateX(-5px);
}

.nav-item.active {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.nav-item.active::before {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 70%;
    background: white;
    border-radius: 0 4px 4px 0;
}

.nav-icon {
    font-size: 1.4rem;
    min-width: 24px;
    text-align: center;
}

.nav-text {
    flex: 1;
    font-size: 1rem;
    font-weight: 500;
}

.badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    min-width: 20px;
    text-align: center;
}

.badge-danger {
    background: #ef4444;
    color: white;
    animation: pulse 2s infinite;
}

.badge-warning {
    background: #f59e0b;
    color: white;
}

.badge-info {
    background: #3b82f6;
    color: white;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.nav-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.15);
    margin: 15px 20px;
}

.nav-item-danger:hover {
    background: rgba(239, 68, 68, 0.2);
}

.sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(0, 0, 0, 0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #ec4899, #f472b6);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
    font-weight: 700;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

.user-details {
    flex: 1;
    min-width: 0;
}

.user-name {
    color: white;
    font-weight: 600;
    font-size: 0.95rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 3px;
}

.user-role {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

/* Scrollbar */
.sidebar-nav::-webkit-scrollbar {
    width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Responsive */
@media (max-width: 968px) {
    .sidebar {
        transform: translateX(100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .mobile-toggle {
        display: block;
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 999;
        background: linear-gradient(135deg, #a855f7, #7c3aed);
        color: white;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);
    }
}

@media (min-width: 969px) {
    .mobile-toggle {
        display: none;
    }
}
</style>

<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    let toggleBtn = document.querySelector('.mobile-toggle');
    
    // Create mobile toggle if it doesn't exist
    if (!toggleBtn && window.innerWidth <= 968) {
        toggleBtn = document.createElement('button');
        toggleBtn.className = 'mobile-toggle';
        toggleBtn.innerHTML = '☰';
        document.body.appendChild(toggleBtn);
        
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            this.innerHTML = sidebar.classList.contains('active') ? '✕' : '☰';
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 968) {
            if (!sidebar.contains(e.target) && !e.target.classList.contains('mobile-toggle')) {
                sidebar.classList.remove('active');
                if (toggleBtn) toggleBtn.innerHTML = '☰';
            }
        }
    });
});
</script>
