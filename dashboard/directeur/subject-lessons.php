<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['directeur']);

global $pdo;

// الحصول على المعرفات
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$level_id = isset($_GET['level_id']) ? intval($_GET['level_id']) : 0;

if ($subject_id == 0 || $level_id == 0) {
    header('Location: subjects.php');
    exit;
}

// جلب معلومات المادة والمستوى
try {
    $info_query = $pdo->prepare("
        SELECT 
            s.id as subject_id,
            s.name as subject_name,
            s.description as subject_description,
            st.name as stage_name,
            l.name as level_name,
            l.id as level_id
        FROM subjects s
        LEFT JOIN stages st ON s.stage_id = st.id
        LEFT JOIN levels l ON l.id = ?
        WHERE s.id = ?
    ");
    $info_query->execute([$level_id, $subject_id]);
    $info = $info_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$info) {
        header('Location: subjects.php');
        exit;
    }
} catch (PDOException $e) {
    die("خطأ في جلب المعلومات: " . $e->getMessage());
}

// جلب الدروس
try {
    $lessons = $pdo->prepare("
        SELECT l.*, u.name as teacher_name
        FROM lessons l
        LEFT JOIN users u ON l.teacher_id = u.id
        WHERE l.subject_id = ? AND l.level_id = ?
        ORDER BY l.order_index, l.created_at
    ");
    $lessons->execute([$subject_id, $level_id]);
    $lessons_list = $lessons->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في جلب الدروس: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دروس <?php echo htmlspecialchars($info['subject_name']); ?> - <?php echo htmlspecialchars($info['level_name']); ?></title>
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
        
        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .lesson-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .lesson-card:hover {
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            transform: translateY(-5px);
        }
        
        .lesson-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .lesson-number {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(255,255,255,0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .lesson-title {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .lesson-card-body {
            padding: 20px;
        }
        
        .lesson-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .lesson-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .meta-item {
            background: #f8f9fa;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .lesson-content-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-right: 4px solid #667eea;
            margin-bottom: 15px;
            max-height: 100px;
            overflow: hidden;
            position: relative;
        }
        
        .lesson-content-preview::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(transparent, #f8f9fa);
        }
        
        .view-lesson-btn {
            display: block;
            text-align: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .view-lesson-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .info-box {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .info-box h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-icon {
            font-size: 1.5rem;
        }
        
        .info-details strong {
            display: block;
            color: #667eea;
            font-size: 0.9rem;
        }
        
        .info-details span {
            color: #666;
            font-size: 1.1rem;
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
            📖 <a href="subject-overview.php?subject_id=<?php echo $subject_id; ?>">
                <?php echo htmlspecialchars($info['subject_name']); ?>
            </a>
            <span>/</span>
            <?php echo htmlspecialchars($info['level_name']); ?>
        </div>
        
        <!-- Page Header -->
        <div class="page-header">
            <h1>📚 دروس <?php echo htmlspecialchars($info['subject_name']); ?></h1>
            <p><?php echo htmlspecialchars($info['stage_name']); ?> - <?php echo htmlspecialchars($info['level_name']); ?></p>
            <small>عدد الدروس: <?php echo count($lessons_list); ?></small>
        </div>
        
        <!-- Info Box -->
        <?php if ($info['subject_description']): ?>
        <div class="info-box">
            <h3>📝 عن المادة</h3>
            <p><?php echo nl2br(htmlspecialchars($info['subject_description'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Lessons Grid -->
        <?php if (empty($lessons_list)): ?>
            <div class="empty-state">
                <div class="empty-icon">📖</div>
                <h4>لا توجد دروس بعد</h4>
                <p>لم يقم الأساتذة بإضافة دروس لهذه المادة في هذا المستوى</p>
            </div>
        <?php else: ?>
            <div class="lessons-grid">
                <?php foreach ($lessons_list as $index => $lesson): ?>
                    <div class="lesson-card">
                        <div class="lesson-card-header">
                            <div class="lesson-number"><?php echo $index + 1; ?></div>
                            <h3 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                        </div>
                        
                        <div class="lesson-card-body">
                            <?php if ($lesson['description']): ?>
                                <div class="lesson-description">
                                    <?php echo nl2br(htmlspecialchars($lesson['description'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="lesson-meta">
                                <?php if ($lesson['teacher_name']): ?>
                                    <div class="meta-item">
                                        <span>👨‍🏫</span>
                                        <?php echo htmlspecialchars($lesson['teacher_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="meta-item">
                                    <span>📅</span>
                                    <?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?>
                                </div>
                                
                                <?php if ($lesson['duration']): ?>
                                    <div class="meta-item">
                                        <span>⏱️</span>
                                        <?php echo $lesson['duration']; ?> دقيقة
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($lesson['content']): ?>
                                <div class="lesson-content-preview">
                                    <?php 
                                    $preview = strip_tags($lesson['content']);
                                    echo mb_substr($preview, 0, 150) . (mb_strlen($preview) > 150 ? '...' : ''); 
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <a href="view-lesson.php?lesson_id=<?php echo $lesson['id']; ?>" 
                               class="view-lesson-btn">
                                👁️ عرض الدرس كاملاً
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
