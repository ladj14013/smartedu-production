<?php
/**
 * Link Student to Parent using Parent Code
 * ربط الطالب بولي الأمر باستخدام كود الولي
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_auth();
has_any_role(['etudiant', 'student']);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// جلب معلومات الطالب
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

// التحقق من وجود ارتباط حالي
$stmt = $pdo->prepare("SELECT COUNT(*) FROM parent_children WHERE child_id = ?");
$stmt->execute([$user_id]);
$already_linked = $stmt->fetchColumn() > 0;

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_linked) {
    $parent_code = trim($_POST['parent_code'] ?? '');
    $relation_type = $_POST['relation_type'] ?? 'father';
    
    if (empty($parent_code)) {
        $error = 'يرجى إدخال كود ولي الأمر';
    } else {
        try {
            // البحث عن ولي الأمر
            $stmt = $pdo->prepare("
                SELECT id, name, email 
                FROM users 
                WHERE parent_code = ? AND role = 'parent'
            ");
            $stmt->execute([$parent_code]);
            $parent = $stmt->fetch();
            
            if (!$parent) {
                $error = 'كود ولي الأمر غير صحيح أو غير موجود';
            } else {
                // التحقق من عدم وجود ربط سابق
                $stmt = $pdo->prepare("
                    SELECT id FROM parent_children 
                    WHERE parent_id = ? AND child_id = ?
                ");
                $stmt->execute([$parent['id'], $user_id]);
                
                if ($stmt->fetch()) {
                    $error = 'أنت مرتبط بولي الأمر هذا بالفعل';
                } else {
                    // إضافة الربط مباشرة
                    $stmt = $pdo->prepare("
                        INSERT INTO parent_children (parent_id, child_id, relation_type, is_primary)
                        VALUES (?, ?, ?, TRUE)
                    ");
                    $stmt->execute([$parent['id'], $user_id, $relation_type]);
                    
                    $success = 'تم الارتباط بنجاح مع ولي الأمر: ' . htmlspecialchars($parent['name']);
                    $already_linked = true;
                    
                    // إعادة التوجيه بعد 3 ثواني
                    header("refresh:3;url=index.php");
                }
            }
        } catch (PDOException $e) {
            $error = 'حدث خطأ في النظام: ' . $e->getMessage();
        }
    }
}

// جلب قائمة أولياء الأمور المرتبطين
$stmt = $pdo->prepare("
    SELECT u.name, u.email, pc.relation_type, pc.created_at
    FROM parent_children pc
    JOIN users u ON pc.parent_id = u.id
    WHERE pc.child_id = ?
    ORDER BY pc.is_primary DESC, pc.created_at DESC
");
$stmt->execute([$user_id]);
$linked_parents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ربط ولي الأمر - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        @media (max-width: 968px) {
            .main-content {
                margin-right: 0 !important;
                padding: 20px;
            }
        }
    </style>
    <link rel="stylesheet" href="link-parent-mobile.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
        }
        
        .main-content {
            margin-right: 300px;
            padding: 30px;
            min-height: 100vh;
        }
        
        .page-header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(33, 150, 243, 0.3);
        }
        
        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .link-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .link-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .link-header .icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        .link-header h2 {
            color: #1f2937;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .link-header p {
            color: #6b7280;
            font-size: 1rem;
        }
        
        .info-box {
            background: #f0f9ff;
            border-right: 4px solid #3b82f6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .info-box h3 {
            color: #1e40af;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        .info-box ol {
            margin: 0;
            padding-right: 20px;
            color: #374151;
        }
        
        .info-box li {
            margin-bottom: 8px;
            line-height: 1.6;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            font-size: 1rem;
        }
        
        .form-input,
        .form-select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
            direction: rtl;
            text-align: right;
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 4px rgba(33, 150, 243, 0.1);
        }
        
        .form-input::placeholder {
            color: #9ca3af;
        }
        
        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(33, 150, 243, 0.4);
        }
        
        .btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            width: 100%;
            padding: 14px;
            background: white;
            color: #6b7280;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-right: 4px solid #dc2626;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-right: 4px solid #10b981;
        }
        
        .linked-parents {
            background: #f9fafb;
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
        }
        
        .linked-parents h3 {
            color: #1f2937;
            font-size: 1.2rem;
            margin-bottom: 20px;
        }
        
        .parent-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .parent-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .parent-info {
            flex: 1;
        }
        
        .parent-info h4 {
            margin: 0 0 5px 0;
            color: #1f2937;
            font-size: 1.1rem;
        }
        
        .parent-info p {
            margin: 0;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .relation-badge {
            padding: 6px 12px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-right: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🔗 ربط ولي الأمر</h1>
            <p>اربط حسابك بحساب ولي أمرك ليتابع تقدمك الدراسي</p>
        </div>
        
        <div class="link-container">
            <div class="link-header">
                <div class="icon">👨‍👩‍👧‍👦</div>
                <h2>ربط ولي الأمر</h2>
                <p>أدخل الكود الذي حصلت عليه من ولي أمرك</p>
            </div>
            
            <?php if (!$already_linked): ?>
            <div class="info-box">
                <h3>📌 كيف تحصل على الكود؟</h3>
                <ol>
                    <li>اطلب من ولي أمرك تسجيل الدخول إلى حسابه</li>
                    <li>سيجد كوده الخاص في صفحته الرئيسية</li>
                    <li>الكود يبدأ بـ <strong>PAR</strong> متبوعاً بأرقام</li>
                    <li>أدخل الكود في الحقل أدناه</li>
                </ol>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success); ?>
                    <br>جاري التحويل إلى الصفحة الرئيسية...
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="parent_code" class="form-label">
                        👤 كود ولي الأمر <span style="color: #dc2626;">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="parent_code" 
                        name="parent_code" 
                        class="form-input" 
                        placeholder="مثال: PAR000001"
                        required
                        pattern="PAR\d{6}"
                        title="الكود يجب أن يكون بصيغة PAR متبوعاً بـ 6 أرقام"
                    >
                    <small style="color: #6b7280; display: block; margin-top: 8px;">
                        💡 مثال: PAR000001
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="relation_type" class="form-label">
                        🔗 صلة القرابة <span style="color: #dc2626;">*</span>
                    </label>
                    <select id="relation_type" name="relation_type" class="form-select" required>
                        <option value="father">أب</option>
                        <option value="mother">أم</option>
                        <option value="guardian">وصي</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary">
                    ➕ ربط ولي الأمر
                </button>
            </form>
            <?php else: ?>
                <div class="alert alert-success">
                    ✅ أنت مرتبط بولي أمر بالفعل
                </div>
            <?php endif; ?>
            
            <a href="index.php" class="btn-secondary">
                ← العودة إلى الصفحة الرئيسية
            </a>
        </div>
        
        <?php if (!empty($linked_parents)): ?>
        <div class="link-container" style="margin-top: 30px;">
            <div class="linked-parents">
                <h3>👥 أولياء الأمور المرتبطين</h3>
                <?php foreach ($linked_parents as $parent): ?>
                <div class="parent-item">
                    <div class="parent-avatar">
                        <?php echo mb_substr($parent['name'], 0, 1); ?>
                    </div>
                    <div class="parent-info">
                        <h4><?php echo htmlspecialchars($parent['name']); ?></h4>
                        <p><?php echo htmlspecialchars($parent['email']); ?></p>
                        <p style="margin-top: 5px; font-size: 0.85rem;">
                            📅 تم الربط: <?php echo date('Y/m/d', strtotime($parent['created_at'])); ?>
                        </p>
                    </div>
                    <span class="relation-badge">
                        <?php 
                        $relations = [
                            'father' => 'أب',
                            'mother' => 'أم',
                            'guardian' => 'وصي',
                            'other' => 'أخرى'
                        ];
                        echo $relations[$parent['relation_type']] ?? 'غير محدد';
                        ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
