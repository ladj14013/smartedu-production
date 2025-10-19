<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/auth.php';
require_once '../../config/database.php';
requireLogin();
requireRole(['superviseur_matiere', 'supervisor_subject', 'subject_supervisor']);

global $pdo;

$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, s.name as subject_name FROM users u 
          LEFT JOIN subjects s ON u.subject_id = s.id 
          WHERE u.id = :user_id";
$stmt = $pdo->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supervisor['subject_id']) {
    die('لم يتم تعيين مادة لهذا المشرف');
}

$subject_id = $supervisor['subject_id'];

// Overall statistics
$stats_query = "SELECT 
    COUNT(*) as total_lessons,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    COUNT(DISTINCT author_id) as total_teachers,
    (SELECT COUNT(*) FROM exercises e 
     JOIN lessons l ON e.lesson_id = l.id 
     WHERE l.subject_id = :subject_id) as total_exercises
    FROM lessons WHERE subject_id = :subject_id";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([':subject_id' => $subject_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Monthly lessons data (last 12 months)
$monthly_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    DATE_FORMAT(created_at, '%M %Y') as month_name,
    COUNT(*) as count,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM lessons
    WHERE subject_id = :subject_id 
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month, month_name
    ORDER BY month DESC";
$stmt = $pdo->prepare($monthly_query);
$stmt->execute([':subject_id' => $subject_id]);
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top teachers by lessons
$top_teachers_query = "SELECT 
    CONCAT(u.nom, ' ', u.prenom) as full_name,
    COUNT(l.id) as lesson_count,
    SUM(CASE WHEN l.status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM users u
    JOIN lessons l ON u.id = l.author_id
    WHERE l.subject_id = :subject_id
    GROUP BY u.id, u.nom, u.prenom
    ORDER BY lesson_count DESC
    LIMIT 10";
$stmt = $pdo->prepare($top_teachers_query);
$stmt->execute([':subject_id' => $subject_id]);
$top_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lesson type distribution
$type_query = "SELECT 
    type as lesson_type,
    COUNT(*) as count
    FROM lessons
    WHERE subject_id = :subject_id
    GROUP BY type";
$stmt = $pdo->prepare($type_query);
$stmt->execute([':subject_id' => $subject_id]);
$type_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate percentages
$approval_rate = $stats['total_lessons'] > 0 ? round(($stats['approved'] / $stats['total_lessons']) * 100, 1) : 0;
$rejection_rate = $stats['total_lessons'] > 0 ? round(($stats['rejected'] / $stats['total_lessons']) * 100, 1) : 0;
$pending_rate = $stats['total_lessons'] > 0 ? round(($stats['pending'] / $stats['total_lessons']) * 100, 1) : 0;

// Prepare data for charts
$months = array_reverse(array_column($monthly_data, 'month'));
$monthly_counts = array_reverse(array_column($monthly_data, 'count'));
$monthly_approved = array_reverse(array_column($monthly_data, 'approved'));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإحصائيات - SmartEdu Hub</title>
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
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .header p { font-size: 1.1em; opacity: 0.9; }
        .controls {
            padding: 20px 30px;
            background: white;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-back {
            padding: 10px 25px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 2.5em; margin-bottom: 10px; }
        .stat-number { font-size: 2.5em; font-weight: bold; margin: 10px 0; }
        .stat-label { color: #666; font-size: 1em; }
        .stat-percentage { font-size: 0.9em; color: #999; margin-top: 5px; }
        .stat-card.total .stat-number { color: #667eea; }
        .stat-card.approved .stat-number { color: #4CAF50; }
        .stat-card.rejected .stat-number { color: #f44336; }
        .stat-card.pending .stat-number { color: #FF9800; }
        .stat-card.teachers .stat-number { color: #2196F3; }
        .stat-card.exercises .stat-number { color: #9C27B0; }
        .charts-section { padding: 30px; }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .chart-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .full-width { grid-column: 1 / -1; }
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover { background: #f8f9fa; }
        .rank-badge {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
            color: white;
        }
        .rank-1 { background: #FFD700; color: #333; }
        .rank-2 { background: #C0C0C0; color: #333; }
        .rank-3 { background: #CD7F32; color: white; }
        .rank-other { background: #e0e0e0; color: #666; }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 الإحصائيات</h1>
            <p>مادة: <?php echo htmlspecialchars($supervisor['subject_name']); ?></p>
        </div>
        <div class="controls">
            <h2 style="color: #333;">📈 إحصائيات شاملة</h2>
            <a href="index.php" class="btn-back">🏠 العودة للرئيسية</a>
        </div>
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo $stats['total_lessons']; ?></div>
                <div class="stat-label">إجمالي الدروس</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">دروس معتمدة</div>
                <div class="stat-percentage"><?php echo $approval_rate; ?>% من الإجمالي</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-icon">❌</div>
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">دروس مرفوضة</div>
                <div class="stat-percentage"><?php echo $rejection_rate; ?>% من الإجمالي</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">دروس معلقة</div>
                <div class="stat-percentage"><?php echo $pending_rate; ?>% من الإجمالي</div>
            </div>
            <div class="stat-card teachers">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-number"><?php echo $stats['total_teachers']; ?></div>
                <div class="stat-label">عدد الأساتذة</div>
            </div>
            <div class="stat-card exercises">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?php echo $stats['total_exercises']; ?></div>
                <div class="stat-label">إجمالي التمارين</div>
            </div>
        </div>
        <div class="charts-section">
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-title">📊 توزيع الدروس حسب الحالة</div>
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="chart-card">
                    <div class="chart-title">📖 توزيع الدروس حسب النوع</div>
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
            <div class="chart-card full-width">
                <div class="chart-title">📈 الدروس المضافة شهرياً (آخر 12 شهر)</div>
                <canvas id="monthlyChart"></canvas>
            </div>
            <div class="chart-card full-width">
                <div class="chart-title">🏆 أفضل 10 أساتذة (حسب عدد الدروس)</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>الترتيب</th>
                                <th>اسم الأستاذ</th>
                                <th>عدد الدروس</th>
                                <th>الدروس المعتمدة</th>
                                <th>معدل الموافقة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_teachers as $index => $teacher): 
                                $rate = $teacher['lesson_count'] > 0 ? round(($teacher['approved'] / $teacher['lesson_count']) * 100, 1) : 0;
                                $rank_class = match($index) {
                                    0 => 'rank-1',
                                    1 => 'rank-2',
                                    2 => 'rank-3',
                                    default => 'rank-other'
                                };
                            ?>
                                <tr>
                                    <td><span class="rank-badge <?php echo $rank_class; ?>"><?php echo $index + 1; ?></span></td>
                                    <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                    <td><?php echo $teacher['lesson_count']; ?></td>
                                    <td><?php echo $teacher['approved']; ?></td>
                                    <td><?php echo $rate; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Status Distribution Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['معتمدة', 'مرفوضة', 'معلقة'],
                datasets: [{
                    data: [<?php echo $stats['approved']; ?>, <?php echo $stats['rejected']; ?>, <?php echo $stats['pending']; ?>],
                    backgroundColor: ['#4CAF50', '#f44336', '#FF9800'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Type Distribution Pie Chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'pie',
            data: {
                labels: [<?php echo implode(',', array_map(function($t) { return "'" . ($t['lesson_type'] === 'interactive' ? 'تفاعلي' : 'مقروء') . "'"; }, $type_data)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($type_data, 'count')); ?>],
                    backgroundColor: ['#2196F3', '#9C27B0'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Monthly Line Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'إجمالي الدروس',
                    data: <?php echo json_encode($monthly_counts); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'الدروس المعتمدة',
                    data: <?php echo json_encode($monthly_approved); ?>,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>
