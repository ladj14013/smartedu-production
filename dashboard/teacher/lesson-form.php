<?php
/**
 * Teacher Lesson Form - نموذج إضافة/تعديل الدروس
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['teacher']);

$user_id = $_SESSION['user_id'];

// جلب معلومات المعلم
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

// تحديد وضع التعديل
$lesson_id = $_GET['id'] ?? 0;
$is_edit = $lesson_id > 0;
$lesson = null;

if ($is_edit) {
    // جلب بيانات الدرس للتعديل
    $stmt = $pdo->prepare("
        SELECT * FROM lessons 
        WHERE id = ? AND teacher_id = ?
    ");
    $stmt->execute([$lesson_id, $user_id]);
    $lesson = $stmt->fetch();
    
    if (!$lesson) {
        header('Location: lessons.php');
        exit;
    }
}

// معالجة النموذج
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subject_id = $_POST['subject_id'] ?? '';
    $stage_id = $_POST['stage_id'] ?? null;
    $level_id = $_POST['level_id'] ?? null;
    $video_url = trim($_POST['video_url'] ?? '');
    
    // التحقق من البيانات
    if (empty($title) || empty($subject_id)) {
        $error_message = "العنوان والمادة مطلوبان";
    } else {
        try {
            if ($is_edit) {
                // تحديث الدرس
                $stmt = $pdo->prepare("
                    UPDATE lessons 
                    SET title = ?, description = ?, subject_id = ?, 
                        stage_id = ?, level_id = ?, video_url = ?,
                        updated_at = NOW()
                    WHERE id = ? AND teacher_id = ?
                ");
                $stmt->execute([
                    $title, $description, $subject_id,
                    $stage_id, $level_id, $video_url,
                    $lesson_id, $user_id
                ]);
                
                $success_message = "تم تحديث الدرس بنجاح! ✓";
            } else {
                // إضافة درس جديد
                $stmt = $pdo->prepare("
                    INSERT INTO lessons (title, description, subject_id, stage_id, level_id, 
                                       video_url, teacher_id, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $title, $description, $subject_id,
                    $stage_id, $level_id, $video_url, $user_id
                ]);
                
                $lesson_id = $pdo->lastInsertId();
                $success_message = "تم إضافة الدرس بنجاح! في انتظار موافقة المشرف ⏳";
                
                // إعادة التوجيه بعد 2 ثانية
                header("refresh:2;url=lessons.php");
            }
        } catch (PDOException $e) {
            $error_message = "حدث خطأ: " . $e->getMessage();
        }
    }
}

// جلب المواد
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY name");
$subjects = $stmt->fetchAll();

// جلب المراحل
$stmt = $pdo->query("SELECT * FROM stages ORDER BY name");
$stages = $stmt->fetchAll();

// جلب المستويات
$stmt = $pdo->query("SELECT * FROM levels ORDER BY name");
$levels = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'تعديل الدرس' : 'إضافة درس جديد'; ?> - SmartEdu</title>
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
        
        .form-header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        
        .breadcrumb {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        
        .breadcrumb a {
            color: white;
            text-decoration: none;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .form-card h3 {
            font-size: 1.3rem;
            color: #1f2937;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }
        
        .form-group label .required {
            color: #ef4444;
            margin-right: 4px;
        }
        
        .form-control {
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
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
            line-height: 1.6;
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        .form-help {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 4px;
        }
        
        .video-preview {
            margin-top: 15px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            border: 2px dashed #d1d5db;
        }
        
        .video-preview iframe {
            width: 100%;
            height: 300px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            align-items: center;
            padding-top: 25px;
            border-top: 2px solid #e5e7eb;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4285F4, #0066cc);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 133, 244, 0.3);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .status-info {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .status-info h4 {
            color: #92400e;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
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
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <!-- Form Header -->
            <div class="form-header">
                <div class="breadcrumb">
                    <a href="index.php">الرئيسية</a> / 
                    <a href="lessons.php">الدروس</a> / 
                    <?php echo $is_edit ? 'تعديل الدرس' : 'إضافة درس جديد'; ?>
                </div>
                
                <h1><?php echo $is_edit ? '✏️ تعديل الدرس' : '➕ إضافة درس جديد'; ?></h1>
                <p><?php echo $is_edit ? 'قم بتحديث معلومات الدرس' : 'أنشئ درساً جديداً لطلابك'; ?></p>
            </div>
            
            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <span style="font-size: 1.5rem;">✓</span>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <span style="font-size: 1.5rem;">✗</span>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Status Info (for edit mode) -->
            <?php if ($is_edit && $lesson): ?>
                <div class="status-info">
                    <h4>📊 حالة الدرس الحالية:</h4>
                    <span class="status-badge status-<?php echo $lesson['status']; ?>">
                        <?php
                        $status_text = [
                            'pending' => '⏳ في انتظار الموافقة',
                            'approved' => '✓ تمت الموافقة',
                            'rejected' => '✗ مرفوض'
                        ];
                        echo $status_text[$lesson['status']] ?? $lesson['status'];
                        ?>
                    </span>
                    <p style="color: #92400e; margin-top: 10px; font-size: 0.9rem;">
                        <?php if ($lesson['status'] == 'pending'): ?>
                            الدرس في انتظار مراجعة المشرف
                        <?php elseif ($lesson['status'] == 'approved'): ?>
                            الدرس منشور ومتاح للطلاب
                        <?php else: ?>
                            تم رفض الدرس من قبل المشرف
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Form -->
            <form method="POST" id="lessonForm">
                <!-- Basic Information -->
                <div class="form-card">
                    <h3>📝 المعلومات الأساسية</h3>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>
                                <span class="required">*</span>
                                عنوان الدرس
                            </label>
                            <input type="text" 
                                   name="title" 
                                   class="form-control" 
                                   placeholder="مثال: مقدمة في الرياضيات"
                                   value="<?php echo htmlspecialchars($lesson['title'] ?? ''); ?>"
                                   required>
                            <div class="form-help">اختر عنواناً واضحاً وجذاباً للدرس</div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>وصف الدرس</label>
                            <textarea name="description" 
                                      class="form-control" 
                                      placeholder="اكتب وصفاً تفصيلياً عن محتوى الدرس وأهدافه..."><?php echo htmlspecialchars($lesson['description'] ?? ''); ?></textarea>
                            <div class="form-help">وصف مفصل يساعد الطلاب على فهم محتوى الدرس</div>
                        </div>
                    </div>
                </div>
                
                <!-- Classification -->
                <div class="form-card">
                    <h3>🏷️ التصنيف والفئة</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                <span class="required">*</span>
                                المادة الدراسية
                            </label>
                            <select name="subject_id" class="form-control" required>
                                <option value="">-- اختر المادة --</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"
                                            <?php echo ($lesson['subject_id'] ?? '') == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo $subject['icon'] . ' ' . htmlspecialchars($subject['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>المرحلة الدراسية</label>
                            <select name="stage_id" class="form-control">
                                <option value="">-- جميع المراحل --</option>
                                <?php foreach ($stages as $stage): ?>
                                    <option value="<?php echo $stage['id']; ?>"
                                            <?php echo ($lesson['stage_id'] ?? '') == $stage['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stage['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-help">اترك فارغاً للجميع</div>
                        </div>
                        
                        <div class="form-group">
                            <label>المستوى الدراسي</label>
                            <select name="level_id" class="form-control">
                                <option value="">-- جميع المستويات --</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo $level['id']; ?>"
                                            <?php echo ($lesson['level_id'] ?? '') == $level['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($level['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-help">اترك فارغاً للجميع</div>
                        </div>
                    </div>
                </div>
                
                <!-- Video Content -->
                <div class="form-card">
                    <h3>🎥 محتوى الفيديو</h3>
                    
                    <div class="form-group">
                        <label>رابط الفيديو (YouTube أو Vimeo)</label>
                        <input type="url" 
                               name="video_url" 
                               id="videoUrl"
                               class="form-control" 
                               placeholder="https://www.youtube.com/watch?v=..."
                               value="<?php echo htmlspecialchars($lesson['video_url'] ?? ''); ?>">
                        <div class="form-help">
                            يمكنك لصق رابط YouTube مباشرة، مثال: https://www.youtube.com/watch?v=dQw4w9WgXcQ
                        </div>
                        
                        <!-- Video Preview -->
                        <div id="videoPreview" class="video-preview" style="display: none;">
                            <strong>معاينة الفيديو:</strong>
                            <iframe id="videoFrame" frameborder="0" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-card">
                    <div class="form-actions">
                        <a href="lessons.php" class="btn btn-secondary">
                            ← إلغاء والعودة
                        </a>
                        
                        <button type="submit" class="btn btn-primary">
                            <?php echo $is_edit ? '💾 حفظ التعديلات' : '➕ إضافة الدرس'; ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Video preview
        const videoUrlInput = document.getElementById('videoUrl');
        const videoPreview = document.getElementById('videoPreview');
        const videoFrame = document.getElementById('videoFrame');
        
        function updateVideoPreview() {
            const url = videoUrlInput.value.trim();
            
            if (!url) {
                videoPreview.style.display = 'none';
                return;
            }
            
            // Extract YouTube video ID
            let videoId = '';
            const patterns = [
                /(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/,
                /youtube\.com\/embed\/([a-zA-Z0-9_-]+)/
            ];
            
            for (const pattern of patterns) {
                const match = url.match(pattern);
                if (match && match[1]) {
                    videoId = match[1];
                    break;
                }
            }
            
            if (videoId) {
                videoFrame.src = `https://www.youtube.com/embed/${videoId}`;
                videoPreview.style.display = 'block';
            } else {
                videoPreview.style.display = 'none';
            }
        }
        
        videoUrlInput.addEventListener('input', updateVideoPreview);
        videoUrlInput.addEventListener('blur', updateVideoPreview);
        
        // Initial preview if editing
        <?php if ($is_edit && !empty($lesson['video_url'])): ?>
            updateVideoPreview();
        <?php endif; ?>
        
        // Form validation
        document.getElementById('lessonForm').addEventListener('submit', function(e) {
            const title = document.querySelector('[name="title"]').value.trim();
            const subject = document.querySelector('[name="subject_id"]').value;
            
            if (!title || !subject) {
                e.preventDefault();
                alert('يرجى ملء جميع الحقول المطلوبة (*)');
                return false;
            }
        });
    </script>
</body>
</html>
