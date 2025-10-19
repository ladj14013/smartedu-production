<?php
/**
 * Parent Dashboard - Notifications
 * لوحة تحكم ولي الأمر - الإشعارات
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['parent']);

$user_id = $_SESSION['user_id'];

// جلب معلومات ولي الأمر
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$parent = $stmt->fetch();

// جلب قائمة الأبناء
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE parent_id = ? AND role = 'student'");
$stmt->execute([$user_id]);
$children = $stmt->fetchAll();
$child_ids = array_column($children, 'id');

// إنشاء إشعارات تلقائية من نشاطات الأبناء
$notifications = [];

if (!empty($child_ids)) {
    $placeholders = str_repeat('?,', count($child_ids) - 1) . '?';
    
    // إشعارات التمارين الجديدة (آخر 7 أيام)
    $stmt = $pdo->prepare("
        SELECT er.*, u.name as student_name, e.title as exercise_title,
               l.title as lesson_title, s.name as subject_name, s.icon
        FROM exercises_results er
        JOIN users u ON er.student_id = u.id
        JOIN exercises e ON er.exercise_id = e.id
        JOIN lessons l ON e.lesson_id = l.id
        JOIN subjects s ON l.subject_id = s.id
        WHERE er.student_id IN ($placeholders)
        AND er.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY er.submitted_at DESC
    ");
    $stmt->execute($child_ids);
    $recent_exercises = $stmt->fetchAll();
    
    foreach ($recent_exercises as $ex) {
        $score = $ex['score'];
        
        // تصنيف الإشعار حسب الأداء
        if ($score >= 80) {
            $type = 'success';
            $icon = '🏆';
            $message = "حصل {$ex['student_name']} على نتيجة ممتازة ({$score}%) في {$ex['exercise_title']}";
        } elseif ($score >= 60) {
            $type = 'info';
            $icon = '✅';
            $message = "أكمل {$ex['student_name']} تمرين {$ex['exercise_title']} بنتيجة جيدة ({$score}%)";
        } elseif ($score >= 40) {
            $type = 'warning';
            $icon = '⚠️';
            $message = "حصل {$ex['student_name']} على نتيجة متوسطة ({$score}%) في {$ex['exercise_title']}";
        } else {
            $type = 'danger';
            $icon = '❌';
            $message = "يحتاج {$ex['student_name']} للمساعدة - نتيجة ({$score}%) في {$ex['exercise_title']}";
        }
        
        $notifications[] = [
            'type' => $type,
            'icon' => $icon,
            'title' => "{$ex['subject_name']} - {$ex['lesson_title']}",
            'message' => $message,
            'date' => $ex['submitted_at'],
            'student_name' => $ex['student_name'],
            'category' => 'exercise'
        ];
    }
}

// إشعارات الرسائل غير المقروءة
$stmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name, u.role as sender_role
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = ? AND m.is_read = 0 AND m.subject LIKE '%إشعار%'
    ORDER BY m.created_at DESC
");
$stmt->execute([$user_id]);
$message_notifications = $stmt->fetchAll();

foreach ($message_notifications as $msg) {
    $notifications[] = [
        'type' => 'info',
        'icon' => '📬',
        'title' => 'رسالة جديدة',
        'message' => "رسالة من {$msg['sender_name']}: {$msg['subject']}",
        'date' => $msg['created_at'],
        'student_name' => null,
        'category' => 'message'
    ];
}

// ترتيب الإشعارات حسب التاريخ
usort($notifications, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// تصفية الإشعارات
$filter_type = $_GET['filter'] ?? 'all';
$filter_student = $_GET['student'] ?? 'all';

$filtered_notifications = array_filter($notifications, function($notif) use ($filter_type, $filter_student) {
    $type_match = ($filter_type === 'all' || $notif['type'] === $filter_type);
    $student_match = ($filter_student === 'all' || $notif['student_name'] === $filter_student);
    return $type_match && $student_match;
});

// إحصائيات
$total_notifications = count($notifications);
$today_notifications = count(array_filter($notifications, function($n) {
    return date('Y-m-d', strtotime($n['date'])) === date('Y-m-d');
}));
$success_count = count(array_filter($notifications, fn($n) => $n['type'] === 'success'));
$warning_count = count(array_filter($notifications, fn($n) => $n['type'] === 'warning'));
$danger_count = count(array_filter($notifications, fn($n) => $n['type'] === 'danger'));

$total_children = count($children);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإشعارات - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.3);
        }
        
        .page-header h1 { font-size: 2rem; margin-bottom: 10px; }
        .page-header p { opacity: 0.95; font-size: 1.1rem; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-right: 4px solid;
            text-align: center;
        }
        
        .stat-card.purple { border-right-color: #8b5cf6; }
        .stat-card.blue { border-right-color: #4285F4; }
        .stat-card.green { border-right-color: #22c55e; }
        .stat-card.orange { border-right-color: #FFA726; }
        .stat-card.red { border-right-color: #ef4444; }
        
        .stat-icon { font-size: 2.5rem; margin-bottom: 10px; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: #1f2937; margin-bottom: 5px; }
        .stat-label { color: #6b7280; font-size: 0.9rem; }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-label {
            font-weight: 600;
            color: #1f2937;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 180px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .notifications-list {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            border-right: 4px solid;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
            transform: translateX(-5px);
        }
        
        .notification-item.success { border-right-color: #22c55e; background: #f0fdf4; }
        .notification-item.info { border-right-color: #4285F4; background: #eff6ff; }
        .notification-item.warning { border-right-color: #FFA726; background: #fffbeb; }
        .notification-item.danger { border-right-color: #ef4444; background: #fef2f2; }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .notification-icon {
            font-size: 2rem;
            margin-left: 15px;
            float: right;
        }
        
        .notification-title {
            font-weight: 700;
            color: #1f2937;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        
        .notification-message {
            color: #4b5563;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .notification-meta {
            display: flex;
            gap: 20px;
            font-size: 0.85rem;
            color: #9ca3af;
        }
        
        .notification-date {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .notification-student {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #8b5cf6;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }
        
        .empty-state-icon { font-size: 4rem; margin-bottom: 20px; }
        
        @media (max-width: 968px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1>🔔 الإشعارات</h1>
                <p>تنبيهات حول أداء ونشاطات الأبناء</p>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card purple">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value"><?php echo $total_notifications; ?></div>
                    <div class="stat-label">إجمالي الإشعارات</div>
                </div>
                
                <div class="stat-card blue">
                    <div class="stat-icon">📅</div>
                    <div class="stat-value"><?php echo $today_notifications; ?></div>
                    <div class="stat-label">إشعارات اليوم</div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-value"><?php echo $success_count; ?></div>
                    <div class="stat-label">إنجازات</div>
                </div>
                
                <div class="stat-card orange">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-value"><?php echo $warning_count; ?></div>
                    <div class="stat-label">تحذيرات</div>
                </div>
                
                <div class="stat-card red">
                    <div class="stat-icon">❌</div>
                    <div class="stat-value"><?php echo $danger_count; ?></div>
                    <div class="stat-label">يحتاج متابعة</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <span class="filter-label">🔍 تصفية:</span>
                <select class="filter-select" id="filterType" onchange="applyFilter()">
                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>جميع الأنواع</option>
                    <option value="success" <?php echo $filter_type === 'success' ? 'selected' : ''; ?>>إنجازات</option>
                    <option value="info" <?php echo $filter_type === 'info' ? 'selected' : ''; ?>>معلومات</option>
                    <option value="warning" <?php echo $filter_type === 'warning' ? 'selected' : ''; ?>>تحذيرات</option>
                    <option value="danger" <?php echo $filter_type === 'danger' ? 'selected' : ''; ?>>يحتاج متابعة</option>
                </select>
                
                <select class="filter-select" id="filterStudent" onchange="applyFilter()">
                    <option value="all" <?php echo $filter_student === 'all' ? 'selected' : ''; ?>>جميع الأبناء</option>
                    <?php foreach ($children as $child): ?>
                        <option value="<?php echo htmlspecialchars($child['name']); ?>" 
                                <?php echo $filter_student === $child['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($child['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Notifications List -->
            <?php if (empty($filtered_notifications)): ?>
                <div class="notifications-list">
                    <div class="empty-state">
                        <div class="empty-state-icon">🔕</div>
                        <h3>لا توجد إشعارات</h3>
                        <p>لم يتم العثور على إشعارات مطابقة للفلاتر المحددة</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="notifications-list">
                    <?php foreach ($filtered_notifications as $notif): ?>
                        <div class="notification-item <?php echo $notif['type']; ?>">
                            <span class="notification-icon"><?php echo $notif['icon']; ?></span>
                            
                            <div class="notification-header">
                                <div>
                                    <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                </div>
                            </div>
                            
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notif['message']); ?>
                            </div>
                            
                            <div class="notification-meta">
                                <span class="notification-date">
                                    🕒 
                                    <?php 
                                    $date = new DateTime($notif['date']);
                                    $now = new DateTime();
                                    $diff = $now->diff($date);
                                    
                                    if ($diff->days == 0) {
                                        if ($diff->h == 0) {
                                            echo $diff->i . ' دقيقة';
                                        } else {
                                            echo $diff->h . ' ساعة';
                                        }
                                    } elseif ($diff->days == 1) {
                                        echo 'أمس';
                                    } else {
                                        echo $date->format('Y-m-d');
                                    }
                                    ?>
                                </span>
                                
                                <?php if ($notif['student_name']): ?>
                                    <span class="notification-student">
                                        👤 <?php echo htmlspecialchars($notif['student_name']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function applyFilter() {
            const type = document.getElementById('filterType').value;
            const student = document.getElementById('filterStudent').value;
            window.location.href = '?filter=' + type + '&student=' + encodeURIComponent(student);
        }
    </script>
</body>
</html>
