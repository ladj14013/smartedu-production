<?php
/**
 * Teacher Exercise Questions Management - إدارة أسئلة التمرين
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['teacher']);

$user_id = $_SESSION['user_id'];
$exercise_id = $_GET['exercise_id'] ?? 0;

if (!$exercise_id) {
    header('Location: exercises.php');
    exit;
}

// جلب معلومات التمرين والتحقق من الملكية
$stmt = $pdo->prepare("
    SELECT e.*, l.title as lesson_title, l.teacher_id
    FROM exercises e
    JOIN lessons l ON e.lesson_id = l.id
    WHERE e.id = ?
");
$stmt->execute([$exercise_id]);
$exercise = $stmt->fetch();

if (!$exercise || $exercise['teacher_id'] != $user_id) {
    header('Location: exercises.php');
    exit;
}

// معالجة الحذف
if (isset($_GET['delete_question'])) {
    $pdo->prepare("DELETE FROM exercise_questions WHERE id = ? AND exercise_id = ?")
       ->execute([$_GET['delete_question'], $exercise_id]);
    header("Location: exercise-questions.php?exercise_id=$exercise_id&deleted=1");
    exit;
}

// معالجة إضافة/تعديل سؤال
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_id = $_POST['question_id'] ?? 0;
    $question_text = trim($_POST['question_text'] ?? '');
    $option_a = trim($_POST['option_a'] ?? '');
    $option_b = trim($_POST['option_b'] ?? '');
    $option_c = trim($_POST['option_c'] ?? '');
    $option_d = trim($_POST['option_d'] ?? '');
    $correct_answer = $_POST['correct_answer'] ?? '';
    
    if (empty($question_text) || empty($option_a) || empty($option_b) || empty($correct_answer)) {
        $error_message = "السؤال والخيارات أ و ب والإجابة الصحيحة مطلوبة";
    } else {
        try {
            if ($question_id > 0) {
                // تحديث
                $stmt = $pdo->prepare("
                    UPDATE exercise_questions 
                    SET question_text = ?, option_a = ?, option_b = ?, 
                        option_c = ?, option_d = ?, correct_answer = ?
                    WHERE id = ? AND exercise_id = ?
                ");
                $stmt->execute([
                    $question_text, $option_a, $option_b, $option_c, $option_d,
                    $correct_answer, $question_id, $exercise_id
                ]);
                $success_message = "تم تحديث السؤال بنجاح! ✓";
            } else {
                // إضافة
                $stmt = $pdo->prepare("
                    INSERT INTO exercise_questions 
                    (exercise_id, question_text, option_a, option_b, option_c, option_d, correct_answer)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $exercise_id, $question_text, $option_a, $option_b,
                    $option_c, $option_d, $correct_answer
                ]);
                $success_message = "تم إضافة السؤال بنجاح! ✓";
            }
        } catch (PDOException $e) {
            $error_message = "حدث خطأ: " . $e->getMessage();
        }
    }
}

// جلب الأسئلة
$stmt = $pdo->prepare("SELECT * FROM exercise_questions WHERE exercise_id = ? ORDER BY id ASC");
$stmt->execute([$exercise_id]);
$questions = $stmt->fetchAll();

// إذا كان هناك سؤال للتعديل
$editing_question = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM exercise_questions WHERE id = ? AND exercise_id = ?");
    $stmt->execute([$_GET['edit'], $exercise_id]);
    $editing_question = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأسئلة - <?php echo htmlspecialchars($exercise['title']); ?></title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #4285F4 0%, #0066cc 100%);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .form-grid {
            display: grid;
            gap: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4285F4;
        }
        
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }
        
        .radio-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4285F4, #0066cc);
            color: white;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.9rem;
        }
        
        .questions-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .question-item {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .question-number {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #4285F4;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        
        .question-text {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
        }
        
        .options-list {
            display: grid;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .option {
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }
        
        .option.correct {
            background: #d1fae5;
            border-color: #22c55e;
            font-weight: 600;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1>❓ إدارة الأسئلة</h1>
                <p><?php echo htmlspecialchars($exercise['title']); ?></p>
                <p style="opacity: 0.9; font-size: 0.9rem;">
                    الدرس: <?php echo htmlspecialchars($exercise['lesson_title']); ?>
                </p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">✓ <?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">✗ <?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">✓ تم حذف السؤال بنجاح</div>
            <?php endif; ?>
            
            <!-- Add/Edit Question Form -->
            <div class="card">
                <h3><?php echo $editing_question ? 'تعديل السؤال' : 'إضافة سؤال جديد'; ?></h3>
                
                <form method="POST">
                    <?php if ($editing_question): ?>
                        <input type="hidden" name="question_id" value="<?php echo $editing_question['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>نص السؤال *</label>
                            <textarea name="question_text" class="form-control" required><?php echo htmlspecialchars($editing_question['question_text'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>الخيار أ *</label>
                            <input type="text" name="option_a" class="form-control" 
                                   value="<?php echo htmlspecialchars($editing_question['option_a'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>الخيار ب *</label>
                            <input type="text" name="option_b" class="form-control" 
                                   value="<?php echo htmlspecialchars($editing_question['option_b'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>الخيار ج</label>
                            <input type="text" name="option_c" class="form-control" 
                                   value="<?php echo htmlspecialchars($editing_question['option_c'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>الخيار د</label>
                            <input type="text" name="option_d" class="form-control" 
                                   value="<?php echo htmlspecialchars($editing_question['option_d'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>الإجابة الصحيحة *</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="correct_answer" value="أ" 
                                           <?php echo ($editing_question['correct_answer'] ?? '') == 'أ' ? 'checked' : ''; ?> required>
                                    أ
                                </label>
                                <label>
                                    <input type="radio" name="correct_answer" value="ب" 
                                           <?php echo ($editing_question['correct_answer'] ?? '') == 'ب' ? 'checked' : ''; ?>>
                                    ب
                                </label>
                                <label>
                                    <input type="radio" name="correct_answer" value="ج" 
                                           <?php echo ($editing_question['correct_answer'] ?? '') == 'ج' ? 'checked' : ''; ?>>
                                    ج
                                </label>
                                <label>
                                    <input type="radio" name="correct_answer" value="د" 
                                           <?php echo ($editing_question['correct_answer'] ?? '') == 'د' ? 'checked' : ''; ?>>
                                    د
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editing_question ? '💾 حفظ التعديلات' : '➕ إضافة السؤال'; ?>
                        </button>
                        <?php if ($editing_question): ?>
                            <a href="exercise-questions.php?exercise_id=<?php echo $exercise_id; ?>" class="btn btn-secondary">
                                إلغاء التعديل
                            </a>
                        <?php endif; ?>
                        <a href="exercises.php" class="btn btn-secondary">← العودة للتمارين</a>
                    </div>
                </form>
            </div>
            
            <!-- Questions List -->
            <div class="card">
                <h3>الأسئلة الحالية (<?php echo count($questions); ?>)</h3>
                
                <?php if (empty($questions)): ?>
                    <div class="alert alert-info">
                        لا توجد أسئلة بعد. ابدأ بإضافة السؤال الأول!
                    </div>
                <?php else: ?>
                    <div class="questions-list">
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="question-item">
                                <div class="question-header">
                                    <div class="question-number"><?php echo $index + 1; ?></div>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="?exercise_id=<?php echo $exercise_id; ?>&edit=<?php echo $question['id']; ?>" 
                                           class="btn btn-primary btn-sm">✏️ تعديل</a>
                                        <a href="?exercise_id=<?php echo $exercise_id; ?>&delete_question=<?php echo $question['id']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('هل أنت متأكد من حذف هذا السؤال؟')">🗑️ حذف</a>
                                    </div>
                                </div>
                                
                                <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                                
                                <div class="options-list">
                                    <?php
                                    $options = [
                                        'أ' => $question['option_a'],
                                        'ب' => $question['option_b'],
                                        'ج' => $question['option_c'],
                                        'د' => $question['option_d']
                                    ];
                                    
                                    foreach ($options as $label => $text):
                                        if (empty($text)) continue;
                                        $is_correct = $question['correct_answer'] == $label;
                                    ?>
                                        <div class="option <?php echo $is_correct ? 'correct' : ''; ?>">
                                            <strong><?php echo $label; ?>.</strong> <?php echo htmlspecialchars($text); ?>
                                            <?php if ($is_correct): ?>
                                                <span style="float: left; color: #22c55e;">✓ صحيح</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
