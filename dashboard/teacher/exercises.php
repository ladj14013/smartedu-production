<?php
/**
 * Teacher Exercises Management - إدارة التمارين والأسئلة
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['enseignant', 'teacher']);

global $pdo;
$user_id = $_SESSION['user_id'];

// جلب معلومات المعلم
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

// معالجة الحذف
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $exercise_id = $_GET['delete'];
    
    // التحقق من الملكية
    $stmt = $pdo->prepare("
        SELECT e.* FROM exercises e
        JOIN lessons l ON e.lesson_id = l.id
        WHERE e.id = ? AND l.author_id = ?
    ");
    $stmt->execute([$exercise_id, $user_id]);
    
    if ($stmt->fetch()) {
        // حذف الأسئلة أولاً (إذا كان الجدول موجود)
        try {
            $pdo->prepare("DELETE FROM exercise_questions WHERE exercise_id = ?")->execute([$exercise_id]);
        } catch (PDOException $e) {
            // الجدول غير موجود
        }
        // حذف النتائج (إذا كان الجدول موجود)
        try {
            $pdo->prepare("DELETE FROM student_answers WHERE exercise_id = ?")->execute([$exercise_id]);
        } catch (PDOException $e) {
            // الجدول غير موجود
        }
        // حذف التمرين
        $pdo->prepare("DELETE FROM exercises WHERE id = ?")->execute([$exercise_id]);
        
        header('Location: exercises.php?deleted=1');
        exit;
    }
}

// Filters
$filter_lesson = $_GET['lesson'] ?? '';
$search = $_GET['search'] ?? '';

// جلب دروس المعلم
$stmt = $pdo->prepare("
    SELECT l.*, s.name as subject_name
    FROM lessons l
    JOIN subjects s ON l.subject_id = s.id
    WHERE l.author_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$user_id]);
$teacher_lessons = $stmt->fetchAll();

// بناء الاستعلام
$where = ["l.author_id = ?"];
$params = [$user_id];

if ($filter_lesson) {
    $where[] = "e.lesson_id = ?";
    $params[] = $filter_lesson;
}

if ($search) {
    $where[] = "(e.question LIKE ? OR e.model_answer LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$where_sql = implode(' AND ', $where);

// جلب التمارين
$stmt = $pdo->prepare("
    SELECT e.*, 
           l.title as lesson_title,
           s.name as subject_name,
           COUNT(DISTINCT sa.id) as submissions_count,
           AVG(sa.score) as avg_score
    FROM exercises e
    JOIN lessons l ON e.lesson_id = l.id
    JOIN subjects s ON l.subject_id = s.id
    LEFT JOIN student_answers sa ON e.id = sa.exercise_id
    WHERE $where_sql
    GROUP BY e.id
    ORDER BY e.`order` ASC, e.created_at DESC
");
$stmt->execute($params);
$exercises = $stmt->fetchAll();

// إحصائيات
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT e.id) as total_exercises,
        COUNT(DISTINCT sa.id) as total_submissions,
        AVG(sa.score) as avg_score
    FROM exercises e
    JOIN lessons l ON e.lesson_id = l.id
    LEFT JOIN student_answers sa ON e.id = sa.exercise_id
    WHERE l.author_id = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة التمارين - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
        
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.blue { border-right-color: #4285F4; }
        .stat-card.green { border-right-color: #22c55e; }
        .stat-card.purple { border-right-color: #8b5cf6; }
        .stat-card.orange { border-right-color: #FFA726; }
        
        .stat-icon { font-size: 2.5rem; margin-bottom: 10px; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #1f2937; margin-bottom: 5px; }
        .stat-label { color: #6b7280; font-size: 0.95rem; }
        
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4285F4;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
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
            background: linear-gradient(135deg, #4285F4, #0066cc);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 133, 244, 0.3);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }
        
        .exercises-grid {
            display: grid;
            gap: 20px;
        }
        
        .exercise-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-top: 4px solid #4285F4;
        }
        
        .exercise-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .exercise-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .exercise-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .exercise-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 15px;
        }
        
        .exercise-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .exercise-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-item-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4285F4;
        }
        
        .stat-item-label {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 4px;
        }
        
        .exercise-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-info {
            background: #4285F4;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .empty-icon { font-size: 4rem; margin-bottom: 15px; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #22c55e;
        }
        
        @media (max-width: 968px) {
            .exercise-stats { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        /* Content Type Badges */
        .content-badges {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .content-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .content-badge.pdf {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        
        .content-badge.equation {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .content-badge:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
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
                        <h1>✍️ إدارة التمارين</h1>
                        <p>إنشاء وإدارة التمارين والأسئلة</p>
                    </div>
                    <div class="header-actions">
                        <a href="exercise-form.php" class="btn btn-success">
                            ➕ إضافة تمرين جديد
                        </a>
                    </div>
                </div>
            </header>
            
            <!-- Success Message -->
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">
                    ✓ تم حذف التمرين بنجاح
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">✍️</div>
                    <div class="stat-value"><?php echo $stats['total_exercises'] ?? 0; ?></div>
                    <div class="stat-label">إجمالي التمارين</div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-icon">📝</div>
                    <div class="stat-value"><?php echo $stats['total_submissions'] ?? 0; ?></div>
                    <div class="stat-label">محاولات الطلاب</div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value"><?php echo round($stats['avg_score'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">متوسط النتائج</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <h3 style="margin-bottom: 10px;">🔍 البحث والتصفية</h3>
                <form method="GET" class="filters-grid">
                    <div class="form-group">
                        <label>بحث</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="ابحث في التمارين..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>الدرس</label>
                        <select name="lesson" class="form-control">
                            <option value="">جميع الدروس</option>
                            <?php foreach ($teacher_lessons as $lesson): ?>
                                <option value="<?php echo $lesson['id']; ?>" 
                                        <?php echo $filter_lesson == $lesson['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lesson['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="justify-content: flex-end;">
                        <label>&nbsp;</label>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">تطبيق الفلاتر</button>
                            <a href="exercises.php" class="btn btn-secondary">إعادة تعيين</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Exercises List -->
            <?php if (empty($exercises)): ?>
                <div class="empty-state">
                    <div class="empty-icon">✍️</div>
                    <h3>لا توجد تمارين بعد</h3>
                    <p style="color: #6b7280; margin-bottom: 20px;">
                        ابدأ بإنشاء تمرين جديد لطلابك
                    </p>
                    <a href="exercise-form.php" class="btn btn-success">
                        ➕ إضافة تمرين جديد
                    </a>
                </div>
            <?php else: ?>
                <div class="exercises-grid">
                    <?php foreach ($exercises as $exercise): ?>
                        <div class="exercise-card">
                            <div class="exercise-header">
                                <div style="flex: 1;">
                                    <div class="exercise-title">
                                        <?php echo htmlspecialchars(mb_substr($exercise['question'], 0, 100)) . (mb_strlen($exercise['question']) > 100 ? '...' : ''); ?>   
                                    </div>
                                    <div class="exercise-meta">
                                        <span>
                                            📚 <?php echo htmlspecialchars($exercise['subject_name']); ?>
                                        </span>
                                        <span>
                                            � <?php echo htmlspecialchars($exercise['lesson_title']); ?>
                                        </span>
                                        <span>
                                            📅 <?php echo date('Y/m/d', strtotime($exercise['created_at'])); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($exercise['description']): ?>
                                        <p style="color: #6b7280; margin-bottom: 15px;">
                                            <?php echo htmlspecialchars($exercise['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="exercise-stats">
                                <div class="stat-item">
                                    <div class="stat-item-value"><?php echo $exercise['submissions_count']; ?></div>
                                    <div class="stat-item-label">محاولات</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-item-value">
                                        <?php echo $exercise['avg_score'] ? round($exercise['avg_score'], 1) . '%' : '-'; ?>
                                    </div>
                                    <div class="stat-item-label">متوسط النتيجة</div>
                                </div>
                            </div>
                            
                            <div class="exercise-actions">
                                <!-- Content Type Icons -->
                                <div class="content-badges">
                                    <?php if (!empty($exercise['pdf_url'])): ?>
                                        <span class="content-badge pdf" title="يحتوي على ملف PDF">📄</span>
                                    <?php endif; ?>
                                    
                                    <?php if (preg_match('/\$.*?\$|\$\$.*?\$\$/s', $exercise['question'] . $exercise['model_answer'])): ?>
                                        <span class="content-badge equation" title="يحتوي على معادلات رياضية">🔢</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="display: flex; gap: 10px; margin-right: auto;">
                                    <a href="exercise-form.php?id=<?php echo $exercise['id']; ?>" 
                                       class="btn btn-info btn-sm">
                                        ✏️ تعديل
                                    </a>
                                    <a href="?delete=<?php echo $exercise['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('هل أنت متأكد من حذف هذا التمرين؟ سيتم حذف جميع الأسئلة والنتائج المرتبطة به.')">
                                        🗑️ حذف
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
