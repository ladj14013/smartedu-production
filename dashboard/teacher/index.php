<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// التحقق من تسجيل الدخول والصلاحيات
require_auth();
if (!has_any_role(['enseignant', 'teacher'])) {
    header("Location: ../../dashboard/index.php");
    exit();
}

global $pdo;
$user_id = $_SESSION['user_id'];

// جلب معلومات الأستاذ
try {
    $teacher_query = $pdo->prepare("
        SELECT u.*, s.name as subject_name, st.name as stage_name
        FROM users u
        LEFT JOIN subjects s ON u.subject_id = s.id
        LEFT JOIN stages st ON s.stage_id = st.id
        WHERE u.id = ?
    ");
    $teacher_query->execute([$user_id]);
    $teacher = $teacher_query->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في جلب البيانات: " . $e->getMessage());
}

// إحصائيات الأستاذ
try {
    // عدد الدروس
    $lessons_count = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE author_id = ?");
    $lessons_count->execute([$user_id]);
    $total_lessons = $lessons_count->fetchColumn();
    
    // عدد التمارين
    $exercises_query = $pdo->prepare("
        SELECT COUNT(*) FROM exercises e 
        JOIN lessons l ON e.lesson_id = l.id 
        WHERE l.author_id = ?
    ");
    $exercises_query->execute([$user_id]);
    $total_exercises = $exercises_query->fetchColumn();
    
    // عدد الطلاب (في نفس المرحلة والمستوى)
    $students_query = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) FROM users u
        JOIN levels lv ON u.level_id = lv.id
        WHERE lv.stage_id = (SELECT stage_id FROM subjects WHERE id = ?)
        AND u.role IN ('etudiant', 'student')
    ");
    $students_query->execute([$teacher['subject_id']]);
    $total_students = $students_query->fetchColumn();
    
    // آخر الدروس المضافة
    $recent_lessons = $pdo->prepare("
        SELECT l.*, lv.name as level_name
        FROM lessons l
        LEFT JOIN levels lv ON l.level_id = lv.id
        WHERE l.author_id = ?
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
    $recent_lessons->execute([$user_id]);
    $recent_lessons_list = $recent_lessons->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $total_lessons = 0;
    $total_exercises = 0;
    $total_students = 0;
    $recent_lessons_list = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الأستاذ - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/rtl-sidebar.css">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --teacher-primary: #4CAF50;
            --teacher-secondary: #45a049;
            --teacher-light: #e8f5e9;
        }
        
        * {
            font-family: 'Amiri', serif;
        }
        
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            min-height: 100vh;
            direction: rtl;
            text-align: right;
        }
        
        .main-content {
            margin-right: 300px !important;
            margin-left: 0 !important;
            padding: 30px;
            min-height: 100vh;
            width: auto !important;
            box-sizing: border-box;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            margin: 0 0 10px 0;
            color: var(--teacher-primary);
            font-size: 2.2rem;
        }
        
        .teacher-info {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .info-badge {
            background: var(--teacher-light);
            padding: 8px 16px;
            border-radius: 20px;
            color: var(--teacher-primary);
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--teacher-primary);
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .action-btn {
            background: linear-gradient(135deg, var(--teacher-primary), var(--teacher-secondary));
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }
        
        .action-btn span {
            display: block;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .recent-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .recent-section h2 {
            margin: 0 0 20px 0;
            color: var(--teacher-primary);
            font-size: 1.6rem;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--teacher-light);
        }
        
        .lesson-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .lesson-item:last-child {
            border-bottom: none;
        }
        
        .lesson-item:hover {
            background: #f8f9fa;
            padding-right: 20px;
        }
        
        .lesson-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .lesson-meta {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.95rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-right: 0 !important;
                padding: 15px;
                width: 100% !important;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .teacher-info {
                flex-direction: column;
                gap: 10px;
            }

            .info-badge {
                width: fit-content;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .quick-actions {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .action-btn {
                padding: 15px;
            }

            .recent-section {
                padding: 20px;
            }

            .lesson-meta {
                flex-direction: column;
                gap: 5px;
            }
        }

        /* تحسينات إضافية للهواتف الصغيرة */
        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .stat-label {
                font-size: 1rem;
            }

            .recent-section h2 {
                font-size: 1.3rem;
            }

            .lesson-title {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>👋 مرحباً، <?php echo htmlspecialchars($teacher['nom'] ?? $teacher['name']); ?></h1>
            <p style="color: #666; margin: 5px 0;">لوحة تحكم الأستاذ</p>
            
            <div class="teacher-info">
                <?php if ($teacher['subject_name']): ?>
                    <div class="info-badge">
                        📚 المادة: <?php echo htmlspecialchars($teacher['subject_name']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($teacher['stage_name']): ?>
                    <div class="info-badge">
                        🎓 المرحلة: <?php echo htmlspecialchars($teacher['stage_name']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo $total_lessons; ?></div>
                <div class="stat-label">الدروس المضافة</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?php echo $total_exercises; ?></div>
                <div class="stat-label">التمارين والواجبات</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">الطلاب</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="create-lesson.php" class="action-btn">
                <span>➕</span>
                إضافة درس جديد
            </a>
            
            <a href="lessons.php" class="action-btn">
                <span>📖</span>
                إدارة الدروس
            </a>
            
            <a href="exercises.php" class="action-btn">
                <span>📝</span>
                إدارة التمارين
            </a>
            
            <a href="students.php" class="action-btn">
                <span>👥</span>
                الطلاب والتقييمات
            </a>
        </div>
        
        <!-- Recent Lessons -->
        <div class="recent-section">
            <h2>📚 آخر الدروس المضافة</h2>
            
            <?php if (empty($recent_lessons_list)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📖</div>
                    <h3>لم تقم بإضافة دروس بعد</h3>
                    <p>ابدأ بإضافة درسك الأول من الزر أعلاه</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_lessons_list as $lesson): ?>
                    <div class="lesson-item">
                        <div class="lesson-title">
                            📖 <?php echo htmlspecialchars($lesson['title']); ?>
                        </div>
                        <div class="lesson-meta">
                            <span>📚 <?php echo htmlspecialchars($lesson['level_name'] ?? 'غير محدد'); ?></span>
                            <span>📅 <?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
