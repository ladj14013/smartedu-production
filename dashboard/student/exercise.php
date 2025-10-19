<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('student');

$student_id = $_SESSION['user_id'];
$exercise_id = $_GET['id'] ?? null;

if (!$exercise_id) {
    header("Location: exercises.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// جلب تفاصيل التمرين
$query = "SELECT e.*, l.title as lesson_title, s.name as subject_name
          FROM exercises e
          JOIN lessons l ON e.lesson_id = l.id
          JOIN subjects s ON l.subject_id = s.id
          WHERE e.id = :exercise_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':exercise_id', $exercise_id);
$stmt->execute();
$exercise = $stmt->fetch();

if (!$exercise) {
    header("Location: exercises.php");
    exit();
}

// جلب الإجابة السابقة إن وجدت
$query = "SELECT * FROM student_answers 
          WHERE student_id = :student_id AND exercise_id = :exercise_id 
          ORDER BY submitted_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->bindParam(':exercise_id', $exercise_id);
$stmt->execute();
$previous_answer = $stmt->fetch();

// معالجة إرسال الإجابة
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $answer = trim($_POST['answer']);
    
    if (empty($answer)) {
        $error = 'يجب إدخال إجابة.';
    } else {
        try {
            $query = "INSERT INTO student_answers (student_id, exercise_id, answer) 
                      VALUES (:student_id, :exercise_id, :answer)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':exercise_id', $exercise_id);
            $stmt->bindParam(':answer', $answer);
            
            if ($stmt->execute()) {
                $success = 'تم إرسال الإجابة بنجاح! ✅';
                
                // تحديث الإجابة السابقة
                $previous_answer = [
                    'answer' => $answer,
                    'ai_feedback' => null,
                    'score' => null,
                    'submitted_at' => date('Y-m-d H:i:s')
                ];
            }
        } catch (PDOException $e) {
            $error = 'حدث خطأ في إرسال الإجابة.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تمرين - <?php echo htmlspecialchars($exercise['lesson_title']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <a href="exercises.php" class="btn btn-secondary">← العودة للتمارين</a>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title"><?php echo htmlspecialchars($exercise['lesson_title']); ?></h1>
                    <span class="badge badge-accent"><?php echo htmlspecialchars($exercise['subject_name']); ?></span>
                </div>
                
                <div class="card-body">
                    <div class="exercise-question">
                        <h3>📝 السؤال:</h3>
                        <p><?php echo nl2br(htmlspecialchars($exercise['question'])); ?></p>
                    </div>
                    
                    <?php if ($exercise['pdf_url']): ?>
                        <div class="exercise-pdf" style="margin-top: 1.5rem;">
                            <a href="<?php echo htmlspecialchars($exercise['pdf_url']); ?>" target="_blank" class="btn btn-primary">
                                📄 عرض ملف PDF المرفق
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($previous_answer): ?>
                        <div class="previous-answer" style="margin-top: 2rem; padding: 1.5rem; background: #f9fafb; border-radius: 0.5rem;">
                            <h3>✅ إجابتك السابقة:</h3>
                            <p><?php echo nl2br(htmlspecialchars($previous_answer['answer'])); ?></p>
                            
                            <?php if ($previous_answer['score']): ?>
                                <div style="margin-top: 1rem;">
                                    <strong>الدرجة: </strong>
                                    <span class="badge badge-<?php echo $previous_answer['score'] >= 70 ? 'success' : 'warning'; ?>">
                                        <?php echo $previous_answer['score']; ?>%
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- الملاحظات غير مستخدمة حالياً -->
                            
                            <small style="color: var(--text-secondary); display: block; margin-top: 1rem;">
                                تم الإرسال في: <?php echo date('Y-m-d H:i', strtotime($previous_answer['submitted_at'])); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="answer-form" style="margin-top: 2rem;">
                        <h3>✍️ إجابتك:</h3>
                        <form method="POST" action="" style="margin-top: 1rem;">
                            <div class="form-group">
                                <textarea 
                                    name="answer" 
                                    rows="8" 
                                    placeholder="اكتب إجابتك هنا..."
                                    required
                                ><?php echo $previous_answer ? htmlspecialchars($previous_answer['answer']) : ''; ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-accent">
                                إرسال الإجابة
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        .exercise-question {
            padding: 1.5rem;
            background: rgba(255, 167, 38, 0.05);
            border-radius: 0.5rem;
            border-right: 4px solid var(--accent);
        }
        
        .exercise-question h3 {
            margin: 0 0 1rem 0;
            color: var(--accent);
        }
        
        .exercise-question p {
            margin: 0;
            font-size: 1.125rem;
            line-height: 1.8;
        }
    </style>
</body>
</html>
