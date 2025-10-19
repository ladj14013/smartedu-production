<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/auth.php';
require_once '../../config/database.php';

// التحقق من تسجيل الدخول والصلاحيات
require_auth();
if (!has_any_role(['superviseur_matiere', 'supervisor_subject', 'subject_supervisor'])) {
    header("Location: ../../dashboard/index.php");
    exit();
}

global $pdo;
$lesson_id = $_GET['id'] ?? 0;
$subject_id = $_SESSION['subject_id'] ?? null;
$supervisor_id = $_SESSION['user_id'];

if (!$subject_id) {
    die('خطأ: لم يتم تعيين مادة لهذا المشرف');
}

// جلب بيانات الدرس
$query = "SELECT l.*, CONCAT(u.nom, ' ', u.prenom) as teacher_name, u.email as teacher_email,
          s.name as subject_name, st.name as stage_name
          FROM lessons l
          JOIN users u ON l.author_id = u.id
          JOIN subjects s ON l.subject_id = s.id
          LEFT JOIN stages st ON s.stage_id = st.id
          WHERE l.id = :lesson_id AND l.subject_id = :subject_id";
$stmt = $pdo->prepare($query);
$stmt->execute([':lesson_id' => $lesson_id, ':subject_id' => $subject_id]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    header('Location: pending-lessons.php?error=not_found');
    exit();
}

// جلب التمارين
$exercises_query = "SELECT * FROM exercises WHERE lesson_id = :lesson_id ORDER BY `order`";
$stmt = $pdo->prepare($exercises_query);
$stmt->execute([':lesson_id' => $lesson_id]);
$exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

// معالجة الموافقة/الرفض
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notes = trim($_POST['supervisor_notes'] ?? '');
    
    if ($action === 'approve') {
        try {
            $update = "UPDATE lessons 
                       SET status = 'approved', 
                           supervisor_notes = :notes,
                           updated_at = NOW()
                       WHERE id = :lesson_id";
            $stmt = $pdo->prepare($update);
            $stmt->execute([
                ':notes' => $notes,
                ':lesson_id' => $lesson_id
            ]);
            
            header('Location: pending-lessons.php?success=approved');
            exit();
        } catch (PDOException $e) {
            $error = 'حدث خطأ أثناء الموافقة على الدرس: ' . $e->getMessage();
        }
        
    } elseif ($action === 'reject') {
        if (empty($notes)) {
            $error = 'يجب كتابة سبب الرفض';
        } else {
            try {
                $update = "UPDATE lessons 
                           SET status = 'rejected', 
                               supervisor_notes = :notes,
                               updated_at = NOW()
                           WHERE id = :lesson_id";
                $stmt = $pdo->prepare($update);
                $stmt->execute([
                    ':notes' => $notes,
                    ':lesson_id' => $lesson_id
                ]);
                
                header('Location: pending-lessons.php?success=rejected');
                exit();
            } catch (PDOException $e) {
                $error = 'حدث خطأ أثناء رفض الدرس: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مراجعة الدرس - <?php echo htmlspecialchars($lesson['title']); ?></title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/rtl-sidebar.css">
    <style>
        body {
            direction: rtl;
            text-align: right;
        }
        
        .main-content {
            margin-right: 300px !important;
            margin-left: 0 !important;
            padding: 40px;
            background: #f5f5f5;
            min-height: 100vh;
            width: auto !important;
            box-sizing: border-box;
        }

        .review-header {
            background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(156, 39, 176, 0.3);
        }

        .review-header h1 {
            margin: 0 0 15px 0;
            font-size: 28px;
        }

        .header-meta {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            opacity: 0.95;
        }

        .header-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            background: #FFF3CD;
            color: #856404;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }

        .content-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #9C27B0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-right: 4px solid #9C27B0;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-value {
            color: #333;
            font-size: 16px;
        }

        .lesson-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            line-height: 1.8;
            color: #333;
            white-space: pre-wrap;
            margin-bottom: 20px;
        }

        .media-preview {
            margin-top: 15px;
        }

        .media-link {
            display: inline-block;
            padding: 12px 20px;
            background: #9C27B0;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-left: 10px;
            transition: all 0.3s ease;
        }

        .media-link:hover {
            background: #7B1FA2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(156, 39, 176, 0.3);
        }

        .video-preview {
            margin-top: 15px;
            max-width: 100%;
        }

        .video-preview iframe {
            width: 100%;
            height: 400px;
            border-radius: 8px;
        }

        .exercises-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .exercise-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-right: 4px solid #4CAF50;
        }

        .exercise-number {
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .exercise-question {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .exercise-answer {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 6px;
            border-right: 3px solid #4CAF50;
        }

        .exercise-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .decision-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-top: 30px;
        }

        .decision-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }

        .form-group textarea {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            min-height: 120px;
            font-size: 14px;
            font-family: 'Cairo', sans-serif;
            transition: all 0.3s ease;
            resize: vertical;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #9C27B0;
            box-shadow: 0 0 0 3px rgba(156, 39, 176, 0.1);
        }

        .decision-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-large {
            flex: 1;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-approve {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }

        .btn-approve:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }

        .btn-reject {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }

        .btn-reject:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(244, 67, 54, 0.3);
        }

        .btn-back {
            background: #f5f5f5;
            color: #666;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #e0e0e0;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .warning-text {
            background: #fff3cd;
            color: #856404;
            padding: 12px 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 14px;
        }

        .empty-exercises {
            text-align: center;
            padding: 40px;
            color: #999;
            background: #f8f9fa;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <a href="pending-lessons.php" class="btn-back">← العودة للدروس المعلقة</a>

        <div class="review-header">
            <span class="status-badge">⏳ معلق - يحتاج مراجعة</span>
            <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
            <div class="header-meta">
                <div class="header-meta-item">
                    <span>👨‍🏫</span>
                    <strong>الأستاذ:</strong> <?php echo htmlspecialchars($lesson['teacher_name']); ?>
                </div>
                <div class="header-meta-item">
                    <span>📚</span>
                    <strong>المادة:</strong> <?php echo htmlspecialchars($lesson['subject_name']); ?>
                </div>
                <div class="header-meta-item">
                    <span>📅</span>
                    <strong>تاريخ الإنشاء:</strong> <?php echo date('Y/m/d - H:i', strtotime($lesson['created_at'])); ?>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                ⚠️ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- معلومات الدرس -->
        <div class="content-section">
            <h2 class="section-title">📋 معلومات الدرس</h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">📚 نوع الدرس</div>
                    <div class="info-value"><?php echo htmlspecialchars($lesson['lesson_type'] ?? 'غير محدد'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">📧 بريد الأستاذ</div>
                    <div class="info-value"><?php echo htmlspecialchars($lesson['teacher_email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">✍️ عدد التمارين</div>
                    <div class="info-value"><?php echo count($exercises); ?> تمرين</div>
                </div>
                <div class="info-item">
                    <div class="info-label">📊 المرحلة</div>
                    <div class="info-value"><?php echo htmlspecialchars($lesson['stage_name'] ?? 'غير محدد'); ?></div>
                </div>
            </div>
        </div>

        <!-- محتوى الدرس -->
        <div class="content-section">
            <h2 class="section-title">📖 محتوى الدرس</h2>
            <?php if (!empty($lesson['content'])): ?>
                <div class="lesson-content">
                    <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                </div>
            <?php else: ?>
                <div class="lesson-content" style="text-align: center; color: #999;">
                    لا يوجد محتوى نصي للدرس
                </div>
            <?php endif; ?>

            <!-- الفيديو -->
            <?php if (!empty($lesson['video_url'])): ?>
                <div class="media-preview">
                    <div class="info-label">🎥 رابط الفيديو:</div>
                    <a href="<?php echo htmlspecialchars($lesson['video_url']); ?>" 
                       target="_blank" class="media-link">
                        🎥 فتح الفيديو
                    </a>
                    
                    <?php 
                    // معاينة YouTube إذا كان الرابط من يوتيوب
                    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $lesson['video_url'], $matches)) {
                        $video_id = $matches[1];
                        echo '<div class="video-preview">';
                        echo '<iframe src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen></iframe>';
                        echo '</div>';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- PDF -->
            <?php if (!empty($lesson['pdf_url'])): ?>
                <div class="media-preview">
                    <div class="info-label">📄 رابط PDF:</div>
                    <a href="<?php echo htmlspecialchars($lesson['pdf_url']); ?>" 
                       target="_blank" class="media-link">
                        📄 فتح PDF
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- التمارين -->
        <div class="content-section">
            <h2 class="section-title">✍️ التمارين المرفقة (<?php echo count($exercises); ?>)</h2>
            
            <?php if (count($exercises) > 0): ?>
                <div class="exercises-list">
                    <?php foreach ($exercises as $index => $exercise): ?>
                        <div class="exercise-card">
                            <div class="exercise-number">
                                📝 تمرين رقم <?php echo $index + 1; ?>
                            </div>
                            
                            <div class="exercise-label">❓ السؤال:</div>
                            <div class="exercise-question">
                                <?php echo nl2br(htmlspecialchars($exercise['question'])); ?>
                            </div>
                            
                            <div class="exercise-label">✅ الإجابة النموذجية:</div>
                            <div class="exercise-answer">
                                <?php echo nl2br(htmlspecialchars($exercise['model_answer'])); ?>
                            </div>
                            
                            <?php if (!empty($exercise['pdf_url'])): ?>
                                <div style="margin-top: 15px;">
                                    <a href="<?php echo htmlspecialchars($exercise['pdf_url']); ?>" 
                                       target="_blank" 
                                       style="color: #9C27B0; text-decoration: none; font-weight: 600;">
                                        📄 ملف PDF مرفق
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-exercises">
                    <p style="font-size: 16px; margin-bottom: 5px;">لا توجد تمارين مرفقة مع هذا الدرس</p>
                    <p style="font-size: 14px; color: #999;">يمكنك الموافقة على الدرس حتى بدون تمارين</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- قسم اتخاذ القرار -->
        <div class="decision-section">
            <h2 class="section-title">⚖️ اتخاذ القرار</h2>
            
            <form method="POST" class="decision-form" id="reviewForm">
                <div class="form-group">
                    <label for="supervisor_notes">📝 ملاحظاتك (اختياري عند الموافقة، إجباري عند الرفض):</label>
                    <textarea name="supervisor_notes" id="supervisor_notes" 
                              placeholder="اكتب ملاحظاتك هنا... (مثال: الدرس واضح ومنظم، التمارين مناسبة، يُنصح بإضافة المزيد من الأمثلة...)"></textarea>
                </div>

                <div class="decision-buttons">
                    <button type="submit" name="action" value="approve" 
                            class="btn-large btn-approve"
                            onclick="return confirm('هل أنت متأكد من الموافقة على هذا الدرس؟ سيصبح الدرس متاحاً للطلاب.');">
                        ✅ الموافقة على الدرس
                    </button>
                    
                    <button type="submit" name="action" value="reject" 
                            class="btn-large btn-reject"
                            onclick="return confirmReject();">
                        ❌ رفض الدرس
                    </button>
                </div>

                <div class="warning-text" id="rejectWarning" style="display: none;">
                    ⚠️ تنبيه: يجب كتابة سبب الرفض في حقل الملاحظات قبل رفض الدرس
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmReject() {
            const notes = document.getElementById('supervisor_notes').value.trim();
            const warning = document.getElementById('rejectWarning');
            
            if (notes === '') {
                warning.style.display = 'block';
                document.getElementById('supervisor_notes').focus();
                return false;
            }
            
            warning.style.display = 'none';
            return confirm('هل أنت متأكد من رفض هذا الدرس؟\n\nسبب الرفض: ' + notes);
        }
    </script>
</body>
</html>
