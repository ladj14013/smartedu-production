<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
requireLogin();
requireRole(['superviseur_matiere', 'supervisor_subject', 'subject_supervisor']);

global $pdo;
$subject_id = $_SESSION['subject_id'] ?? null;

if (!$subject_id) {
    die('خطأ: لم يتم تعيين مادة لهذا المشرف');
}

// معالجة إلغاء الموافقة
if (isset($_GET['revert']) && isset($_GET['id'])) {
    $lesson_id = $_GET['id'];
    try {
        $update = "UPDATE lessons 
                   SET status = 'pending', 
                       supervisor_notes = CONCAT(COALESCE(supervisor_notes, ''), '\n[تم إلغاء الموافقة في ', NOW(), ']')
                   WHERE id = :lesson_id AND subject_id = :subject_id AND status = 'approved'";
        $stmt = $pdo->prepare($update);
        $stmt->execute([':lesson_id' => $lesson_id, ':subject_id' => $subject_id]);
        header('Location: approved-lessons.php?success=reverted');
        exit();
    } catch (PDOException $e) {
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// الفلاتر
$filters = [];
$params = [':subject_id' => $subject_id];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters[] = "l.title LIKE :search";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

$where_clause = "l.subject_id = :subject_id AND l.status = 'approved'";
if (!empty($filters)) {
    $where_clause .= " AND " . implode(" AND ", $filters);
}

$query = "SELECT l.*, CONCAT(u.nom, ' ', u.prenom) as teacher_name,
          (SELECT COUNT(*) FROM exercises WHERE lesson_id = l.id) as exercises_count
          FROM lessons l
          JOIN users u ON l.author_id = u.id
          WHERE $where_clause
          ORDER BY l.updated_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الدروس المعتمدة - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .main-content {
            margin-right: 280px;
            padding: 40px;
            background: #f5f5f5;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(76, 175, 80, 0.3);
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
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
            border-right: 5px solid #4CAF50;
        }

        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.15);
        }

        .lesson-status {
            background: #D4EDDA;
            color: #155724;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .lesson-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 15px 0 10px 0;
        }

        .lesson-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }

        .lesson-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            text-align: center;
        }

        .btn-view {
            background: #9C27B0;
            color: white;
            flex: 1;
        }

        .btn-view:hover {
            background: #7B1FA2;
        }

        .btn-revert {
            background: #FF9800;
            color: white;
        }

        .btn-revert:hover {
            background: #F57C00;
        }

        .search-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>✅ الدروس المعتمدة</h1>
            <p>الدروس التي تمت الموافقة عليها والمتاحة للطلاب</p>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'reverted'): ?>
            <div class="alert-success">
                ✅ تم إلغاء الموافقة بنجاح. الدرس أصبح معلقاً مرة أخرى.
            </div>
        <?php endif; ?>

        <div class="search-box">
            <form method="GET">
                <input type="text" name="search" placeholder="🔍 ابحث في الدروس المعتمدة..." 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </form>
        </div>

        <p style="margin-bottom: 20px; color: #666;">
            إجمالي الدروس المعتمدة: <strong style="color: #4CAF50;"><?php echo count($lessons); ?></strong>
        </p>

        <?php if (count($lessons) > 0): ?>
            <div class="lessons-grid">
                <?php foreach ($lessons as $lesson): ?>
                    <div class="lesson-card">
                        <span class="lesson-status">✅ معتمد</span>
                        <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                        <div class="lesson-meta">
                            <span>👨‍🏫 <?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                            <span>📅 <?php echo date('Y/m/d', strtotime($lesson['updated_at'])); ?></span>
                            <span>✍️ <?php echo $lesson['exercises_count']; ?> تمرين</span>
                        </div>
                        <div class="lesson-actions">
                            <a href="preview-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-view">
                                👁️ معاينة
                            </a>
                            <a href="?revert=1&id=<?php echo $lesson['id']; ?>" class="btn btn-revert"
                               onclick="return confirm('هل تريد إلغاء الموافقة؟ سيعود الدرس إلى حالة المعلق.');">
                                ↩️ إلغاء
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p style="font-size: 60px;">📚</p>
                <p style="font-size: 18px; color: #666;">لا توجد دروس معتمدة بعد</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
