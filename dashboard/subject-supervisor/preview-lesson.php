<?php
/**
 * Subject Supervisor - Lesson Preview
 * معاينة الدرس قبل الموافقة عليه
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// التحقق من تسجيل الدخول كمشرف مادة
require_auth();
if (!has_any_role(['superviseur_matiere', 'supervisor_subject', 'subject_supervisor'])) {
    header("Location: ../../dashboard/index.php");
    exit();
}

global $pdo;
$supervisor_id = $_SESSION['user_id'];
$subject_id = $_SESSION['subject_id'] ?? null;
$lesson_id = $_GET['id'] ?? 0;

if (!$subject_id) {
    die("خطأ: لم يتم تعيين مادة لهذا المشرف.");
}

if (!$lesson_id) {
    header('Location: approve-lessons.php');
    exit;
}

$success = '';
$error = '';

// معالجة الموافقة/الرفض
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE lessons SET status = 'approved' WHERE id = ? AND subject_id = ?");
        $stmt->execute([$lesson_id, $subject_id]);
        header("Location: approve-lessons.php?success=approved");
        exit();
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE lessons SET status = 'rejected' WHERE id = ? AND subject_id = ?");
        $stmt->execute([$lesson_id, $subject_id]);
        header("Location: approve-lessons.php?success=rejected");
        exit();
    }
}

// جلب معلومات الدرس
$stmt = $pdo->prepare("
    SELECT l.*, 
           s.name as subject_name,
           lv.name as level_name,
           st.name as stage_name,
           u.name as teacher_name, u.email as teacher_email
    FROM lessons l
    JOIN subjects s ON l.subject_id = s.id
    LEFT JOIN levels lv ON l.level_id = lv.id
    LEFT JOIN stages st ON lv.stage_id = st.id
    JOIN users u ON l.author_id = u.id
    WHERE l.id = ? AND l.subject_id = ?
");
$stmt->execute([$lesson_id, $subject_id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: approve-lessons.php');
    exit;
}

// جلب التمارين المتعلقة بالدرس
$stmt = $pdo->prepare("
    SELECT e.*
    FROM exercises e
    WHERE e.lesson_id = ?
    ORDER BY e.created_at ASC
");
$stmt->execute([$lesson_id]);
$exercises = $stmt->fetchAll();

// تحويل رابط اليوتيوب لصيغة embed
function getYoutubeEmbedUrl($url) {
    if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    } elseif (preg_match('/youtu\.be\/([^?]+)/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }
    return $url;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معاينة الدرس - <?php echo htmlspecialchars($lesson['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header Navigation */
        .top-nav {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #e9d5ff;
            color: #7c3aed;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #ddd6fe;
            transform: translateX(5px);
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-pending {
            background: #fef3c7;
            color: #f59e0b;
        }

        .status-approved {
            background: #d1fae5;
            color: #10b981;
        }

        /* Lesson Header */
        .lesson-header {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(124, 58, 237, 0.3);
        }

        .subject-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .lesson-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .lesson-meta {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            font-size: 0.95rem;
            opacity: 0.95;
        }

        .lesson-meta span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Content Layout */
        .content-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .card h3 {
            font-size: 1.3rem;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }

        /* Video Section */
        .video-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 */
            height: 0;
            overflow: hidden;
            border-radius: 12px;
            margin-bottom: 25px;
            background: #000;
        }

        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        .no-video {
            background: #f3f4f6;
            padding: 40px;
            text-align: center;
            border-radius: 12px;
            color: #6b7280;
        }

        .no-video i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Content */
        .lesson-description {
            color: #374151;
            line-height: 1.8;
            font-size: 1.05rem;
            white-space: pre-wrap;
        }

        /* Teacher Info */
        .teacher-info {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #7c3aed;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .teacher-info h4 {
            color: #7c3aed;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .teacher-info p {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: #374151;
        }

        /* Lesson Details */
        .lesson-details {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #6b7280;
            font-weight: 600;
        }

        .detail-value {
            color: #1f2937;
        }

        /* PDF Download */
        .pdf-download {
            display: block;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .pdf-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
        }

        .pdf-download i {
            margin-left: 10px;
        }

        /* Exercises Section */
        .exercises-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .exercise-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .exercise-card:hover {
            border-color: #7c3aed;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.1);
        }

        .exercise-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .exercise-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }

        .exercise-type {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .type-quiz {
            background: #dbeafe;
            color: #2563eb;
        }

        .type-assignment {
            background: #fef3c7;
            color: #f59e0b;
        }

        .exercise-description {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        /* Approval Actions */
        .approval-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-top: 30px;
        }

        .approval-section h3 {
            color: #1f2937;
            margin-bottom: 25px;
            font-size: 1.5rem;
            text-align: center;
        }

        .approval-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-approve {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.3);
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
        }

        .rejection-form {
            display: none;
            margin-top: 20px;
        }

        .rejection-form.active {
            display: block;
        }

        .rejection-form textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-family: 'Cairo', sans-serif;
            font-size: 1rem;
            margin-bottom: 15px;
            resize: vertical;
            min-height: 100px;
        }

        .rejection-form textarea:focus {
            outline: none;
            border-color: #ef4444;
        }

        @media (max-width: 968px) {
            .content-layout {
                grid-template-columns: 1fr;
            }

            .approval-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Top Navigation -->
        <div class="top-nav">
            <a href="approve-lessons.php" class="back-btn">
                <i class="fas fa-arrow-right"></i>
                رجوع إلى قائمة المراجعة
            </a>
            <span class="status-badge status-<?php echo $lesson['status']; ?>">
                <?php 
                    $statuses = [
                        'pending' => 'قيد المراجعة',
                        'approved' => 'موافق عليه',
                        'rejected' => 'مرفوض'
                    ];
                    echo $statuses[$lesson['status']] ?? $lesson['status'];
                ?>
            </span>
        </div>

        <!-- Lesson Header -->
        <div class="lesson-header">
            <div class="subject-badge">
                <i class="fas fa-book"></i>
                <span><?php echo htmlspecialchars($lesson['subject_name']); ?></span>
            </div>
            <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
            <div class="lesson-meta">
                <span>
                    <i class="fas fa-chalkboard-teacher"></i>
                    الأستاذ: <?php echo htmlspecialchars($lesson['teacher_name']); ?>
                </span>
                <span>
                    <i class="fas fa-layer-group"></i>
                    <?php echo htmlspecialchars($lesson['level_name']); ?>
                </span>
                <span>
                    <i class="fas fa-clock"></i>
                    <?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?>
                </span>
                <span>
                    <i class="fas fa-globe"></i>
                    درس <?php echo $lesson['type'] === 'public' ? 'عام' : 'خاص'; ?>
                </span>
            </div>
        </div>

        <!-- Content Layout -->
        <div class="content-layout">
            <!-- Main Content -->
            <div>
                <!-- Video Section -->
                <div class="card">
                    <h3>
                        <i class="fas fa-video" style="color: #ef4444;"></i>
                        الفيديو التعليمي
                    </h3>
                    <?php if ($lesson['video_url']): ?>
                        <div class="video-container">
                            <iframe src="<?php echo htmlspecialchars(getYoutubeEmbedUrl($lesson['video_url'])); ?>" 
                                    allowfullscreen></iframe>
                        </div>
                    <?php else: ?>
                        <div class="no-video">
                            <i class="fas fa-video-slash"></i>
                            <p>لم يتم إضافة فيديو لهذا الدرس</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="card">
                    <h3>
                        <i class="fas fa-align-right" style="color: #3b82f6;"></i>
                        وصف الدرس
                    </h3>
                    <div class="lesson-description">
                        <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Teacher Info -->
                <div class="teacher-info">
                    <h4>
                        <i class="fas fa-user-tie"></i>
                        معلومات الأستاذ
                    </h4>
                    <p>
                        <i class="fas fa-user"></i>
                        <strong>الاسم:</strong> <?php echo htmlspecialchars($lesson['teacher_name']); ?>
                    </p>
                    <p>
                        <i class="fas fa-envelope"></i>
                        <strong>البريد:</strong> <?php echo htmlspecialchars($lesson['teacher_email']); ?>
                    </p>
                </div>

                <!-- Lesson Details -->
                <div class="lesson-details">
                    <h4 style="margin-bottom: 15px; color: #7c3aed;">
                        <i class="fas fa-info-circle"></i>
                        تفاصيل الدرس
                    </h4>
                    <div class="detail-item">
                        <span class="detail-label">المرحلة:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($lesson['stage_name'] ?? 'غير محدد'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">المستوى:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($lesson['level_name'] ?? 'غير محدد'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">النوع:</span>
                        <span class="detail-value"><?php echo $lesson['type'] === 'public' ? 'عام' : 'خاص'; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">التمارين:</span>
                        <span class="detail-value"><?php echo count($exercises); ?> تمرين</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">تاريخ الإنشاء:</span>
                        <span class="detail-value"><?php echo date('Y/m/d h:i A', strtotime($lesson['created_at'])); ?></span>
                    </div>
                </div>

                <!-- PDF Download -->
                <?php if ($lesson['pdf_url']): ?>
                    <a href="../../<?php echo htmlspecialchars($lesson['pdf_url']); ?>" 
                       class="pdf-download" 
                       target="_blank">
                        <i class="fas fa-file-pdf"></i>
                        تحميل الملف المرفق (PDF)
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Exercises Section -->
        <?php if (count($exercises) > 0): ?>
        <div class="exercises-section">
            <h3>
                <i class="fas fa-tasks" style="color: #f59e0b;"></i>
                التمارين المرفقة (<?php echo count($exercises); ?>)
            </h3>
            <?php foreach ($exercises as $exercise): ?>
                <div class="exercise-card">
                    <div class="exercise-header">
                        <div class="exercise-title">
                            <i class="fas fa-pen"></i>
                            <?php echo htmlspecialchars($exercise['title']); ?>
                        </div>
                        <span class="exercise-type type-quiz">
                            <i class="fas fa-question-circle"></i>
                            تمرين
                        </span>
                    </div>
                    <?php if ($exercise['description']): ?>
                        <div class="exercise-description">
                            <?php echo nl2br(htmlspecialchars($exercise['description'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Approval Section -->
        <?php if ($lesson['status'] === 'pending'): ?>
        <div class="approval-section">
            <h3>
                <i class="fas fa-clipboard-check"></i>
                إجراء المراجعة
            </h3>
            
            <form method="POST" id="approvalForm">
                <div class="approval-buttons">
                    <button type="submit" name="action" value="approve" class="btn btn-approve">
                        <i class="fas fa-check-circle"></i>
                        الموافقة على الدرس
                    </button>
                    <button type="button" class="btn btn-reject" onclick="toggleRejectForm()">
                        <i class="fas fa-times-circle"></i>
                        رفض الدرس
                    </button>
                </div>

                <div class="rejection-form" id="rejectionForm">
                    <textarea name="rejection_reason" 
                              placeholder="اكتب سبب الرفض (اختياري)..."
                              rows="4"></textarea>
                    <button type="submit" name="action" value="reject" class="btn btn-reject" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i>
                        تأكيد الرفض
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleRejectForm() {
            const form = document.getElementById('rejectionForm');
            form.classList.toggle('active');
        }

        // تأكيد الموافقة
        document.getElementById('approvalForm')?.addEventListener('submit', function(e) {
            const action = e.submitter.value;
            if (action === 'approve') {
                if (!confirm('هل أنت متأكد من الموافقة على هذا الدرس ونشره للطلاب؟')) {
                    e.preventDefault();
                }
            } else if (action === 'reject') {
                if (!confirm('هل أنت متأكد من رفض هذا الدرس؟')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>
