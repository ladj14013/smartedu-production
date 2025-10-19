<?php
// عرض الأخطاء للتشخيص
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/auth.php';
require_once '../../config/database.php';
requireLogin();
requireRole(['superviseur_matiere', 'supervisor_subject', 'subject_supervisor']);
$subject_id = $_SESSION['subject_id'] ?? null;

if (!$subject_id) {
    die('خطأ: لم يتم تعيين مادة لهذا المشرف');
}

// معالجة الفلاتر
$filters = [];
$params = [':subject_id' => $subject_id];

if (isset($_GET['teacher']) && !empty($_GET['teacher'])) {
    $filters[] = "l.author_id = :teacher_id";
    $params[':teacher_id'] = $_GET['teacher'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters[] = "l.title LIKE :search";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $filters[] = "l.lesson_type = :type";
    $params[':type'] = $_GET['type'];
}

// الترتيب
$order_by = "l.created_at DESC";
if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'oldest':
            $order_by = "l.created_at ASC";
            break;
        case 'title':
            $order_by = "l.title ASC";
            break;
        case 'teacher':
            $order_by = "u.nom ASC";
            break;
    }
}

// بناء الاستعلام
$where_clause = "l.subject_id = :subject_id AND l.status = 'pending'";
if (!empty($filters)) {
    $where_clause .= " AND " . implode(" AND ", $filters);
}

$query = "SELECT l.*, CONCAT(u.nom, ' ', u.prenom) as teacher_name, u.email as teacher_email,
          (SELECT COUNT(*) FROM exercises WHERE lesson_id = l.id) as exercises_count
          FROM lessons l
          JOIN users u ON l.author_id = u.id
          WHERE $where_clause
          ORDER BY $order_by";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب قائمة الأساتذة للفلتر
$teachers_query = "SELECT DISTINCT u.id, CONCAT(u.nom, ' ', u.prenom) as full_name 
                   FROM users u
                   JOIN lessons l ON u.id = l.author_id
                   WHERE l.subject_id = :subject_id 
                   AND u.role IN ('enseignant', 'teacher')
                   ORDER BY u.nom, u.prenom";
$stmt = $pdo->prepare($teachers_query);
$stmt->execute([':subject_id' => $subject_id]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الدروس المعلقة - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .main-content {
            margin-right: 280px;
            padding: 40px;
            background: #f5f5f5;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(255, 152, 0, 0.3);
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
            font-weight: 700;
        }

        .page-header p {
            margin: 5px 0;
            opacity: 0.95;
            font-size: 15px;
        }

        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #FF9800;
            box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 25px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #FF9800;
            color: white;
        }

        .btn-primary:hover {
            background: #F57C00;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .lessons-count {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .count-text {
            font-size: 16px;
            color: #666;
        }

        .count-number {
            font-size: 24px;
            font-weight: bold;
            color: #FF9800;
        }

        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .lesson-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-right: 5px solid #FF9800;
        }

        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(255, 152, 0, 0.15);
        }

        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .lesson-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .lesson-status {
            background: #FFF3CD;
            color: #856404;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .lesson-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }

        .meta-icon {
            font-size: 16px;
        }

        .lesson-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .stat-badge {
            background: #f5f5f5;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #666;
        }

        .lesson-actions {
            display: flex;
            gap: 10px;
        }

        .btn-review {
            flex: 1;
            background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-review:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(156, 39, 176, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-text {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }

        .empty-subtext {
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>⏳ الدروس المعلقة</h1>
            <p>قائمة الدروس التي تحتاج إلى مراجعتك والموافقة عليها</p>
        </div>

        <!-- الفلاتر -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>🔍 البحث في العنوان</label>
                        <input type="text" name="search" placeholder="ابحث عن درس..." 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>

                    <div class="filter-group">
                        <label>👨‍🏫 الأستاذ</label>
                        <select name="teacher">
                            <option value="">جميع الأساتذة</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" 
                                        <?php echo (isset($_GET['teacher']) && $_GET['teacher'] == $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>📚 نوع الدرس</label>
                        <select name="type">
                            <option value="">جميع الأنواع</option>
                            <option value="interactive" <?php echo (isset($_GET['type']) && $_GET['type'] == 'interactive') ? 'selected' : ''; ?>>
                                تفاعلي
                            </option>
                            <option value="مقروء" <?php echo (isset($_GET['type']) && $_GET['type'] == 'مقروء') ? 'selected' : ''; ?>>
                                مقروء
                            </option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>🔄 الترتيب</label>
                        <select name="sort">
                            <option value="newest" <?php echo (!isset($_GET['sort']) || $_GET['sort'] == 'newest') ? 'selected' : ''; ?>>
                                الأحدث أولاً
                            </option>
                            <option value="oldest" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'oldest') ? 'selected' : ''; ?>>
                                الأقدم أولاً
                            </option>
                            <option value="title" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'title') ? 'selected' : ''; ?>>
                                حسب العنوان
                            </option>
                            <option value="teacher" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'teacher') ? 'selected' : ''; ?>>
                                حسب الأستاذ
                            </option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 تطبيق الفلاتر</button>
                    <a href="pending-lessons.php" class="btn btn-secondary">🔄 إعادة تعيين</a>
                </div>
            </form>
        </div>

        <!-- عدد النتائج -->
        <div class="lessons-count">
            <span class="count-text">إجمالي الدروس المعلقة:</span>
            <span class="count-number"><?php echo count($lessons); ?></span>
        </div>

        <!-- قائمة الدروس -->
        <?php if (count($lessons) > 0): ?>
            <div class="lessons-grid">
                <?php foreach ($lessons as $lesson): ?>
                    <div class="lesson-card">
                        <div class="lesson-header">
                            <div style="flex: 1;">
                                <div class="lesson-title">
                                    <?php echo htmlspecialchars($lesson['title']); ?>
                                </div>
                            </div>
                            <div class="lesson-status">
                                ⏳ معلق
                            </div>
                        </div>

                        <div class="lesson-meta">
                            <div class="meta-item">
                                <span class="meta-icon">👨‍🏫</span>
                                <strong>الأستاذ:</strong> 
                                <?php echo htmlspecialchars($lesson['teacher_name']); ?>
                            </div>
                            <div class="meta-item">
                                <span class="meta-icon">📅</span>
                                <strong>تاريخ الإنشاء:</strong> 
                                <?php echo date('Y/m/d - H:i', strtotime($lesson['created_at'])); ?>
                            </div>
                            <div class="meta-item">
                                <span class="meta-icon">📚</span>
                                <strong>النوع:</strong> 
                                <?php echo htmlspecialchars($lesson['lesson_type'] ?? 'غير محدد'); ?>
                            </div>
                        </div>

                        <div class="lesson-stats">
                            <span class="stat-badge">
                                ✍️ <?php echo $lesson['exercises_count']; ?> تمرين
                            </span>
                            <?php if (!empty($lesson['video_url'])): ?>
                                <span class="stat-badge">🎥 فيديو</span>
                            <?php endif; ?>
                            <?php if (!empty($lesson['pdf_url'])): ?>
                                <span class="stat-badge">📄 PDF</span>
                            <?php endif; ?>
                        </div>

                        <div class="lesson-actions">
                            <a href="review-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn-review">
                                👁️ مراجعة الآن
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">✅</div>
                <div class="empty-text">لا توجد دروس معلقة</div>
                <div class="empty-subtext">جميع الدروس تمت مراجعتها بنجاح</div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
