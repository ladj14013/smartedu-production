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
$teacher_code = $_SESSION['teacher_code'] ?? null;

// جلب كود الأستاذ إذا لم يكن في الجلسة
if (!$teacher_code) {
    $stmt = $pdo->prepare("SELECT teacher_code FROM users WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher_data = $stmt->fetch();
    $teacher_code = $teacher_data['teacher_code'] ?? null;
    if ($teacher_code) {
        $_SESSION['teacher_code'] = $teacher_code;
    }
}

// جلب الطلاب المرتبطين من جدول student_teacher_links
$query = "SELECT u.id, 
                 CONCAT(u.nom, ' ', u.prenom) as name,
                 u.email, 
                 stl.linked_at as created_at, 
                 s.name as stage_name, 
                 l.name as level_name,
                 COUNT(DISTINCT sp.id) as completed_lessons,
                 AVG(sa.score) as avg_score
          FROM student_teacher_links stl
          JOIN users u ON stl.student_id = u.id
          LEFT JOIN stages s ON u.stage_id = s.id
          LEFT JOIN levels l ON u.level_id = l.id
          LEFT JOIN student_progress sp ON u.id = sp.student_id
          LEFT JOIN student_answers sa ON u.id = sa.student_id AND sa.score IS NOT NULL
          WHERE stl.teacher_id = :teacher_id AND stl.status = 'active'
          GROUP BY u.id, u.nom, u.prenom, u.email, stl.linked_at, s.name, l.name
          ORDER BY stl.linked_at DESC";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلابي - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/rtl-sidebar.css">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --teacher-primary: #4CAF50;
            --teacher-secondary: #45a049;
            --teacher-light: #e8f5e9;
        }
        
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
            color: var(--teacher-primary);
            font-size: 2.2rem;
        }
        
        .page-header p {
            color: #666;
            margin: 0;
        }
        
        .students-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .student-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .student-card:hover {
            transform: translateY(-5px);
        }
        
        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--teacher-light);
            padding-bottom: 15px;
        }
        
        .student-info h3 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 1.2rem;
        }
        
        .student-email {
            color: #666;
            font-size: 0.9rem;
        }
        
        .student-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .meta-item {
            background: var(--teacher-light);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }
        
        .meta-label {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .meta-value {
            color: var(--teacher-primary);
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .teacher-code {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .code-display {
            background: var(--teacher-light);
            padding: 20px;
            border-radius: 10px;
            margin-top: 15px;
            border: 2px dashed var(--teacher-primary);
        }
        
        .code-value {
            font-size: 2rem;
            color: var(--teacher-primary);
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .empty-state {
            background: white;
            border-radius: 15px;
            padding: 60px 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--teacher-primary);
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .score-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .score-badge.good {
            background: var(--teacher-light);
            color: var(--teacher-primary);
        }
        
        .score-badge.average {
            background: #fff3e0;
            color: #ff9800;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-right: 0 !important;
                padding: 15px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .student-meta {
                grid-template-columns: 1fr;
            }
            
            .code-value {
                font-size: 1.5rem;
            }
            
            .teacher-code {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>👥 طلابي</h1>
                <p>الطلاب المرتبطون بحسابك</p>
            </div>
            
            <!-- كود المعلم -->
            <div class="teacher-code">
                <h2>🔑 كود الربط الخاص بك</h2>
                <p>شارك هذا الكود مع طلابك ليتمكنوا من الربط بحسابك</p>
                <div class="code-display">
                    <div class="code-value"><?php echo $teacher_code; ?></div>
                </div>
            </div>

            <?php if (empty($students)): ?>
                <!-- حالة عدم وجود طلاب -->
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h3>لا يوجد طلاب مرتبطون بعد</h3>
                    <p>بمجرد ربط الطلاب بحسابك باستخدام الكود أعلاه، ستظهر معلوماتهم هنا</p>
                </div>
            <?php else: ?>
                    <div class="students-grid">
                    <?php foreach ($students as $student): ?>
                        <div class="student-card">
                            <div class="student-header">
                                <div class="student-info">
                                    <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                                    <div class="student-email"><?php echo htmlspecialchars($student['email']); ?></div>
                                </div>
                            </div>
                            
                            <div class="student-meta">
                                <div class="meta-item">
                                    <div class="meta-label">المرحلة/المستوى</div>
                                    <div class="meta-value">
                                        <?php if ($student['stage_name']): ?>
                                            <?php echo htmlspecialchars($student['stage_name']); ?>
                                            <br>
                                            <small style="font-size: 0.8em; color: #666;">
                                                <?php echo htmlspecialchars($student['level_name']); ?>
                                            </small>
                                        <?php else: ?>
                                            غير محدد
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="meta-item">
                                    <div class="meta-label">الدروس المكتملة</div>
                                    <div class="meta-value">
                                        <?php echo $student['completed_lessons']; ?> درس
                                    </div>
                                </div>
                                
                                <div class="meta-item">
                                    <div class="meta-label">متوسط الدرجات</div>
                                    <div class="meta-value">
                                        <?php if ($student['avg_score']): ?>
                                            <span class="score-badge <?php echo $student['avg_score'] >= 70 ? 'good' : 'average'; ?>">
                                                <?php echo round($student['avg_score'], 1); ?>%
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="meta-item">
                                    <div class="meta-label">تاريخ الانضمام</div>
                                    <div class="meta-value" style="font-size: 0.9rem;">
                                        <?php echo date('Y/m/d', strtotime($student['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
        </main>
    </div>
    
    <style>
        .table-container {
            overflow-x: auto;
        }
    </style>
</body>
</html>
