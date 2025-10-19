<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// تمكين عرض الأخطاء
ini_set('display_errors', 1);
error_reporting(E_ALL);

// التحقق من تسجيل الدخول والصلاحيات
requireLogin();
requireRole(['parent']);

// الحصول على معرف الابن
$child_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$child_id) {
    header('Location: children.php');
    exit();
}

global $pdo;

// التحقق من أن الابن مرتبط بولي الأمر الحالي (مؤقتاً نتخطى هذا الفحص)
// سنفترض أن ولي الأمر يمكنه رؤية أي طالب مؤقتاً
/*
$check_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM parent_children 
    WHERE parent_id = ? AND child_id = ?
");
$check_stmt->execute([$_SESSION['user_id'], $child_id]);

if ($check_stmt->fetchColumn() == 0) {
    header('Location: children.php');
    exit();
}
*/

// جلب معلومات الابن
$child_stmt = $pdo->prepare("
    SELECT u.*, s.name as stage_name, l.name as level_name
    FROM users u
    LEFT JOIN stages s ON u.stage_id = s.id
    LEFT JOIN levels l ON u.level_id = l.id
    WHERE u.id = ? AND u.role = 'etudiant'
");
$child_stmt->execute([$child_id]);
$child = $child_stmt->fetch(PDO::FETCH_ASSOC);

if (!$child) {
    header('Location: children.php');
    exit();
}

// إحصائيات الابن
// 1. عدد الدروس المكتملة
$completed_lessons_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT lesson_id) as count
    FROM student_progress
    WHERE student_id = ? AND completion_date IS NOT NULL
");
$completed_lessons_stmt->execute([$child_id]);
$completed_lessons = $completed_lessons_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 2. عدد التمارين المحلولة
$solved_exercises_stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM student_answers
    WHERE student_id = ?
");
$solved_exercises_stmt->execute([$child_id]);
$solved_exercises = $solved_exercises_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 3. متوسط الدرجات
$avg_score_stmt = $pdo->prepare("
    SELECT AVG(score) as avg_score
    FROM student_answers
    WHERE student_id = ? AND score IS NOT NULL
");
$avg_score_stmt->execute([$child_id]);
$avg_score = $avg_score_stmt->fetch(PDO::FETCH_ASSOC)['avg_score'] ?? 0;

// 4. آخر الدروس المكتملة
$recent_lessons_stmt = $pdo->prepare("
    SELECT l.title, l.id, sp.completion_date, s.name as subject_name
    FROM student_progress sp
    JOIN lessons l ON sp.lesson_id = l.id
    JOIN subjects s ON l.subject_id = s.id
    WHERE sp.student_id = ? AND sp.completion_date IS NOT NULL
    ORDER BY sp.completion_date DESC
    LIMIT 5
");
$recent_lessons_stmt->execute([$child_id]);
$recent_lessons = $recent_lessons_stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. آخر التمارين المحلولة مع الدرجات
$recent_exercises_stmt = $pdo->prepare("
    SELECT 
        sa.id,
        e.question,
        l.title as lesson_title,
        s.name as subject_name,
        sa.score,
        sa.ai_feedback
    FROM student_answers sa
    JOIN exercises e ON sa.exercise_id = e.id
    JOIN lessons l ON e.lesson_id = l.id
    JOIN subjects s ON l.subject_id = s.id
    WHERE sa.student_id = ?
    ORDER BY sa.id DESC
    LIMIT 10
");
$recent_exercises_stmt->execute([$child_id]);
$recent_exercises = $recent_exercises_stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. التقدم حسب المواد مع معلومات المعلمين المرتبطين
$subjects_progress_stmt = $pdo->prepare("
    SELECT 
        s.id as subject_id,
        s.name as subject_name,
        COUNT(DISTINCT l.id) as total_lessons,
        COUNT(DISTINCT sp.lesson_id) as completed_lessons,
        AVG(sa.score) as avg_score,
        stl.teacher_id,
        CONCAT(t.nom, ' ', t.prenom) as teacher_name,
        t.role as teacher_role,
        t.accept_messages,
        stl.status as link_status
    FROM subjects s
    LEFT JOIN lessons l ON s.id = l.subject_id AND l.level_id = ?
    LEFT JOIN student_progress sp ON l.id = sp.lesson_id AND sp.student_id = ? AND sp.completion_date IS NOT NULL
    LEFT JOIN exercises ex ON l.id = ex.lesson_id
    LEFT JOIN student_answers sa ON ex.id = sa.exercise_id AND sa.student_id = ?
    LEFT JOIN student_teacher_links stl ON (s.id = stl.subject_id AND stl.student_id = ? AND stl.status = 'active')
    LEFT JOIN users t ON stl.teacher_id = t.id
    WHERE s.stage_id = ?
    GROUP BY s.id, s.name, stl.teacher_id, t.nom, t.prenom, t.role, t.accept_messages, stl.status
    ORDER BY s.name
");
$subjects_progress_stmt->execute([$child['level_id'], $child_id, $child_id, $child_id, $child['stage_id']]);
$subjects_progress = $subjects_progress_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "تفاصيل " . $child['nom'] . " " . $child['prenom'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- رأس الصفحة -->
        <header class="page-header">
            <div class="header-content">
                <div>
                    <h1><?php echo $page_title; ?></h1>
                    <p>تفاصيل شاملة عن أداء وتقدم الطالب</p>
                </div>
                <div>
                    <a href="children.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                </div>
            </div>
        </header>

        <!-- معلومات الطالب -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user"></i> معلومات الطالب</h2>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <strong>الاسم الكامل:</strong>
                        <span><?php echo htmlspecialchars($child['nom'] . ' ' . $child['prenom']); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>البريد الإلكتروني:</strong>
                        <span><?php echo htmlspecialchars($child['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>المرحلة:</strong>
                        <span><?php echo htmlspecialchars($child['stage_name'] ?? 'غير محدد'); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>المستوى:</strong>
                        <span><?php echo htmlspecialchars($child['level_name'] ?? 'غير محدد'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- بطاقات الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $completed_lessons; ?></h3>
                    <p>دروس مكتملة</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-pencil-alt"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $solved_exercises; ?></h3>
                    <p>تمارين محلولة</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo number_format($avg_score, 1); ?>%</h3>
                    <p>متوسط الدرجات</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-details">
                    <h3><?php 
                        if ($avg_score >= 90) echo 'ممتاز';
                        elseif ($avg_score >= 75) echo 'جيد جداً';
                        elseif ($avg_score >= 60) echo 'جيد';
                        else echo 'مقبول';
                    ?></h3>
                    <p>التقييم العام</p>
                </div>
            </div>
        </div>

        <!-- التقدم حسب المواد -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-bar"></i> التقدم حسب المواد</h2>
            </div>
            <div class="card-body">
                <?php if (empty($subjects_progress)): ?>
                    <p class="text-muted">لا توجد بيانات متاحة حالياً</p>
                <?php else: ?>
                    <div class="subjects-progress-list">
                        <?php 
                        $role_ar = [
                            'enseignant' => 'معلم',
                            'supervisor_subject' => 'مشرف مادة'
                        ];
                        
                        foreach ($subjects_progress as $subject): 
                            $total = $subject['total_lessons'];
                            $completed = $subject['completed_lessons'];
                            $percentage = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
                            $subject_avg = $subject['avg_score'] ?? 0;
                        ?>
                            <div class="subject-progress-item">
                                <div class="subject-info">
                                    <h4 style="margin: 0;"><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                                    <p><?php echo $completed; ?> من <?php echo $total; ?> دروس | متوسط: <?php echo number_format($subject_avg, 1); ?>%</p>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $percentage; ?>%;">
                                        <?php echo $percentage; ?>%
                                    </div>
                                </div>
                                
                                <?php if ($subject['teacher_id'] && $subject['link_status'] === 'active' && $subject['accept_messages']): ?>
                                    <a href="compose-message.php?teacher_id=<?php echo $subject['teacher_id']; ?>&subject_name=<?php echo urlencode($subject['subject_name']); ?>&child_id=<?php echo $child_id; ?>" 
                                       class="btn-message-bottom" 
                                       title="إرسال رسالة إلى <?php echo $role_ar[$subject['teacher_role']] ?? $subject['teacher_role']; ?> <?php echo htmlspecialchars($subject['subject_name']); ?>">
                                        <i class="fas fa-envelope"></i>
                                        <span class="message-tooltip-bottom">
                                            راسل <?php echo $role_ar[$subject['teacher_role']] ?? $subject['teacher_role']; ?>
                                        </span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- آخر الدروس المكتملة -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-check-circle"></i> آخر الدروس المكتملة</h2>
            </div>
            <div class="card-body">
                <?php if (empty($recent_lessons)): ?>
                    <p class="text-muted">لم يكمل أي دروس بعد</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>عنوان الدرس</th>
                                    <th>المادة</th>
                                    <th>تاريخ الإكمال</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_lessons as $lesson): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                                        <td><?php echo htmlspecialchars($lesson['subject_name']); ?></td>
                                        <td><?php echo date('Y/m/d', strtotime($lesson['completion_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- آخر التمارين المحلولة -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-clipboard-list"></i> آخر التمارين المحلولة</h2>
            </div>
            <div class="card-body">
                <?php if (empty($recent_exercises)): ?>
                    <p class="text-muted">لم يحل أي تمارين بعد</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>الدرس</th>
                                    <th>المادة</th>
                                    <th>الدرجة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_exercises as $exercise): 
                                    $score = $exercise['score'] ?? 0;
                                    $badge_class = $score >= 90 ? 'badge-success' : ($score >= 75 ? 'badge-warning' : 'badge-danger');
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exercise['lesson_title']); ?></td>
                                        <td><?php echo htmlspecialchars($exercise['subject_name']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo number_format($score, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <button onclick="showFeedback(<?php echo $exercise['id']; ?>)" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> عرض التقييم
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .info-item {
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-item strong {
            display: block;
            color: #667eea;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .info-item span {
            color: #2d3748;
            font-size: 1rem;
        }

        .subjects-progress-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .subject-progress-item {
            background: #f8f9fa;
            padding: 1.25rem;
            border-radius: 10px;
            position: relative;
            margin-bottom: 1rem;
        }

        .subject-info h4 {
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .subject-info p {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .btn-message-bottom {
            position: absolute;
            bottom: 15px;
            left: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
            z-index: 10;
        }

        .btn-message-bottom:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.6);
        }

        .btn-message-bottom i {
            font-size: 18px;
        }

        .message-tooltip-bottom {
            position: absolute;
            bottom: 50px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .message-tooltip-bottom::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.85);
        }

        .btn-message-bottom:hover .message-tooltip-bottom {
            opacity: 1;
        }

        /* Old styles - keeping for compatibility */
        .btn-message {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
        }

        .btn-message:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.5);
        }

        .btn-message i {
            font-size: 16px;
        }

        .message-tooltip {
            position: absolute;
            bottom: -35px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .btn-message:hover .message-tooltip {
            opacity: 1;
        }

        .progress-bar-container {
            background: #e2e8f0;
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.85rem;
            transition: width 0.3s ease;
        }

        .text-muted {
            color: #718096;
            text-align: center;
            padding: 2rem;
            font-style: italic;
        }

        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-success {
            background: #48bb78;
            color: white;
        }

        .badge-warning {
            background: #ed8936;
            color: white;
        }

        .badge-danger {
            background: #f56565;
            color: white;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }

        .btn-info {
            background: #4299e1;
            color: white;
        }

        .btn-info:hover {
            background: #3182ce;
        }
    </style>

    <script>
        function showFeedback(answerId) {
            // يمكن تطوير هذا لاحقاً لعرض نافذة منبثقة بالتقييم
            alert('سيتم عرض تفاصيل التقييم قريباً');
        }
    </script>
</body>
</html>
