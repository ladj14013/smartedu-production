<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/auth.php';
require_once '../../config/database.php';
requireLogin();
requireRole(['superviseur_matiere', 'supervisor_subject', 'subject_supervisor']);

global $pdo;

$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, s.name as subject_name FROM users u 
          LEFT JOIN subjects s ON u.subject_id = s.id 
          WHERE u.id = :user_id";
$stmt = $pdo->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supervisor['subject_id']) {
    die('لم يتم تعيين مادة لهذا المشرف');
}

$subject_id = $supervisor['subject_id'];

// Get all students studying this subject
$query = "SELECT DISTINCT u.id, u.name, CONCAT(u.nom, ' ', u.prenom) as full_name, u.email, 
          st.name as stage_name, lv.name as level_name,
          (SELECT COUNT(DISTINCT l.id) FROM lessons l 
           JOIN student_progress lp ON l.id = lp.lesson_id 
           WHERE lp.student_id = u.id AND l.subject_id = :subject_id) as lessons_started,
          (SELECT COUNT(DISTINCT l.id) FROM lessons l 
           JOIN student_progress lp ON l.id = lp.lesson_id 
           WHERE lp.student_id = u.id AND l.subject_id = :subject_id2 
           AND lp.completion_date IS NOT NULL) as lessons_completed,
          (SELECT COUNT(DISTINCT sa.id) FROM student_answers sa
           JOIN exercises e ON sa.exercise_id = e.id
           JOIN lessons l ON e.lesson_id = l.id
           WHERE sa.student_id = u.id AND l.subject_id = :subject_id3) as exercises_done,
          (SELECT AVG(sa.score) FROM student_answers sa
           JOIN exercises e ON sa.exercise_id = e.id
           JOIN lessons l ON e.lesson_id = l.id
           WHERE sa.student_id = u.id AND l.subject_id = :subject_id4) as avg_score
          FROM users u
          LEFT JOIN stages st ON u.stage_id = st.id
          LEFT JOIN levels lv ON u.level_id = lv.id
          WHERE u.role IN ('etudiant', 'student')
          AND EXISTS (
              SELECT 1 FROM student_progress lp
              JOIN lessons l ON lp.lesson_id = l.id
              WHERE lp.student_id = u.id AND l.subject_id = :subject_id5
          )
          ORDER BY full_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute([
    ':subject_id' => $subject_id,
    ':subject_id2' => $subject_id,
    ':subject_id3' => $subject_id,
    ':subject_id4' => $subject_id,
    ':subject_id5' => $subject_id
]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_students = count($students);
$active_students = 0;
$total_avg_score = 0;
$students_with_scores = 0;

foreach ($students as $student) {
    if ($student['lessons_started'] > 0) {
        $active_students++;
    }
    if ($student['avg_score'] !== null) {
        $total_avg_score += $student['avg_score'];
        $students_with_scores++;
    }
}

$overall_avg_score = $students_with_scores > 0 ? $total_avg_score / $students_with_scores : 0;
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الطلاب - <?php echo htmlspecialchars($supervisor['subject_name']); ?></title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-card.blue i { color: #3498db; }
        .stat-card.green i { color: #2ecc71; }
        .stat-card.orange i { color: #e67e22; }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
            color: #2c3e50;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.95rem;
        }

        .students-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .table-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .table-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .filter-section {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .search-box button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th {
            padding: 15px;
            text-align: right;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .student-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .student-details h4 {
            margin: 0 0 5px 0;
            font-size: 0.95rem;
            color: #2c3e50;
        }

        .student-details p {
            margin: 0;
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s;
        }

        .score-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .score-excellent { background: #d4edda; color: #155724; }
        .score-good { background: #d1ecf1; color: #0c5460; }
        .score-average { background: #fff3cd; color: #856404; }
        .score-poor { background: #f8d7da; color: #721c24; }

        .no-students {
            padding: 60px 20px;
            text-align: center;
            color: #7f8c8d;
        }

        .no-students i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> الطلاب</h1>
            <p>إدارة ومتابعة طلاب مادة <?php echo htmlspecialchars($supervisor['subject_name']); ?></p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <i class="fas fa-users"></i>
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">إجمالي الطلاب</div>
            </div>

            <div class="stat-card green">
                <i class="fas fa-user-check"></i>
                <div class="stat-value"><?php echo $active_students; ?></div>
                <div class="stat-label">الطلاب النشطين</div>
            </div>

            <div class="stat-card orange">
                <i class="fas fa-chart-line"></i>
                <div class="stat-value"><?php echo number_format($overall_avg_score, 1); ?>%</div>
                <div class="stat-label">متوسط الدرجات</div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="students-table">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> قائمة الطلاب</h2>
            </div>

            <div class="filter-section">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="ابحث عن طالب بالاسم أو البريد الإلكتروني...">
                </div>
            </div>

            <?php if (empty($students)): ?>
                <div class="no-students">
                    <i class="fas fa-user-slash"></i>
                    <h3>لا يوجد طلاب حالياً</h3>
                    <p>لم يقم أي طالب بدراسة دروس هذه المادة بعد</p>
                </div>
            <?php else: ?>
                <table id="studentsTable">
                    <thead>
                        <tr>
                            <th>الطالب</th>
                            <th>المرحلة / المستوى</th>
                            <th>الدروس المبدوءة</th>
                            <th>الدروس المكتملة</th>
                            <th>التمارين المنجزة</th>
                            <th>متوسط الدرجات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            $completion_rate = $student['lessons_started'] > 0 
                                ? ($student['lessons_completed'] / $student['lessons_started']) * 100 
                                : 0;
                            
                            $score = $student['avg_score'] ?? 0;
                            $score_class = 'score-poor';
                            if ($score >= 85) $score_class = 'score-excellent';
                            elseif ($score >= 70) $score_class = 'score-good';
                            elseif ($score >= 50) $score_class = 'score-average';
                            
                            $initials = '';
                            $name_parts = explode(' ', $student['full_name']);
                            foreach ($name_parts as $part) {
                                if (!empty($part)) {
                                    $initials .= mb_substr($part, 0, 1);
                                    if (mb_strlen($initials) >= 2) break;
                                }
                            }
                        ?>
                            <tr class="student-row">
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                        <div class="student-details">
                                            <h4><?php echo htmlspecialchars($student['full_name']); ?></h4>
                                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($student['stage_name'] || $student['level_name']): ?>
                                        <div>
                                            <?php if ($student['stage_name']): ?>
                                                <div><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($student['stage_name']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($student['level_name']): ?>
                                                <div style="font-size: 0.85rem; color: #7f8c8d; margin-top: 3px;">
                                                    <i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($student['level_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #95a5a6;">غير محدد</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #3498db;"><?php echo $student['lessons_started']; ?></strong> دروس
                                </td>
                                <td>
                                    <div>
                                        <strong style="color: #2ecc71;"><?php echo $student['lessons_completed']; ?></strong> 
                                        / <?php echo $student['lessons_started']; ?>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: #9b59b6;"><?php echo $student['exercises_done']; ?></strong> تمرين
                                </td>
                                <td>
                                    <?php if ($student['avg_score'] !== null): ?>
                                        <span class="score-badge <?php echo $score_class; ?>">
                                            <?php echo number_format($student['avg_score'], 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #95a5a6;">لا توجد درجات</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.student-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
