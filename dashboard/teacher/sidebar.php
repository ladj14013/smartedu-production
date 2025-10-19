<?php
/**
 * Teacher Sidebar Navigation - Updated
 * الشريط الجانبي للأستاذ - محدث
 */

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Get teacher ID from session
$teacher_id = $_SESSION['user_id'] ?? null;

// Get database connection - use global $pdo
global $pdo;
if (!isset($pdo)) {
    require_once '../../config/database.php';
}

// Get teacher info if not already loaded
if (!isset($teacher)) {
    try {
        $teacher_query = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $teacher_query->execute([$teacher_id]);
        $teacher = $teacher_query->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $teacher = ['name' => 'الأستاذ'];
    }
}

// Get subject info if teacher has a subject
$subject = null;
if (isset($teacher['subject_id']) && $teacher['subject_id']) {
    try {
        $subject_query = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $subject_query->execute([$teacher['subject_id']]);
        $subject = $subject_query->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $subject = null;
    }
}

// Get counts for badges
try {
    // عدد الرسائل غير المقروءة الخاصة بهذا الأستاذ
    // مع مراعاة read_by للرسائل العامة وdeleted_by
    $unread_query = $pdo->prepare("
        SELECT * 
        FROM messages 
        WHERE ((recipient_type = 'teacher' AND recipient_id = ?) 
               OR recipient_type = 'general')
        AND (deleted_by IS NULL OR deleted_by NOT LIKE CONCAT('%', ?, '%'))
    ");
    $unread_query->execute([$teacher_id, $teacher_id]);
    $messages = $unread_query->fetchAll(PDO::FETCH_ASSOC);
    
    $unread_messages = 0;
    foreach ($messages as $msg) {
        if ($msg['recipient_type'] === 'general') {
            // للرسائل العامة: تحقق من read_by
            $read_by = $msg['read_by'] ? explode(',', $msg['read_by']) : [];
            if (!in_array($teacher_id, $read_by)) {
                $unread_messages++;
            }
        } else {
            // للرسائل الخاصة: تحقق من is_read
            if (!$msg['is_read']) {
                $unread_messages++;
            }
        }
    }
} catch (PDOException $e) {
    // If messages table doesn't exist or query fails, just set to 0
    $unread_messages = 0;
}
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <span class="logo-icon">👨‍🏫</span>
            <span class="logo-text">لوحة الأستاذ</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo in_array($current_page, ['index.php', 'index_new.php']) ? 'active' : ''; ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-text">الرئيسية</span>
        </a>
        
        <a href="manage-lessons.php" class="nav-item <?php echo in_array($current_page, ['manage-lessons.php', 'create-lesson.php', 'edit-lesson.php']) ? 'active' : ''; ?>">
            <span class="nav-icon">📚</span>
            <span class="nav-text">إدارة الدروس</span>
        </a>
        
        <a href="my-lessons.php" class="nav-item <?php echo $current_page === 'my-lessons.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📖</span>
            <span class="nav-text">دروسي</span>
            <?php if (isset($stats['rejected']) && $stats['rejected'] > 0): ?>
                <span class="nav-badge" style="background: #f44336;"><?php echo $stats['rejected']; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="exercises.php" class="nav-item <?php echo in_array($current_page, ['exercises.php', 'exercise-form.php']) ? 'active' : ''; ?>">
            <span class="nav-icon">📝</span>
            <span class="nav-text">التمارين والواجبات</span>
        </a>
        
        <a href="my-code.php" class="nav-item <?php echo $current_page === 'my-code.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🔑</span>
            <span class="nav-text">كودي الخاص</span>
        </a>
        
        <a href="my-students.php" class="nav-item <?php echo $current_page === 'my-students.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🎓</span>
            <span class="nav-text">تلاميذي</span>
        </a>
        
        <a href="messages.php" class="nav-item <?php echo $current_page === 'messages.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📨</span>
            <span class="nav-text">الرسائل</span>
            <?php if ($unread_messages > 0): ?>
                <span class="badge badge-danger"><?php echo $unread_messages; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="settings.php" class="nav-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
            <span class="nav-icon">⚙️</span>
            <span class="nav-text">الإعدادات</span>
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
                <?php echo mb_substr($teacher['nom'] ?? $teacher['name'], 0, 1, 'UTF-8'); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($teacher['nom'] ?? $teacher['name']); ?></div>
                <div class="user-role">
                    أستاذ<?php echo ($subject ? ' ' . htmlspecialchars($subject['name']) : ''); ?>
                </div>
            </div>
        </div>
    </div>
</aside>

<style>
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, #4CAF50 0%, #45a049 100%);
    height: 100vh;
    position: fixed;
    right: 0 !important;
    left: auto !important;
    top: 0;
    display: flex;
    flex-direction: column;
    box-shadow: -4px 0 20px rgba(76, 175, 80, 0.15);
    z-index: 1000;
    direction: rtl;
}

.sidebar-header {
    padding: 30px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
    font-size: 1.4rem;
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
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    margin: 4px 10px;
    border-radius: 10px;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transform: translateX(-5px);
}

.nav-item.active {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.nav-item.active::before {
    content: '';
    position: absolute;
    left: 0;
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
}

.badge-warning {
    background: #FFA726;
    color: white;
}

.nav-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.1);
    margin: 15px 20px;
}

.nav-item-danger:hover {
    background: rgba(239, 68, 68, 0.2);
}

.sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
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
    background: linear-gradient(135deg, #45a049, #388E3C);
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
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Scrollbar Styling */
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
    
    .main-content {
        margin-right: 0 !important;
    }
    
    .mobile-toggle {
        display: block;
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 999;
        background: linear-gradient(135deg, #4CAF50, #45a049);
        color: white;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
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
