<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['student', 'etudiant']);

$user_id = $_SESSION['user_id'];

// جلب بيانات الطالب مع كود الطالب
try {
    $stmt = $pdo->prepare("
        SELECT u.*, s.name as stage_name, l.name as level_name 
        FROM users u
        LEFT JOIN stages s ON u.stage_id = s.id
        LEFT JOIN levels l ON u.level_id = l.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        die('خطأ: لم يتم العثور على بيانات الطالب');
    }
} catch (PDOException $e) {
    die('خطأ في قاعدة البيانات: ' . $e->getMessage());
}

// إحصائيات الطالب
try {
    // عدد الدروس المكتملة (من خلال التمارين المحلولة)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.lesson_id) 
        FROM student_answers sa
        JOIN exercises e ON sa.exercise_id = e.id
        WHERE sa.student_id = ? AND sa.score IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $completed_lessons = $stmt->fetchColumn() ?? 0;
    
    // عدد التمارين المكتملة
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT sa.exercise_id) 
        FROM student_answers sa
        WHERE sa.student_id = ? AND sa.score IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $exercises_completed = $stmt->fetchColumn() ?? 0;
    
    // متوسط الدرجات
    $stmt = $pdo->prepare("
        SELECT AVG(sa.score) 
        FROM student_answers sa
        WHERE sa.student_id = ? AND sa.score IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $avg_score = round($stmt->fetchColumn() ?? 0);
    
    // عدد الأولياء المرتبطين
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM parent_children 
            WHERE child_id = ?
        ");
        $stmt->execute([$user_id]);
        $linked_parents = $stmt->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $linked_parents = 0;
    }
    
} catch (PDOException $e) {
    $completed_lessons = 0;
    $exercises_completed = 0;
    $avg_score = 0;
    $linked_parents = 0;
}

// آخر الدروس المتاحة
try {
    $stmt = $pdo->prepare("
        SELECT l.*, s.name as subject_name 
        FROM lessons l
        LEFT JOIN subjects s ON l.subject_id = s.id
        WHERE l.level_id = ? AND l.stage_id = ?
        ORDER BY l.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$student['level_id'], $student['stage_id']]);
    $recent_lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_lessons = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الطالب - SmartEdu</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Amiri', serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
        }
        .main-content {
            margin-right: 280px;
            padding: 30px;
            min-height: 100vh;
        }
        .welcome-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .welcome-card h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .student-code-card {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            color: white;
            box-shadow: 0 8px 30px rgba(34, 197, 94, 0.4);
        }
        .student-code-card h2 {
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        .code-display {
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
            margin-bottom: 15px;
        }
        .code-display h3 {
            font-size: 2.5rem;
            letter-spacing: 3px;
            margin-top: 10px;
        }
        .copy-btn {
            background: white;
            color: #22c55e;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card .icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .stat-card h3 {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-card p {
            color: #666;
        }
        .quick-links {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .quick-links h2 {
            color: #667eea;
            margin-bottom: 20px;
        }
        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .link-card {
            padding: 20px;
            border-radius: 10px;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
            display: block;
        }
        .link-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .link-card .icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .link-card h3 {
            margin-bottom: 5px;
        }
        .link-card p {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .action-group {
            display: flex;
            flex-direction: column;
        }
        .action-icons {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #666;
        }
        .action-icons span {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 5px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .action-icons span:hover {
            transform: translateY(-2px);
        }
        .recent-lessons {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .recent-lessons h2 {
            color: #667eea;
            margin-bottom: 20px;
        }
        .lesson-item {
            padding: 15px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .lesson-item:hover {
            border-color: #667eea;
            background: #f9f9ff;
        }
        .lesson-item h4 {
            color: #667eea;
            margin-bottom: 5px;
        }
        .lesson-item p {
            color: #666;
            font-size: 0.9rem;
        }
        .lesson-item a {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        @media (max-width: 968px) {
            .main-content {
                margin-right: 0;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-right: 0;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- بطاقة الترحيب -->
        <div class="welcome-card">
            <h1>مرحباً <?php echo htmlspecialchars($student['name']); ?>! 🎓</h1>
            <p>تابع تقدمك الأكاديمي واستكشف الدروس الجديدة</p>
            <?php if ($student['stage_name'] && $student['level_name']): ?>
                <p style="color: #666; margin-top: 10px;">
                    📚 <strong><?php echo htmlspecialchars($student['stage_name']); ?></strong> - 
                    <?php echo htmlspecialchars($student['level_name']); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <!-- بطاقة كود الطالب -->
        <?php if (isset($student['student_code']) && $student['student_code']): ?>
        <div class="student-code-card">
            <h2>🎯 كود الطالب الخاص بك</h2>
            <div class="code-display">
                <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">استخدم هذا الكود للارتباط بالأساتذة</p>
                <h3><?php echo htmlspecialchars($student['student_code']); ?></h3>
            </div>
            <button onclick="copyStudentCode()" class="copy-btn">📋 نسخ الكود</button>
            <p style="margin-top: 15px; font-size: 0.9rem; opacity: 0.95;">
                💡 <strong>ملاحظة:</strong> شارك هذا الكود مع أستاذك أو ولي أمرك للارتباط بحسابك
            </p>
        </div>
        
        <script>
        function copyStudentCode() {
            const code = "<?php echo $student['student_code']; ?>";
            navigator.clipboard.writeText(code).then(function() {
                alert('✅ تم نسخ الكود: ' + code + '\nيمكنك مشاركته الآن!');
            }, function() {
                prompt('انسخ الكود التالي:', code);
            });
        }
        </script>
        <?php endif; ?>
        
        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">📚</div>
                <h3><?php echo $completed_lessons; ?></h3>
                <p>الدروس المكتملة</p>
            </div>
            
            <div class="stat-card">
                <div class="icon">💪</div>
                <h3><?php echo $exercises_completed; ?></h3>
                <p>التمارين المحلولة</p>
            </div>
            
            <div class="stat-card">
                <div class="icon">⭐</div>
                <h3><?php echo $avg_score; ?>%</h3>
                <p>متوسط النتائج</p>
            </div>
            
            <div class="stat-card">
                <div class="icon">👨‍👩‍👧‍👦</div>
                <h3><?php echo $linked_parents; ?></h3>
                <p>الأولياء المرتبطين</p>
            </div>
        </div>
        
        <!-- روابط سريعة -->
        <div class="quick-links">
            <h2>🔗 روابط سريعة</h2>
            <div class="links-grid">
                <div class="action-group">
                    <a href="lessons.php" class="link-card" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
                        <div class="icon">📚</div>
                        <h3>الدروس</h3>
                        <p>استكشف الدروس المتاحة</p>
                    </a>
                    <div class="action-icons">
                        <span>👥 الطلاب</span>
                        <span>📜 إدارة الدروس</span>
                        <span>⚙️ إضافة درس جديد</span>
                    </div>
                </div>
                
                <div class="action-group">
                    <a href="exercises.php" class="link-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        <div class="icon">💪</div>
                        <h3>التمارين</h3>
                        <p>حل التمارين واختبر معلوماتك</p>
                    </a>
                    <div class="action-icons">
                        <span>✍️ حل التمارين</span>
                        <span>📊 عرض النتائج</span>
                        <span>🎯 المراجعة</span>
                    </div>
                </div>
                
                <div class="action-group">
                    <a href="results.php" class="link-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <div class="icon">📊</div>
                        <h3>النتائج</h3>
                        <p>راجع درجاتك وتقدمك</p>
                    </a>
                    <div class="action-icons">
                        <span>📈 التقدم</span>
                        <span>🏆 الإنجازات</span>
                        <span>📋 التقارير</span>
                    </div>
                </div>
                
                <div class="action-group">
                    <a href="link-parent.php" class="link-card" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                        <div class="icon">👨‍👩‍👧‍👦</div>
                        <h3>ربط ولي الأمر</h3>
                        <p>اربط حسابك بولي أمرك</p>
                    </a>
                    <div class="action-icons">
                        <span>🔗 الربط</span>
                        <span>👥 الأولياء</span>
                        <span>📬 الرسائل</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- آخر الدروس المتاحة -->
        <?php if (!empty($recent_lessons)): ?>
        <div class="recent-lessons">
            <h2>📖 آخر الدروس المتاحة</h2>
            <?php foreach ($recent_lessons as $lesson): ?>
                <div class="lesson-item">
                    <h4><?php echo htmlspecialchars($lesson['title']); ?></h4>
                    <p>
                        <?php if ($lesson['subject_name']): ?>
                            📚 <?php echo htmlspecialchars($lesson['subject_name']); ?>
                        <?php endif; ?>
                        <?php if ($lesson['description']): ?>
                            | <?php echo htmlspecialchars(substr($lesson['description'], 0, 100)); ?>...
                        <?php endif; ?>
                    </p>
                    <a href="lesson.php?id=<?php echo $lesson['id']; ?>">ابدأ الدرس ←</a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
