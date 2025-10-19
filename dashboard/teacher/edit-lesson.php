<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['enseignant', 'teacher']);

global $pdo;
$teacher_id = $_SESSION['user_id'];
$lesson_id = $_GET['id'] ?? null;

if (!$lesson_id) {
    header("Location: manage-lessons.php");
    exit();
}

// التحقق من ملكية الدرس
$query = "SELECT * FROM lessons WHERE id = :lesson_id AND author_id = :teacher_id";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':lesson_id', $lesson_id);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$lesson = $stmt->fetch();

if (!$lesson) {
    header("Location: manage-lessons.php");
    exit();
}

// جلب التمارين
$query = "SELECT * FROM exercises WHERE lesson_id = :lesson_id ORDER BY `order`";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':lesson_id', $lesson_id);
$stmt->execute();
$exercises = $stmt->fetchAll();

// تعديل الدرس
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lesson'])) {
    $title = sanitize_input($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $video_url = sanitize_input($_POST['video_url'] ?? '');
    $pdf_url = sanitize_input($_POST['pdf_url'] ?? '');
    $type = $_POST['type'] ?? 'public';
    
    if (!empty($title) && !empty($content)) {
        $query = "UPDATE lessons SET title = :title, content = :content, video_url = :video_url, 
                  pdf_url = :pdf_url, type = :type WHERE id = :lesson_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':video_url', $video_url);
        $stmt->bindParam(':pdf_url', $pdf_url);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':lesson_id', $lesson_id);
        $stmt->execute();
        
        set_flash_message('success', 'تم تحديث الدرس بنجاح.');
        header("Location: edit-lesson.php?id=" . $lesson_id);
        exit();
    }
}

// إضافة تمرين
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exercise'])) {
    $question = trim($_POST['question'] ?? '');
    $model_answer = trim($_POST['model_answer'] ?? '');
    
    if (!empty($question) && !empty($model_answer)) {
        // الحصول على أعلى ترتيب
        $order_stmt = $pdo->prepare("SELECT COALESCE(MAX(`order`), 0) + 1 as next_order FROM exercises WHERE lesson_id = ?");
        $order_stmt->execute([$lesson_id]);
        $next_order = $order_stmt->fetchColumn();
        
        $query = "INSERT INTO exercises (lesson_id, question, model_answer, `order`) 
                  VALUES (:lesson_id, :question, :model_answer, :order_val)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':lesson_id', $lesson_id);
        $stmt->bindParam(':question', $question);
        $stmt->bindParam(':model_answer', $model_answer);
        $stmt->bindParam(':order_val', $next_order);
        $stmt->execute();
        
        set_flash_message('success', 'تم إضافة التمرين بنجاح.');
        header("Location: edit-lesson.php?id=" . $lesson_id);
        exit();
    }
}

// حذف تمرين
if (isset($_GET['delete_exercise'])) {
    $exercise_id = $_GET['delete_exercise'];
    $query = "DELETE FROM exercises WHERE id = :exercise_id AND lesson_id = :lesson_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':exercise_id', $exercise_id);
    $stmt->bindParam(':lesson_id', $lesson_id);
    $stmt->execute();
    
    set_flash_message('success', 'تم حذف التمرين بنجاح.');
    header("Location: edit-lesson.php?id=" . $lesson_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الدرس - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .edit-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(76, 175, 80, 0.3);
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .card h3 {
            color: #1f2937;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
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
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
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
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
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
        
        .exercise-item {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #4CAF50;
        }
        
        .exercise-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .exercise-question {
            font-weight: 600;
            color: #1f2937;
            flex: 1;
        }
        
        .exercise-answer {
            color: #6b7280;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="edit-header">
                <h1>✏️ تعديل الدرس</h1>
                <p>قم بتحديث معلومات الدرس والتمارين</p>
            </div>
            
            <div style="margin-bottom: 25px;">
                <a href="manage-lessons.php" class="btn btn-secondary">← العودة للدروس</a>
            </div>
            
            <?php
            $flash = get_flash_message();
            if ($flash):
            ?>
                <div class="alert alert-success">
                    ✓ <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- تعديل الدرس -->
            <div class="card">
                <h3>📝 معلومات الدرس</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="title">عنوان الدرس <span style="color: #ef4444;">*</span></label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title"
                            class="form-control"
                            value="<?php echo htmlspecialchars($lesson['title']); ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="content">محتوى الدرس <span style="color: #ef4444;">*</span></label>
                        <textarea 
                            id="content" 
                            name="content"
                            class="form-control"
                            required
                        ><?php echo htmlspecialchars($lesson['content']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="video_url">رابط الفيديو (اختياري)</label>
                        <input 
                            type="url" 
                            id="video_url" 
                            name="video_url"
                            class="form-control"
                            value="<?php echo htmlspecialchars($lesson['video_url'] ?? ''); ?>"
                            placeholder="https://youtube.com/watch?v=..."
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="pdf_url">رابط ملف PDF (اختياري)</label>
                        <input 
                            type="url" 
                            id="pdf_url" 
                            name="pdf_url"
                            class="form-control"
                            value="<?php echo htmlspecialchars($lesson['pdf_url'] ?? ''); ?>"
                            placeholder="https://example.com/file.pdf"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="type">نوع الدرس</label>
                        <select id="type" name="type" class="form-control">
                            <option value="public" <?php echo $lesson['type'] == 'public' ? 'selected' : ''; ?>>
                                🌐 عام (يحتاج موافقة المشرف)
                            </option>
                            <option value="private" <?php echo $lesson['type'] == 'private' ? 'selected' : ''; ?>>
                                🔒 خاص (للطلاب المرتبطين فقط)
                            </option>
                        </select>
                    </div>
                    
                    <button type="submit" name="update_lesson" class="btn btn-primary">
                        💾 حفظ التعديلات
                    </button>
                </form>
            </div>
            
            <!-- التمارين -->
            <div class="card">
                <h3>✍️ التمارين (<?php echo count($exercises); ?>)</h3>
                
                <?php if (!empty($exercises)): ?>
                    <div style="margin-bottom: 25px;">
                        <?php foreach ($exercises as $index => $exercise): ?>
                            <div class="exercise-item">
                                <div class="exercise-header">
                                    <div style="flex: 1;">
                                        <div class="exercise-question">
                                            <?php echo ($index + 1) . '. ' . htmlspecialchars($exercise['question']); ?>
                                        </div>
                                        <div class="exercise-answer">
                                            <strong>الإجابة النموذجية:</strong> <?php echo htmlspecialchars($exercise['model_answer']); ?>
                                        </div>
                                    </div>
                                    <a href="?id=<?php echo $lesson_id; ?>&delete_exercise=<?php echo $exercise['id']; ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('هل أنت متأكد من حذف هذا التمرين؟')"
                                       style="padding: 8px 16px; font-size: 0.9rem;">
                                        🗑️ حذف
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div style="padding: 25px; background: #f0f7ff; border-radius: 12px;">
                    <h3 style="margin: 0 0 20px 0;">➕ إضافة تمرين جديد</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="question">السؤال <span style="color: #ef4444;">*</span></label>
                            <textarea 
                                id="question" 
                                name="question"
                                class="form-control"
                                rows="4" 
                                placeholder="اكتب السؤال هنا..."
                                required
                            ></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="model_answer">الإجابة النموذجية <span style="color: #ef4444;">*</span></label>
                            <textarea 
                                id="model_answer" 
                                name="model_answer"
                                class="form-control"
                                rows="4" 
                                placeholder="اكتب الإجابة النموذجية هنا..."
                                required
                            ></textarea>
                        </div>
                        
                        <button type="submit" name="add_exercise" class="btn btn-primary">
                            ➕ إضافة التمرين
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
