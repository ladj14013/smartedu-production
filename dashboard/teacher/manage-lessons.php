<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// التحقق من تسجيل الدخول والصلاحيات
require_auth();
if (!has_any_role(['enseignant', 'teacher'])) {
    header("Location: ../../dashboard/index.php");
    exit();
}

global $pdo;
$teacher_id = $_SESSION['user_id'];
$teacher_subject_id = $_SESSION['subject_id'] ?? null;

// جلب معلومات المادة
$stmt = $pdo->prepare("
    SELECT s.name as subject_name, st.name as stage_name
    FROM subjects s
    LEFT JOIN stages st ON s.stage_id = st.id
    WHERE s.id = ?
");
$stmt->execute([$teacher_subject_id]);
$subject_info = $stmt->fetch();

// معالجة الحذف
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $lesson_id = (int)$_GET['delete'];
    
    // التحقق من أن الدرس يخص هذا الأستاذ
    $stmt = $pdo->prepare("SELECT id FROM lessons WHERE id = ? AND author_id = ?");
    $stmt->execute([$lesson_id, $teacher_id]);
    
    if ($stmt->fetch()) {
        try {
            // حذف التمارين المرتبطة أولاً
            $pdo->prepare("DELETE FROM exercises WHERE lesson_id = ?")->execute([$lesson_id]);
            
            // حذف الدرس
            $pdo->prepare("DELETE FROM lessons WHERE id = ?")->execute([$lesson_id]);
            
            header("Location: manage-lessons.php?success=deleted");
            exit();
        } catch (PDOException $e) {
            $error = "فشل حذف الدرس: " . $e->getMessage();
        }
    }
}

// فلترة الدروس
$filter_status = $_GET['status'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// بناء استعلام الدروس
$where_conditions = ["l.author_id = ?"];
$params = [$teacher_id];

if ($filter_status !== 'all') {
    $where_conditions[] = "l.status = ?";
    $params[] = $filter_status;
}

if ($filter_type !== 'all') {
    $where_conditions[] = "l.type = ?";
    $params[] = $filter_type;
}

if (!empty($search)) {
    $where_conditions[] = "(l.title LIKE ? OR l.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_sql = implode(' AND ', $where_conditions);

// جلب جميع دروس الأستاذ مع الإحصائيات
$stmt = $pdo->prepare("
    SELECT 
        l.*,
        lv.name as level_name,
        s.name as subject_name,
        COUNT(DISTINCT e.id) as exercises_count,
        l.status,
        l.type
    FROM lessons l
    LEFT JOIN levels lv ON l.level_id = lv.id
    LEFT JOIN subjects s ON l.subject_id = s.id
    LEFT JOIN exercises e ON l.id = e.lesson_id
    WHERE $where_sql
    GROUP BY l.id
    ORDER BY l.created_at DESC
");
$stmt->execute($params);
$lessons = $stmt->fetchAll();

// إحصائيات عامة
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN type = 'public' THEN 1 ELSE 0 END) as public_count,
        SUM(CASE WHEN type = 'private' THEN 1 ELSE 0 END) as private_count
    FROM lessons
    WHERE author_id = ?
");
$stmt->execute([$teacher_id]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الدروس - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Cairo', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            margin: 0;
            padding: 0;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 2rem;
        }
        
        .page-header p {
            color: #7f8c8d;
            margin: 0;
        }
        
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-badge {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #4CAF50;
        }
        
        .stat-badge.pending { border-left-color: #ff9800; }
        .stat-badge.approved { border-left-color: #4CAF50; }
        .stat-badge.rejected { border-left-color: #f44336; }
        .stat-badge.public { border-left-color: #2196F3; }
        .stat-badge.private { border-left-color: #9c27b0; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .actions-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
        }
        
        .filters-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            font-size: 0.95rem;
            cursor: pointer;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .search-box {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            min-width: 250px;
        }
        
        .search-box:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .lessons-grid {
            display: grid;
            gap: 20px;
        }
        
        .lesson-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            position: relative;
        }
        
        .lesson-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .lesson-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0 0 10px 0;
        }
        
        .lesson-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-pending {
            background: #fff3e0;
            color: #e65100;
        }
        
        .badge-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-rejected {
            background: #ffebee;
            color: #c62828;
        }
        
        .badge-public {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .badge-private {
            background: #f3e5f5;
            color: #6a1b9a;
        }
        
        .lesson-meta {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            color: #7f8c8d;
            font-size: 0.95rem;
            flex-wrap: wrap;
        }
        
        .lesson-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .lesson-description {
            color: #546e7a;
            margin: 15px 0;
            line-height: 1.6;
        }
        
        .lesson-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eceff1;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 0.9rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-edit {
            background: #2196F3;
            color: white;
        }
        
        .btn-edit:hover {
            background: #1976D2;
        }
        
        .btn-exercises {
            background: #ff9800;
            color: white;
        }
        
        .btn-exercises:hover {
            background: #f57c00;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background: #d32f2f;
        }
        
        .btn-view {
            background: #9c27b0;
            color: white;
        }
        
        .btn-view:hover {
            background: #7b1fa2;
        }
        
        .empty-state {
            background: white;
            padding: 60px 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .empty-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            color: #546e7a;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #90a4ae;
        }
        
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #4CAF50;
        }
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
        }
        
        @media (max-width: 768px) {
            .stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filters-group {
                flex-direction: column;
            }
            
            .search-box {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1>📚 إدارة الدروس</h1>
                <p>إدارة وتنظيم المحتوى التعليمي الخاص بك</p>
                
                <div class="stats-bar">
                    <div class="stat-badge">
                        <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                        <div class="stat-label">إجمالي الدروس</div>
                    </div>
                    
                    <div class="stat-badge pending">
                        <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                        <div class="stat-label">قيد الانتظار</div>
                    </div>
                    
                    <div class="stat-badge approved">
                        <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
                        <div class="stat-label">موافق عليها</div>
                    </div>
                    
                    <div class="stat-badge rejected">
                        <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                        <div class="stat-label">مرفوضة</div>
                    </div>
                    
                    <div class="stat-badge public">
                        <div class="stat-number"><?php echo $stats['public_count'] ?? 0; ?></div>
                        <div class="stat-label">دروس عامة</div>
                    </div>
                    
                    <div class="stat-badge private">
                        <div class="stat-number"><?php echo $stats['private_count'] ?? 0; ?></div>
                        <div class="stat-label">دروس خاصة</div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
                <div class="success-message">
                    ✅ تم حذف الدرس بنجاح
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    ⚠️ <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="actions-bar">
                <a href="create-lesson.php" class="btn btn-primary">
                    <span>➕</span>
                    <span>إضافة درس جديد</span>
                </a>
                
                <form method="GET" class="filters-group" style="margin: 0;">
                    <input 
                        type="text" 
                        name="search" 
                        class="search-box" 
                        placeholder="🔍 البحث عن درس..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                    
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all">جميع الحالات</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                        <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>موافق عليها</option>
                        <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>مرفوضة</option>
                    </select>
                    
                    <select name="type" class="filter-select" onchange="this.form.submit()">
                        <option value="all">جميع الأنواع</option>
                        <option value="public" <?php echo $filter_type === 'public' ? 'selected' : ''; ?>>عامة</option>
                        <option value="private" <?php echo $filter_type === 'private' ? 'selected' : ''; ?>>خاصة</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">بحث</button>
                </form>
            </div>
            
            <div class="lessons-grid">
                <?php if (empty($lessons)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📝</div>
                        <h3>لا توجد دروس بعد</h3>
                        <p>ابدأ بإنشاء درسك الأول لمشاركة المعرفة مع طلابك</p>
                        <a href="create-lesson.php" class="btn btn-primary" style="margin-top: 20px;">
                            ➕ إنشاء درس جديد
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($lessons as $lesson): ?>
                        <div class="lesson-card">
                            <div class="lesson-header">
                                <div style="flex: 1;">
                                    <h3 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                    <div class="lesson-badges">
                                        <span class="badge badge-<?php echo $lesson['status']; ?>">
                                            <?php 
                                            $status_text = [
                                                'pending' => '⏳ قيد الانتظار',
                                                'approved' => '✅ موافق عليه',
                                                'rejected' => '❌ مرفوض'
                                            ];
                                            echo $status_text[$lesson['status']] ?? $lesson['status'];
                                            ?>
                                        </span>
                                        <span class="badge badge-<?php echo $lesson['type']; ?>">
                                            <?php echo $lesson['type'] === 'public' ? '🌍 عام' : '🔒 خاص'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="lesson-meta">
                                <span>📅 <?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?></span>
                                <span>📖 <?php echo htmlspecialchars($lesson['level_name'] ?? 'غير محدد'); ?></span>
                                <span>✍️ <?php echo $lesson['exercises_count']; ?> تمرين</span>
                                <?php if ($lesson['video_url']): ?>
                                    <span>🎥 فيديو</span>
                                <?php endif; ?>
                                <?php if ($lesson['pdf_url']): ?>
                                    <span>📄 PDF</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($lesson['description'])): ?>
                                <div class="lesson-description">
                                    <?php 
                                    $desc = htmlspecialchars($lesson['description']);
                                    echo strlen($desc) > 150 ? substr($desc, 0, 150) . '...' : $desc;
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="lesson-actions">
                                <a href="edit-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn-small btn-edit">
                                    ✏️ تعديل
                                </a>
                                <a href="exercise-form.php?lesson_id=<?php echo $lesson['id']; ?>" class="btn-small btn-exercises">
                                    ➕ إضافة تمرين
                                </a>
                                <a href="preview-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn-small btn-view">
                                    👁️ معاينة
                                </a>
                                <a href="?delete=<?php echo $lesson['id']; ?>" 
                                   class="btn-small btn-delete" 
                                   onclick="return confirm('هل أنت متأكد من حذف هذا الدرس؟ سيتم حذف جميع التمارين المرتبطة به أيضاً.')">
                                    🗑️ حذف
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
