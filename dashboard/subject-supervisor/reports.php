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

// إحصائيات شاملة
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT l.id) as total_lessons,
        COUNT(DISTINCT e.id) as total_exercises,
        COUNT(DISTINCT u.id) as total_teachers,
        COUNT(DISTINCT lp.id) as total_completions,
        AVG(er.score) as avg_score
    FROM lessons l
    LEFT JOIN exercises e ON e.lesson_id = l.id
    LEFT JOIN users u ON l.author_id = u.id
    LEFT JOIN student_progress lp ON lp.lesson_id = l.id AND lp.completion_date IS NOT NULL
    LEFT JOIN exercise_results er ON er.exercise_id = e.id
    WHERE l.subject_id = ?
");
$stmt->execute([$subject_id]);
$stats = $stmt->fetch();

// أفضل الأساتذة (بناءً على عدد الدروس)
$stmt = $pdo->prepare("
    SELECT 
        u.name,
        COUNT(DISTINCT l.id) as lessons_count,
        COUNT(DISTINCT lp.id) as completions_count
    FROM users u
    LEFT JOIN lessons l ON l.author_id = u.id AND l.subject_id = ?
    LEFT JOIN student_progress lp ON lp.lesson_id = l.id AND lp.completion_date IS NOT NULL
    WHERE u.subject_id = ? AND u.role IN ('enseignant', 'teacher')
    GROUP BY u.id
    ORDER BY lessons_count DESC
    LIMIT 5
");
$stmt->execute([$subject_id, $subject_id]);
$top_teachers = $stmt->fetchAll();

// الدروس الأكثر إكمالاً
$stmt = $pdo->prepare("
    SELECT 
        l.title,
        u.name as teacher_name,
        COUNT(DISTINCT lp.id) as completions_count
    FROM lessons l
    LEFT JOIN users u ON l.author_id = u.id
    LEFT JOIN student_progress lp ON lp.lesson_id = l.id AND lp.completion_date IS NOT NULL
    WHERE l.subject_id = ?
    GROUP BY l.id
    ORDER BY completions_count DESC
    LIMIT 5
");
$stmt->execute([$subject_id]);
$popular_lessons = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقارير <?php echo htmlspecialchars($subject['subject_name'] ?? 'المادة'); ?> | مشرف المادة</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-right: 4px solid #9C27B0;
        }
        
        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .stat-card .label {
            font-size: 0.9rem;
            color: #718096;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
        }
        
        .report-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .report-header {
            background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
            color: white;
            padding: 20px 25px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .report-body {
            padding: 25px;
        }
        
        .ranking-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        
        .ranking-item:last-child {
            margin-bottom: 0;
        }
        
        .rank-number {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
        }
        
        .rank-info {
            flex: 1;
        }
        
        .rank-name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .rank-details {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .rank-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #9C27B0;
        }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
        }
        
        .no-data-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>📊 تقارير وإحصائيات</h1>
            <div class="breadcrumb">
                <a href="index.php">الرئيسية</a> / التقارير / 
                <strong><?php echo htmlspecialchars($subject['subject_name'] ?? 'المادة'); ?></strong>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">📚</div>
                <div class="value"><?php echo $stats['total_lessons']; ?></div>
                <div class="label">إجمالي الدروس</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">📝</div>
                <div class="value"><?php echo $stats['total_exercises']; ?></div>
                <div class="label">إجمالي التمارين</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">👨‍🏫</div>
                <div class="value"><?php echo $stats['total_teachers']; ?></div>
                <div class="label">عدد الأساتذة</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">✅</div>
                <div class="value"><?php echo $stats['total_completions']; ?></div>
                <div class="label">إكمالات الدروس</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">⭐</div>
                <div class="value"><?php echo $stats['avg_score'] ? round($stats['avg_score'], 1) . '%' : '-'; ?></div>
                <div class="label">متوسط الدرجات</div>
            </div>
        </div>
        
        <div class="reports-grid">
            <!-- أفضل الأساتذة -->
            <div class="report-card">
                <div class="report-header">
                    🏆 أفضل الأساتذة
                </div>
                <div class="report-body">
                    <?php if (empty($top_teachers)): ?>
                        <div class="no-data">
                            <div class="no-data-icon">👨‍🏫</div>
                            <p>لا توجد بيانات متاحة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($top_teachers as $index => $teacher): ?>
                            <div class="ranking-item">
                                <div class="rank-number"><?php echo $index + 1; ?></div>
                                <div class="rank-info">
                                    <div class="rank-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                                    <div class="rank-details">
                                        <?php echo $teacher['completions_count']; ?> إكمال
                                    </div>
                                </div>
                                <div class="rank-value"><?php echo $teacher['lessons_count']; ?> <small>دروس</small></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- الدروس الأكثر شعبية -->
            <div class="report-card">
                <div class="report-header">
                    🔥 الدروس الأكثر إكمالاً
                </div>
                <div class="report-body">
                    <?php if (empty($popular_lessons)): ?>
                        <div class="no-data">
                            <div class="no-data-icon">📚</div>
                            <p>لا توجد بيانات متاحة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($popular_lessons as $index => $lesson): ?>
                            <div class="ranking-item">
                                <div class="rank-number"><?php echo $index + 1; ?></div>
                                <div class="rank-info">
                                    <div class="rank-name"><?php echo htmlspecialchars($lesson['title']); ?></div>
                                    <div class="rank-details">
                                        بواسطة: <?php echo htmlspecialchars($lesson['teacher_name'] ?? 'غير محدد'); ?>
                                    </div>
                                </div>
                                <div class="rank-value"><?php echo $lesson['completions_count']; ?> <small>طالب</small></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
