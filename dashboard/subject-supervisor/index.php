<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// التحقق من تسجيل الدخول كمشرف مادة
require_auth();
if (!has_any_role(['superviseur_matiere', 'supervisor_subject', 'subject_supervisor'])) {
    header("Location: ../../dashboard/index.php");
    exit();
}

global $pdo;
$supervisor_id = $_SESSION['user_id'];
$subject_id = $_SESSION['subject_id'] ?? null;

// جلب معلومات المشرف والمادة
try {
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, s.name as subject_name, s.stage_id, st.name as stage_name
        FROM users u
        LEFT JOIN subjects s ON u.subject_id = s.id
        LEFT JOIN stages st ON s.stage_id = st.id
        WHERE u.id = ?
    ");
    $stmt->execute([$supervisor_id]);
    $supervisor = $stmt->fetch();
    
    if (!$supervisor || !$subject_id) {
        die("خطأ: لم يتم تعيين مادة لهذا المشرف. الرجاء تسجيل الخروج والدخول مرة أخرى.");
    }
    
    // عدد الأساتذة في المادة
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE subject_id = ? AND role IN ('enseignant', 'teacher')
    ");
    $stmt->execute([$subject_id]);
    $teachers_count = $stmt->fetchColumn();
    
    // عدد الدروس في المادة حسب الحالة
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM lessons WHERE subject_id = ?
    ");
    $stmt->execute([$subject_id]);
    $lessons_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $lessons_count = $lessons_stats['total'];
    $pending_count = $lessons_stats['pending'] ?? 0;
    $approved_count = $lessons_stats['approved'] ?? 0;
    $rejected_count = $lessons_stats['rejected'] ?? 0;
    
    // عدد التمارين في المادة
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.id) FROM exercises e
        JOIN lessons l ON e.lesson_id = l.id
        WHERE l.subject_id = ?
    ");
    $stmt->execute([$subject_id]);
    $exercises_count = $stmt->fetchColumn();
    
    // عدد الطلاب (حسب المرحلة)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE role IN ('etudiant', 'student') AND stage_id = ?
    ");
    $stmt->execute([$supervisor['stage_id']]);
    $students_count = $stmt->fetchColumn();
    
    // أحدث الدروس المعلقة
    $stmt = $pdo->prepare("
        SELECT l.*, CONCAT(u.nom, ' ', u.prenom) as teacher_name, lv.name as level_name
        FROM lessons l
        LEFT JOIN users u ON l.author_id = u.id
        LEFT JOIN levels lv ON l.level_id = lv.id
        WHERE l.subject_id = ? AND l.status = 'pending'
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$subject_id]);
    $pending_lessons = $stmt->fetchAll();
    
    // الأساتذة النشطين
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, 
               (SELECT COUNT(*) FROM lessons WHERE author_id = u.id) as lessons_count
        FROM users u
        WHERE u.subject_id = ? AND u.role IN ('enseignant', 'teacher')
        ORDER BY lessons_count DESC
        LIMIT 5
    ");
    $stmt->execute([$subject_id]);
    $active_teachers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "خطأ في جلب البيانات: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة مشرف المادة - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/rtl-sidebar.css">
    <style>
        body {
            direction: rtl;
            text-align: right;
        }
        
        .main-content {
            margin-right: 300px !important;
            margin-left: 0 !important;
            padding: 20px;
            min-height: 100vh;
            width: auto !important;
            box-sizing: border-box;
        }
        
        .dashboard-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 30px;
            direction: rtl;
            text-align: right;
        }

        .page-header {
            background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }

        .page-header p {
            margin: 5px 0;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            direction: rtl;
            text-align: right;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stat-card .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
            color: #9C27B0;
            margin-bottom: 10px;
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .section-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-card h2 {
            color: #333;
            margin: 0 0 20px 0;
            font-size: 22px;
            padding-bottom: 15px;
            border-bottom: 2px solid #9C27B0;
        }

        .lesson-item, .teacher-item {
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-right: 4px solid #9C27B0;
        }

        .lesson-item:hover, .teacher-item:hover {
            background: #f3e5f5;
        }

        .lesson-title, .teacher-name {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        .lesson-meta, .teacher-meta {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #666;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-public {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-private {
            background: #fff3e0;
            color: #e65100;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }

        .action-btn {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(156, 39, 176, 0.2);
            background: #f3e5f5;
        }

        .action-btn .icon {
            font-size: 36px;
        }

        .action-btn .text {
            color: #9C27B0;
            font-weight: 600;
        }

        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <div class="page-header">
                <h1>📊 لوحة مشرف المادة</h1>
                <p><strong>المادة:</strong> <?php echo htmlspecialchars($supervisor['subject_name'] ?? 'غير محددة'); ?></p>
                <p><strong>المرحلة:</strong> <?php echo htmlspecialchars($supervisor['stage_name'] ?? 'غير محددة'); ?></p>
                <p><strong>المشرف:</strong> <?php echo htmlspecialchars($supervisor['name']); ?></p>
            </div>

            <?php if (isset($error_message)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon">⏳</div>
                    <div class="value"><?php echo $pending_count; ?></div>
                    <div class="label">دروس معلقة</div>
                </div>

                <div class="stat-card">
                    <div class="icon">✅</div>
                    <div class="value"><?php echo $approved_count; ?></div>
                    <div class="label">دروس معتمدة</div>
                </div>

                <div class="stat-card">
                    <div class="icon">❌</div>
                    <div class="value"><?php echo $rejected_count; ?></div>
                    <div class="label">دروس مرفوضة</div>
                </div>

                <div class="stat-card">
                    <div class="icon">👨‍🏫</div>
                    <div class="value"><?php echo $teachers_count; ?></div>
                    <div class="label">الأساتذة</div>
                </div>

                <div class="stat-card">
                    <div class="icon">✍️</div>
                    <div class="value"><?php echo $exercises_count; ?></div>
                    <div class="label">التمارين</div>
                </div>

                <div class="stat-card">
                    <div class="icon">🎓</div>
                    <div class="value"><?php echo $students_count; ?></div>
                    <div class="label">الطلاب</div>
                </div>
            </div>

            <div class="content-grid">
                <div class="section-card">
                    <h2>⏳ الدروس المعلقة (تحتاج مراجعة)</h2>
                    <?php if (count($pending_lessons) > 0): ?>
                        <?php foreach ($pending_lessons as $lesson): ?>
                            <div class="lesson-item">
                                <div class="lesson-title">
                                    <?php echo htmlspecialchars($lesson['title']); ?>
                                </div>
                                <div class="lesson-meta">
                                    <span class="meta-item">
                                        👨‍🏫 <?php echo htmlspecialchars($lesson['teacher_name'] ?? 'غير معروف'); ?>
                                    </span>
                                    <span class="meta-item">
                                        📅 <?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?>
                                    </span>
                                    <span class="badge" style="background: #fff3cd; color: #856404;">
                                        ⏳ معلق
                                    </span>
                                </div>
                                <a href="review-lesson.php?id=<?php echo $lesson['id']; ?>" 
                                   style="display: inline-block; margin-top: 10px; padding: 6px 15px; background: #9C27B0; color: white; text-decoration: none; border-radius: 5px; font-size: 13px;">
                                    👁️ مراجعة الآن
                                </a>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($pending_count > 5): ?>
                            <div style="text-align: center; margin-top: 15px;">
                                <a href="pending-lessons.php" style="color: #9C27B0; text-decoration: none; font-weight: 600;">
                                    عرض جميع الدروس المعلقة (<?php echo $pending_count; ?>)  ←
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>✅ لا توجد دروس معلقة</p>
                            <p style="font-size: 12px; color: #999;">جميع الدروس تمت مراجعتها</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="section-card">
                    <h2>⭐ الأساتذة النشطين</h2>
                    <?php if (count($active_teachers) > 0): ?>
                        <?php foreach ($active_teachers as $teacher): ?>
                            <div class="teacher-item">
                                <div class="teacher-name">
                                    👨‍🏫 <?php echo htmlspecialchars($teacher['name']); ?>
                                </div>
                                <div class="teacher-meta">
                                    <span class="meta-item">
                                        ✉️ <?php echo htmlspecialchars($teacher['email']); ?>
                                    </span>
                                    <span class="meta-item">
                                        📚 <?php echo $teacher['lessons_count']; ?> درس
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>لا يوجد أساتذة بعد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="quick-actions">
                <a href="pending-lessons.php" class="action-btn" style="border: 2px solid #FF9800;">
                    <span class="icon" style="color: #FF9800;">⏳</span>
                    <span class="text" style="color: #FF9800;">الدروس المعلقة</span>
                    <?php if ($pending_count > 0): ?>
                        <span style="background: #FF9800; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                            <?php echo $pending_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="approved-lessons.php" class="action-btn">
                    <span class="icon" style="color: #4CAF50;">✅</span>
                    <span class="text" style="color: #4CAF50;">الدروس المعتمدة</span>
                </a>
                <a href="teachers.php" class="action-btn">
                    <span class="icon">👥</span>
                    <span class="text">إدارة الأساتذة</span>
                </a>
                <a href="lessons.php" class="action-btn">
                    <span class="icon">📚</span>
                    <span class="text">جميع الدروس</span>
                </a>
                <a href="students.php" class="action-btn">
                    <span class="icon">🎓</span>
                    <span class="text">تقدم الطلاب</span>
                </a>
                <a href="statistics.php" class="action-btn">
                    <span class="icon">📊</span>
                    <span class="text">الإحصائيات</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
