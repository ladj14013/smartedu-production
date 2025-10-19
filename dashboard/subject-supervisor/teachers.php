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

// Get teachers statistics
$query = "SELECT 
    u.id, CONCAT(u.nom, ' ', u.prenom) as full_name, u.email, u.created_at as joined_date,
    COUNT(l.id) as total_lessons,
    SUM(CASE WHEN l.status = 'approved' THEN 1 ELSE 0 END) as approved_lessons,
    SUM(CASE WHEN l.status = 'rejected' THEN 1 ELSE 0 END) as rejected_lessons,
    SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) as pending_lessons,
    MAX(l.created_at) as last_activity,
    (SELECT COUNT(*) FROM exercises e 
     JOIN lessons ll ON e.lesson_id = ll.id 
     WHERE ll.author_id = u.id AND ll.subject_id = :subject_id) as total_exercises
    FROM users u
    JOIN lessons l ON u.id = l.author_id
    WHERE l.subject_id = :subject_id
    GROUP BY u.id, u.nom, u.prenom, u.email, u.created_at
    ORDER BY total_lessons DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([':subject_id' => $subject_id]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($teachers as &$teacher) {
    $total = $teacher['total_lessons'];
    $approved = $teacher['approved_lessons'];
    $teacher['approval_rate'] = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
    
    if ($teacher['approval_rate'] >= 80) {
        $teacher['performance'] = 'excellent';
    } elseif ($teacher['approval_rate'] >= 60) {
        $teacher['performance'] = 'good';
    } elseif ($teacher['approval_rate'] >= 40) {
        $teacher['performance'] = 'average';
    } else {
        $teacher['performance'] = 'poor';
    }
}

$sort = $_GET['sort'] ?? 'lessons';
usort($teachers, function($a, $b) use ($sort) {
    return match($sort) {
        'approval' => $b['approval_rate'] <=> $a['approval_rate'],
        'activity' => strtotime($b['last_activity'] ?? '1970-01-01') <=> strtotime($a['last_activity'] ?? '1970-01-01'),
        'name' => strcmp($a['full_name'], $b['full_name']),
        default => $b['total_lessons'] <=> $a['total_lessons']
    };
});

$total_teachers = count($teachers);
$total_all_lessons = array_sum(array_column($teachers, 'total_lessons'));
$total_all_exercises = array_sum(array_column($teachers, 'total_exercises'));
$avg_approval_rate = $total_teachers > 0 ? round(array_sum(array_column($teachers, 'approval_rate')) / $total_teachers, 1) : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الأساتذة - SmartEdu Hub</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .header p { font-size: 1.1em; opacity: 0.9; }
        .stats-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; padding: 30px; background: #f8f9fa; border-bottom: 3px solid #667eea; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-card .icon { font-size: 2em; margin-bottom: 10px; }
        .stat-card .number { font-size: 2em; font-weight: bold; color: #667eea; margin: 5px 0; }
        .stat-card .label { color: #666; font-size: 0.9em; }
        .controls { padding: 20px 30px; background: white; border-bottom: 2px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .sort-group { display: flex; align-items: center; gap: 10px; }
        .sort-group label { font-weight: 600; color: #333; }
        .sort-group select { padding: 10px 15px; border: 2px solid #ddd; border-radius: 8px; font-size: 1em; cursor: pointer; }
        .btn-back { padding: 10px 25px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: background 0.3s; }
        .btn-back:hover { background: #5a6268; }
        .teachers-section { padding: 30px; }
        .teachers-grid { display: grid; gap: 25px; }
        .teacher-card { background: white; border: 2px solid #e0e0e0; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s; }
        .teacher-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .teacher-card.excellent { border-color: #4CAF50; background: linear-gradient(to left, white 98%, #4CAF50 2%); }
        .teacher-card.good { border-color: #2196F3; background: linear-gradient(to left, white 98%, #2196F3 2%); }
        .teacher-card.average { border-color: #FF9800; background: linear-gradient(to left, white 98%, #FF9800 2%); }
        .teacher-card.poor { border-color: #f44336; background: linear-gradient(to left, white 98%, #f44336 2%); }
        .teacher-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .teacher-info { flex: 1; }
        .teacher-name { font-size: 1.5em; font-weight: bold; color: #333; margin-bottom: 5px; }
        .teacher-contact { font-size: 0.9em; color: #666; margin: 5px 0; }
        .performance-badge { padding: 8px 20px; border-radius: 20px; font-size: 0.9em; font-weight: bold; white-space: nowrap; }
        .performance-badge.excellent { background: #d4edda; color: #155724; }
        .performance-badge.good { background: #d1ecf1; color: #0c5460; }
        .performance-badge.average { background: #fff3cd; color: #856404; }
        .performance-badge.poor { background: #f8d7da; color: #721c24; }
        .teacher-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-box { background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center; }
        .stat-box .value { font-size: 1.8em; font-weight: bold; margin: 5px 0; }
        .stat-box .label { font-size: 0.85em; color: #666; }
        .stat-box.total .value { color: #667eea; }
        .stat-box.approved .value { color: #4CAF50; }
        .stat-box.rejected .value { color: #f44336; }
        .stat-box.pending .value { color: #FF9800; }
        .stat-box.exercises .value { color: #2196F3; }
        .approval-rate { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .approval-rate-label { font-size: 0.9em; color: #666; margin-bottom: 8px; }
        .progress-bar { width: 100%; height: 25px; background: #e9ecef; border-radius: 15px; overflow: hidden; position: relative; }
        .progress-fill { height: 100%; transition: width 0.5s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.85em; }
        .progress-fill.excellent { background: linear-gradient(90deg, #4CAF50, #45a049); }
        .progress-fill.good { background: linear-gradient(90deg, #2196F3, #1976D2); }
        .progress-fill.average { background: linear-gradient(90deg, #FF9800, #F57C00); }
        .progress-fill.poor { background: linear-gradient(90deg, #f44336, #d32f2f); }
        .teacher-meta { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 15px; padding-top: 15px; border-top: 2px solid #f0f0f0; font-size: 0.9em; color: #666; }
        .meta-item { display: flex; align-items: center; gap: 5px; }
        .teacher-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 15px; }
        .btn-action { padding: 10px 15px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; text-align: center; transition: all 0.3s; font-size: 0.9em; }
        .btn-view { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-view:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .btn-message { background: #2196F3; color: white; }
        .btn-message:hover { background: #1976D2; }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-icon { font-size: 5em; margin-bottom: 20px; }
        .empty-text { font-size: 1.5em; color: #666; }
        @media (max-width: 768px) {
            .teacher-stats { grid-template-columns: repeat(2, 1fr); }
            .stats-overview { grid-template-columns: repeat(2, 1fr); }
            .controls { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👨‍🏫 أساتذة المادة</h1>
            <p>مادة: <?php echo htmlspecialchars($supervisor['subject_name']); ?></p>
        </div>
        <div class="stats-overview">
            <div class="stat-card">
                <div class="icon">👥</div>
                <div class="number"><?php echo $total_teachers; ?></div>
                <div class="label">إجمالي الأساتذة</div>
            </div>
            <div class="stat-card">
                <div class="icon">📚</div>
                <div class="number"><?php echo $total_all_lessons; ?></div>
                <div class="label">إجمالي الدروس</div>
            </div>
            <div class="stat-card">
                <div class="icon">📝</div>
                <div class="number"><?php echo $total_all_exercises; ?></div>
                <div class="label">إجمالي التمارين</div>
            </div>
            <div class="stat-card">
                <div class="icon">📊</div>
                <div class="number"><?php echo $avg_approval_rate; ?>%</div>
                <div class="label">متوسط معدل الموافقة</div>
            </div>
        </div>
        <div class="controls">
            <div class="sort-group">
                <label>🔄 الترتيب حسب:</label>
                <select onchange="window.location.href='?sort=' + this.value">
                    <option value="lessons" <?php echo $sort === 'lessons' ? 'selected' : ''; ?>>عدد الدروس</option>
                    <option value="approval" <?php echo $sort === 'approval' ? 'selected' : ''; ?>>معدل الموافقة</option>
                    <option value="activity" <?php echo $sort === 'activity' ? 'selected' : ''; ?>>آخر نشاط</option>
                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>الاسم</option>
                </select>
            </div>
            <a href="index.php" class="btn-back">🏠 العودة للرئيسية</a>
        </div>
        <div class="teachers-section">
            <?php if (empty($teachers)): ?>
                <div class="empty-state">
                    <div class="empty-icon">👨‍🏫</div>
                    <div class="empty-text">لا يوجد أساتذة حتى الآن</div>
                </div>
            <?php else: ?>
                <div class="teachers-grid">
                    <?php foreach ($teachers as $teacher): ?>
                        <div class="teacher-card <?php echo $teacher['performance']; ?>">
                            <div class="teacher-header">
                                <div class="teacher-info">
                                    <div class="teacher-name"><?php echo htmlspecialchars($teacher['full_name']); ?></div>
                                    <div class="teacher-contact">📧 <?php echo htmlspecialchars($teacher['email']); ?></div>
                                    <?php if (!empty($teacher['phone'])): ?>
                                        <div class="teacher-contact">📱 <?php echo htmlspecialchars($teacher['phone']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="performance-badge <?php echo $teacher['performance']; ?>">
                                    <?php echo match($teacher['performance']) {
                                        'excellent' => '⭐ ممتاز',
                                        'good' => '✅ جيد',
                                        'average' => '📊 متوسط',
                                        'poor' => '⚠️ يحتاج تحسين',
                                        default => 'غير محدد'
                                    }; ?>
                                </span>
                            </div>
                            <div class="teacher-stats">
                                <div class="stat-box total">
                                    <div class="value"><?php echo $teacher['total_lessons']; ?></div>
                                    <div class="label">إجمالي الدروس</div>
                                </div>
                                <div class="stat-box approved">
                                    <div class="value"><?php echo $teacher['approved_lessons']; ?></div>
                                    <div class="label">✅ معتمدة</div>
                                </div>
                                <div class="stat-box rejected">
                                    <div class="value"><?php echo $teacher['rejected_lessons']; ?></div>
                                    <div class="label">❌ مرفوضة</div>
                                </div>
                                <div class="stat-box pending">
                                    <div class="value"><?php echo $teacher['pending_lessons']; ?></div>
                                    <div class="label">⏳ معلقة</div>
                                </div>
                                <div class="stat-box exercises">
                                    <div class="value"><?php echo $teacher['total_exercises']; ?></div>
                                    <div class="label">📝 تمارين</div>
                                </div>
                            </div>
                            <div class="approval-rate">
                                <div class="approval-rate-label">معدل الموافقة: <strong><?php echo $teacher['approval_rate']; ?>%</strong></div>
                                <div class="progress-bar">
                                    <div class="progress-fill <?php echo $teacher['performance']; ?>" style="width: <?php echo $teacher['approval_rate']; ?>%">
                                        <?php echo $teacher['approval_rate']; ?>%
                                    </div>
                                </div>
                            </div>
                            <div class="teacher-meta">
                                <div class="meta-item">
                                    <span>📅</span>
                                    <span>انضم: <?php echo date('Y/m/d', strtotime($teacher['joined_date'])); ?></span>
                                </div>
                                <?php if ($teacher['last_activity']): ?>
                                    <div class="meta-item">
                                        <span>🕐</span>
                                        <span>آخر نشاط: <?php echo date('Y/m/d', strtotime($teacher['last_activity'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="teacher-actions">
                                <a href="all-lessons.php?teacher=<?php echo $teacher['id']; ?>" class="btn-action btn-view">📚 عرض الدروس</a>
                                <a href="messages.php?teacher=<?php echo $teacher['id']; ?>" class="btn-action btn-message">💬 إرسال رسالة</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
