<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// إعادة توجيه المستخدمين المسجلين بالفعل
if (is_logged_in()) {
    header("Location: ../dashboard/index.php");
    exit();
}

$error = '';
$success = '';

// جلب المراحل والمستويات والمواد للنموذج
$stages = [];
$levels = [];
$subjects = [];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // جلب المراحل
    $stmt = $db->query("SELECT * FROM stages ORDER BY `order`, id");
    $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المستويات
    $stmt = $db->query("SELECT l.*, s.name as stage_name FROM levels l LEFT JOIN stages s ON l.stage_id = s.id ORDER BY s.`order`, l.`order`");
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المواد مع اسم المرحلة
    $stmt = $db->query("
        SELECT s.*, st.name as stage_name 
        FROM subjects s 
        LEFT JOIN stages st ON s.stage_id = st.id 
        ORDER BY st.`order`, s.name
    ");
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // في حالة وجود خطأ، عرضه للمطور
    // $error = 'خطأ في تحميل البيانات: ' . $e->getMessage();
    $stages = [];
    $levels = [];
    $subjects = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = sanitize_input($_POST['nom'] ?? '');
    $prenom = sanitize_input($_POST['prenom'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? 'etudiant');
    
    // حقول إضافية حسب نوع المستخدم
    $stage_id = isset($_POST['stage_id']) && $_POST['stage_id'] !== '' ? intval($_POST['stage_id']) : null;
    $level_id = isset($_POST['level_id']) && $_POST['level_id'] !== '' ? intval($_POST['level_id']) : null;
    $subject_id = isset($_POST['subject_id']) && $_POST['subject_id'] !== '' ? intval($_POST['subject_id']) : null;
    
    // DEBUG - للاختبار فقط (احذف هذا لاحقاً)
    if ($role === 'etudiant' && isset($_GET['debug'])) {
        echo "<div style='background: #fff3cd; padding: 20px; margin: 20px; border: 2px solid #ffc107; border-radius: 10px; direction: rtl;'>";
        echo "<h3>🔍 معلومات التصحيح (Debug):</h3>";
        echo "<strong>POST Data:</strong><br>";
        echo "stage_id من POST: " . ($_POST['stage_id'] ?? 'غير موجود') . "<br>";
        echo "level_id من POST: " . ($_POST['level_id'] ?? 'غير موجود') . "<br>";
        echo "<br><strong>بعد المعالجة:</strong><br>";
        echo "stage_id = " . ($stage_id === null ? 'NULL' : $stage_id) . "<br>";
        echo "level_id = " . ($level_id === null ? 'NULL' : $level_id) . "<br>";
        echo "<br><strong>الفحص:</strong><br>";
        echo "stage_id === null? " . ($stage_id === null ? 'نعم' : 'لا') . "<br>";
        echo "level_id === null? " . ($level_id === null ? 'نعم' : 'لا') . "<br>";
        echo "</div>";
    }
    
    // حقول إضافية حسب نوع المستخدم (السطر المكرر تم حذفه)
    $subject_id = isset($_POST['subject_id']) && $_POST['subject_id'] !== '' ? intval($_POST['subject_id']) : null;
    $subject_id = isset($_POST['subject_id']) && $_POST['subject_id'] !== '' ? intval($_POST['subject_id']) : null;
    
    // التحقق من البيانات الأساسية
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $error = 'الرجاء إدخال جميع الحقول المطلوبة.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صحيح.';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.';
    } elseif ($password !== $confirm_password) {
        $error = 'كلمات المرور غير متطابقة.';
    }
    // التحقق من الحقول الإضافية حسب نوع المستخدم
    elseif ($role === 'enseignant' && ($stage_id === null || $subject_id === null)) {
        $error = 'الأستاذ يجب عليه اختيار المرحلة الدراسية والمادة التي يدرسها.';
    } elseif ($role === 'etudiant' && ($stage_id === null || $level_id === null)) {
        $error = 'الطالب يجب عليه اختيار المرحلة الدراسية والسنة الدراسية.';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // التحقق من عدم وجود البريد الإلكتروني مسبقاً
            $check_query = "SELECT id FROM users WHERE email = :email LIMIT 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error = 'البريد الإلكتروني مسجل مسبقاً. الرجاء استخدام بريد إلكتروني آخر.';
            } else {
                // تشفير كلمة المرور
                $hashed_password = hash_password($password);
                
                // دمج الاسم الكامل
                $full_name = $nom . ' ' . $prenom;
                
                // إيجاد أكبر قيمة ID موجودة حالياً
                $max_id_query = "SELECT MAX(id) as max_id FROM users";
                $max_id_stmt = $db->query($max_id_query);
                $max_id = $max_id_stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
                $new_id = $max_id + 1;

                // إدراج المستخدم الجديد
                $insert_query = "INSERT INTO users (id, nom, prenom, name, email, password, role, stage_id, level_id, subject_id, created_at) 
                                VALUES (:id, :nom, :prenom, :name, :email, :password, :role, :stage_id, :level_id, :subject_id, NOW())";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':id', $new_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':nom', $nom);
                $insert_stmt->bindParam(':prenom', $prenom);
                $insert_stmt->bindParam(':name', $full_name);
                $insert_stmt->bindParam(':email', $email);
                $insert_stmt->bindParam(':password', $hashed_password);
                $insert_stmt->bindParam(':role', $role);
                $insert_stmt->bindParam(':stage_id', $stage_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':level_id', $level_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
                
                if ($insert_stmt->execute()) {
                    $success = 'تم إنشاء الحساب بنجاح! يمكنك الآن تسجيل الدخول.';
                    // إعادة تعيين المتغيرات
                    $nom = $prenom = $email = '';
                } else {
                    $error = 'حدث خطأ أثناء إنشاء الحساب. حاول مرة أخرى.';
                }
            }
        } catch (PDOException $e) {
            $error = 'حدث خطأ في الاتصال: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب جديد - Smart Education Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-page">
    <!-- Debug: عرض عدد المراحل المجلوبة -->
    <!-- Stages: <?php echo count($stages); ?>, Levels: <?php echo count($levels); ?>, Subjects: <?php echo count($subjects); ?> -->
    
    <div class="auth-container">
        <div class="auth-card signup-card">
            <div class="auth-header">
                <div class="logo">
                    <h1>Smart Education</h1>
                </div>
                <h2>إنشاء حساب جديد</h2>
                <p>أدخل بياناتك للبدء في رحلتك التعليمية</p>
            </div>
            
            <div class="auth-body">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <span><?php echo $success; ?></span>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="../public/login.php" class="btn btn-primary">
                            الانتقال لتسجيل الدخول
                        </a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="auth-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nom">الاسم العائلي</label>
                                <input 
                                    type="text" 
                                    id="nom" 
                                    name="nom" 
                                    placeholder="أدخل اسمك العائلي" 
                                    required
                                    value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="prenom">الاسم الشخصي</label>
                                <input 
                                    type="text" 
                                    id="prenom" 
                                    name="prenom" 
                                    placeholder="أدخل اسمك الشخصي" 
                                    required
                                    value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>"
                                >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">البريد الإلكتروني</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="m@example.com" 
                                required
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        <div class="form-group">
                            <label for="role">نوع الحساب</label>
                            <select id="role" name="role" required onchange="toggleRoleFields()">
                                <option value="etudiant" <?php echo (isset($_POST['role']) && $_POST['role'] === 'etudiant') ? 'selected' : ''; ?>>
                                    طالب 👨‍🎓
                                </option>
                                <option value="enseignant" <?php echo (isset($_POST['role']) && $_POST['role'] === 'enseignant') ? 'selected' : ''; ?>>
                                    أستاذ 👨‍🏫
                                </option>
                                <option value="parent" <?php echo (isset($_POST['role']) && $_POST['role'] === 'parent') ? 'selected' : ''; ?>>
                                    ولي أمر 👨‍👩‍👦
                                </option>
                            </select>
                            <small style="color: #6b7280; font-size: 0.875rem; margin-top: 5px; display: block;">
                                لا يمكن تغيير نوع الحساب بعد التسجيل
                            </small>
                        </div>
                        
                        <!-- حقول خاصة بالطالب -->
                        <div id="student-fields" style="display: none;">
                            <div class="form-group">
                                <label for="student_stage">المرحلة الدراسية <span style="color: red;">*</span></label>
                                <select id="student_stage" name="stage_id" onchange="updateLevels(this.value)">
                                    <option value="">-- اختر المرحلة --</option>
                                    <?php foreach ($stages as $stage): ?>
                                        <option value="<?php echo $stage['id']; ?>" <?php echo (isset($_POST['stage_id']) && $_POST['stage_id'] == $stage['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($stage['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="student_level">السنة الدراسية <span style="color: red;">*</span></label>
                                <select id="student_level" name="level_id">
                                    <option value="">-- اختر السنة الدراسية --</option>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>" data-stage="<?php echo $level['stage_id']; ?>" <?php echo (isset($_POST['level_id']) && $_POST['level_id'] == $level['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($level['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- حقول خاصة بالأستاذ -->
                        <div id="teacher-fields" style="display: none;">
                            <div class="form-group">
                                <label for="teacher_stage">المرحلة الدراسية <span style="color: red;">*</span></label>
                                <select id="teacher_stage" name="stage_id" onchange="updateTeacherSubjects(this.value)">
                                    <option value="">-- اختر المرحلة --</option>
                                    <?php foreach ($stages as $stage): ?>
                                        <option value="<?php echo $stage['id']; ?>" <?php echo (isset($_POST['stage_id']) && $_POST['stage_id'] == $stage['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($stage['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="teacher_subject">المادة التي تدرسها <span style="color: red;">*</span></label>
                                <select id="teacher_subject" name="subject_id">
                                    <option value="">-- اختر المادة --</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>" 
                                                data-stage="<?php echo $subject['stage_id']; ?>"
                                                <?php echo (isset($_POST['subject_id']) && $_POST['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color: #6b7280; font-size: 0.875rem; margin-top: 5px; display: block;">
                                    اختر المرحلة أولاً لعرض المواد المتاحة
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">كلمة المرور</label>
                            <div class="password-input-wrapper">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password"
                                    placeholder="على الأقل 6 أحرف" 
                                    required
                                    minlength="6"
                                >
                                <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                    <svg id="eye-icon-password" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">تأكيد كلمة المرور</label>
                            <div class="password-input-wrapper">
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password"
                                    placeholder="أعد إدخال كلمة المرور" 
                                    required
                                    minlength="6"
                                >
                                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                    <svg id="eye-icon-confirm" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 10px;">
                            <label class="checkbox-container">
                                <input type="checkbox" required>
                                <span class="checkmark"></span>
                                <span class="checkbox-label">
                                    أوافق على <a href="#" style="color: #667eea; text-decoration: underline;">الشروط والأحكام</a>
                                </span>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            إنشاء الحساب
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="auth-footer">
                لديك حساب بالفعل؟ 
                <a href="../public/login.php">تسجيل الدخول</a>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/auth.js"></script>
    <script>
        // تبديل كلمة المرور
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById('eye-icon-' + fieldId.replace('_', '-'));
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                field.type = 'password';
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }
        
        // إظهار/إخفاء الحقول حسب نوع المستخدم
        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            const studentFields = document.getElementById('student-fields');
            const teacherFields = document.getElementById('teacher-fields');
            
            const studentStage = document.getElementById('student_stage');
            const studentLevel = document.getElementById('student_level');
            const teacherStage = document.getElementById('teacher_stage');
            const teacherSubject = document.getElementById('teacher_subject');
            
            // إخفاء جميع الحقول أولاً وتعطيلها
            studentFields.style.display = 'none';
            teacherFields.style.display = 'none';
            
            studentStage.removeAttribute('required');
            studentLevel.removeAttribute('required');
            teacherStage.removeAttribute('required');
            teacherSubject.removeAttribute('required');
            
            studentStage.disabled = true;
            studentLevel.disabled = true;
            teacherStage.disabled = true;
            teacherSubject.disabled = true;
            
            // إظهار الحقول المناسبة وتفعيلها
            if (role === 'etudiant') {
                studentFields.style.display = 'block';
                studentStage.setAttribute('required', 'required');
                studentLevel.setAttribute('required', 'required');
                studentStage.disabled = false;
                studentLevel.disabled = false;
            } else if (role === 'enseignant') {
                teacherFields.style.display = 'block';
                teacherStage.setAttribute('required', 'required');
                teacherSubject.setAttribute('required', 'required');
                teacherStage.disabled = false;
                teacherSubject.disabled = false;
            }
        }
        
        // تحديث قائمة المستويات عند تغيير المرحلة (للطالب)
        function updateLevels(stageId) {
            const levelSelect = document.getElementById('student_level');
            const allOptions = levelSelect.querySelectorAll('option');
            
            // إخفاء جميع الخيارات
            allOptions.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                } else if (option.getAttribute('data-stage') === stageId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // إعادة تعيين القيمة
            levelSelect.value = '';
        }
        
        // تحديث قائمة المواد عند تغيير المرحلة (للأستاذ)
        function updateTeacherSubjects(stageId) {
            const subjectSelect = document.getElementById('teacher_subject');
            const allOptions = subjectSelect.querySelectorAll('option');
            
            // إخفاء جميع الخيارات
            allOptions.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                } else if (option.getAttribute('data-stage') === stageId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // إعادة تعيين القيمة
            subjectSelect.value = '';
        }
        
        // تشغيل عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            toggleRoleFields();
            
            // إذا كان هناك قيمة محددة للمرحلة (بعد إعادة التحميل بسبب خطأ)
            const studentStage = document.getElementById('student_stage');
            if (studentStage.value) {
                updateLevels(studentStage.value);
            }
            
            const teacherStage = document.getElementById('teacher_stage');
            if (teacherStage.value) {
                updateTeacherSubjects(teacherStage.value);
            }
        });
    </script>
</body>
</html>
