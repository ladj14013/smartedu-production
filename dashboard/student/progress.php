<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('student');

$student_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

// جلب إحصائيات التقدم
$query = "SELECT 
            COUNT(DISTINCT sp.lesson_id) as completed_lessons,
            COUNT(DISTINCT sa.exercise_id) as completed_exercises,
            AVG(sa.score) as average_score
          FROM student_progress sp
          LEFT JOIN student_answers sa ON sp.student_id = sa.student_id
          WHERE sp.student_id = :student_id AND sp.completion_date IS NOT NULL";
$stmt = $db->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$stats = $stmt->fetch();

// جلب آخر الدروس المكتملة
$query = "SELECT l.title, l.created_at, s.name as subject_name, sp.completion_date, sp.score
          FROM student_progress sp
          JOIN lessons l ON sp.lesson_id = l.id
          JOIN subjects s ON l.subject_id = s.id
          WHERE sp.student_id = :student_id AND sp.completion_date IS NOT NULL
          ORDER BY sp.completion_date DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$completed_lessons = $stmt->fetchAll();

// جلب آخر التمارين المحلولة
$query = "SELECT e.question, l.title as lesson_title, sa.score, sa.submitted_at
          FROM student_answers sa
          JOIN exercises e ON sa.exercise_id = e.id
          JOIN lessons l ON e.lesson_id = l.id
          WHERE sa.student_id = :student_id
          ORDER BY sa.submitted_at DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$recent_exercises = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقدمي - Smart Education Hub</title>
    <link rel="stylesheet" href="/assets/css/style.css">
        <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="progress-mobile.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>📊 تقدمي الدراسي</h1>
                <p>تابع إنجازاتك وتقدمك في التعلم</p>
            </div>
            
            <!-- إحصائيات -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(34, 197, 94, 0.1); color: #22c55e;">
                        <span style="font-size: 2rem;">✅</span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['completed_lessons'] ?? 0; ?></h3>
                        <p>الدروس المكتملة</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(255, 167, 38, 0.1); color: #FFA726;">
                        <span style="font-size: 2rem;">✍️</span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['completed_exercises'] ?? 0; ?></h3>
                        <p>التمارين المحلولة</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                        <span style="font-size: 2rem;">⭐</span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['average_score'] ? round($stats['average_score'], 1) . '%' : 'N/A'; ?></h3>
                        <p>متوسط الدرجات</p>
                    </div>
                </div>
            </div>
            
            <!-- الدروس المكتملة -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2 class="card-title">✅ الدروس المكتملة</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($completed_lessons)): ?>
                        <div class="text-center" style="padding: 2rem; color: var(--text-secondary);">
                            <p>لم تكمل أي درس بعد. ابدأ في تعلم محتوى جديد!</p>
                        </div>
                    <?php else: ?>
                        <div class="progress-list">
                            <?php foreach ($completed_lessons as $lesson): ?>
                                <div class="progress-item">
                                    <div class="progress-info">
                                        <h4><?php echo htmlspecialchars($lesson['title']); ?></h4>
                                        <p>
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($lesson['subject_name']); ?></span>
                                            <small style="color: var(--text-secondary); margin-right: 1rem;">
                                                📅 <?php echo date('Y-m-d', strtotime($lesson['completion_date'])); ?>
                                            </small>
                                        </p>
                                    </div>
                                    <?php if ($lesson['score']): ?>
                                        <div class="progress-score">
                                            <span class="badge badge-success">⭐ <?php echo $lesson['score']; ?>%</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- التمارين الأخيرة -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2 class="card-title">✍️ آخر التمارين المحلولة</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_exercises)): ?>
                        <div class="text-center" style="padding: 2rem; color: var(--text-secondary);">
                            <p>لم تحل أي تمرين بعد. ابدأ في اختبار معرفتك!</p>
                        </div>
                    <?php else: ?>
                        <div class="progress-list">
                            <?php foreach ($recent_exercises as $exercise): ?>
                                <div class="progress-item">
                                    <div class="progress-info">
                                        <h4><?php echo htmlspecialchars($exercise['lesson_title']); ?></h4>
                                        <p style="color: var(--text-secondary); font-size: 0.875rem;">
                                            <?php echo htmlspecialchars(substr($exercise['question'], 0, 100)) . '...'; ?>
                                        </p>
                                        <small style="color: var(--text-secondary);">
                                            📅 <?php echo date('Y-m-d H:i', strtotime($exercise['submitted_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="progress-score">
                                        <?php if ($exercise['score']): ?>
                                            <span class="badge badge-<?php echo $exercise['score'] >= 70 ? 'success' : 'warning'; ?>">
                                                ⭐ <?php echo $exercise['score']; ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">⏳ قيد التقييم</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        .progress-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .progress-item {
            display: flex;
            justify-content: space-between;
            align-items: start;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 0.5rem;
            transition: background 0.2s;
        }
        
        .progress-item:hover {
            background: #f3f4f6;
        }
        
        .progress-info {
            flex: 1;
        }
        
        .progress-info h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
        }
        
        .progress-info p {
            margin: 0;
        }
        
        .progress-score {
            margin-right: 1rem;
        }
    </style>
</body>
</html>
