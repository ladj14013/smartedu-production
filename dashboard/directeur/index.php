<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('directeur');

// إحصائيات بسيطة
try {
    $users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $teachers_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $students_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $lessons_count = $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
    $subjects_count = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة المدير - SmartEdu</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Amiri', serif;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            direction: rtl;
        }
        
        .page-header {
            background: white;
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .page-header h1 {
            color: #374151;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #6b7280;
            font-size: 1.1rem;
        }
        
        .container {
            padding: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 3rem;
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
        }
        
        .stat-content h3 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: #666;
            font-size: 1rem;
        }
        
        .quick-links {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .quick-links h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .link-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease;
        }
        
        .link-card:hover {
            transform: translateX(-5px);
        }
        
        .link-icon {
            font-size: 2rem;
        }
        
        .link-content h3 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .link-content p {
            font-size: 0.85rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1>مرحباً <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 👋</h1>
                <p>إدارة شاملة لمنصة SmartEdu Hub</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(102, 126, 234, 0.1);">
                        👥
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $users_count; ?></h3>
                        <p>إجمالي المستخدمين</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1);">
                        👨‍🏫
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $teachers_count; ?></h3>
                        <p>المعلمون</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 167, 38, 0.1);">
                        🎓
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $students_count; ?></h3>
                        <p>الطلاب</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1);">
                        📚
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $lessons_count; ?></h3>
                        <p>الدروس</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(168, 85, 247, 0.1);">
                        📖
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $subjects_count; ?></h3>
                        <p>المواد الدراسية</p>
                    </div>
                </div>
            </div>
            
            <div class="quick-links">
                <h2>🔗 روابط سريعة</h2>
                <div class="links-grid">
                    <a href="users.php" class="link-card">
                        <div class="link-icon">👥</div>
                        <div class="link-content">
                            <h3>إدارة المستخدمين</h3>
                            <p>إضافة وتعديل المستخدمين</p>
                        </div>
                    </a>
                    
                    <a href="subjects.php" class="link-card">
                        <div class="link-icon">📚</div>
                        <div class="link-content">
                            <h3>إدارة المواد</h3>
                            <p>المواد الدراسية</p>
                        </div>
                    </a>
                    
                    <a href="stages.php" class="link-card">
                        <div class="link-icon">🎯</div>
                        <div class="link-content">
                            <h3>المراحل والمستويات</h3>
                            <p>إدارة الهيكل التعليمي</p>
                        </div>
                    </a>
                    
                    <a href="messages.php" class="link-card">
                        <div class="link-icon">💬</div>
                        <div class="link-content">
                            <h3>الرسائل</h3>
                            <p>نظام المراسلات</p>
                        </div>
                    </a>
                    
                    <a href="/smartedu/test/create_test_data.php" class="link-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <div class="link-icon">🧪</div>
                        <div class="link-content">
                            <h3>بيانات تجريبية</h3>
                            <p>إنشاء بيانات للاختبار</p>
                        </div>
                    </a>
                    
                    <a href="/smartedu/TESTING_GUIDE.md" class="link-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);" target="_blank">
                        <div class="link-icon">📖</div>
                        <div class="link-content">
                            <h3>دليل الاختبار</h3>
                            <p>خطوات الاختبار الشامل</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>