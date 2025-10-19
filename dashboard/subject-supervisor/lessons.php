<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

if (!$subject_id) {
    die("خطأ: لم يتم تعيين مادة لهذا المشرف. الرجاء تسجيل الخروج والدخول مرة أخرى.");
}

// جلب معلومات المادة
$stmt = $pdo->prepare("
    SELECT s.name as subject_name, st.name as stage_name
    FROM subjects s
    LEFT JOIN stages st ON s.stage_id = st.id
    WHERE s.id = ?
");
$stmt->execute([$subject_id]);
$subject = $stmt->fetch();

// جلب جميع الدروس في هذه المادة
$stmt = $pdo->prepare("
    SELECT 
        l.id,
        l.title,
        l.content,
        l.description,
        l.type,
        l.created_at,
        u.name as teacher_name,
        lv.name as level_name,
        COUNT(DISTINCT e.id) as exercises_count,
        COUNT(DISTINCT pr.id) as completions_count
    FROM lessons l
    LEFT JOIN users u ON l.author_id = u.id
    LEFT JOIN levels lv ON l.level_id = lv.id
    LEFT JOIN exercises e ON e.lesson_id = l.id
    LEFT JOIN student_progress pr ON pr.lesson_id = l.id AND pr.completion_date IS NOT NULL
    WHERE l.subject_id = ?
    GROUP BY l.id
    ORDER BY l.created_at DESC
");
$stmt->execute([$subject_id]);
$lessons = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دروس <?php echo htmlspecialchars($subject['subject_name'] ?? 'المادة'); ?> | مشرف المادة</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            display: flex;
        }
        
        .main-content {
            flex: 1;
            margin-right: 280px;
            padding: 30px;
        }
        
        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .breadcrumb {
            font-size: 0.9rem;
            color: #718096;
        }
        
        .breadcrumb a {
            color: #9C27B0;
            text-decoration: none;
        }
        
        .stats-bar {
            background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-around;
            align-items: center;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .lessons-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .lesson-item {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .lesson-item:hover {
            background: #f7fafc;
        }
        
        .lesson-item:last-child {
            border-bottom: none;
        }
        
        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .lesson-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .lesson-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .lesson-title h3 {
            font-size: 1.2rem;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .lesson-type {
            font-size: 0.8rem;
            color: white;
            background: #9C27B0;
            padding: 4px 12px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .lesson-description {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .lesson-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 0.85rem;
            color: #4a5568;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .lesson-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .stat-badge {
            background: #f7fafc;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #4a5568;
        }
        
        .stat-badge strong {
            color: #9C27B0;
            font-weight: 700;
        }
        
        .no-lessons {
            text-align: center;
            padding: 60px 20px;
        }
        
        .no-lessons-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .no-lessons h3 {
            font-size: 1.5rem;
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .no-lessons p {
            color: #718096;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>📚 دروس المادة</h1>
            <div class="breadcrumb">
                <a href="index.php">الرئيسية</a> / الدروس / 
                <strong><?php echo htmlspecialchars($subject['subject_name'] ?? 'المادة'); ?></strong>
            </div>
        </div>
        
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-value"><?php echo count($lessons); ?></div>
                <div class="stat-label">إجمالي الدروس</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo array_sum(array_column($lessons, 'exercises_count')); ?></div>
                <div class="stat-label">التمارين</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo array_sum(array_column($lessons, 'completions_count')); ?></div>
                <div class="stat-label">إكمالات الدروس</div>
            </div>
        </div>
        
        <div class="lessons-container">
            <?php if (empty($lessons)): ?>
                <div class="no-lessons">
                    <div class="no-lessons-icon">📚</div>
                    <h3>لا توجد دروس بعد</h3>
                    <p>لم يتم إضافة دروس لهذه المادة حتى الآن</p>
                </div>
            <?php else: ?>
                <?php foreach ($lessons as $lesson): ?>
                    <div class="lesson-item">
                        <div class="lesson-header">
                            <div class="lesson-title">
                                <div class="lesson-icon">📖</div>
                                <div>
                                    <h3><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                    <span class="lesson-type">
                                        <?php echo $lesson['type'] == 'video' ? '🎥 فيديو' : '📄 نصي'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($lesson['description'])): ?>
                            <p class="lesson-description">
                                <?php echo htmlspecialchars(mb_substr($lesson['description'], 0, 200, 'UTF-8')); ?>
                                <?php echo mb_strlen($lesson['description'], 'UTF-8') > 200 ? '...' : ''; ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="lesson-meta">
                            <span class="meta-item">
                                <span>👨‍🏫</span>
                                <span><?php echo htmlspecialchars($lesson['teacher_name'] ?? 'غير محدد'); ?></span>
                            </span>
                            <span class="meta-item">
                                <span>📚</span>
                                <span><?php echo htmlspecialchars($lesson['level_name'] ?? 'جميع المستويات'); ?></span>
                            </span>
                            <span class="meta-item">
                                <span>📅</span>
                                <span><?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?></span>
                            </span>
                        </div>
                        
                        <div class="lesson-stats">
                            <div class="stat-badge">
                                <strong><?php echo $lesson['exercises_count']; ?></strong> تمرين
                            </div>
                            <div class="stat-badge">
                                <strong><?php echo $lesson['completions_count']; ?></strong> طالب أكمل
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
