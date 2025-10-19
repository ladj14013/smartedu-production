<?php
/**
 * Teacher Settings Page
 * صفحة إعدادات الأستاذ
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// التحقق من تسجيل الدخول والصلاحيات
require_auth();
if (!has_any_role(['enseignant', 'teacher'])) {
    header("Location: ../../dashboard/index.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get teacher data
try {
    $teacher_query = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $teacher_query->execute([$teacher_id]);
    $teacher = $teacher_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        header('Location: ../../public/login.php');
        exit;
    }
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

// Handle AJAX request for updating accept_messages
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    
    try {
        $accept_messages = isset($_POST['accept_messages']) && $_POST['accept_messages'] === '1' ? 1 : 0;
        
        $update_query = $pdo->prepare("UPDATE users SET accept_messages = ? WHERE id = ?");
        $update_query->execute([$accept_messages, $teacher_id]);
        
        echo json_encode([
            'success' => true,
            'message' => $accept_messages ? 'تم تفعيل استقبال الرسائل بنجاح' : 'تم تعطيل استقبال الرسائل بنجاح',
            'accept_messages' => $accept_messages
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'حدث خطأ أثناء التحديث: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Get subject info if teacher has a subject
$subject = null;
if (isset($teacher['subject_id']) && $teacher['subject_id']) {
    try {
        $subject_query = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $subject_query->execute([$teacher['subject_id']]);
        $subject = $subject_query->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $subject = null;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعدادات - لوحة الأستاذ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            direction: rtl;
        }

        .main-content {
            margin-right: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            color: #7f8c8d;
            font-size: 1rem;
        }

        .settings-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .settings-section {
            padding: 30px;
            border-bottom: 1px solid #ecf0f1;
        }

        .settings-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-description {
            color: #7f8c8d;
            font-size: 0.95rem;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .setting-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .setting-item:hover {
            background: #f1f3f5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .setting-item:last-child {
            margin-bottom: 0;
        }

        .setting-info {
            flex: 1;
        }

        .setting-label {
            font-size: 1.1rem;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .setting-text {
            color: #7f8c8d;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 32px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #4CAF50;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(28px);
        }

        .toggle-slider:hover {
            box-shadow: 0 0 8px rgba(76, 175, 80, 0.4);
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 10px;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        .alert.show {
            display: flex;
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

        .alert i {
            font-size: 1.3rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Info Box */
        .info-box {
            background: #e3f2fd;
            border-right: 4px solid #2196F3;
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .info-box i {
            color: #2196F3;
            font-size: 1.3rem;
            margin-top: 2px;
        }

        .info-box-content {
            flex: 1;
        }

        .info-box-title {
            font-weight: 600;
            color: #1565C0;
            margin-bottom: 5px;
        }

        .info-box-text {
            color: #0D47A1;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .main-content {
                margin-right: 0;
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .setting-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .toggle-switch {
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>
                <i class="fas fa-cog"></i>
                الإعدادات
            </h1>
            <p>إدارة إعدادات حسابك وتفضيلاتك</p>
        </div>

        <div id="alertContainer"></div>

        <div class="settings-container">
            <!-- Privacy Settings Section -->
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    إعدادات الخصوصية
                </h2>
                <p class="section-description">
                    تحكم في من يمكنه التواصل معك عبر نظام الرسائل
                </p>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">
                            <i class="fas fa-envelope"></i>
                            استقبال الرسائل من أولياء الأمور
                            <span class="status-badge <?php echo $teacher['accept_messages'] ? 'active' : 'inactive'; ?>" id="statusBadge">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                <span id="statusText"><?php echo $teacher['accept_messages'] ? 'مفعل' : 'معطل'; ?></span>
                            </span>
                        </div>
                        <div class="setting-text">
                            عند التفعيل، سيتمكن أولياء أمور طلابك من إرسال رسائل مباشرة إليك.
                            عند التعطيل، لن تظهر أيقونة المراسلة لأولياء الأمور.
                        </div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               id="acceptMessagesToggle" 
                               <?php echo $teacher['accept_messages'] ? 'checked' : ''; ?>
                               onchange="updateMessageSettings(this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <div class="info-box-content">
                        <div class="info-box-title">ملاحظة هامة</div>
                        <div class="info-box-text">
                            • عند تعطيل استقبال الرسائل، لن يتمكن أولياء الأمور من رؤية أيقونة المراسلة الخاصة بك في صفحة متابعة أبنائهم.<br>
                            • الرسائل التي وصلتك سابقًا ستبقى متاحة في صندوق الوارد.<br>
                            • يمكنك تغيير هذا الإعداد في أي وقت.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Info Section -->
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-user-circle"></i>
                    معلومات الحساب
                </h2>
                <p class="section-description">
                    معلومات حسابك الأساسية
                </p>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">
                            <i class="fas fa-id-card"></i>
                            الاسم الكامل
                        </div>
                        <div class="setting-text">
                            <?php echo htmlspecialchars($teacher['nom'] ?? $teacher['name']); ?>
                        </div>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">
                            <i class="fas fa-envelope"></i>
                            البريد الإلكتروني
                        </div>
                        <div class="setting-text">
                            <?php echo htmlspecialchars($teacher['email']); ?>
                        </div>
                    </div>
                </div>

                <?php if ($subject): ?>
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">
                            <i class="fas fa-book"></i>
                            المادة
                        </div>
                        <div class="setting-text">
                            <?php echo htmlspecialchars($subject['name']); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">
                            <i class="fas fa-key"></i>
                            كود الانضمام الخاص
                        </div>
                        <div class="setting-text">
                            <?php echo htmlspecialchars($teacher['teacher_code']); ?>
                            <a href="my-code.php" style="color: #4CAF50; margin-right: 10px; text-decoration: none;">
                                <i class="fas fa-external-link-alt"></i> إدارة الكود
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateMessageSettings(isEnabled) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('accept_messages', isEnabled ? '1' : '0');

            // Update UI immediately for better UX
            updateStatusBadge(isEnabled);

            fetch('settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                } else {
                    showAlert('error', data.message);
                    // Revert toggle if failed
                    document.getElementById('acceptMessagesToggle').checked = !isEnabled;
                    updateStatusBadge(!isEnabled);
                }
            })
            .catch(error => {
                showAlert('error', 'حدث خطأ في الاتصال. الرجاء المحاولة مرة أخرى.');
                console.error('Error:', error);
                // Revert toggle if failed
                document.getElementById('acceptMessagesToggle').checked = !isEnabled;
                updateStatusBadge(!isEnabled);
            });
        }

        function updateStatusBadge(isEnabled) {
            const badge = document.getElementById('statusBadge');
            const statusText = document.getElementById('statusText');
            
            if (isEnabled) {
                badge.classList.remove('inactive');
                badge.classList.add('active');
                statusText.textContent = 'مفعل';
            } else {
                badge.classList.remove('active');
                badge.classList.add('inactive');
                statusText.textContent = 'معطل';
            }
        }

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} show`;
            alert.innerHTML = `
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            `;
            
            alertContainer.appendChild(alert);
            
            // Remove alert after 4 seconds
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }, 4000);
        }
    </script>
</body>
</html>
