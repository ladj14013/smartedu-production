<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$tab = $_GET['tab'] ?? 'all';

// جلب معلومات الأستاذ والمادة
$stmt = $pdo->prepare("
    SELECT u.*, s.name as subject_name, st.name as stage_name
    FROM users u
    LEFT JOIN subjects s ON u.subject_id = s.id
    LEFT JOIN stages st ON s.stage_id = st.id
    WHERE u.id = ?
");
$stmt->execute([$teacher_id]);
$teacher_info = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب الدروس حسب التبويب
$query_base = "
    SELECT l.*, lv.name as level_name, s.name as subject_name
    FROM lessons l
    LEFT JOIN levels lv ON l.level_id = lv.id
    LEFT JOIN subjects s ON l.subject_id = s.id
    WHERE l.author_id = ?
";

switch ($tab) {
    case 'pending':
        $query = $query_base . " AND l.status = 'pending' ORDER BY l.created_at DESC";
        break;
    case 'approved':
        $query = $query_base . " AND l.status = 'approved' ORDER BY l.created_at DESC";
        break;
    case 'rejected':
        $query = $query_base . " AND l.status = 'rejected' ORDER BY l.created_at DESC";
        break;
    default:
        $query = $query_base . " ORDER BY l.created_at DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute([$teacher_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM lessons
    WHERE author_id = ?
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([$teacher_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دروسي - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/rtl-sidebar.css">
    <style>
        body {
            direction: rtl;
            text-align: right;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            min-height: 100vh;
        }
        
        .main-content {
            margin-right: 300px !important;
            margin-left: 0 !important;
            padding: 30px;
            min-height: 100vh;
            width: auto !important;
            box-sizing: border-box;
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
            color: #4CAF50;
            font-size: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-top: 4px solid;
        }
        
        .stat-card.total { border-top-color: #2196F3; }
        .stat-card.pending { border-top-color: #FF9800; }
        .stat-card.approved { border-top-color: #4CAF50; }
        .stat-card.rejected { border-top-color: #f44336; }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .tabs {
            background: white;
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .tab {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            transition: all 0.3s;
            background: transparent;
        }
        
        .tab:hover {
            background: #f0f0f0;
        }
        
        .tab.active {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        
        .lessons-grid {
            display: grid;
            gap: 20px;
        }
        
        .lesson-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border-right: 5px solid;
        }
        
        .lesson-card.pending { border-right-color: #FF9800; }
        .lesson-card.approved { border-right-color: #4CAF50; }
        .lesson-card.rejected { border-right-color: #f44336; }
        
        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .lesson-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin: 0 0 10px 0;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .status-badge.pending {
            background: #FFF3E0;
            color: #E65100;
        }
        
        .status-badge.approved {
            background: #E8F5E9;
            color: #2E7D32;
        }
        
        .status-badge.rejected {
            background: #FFEBEE;
            color: #C62828;
        }
        
        .lesson-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .lesson-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .lesson-content {
            color: #555;
            line-height: 1.6;
            margin: 15px 0;
            max-height: 100px;
            overflow: hidden;
        }
        
        .supervisor-notes {
            background: #FFF3E0;
            border-right: 3px solid #FF9800;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .supervisor-notes h4 {
            margin: 0 0 10px 0;
            color: #E65100;
            font-size: 0.95rem;
        }
        
        .supervisor-notes p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .lesson-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .btn {
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .empty-state {
            background: white;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .empty-state p {
            color: #666;
            font-size: 1.1rem;
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
        <div class="page-header">
            <h1>📚 دروسي</h1>
            <p>إدارة ومتابعة دروسك</p>
        </div>
        
        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-label">📊 إجمالي الدروس</div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label">⏳ قيد المراجعة</div>
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card approved">
                <div class="stat-label">✅ المعتمدة</div>
                <div class="stat-value"><?php echo $stats['approved']; ?></div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-label">❌ المرفوضة</div>
                <div class="stat-value"><?php echo $stats['rejected']; ?></div>
            </div>
        </div>
        
        <!-- التبويبات -->
        <div class="tabs">
            <a href="?tab=all" class="tab <?php echo $tab === 'all' ? 'active' : ''; ?>">
                📋 جميع الدروس (<?php echo $stats['total']; ?>)
            </a>
            <a href="?tab=pending" class="tab <?php echo $tab === 'pending' ? 'active' : ''; ?>">
                ⏳ قيد المراجعة (<?php echo $stats['pending']; ?>)
            </a>
            <a href="?tab=approved" class="tab <?php echo $tab === 'approved' ? 'active' : ''; ?>">
                ✅ المعتمدة (<?php echo $stats['approved']; ?>)
            </a>
            <a href="?tab=rejected" class="tab <?php echo $tab === 'rejected' ? 'active' : ''; ?>">
                ❌ المرفوضة (<?php echo $stats['rejected']; ?>)
            </a>
        </div>
        
        <!-- قائمة الدروس -->
        <div class="lessons-grid">
            <?php if (empty($lessons)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>لا توجد دروس في هذا القسم</p>
                    <a href="lessons.php" class="btn btn-primary" style="display: inline-block; margin-top: 20px;">
                        ➕ إضافة درس جديد
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($lessons as $lesson): ?>
                    <div class="lesson-card <?php echo $lesson['status']; ?>">
                        <div class="lesson-header">
                            <div>
                                <h3 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                <div class="lesson-meta">
                                    <span>📖 <?php echo htmlspecialchars($lesson['subject_name']); ?></span>
                                    <span>🎓 <?php echo htmlspecialchars($lesson['level_name']); ?></span>
                                    <span>📅 <?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?></span>
                                </div>
                            </div>
                            <span class="status-badge <?php echo $lesson['status']; ?>">
                                <?php 
                                switch ($lesson['status']) {
                                    case 'pending': echo '⏳ قيد المراجعة'; break;
                                    case 'approved': echo '✅ معتمد'; break;
                                    case 'rejected': echo '❌ مرفوض'; break;
                                }
                                ?>
                            </span>
                        </div>
                        
                        <div class="lesson-content">
                            <?php echo nl2br(htmlspecialchars(substr($lesson['content'], 0, 200))); ?>...
                        </div>
                        
                        <?php if ($lesson['status'] === 'rejected' && !empty($lesson['supervisor_notes'])): ?>
                            <div class="supervisor-notes">
                                <h4>📝 ملاحظات المشرف:</h4>
                                <p><?php echo nl2br(htmlspecialchars($lesson['supervisor_notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="lesson-actions">
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
                            
                            <div style="display: flex; gap: 10px;">
                                <a href="preview-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-primary">
                                    👁️ عرض التفاصيل
                                </a>
                                <?php if ($lesson['status'] === 'rejected'): ?>
                                    <a href="edit-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-secondary">
                                        ✏️ تعديل وإعادة الإرسال
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
