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

// Check for logout message
if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $success = 'تم تسجيل الخروج بنجاح';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'الرجاء إدخال البريد الإلكتروني وكلمة المرور.';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT * FROM users WHERE email = :email LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch();
                
                if (verify_password($password, $user['password'])) {
                    login_user($user);
                    header("Location: ../dashboard/index.php");
                    exit();
                } else {
                    $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
                }
            } else {
                $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
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
    <title>تسجيل الدخول - Smart Education Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <h1>Smart Education</h1>
                </div>
                <h2>مرحباً بعودتك</h2>
                <p>أدخل بريدك الإلكتروني وكلمة المرور للوصول إلى حسابك</p>
            </div>
            
            <div class="auth-body">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>
                
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
                    <div class="form-group">
                        <label for="email">البريد الإلكتروني</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="m@example.com" 
                            required
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label-row">
                            <label for="password">كلمة المرور</label>
                            <a href="#" class="forgot-password">هل نسيت كلمة المرور؟</a>
                        </div>
                        <div class="password-input-wrapper">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        تسجيل الدخول
                    </button>
                </form>
                
                <!-- Test Users Section - للاختبار فقط -->
                <div class="test-users-section" style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-radius: 12px; border: 2px solid #ffc107;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                        <span style="font-size: 1.5rem;">🧪</span>
                        <h3 style="margin: 0; color: #856404; font-size: 1.2rem;">حسابات تجريبية (للاختبار فقط)</h3>
                    </div>
                    <p style="color: #856404; margin: 0 0 15px 0; font-size: 0.9rem;">
                        <strong>ملاحظة:</strong> جميع كلمات المرور: <code style="background: #fff; padding: 3px 8px; border-radius: 4px; color: #d63031; font-weight: 700;">123456</code>
                    </p>
                    
                    <div class="test-users-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px;">
                        <!-- أستاذ -->
                        <div class="test-user-card" onclick="fillLogin('teacher@test.com', '123456')" style="background: white; padding: 12px; border-radius: 8px; cursor: pointer; border: 2px solid #4CAF50; transition: all 0.3s;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 1.5rem;">👨‍🏫</span>
                                <strong style="color: #4CAF50;">أستاذ</strong>
                            </div>
                            <div style="font-size: 0.85rem; color: #666;">
                                <div style="margin-bottom: 3px;">📧 teacher@test.com</div>
                                <div style="color: #999;">اضغط للتعبئة التلقائية</div>
                            </div>
                        </div>
                        
                        <!-- أستاذ 2 -->
                        <div class="test-user-card" onclick="fillLogin('teacher1@sep.app', '123456')" style="background: white; padding: 12px; border-radius: 8px; cursor: pointer; border: 2px solid #4CAF50; transition: all 0.3s;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 1.5rem;">👨‍🏫</span>
                                <strong style="color: #4CAF50;">محمد أحمد</strong>
                            </div>
                            <div style="font-size: 0.85rem; color: #666;">
                                <div style="margin-bottom: 3px;">📧 teacher1@sep.app</div>
                                <div style="color: #999;">اضغط للتعبئة التلقائية</div>
                            </div>
                        </div>
                        
                        <!-- طالب -->
                        <div class="test-user-card" onclick="fillLogin('student@test.com', '123456')" style="background: white; padding: 12px; border-radius: 8px; cursor: pointer; border: 2px solid #2196F3; transition: all 0.3s;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 1.5rem;">👨‍🎓</span>
                                <strong style="color: #2196F3;">طالب</strong>
                            </div>
                            <div style="font-size: 0.85rem; color: #666;">
                                <div style="margin-bottom: 3px;">📧 student@test.com</div>
                                <div style="color: #999;">اضغط للتعبئة التلقائية</div>
                            </div>
                        </div>
                        
                        <!-- ولي أمر -->
                        <div class="test-user-card" onclick="fillLogin('parent@test.com', '123456')" style="background: white; padding: 12px; border-radius: 8px; cursor: pointer; border: 2px solid #9C27B0; transition: all 0.3s;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 1.5rem;">👨‍👩‍👧</span>
                                <strong style="color: #9C27B0;">ولي أمر</strong>
                            </div>
                            <div style="font-size: 0.85rem; color: #666;">
                                <div style="margin-bottom: 3px;">📧 parent@test.com</div>
                                <div style="color: #999;">اضغط للتعبئة التلقائية</div>
                            </div>
                        </div>
                        
                        <!-- مشرف عام -->
                        <div class="test-user-card" onclick="fillLogin('supervisor@test.com', '123456')" style="background: white; padding: 12px; border-radius: 8px; cursor: pointer; border: 2px solid #FF9800; transition: all 0.3s;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 1.5rem;">👨‍💼</span>
                                <strong style="color: #FF9800;">مشرف عام</strong>
                            </div>
                            <div style="font-size: 0.85rem; color: #666;">
                                <div style="margin-bottom: 3px;">📧 supervisor@test.com</div>
                                <div style="color: #999;">اضغط للتعبئة التلقائية</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 6px; font-size: 0.85rem; color: #856404;">
                        <strong>💡 نصيحة:</strong> اضغط على أي بطاقة لملء البيانات تلقائياً، ثم اضغط "تسجيل الدخول"
                    </div>
                </div>
                
                <style>
                .test-user-card:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }
                </style>
                
                <script>
                function fillLogin(email, password) {
                    document.getElementById('email').value = email;
                    document.getElementById('password').value = password;
                    // إضافة تأثير بصري
                    const emailInput = document.getElementById('email');
                    const passInput = document.getElementById('password');
                    emailInput.style.background = '#e8f5e9';
                    passInput.style.background = '#e8f5e9';
                    setTimeout(() => {
                        emailInput.style.background = '';
                        passInput.style.background = '';
                    }, 500);
                }
                </script>
            </div>
            
            <div class="auth-footer">
                ليس لديك حساب؟ 
                <a href="../public/signup.php">اشتراك</a>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/auth.js"></script>
</body>
</html>
