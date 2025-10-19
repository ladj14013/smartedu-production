<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['enseignant', 'teacher']);

global $pdo;
$teacher_id = $_SESSION['user_id'];

// معالجة طلب توليد كود جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_code'])) {
    // جلب الكود القديم أولاً
    $old_code_stmt = $pdo->prepare("SELECT teacher_code FROM users WHERE id = ?");
    $old_code_stmt->execute([$teacher_id]);
    $old_teacher_data = $old_code_stmt->fetch();
    $old_code = $old_teacher_data['teacher_code'] ?? null;
    
    // توليد كود عشوائي فريد
    do {
        $new_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        // التحقق من عدم وجود الكود
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE teacher_code = ?");
        $check_stmt->execute([$new_code]);
    } while ($check_stmt->rowCount() > 0);
    
    try {
        // بدء معاملة لضمان تنفيذ كل العمليات معاً
        $pdo->beginTransaction();
        
        // تحديث كود الأستاذ
        $update_stmt = $pdo->prepare("UPDATE users SET teacher_code = ? WHERE id = ?");
        $update_stmt->execute([$new_code, $teacher_id]);
        
        // إلغاء ارتباط الطلاب القدامى (حذف الكود القديم من connected_teacher_code)
        $disconnected_count = 0;
        if ($old_code) {
            // 1. حذف من جدول users
            $disconnect_students_stmt = $pdo->prepare("
                UPDATE users 
                SET connected_teacher_code = NULL 
                WHERE connected_teacher_code = ? AND role = 'etudiant'
            ");
            $disconnect_students_stmt->execute([$old_code]);
            $disconnected_count = $disconnect_students_stmt->rowCount();
            
            // 2. حذف من جدول student_teacher_links (إذا كان موجوداً)
            try {
                $delete_links_stmt = $pdo->prepare("
                    DELETE FROM student_teacher_links 
                    WHERE teacher_id = ?
                ");
                $delete_links_stmt->execute([$teacher_id]);
            } catch (PDOException $e) {
                // الجدول قد لا يكون موجوداً، نتجاهل الخطأ
            }
        }
        
        // تأكيد المعاملة
        $pdo->commit();
        
        if ($disconnected_count > 0) {
            $_SESSION['success_message'] = "تم توليد كود جديد بنجاح! تم فصل {$disconnected_count} طالب عن الارتباط القديم.";
        } else {
            $_SESSION['success_message'] = "تم توليد كود جديد بنجاح!";
        }
        
        header('Location: my-code.php');
        exit();
    } catch (Exception $e) {
        // إلغاء المعاملة في حالة الخطأ
        $pdo->rollBack();
        $_SESSION['error_message'] = "حدث خطأ أثناء توليد الكود: " . $e->getMessage();
        header('Location: my-code.php');
        exit();
    }
}

// جلب معلومات الأستاذ وكوده الحالي
$stmt = $pdo->prepare("
    SELECT u.name, u.teacher_code, s.name as subject_name
    FROM users u
    LEFT JOIN subjects s ON u.subject_id = s.id
    WHERE u.id = ?
");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

// جلب عدد التلاميذ المرتبطين
$links_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT student_id) as student_count
    FROM student_teacher_links
    WHERE teacher_id = ? AND status = 'active'
");
$links_stmt->execute([$teacher_id]);
$links_data = $links_stmt->fetch();
$student_count = $links_data['student_count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كودي الخاص - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .code-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
        }

        .code-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        .code-card h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
            font-weight: 300;
        }

        .teacher-code {
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 8px;
            margin: 20px 0;
            padding: 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            font-family: 'Courier New', monospace;
        }

        .no-code {
            font-size: 18px;
            color: rgba(255,255,255,0.8);
            margin: 20px 0;
        }

        .code-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,255,255,0.3);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .info-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .info-card .icon {
            font-size: 36px;
            color: #4CAF50;
            margin-bottom: 10px;
        }

        .info-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin: 10px 0 5px 0;
        }

        .info-card .label {
            color: #666;
            font-size: 14px;
        }

        .instructions {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .instructions h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .instructions ol {
            padding-right: 20px;
            color: #666;
            line-height: 1.8;
        }

        .instructions li {
            margin-bottom: 10px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
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

        .copy-notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #4CAF50;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            z-index: 1000;
            display: none;
            animation: slideDown 0.3s ease;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="code-container">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="code-card">
                <h2>كود التسجيل الخاص بك</h2>
                <?php if ($teacher['teacher_code']): ?>
                    <div class="teacher-code" id="teacherCode"><?php echo htmlspecialchars($teacher['teacher_code']); ?></div>
                    <p style="font-size: 14px; margin: 10px 0 0 0; opacity: 0.9;">
                        شارك هذا الكود مع تلاميذك للسماح لهم بالوصول إلى دروسك الخاصة
                    </p>
                <?php else: ?>
                    <div class="no-code">لم يتم توليد كود بعد</div>
                    <p style="font-size: 14px; margin: 10px 0 0 0; opacity: 0.9;">
                        قم بتوليد كود خاص بك لكي يتمكن التلاميذ من الارتباط بك
                    </p>
                <?php endif; ?>

                <div class="code-actions">
                    <?php if ($teacher['teacher_code']): ?>
                        <button class="btn btn-primary" onclick="copyCode()">
                            📋 نسخ الكود
                        </button>
                    <?php endif; ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="generate_code" class="btn btn-secondary" 
                                onclick="return confirm('<?php echo $teacher['teacher_code'] ? '⚠️ تحذير: سيتم فصل جميع الطلاب المرتبطين حالياً (' . $student_count . ' طالب). الكود الجديد سيكون للطلاب الجدد فقط. هل أنت متأكد؟' : 'هل تريد توليد كود جديد؟'; ?>')">
                            🔄 <?php echo $teacher['teacher_code'] ? 'توليد كود جديد' : 'توليد كود'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <div class="icon">👥</div>
                    <div class="value"><?php echo $student_count; ?></div>
                    <div class="label">تلميذ مرتبط</div>
                </div>
                <div class="info-card">
                    <div class="icon">📚</div>
                    <div class="value"><?php echo htmlspecialchars($teacher['subject_name'] ?? '-'); ?></div>
                    <div class="label">المادة</div>
                </div>
            </div>

            <div class="instructions">
                <h3>📌 كيفية استخدام الكود</h3>
                <ol>
                    <li><strong>قم بتوليد الكود</strong> إذا لم يكن لديك واحد بعد</li>
                    <li><strong>شارك الكود</strong> مع تلاميذك (يمكنك نسخه بنقرة واحدة)</li>
                    <li><strong>يقوم التلميذ بإدخال الكود</strong> في صفحته الخاصة في قسم "ربط بأستاذ"</li>
                    <li><strong>بعد الربط</strong> سيتمكن التلميذ من رؤية دروسك الخاصة بالإضافة للدروس العامة</li>
                    <li><strong>يمكنك مراجعة</strong> قائمة التلاميذ المرتبطين بك من قسم "تلاميذي"</li>
                </ol>
                <p style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 5px; color: #856404;">
                    ⚠️ <strong>تحذير مهم:</strong> عند توليد كود جديد، سيتم فصل جميع التلاميذ المرتبطين حالياً. الكود الجديد سيكون للطلاب الجدد فقط. إذا أردت الاحتفاظ بطلابك الحاليين، لا تقم بتغيير الكود.
                </p>
            </div>
        </div>
    </div>

    <div class="copy-notification" id="copyNotification">
        ✓ تم نسخ الكود بنجاح!
    </div>

    <script>
        function copyCode() {
            const codeElement = document.getElementById('teacherCode');
            const code = codeElement.textContent;
            
            // نسخ النص إلى الحافظة
            navigator.clipboard.writeText(code).then(function() {
                // إظهار الإشعار
                const notification = document.getElementById('copyNotification');
                notification.style.display = 'block';
                
                // إخفاء الإشعار بعد 2 ثانية
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 2000);
            }).catch(function(err) {
                alert('فشل نسخ الكود: ' + err);
            });
        }
    </script>
</body>
</html>
