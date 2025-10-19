<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('directeur');

$database = new Database();
$db = $database->getConnection();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

if ($user_id === 0) {
    header("Location: users.php");
    exit();
}

// جلب بيانات المستخدم
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php");
    exit();
}

// جلب المراحل والمستويات والمواد
$stages = $db->query("SELECT * FROM stages ORDER BY `order`")->fetchAll();
$levels = $db->query("SELECT * FROM levels ORDER BY `order`")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

// معالجة التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $role = sanitize_input($_POST['role']);
    $stage_id = !empty($_POST['stage_id']) ? (int)$_POST['stage_id'] : null;
    $level_id = !empty($_POST['level_id']) ? (int)$_POST['level_id'] : null;
    $subject_id = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
    
    if (empty($name) || empty($email) || empty($role)) {
        $error = 'الاسم والبريد الإلكتروني والدور مطلوبة';
    } else {
        // التحقق من عدم تكرار البريد الإلكتروني
        $query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':email' => $email, ':user_id' => $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'البريد الإلكتروني مستخدم بالفعل من قبل مستخدم آخر';
        } else {
            try {
                $query = "UPDATE users SET name = :name, email = :email, role = :role, 
                          stage_id = :stage_id, level_id = :level_id, subject_id = :subject_id,
                          updated_at = NOW()
                          WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':role' => $role,
                    ':stage_id' => $stage_id,
                    ':level_id' => $level_id,
                    ':subject_id' => $subject_id,
                    ':user_id' => $user_id
                ]);
                
                // تحديث كلمة المرور إذا تم إدخال واحدة جديدة
                if (!empty($_POST['new_password'])) {
                    $new_password = hash_password($_POST['new_password']);
                    $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :user_id");
                    $stmt->execute([':password' => $new_password, ':user_id' => $user_id]);
                }
                
                $success = 'تم تحديث بيانات المستخدم بنجاح!';
                
                // إعادة جلب البيانات المحدثة
                $stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
                $stmt->execute([':user_id' => $user_id]);
                $user = $stmt->fetch();
                
            } catch (PDOException $e) {
                $error = 'خطأ في تحديث البيانات: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المستخدم: <?php echo htmlspecialchars($user['name']); ?> - Smart Education Hub</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .breadcrumb a {
            color: #4285F4;
            text-decoration: none;
        }
        
        .user-info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .user-info-box h2 {
            margin: 0 0 0.5rem 0;
        }
        
        .user-info-box p {
            margin: 0;
            opacity: 0.9;
        }
        
        .form-section {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 1.5rem 0;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4285F4;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }
        
        .form-group input:disabled {
            background: #f9fafb;
            color: #6b7280;
            cursor: not-allowed;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .role-dependent {
            display: none;
        }
        
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #1e40af;
        }
        
        .info-box strong {
            display: block;
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="users.php">إدارة المستخدمين</a>
                <span>›</span>
                <strong>تعديل مستخدم</strong>
            </div>
            
            <!-- User Info -->
            <div class="user-info-box">
                <h2>👤 تعديل بيانات المستخدم</h2>
                <p>معرف المستخدم: #<?php echo $user['id']; ?> • تاريخ التسجيل: <?php echo date('Y-m-d', strtotime($user['created_at'])); ?></p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 2rem;">
                    ✓ <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 2rem;">
                    ✗ <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <!-- المعلومات الأساسية -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span>📋</span>
                        المعلومات الأساسية
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">الاسم الكامل *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($user['name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">البريد الإلكتروني *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                            <small style="color: #6b7280; margin-top: 0.25rem; display: block;">
                                تأكد من صحة البريد الإلكتروني
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">الدور *</label>
                            <select id="role" name="role" required onchange="handleRoleChange()">
                                <option value="">اختر الدور</option>
                                <option value="etudiant" <?php echo $user['role'] == 'etudiant' ? 'selected' : ''; ?>>تلميذ (Étudiant)</option>
                                <option value="student" <?php echo $user['role'] == 'student' ? 'selected' : ''; ?>>طالب (Student)</option>
                                <option value="enseignant" <?php echo $user['role'] == 'enseignant' ? 'selected' : ''; ?>>أستاذ (Enseignant)</option>
                                <option value="teacher" <?php echo $user['role'] == 'teacher' ? 'selected' : ''; ?>>معلم (Teacher)</option>
                                <option value="directeur" <?php echo $user['role'] == 'directeur' ? 'selected' : ''; ?>>مدير (Directeur)</option>
                                <option value="superviseur_general" <?php echo $user['role'] == 'superviseur_general' ? 'selected' : ''; ?>>مشرف عام (Superviseur Général)</option>
                                <option value="supervisor_general" <?php echo $user['role'] == 'supervisor_general' ? 'selected' : ''; ?>>مشرف عام (Supervisor General)</option>
                                <option value="superviseur_matiere" <?php echo $user['role'] == 'superviseur_matiere' ? 'selected' : ''; ?>>مشرف مادة (Superviseur Matière)</option>
                                <option value="supervisor_subject" <?php echo $user['role'] == 'supervisor_subject' ? 'selected' : ''; ?>>مشرف مادة (Supervisor Subject)</option>
                                <option value="parent" <?php echo $user['role'] == 'parent' ? 'selected' : ''; ?>>ولي أمر (Parent)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- معلومات الدراسة (للطلاب والمعلمين) -->
                <div class="form-section" id="educationFields">
                    <h3 class="form-section-title">
                        <span>🎓</span>
                        معلومات الدراسة
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="stage_id">المرحلة الدراسية</label>
                            <select id="stage_id" name="stage_id" onchange="loadLevels(this.value)">
                                <option value="">اختر المرحلة</option>
                                <?php foreach ($stages as $stage): ?>
                                    <option value="<?php echo $stage['id']; ?>" <?php echo $user['stage_id'] == $stage['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stage['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="levelField">
                            <label for="level_id">المستوى الدراسي</label>
                            <select id="level_id" name="level_id">
                                <option value="">اختر المستوى</option>
                                <?php foreach ($levels as $level): ?>
                                    <?php if ($level['stage_id'] == $user['stage_id']): ?>
                                        <option value="<?php echo $level['id']; ?>" <?php echo $user['level_id'] == $level['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($level['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="subjectField">
                            <label for="subject_id">المادة (للمعلمين ومشرفي المواد)</label>
                            <select id="subject_id" name="subject_id">
                                <option value="">اختر المادة</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo $user['subject_id'] == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: #6b7280; margin-top: 0.25rem; display: block;">
                                <strong>هام:</strong> يجب تحديد المادة عند ترقية أستاذ إلى مشرف مادة
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- تغيير كلمة المرور -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <span>🔒</span>
                        تغيير كلمة المرور
                    </h3>
                    
                    <div class="info-box">
                        <strong>ملاحظة:</strong>
                        اترك هذا الحقل فارغًا إذا كنت لا تريد تغيير كلمة المرور
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">كلمة المرور الجديدة</label>
                        <input type="password" id="new_password" name="new_password" 
                               placeholder="اترك فارغًا إذا كنت لا تريد التغيير"
                               minlength="6">
                        <small style="color: #6b7280; margin-top: 0.25rem; display: block;">
                            يجب أن تكون كلمة المرور 6 أحرف على الأقل
                        </small>
                    </div>
                </div>
                
                <!-- أزرار الحفظ -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        💾 حفظ التغييرات
                    </button>
                    <a href="users.php" class="btn btn-outline">
                        ← إلغاء
                    </a>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="users.php?delete=<?php echo $user['id']; ?>" 
                           class="btn btn-danger" 
                           onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')"
                           style="margin-right: auto;">
                            🗑️ حذف المستخدم
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        const levels = <?php echo json_encode($levels); ?>;
        
        function handleRoleChange() {
            const role = document.getElementById('role').value;
            const educationFields = document.getElementById('educationFields');
            const subjectField = document.getElementById('subjectField');
            
            // إظهار حقول الدراسة للطلاب والمعلمين ومشرفي المواد
            if (role === 'student' || role === 'etudiant' || 
                role === 'teacher' || role === 'enseignant' || 
                role === 'supervisor_subject' || role === 'superviseur_matiere') {
                educationFields.style.display = 'block';
            } else {
                educationFields.style.display = 'none';
            }
            
            // إظهار حقل المادة للمعلمين ومشرفي المواد
            if (role === 'teacher' || role === 'enseignant' || 
                role === 'supervisor_subject' || role === 'superviseur_matiere') {
                subjectField.style.display = 'block';
            } else {
                subjectField.style.display = 'none';
            }
        }
        
        function loadLevels(stageId) {
            const levelSelect = document.getElementById('level_id');
            levelSelect.innerHTML = '<option value="">اختر المستوى</option>';
            
            if (stageId) {
                const filteredLevels = levels.filter(l => l.stage_id == stageId);
                filteredLevels.forEach(level => {
                    const option = document.createElement('option');
                    option.value = level.id;
                    option.textContent = level.name;
                    levelSelect.appendChild(option);
                });
            }
        }
        
        // تشغيل عند تحميل الصفحة
        handleRoleChange();
        
        // تحذير قبل مغادرة الصفحة
        let formChanged = false;
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, select');
        
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        form.addEventListener('submit', () => {
            formChanged = false;
        });
    </script>
</body>
</html>
