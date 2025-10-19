<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['enseignant', 'teacher']);

global $pdo;
$teacher_id = $_SESSION['user_id'];

// جلب معلومات الأستاذ
$teacher_stmt = $pdo->prepare("
    SELECT u.name, u.teacher_code, s.name as subject_name
    FROM users u
    LEFT JOIN subjects s ON u.subject_id = s.id
    WHERE u.id = ?
");
$teacher_stmt->execute([$teacher_id]);
$teacher = $teacher_stmt->fetch();

// جلب قائمة التلاميذ المرتبطين (الذين استخدموا كود الأستاذ)
$students_stmt = $pdo->prepare("
    SELECT 
        u.id as student_id,
        u.name as student_name,
        u.email as student_email,
        u.created_at as linked_at,
        l.name as level_name,
        st.name as stage_name,
        COUNT(DISTINCT sp.lesson_id) as completed_lessons,
        AVG(sp.score) as avg_score
    FROM users u
    LEFT JOIN levels l ON u.level_id = l.id
    LEFT JOIN stages st ON u.stage_id = st.id
    LEFT JOIN student_progress sp ON u.id = sp.student_id
    WHERE u.connected_teacher_code = ? AND u.role = 'etudiant'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$students_stmt->execute([$teacher['teacher_code']]);
$students = $students_stmt->fetchAll();

$total_students = count($students);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تلاميذي - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .students-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 30px;
        }

        .page-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
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

        .page-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
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
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
        }

        .students-table {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .students-table h2 {
            margin: 0 0 25px 0;
            color: #333;
            font-size: 24px;
        }

        .student-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .student-card:hover {
            border-color: #4CAF50;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .student-basic-info {
            flex: 1;
        }

        .student-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-email {
            color: #666;
            font-size: 14px;
        }

        .student-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .meta-badge {
            background: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #666;
            border: 1px solid #dee2e6;
        }

        .student-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .mini-stat {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .mini-stat .mini-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }

        .mini-stat .mini-label {
            font-size: 12px;
            color: #666;
        }

        .linked-date {
            font-size: 12px;
            color: #999;
            margin-top: 10px;
            text-align: center;
            padding-top: 10px;
            border-top: 1px solid #e9ecef;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .empty-state .hint {
            font-size: 14px;
            color: #bbb;
        }

        .btn-share-code {
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-share-code:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .no-code-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="students-container">
            <div class="page-header">
                <h1>🎓 تلاميذي المرتبطون</h1>
                <p>قائمة التلاميذ الذين قاموا بالربط معك باستخدام كودك الخاص</p>
            </div>

            <?php if (!$teacher['teacher_code']): ?>
                <div class="no-code-warning">
                    <span style="font-size: 24px;">⚠️</span>
                    <div style="flex: 1;">
                        لم تقم بتوليد كود بعد. قم بزيارة صفحة "كودي الخاص" لتوليد كود وشاركه مع تلاميذك.
                    </div>
                    <a href="my-code.php" class="btn-share-code">
                        🔑 توليد كود
                    </a>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon">👥</div>
                    <div class="value"><?php echo $total_students; ?></div>
                    <div class="label">إجمالي التلاميذ</div>
                </div>
                <div class="stat-card">
                    <div class="icon">📚</div>
                    <div class="value"><?php echo $teacher['subject_name'] ?? '-'; ?></div>
                    <div class="label">المادة</div>
                </div>
                <div class="stat-card">
                    <div class="icon">🔑</div>
                    <div class="value"><?php echo $teacher['teacher_code'] ? substr($teacher['teacher_code'], 0, 4) . '...' : '-'; ?></div>
                    <div class="label">كودك</div>
                </div>
            </div>

            <div class="students-table">
                <h2>📋 قائمة التلاميذ</h2>
                
                <?php if (count($students) > 0): ?>
                    <?php foreach ($students as $student): ?>
                        <div class="student-card">
                            <div class="student-header">
                                <div class="student-basic-info">
                                    <div class="student-name">
                                        <span>🎓</span>
                                        <span><?php echo htmlspecialchars($student['student_name']); ?></span>
                                    </div>
                                    <div class="student-email">
                                        ✉️ <?php echo htmlspecialchars($student['student_email']); ?>
                                    </div>
                                </div>
                                <div class="student-meta">
                                    <span class="meta-badge">
                                        📚 <?php echo htmlspecialchars($student['level_name'] ?? 'غير محدد'); ?>
                                    </span>
                                    <span class="meta-badge">
                                        🏫 <?php echo htmlspecialchars($student['stage_name'] ?? 'غير محدد'); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="student-stats">
                                <div class="mini-stat">
                                    <div class="mini-value"><?php echo $student['completed_lessons'] ?? 0; ?></div>
                                    <div class="mini-label">دروس مكتملة</div>
                                </div>
                                <div class="mini-stat">
                                    <div class="mini-value">
                                        <?php 
                                        $avg = $student['avg_score'] ?? 0;
                                        echo $avg > 0 ? number_format($avg, 1) . '%' : '-';
                                        ?>
                                    </div>
                                    <div class="mini-label">المعدل</div>
                                </div>
                            </div>

                            <div class="linked-date">
                                📅 تم الربط في: <?php echo date('Y/m/d H:i', strtotime($student['linked_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <p>لا يوجد تلاميذ مرتبطون بك بعد</p>
                        <p class="hint">شارك كودك الخاص مع تلاميذك لكي يتمكنوا من الارتباط بك</p>
                        <?php if ($teacher['teacher_code']): ?>
                            <a href="my-code.php" class="btn-share-code" style="margin-top: 20px;">
                                🔑 عرض كودي
                            </a>
                        <?php else: ?>
                            <a href="my-code.php" class="btn-share-code" style="margin-top: 20px;">
                                🔑 توليد كود
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
