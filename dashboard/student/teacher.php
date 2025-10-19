<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('student');

$student_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

// جلب معلومات الطالب
$query = "SELECT connected_teacher_code FROM users WHERE id = :student_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$student = $stmt->fetch();

$connected_teacher = null;
$success = '';
$error = '';

// جلب معلومات المعلم إذا كان مرتبطاً
if ($student['connected_teacher_code']) {
    $query = "SELECT id, name, email, subject_id FROM users 
              WHERE teacher_code = :teacher_code AND role = 'teacher'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_code', $student['connected_teacher_code']);
    $stmt->execute();
    $connected_teacher = $stmt->fetch();
    
    // جلب اسم المادة
    if ($connected_teacher && $connected_teacher['subject_id']) {
        $query = "SELECT name FROM subjects WHERE id = :subject_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':subject_id', $connected_teacher['subject_id']);
        $stmt->execute();
        $subject = $stmt->fetch();
        $connected_teacher['subject_name'] = $subject['name'] ?? 'غير محدد';
    }
}

// معالجة الربط بمعلم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_code'])) {
    $teacher_code = strtoupper(trim($_POST['teacher_code']));
    
    if (empty($teacher_code)) {
        $error = 'يجب إدخال كود المعلم.';
    } else {
        // التحقق من وجود المعلم
        $query = "SELECT id FROM users WHERE teacher_code = :teacher_code AND role = 'teacher'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':teacher_code', $teacher_code);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // ربط الطالب بالمعلم
            $query = "UPDATE users SET connected_teacher_code = :teacher_code WHERE id = :student_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':teacher_code', $teacher_code);
            $stmt->bindParam(':student_id', $student_id);
            
            if ($stmt->execute()) {
                $_SESSION['connected_teacher_code'] = $teacher_code;
                $success = 'تم الربط بالمعلم بنجاح! ✅';
                header("Refresh:1");
            } else {
                $error = 'حدث خطأ في الربط بالمعلم.';
            }
        } else {
            $error = 'كود المعلم غير صحيح. تأكد من الكود وحاول مرة أخرى.';
        }
    }
}

// معالجة إلغاء الربط
if (isset($_GET['disconnect']) && $_GET['disconnect'] == '1') {
    $query = "UPDATE users SET connected_teacher_code = NULL WHERE id = :student_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    
    if ($stmt->execute()) {
        unset($_SESSION['connected_teacher_code']);
        $success = 'تم إلغاء الربط بنجاح.';
        header("Refresh:1; url=teacher.php");
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معلمي - Smart Education Hub</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>👨‍🏫 معلمي</h1>
                <p>اربط حسابك بمعلمك لمتابعة أفضل</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($connected_teacher): ?>
                <!-- المعلم المرتبط -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">✅ أنت مرتبط بمعلم</h2>
                    </div>
                    <div class="card-body">
                        <div class="teacher-card">
                            <div class="teacher-avatar">
                                <div style="width: 80px; height: 80px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                    👨‍🏫
                                </div>
                            </div>
                            <div class="teacher-info">
                                <h3><?php echo htmlspecialchars($connected_teacher['name']); ?></h3>
                                <p>
                                    <span class="badge badge-primary">
                                        <?php echo htmlspecialchars($connected_teacher['subject_name'] ?? 'غير محدد'); ?>
                                    </span>
                                </p>
                                <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                                    📧 <?php echo htmlspecialchars($connected_teacher['email']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                            <h3>📌 ملاحظة:</h3>
                            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                                الآن يمكنك الوصول إلى الدروس الخاصة التي ينشرها المعلم 
                                <?php echo htmlspecialchars($connected_teacher['name']); ?>.
                                يمكنه أيضاً متابعة تقدمك وتقييم إجاباتك.
                            </p>
                            
                            <a href="?disconnect=1" class="btn btn-secondary" style="margin-top: 1rem;" 
                               onclick="return confirm('هل أنت متأكد من إلغاء الربط بهذا المعلم؟')">
                                إلغاء الربط
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- نموذج الربط -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🔗 ربط حسابك بمعلم</h2>
                        <p class="card-description">
                            اطلب كود المعلم منه وأدخله هنا للحصول على متابعة شخصية ودروس خاصة
                        </p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" style="max-width: 500px;">
                            <div class="form-group">
                                <label for="teacher_code">كود المعلم</label>
                                <input 
                                    type="text" 
                                    id="teacher_code" 
                                    name="teacher_code" 
                                    placeholder="مثال: T12345678"
                                    required
                                    style="text-transform: uppercase;"
                                >
                                <small style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">
                                    الكود عبارة عن حرف T يتبعه 8 أرقام/حروف
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                ربط الحساب
                            </button>
                        </form>
                        
                        <div class="info-box" style="margin-top: 2rem; padding: 1.5rem; background: rgba(66, 133, 244, 0.05); border-radius: 0.5rem; border-right: 4px solid var(--primary);">
                            <h3 style="margin: 0 0 1rem 0;">💡 فوائد الربط بمعلم:</h3>
                            <ul style="margin: 0; padding-right: 1.5rem;">
                                <li>الوصول إلى الدروس الخاصة</li>
                                <li>متابعة شخصية لتقدمك</li>
                                <li>تقييم مخصص لإجاباتك</li>
                                <li>ملاحظات وتوجيهات مباشرة</li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <style>
        .teacher-card {
            display: flex;
            gap: 2rem;
            align-items: start;
        }
        
        .teacher-info h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
        }
        
        .teacher-info p {
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .teacher-card {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }
        }
    </style>
</body>
</html>
