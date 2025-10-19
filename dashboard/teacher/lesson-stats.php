<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['enseignant', 'teacher']);

global $pdo;
$teacher_id = $_SESSION['user_id'];

// Get teacher info
$teacher_stmt = $pdo->prepare("
    SELECT u.*, s.name as subject_name 
    FROM users u
    LEFT JOIN subjects s ON u.subject_id = s.id
    WHERE u.id = ?
");
$teacher_stmt->execute([$teacher_id]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

// Get detailed lesson statistics
$lessons_query = "SELECT 
    l.id,
    l.title,
    l.status,
    l.type,
    l.created_at,
    lv.name as level_name,
    COUNT(DISTINCT e.id) as exercises_count,
    COUNT(DISTINCT sp.student_id) as students_started,
    COUNT(DISTINCT CASE WHEN sp.completion_date IS NOT NULL THEN sp.student_id END) as students_completed,
    (SELECT AVG(sa2.score) 
     FROM student_answers sa2 
     JOIN exercises e2 ON sa2.exercise_id = e2.id 
     WHERE e2.lesson_id = l.id AND sa2.score IS NOT NULL) as avg_score,
    COUNT(DISTINCT sa.id) as total_submissions
FROM lessons l
LEFT JOIN levels lv ON l.level_id = lv.id
LEFT JOIN exercises e ON e.lesson_id = l.id
LEFT JOIN student_progress sp ON sp.lesson_id = l.id
LEFT JOIN student_answers sa ON sa.exercise_id = e.id
WHERE l.author_id = :teacher_id
GROUP BY l.id
ORDER BY l.created_at DESC";

$stmt = $pdo->prepare($lessons_query);
$stmt->execute([':teacher_id' => $teacher_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall statistics
$total_lessons = count($lessons);
$approved_lessons = count(array_filter($lessons, fn($l) => $l['status'] === 'approved'));
$pending_lessons = count(array_filter($lessons, fn($l) => $l['status'] === 'pending'));
$rejected_lessons = count(array_filter($lessons, fn($l) => $l['status'] === 'rejected'));

$total_students = 0;
$total_completions = 0;
foreach ($lessons as $lesson) {
    $total_students += $lesson['students_started'];
    $total_completions += $lesson['students_completed'];
}
$completion_rate = $total_students > 0 ? ($total_completions / $total_students) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إحصائيات الدروس - لوحة تحكم الأستاذ</title>
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
            padding: 25px;
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2d3748;
        }
        .stat-card.approved .stat-value { color: #48bb78; }
        .stat-card.pending .stat-value { color: #ed8936; }
        .stat-card.rejected .stat-value { color: #f56565; }
        .stat-card.completion .stat-value { color: #4299e1; }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .chart-card h3 {
            color: #2d3748;
            margin-bottom: 20px;
        }
        
        .lessons-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #f7fafc;
        }
        th {
            padding: 15px;
            text-align: right;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        tbody tr:hover {
            background: #f7fafc;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-approved { background: #c6f6d5; color: #22543d; }
        .status-pending { background: #feebc8; color: #7c2d12; }
        .status-rejected { background: #fed7d7; color: #742a2a; }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
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
        .back-btn:hover {
            background: #f7fafc;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">← العودة للوحة التحكم</a>
        
        <div class="header">
            <h1>📊 إحصائيات الدروس التفصيلية</h1>
            <p>مرحباً أ. <?php echo htmlspecialchars($teacher['nom'] . ' ' . $teacher['prenom']); ?> - <?php echo htmlspecialchars($teacher['subject_name'] ?? 'غير محدد'); ?></p>
        </div>

        <!-- Overview Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>إجمالي الدروس</h3>
                <div class="stat-value"><?php echo $total_lessons; ?></div>
            </div>
            <div class="stat-card approved">
                <h3>الدروس المعتمدة</h3>
                <div class="stat-value"><?php echo $approved_lessons; ?></div>
            </div>
            <div class="stat-card pending">
                <h3>قيد المراجعة</h3>
                <div class="stat-value"><?php echo $pending_lessons; ?></div>
            </div>
            <div class="stat-card rejected">
                <h3>المرفوضة</h3>
                <div class="stat-value"><?php echo $rejected_lessons; ?></div>
            </div>
            <div class="stat-card completion">
                <h3>معدل الإكمال</h3>
                <div class="stat-value"><?php echo number_format($completion_rate, 1); ?>%</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3>توزيع الدروس حسب الحالة</h3>
                <canvas id="statusChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>نسبة إكمال الطلاب</h3>
                <canvas id="completionChart"></canvas>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="lessons-table">
            <div class="table-header">
                <h2>تفاصيل الدروس</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>عنوان الدرس</th>
                        <th>المستوى</th>
                        <th>الحالة</th>
                        <th>التمارين</th>
                        <th>الطلاب بدأوا</th>
                        <th>أكملوا</th>
                        <th>معدل الإكمال</th>
                        <th>متوسط الدرجات</th>
                        <th>الإرساليات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessons as $lesson): 
                        $completion = $lesson['students_started'] > 0 
                            ? ($lesson['students_completed'] / $lesson['students_started']) * 100 
                            : 0;
                        $status_class = 'status-' . $lesson['status'];
                        $status_text = [
                            'approved' => 'معتمد',
                            'pending' => 'قيد المراجعة',
                            'rejected' => 'مرفوض'
                        ][$lesson['status']] ?? 'غير معروف';
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                <br>
                                <small style="color: #718096;">
                                    <?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?>
                                </small>
                            </td>
                            <td><?php echo htmlspecialchars($lesson['level_name'] ?? 'غير محدد'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td><?php echo $lesson['exercises_count']; ?></td>
                            <td><?php echo $lesson['students_started']; ?></td>
                            <td><?php echo $lesson['students_completed']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $completion; ?>%"></div>
                                </div>
                                <small><?php echo number_format($completion, 1); ?>%</small>
                            </td>
                            <td>
                                <?php 
                                    $score = $lesson['avg_score'];
                                    echo $score !== null ? number_format($score, 1) . '%' : '-';
                                ?>
                            </td>
                            <td><?php echo $lesson['total_submissions']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['معتمد', 'قيد المراجعة', 'مرفوض'],
                datasets: [{
                    data: [<?php echo $approved_lessons; ?>, <?php echo $pending_lessons; ?>, <?php echo $rejected_lessons; ?>],
                    backgroundColor: ['#48bb78', '#ed8936', '#f56565']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Completion Chart
        const completionCtx = document.getElementById('completionChart').getContext('2d');
        const lessonTitles = <?php echo json_encode(array_column($lessons, 'title')); ?>;
        const completionRates = <?php echo json_encode(array_map(function($l) {
            return $l['students_started'] > 0 ? ($l['students_completed'] / $l['students_started']) * 100 : 0;
        }, $lessons)); ?>;

        new Chart(completionCtx, {
            type: 'bar',
            data: {
                labels: lessonTitles.slice(0, 10),
                datasets: [{
                    label: 'معدل الإكمال %',
                    data: completionRates.slice(0, 10),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    </script>
</body>
</html>
