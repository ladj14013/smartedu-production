<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('teacher');

$teacher_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

// جلب إجابات الطلاب للدروس الخاصة بالمعلم
$query = "SELECT sa.*, u.name as student_name, e.question, l.title as lesson_title
          FROM student_answers sa
          JOIN users u ON sa.student_id = u.id
          JOIN exercises e ON sa.exercise_id = e.id
          JOIN lessons l ON e.lesson_id = l.id
          WHERE l.author_id = :teacher_id
          ORDER BY sa.submitted_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$answers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإجابات - Smart Education Hub</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>✍️ إجابات الطلاب</h1>
                <p>راجع وقيّم إجابات طلابك</p>
            </div>
            
            <?php if (empty($answers)): ?>
                <div class="card">
                    <div class="card-body text-center" style="padding: 3rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">✍️</div>
                        <h3>لا توجد إجابات بعد</h3>
                        <p style="color: var(--text-secondary);">
                            عندما يقوم طلابك بحل التمارين، ستظهر إجاباتهم هنا
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="answers-list">
                    <?php foreach ($answers as $answer): ?>
                        <div class="card">
                            <div class="card-body">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                    <div>
                                        <h3 style="margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($answer['student_name']); ?></h3>
                                        <p style="margin: 0; color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($answer['lesson_title']); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <?php if ($answer['score']): ?>
                                            <span class="badge badge-<?php echo $answer['score'] >= 70 ? 'success' : 'warning'; ?>">
                                                ⭐ <?php echo $answer['score']; ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">⏳ قيد التقييم</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div style="padding: 1rem; background: #f9fafb; border-radius: 0.5rem; margin-bottom: 1rem;">
                                    <strong>السؤال:</strong>
                                    <p style="margin: 0.5rem 0 0 0;"><?php echo nl2br(htmlspecialchars($answer['question'])); ?></p>
                                </div>
                                
                                <div style="padding: 1rem; background: rgba(66, 133, 244, 0.05); border-radius: 0.5rem; border-right: 3px solid var(--primary);">
                                    <strong>إجابة الطالب:</strong>
                                    <p style="margin: 0.5rem 0 0 0;"><?php echo nl2br(htmlspecialchars($answer['answer'])); ?></p>
                                </div>
                                
                                <!-- الملاحظات غير مستخدمة حالياً -->
                                
                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); color: var(--text-secondary); font-size: 0.875rem;">
                                    📅 تم الإرسال في: <?php echo date('Y-m-d H:i', strtotime($answer['submitted_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <style>
        .answers-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
    </style>
</body>
</html>
