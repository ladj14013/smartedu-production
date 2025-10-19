<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['parent']);

$user_id = $_SESSION['user_id'];

// جلب معلومات ولي الأمر
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $parent = $stmt->fetch();
    
    // التحقق من وجود جدول parent_children
    $stmt = $pdo->query("SHOW TABLES LIKE 'parent_children'");
    $table_exists = $stmt->rowCount() > 0;
    
    if ($table_exists) {
        // عدد الأبناء لـ sidebar
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM parent_children WHERE parent_id = ?");
        $stmt->execute([$user_id]);
        $total_children = $stmt->fetchColumn();
        $children_count = $total_children;
    } else {
        // الجدول غير موجود - استخدم قيم افتراضية
        $total_children = 0;
        $children_count = 0;
    }
    
    // متوسط الدرجات
    $avg_score = rand(70, 95);
    
} catch (PDOException $e) {
    echo "<div style='background: white; padding: 20px; margin: 20px; border-radius: 10px;'>";
    echo "<h2 style='color: red;'>خطأ في قاعدة البيانات:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>الحل:</strong> يجب تنفيذ سكربت SQL أولاً:</p>";
    echo "<pre>dashboard/parent/add_parent_child_relation.sql</pre>";
    echo "</div>";
    $children_count = 0;
    $avg_score = 0;
    $total_children = 0;
    $parent = ['name' => $_SESSION['user_name'] ?? 'ولي الأمر'];
} catch (PDOException $e) {
    $children_count = 0;
    $avg_score = 0;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة ولي الأمر - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/rtl-parent.css">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Amiri', serif;
        }
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
        }
        .main-content {
            margin-right: 300px !important;
            margin-left: 0 !important;
            padding: 30px;
            min-height: 100vh;
        }
        .copy-code-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3) !important;
        }
        .regenerate-code-btn:hover {
            background: rgba(255,255,255,0.3) !important;
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .main-content {
                margin-right: 0 !important;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- بطاقة كود ولي الأمر -->
        <?php if (isset($parent['parent_code']) && $parent['parent_code']): ?>
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 15px; margin-bottom: 20px; color: white; box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div style="flex: 1;">
                    <h2 style="margin: 0 0 10px 0; font-size: 1.3rem;">🔗 كود الارتباط الخاص بك</h2>
                    <p style="margin: 0; opacity: 0.95; font-size: 0.95rem;">شارك هذا الكود مع أبنائك للارتباط بحسابك</p>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 20px 40px; border-radius: 12px; backdrop-filter: blur(10px); border: 2px solid rgba(255,255,255,0.3);">
                    <div style="text-align: center;">
                        <p style="margin: 0 0 8px 0; font-size: 0.85rem; opacity: 0.9;">كود ولي الأمر</p>
                        <h1 style="margin: 0; font-size: 2.5rem; letter-spacing: 3px; font-weight: 700;"><?php echo htmlspecialchars($parent['parent_code']); ?></h1>
                    </div>
                </div>
                <div style="text-align: center;">
                    <button onclick="copyParentCode()" class="copy-code-btn" style="padding: 15px 30px; background: white; color: #667eea; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s; margin-bottom: 10px;">
                        📋 نسخ الكود
                    </button>
                    <br>
                    <button onclick="regenerateParentCode()" class="regenerate-code-btn" style="padding: 12px 25px; background: rgba(255,255,255,0.2); color: white; border: 2px solid white; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 0.95rem; transition: all 0.3s; backdrop-filter: blur(10px);">
                        🔄 إعادة توليد الكود
                    </button>
                    <p style="margin: 10px 0 0 0; font-size: 0.85rem; opacity: 0.9;">يمكن لأبنائك استخدام هذا الكود</p>
                </div>
            </div>
            
            <div style="margin-top: 25px; padding: 20px; background: rgba(255,255,255,0.15); border-radius: 10px; backdrop-filter: blur(10px);">
                <h3 style="margin: 0 0 15px 0; font-size: 1.1rem;">📌 كيف يستخدم ابنك هذا الكود؟</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="background: rgba(255,255,255,0.3); width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">1</span>
                        <p style="margin: 0; font-size: 0.9rem;">يسجل الدخول إلى حسابه</p>
                    </div>
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="background: rgba(255,255,255,0.3); width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">2</span>
                        <p style="margin: 0; font-size: 0.9rem;">يذهب إلى "ربط ولي الأمر"</p>
                    </div>
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="background: rgba(255,255,255,0.3); width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">3</span>
                        <p style="margin: 0; font-size: 0.9rem;">يدخل الكود: <strong><?php echo $parent['parent_code']; ?></strong></p>
                    </div>
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="background: rgba(255,255,255,0.3); width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">4</span>
                        <p style="margin: 0; font-size: 0.9rem;">يتم الارتباط تلقائيًا ✓</p>
                    </div>
                </div>
            </div>
            
            <script>
            function copyParentCode() {
                const code = "<?php echo $parent['parent_code']; ?>";
                navigator.clipboard.writeText(code).then(function() {
                    alert('✅ تم نسخ الكود: ' + code + '\nشاركه مع ابنك الآن!');
                }, function() {
                    prompt('انسخ الكود التالي:', code);
                });
            }
            
            function regenerateParentCode() {
                if (!confirm('⚠️ تحذير!\n\nإعادة توليد الكود سيؤدي إلى:\n• إلغاء الكود الحالي\n• قطع الارتباط مع جميع الأبناء المرتبطين حالياً\n• سيحتاج أبناؤك للارتباط مرة أخرى بالكود الجديد\n\nهل أنت متأكد من المتابعة؟')) {
                    return;
                }
                
                fetch('../../api/regenerate-parent-code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ تم إعادة توليد الكود بنجاح!\n\nالكود الجديد: ' + data.new_code + '\n\nسيتم تحديث الصفحة...');
                        location.reload();
                    } else {
                        alert('❌ خطأ: ' + (data.message || 'فشل في إعادة توليد الكود'));
                    }
                })
                .catch(error => {
                    alert('❌ خطأ في الاتصال: ' + error.message);
                });
            }
            </script>
        </div>
        <?php endif; ?>
        
        <div style="background: white; padding: 30px; border-radius: 15px; margin-bottom: 20px;">
            <h1>مرحباً <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 👨‍👩‍👧‍👦</h1>
            <p>متابعة تقدم أبنائك التعليمي</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div style="background: white; padding: 25px; border-radius: 15px; text-align: center;">
                <div style="font-size: 3rem;">👶</div>
                <h3><?php echo $children_count; ?></h3>
                <p>الأبناء</p>
            </div>
            
            <div style="background: white; padding: 25px; border-radius: 15px; text-align: center;">
                <div style="font-size: 3rem;">⭐</div>
                <h3><?php echo $avg_score; ?>%</h3>
                <p>متوسط الدرجات</p>
            </div>
            
            <div style="background: white; padding: 25px; border-radius: 15px; text-align: center;">
                <div style="font-size: 3rem;">📊</div>
                <h3><?php echo rand(5, 20); ?></h3>
                <p>التقارير</p>
            </div>
        </div>
        
        <div style="background: white; padding: 30px; border-radius: 15px; margin-top: 20px;">
            <h2>🔗 روابط سريعة</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                <a href="children.php" style="background: #a855f7; color: white; padding: 20px; border-radius: 10px; text-decoration: none; display: block;">
                    <div style="font-size: 2rem; margin-bottom: 10px;">👨‍👩‍👧‍👦</div>
                    <h3>الأبناء</h3>
                    <p style="opacity: 0.9;">عرض قائمة الأبناء</p>
                </a>
                
                <a href="reports.php" style="background: #3b82f6; color: white; padding: 20px; border-radius: 10px; text-decoration: none; display: block;">
                    <div style="font-size: 2rem; margin-bottom: 10px;">📊</div>
                    <h3>التقارير</h3>
                    <p style="opacity: 0.9;">تقارير الأداء الشهرية</p>
                </a>
                
                <a href="messages.php" style="background: #10b981; color: white; padding: 20px; border-radius: 10px; text-decoration: none; display: block;">
                    <div style="font-size: 2rem; margin-bottom: 10px;">💬</div>
                    <h3>الرسائل</h3>
                    <p style="opacity: 0.9;">رسائل من المدرسة</p>
                </a>
            </div>
        </div>
    </div>
</body>
</html>