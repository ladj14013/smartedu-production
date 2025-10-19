<?php
/**
 * Supervisor General Dashboard - Main Page
 * لوحة تحكم المشرف العام - الصفحة الرئيسية
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// التحقق من تسجيل الدخول والصلاحيات
requireLogin();
requireRole(['supervisor_general']);

// جلب معلومات المشرف
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// ========== إحصائيات شاملة ==========

// إجمالي المواد
$stmt = $pdo->query("SELECT COUNT(*) FROM subjects");
$total_subjects = $stmt->fetchColumn();

// إجمالي المعلمين
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'");
$total_teachers = $stmt->fetchColumn();

// إجمالي الطلاب
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$total_students = $stmt->fetchColumn();

// إجمالي الدروس
$stmt = $pdo->query("SELECT COUNT(*) FROM lessons");
$total_lessons = $stmt->fetchColumn();

// الدروس قيد المراجعة (pending)
$stmt = $pdo->query("SELECT COUNT(*) FROM lessons WHERE status = 'pending'");
$pending_lessons = $stmt->fetchColumn();

// الدروس المعتمدة
$stmt = $pdo->query("SELECT COUNT(*) FROM lessons WHERE status = 'approved'");
$approved_lessons = $stmt->fetchColumn();

// الدروس المرفوضة
$stmt = $pdo->query("SELECT COUNT(*) FROM lessons WHERE status = 'rejected'");
$rejected_lessons = $stmt->fetchColumn();

// إجمالي التمارين
$stmt = $pdo->query("SELECT COUNT(*) FROM exercises");
$total_exercises = $stmt->fetchColumn();

// الرسائل غير المقروءة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetchColumn();

// ========== أحدث الدروس قيد المراجعة ==========
$stmt = $pdo->prepare("
    SELECT l.*, u.name as teacher_name, s.name as subject_name 
    FROM lessons l
    JOIN users u ON l.teacher_id = u.id
    JOIN subjects s ON l.subject_id = s.id
    WHERE l.status = 'pending'
    ORDER BY l.created_at DESC
    LIMIT 10
");
$stmt->execute();
$pending_lessons_list = $stmt->fetchAll();

// ========== آخر المعلمين النشطين ==========
$stmt = $pdo->prepare("
    SELECT u.*, COUNT(l.id) as lessons_count,
           SUM(CASE WHEN l.status = 'approved' THEN 1 ELSE 0 END) as approved_count
    FROM users u
    LEFT JOIN lessons l ON u.id = l.teacher_id
    WHERE u.role = 'teacher'
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT 8
");
$stmt->execute();
$recent_teachers = $stmt->fetchAll();

// ========== إحصائيات حسب المادة ==========
$stmt = $pdo->query("
    SELECT s.name, s.id,
           COUNT(DISTINCT l.id) as lessons_count,
           COUNT(DISTINCT l.teacher_id) as teachers_count,
           SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) as pending_count
    FROM subjects s
    LEFT JOIN lessons l ON s.id = l.subject_id
    GROUP BY s.id
    ORDER BY lessons_count DESC
");
$subjects_stats = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة المشرف العام - SmartEdu</title>
    <link rel="stylesheet" href="/smartedu/assets/css/dashboard.css">
    <style>
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-right: 4px solid;
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-box.primary { border-right-color: #4285F4; }
        .stat-box.success { border-right-color: #22c55e; }
        .stat-box.warning { border-right-color: #FFA726; }
        .stat-box.danger { border-right-color: #ef4444; }
        .stat-box.info { border-right-color: #0ea5e9; }
        
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
        
        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
        
        .lesson-item:last-child {
            border-bottom: none;
        }
        
        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .lesson-title {
            font-weight: 600;
            color: #1f2937;
            font-size: 1rem;
        }
        
        .lesson-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .lesson-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
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
        
        .badge.approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-review {
            padding: 6px 15px;
            background: #4285F4;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .btn-review:hover {
            background: #3367d6;
            transform: translateY(-2px);
        }
        
        .teacher-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .teacher-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .teacher-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #4285F4, #22c55e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .teacher-details {
            display: flex;
            flex-direction: column;
        }
        
        .teacher-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .teacher-stats {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .subject-stats-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .subject-stats-table th,
        .subject-stats-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .subject-stats-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #1f2937;
        }
        
        .subject-stats-table tr:hover {
            background: #f8f9fa;
        }
        
        @media (max-width: 968px) {
            .section-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-overview {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>مرحباً، <?php echo htmlspecialchars($user['name']); ?> 👋</h1>
                        <p>لوحة تحكم المشرف العام</p>
                    </div>
                    <div class="header-actions">
                        <a href="/smartedu/public/logout.php" class="btn-logout">تسجيل الخروج</a>
                    </div>
                </div>
            </header>
            
            <!-- الإحصائيات الرئيسية -->
            <div class="stats-overview">
                <div class="stat-box primary">
                    <div class="stat-icon">📚</div>
                    <div class="stat-value"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">إجمالي المواد</div>
                </div>
                
                <div class="stat-box success">
                    <div class="stat-icon">👨‍🏫</div>
                    <div class="stat-value"><?php echo $total_teachers; ?></div>
                    <div class="stat-label">المعلمون</div>
                </div>
                
                <div class="stat-box info">
                    <div class="stat-icon">🎓</div>
                    <div class="stat-value"><?php echo $total_students; ?></div>
                    <div class="stat-label">الطلاب</div>
                </div>
                
                <div class="stat-box warning">
                    <div class="stat-icon">📖</div>
                    <div class="stat-value"><?php echo $total_lessons; ?></div>
                    <div class="stat-label">إجمالي الدروس</div>
                </div>
                
                <div class="stat-box danger">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-value"><?php echo $pending_lessons; ?></div>
                    <div class="stat-label">قيد المراجعة</div>
                </div>
                
                <div class="stat-box success">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php echo $approved_lessons; ?></div>
                    <div class="stat-label">دروس معتمدة</div>
                </div>
                
                <div class="stat-box danger">
                    <div class="stat-icon">❌</div>
                    <div class="stat-value"><?php echo $rejected_lessons; ?></div>
                    <div class="stat-label">دروس مرفوضة</div>
                </div>
                
                <div class="stat-box primary">
                    <div class="stat-icon">📨</div>
                    <div class="stat-value"><?php echo $unread_messages; ?></div>
                    <div class="stat-label">رسائل جديدة</div>
                </div>
            </div>
            
            <!-- القسم الرئيسي -->
            <div class="section-grid">
                <!-- الدروس قيد المراجعة -->
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
                                لا توجد دروس قيد المراجعة حالياً
                            </p>
                        <?php else: ?>
                            <?php foreach ($pending_lessons_list as $lesson): ?>
                                <div class="lesson-item">
                                    <div class="lesson-header">
                                        <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                                        <button class="btn-review" onclick="location.href='review-lesson.php?id=<?php echo $lesson['id']; ?>'">
                                            مراجعة
                                        </button>
                                    </div>
                                    <div class="lesson-meta">
                                        <span>👨‍🏫 <?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                                        <span>📚 <?php echo htmlspecialchars($lesson['subject_name']); ?></span>
                                        <span>🕐 <?php echo date('Y-m-d', strtotime($lesson['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- آخر المعلمين المسجلين -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <span>👨‍🏫</span>
                            آخر المعلمين المسجلين
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_teachers)): ?>
                            <p style="text-align: center; color: #6b7280; padding: 30px;">
                                لا يوجد معلمون مسجلون
                            </p>
                        <?php else: ?>
                            <?php foreach ($recent_teachers as $teacher): ?>
                                <div class="teacher-item">
                                    <div class="teacher-info">
                                        <div class="teacher-avatar">
                                            <?php echo mb_substr($teacher['name'], 0, 1); ?>
                                        </div>
                                        <div class="teacher-details">
                                            <div class="teacher-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                                            <div class="teacher-stats">
                                                <?php echo $teacher['lessons_count']; ?> دروس 
                                                (<?php echo $teacher['approved_count']; ?> معتمد)
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <span style="font-size: 0.85rem; color: #6b7280;">
                                            <?php echo date('Y-m-d', strtotime($teacher['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- إحصائيات المواد -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <span>📊</span>
                        إحصائيات المواد الدراسية
                    </h2>
                </div>
                <div class="card-body">
                    <table class="subject-stats-table">
                        <thead>
                            <tr>
                                <th>المادة</th>
                                <th>عدد الدروس</th>
                                <th>عدد المعلمين</th>
                                <th>قيد المراجعة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjects_stats)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #6b7280; padding: 30px;">
                                        لا توجد مواد مسجلة
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subjects_stats as $subject): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($subject['name']); ?></strong>
                                        </td>
                                        <td><?php echo $subject['lessons_count']; ?></td>
                                        <td><?php echo $subject['teachers_count']; ?></td>
                                        <td>
                                            <?php if ($subject['pending_count'] > 0): ?>
                                                <span class="badge pending"><?php echo $subject['pending_count']; ?></span>
                                            <?php else: ?>
                                                <span style="color: #6b7280;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="subject-details.php?id=<?php echo $subject['id']; ?>" 
                                               style="color: #4285F4; text-decoration: none; font-size: 0.9rem;">
                                                عرض التفاصيل →
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
