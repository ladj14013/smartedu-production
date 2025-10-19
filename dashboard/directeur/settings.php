<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/platform.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['directeur']);

$success = '';
$error = '';

// معالجة حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // هنا يمكن إضافة منطق حفظ الإعدادات في قاعدة البيانات
    $success = 'تم حفظ الإعدادات بنجاح';
}

echo get_standard_html_head('إعدادات المنصة - SmartEdu', ['../../assets/css/dashboard.css']);
echo get_role_css($_SESSION['user_role']);
?>

<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>⚙️ إعدادات المنصة</h1>
            <p>إعدادات عامة لمنصة سمارت التعليمية</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="settings-container">
            <!-- إعدادات اللغة والخط -->
            <div class="settings-card">
                <div class="card-header">
                    <h2>🔤 إعدادات اللغة والخط</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>اللغة الافتراضية للمنصة:</label>
                            <input type="text" value="<?php echo PLATFORM_LANGUAGE; ?>" readonly class="form-control">
                            <small class="form-text">اللغة العربية هي اللغة الرسمية للمنصة</small>
                        </div>

                        <div class="form-group">
                            <label>خط المنصة الرسمي:</label>
                            <input type="text" value="<?php echo PLATFORM_FONT_FAMILY; ?>" readonly class="form-control">
                            <small class="form-text">خط Amiri هو الخط الرسمي المعتمد</small>
                        </div>

                        <div class="form-group">
                            <label>اتجاه النص:</label>
                            <input type="text" value="من اليمين إلى اليسار (RTL)" readonly class="form-control">
                        </div>

                        <div class="form-group">
                            <label>ترميز الأحرف:</label>
                            <input type="text" value="<?php echo PLATFORM_CHARSET; ?>" readonly class="form-control">
                        </div>
                    </form>
                </div>
            </div>

            <!-- ألوان الأدوار -->
            <div class="settings-card">
                <div class="card-header">
                    <h2>🎨 ألوان الأدوار</h2>
                </div>
                <div class="card-body">
                    <div class="color-grid">
                        <?php
                        global $ROLE_COLORS;
                        $role_names = [
                            'directeur' => 'مدير النظام',
                            'supervisor_general' => 'مشرف عام', 
                            'supervisor_subject' => 'مشرف مادة',
                            'teacher' => 'معلم',
                            'student' => 'طالب',
                            'parent' => 'ولي أمر'
                        ];
                        
                        foreach ($ROLE_COLORS as $role => $color):
                        ?>
                            <div class="color-item">
                                <div class="color-preview" style="background-color: <?php echo $color; ?>"></div>
                                <div class="color-info">
                                    <strong><?php echo $role_names[$role] ?? $role; ?></strong>
                                    <span class="color-code"><?php echo $color; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- معلومات المنصة -->
            <div class="settings-card">
                <div class="card-header">
                    <h2>ℹ️ معلومات المنصة</h2>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>اسم المنصة:</strong>
                            <span><?php echo PLATFORM_NAME; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>الاسم بالعربية:</strong>
                            <span><?php echo PLATFORM_NAME_AR; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>رابط الخط:</strong>
                            <small style="word-break: break-all;"><?php echo PLATFORM_FONT_URL; ?></small>
                        </div>
                        <div class="info-item">
                            <strong>تاريخ آخر تحديث:</strong>
                            <span><?php echo date('Y-m-d H:i:s'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- عينات النصوص -->
            <div class="settings-card">
                <div class="card-header">
                    <h2>📝 عينات النصوص</h2>
                </div>
                <div class="card-body">
                    <div class="text-samples">
                        <div class="sample-item">
                            <h3>عنوان رئيسي بخط Amiri</h3>
                            <p>هذا نص تجريبي باللغة العربية بخط Amiri الرسمي للمنصة. يتميز هذا الخط بجماله وقابليته للقراءة.</p>
                        </div>
                        
                        <div class="sample-item">
                            <h4>عنوان فرعي</h4>
                            <p>نص آخر يوضح كيفية ظهور النصوص العربية بخط Amiri مع الأرقام 1234567890</p>
                        </div>
                        
                        <div class="sample-item">
                            <strong>نص غامق:</strong> <span>نص عادي</span>
                            <br>
                            <em>نص مائل</em>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .settings-container {
            display: grid;
            gap: 20px;
            max-width: 1200px;
        }

        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: var(--role-color, #667eea);
            color: white;
            padding: 20px;
            font-family: 'Amiri', serif !important;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.4rem;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Amiri', serif !important;
        }

        .form-text {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .color-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .color-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .color-info strong {
            display: block;
            margin-bottom: 5px;
        }

        .color-code {
            color: #666;
            font-size: 14px;
            font-family: monospace;
        }

        .info-grid {
            display: grid;
            gap: 15px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .text-samples {
            display: grid;
            gap: 20px;
        }

        .sample-item {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-right: 4px solid var(--role-color, #667eea);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            color: var(--role-color, #667eea);
        }

        .page-header p {
            margin: 0;
            color: #666;
        }
    </style>
</body>
</html>