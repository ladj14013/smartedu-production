<?php
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

if (!$subject_id) {
    die("خطأ: لم يتم تعيين مادة لهذا المشرف. الرجاء تسجيل الخروج والدخول مرة أخرى.");
}

$success = '';
$error = '';

// التحقق من رسائل النجاح من URL
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'approved') {
        $success = 'تم قبول الدرس ونشره بنجاح!';
    } elseif ($_GET['success'] === 'rejected') {
        $success = 'تم رفض الدرس.';
    }
}

// معالجة الموافقة/الرفض
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['lesson_id'])) {
    $lesson_id = intval($_POST['lesson_id']);
    $action = $_POST['action']; // approve or reject
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE lessons SET status = 'approved' WHERE id = ? AND subject_id = ?");
        $stmt->execute([$lesson_id, $subject_id]);
        $success = 'تم قبول الدرس ونشره بنجاح!';
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE lessons SET status = 'rejected' WHERE id = ? AND subject_id = ?");
        $stmt->execute([$lesson_id, $subject_id]);
        $success = 'تم رفض الدرس.';
    }
}

// جلب معلومات المادة
$stmt = $pdo->prepare("
    SELECT s.name as subject_name, st.name as stage_name
    FROM subjects s
    LEFT JOIN stages st ON s.stage_id = st.id
    WHERE s.id = ?
");
$stmt->execute([$subject_id]);
$subject = $stmt->fetch();

// جلب الدروس المعلقة (بانتظار الموافقة)
$stmt = $pdo->prepare("
    SELECT 
        l.id,
        l.title,
        l.content,
        l.type,
        l.video_url,
        l.pdf_url,
        l.created_at,
        u.name as teacher_name,
        u.email as teacher_email,
        lv.name as level_name
    FROM lessons l
    LEFT JOIN users u ON l.author_id = u.id
    LEFT JOIN levels lv ON l.level_id = lv.id
    WHERE l.subject_id = ? AND l.status = 'pending' AND l.type = 'public'
    ORDER BY l.created_at ASC
");
$stmt->execute([$subject_id]);
$pending_lessons = $stmt->fetchAll();

// جلب إحصائيات
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as pending_count
    FROM lessons 
    WHERE subject_id = ? AND status = 'pending' AND type = 'public'
");
$stmt->execute([$subject_id]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مراجعة الدروس | مشرف المادة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            display: flex;
            line-height: 1.7;
        }
        
        .main-content {
            flex: 1;
            margin-right: 280px;
            padding: 30px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 30px 35px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-right: 5px solid #9C27B0;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .breadcrumb {
            font-size: 0.95rem;
            color: #718096;
            margin-top: 8px;
        }
        
        .breadcrumb a {
            color: #9C27B0;
            text-decoration: none;
            font-weight: 600;
        }
        
        .stats-bar {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
            padding: 25px 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(255, 152, 0, 0.3);
        }
        
        .stats-bar .number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stats-bar .label {
            font-size: 1.1rem;
            opacity: 0.95;
        }
        
        .alert {
            padding: 18px 24px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
        }
        
        .lesson-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-right: 5px solid #ff9800;
            transition: all 0.3s;
        }
        
        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.12);
        }
        
        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .lesson-title h2 {
            font-size: 1.5rem;
            color: #2d3748;
            margin-bottom: 12px;
            font-weight: 700;
        }
        
        .lesson-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 0.9rem;
            color: #718096;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .lesson-content {
            background: #f7fafc;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            max-height: 200px;
            overflow-y: auto;
            line-height: 1.8;
            color: #4a5568;
        }
        
        .lesson-extras {
            display: flex;
            gap: 15px;
            margin: 20px 0;
        }
        
        .extra-badge {
            padding: 8px 16px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .lesson-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Cairo', inherit;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-approve:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-reject:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn-preview {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        
        .btn-preview:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        .no-lessons {
            text-align: center;
            padding: 80px 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        
        .no-lessons-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }
        
        .no-lessons h3 {
            font-size: 1.8rem;
            color: #2d3748;
            margin-bottom: 12px;
        }
        
        .no-lessons p {
            color: #718096;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>
                <span>✅</span>
                مراجعة الدروس العامة
            </h1>
            <div class="breadcrumb">
                <a href="index.php">الرئيسية</a> / 
                <strong>مراجعة الدروس</strong>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <span style="font-size: 1.5rem;">✅</span>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>
        
        <div class="stats-bar">
            <div class="number"><?php echo $stats['pending_count']; ?></div>
            <div class="label">درس بانتظار المراجعة</div>
        </div>
        
        <?php if (empty($pending_lessons)): ?>
            <div class="no-lessons">
                <div class="no-lessons-icon">✅</div>
                <h3>رائع! لا توجد دروس بانتظار المراجعة</h3>
                <p>جميع الدروس العامة تمت مراجعتها</p>
            </div>
        <?php else: ?>
            <?php foreach ($pending_lessons as $lesson): ?>
                <div class="lesson-card">
                    <div class="lesson-header">
                        <div class="lesson-title">
                            <h2><?php echo htmlspecialchars($lesson['title']); ?></h2>
                            <div class="lesson-meta">
                                <span class="meta-item">
                                    <span>👨‍🏫</span>
                                    <span><?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                                </span>
                                <span class="meta-item">
                                    <span>📚</span>
                                    <span><?php echo htmlspecialchars($lesson['level_name'] ?? 'جميع المستويات'); ?></span>
                                </span>
                                <span class="meta-item">
                                    <span>📅</span>
                                    <span><?php echo date('Y/m/d H:i', strtotime($lesson['created_at'])); ?></span>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="lesson-content">
                        <?php echo nl2br(htmlspecialchars(mb_substr($lesson['content'], 0, 500, 'UTF-8'))); ?>
                        <?php if (mb_strlen($lesson['content'], 'UTF-8') > 500): ?>
                            <strong>...</strong>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($lesson['video_url'] || $lesson['pdf_url']): ?>
                        <div class="lesson-extras">
                            <?php if ($lesson['video_url']): ?>
                                <span class="extra-badge">
                                    <span>🎥</span>
                                    <span>يحتوي على فيديو</span>
                                </span>
                            <?php endif; ?>
                            <?php if ($lesson['pdf_url']): ?>
                                <span class="extra-badge">
                                    <span>📄</span>
                                    <span>يحتوي على PDF</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="lesson-actions">
                        <a href="preview-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-preview">
                            <i class="fas fa-eye"></i>
                            <span>معاينة الدرس كاملاً</span>
                        </a>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-approve">
                                <span>✅</span>
                                <span>قبول ونشر</span>
                            </button>
                        </form>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-reject" onclick="return confirm('هل أنت متأكد من رفض هذا الدرس؟')">
                                <span>❌</span>
                                <span>رفض</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>
