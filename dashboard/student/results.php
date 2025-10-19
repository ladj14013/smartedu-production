<?php
/**
 * Student Results Page - صفحة النتائج والتقدم
 */

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['student', 'etudiant']);

$user_id = $_SESSION['user_id'];

// التحقق من صحة user_id
if (empty($user_id)) {
    die("خطأ: لم يتم العثور على معرف المستخدم في الجلسة");
}

// جلب معلومات الطالب
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

// الإحصائيات العامة
$overall_stats = ['completed_lessons' => 0, 'total_exercises' => 0, 'avg_score' => 0, 'max_score' => 0, 'min_score' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT e.lesson_id) as completed_lessons,
            COUNT(DISTINCT sa.exercise_id) as total_exercises,
            AVG(sa.score) as avg_score,
            MAX(sa.score) as max_score,
            MIN(sa.score) as min_score
        FROM student_answers sa
        JOIN exercises e ON sa.exercise_id = e.id
        WHERE sa.student_id = ? AND sa.score IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $overall_stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

// النتائج حسب المادة
$subjects_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id, s.name,
            COUNT(DISTINCT sa.id) as exercises_count,
            AVG(sa.score) as avg_score
        FROM subjects s
        JOIN lessons l ON s.id = l.subject_id
        JOIN exercises e ON l.id = e.lesson_id
        JOIN student_answers sa ON e.id = sa.exercise_id
        WHERE sa.student_id = ? AND sa.score IS NOT NULL
        GROUP BY s.id, s.name
        ORDER BY avg_score DESC
    ");
    $stmt->execute([$user_id]);
    $subjects_stats = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching subject stats: " . $e->getMessage());
}

// جميع النتائج مع الفلاتر
$filter_subject = $_GET['subject'] ?? '';
$filter_period = $_GET['period'] ?? '';

$all_results = [];
try {
    $where = ["sa.student_id = ?", "sa.score IS NOT NULL"];
    $params = [$user_id];

    if ($filter_subject) {
        $where[] = "s.id = ?";
        $params[] = $filter_subject;
    }

    if ($filter_period == 'week') {
        $where[] = "sa.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($filter_period == 'month') {
        $where[] = "sa.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }

    $where_sql = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT sa.*, 
               e.question as exercise_title,
               l.title as lesson_title,
               s.name as subject_name
        FROM student_answers sa
        JOIN exercises e ON sa.exercise_id = e.id
        JOIN lessons l ON e.lesson_id = l.id
        JOIN subjects s ON l.subject_id = s.id
        WHERE $where_sql
        ORDER BY sa.submitted_at DESC
    ");
    $stmt->execute($params);
    $all_results = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching results: " . $e->getMessage());
}

// جلب المواد للفلتر
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY name");
$subjects = $stmt->fetchAll();

// حساب الترتيب (Ranking)
$rankings = [];
$my_rank = 0;
if (isset($student['stage_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, CONCAT(u.nom, ' ', u.prenom) as name, AVG(sa.score) as avg_score
            FROM users u
            JOIN student_answers sa ON u.id = sa.student_id
            WHERE u.role IN ('student', 'etudiant')
            AND u.stage_id = ?
            AND sa.score IS NOT NULL
            GROUP BY u.id, u.nom, u.prenom
            ORDER BY avg_score DESC
        ");
        $stmt->execute([$student['stage_id']]);
        $rankings = $stmt->fetchAll();

        foreach ($rankings as $index => $rank) {
            if ($rank['id'] == $user_id) {
                $my_rank = $index + 1;
                break;
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching rankings: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتائجي - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        @media (max-width: 968px) {
            .main-content {
                margin-right: 0 !important;
                padding: 20px;
            }
        }
    </style>
    <link rel="stylesheet" href="results-mobile.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-right: 4px solid;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.12); }
        .stat-card.green { border-right-color: #22c55e; }
        .stat-card.blue { border-right-color: #4285F4; }
        .stat-card.purple { border-right-color: #8b5cf6; }
        .stat-card.gold { border-right-color: #f59e0b; }
        
        .stat-icon { font-size: 2.5rem; margin-bottom: 10px; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #1f2937; margin-bottom: 5px; }
        .stat-label { color: #6b7280; font-size: 0.95rem; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .card h3 {
            font-size: 1.3rem;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filters-section {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .form-control {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #22c55e;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .subject-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .subject-item:last-child { border-bottom: none; }
        
        .subject-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .subject-icon-large {
            font-size: 2.5rem;
        }
        
        .progress-container {
            flex: 1;
            max-width: 200px;
        }
        
        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.5s ease;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .results-table th {
            background: #f9fafb;
            padding: 15px;
            text-align: right;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .results-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #6b7280;
        }
        
        .results-table tr:hover {
            background: #f9fafb;
        }
        
        .score-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
        }
        
        .score-excellent { background: #d1fae5; color: #065f46; }
        .score-good { background: #dbeafe; color: #1e40af; }
        .score-average { background: #fef3c7; color: #92400e; }
        .score-poor { background: #fee2e2; color: #991b1b; }
        
        .ranking-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .rank-medal {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        .rank-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }
        
        .empty-icon { font-size: 4rem; margin-bottom: 15px; }
        
        @media (max-width: 968px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .results-table { font-size: 0.9rem; }
            .results-table th, .results-table td { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>📊 نتائجي وتقدمي</h1>
                        <p>تابع أداءك ونتائجك الدراسية</p>
                    </div>
                </div>
            </header>
            
            <!-- Overall Statistics -->
            <div class="stats-grid">
                <div class="stat-card green">
                    <div class="stat-icon">📚</div>
                    <div class="stat-value"><?php echo $overall_stats['completed_lessons'] ?? 0; ?></div>
                    <div class="stat-label">دروس مكتملة</div>
                </div>
                
                <div class="stat-card blue">
                    <div class="stat-icon">✍️</div>
                    <div class="stat-value"><?php echo $overall_stats['total_exercises'] ?? 0; ?></div>
                    <div class="stat-label">تمارين منجزة</div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-icon">📈</div>
                    <div class="stat-value"><?php echo round($overall_stats['avg_score'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">متوسط الأداء</div>
                </div>
                
                <div class="stat-card gold">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-value"><?php echo round($overall_stats['max_score'] ?? 0); ?>%</div>
                    <div class="stat-label">أعلى درجة</div>
                </div>
            </div>
            
            <!-- Ranking -->
            <?php if ($my_rank > 0): ?>
                <div class="ranking-card">
                    <div class="rank-medal">
                        <?php
                        if ($my_rank == 1) echo '🥇';
                        elseif ($my_rank == 2) echo '🥈';
                        elseif ($my_rank == 3) echo '🥉';
                        else echo '🏅';
                        ?>
                    </div>
                    <div class="rank-number">المرتبة #<?php echo $my_rank; ?></div>
                    <p style="color: #92400e; font-weight: 600;">
                        من بين <?php echo count($rankings); ?> طالب في مرحلتك
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Performance by Subject -->
            <?php if (!empty($subjects_stats)): ?>
                <div class="card">
                    <h3>📈 الأداء حسب المواد</h3>
                    
                    <?php foreach ($subjects_stats as $subject): ?>
                        <?php
                        $score = round($subject['avg_score'], 1);
                        $color = $score >= 80 ? '#22c55e' : 
                                ($score >= 60 ? '#4285F4' : 
                                ($score >= 40 ? '#FFA726' : '#ef4444'));
                        ?>
                        <div class="subject-item">
                            <div class="subject-info">
                                <span class="subject-icon-large">📚</span>
                                <div>
                                    <div style="font-weight: 600; color: #1f2937; font-size: 1.1rem;">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </div>
                                    <div style="font-size: 0.9rem; color: #6b7280;">
                                        <?php echo $subject['exercises_count']; ?> تمرين مكتمل
                                    </div>
                                </div>
                            </div>
                            
                            <div class="progress-container">
                                <div style="text-align: left; font-size: 1.3rem; font-weight: 700; color: <?php echo $color; ?>; margin-bottom: 8px;">
                                    <?php echo $score; ?>%
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $score; ?>%; background: <?php echo $color; ?>"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="filters-section">
                <h3 style="margin-bottom: 10px;">🔍 تصفية النتائج</h3>
                <form method="GET" class="filters-grid">
                    <div class="form-group">
                        <label>المادة</label>
                        <select name="subject" class="form-control">
                            <option value="">جميع المواد</option>
                            <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>" 
                                        <?php echo $filter_subject == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo '📚 ' . htmlspecialchars($subject['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>الفترة الزمنية</label>
                        <select name="period" class="form-control">
                            <option value="">كل الأوقات</option>
                            <option value="week" <?php echo $filter_period == 'week' ? 'selected' : ''; ?>>
                                آخر أسبوع
                            </option>
                            <option value="month" <?php echo $filter_period == 'month' ? 'selected' : ''; ?>>
                                آخر شهر
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="justify-content: flex-end;">
                        <label>&nbsp;</label>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">تطبيق</button>
                            <a href="results.php" class="btn btn-secondary">إعادة تعيين</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- All Results -->
            <div class="card">
                <h3>📋 سجل النتائج (<?php echo count($all_results); ?>)</h3>
                
                <?php if (empty($all_results)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📝</div>
                        <h3>لا توجد نتائج بعد</h3>
                        <p>ابدأ بحل التمارين لعرض نتائجك هنا</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>التاريخ</th>
                                    <th>المادة</th>
                                    <th>الدرس</th>
                                    <th>التمرين</th>
                                    <th>النتيجة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_results as $result): ?>
                                    <?php
                                    $score = $result['score'];
                                    $score_class = $score >= 80 ? 'score-excellent' : 
                                                  ($score >= 60 ? 'score-good' : 
                                                  ($score >= 40 ? 'score-average' : 'score-poor'));
                                    ?>
                                    <tr>
                                        <td><?php echo date('Y/m/d H:i', strtotime($result['submitted_at'])); ?></td>
                                        <td>
                                            <span style="font-size: 1.2rem;">📚</span>
                                            <?php echo htmlspecialchars($result['subject_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($result['lesson_title']); ?></td>
                                        <td><?php echo htmlspecialchars($result['exercise_title']); ?></td>
                                        <td>
                                            <span class="score-badge <?php echo $score_class; ?>">
                                                <?php echo $score; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</body>
</html>
