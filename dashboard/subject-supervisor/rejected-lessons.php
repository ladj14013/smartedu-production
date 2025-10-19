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

// معالجة إعادة المراجعة
if (isset($_GET['review_again']) && isset($_GET['id'])) {
    $lesson_id = $_GET['id'];
    try {
        $update = "UPDATE lessons 
                   SET status = 'pending'
                   WHERE id = :lesson_id AND subject_id = :subject_id AND status = 'rejected'";
        $stmt = $pdo->prepare($update);
        $stmt->execute([':lesson_id' => $lesson_id, ':subject_id' => $subject_id]);
        header('Location: pending-lessons.php?success=review_again');
        exit();
    } catch (PDOException $e) {
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// جلب الدروس المرفوضة
$query = "SELECT l.*, CONCAT(u.nom, ' ', u.prenom) as teacher_name,
          (SELECT COUNT(*) FROM exercises WHERE lesson_id = l.id) as exercises_count
          FROM lessons l
          JOIN users u ON l.author_id = u.id
          WHERE l.subject_id = :subject_id AND l.status = 'rejected'
          ORDER BY l.updated_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([':subject_id' => $subject_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الدروس المرفوضة - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .main-content {
            margin-right: 280px;
            padding: 40px;
            background: #f5f5f5;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(244, 67, 54, 0.3);
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
            border-right: 5px solid #f44336;
        }

        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(244, 67, 54, 0.15);
        }

        .lesson-status {
            background: #F8D7DA;
            color: #721C24;
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

        .rejection-reason {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-right: 4px solid #f44336;
        }

        .rejection-label {
            font-weight: 600;
            color: #856404;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .rejection-text {
            color: #333;
            line-height: 1.6;
            white-space: pre-wrap;
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

        .btn-review {
            background: #FF9800;
            color: white;
        }

        .btn-review:hover {
            background: #F57C00;
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
            <h1>❌ الدروس المرفوضة</h1>
            <p>الدروس التي تم رفضها مع أسباب الرفض</p>
        </div>

        <p style="margin-bottom: 20px; color: #666;">
            إجمالي الدروس المرفوضة: <strong style="color: #f44336;"><?php echo count($lessons); ?></strong>
        </p>

        <?php if (count($lessons) > 0): ?>
            <div class="lessons-grid">
                <?php foreach ($lessons as $lesson): ?>
                    <div class="lesson-card">
                        <span class="lesson-status">❌ مرفوض</span>
                        <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                        <div class="lesson-meta">
                            <span>👨‍🏫 <?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                            <span>📅 تاريخ الرفض: <?php echo date('Y/m/d', strtotime($lesson['updated_at'])); ?></span>
                            <span>✍️ <?php echo $lesson['exercises_count']; ?> تمرين</span>
                        </div>

                        <?php if (!empty($lesson['supervisor_notes'])): ?>
                            <div class="rejection-reason">
                                <div class="rejection-label">📝 سبب الرفض:</div>
                                <div class="rejection-text">
                                    <?php echo nl2br(htmlspecialchars($lesson['supervisor_notes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="lesson-actions">
                            <a href="preview-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-view">
                                👁️ معاينة
                            </a>
                            <a href="?review_again=1&id=<?php echo $lesson['id']; ?>" class="btn btn-review"
                               onclick="return confirm('إعادة الدرس للمراجعة؟ سيصبح معلقاً مرة أخرى.');">
                                🔄 إعادة مراجعة
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p style="font-size: 60px;">✅</p>
                <p style="font-size: 18px; color: #666;">لا توجد دروس مرفوضة</p>
                <p style="font-size: 14px; color: #999;">جميع الدروس إما معتمدة أو معلقة</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
