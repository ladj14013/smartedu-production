<?php
/**
 * Student Exercise Solve Page - صفحة حل التمرين
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['etudiant', 'student']);

global $pdo;
$student_id = $_SESSION['user_id'];
$exercise_id = $_GET['id'] ?? 0;

if (!$exercise_id) {
    header('Location: lessons.php');
    exit;
}

// جلب بيانات التمرين
$stmt = $pdo->prepare("
    SELECT e.*, 
           l.title as lesson_title,
           l.id as lesson_id,
           s.name as subject_name
    FROM exercises e
    JOIN lessons l ON e.lesson_id = l.id
    JOIN subjects s ON l.subject_id = s.id
    WHERE e.id = ?
");
$stmt->execute([$exercise_id]);
$exercise = $stmt->fetch();

if (!$exercise) {
    header('Location: lessons.php');
    exit;
}

// جلب آخر إجابة للطالب إذا وجدت
$stmt = $pdo->prepare("
    SELECT * FROM student_answers 
    WHERE student_id = ? AND exercise_id = ?
    ORDER BY submitted_at DESC
    LIMIT 1
");
$stmt->execute([$student_id, $exercise_id]);
$last_answer = $stmt->fetch();

// معالجة إرسال الإجابة
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    $answer = trim($_POST['answer'] ?? '');
    
    if (empty($answer)) {
        $error_message = "يرجى كتابة إجابتك";
    } else {
        try {
            // حذف الإجابة القديمة إذا وجدت
            $pdo->prepare("DELETE FROM student_answers WHERE student_id = ? AND exercise_id = ?")
                ->execute([$student_id, $exercise_id]);
            
            // إضافة الإجابة الجديدة
            $stmt = $pdo->prepare("
                INSERT INTO student_answers (student_id, exercise_id, answer, score, submitted_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            // حساب درجة بسيطة (يمكن تحسينها لاحقاً بالذكاء الاصطناعي)
            $score = 70; // درجة افتراضية
            
            $stmt->execute([$student_id, $exercise_id, $answer, $score]);
            $success_message = "تم إرسال إجابتك بنجاح! ✓ الدرجة: $score%";
            
            // تحديث التقدم
            header("refresh:2;url=lesson-view.php?id=" . $exercise['lesson_id']);
        } catch (PDOException $e) {
            $error_message = "حدث خطأ: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حل التمرين - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .exercise-header {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.3);
        }
        
        .breadcrumb {
            font-size: 0.9rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .breadcrumb a {
            color: white;
            text-decoration: underline;
        }
        
        .exercise-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .question-section {
            background: #f9fafb;
            padding: 25px;
            border-radius: 12px;
            border-left: 4px solid #8b5cf6;
            margin-bottom: 30px;
        }
        
        .question-label {
            font-weight: 700;
            color: #8b5cf6;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        
        .question-text {
            font-size: 1.1rem;
            color: #1f2937;
            line-height: 1.8;
            white-space: pre-wrap;
        }
        
        .answer-section {
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #22c55e;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        
        .info-box {
            background: #dbeafe;
            border: 2px solid #3b82f6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .info-box h4 {
            color: #1e40af;
            margin: 0 0 10px 0;
        }
        
        .info-box p {
            color: #1e3a8a;
            margin: 0;
        }
        
        .previous-answer {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .previous-answer h4 {
            color: #92400e;
            margin: 0 0 15px 0;
        }
        
        .score-badge {
            display: inline-block;
            background: #22c55e;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 700;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="exercise-header">
                <div class="breadcrumb">
                    <a href="lessons.php">الدروس</a> / 
                    <a href="lesson-view.php?id=<?php echo $exercise['lesson_id']; ?>"><?php echo htmlspecialchars($exercise['lesson_title']); ?></a> / 
                    حل التمرين
                </div>
                <h1>✍️ حل التمرين</h1>
                <p>📚 المادة: <?php echo htmlspecialchars($exercise['subject_name']); ?></p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">✓ <?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">✗ <?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if ($last_answer): ?>
                <div class="previous-answer">
                    <h4>📋 إجابتك السابقة</h4>
                    <p><?php echo htmlspecialchars($last_answer['answer']); ?></p>
                    <span class="score-badge">🏆 الدرجة: <?php echo $last_answer['score']; ?>%</span>
                    <p style="margin-top: 10px; color: #92400e; font-size: 0.9rem;">
                        يمكنك إعادة الإجابة لتحسين درجتك
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="exercise-card">
                <div class="question-section">
                    <div class="question-label">❓ السؤال:</div>
                    <div class="question-text"><?php echo nl2br(htmlspecialchars($exercise['question'])); ?></div>
                </div>
                
                <div class="info-box">
                    <h4>💡 نصائح للإجابة:</h4>
                    <p>• اكتب إجابتك بشكل واضح ومفصل</p>
                    <p>• راجع إجابتك قبل الإرسال</p>
                    <p>• يمكنك إعادة المحاولة لتحسين درجتك</p>
                </div>
                
                <div class="answer-section">
                    <form method="POST">
                        <div class="form-group">
                            <label>✏️ إجابتك:</label>
                            <textarea name="answer" class="form-control" 
                                      placeholder="اكتب إجابتك هنا..." 
                                      required><?php echo $last_answer ? htmlspecialchars($last_answer['answer']) : ''; ?></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 25px;">
                            <button type="submit" name="submit_answer" class="btn btn-primary">
                                📤 إرسال الإجابة
                            </button>
                            <a href="lesson-view.php?id=<?php echo $exercise['lesson_id']; ?>" class="btn btn-secondary">
                                ← العودة للدرس
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
