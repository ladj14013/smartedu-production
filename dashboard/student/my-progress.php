<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['etudiant', 'student']);

global $pdo;
$student_id = $_SESSION['user_id'];

// التحقق من صحة student_id
if (empty($student_id)) {
    die("خطأ: لم يتم العثور على معرف الطالب في الجلسة");
}

// Get student info
$student_stmt = $pdo->prepare("
    SELECT u.*, l.name as level_name, s.name as stage_name
    FROM users u
    LEFT JOIN levels l ON u.level_id = l.id
    LEFT JOIN stages s ON u.stage_id = s.id
    WHERE u.id = ?
");
$student_stmt->execute([$student_id]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

// Overall statistics
$stats_query = "SELECT 
    COUNT(DISTINCT sp.lesson_id) as lessons_started,
    COUNT(DISTINCT CASE WHEN sp.completion_date IS NOT NULL THEN sp.lesson_id END) as lessons_completed,
    COUNT(DISTINCT sa.exercise_id) as exercises_done,
    AVG(sa.score) as avg_score,
    SUM(CASE WHEN sa.score >= 50 THEN 1 ELSE 0 END) as passed_exercises,
    COUNT(sa.id) as total_answers
FROM student_progress sp
LEFT JOIN student_answers sa ON sp.student_id = sa.student_id
WHERE sp.student_id = ?";

$stmt = $pdo->prepare($stats_query);
$stmt->execute([$student_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$completion_rate = $stats['lessons_started'] > 0 
    ? ($stats['lessons_completed'] / $stats['lessons_started']) * 100 
    : 0;

$pass_rate = $stats['total_answers'] > 0 
    ? ($stats['passed_exercises'] / $stats['total_answers']) * 100 
    : 0;

// Recent completed lessons
$recent_lessons_query = "SELECT 
    l.id,
    l.title,
    s.name as subject_name,
    lv.name as level_name,
    sp.completion_date,
    (SELECT AVG(sa.score) 
     FROM student_answers sa 
     JOIN exercises e ON sa.exercise_id = e.id 
     WHERE e.lesson_id = l.id AND sa.student_id = ?) as score,
    (SELECT COUNT(*) FROM exercises WHERE lesson_id = l.id) as exercises_count
FROM student_progress sp
JOIN lessons l ON sp.lesson_id = l.id
LEFT JOIN subjects s ON l.subject_id = s.id
LEFT JOIN levels lv ON l.level_id = lv.id
WHERE sp.student_id = ? AND sp.completion_date IS NOT NULL
ORDER BY sp.completion_date DESC
LIMIT 10";

$stmt = $pdo->prepare($recent_lessons_query);
$stmt->execute([$student_id, $student_id]);
$recent_lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// In progress lessons
$in_progress_query = "SELECT 
    l.id,
    l.title,
    s.name as subject_name,
    (SELECT COUNT(*) FROM exercises WHERE lesson_id = l.id) as exercises_count,
    (SELECT COUNT(*) FROM student_answers sa 
     JOIN exercises e ON sa.exercise_id = e.id 
     WHERE e.lesson_id = l.id AND sa.student_id = ?) as completed_exercises
FROM student_progress sp
JOIN lessons l ON sp.lesson_id = l.id
LEFT JOIN subjects s ON l.subject_id = s.id
WHERE sp.student_id = ? AND sp.completion_date IS NULL
ORDER BY sp.updated_at DESC
LIMIT 5";

$stmt = $pdo->prepare($in_progress_query);
$stmt->execute([$student_id, $student_id]);
$in_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Subject breakdown
$subjects_query = "SELECT 
    s.name as subject_name,
    COUNT(DISTINCT sp.lesson_id) as lessons_count,
    COUNT(DISTINCT CASE WHEN sp.completion_date IS NOT NULL THEN sp.lesson_id END) as completed,
    (SELECT AVG(sa.score) 
     FROM student_answers sa 
     JOIN exercises e ON sa.exercise_id = e.id 
     JOIN lessons l2 ON e.lesson_id = l2.id 
     WHERE l2.subject_id = s.id AND sa.student_id = ?) as avg_score
FROM student_progress sp
JOIN lessons l ON sp.lesson_id = l.id
JOIN subjects s ON l.subject_id = s.id
WHERE sp.student_id = ?
GROUP BY s.id, s.name
ORDER BY lessons_count DESC";

$stmt = $pdo->prepare($subjects_query);
$stmt->execute([$student_id, $student_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly activity
$monthly_query = "SELECT 
    DATE_FORMAT(sp.completion_date, '%Y-%m') as month,
    COUNT(*) as completions
FROM student_progress sp
WHERE sp.student_id = ? AND sp.completion_date IS NOT NULL
GROUP BY DATE_FORMAT(sp.completion_date, '%Y-%m')
ORDER BY month DESC
LIMIT 6";

$stmt = $pdo->prepare($monthly_query);
$stmt->execute([$student_id]);
$monthly_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقدم والإنجازات - لوحة تحكم الطالب</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2d3748;
            margin: 10px 0;
        }
        .stat-label {
            color: #718096;
            font-size: 0.9rem;
        }
        .progress-ring {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }
        .progress-ring svg {
            transform: rotate(-90deg);
        }
        .progress-ring-circle {
            transition: stroke-dashoffset 0.5s;
        }
        .progress-percentage {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .content-grid { grid-template-columns: 1fr; }
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h2 {
            color: #2d3748;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        .lesson-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 10px;
            border-right: 4px solid #667eea;
        }
        .lesson-item h4 {
            color: #2d3748;
            margin-bottom: 5px;
        }
        .lesson-item p {
            color: #718096;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }
        .lesson-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .score-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .score-excellent { background: #c6f6d5; color: #22543d; }
        .score-good { background: #bee3f8; color: #2c5282; }
        .score-average { background: #feebc8; color: #7c2d12; }
        .score-poor { background: #fed7d7; color: #742a2a; }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        .subject-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        .subject-item h4 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        .subject-stats {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            color: #718096;
            margin-top: 10px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .back-btn:hover { background: #f7fafc; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">← العودة للوحة التحكم</a>
        
        <div class="header">
            <h1>📈 التقدم والإنجازات</h1>
            <p>مرحباً <?php echo htmlspecialchars($student['nom'] . ' ' . $student['prenom']); ?> 
               - <?php echo htmlspecialchars($student['level_name'] ?? 'المستوى غير محدد'); ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-value"><?php echo $stats['lessons_started']; ?></div>
                <div class="stat-label">دروس بدأتها</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $stats['lessons_completed']; ?></div>
                <div class="stat-label">دروس أكملتها</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-value"><?php echo $stats['exercises_done']; ?></div>
                <div class="stat-label">تمارين حلّيتها</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value"><?php echo number_format($stats['avg_score'] ?? 0, 1); ?>%</div>
                <div class="stat-label">متوسط درجاتك</div>
            </div>
        </div>

        <!-- Completion Rate -->
        <div class="card" style="text-align: center; margin-bottom: 20px;">
            <h2>نسبة الإكمال</h2>
            <div class="progress-ring">
                <svg width="150" height="150">
                    <circle cx="75" cy="75" r="65" stroke="#e2e8f0" stroke-width="10" fill="none"/>
                    <circle class="progress-ring-circle" cx="75" cy="75" r="65" 
                            stroke="#667eea" stroke-width="10" fill="none"
                            stroke-dasharray="<?php echo 2 * 3.14159 * 65; ?>"
                            stroke-dashoffset="<?php echo 2 * 3.14159 * 65 * (1 - $completion_rate / 100); ?>"/>
                </svg>
                <div class="progress-percentage"><?php echo number_format($completion_rate, 1); ?>%</div>
            </div>
            <p style="color: #718096;">معدل النجاح: <?php echo number_format($pass_rate, 1); ?>%</p>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Lessons -->
            <div class="card">
                <h2>آخر الدروس المكتملة</h2>
                <?php if (empty($recent_lessons)): ?>
                    <div class="empty-state">
                        <div style="font-size: 3rem;">📭</div>
                        <p>لم تكمل أي درس بعد</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_lessons as $lesson): 
                        $score = $lesson['score'];
                        $score_class = '';
                        if ($score !== null) {
                            if ($score >= 85) $score_class = 'score-excellent';
                            elseif ($score >= 70) $score_class = 'score-good';
                            elseif ($score >= 50) $score_class = 'score-average';
                            else $score_class = 'score-poor';
                        }
                    ?>
                        <div class="lesson-item">
                            <h4><?php echo htmlspecialchars($lesson['title']); ?></h4>
                            <p><?php echo htmlspecialchars($lesson['subject_name']); ?> - <?php echo htmlspecialchars($lesson['level_name']); ?></p>
                            <div class="lesson-meta">
                                <span style="color: #718096; font-size: 0.85rem;">
                                    <?php echo date('Y/m/d', strtotime($lesson['completion_date'])); ?>
                                </span>
                                <?php if ($score !== null): ?>
                                    <span class="score-badge <?php echo $score_class; ?>">
                                        <?php echo number_format($score, 1); ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- In Progress -->
            <div class="card">
                <h2>الدروس الجارية</h2>
                <?php if (empty($in_progress)): ?>
                    <div class="empty-state">
                        <div style="font-size: 3rem;">🎯</div>
                        <p>لا توجد دروس جارية</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($in_progress as $lesson): 
                        $progress = $lesson['exercises_count'] > 0 
                            ? ($lesson['completed_exercises'] / $lesson['exercises_count']) * 100 
                            : 0;
                    ?>
                        <div class="lesson-item">
                            <h4><?php echo htmlspecialchars($lesson['title']); ?></h4>
                            <p><?php echo htmlspecialchars($lesson['subject_name']); ?></p>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <span style="color: #718096; font-size: 0.85rem;">
                                <?php echo $lesson['completed_exercises']; ?> / <?php echo $lesson['exercises_count']; ?> تمرين
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subjects Breakdown -->
        <div class="card">
            <h2>التقدم حسب المواد</h2>
            <?php if (empty($subjects)): ?>
                <div class="empty-state">
                    <div style="font-size: 3rem;">📖</div>
                    <p>لم تبدأ أي مادة بعد</p>
                </div>
            <?php else: ?>
                <?php foreach ($subjects as $subject): 
                    $subject_completion = $subject['lessons_count'] > 0 
                        ? ($subject['completed'] / $subject['lessons_count']) * 100 
                        : 0;
                ?>
                    <div class="subject-item">
                        <h4><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $subject_completion; ?>%"></div>
                        </div>
                        <div class="subject-stats">
                            <span>📚 <?php echo $subject['lessons_count']; ?> دروس</span>
                            <span>✅ <?php echo $subject['completed']; ?> مكتمل</span>
                            <span>⭐ متوسط: <?php echo number_format($subject['avg_score'] ?? 0, 1); ?>%</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Monthly Activity Chart -->
        <?php if (!empty($monthly_activity)): ?>
            <div class="card" style="margin-top: 20px;">
                <h2>النشاط الشهري</h2>
                <canvas id="activityChart"></canvas>
            </div>
            
            <script>
                const activityCtx = document.getElementById('activityChart').getContext('2d');
                new Chart(activityCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_reverse(array_column($monthly_activity, 'month'))); ?>,
                        datasets: [{
                            label: 'الدروس المكتملة',
                            data: <?php echo json_encode(array_reverse(array_column($monthly_activity, 'completions'))); ?>,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
