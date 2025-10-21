<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// التحقق من تسجيل الدخول والصلاحيات
require_auth();
if (!has_any_role(['etudiant', 'student'])) {
    header("Location: ../../dashboard/index.php");
    exit();
}

global $pdo;
$student_id = $_SESSION['user_id'];
$student_level_id = $_SESSION['level_id'] ?? null;
$student_stage_id = $_SESSION['stage_id'] ?? null;

// التحقق من وجود معرف المادة في الرابط
$subject_id = $_GET['subject_id'] ?? null;
if (!$subject_id) {
    header("Location: available-subjects.php");
    exit();
}

// إذا لم يكن level_id موجود في session، جلبه من قاعدة البيانات
if (!$student_level_id || !$student_stage_id) {
    try {
        $stmt = $pdo->prepare("SELECT level_id, stage_id FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $user_data = $stmt->fetch();
        if ($user_data) {
            $student_level_id = $user_data['level_id'];
            $student_stage_id = $user_data['stage_id'];
            $_SESSION['level_id'] = $user_data['level_id'];
            $_SESSION['stage_id'] = $user_data['stage_id'];
        }
    } catch (PDOException $e) {
        // ignore
    }
}

// جلب معلومات المادة
try {
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ? AND stage_id = ?");
    $stmt->execute([$subject_id, $student_stage_id]);
    $subject = $stmt->fetch();
    if (!$subject) {
        header("Location: available-subjects.php");
        exit();
    }
} catch (PDOException $e) {
    header("Location: available-subjects.php");
    exit();
}

// معالجة الفلاتر
$filter_type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// بناء الاستعلام للدروس
$where_conditions = ["l.level_id = ?", "l.subject_id = ?"];
$params = [$student_level_id, $subject_id];

// فقط الدروس الموافق عليها
$where_conditions[] = "l.status = 'approved'";

// شرط عرض الدروس العامة والخاصة
$where_conditions[] = "(
    l.type = 'public' 
    OR (
        l.type = 'private' 
        AND l.author_id IN (
            SELECT teacher_id 
            FROM student_teacher_links 
            WHERE student_id = ? AND status = 'active'
        )
    )
)";
$params[] = $student_id;

// فلتر النوع
if ($filter_type) {
    $where_conditions[] = "l.type = ?";
    $params[] = $filter_type;
}

// البحث
if ($search) {
    $where_conditions[] = "(l.title LIKE ? OR l.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where_conditions);

// جلب الدروس
try {
    $query = "
        SELECT 
            l.*,
            s.name as subject_name,
            u.name as teacher_name,
            lv.name as level_name,
            (SELECT COUNT(*) FROM exercises WHERE lesson_id = l.id) as exercises_count
        FROM lessons l
        LEFT JOIN subjects s ON l.subject_id = s.id
        LEFT JOIN users u ON l.author_id = u.id
        LEFT JOIN levels lv ON l.level_id = lv.id
        WHERE $where_sql
        ORDER BY l.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $lessons = $stmt->fetchAll();
} catch (PDOException $e) {
    $lessons = [];
    $error_message = "حدث خطأ في جلب الدروس: " . $e->getMessage();
}

// دالة للتحقق من إكمال الدرس
function isLessonCompleted($pdo, $student_id, $lesson_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM lesson_progress 
            WHERE student_id = ? AND lesson_id = ? AND completed = 1
        ");
        $stmt->execute([$student_id, $lesson_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subject['name']); ?> - الدروس المتاحة - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        @media (max-width: 968px) {
            .main-content {
                margin-right: 0 !important;
                padding: 20px;
            }
        }
    </style>
    <style>
        .btn-back {
            background: #E3F2FD;
            color: #1976D2;
            padding: 8px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid #1976D2;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #1976D2;
            color: white;
        }

        .filters-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-input:focus {
            border-color: #2196F3;
            outline: none;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.2);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 8px 15px;
            border: 2px solid #2196F3;
            background: transparent;
            color: #2196F3;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #2196F3;
            color: white;
        }
        
        .lessons-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 30px;
        }
        .page-header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }
        .breadcrumb {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .breadcrumb a {
            color: #E3F2FD;
            text-decoration: none;
            transition: color 0.3s;
        }
        .breadcrumb a:hover {
            color: white;
        }
        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        .lesson-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #2196F3;
        }
        .lesson-header {
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid #dee2e6;
        }
        .lesson-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .lesson-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .meta-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-subject {
            background: #e3f2fd;
            color: #1976D2;
        }
        .badge-teacher {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .badge-public {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-private {
            background: #fff3e0;
            color: #e65100;
        }
        .lesson-body {
            padding: 20px;
        }
        .lesson-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .lesson-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #dee2e6;
        }
        .btn-view {
            padding: 8px 20px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-view:hover {
            background: #1976D2;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
        }
        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        /* Content Type Badges */
        .content-badges {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .content-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .content-badge.video {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }
        
        .content-badge.pdf {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        
        .content-badge.image {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .content-badge.equation {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .content-badge:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="lessons-container">
            <a href="available-subjects.php" class="btn-back">
                ← العودة للمواد المتاحة
            </a>

            <div class="page-header">
                <div class="breadcrumb">
                    <a href="available-subjects.php">المواد المتاحة</a>
                    <span>←</span>
                    <span><?php echo htmlspecialchars($subject['name']); ?></span>
                </div>
                <h1>📚 <?php echo htmlspecialchars($subject['name']); ?></h1>
                <p>تصفح جميع الدروس المتاحة في هذه المادة</p>
            </div>

            <?php if (isset($error_message)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Filters Bar -->
            <div class="filters-bar">
                <form action="" method="get" style="flex: 1; display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                    <input type="search" 
                           name="search" 
                           placeholder="ابحث عن درس..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="search-input">
                    
                    <div class="filter-buttons">
                        <button type="submit" 
                                name="type" 
                                value="" 
                                class="filter-btn <?php echo $filter_type === '' ? 'active' : ''; ?>">
                            الكل
                        </button>
                        <button type="submit" 
                                name="type" 
                                value="public" 
                                class="filter-btn <?php echo $filter_type === 'public' ? 'active' : ''; ?>">
                            🌍 الدروس العامة
                        </button>
                        <button type="submit" 
                                name="type" 
                                value="private" 
                                class="filter-btn <?php echo $filter_type === 'private' ? 'active' : ''; ?>">
                            🔒 الدروس الخاصة
                        </button>
                    </div>
                </form>
            </div>

            <?php if (count($lessons) > 0): ?>
                <div class="lessons-grid">
                    <?php foreach ($lessons as $lesson): 
                        $is_completed = isLessonCompleted($pdo, $student_id, $lesson['id']);
                    ?>
                        <div class="lesson-card">
                            <div class="lesson-header">
                                <div class="lesson-title">
                                    📖 <?php echo htmlspecialchars($lesson['title']); ?>
                                </div>
                                <div class="lesson-meta">
                                    <span class="meta-badge badge-subject">
                                        <?php echo htmlspecialchars($lesson['subject_name'] ?? 'غير محدد'); ?>
                                    </span>
                                    <span class="meta-badge badge-teacher">
                                        👨‍🏫 <?php echo htmlspecialchars($lesson['teacher_name'] ?? 'غير معروف'); ?>
                                    </span>
                                    <span class="meta-badge badge-level">
                                        <?php echo htmlspecialchars($lesson['level_name'] ?? 'غير معروف'); ?>
                                    </span>
                                    <?php if ($lesson['type'] == 'public'): ?>
                                        <span class="meta-badge badge-public">🌍 عام</span>
                                    <?php else: ?>
                                        <span class="meta-badge badge-private">🔒 خاص</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="lesson-body">
                                <div class="lesson-description">
                                    <?php 
                                    $content = strip_tags($lesson['content']);
                                    echo htmlspecialchars(mb_substr($content, 0, 150)) . (mb_strlen($content) > 150 ? '...' : '');
                                    ?>
                                </div>
                            </div>
                            <div class="lesson-footer">
                                <div style="font-size: 12px; color: #999;">
                                    📅 <?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?>
                                </div>
                                
                                <!-- Content Type Icons -->
                                <div class="content-badges">
                                    <?php if (!empty($lesson['video_url'])): ?>
                                        <span class="content-badge video" title="يحتوي على فيديو تعليمي">🎬</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lesson['pdf_url'])): ?>
                                        <span class="content-badge pdf" title="يحتوي على ملف PDF">📄</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lesson['images'])): ?>
                                        <span class="content-badge image" title="يحتوي على صور توضيحية">🖼️</span>
                                    <?php endif; ?>
                                    
                                    <?php if (preg_match('/\$.*?\$|\$\$.*?\$\$/s', $lesson['content'])): ?>
                                        <span class="content-badge equation" title="يحتوي على معادلات رياضية">🔢</span>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="lesson-view.php?id=<?php echo $lesson['id']; ?>" class="btn-view">
                                    مشاهدة الدرس ←
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <p>لا توجد دروس متاحة حالياً</p>
                    <p style="font-size: 14px; color: #bbb; margin-top: 10px;">
                        قم بالربط مع أستاذك للوصول إلى المزيد من الدروس
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
