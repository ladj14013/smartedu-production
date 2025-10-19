<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// التحقق من تسجيل الدخول كتلميذ
if (!has_any_role(['etudiant', 'student'])) {
    header('Location: ../../public/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$db = getDB();

$success_message = '';
$error_message = '';

// معالجة إدخال الكود
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_code'])) {
    $teacher_code = trim(strtoupper($_POST['teacher_code']));
    
    if (empty($teacher_code)) {
        $error_message = "الرجاء إدخال كود الأستاذ";
    } else {
        // البحث عن الأستاذ بالكود
        $teacher_stmt = $db->prepare("
            SELECT id, CONCAT(nom, ' ', prenom) as name, subject_id 
            FROM users 
            WHERE teacher_code = ? AND role IN ('enseignant', 'teacher')
        ");
        $teacher_stmt->execute([$teacher_code]);
        $teacher = $teacher_stmt->fetch();
        
        if (!$teacher) {
            $error_message = "كود الأستاذ غير صحيح أو غير موجود";
        } else {
            // التحقق من عدم وجود ربط سابق
            $check_stmt = $db->prepare("
                SELECT id FROM student_teacher_links 
                WHERE student_id = ? AND teacher_id = ? AND status = 'active'
            ");
            $check_stmt->execute([$student_id, $teacher['id']]);
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = "أنت مرتبط بالفعل بهذا الأستاذ";
            } else {
                // إنشاء الربط
                $link_stmt = $db->prepare("
                    INSERT INTO student_teacher_links 
                    (student_id, teacher_id, subject_id, status, linked_at) 
                    VALUES (?, ?, ?, 'active', NOW())
                ");
                
                if ($link_stmt->execute([$student_id, $teacher['id'], $teacher['subject_id']])) {
                    $success_message = "تم الربط بالأستاذ " . htmlspecialchars($teacher['name']) . " بنجاح! يمكنك الآن الوصول إلى دروسه الخاصة.";
                } else {
                    $error_message = "حدث خطأ أثناء الربط، الرجاء المحاولة مرة أخرى";
                }
            }
        }
    }
}

// جلب قائمة الأساتذة المرتبطين
$linked_teachers_stmt = $db->prepare("
    SELECT 
        stl.id as link_id,
        stl.linked_at,
        CONCAT(u.nom, ' ', u.prenom) as teacher_name,
        s.name as subject_name
    FROM student_teacher_links stl
    JOIN users u ON stl.teacher_id = u.id
    LEFT JOIN subjects s ON stl.subject_id = s.id
    WHERE stl.student_id = ? AND stl.status = 'active'
    ORDER BY stl.linked_at DESC
");
$linked_teachers_stmt->execute([$student_id]);
$linked_teachers = $linked_teachers_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ربط بأستاذ - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        @media (max-width: 968px) {
            .main-content {
                margin-right: 0 !important;
                padding: 20px;
            }
        }
    </style>
    <link rel="stylesheet" href="link-teacher-mobile.css">
    <style>
        .link-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
        }

        .link-form-card {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            border-radius: 15px;
            padding: 40px;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        .link-form-card h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .link-form-card p {
            opacity: 0.9;
            margin-bottom: 25px;
            font-size: 15px;
            line-height: 1.6;
        }

        .code-input-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .code-input {
            flex: 1;
            padding: 15px 20px;
            font-size: 24px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            background: rgba(255,255,255,0.1);
            color: white;
            text-align: center;
            letter-spacing: 4px;
            font-family: 'Courier New', monospace;
            text-transform: uppercase;
        }

        .code-input::placeholder {
            color: rgba(255,255,255,0.5);
            letter-spacing: normal;
        }

        .code-input:focus {
            outline: none;
            background: rgba(255,255,255,0.2);
            border-color: white;
        }

        .btn-link {
            padding: 15px 40px;
            background: white;
            color: #2196F3;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .btn-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,255,255,0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .linked-teachers {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .linked-teachers h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .teacher-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .teacher-card:hover {
            border-color: #2196F3;
            transform: translateX(-5px);
        }

        .teacher-info {
            flex: 1;
        }

        .teacher-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .teacher-subject {
            color: #666;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .teacher-date {
            color: #999;
            font-size: 13px;
        }

        .teacher-badge {
            background: #2196F3;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }

        .instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .instructions h4 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .instructions ul {
            margin: 0;
            padding-right: 20px;
            color: #856404;
        }

        .instructions li {
            margin-bottom: 8px;
            line-height: 1.6;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="link-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    ✓ <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    ✗ <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="instructions">
                <h4>📌 كيف تربط نفسك بأستاذ؟</h4>
                <ul>
                    <li>احصل على <strong>كود التسجيل</strong> من أستاذك</li>
                    <li>أدخل الكود في الحقل أدناه</li>
                    <li>بعد الربط ستتمكن من رؤية <strong>الدروس الخاصة</strong> بالأستاذ</li>
                    <li>يمكنك الربط بعدة أساتذة لمواد مختلفة</li>
                </ul>
            </div>

            <div class="link-form-card">
                <h2>🔗 ربط بأستاذ جديد</h2>
                <p>
                    أدخل كود التسجيل الذي حصلت عليه من أستاذك للارتباط به والوصول إلى دروسه الخاصة
                </p>
                
                <form method="POST">
                    <div class="code-input-group">
                        <input 
                            type="text" 
                            name="teacher_code" 
                            class="code-input" 
                            placeholder="أدخل الكود هنا"
                            maxlength="50"
                            required
                            autocomplete="off"
                        >
                        <button type="submit" class="btn-link">
                            ✓ ربط الآن
                        </button>
                    </div>
                </form>
            </div>

            <div class="linked-teachers">
                <h3>👨‍🏫 الأساتذة المرتبطون</h3>
                
                <?php if (count($linked_teachers) > 0): ?>
                    <?php foreach ($linked_teachers as $teacher): ?>
                        <div class="teacher-card">
                            <div class="teacher-info">
                                <div class="teacher-name">
                                    👨‍🏫 <?php echo htmlspecialchars($teacher['teacher_name']); ?>
                                </div>
                                <div class="teacher-subject">
                                    📚 <?php echo htmlspecialchars($teacher['subject_name'] ?? 'غير محدد'); ?>
                                </div>
                                <div class="teacher-date">
                                    تم الربط في: <?php echo date('Y/m/d', strtotime($teacher['linked_at'])); ?>
                                </div>
                            </div>
                            <div class="teacher-badge">
                                مرتبط ✓
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <p>لم تقم بالربط بأي أستاذ بعد</p>
                        <p style="font-size: 14px; color: #bbb;">استخدم النموذج أعلاه للربط بأستاذك</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
