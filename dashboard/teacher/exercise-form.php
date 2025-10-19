<?php
/**
 * Teacher Exercise Form - نموذج إضافة/تعديل التمارين
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['enseignant', 'teacher']);

global $pdo;
$user_id = $_SESSION['user_id'];

// تحديد وضع التعديل
$exercise_id = $_GET['id'] ?? 0;
$is_edit = $exercise_id > 0;
$exercise = null;

if ($is_edit) {
    // جلب بيانات التمرين
    $stmt = $pdo->prepare("
        SELECT e.* FROM exercises e
        JOIN lessons l ON e.lesson_id = l.id
        WHERE e.id = ? AND l.author_id = ?
    ");
    $stmt->execute([$exercise_id, $user_id]);
    $exercise = $stmt->fetch();
    
    if (!$exercise) {
        header('Location: exercises.php');
        exit;
    }
}

// معالجة النموذج
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim($_POST['question'] ?? '');
    $model_answer = trim($_POST['model_answer'] ?? '');
    $lesson_id = $_POST['lesson_id'] ?? '';
    $order = $_POST['order'] ?? 0;
    
    if (empty($question) || empty($lesson_id)) {
        $error_message = "السؤال والدرس مطلوبان";
    } else {
        // التحقق من ملكية الدرس
        $stmt = $pdo->prepare("SELECT id FROM lessons WHERE id = ? AND author_id = ?");
        $stmt->execute([$lesson_id, $user_id]);
        
        if (!$stmt->fetch()) {
            $error_message = "الدرس غير موجود أو لا تملك صلاحية للوصول إليه";
        } else {
            try {
                if ($is_edit) {
                    $stmt = $pdo->prepare("
                        UPDATE exercises 
                        SET question = ?, model_answer = ?, lesson_id = ?, `order` = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$question, $model_answer, $lesson_id, $order, $exercise_id]);
                    $success_message = "تم تحديث التمرين بنجاح! ✓";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO exercises (question, model_answer, lesson_id, `order`, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$question, $model_answer, $lesson_id, $order]);
                    $exercise_id = $pdo->lastInsertId();
                    $success_message = "تم إضافة التمرين بنجاح! ✓";
                }
            } catch (PDOException $e) {
                $error_message = "حدث خطأ: " . $e->getMessage();
            }
        }
    }
}

// جلب دروس المعلم
$stmt = $pdo->prepare("
    SELECT l.*, s.name as subject_name
    FROM lessons l
    JOIN subjects s ON l.subject_id = s.id
    WHERE l.author_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$user_id]);
$lessons = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'تعديل التمرين' : 'إضافة تمرين جديد'; ?> - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .form-header {
            background: linear-gradient(135deg, #4285F4 0%, #0066cc 100%);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(66, 133, 244, 0.3);
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .required { color: #ef4444; }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4285F4;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4285F4, #0066cc);
            color: white;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="form-header">
                <h1><?php echo $is_edit ? '✏️ تعديل التمرين' : '➕ إضافة تمرين جديد'; ?></h1>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">✓ <?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">✗ <?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="form-card">
                <form method="POST">
                    <div class="form-group">
                        <label><span class="required">*</span> السؤال / التمرين</label>
                        <textarea name="question" class="form-control" required><?php echo htmlspecialchars($exercise['question'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><span class="required">*</span> الإجابة النموذجية</label>
                        <textarea name="model_answer" class="form-control" required><?php echo htmlspecialchars($exercise['model_answer'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><span class="required">*</span> الدرس</label>
                        <select name="lesson_id" class="form-control" required>
                            <option value="">-- اختر الدرس --</option>
                            <?php foreach ($lessons as $lesson): ?>
                                <option value="<?php echo $lesson['id']; ?>"
                                        <?php echo ($exercise['lesson_id'] ?? '') == $lesson['id'] ? 'selected' : ''; ?>>
                                    📚 <?php echo htmlspecialchars($lesson['title']) . ' - ' . htmlspecialchars($lesson['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>الترتيب (اختياري)</label>
                        <input type="number" name="order" class="form-control" 
                               value="<?php echo htmlspecialchars($exercise['order'] ?? '0'); ?>" min="0">
                        <small style="color: #6b7280;">رقم الترتيب لعرض التمارين بترتيب معين</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $is_edit ? '💾 حفظ التعديلات' : '➕ إضافة التمرين'; ?>
                        </button>
                        <a href="exercises.php" class="btn btn-secondary">← إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
