<?php
/**
 * Teacher Lesson Preview - معاينة الدرس للأستاذ
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['enseignant', 'teacher']);

global $pdo;
$teacher_id = $_SESSION['user_id'];
$lesson_id = $_GET['id'] ?? 0;

if (!$lesson_id) {
    header('Location: manage-lessons.php');
    exit;
}

// جلب معلومات الدرس (تحقق من الملكية)
$stmt = $pdo->prepare("
    SELECT l.*, 
           s.name as subject_name,
           lv.name as level_name,
           st.name as stage_name,
           u.name as teacher_name
    FROM lessons l
    JOIN subjects s ON l.subject_id = s.id
    LEFT JOIN levels lv ON l.level_id = lv.id
    LEFT JOIN stages st ON lv.stage_id = st.id
    JOIN users u ON l.author_id = u.id
    WHERE l.id = ? AND l.author_id = ?
");
$stmt->execute([$lesson_id, $teacher_id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: manage-lessons.php');
    exit;
}

// جلب التمارين
$stmt = $pdo->prepare("
    SELECT e.*,
           COUNT(sa.id) as total_submissions,
           AVG(sa.score) as avg_score
    FROM exercises e
    LEFT JOIN student_answers sa ON e.id = sa.exercise_id
    WHERE e.lesson_id = ?
    GROUP BY e.id
    ORDER BY e.`order` ASC, e.created_at ASC
");
$stmt->execute([$lesson_id]);
$exercises = $stmt->fetchAll();
$total_exercises = count($exercises);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معاينة الدرس - <?php echo htmlspecialchars($lesson['title']); ?></title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .preview-banner {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        
        .lesson-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(76, 175, 80, 0.3);
        }
        
        .lesson-meta {
            display: flex;
            gap: 25px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 8px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .type-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .type-public {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .type-private {
            background: #fce7f3;
            color: #831843;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .card h2 {
            color: #1f2937;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .lesson-content {
            line-height: 1.8;
            color: #374151;
            font-size: 1.05rem;
        }
        
        .media-section {
            background: #f9fafb;
            padding: 25px;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        .exercise-item {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #4CAF50;
        }
        
        .exercise-question {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 10px;
            font-size: 1.05rem;
        }
        
        .exercise-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .actions-bar {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="preview-banner">
                ⚠️ وضع المعاينة - هذه الصفحة للمعاينة فقط ولن يتمكن الطلاب من رؤيتها حتى تتم الموافقة عليها
            </div>
            
            <div class="lesson-header">
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px;">
                    <div style="flex: 1;">
                        <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
                    </div>
                    <div>
                        <span class="status-badge status-<?php echo $lesson['status']; ?>">
                            <?php 
                            $status_text = [
                                'pending' => '⏳ قيد المراجعة',
                                'approved' => '✓ تمت الموافقة',
                                'rejected' => '✗ مرفوض'
                            ];
                            echo $status_text[$lesson['status']] ?? $lesson['status'];
                            ?>
                        </span>
                        <span class="type-badge type-<?php echo $lesson['type']; ?>">
                            <?php echo $lesson['type'] == 'public' ? '🌐 عام' : '🔒 خاص'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="lesson-meta">
                    <div class="meta-item">
                        <span>📚</span>
                        <span><?php echo htmlspecialchars($lesson['subject_name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <span>🎓</span>
                        <span><?php echo htmlspecialchars($lesson['level_name'] ?? 'غير محدد'); ?></span>
                    </div>
                    <div class="meta-item">
                        <span>✍️</span>
                        <span><?php echo $total_exercises; ?> تمرين</span>
                    </div>
                    <div class="meta-item">
                        <span>📅</span>
                        <span><?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="actions-bar">
                <a href="edit-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-primary">
                    ✏️ تعديل الدرس
                </a>
                <a href="exercise-form.php?lesson_id=<?php echo $lesson['id']; ?>" class="btn btn-primary">
                    ➕ إضافة تمرين
                </a>
                <a href="manage-lessons.php" class="btn btn-secondary">
                    ← العودة للدروس
                </a>
            </div>
            
            <div class="card">
                <h2>📖 محتوى الدرس</h2>
                <div class="lesson-content">
                    <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                </div>
                
                <?php if ($lesson['video_url'] || $lesson['pdf_url']): ?>
                    <div class="media-section">
                        <h3 style="margin-bottom: 15px;">📎 الملفات المرفقة</h3>
                        <?php if ($lesson['video_url']): ?>
                            <p>🎥 <strong>فيديو:</strong> <a href="<?php echo htmlspecialchars($lesson['video_url']); ?>" target="_blank"><?php echo htmlspecialchars($lesson['video_url']); ?></a></p>
                        <?php endif; ?>
                        <?php if ($lesson['pdf_url']): ?>
                            <p>📄 <strong>PDF:</strong> <a href="<?php echo htmlspecialchars($lesson['pdf_url']); ?>" target="_blank"><?php echo htmlspecialchars($lesson['pdf_url']); ?></a></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($exercises)): ?>
                <div class="card">
                    <h2>✍️ التمارين (<?php echo $total_exercises; ?>)</h2>
                    
                    <?php foreach ($exercises as $index => $exercise): ?>
                        <div class="exercise-item">
                            <div class="exercise-question">
                                <?php echo ($index + 1) . '. ' . htmlspecialchars($exercise['question']); ?>
                            </div>
                            <div style="color: #6b7280; margin-top: 10px;">
                                <strong>الإجابة النموذجية:</strong> 
                                <?php echo htmlspecialchars($exercise['model_answer']); ?>
                            </div>
                            <div class="exercise-stats">
                                <span>📊 <?php echo $exercise['total_submissions']; ?> محاولة</span>
                                <span>🏆 متوسط الدرجات: <?php echo $exercise['avg_score'] ? round($exercise['avg_score'], 1) . '%' : '-'; ?></span>
                                <span>🔢 الترتيب: <?php echo $exercise['order']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
