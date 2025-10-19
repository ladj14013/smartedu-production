<?php
/**
 * Student Sidebar - قائمة التنقل للطالب
 */

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Get student ID and level from session
$student_id = $_SESSION['user_id'] ?? null;
$student_level_id = $_SESSION['level_id'] ?? null;
$student_stage_id = $_SESSION['stage_id'] ?? null;

// Get database connection
if (!isset($pdo)) {
    global $pdo;
}

// Get student info
try {
    $student_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $student_stmt->execute([$student_id]);
    $student = $student_stmt->fetch();
    
    if (!$student) {
        $student = ['name' => 'التلميذ', 'email' => ''];
    }
} catch (PDOException $e) {
    $student = ['name' => 'التلميذ', 'email' => ''];
}

// Get completed lessons count
try {
    $completed_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM lesson_progress 
        WHERE student_id = ? AND completed = 1
    ");
    $completed_stmt->execute([$student_id]);
    $completed_lessons = $completed_stmt->fetchColumn();
} catch (PDOException $e) {
    $completed_lessons = 0;
}

try {
    // جلب عدد التمارين المتاحة
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.id) as pending_exercises
        FROM exercises e
        JOIN lessons l ON e.lesson_id = l.id
        WHERE l.level_id = ?
        AND e.id NOT IN (
            SELECT exercise_id FROM exercises_results WHERE student_id = ?
        )
    ");
    $stmt->execute([$student_level_id, $student_id]);
    $pending_exercises = $stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_exercises = 0;
}
?>

<style>
    .sidebar {
        width: 280px;
        background: linear-gradient(180deg, #22c55e 0%, #16a34a 100%);
        color: white;
        height: 100vh;
        position: fixed;
        right: 0 !important;
        left: auto !important;
        top: 0;
        overflow-y: auto;
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        z-index: 1000;
        transition: transform 0.3s ease;
        direction: rtl;
        display: flex;
        flex-direction: column;
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
        flex: 1;
        overflow-y: auto;
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
        position: relative;
        flex-shrink: 0;
        white-space: nowrap;
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
    
    .nav-item span {
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    
    .nav-item span:last-child {
        font-size: 1rem;
        flex: 1;
    }
    
    .badge {
        position: absolute;
        left: 20px;
        background: #ef4444;
        color: white;
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 600;
    }
    
    .sidebar-footer {
        width: 100%;
        padding: 20px 25px;
        background: rgba(0,0,0,0.1);
        border-top: 1px solid rgba(255,255,255,0.1);
        margin-top: auto;
        flex-shrink: 0;
    }
    
    .user-info {
        margin-bottom: 15px;
    }
    
    .user-name {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 4px;
    }
    
    .user-email {
        font-size: 0.85rem;
        opacity: 0.8;
    }
    
    .progress-info {
        background: rgba(255,255,255,0.15);
        padding: 10px;
        border-radius: 8px;
        margin-top: 10px;
        font-size: 0.85rem;
    }
    
    .progress-info strong {
        font-size: 1.2rem;
        display: block;
        margin-bottom: 4px;
    }
    
    /* Mobile Toggle */
    .sidebar-toggle {
        display: none;
        position: fixed;
        bottom: 20px;
        left: 20px;
        background: #22c55e;
        color: white;
        border: none;
        padding: 15px;
        border-radius: 50%;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 999;
        transition: all 0.3s ease;
    }

    .sidebar-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    }

    /* Mobile Styles */
    @media (max-width: 968px) {
        .sidebar {
            transform: translateX(100%);
            z-index: 1001;
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar-toggle {
            display: block;
        }

        /* Overlay when sidebar is open */
        .sidebar.active::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: -1;
        }
    }

    /* Hide sidebar on very small screens by default */
    @media (max-width: 768px) {
        .sidebar {
            width: 100%;
            max-width: 300px;
        }
    }
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2>🎓 SmartEdu</h2>
        <p class="user-role">طالب</p>
    </div>
    
    <nav class="sidebar-nav">
        <a href="../../dashboard/student/index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <span>🏠</span>
            <span>الرئيسية</span>
        </a>
        
        <a href="../../dashboard/student/available-subjects.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'available-subjects.php' ? 'active' : ''; ?>">
            <span>📚</span>
            <span>المواد المتاحة</span>
        </a>
        
        <a href="../../dashboard/student/exercises.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'exercises.php' ? 'active' : ''; ?>">
            <span>✍️</span>
            <span>التمارين</span>
            <?php if ($pending_exercises > 0): ?>
                <span class="badge"><?php echo $pending_exercises; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="../../dashboard/student/results.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'results.php' ? 'active' : ''; ?>">
            <span>📊</span>
            <span>نتائجي</span>
        </a>
        
        <a href="../../dashboard/student/link-teacher.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'link-teacher.php' ? 'active' : ''; ?>">
            <span>🔗</span>
            <span>ربط بأستاذ</span>
        </a>
        
        <a href="../../dashboard/student/link-parent.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'link-parent.php' ? 'active' : ''; ?>">
            <span>👨‍👩‍👧‍👦</span>
            <span>ربط ولي الأمر</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../../public/logout.php" class="nav-item" style="width: 100%; margin-bottom: 15px;">
            <span>🚪</span>
            <span>تسجيل الخروج</span>
        </a>
        
        <div class="user-info">
            <p class="user-name"><?php echo htmlspecialchars($student['name']); ?></p>
            <p class="user-email"><?php echo htmlspecialchars($student['email']); ?></p>
            
            <div class="progress-info">
                <strong><?php echo $completed_lessons; ?></strong>
                دروس مكتملة 🎯
            </div>
        </div>
    </div>
</aside>

<button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
    ☰
</button>

<script>
    // إغلاق القائمة عند الضغط على رابط في الموبايل
    if (window.innerWidth <= 968) {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelector('.sidebar').classList.remove('active');
            });
        });
    }
</script>
