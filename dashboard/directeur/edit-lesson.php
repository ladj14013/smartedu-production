<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('directeur');

$database = new Database();
$db = $database->getConnection();

$lesson_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

if ($lesson_id === 0) {
    header('Location: content.php');
    exit();
}

// جلب معلومات الدرس
$query = "SELECT l.*, s.name as subject_name, u.name as author_name 
          FROM lessons l 
          JOIN subjects s ON l.subject_id = s.id 
          JOIN users u ON l.author_id = u.id 
          WHERE l.id = :lesson_id";
$stmt = $db->prepare($query);
$stmt->execute([':lesson_id' => $lesson_id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: content.php');
    exit();
}

// جلب التمارين
$query = "SELECT * FROM exercises WHERE lesson_id = :lesson_id ORDER BY order_num";
$stmt = $db->prepare($query);
$stmt->execute([':lesson_id' => $lesson_id]);
$exercises = $stmt->fetchAll();

// معالجة تحديث الدرس
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title']);
    $content = $_POST['content']; // لا نستخدم sanitize للمحتوى الطويل
    $video_url = sanitize_input($_POST['video_url']);
    $is_locked = isset($_POST['is_locked']) ? 1 : 0;
    
    try {
        // تحديث الدرس
        $query = "UPDATE lessons SET 
                  title = :title, 
                  content = :content, 
                  video_url = :video_url, 
                  is_locked = :is_locked,
                  updated_at = NOW()
                  WHERE id = :lesson_id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':title' => $title,
            ':content' => $content,
            ':video_url' => $video_url,
            ':is_locked' => $is_locked,
            ':lesson_id' => $lesson_id
        ]);
        
        // حذف التمارين القديمة
        $db->prepare("DELETE FROM exercises WHERE lesson_id = ?")->execute([$lesson_id]);
        
        // إضافة التمارين الجديدة
        if (isset($_POST['exercises']) && is_array($_POST['exercises'])) {
            $order = 1;
            foreach ($_POST['exercises'] as $exercise) {
                if (!empty($exercise['question'])) {
                    $query = "INSERT INTO exercises (lesson_id, question, model_answer, order_num) 
                              VALUES (:lesson_id, :question, :model_answer, :order_num)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        ':lesson_id' => $lesson_id,
                        ':question' => $exercise['question'],
                        ':model_answer' => $exercise['model_answer'] ?? '',
                        ':order_num' => $order
                    ]);
                    $order++;
                }
            }
        }
        
        $success = 'تم حفظ التغييرات بنجاح';
        
        // إعادة جلب البيانات المحدثة
        $stmt = $db->prepare("SELECT * FROM lessons WHERE id = ?");
        $stmt->execute([$lesson_id]);
        $lesson = $stmt->fetch();
        
        $stmt = $db->prepare("SELECT * FROM exercises WHERE lesson_id = ? ORDER BY order_num");
        $stmt->execute([$lesson_id]);
        $exercises = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = 'حدث خطأ في الحفظ: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الدرس: <?php echo htmlspecialchars($lesson['title']); ?> - Smart Education Hub</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .breadcrumb a {
            color: #4285F4;
            text-decoration: none;
        }
        
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e5e7eb;
        }
        
        .form-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-group textarea {
            min-height: 200px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4285F4;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }
        
        .switch-group {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 26px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #4285F4;
        }
        
        input:checked + .slider:before {
            transform: translateX(24px);
        }
        
        .exercises-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .exercise-item {
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
        }
        
        .exercise-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .exercise-number {
            font-weight: 600;
            color: #4285F4;
        }
        
        .btn-remove-exercise {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .btn-remove-exercise:hover {
            background: #dc2626;
        }
        
        .btn-add-exercise {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-add-exercise:hover {
            background: #059669;
        }
        
        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            box-shadow: 0 -4px 6px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="content.php">إدارة المحتوى</a>
                <span>›</span>
                <a href="subject-content.php?id=<?php echo $lesson['subject_id']; ?>">
                    <?php echo htmlspecialchars($lesson['subject_name']); ?>
                </a>
                <span>›</span>
                <strong>تعديل الدرس</strong>
            </div>
            
            <div class="page-header">
                <div>
                    <h1>✏️ تعديل محتوى الدرس</h1>
                    <p>تعديل وإدارة محتوى الدرس والتمارين</p>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <!-- معلومات الدرس الأساسية -->
                <div class="form-section">
                    <h2 class="form-section-title">
                        <span>📝</span>
                        معلومات الدرس الأساسية
                    </h2>
                    
                    <div class="form-group">
                        <label>عنوان الدرس *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>محتوى الدرس *</label>
                        <textarea name="content" required><?php echo htmlspecialchars($lesson['content']); ?></textarea>
                        <small style="color: #6b7280; margin-top: 0.5rem; display: block;">
                            يمكنك استخدام فقرات وسطور متعددة لتنسيق المحتوى
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>رابط الفيديو (اختياري)</label>
                        <input type="url" name="video_url" value="<?php echo htmlspecialchars($lesson['video_url'] ?? ''); ?>" 
                               placeholder="https://www.youtube.com/watch?v=...">
                        <small style="color: #6b7280; margin-top: 0.5rem; display: block;">
                            أدخل رابط فيديو YouTube أو أي منصة أخرى
                        </small>
                    </div>
                    
                    <div class="switch-group">
                        <label class="switch">
                            <input type="checkbox" name="is_locked" <?php echo $lesson['is_locked'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <div>
                            <strong>قفل الدرس</strong>
                            <p style="margin: 0; font-size: 0.875rem; color: #6b7280;">
                                عند تفعيل هذا الخيار، لن يتمكن الطلاب من الوصول إلى هذا الدرس
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- التمارين -->
                <div class="form-section">
                    <h2 class="form-section-title">
                        <span>✍️</span>
                        التمارين
                    </h2>
                    
                    <div id="exercises-container" class="exercises-list">
                        <?php if (!empty($exercises)): ?>
                            <?php foreach ($exercises as $index => $exercise): ?>
                                <div class="exercise-item">
                                    <div class="exercise-header">
                                        <span class="exercise-number">تمرين رقم <?php echo $index + 1; ?></span>
                                        <button type="button" class="btn-remove-exercise" onclick="removeExercise(this)">
                                            🗑️ حذف
                                        </button>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>السؤال *</label>
                                        <textarea name="exercises[<?php echo $index; ?>][question]" rows="3" required><?php echo htmlspecialchars($exercise['question']); ?></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>الإجابة النموذجية *</label>
                                        <textarea name="exercises[<?php echo $index; ?>][model_answer]" rows="3" required><?php echo htmlspecialchars($exercise['model_answer']); ?></textarea>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #6b7280; padding: 2rem;">
                                لا توجد تمارين. انقر على "إضافة تمرين" لإضافة أسئلة جديدة.
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" class="btn-add-exercise" onclick="addExercise()">
                        ➕ إضافة تمرين جديد
                    </button>
                </div>
                
                <!-- أزرار الحفظ -->
                <div class="sticky-actions">
                    <a href="subject-content.php?id=<?php echo $lesson['subject_id']; ?>" class="btn btn-outline">
                        إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        💾 حفظ كل التغييرات
                    </button>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        let exerciseCount = <?php echo count($exercises); ?>;
        
        function addExercise() {
            exerciseCount++;
            const container = document.getElementById('exercises-container');
            
            const exerciseHtml = `
                <div class="exercise-item">
                    <div class="exercise-header">
                        <span class="exercise-number">تمرين رقم ${exerciseCount}</span>
                        <button type="button" class="btn-remove-exercise" onclick="removeExercise(this)">
                            🗑️ حذف
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label>السؤال *</label>
                        <textarea name="exercises[${exerciseCount - 1}][question]" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>الإجابة النموذجية *</label>
                        <textarea name="exercises[${exerciseCount - 1}][model_answer]" rows="3" required></textarea>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', exerciseHtml);
        }
        
        function removeExercise(button) {
            if (confirm('هل أنت متأكد من حذف هذا التمرين؟')) {
                const exerciseItem = button.closest('.exercise-item');
                exerciseItem.remove();
                updateExerciseNumbers();
            }
        }
        
        function updateExerciseNumbers() {
            const exercises = document.querySelectorAll('.exercise-item');
            exercises.forEach((exercise, index) => {
                const numberSpan = exercise.querySelector('.exercise-number');
                numberSpan.textContent = `تمرين رقم ${index + 1}`;
            });
        }
        
        // تأكيد قبل مغادرة الصفحة إذا كانت هناك تغييرات
        let formChanged = false;
        document.querySelector('form').addEventListener('change', function() {
            formChanged = true;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        document.querySelector('form').addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>
