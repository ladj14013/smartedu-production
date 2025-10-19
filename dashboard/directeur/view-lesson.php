<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['directeur']);

global $pdo;

// الحصول على معرف الدرس
$lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;

if ($lesson_id == 0) {
    header('Location: subjects.php');
    exit;
}

// جلب معلومات الدرس الكاملة
try {
    $lesson_query = $pdo->prepare("
        SELECT 
            l.*,
            s.name as subject_name,
            s.id as subject_id,
            lv.name as level_name,
            lv.id as level_id,
            st.name as stage_name,
            u.name as teacher_name,
            u.email as teacher_email
        FROM lessons l
        LEFT JOIN subjects s ON l.subject_id = s.id
        LEFT JOIN levels lv ON l.level_id = lv.id
        LEFT JOIN stages st ON s.stage_id = st.id
        LEFT JOIN users u ON l.teacher_id = u.id
        WHERE l.id = ?
    ");
    $lesson_query->execute([$lesson_id]);
    $lesson = $lesson_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$lesson) {
        header('Location: subjects.php');
        exit;
    }
} catch (PDOException $e) {
    die("خطأ في جلب الدرس: " . $e->getMessage());
}

// جلب المرفقات إن وجدت
try {
    $attachments = $pdo->prepare("
        SELECT * FROM lesson_attachments 
        WHERE lesson_id = ?
        ORDER BY created_at
    ");
    $attachments->execute([$lesson_id]);
    $attachments_list = $attachments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $attachments_list = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title']); ?></title>
    <link rel="stylesheet" href="../../assets/css/subjects-enhanced.css">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .breadcrumb {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb span {
            color: #999;
            margin: 0 8px;
        }
        
        .lesson-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .lesson-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
        }
        
        .lesson-header h1 {
            margin: 0 0 15px 0;
            font-size: 2.2rem;
        }
        
        .lesson-meta-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        
        .meta-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .lesson-body {
            padding: 40px;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-right: 5px solid #667eea;
        }
        
        .info-section h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.3rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .info-icon {
            font-size: 1.8rem;
            margin-top: 3px;
        }
        
        .info-details strong {
            display: block;
            color: #667eea;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .info-details span {
            color: #333;
            font-size: 1.1rem;
        }
        
        .content-section {
            margin-top: 30px;
        }
        
        .content-section h3 {
            color: #333;
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }
        
        .lesson-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            line-height: 1.8;
            font-size: 1.1rem;
            color: #333;
        }
        
        .lesson-content p {
            margin-bottom: 15px;
        }
        
        .lesson-content h1,
        .lesson-content h2,
        .lesson-content h3,
        .lesson-content h4 {
            color: #667eea;
            margin-top: 25px;
            margin-bottom: 15px;
        }
        
        .lesson-content ul,
        .lesson-content ol {
            margin: 15px 0;
            padding-right: 30px;
        }
        
        .lesson-content li {
            margin-bottom: 10px;
        }
        
        .lesson-content img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .lesson-content code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #e83e8c;
        }
        
        .lesson-content pre {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            border-right: 4px solid #667eea;
        }
        
        .attachments-section {
            margin-top: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .attachments-section h3 {
            margin: 0 0 20px 0;
            color: #333;
        }
        
        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .attachment-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .attachment-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .attachment-icon {
            font-size: 2rem;
        }
        
        .attachment-info {
            flex: 1;
        }
        
        .attachment-info strong {
            display: block;
            color: #333;
            margin-bottom: 3px;
        }
        
        .attachment-info small {
            color: #999;
        }
        
        .download-btn {
            background: #667eea;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .download-btn:hover {
            background: #764ba2;
        }
        
        .readonly-badge {
            background: #ffc107;
            color: #856404;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .back-btn {
            display: inline-block;
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 30px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            🏠 <a href="index.php">الرئيسية</a>
            <span>/</span>
            📚 <a href="subjects.php">المواد</a>
            <span>/</span>
            📖 <a href="subject-overview.php?subject_id=<?php echo $lesson['subject_id']; ?>">
                <?php echo htmlspecialchars($lesson['subject_name']); ?>
            </a>
            <span>/</span>
            📚 <a href="subject-lessons.php?subject_id=<?php echo $lesson['subject_id']; ?>&level_id=<?php echo $lesson['level_id']; ?>">
                دروس <?php echo htmlspecialchars($lesson['level_name']); ?>
            </a>
            <span>/</span>
            <?php echo htmlspecialchars($lesson['title']); ?>
        </div>
        
        <!-- Lesson Container -->
        <div class="lesson-container">
            <!-- Lesson Header -->
            <div class="lesson-header">
                <h1>📖 <?php echo htmlspecialchars($lesson['title']); ?></h1>
                
                <div class="lesson-meta-bar">
                    <div class="meta-badge">
                        <span>🎓</span>
                        <?php echo htmlspecialchars($lesson['stage_name']); ?>
                    </div>
                    
                    <div class="meta-badge">
                        <span>📚</span>
                        <?php echo htmlspecialchars($lesson['level_name']); ?>
                    </div>
                    
                    <div class="meta-badge">
                        <span>📖</span>
                        <?php echo htmlspecialchars($lesson['subject_name']); ?>
                    </div>
                    
                    <?php if ($lesson['duration']): ?>
                    <div class="meta-badge">
                        <span>⏱️</span>
                        <?php echo $lesson['duration']; ?> دقيقة
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Lesson Body -->
            <div class="lesson-body">
                <!-- Read-only Badge -->
                <div class="readonly-badge">
                    <span>👁️</span>
                    وضع العرض فقط - لا يمكن التعديل
                </div>
                
                <!-- Info Section -->
                <div class="info-section">
                    <h3>ℹ️ معلومات الدرس</h3>
                    
                    <div class="info-grid">
                        <?php if ($lesson['teacher_name']): ?>
                        <div class="info-item">
                            <div class="info-icon">👨‍🏫</div>
                            <div class="info-details">
                                <strong>الأستاذ</strong>
                                <span><?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <div class="info-icon">📅</div>
                            <div class="info-details">
                                <strong>تاريخ الإنشاء</strong>
                                <span><?php echo date('Y/m/d - H:i', strtotime($lesson['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($lesson['order_index']): ?>
                        <div class="info-item">
                            <div class="info-icon">🔢</div>
                            <div class="info-details">
                                <strong>ترتيب الدرس</strong>
                                <span><?php echo $lesson['order_index']; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Description -->
                <?php if ($lesson['description']): ?>
                <div class="content-section">
                    <h3>📝 وصف الدرس</h3>
                    <div class="lesson-content">
                        <?php echo nl2br(htmlspecialchars($lesson['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Main Content -->
                <div class="content-section">
                    <h3>📚 محتوى الدرس</h3>
                    <div class="lesson-content">
                        <?php 
                        if ($lesson['content']) {
                            // عرض المحتوى مع السماح ببعض HTML الآمن
                            echo $lesson['content']; 
                        } else {
                            echo "<p style='color: #999; text-align: center; padding: 40px;'>لا يوجد محتوى لهذا الدرس</p>";
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Attachments -->
                <?php if (!empty($attachments_list)): ?>
                <div class="attachments-section">
                    <h3>📎 المرفقات (<?php echo count($attachments_list); ?>)</h3>
                    
                    <div class="attachments-grid">
                        <?php foreach ($attachments_list as $attachment): ?>
                            <div class="attachment-card">
                                <div class="attachment-icon">📄</div>
                                <div class="attachment-info">
                                    <strong><?php echo htmlspecialchars($attachment['filename']); ?></strong>
                                    <small><?php echo htmlspecialchars($attachment['file_type'] ?? 'ملف'); ?></small>
                                </div>
                                <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                   class="download-btn" 
                                   download
                                   target="_blank">
                                    تحميل
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Back Button -->
                <a href="subject-lessons.php?subject_id=<?php echo $lesson['subject_id']; ?>&level_id=<?php echo $lesson['level_id']; ?>" 
                   class="back-btn">
                    ← العودة إلى قائمة الدروس
                </a>
            </div>
        </div>
    </div>
</body>
</html>
