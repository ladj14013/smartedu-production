<?php
/**
 * Subject Supervisor Dashboard - Main Page
 * لوحة تحكم مشرف المادة - الصفحة الرئيسية
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['supervisor_subject']);

$user_id = $_SESSION['user_id'];

// جلب معلومات المشرف
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// جلب المادة المسؤول عنها
$supervisor_subject_id = $user['subject_id'];

if (!$supervisor_subject_id) {
    die("لم يتم تعيين مادة لهذا المشرف");
}

$stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
$stmt->execute([$supervisor_subject_id]);
$subject = $stmt->fetch();

// ========== إحصائيات المادة ==========

// عدد المعلمين في المادة
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT teacher_id) 
    FROM lessons 
    WHERE subject_id = ?
");
$stmt->execute([$supervisor_subject_id]);
$teachers_count = $stmt->fetchColumn();

// عدد الدروس في المادة
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM lessons 
    WHERE subject_id = ?
");
$stmt->execute([$supervisor_subject_id]);
$lessons_count = $stmt->fetchColumn();

// الدروس قيد المراجعة
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM lessons 
    WHERE subject_id = ? AND status = 'pending'
");
$stmt->execute([$supervisor_subject_id]);
$pending_lessons = $stmt->fetchColumn();

// الدروس المعتمدة
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM lessons 
    WHERE subject_id = ? AND status = 'approved'
");
$stmt->execute([$supervisor_subject_id]);
$approved_lessons = $stmt->fetchColumn();

// الدروس المرفوضة
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM lessons 
    WHERE subject_id = ? AND status = 'rejected'
");
$stmt->execute([$supervisor_subject_id]);
$rejected_lessons = $stmt->fetchColumn();

// عدد الطلاب المسجلين
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$students_count = $stmt->fetchColumn();

// الرسائل غير المقروءة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetchColumn();

// ========== أحدث الدروس قيد المراجعة ==========
$stmt = $pdo->prepare("
    SELECT l.*, u.name as teacher_name 
    FROM lessons l
    JOIN users u ON l.teacher_id = u.id
    WHERE l.subject_id = ? AND l.status = 'pending'
    ORDER BY l.created_at DESC
    LIMIT 10
");
$stmt->execute([$supervisor_subject_id]);
$pending_lessons_list = $stmt->fetchAll();

// ========== أداء المعلمين ==========
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email,
           COUNT(l.id) as total_lessons,
           SUM(CASE WHEN l.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
           SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
           SUM(CASE WHEN l.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM users u
    LEFT JOIN lessons l ON u.id = l.teacher_id AND l.subject_id = ?
    WHERE u.role = 'teacher'
    GROUP BY u.id
    HAVING total_lessons > 0
    ORDER BY total_lessons DESC
");
$stmt->execute([$supervisor_subject_id]);
$teachers_performance = $stmt->fetchAll();

// ========== آخر التحديثات ==========
$stmt = $pdo->prepare("
    SELECT l.*, u.name as teacher_name 
    FROM lessons l
    JOIN users u ON l.teacher_id = u.id
    WHERE l.subject_id = ?
    ORDER BY l.updated_at DESC
    LIMIT 8
");
$stmt->execute([$supervisor_subject_id]);
$recent_updates = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة مشرف المادة - <?php echo htmlspecialchars($subject['name']); ?></title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .subject-header {
            background: linear-gradient(135deg, #4285F4 0%, #22c55e 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(66, 133, 244, 0.3);
        }
        
        .subject-header h2 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .subject-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-right: 4px solid;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card.primary { border-right-color: #4285F4; }
        .stat-card.success { border-right-color: #22c55e; }
        .stat-card.warning { border-right-color: #FFA726; }
        .stat-card.danger { border-right-color: #ef4444; }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            background: linear-gradient(135deg, #4285F4 0%, #22c55e 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .lesson-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }
        
        .lesson-item:hover {
            background: #f8f9fa;
        }
        
        .lesson-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .lesson-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .btn-review {
            padding: 6px 15px;
            background: #4285F4;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-review:hover {
            background: #3367d6;
            transform: translateY(-2px);
        }
        
        .teacher-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }
        
        .teacher-row:hover {
            background: #f8f9fa;
        }
        
        .teacher-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .performance-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .performance-fill {
            height: 100%;
            background: linear-gradient(90deg, #4285F4, #22c55e);
            transition: width 0.3s ease;
        }
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>مرحباً، <?php echo htmlspecialchars($user['name']); ?> 👋</h1>
                        <p>مشرف مادة <?php echo htmlspecialchars($subject['name']); ?></p>
                    </div>
                    <div class="header-actions">
                        <a href="../../public/logout.php" class="btn-logout">تسجيل الخروج</a>
                    </div>
                </div>
            </header>
            
            <!-- Subject Header -->
            <div class="subject-header">
                <h2>📚 مادة <?php echo htmlspecialchars($subject['name']); ?></h2>
                <p><?php echo htmlspecialchars($subject['description'] ?? 'إشراف على جميع دروس ومعلمي المادة'); ?></p>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">👨‍🏫</div>
                    <div class="stat-value"><?php echo $teachers_count; ?></div>
                    <div class="stat-label">المعلمون</div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">📖</div>
                    <div class="stat-value"><?php echo $lessons_count; ?></div>
                    <div class="stat-label">إجمالي الدروس</div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-value"><?php echo $pending_lessons; ?></div>
                    <div class="stat-label">قيد المراجعة</div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php echo $approved_lessons; ?></div>
                    <div class="stat-label">معتمدة</div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-icon">❌</div>
                    <div class="stat-value"><?php echo $rejected_lessons; ?></div>
                    <div class="stat-label">مرفوضة</div>
                </div>
                
                <div class="stat-card primary">
                    <div class="stat-icon">📨</div>
                    <div class="stat-value"><?php echo $unread_messages; ?></div>
                    <div class="stat-label">رسائل جديدة</div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="content-grid">
                <!-- Pending Lessons -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <span>⏳</span>
                            الدروس قيد المراجعة
                        </h2>
                        <span class="badge pending"><?php echo $pending_lessons; ?> درس</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_lessons_list)): ?>
                            <p style="text-align: center; color: #6b7280; padding: 30px;">
                                لا توجد دروس قيد المراجعة
                            </p>
                        <?php else: ?>
                            <?php foreach ($pending_lessons_list as $lesson): ?>
                                <div class="lesson-item">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                                        <a href="review-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn-review">
                                            مراجعة
                                        </a>
                                    </div>
                                    <div class="lesson-meta">
                                        <span>👨‍🏫 <?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                                        <span>🕐 <?php echo date('Y-m-d', strtotime($lesson['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Updates -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <span>🔔</span>
                            آخر التحديثات
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_updates)): ?>
                            <p style="text-align: center; color: #6b7280; padding: 30px;">
                                لا توجد تحديثات
                            </p>
                        <?php else: ?>
                            <?php foreach ($recent_updates as $lesson): ?>
                                <div class="lesson-item">
                                    <div class="lesson-title" style="font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($lesson['title']); ?>
                                    </div>
                                    <div class="lesson-meta">
                                        <span><?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                                        <span><?php echo date('Y-m-d', strtotime($lesson['updated_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Teachers Performance -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <span>📊</span>
                        أداء المعلمين
                    </h2>
                </div>
                <div class="card-body">
                    <?php if (empty($teachers_performance)): ?>
                        <p style="text-align: center; color: #6b7280; padding: 30px;">
                            لا يوجد معلمون في هذه المادة
                        </p>
                    <?php else: ?>
                        <div class="teacher-row" style="background: #f8f9fa; font-weight: 600;">
                            <div>المعلم</div>
                            <div style="text-align: center;">إجمالي الدروس</div>
                            <div style="text-align: center;">معتمد</div>
                            <div style="text-align: center;">قيد المراجعة</div>
                            <div style="text-align: center;">مرفوض</div>
                        </div>
                        <?php foreach ($teachers_performance as $teacher): ?>
                            <div class="teacher-row">
                                <div>
                                    <div class="teacher-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">
                                        <?php echo htmlspecialchars($teacher['email']); ?>
                                    </div>
                                    <?php 
                                    $approval_rate = $teacher['total_lessons'] > 0 
                                        ? ($teacher['approved_count'] / $teacher['total_lessons']) * 100 
                                        : 0;
                                    ?>
                                    <div class="performance-bar">
                                        <div class="performance-fill" style="width: <?php echo $approval_rate; ?>%"></div>
                                    </div>
                                </div>
                                <div style="text-align: center; font-size: 1.2rem; font-weight: 700; color: #1f2937;">
                                    <?php echo $teacher['total_lessons']; ?>
                                </div>
                                <div style="text-align: center; font-size: 1.1rem; font-weight: 600; color: #22c55e;">
                                    <?php echo $teacher['approved_count']; ?>
                                </div>
                                <div style="text-align: center; font-size: 1.1rem; font-weight: 600; color: #FFA726;">
                                    <?php echo $teacher['pending_count']; ?>
                                </div>
                                <div style="text-align: center; font-size: 1.1rem; font-weight: 600; color: #ef4444;">
                                    <?php echo $teacher['rejected_count']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
