<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('student');

$student_id = $_SESSION['user_id'];
$lesson_id = $_GET['id'] ?? null;

if (!$lesson_id) {
    header("Location: lessons.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// جلب تفاصيل الدرس
$query = "SELECT l.*, s.name as subject_name, u.name as teacher_name 
          FROM lessons l
          JOIN subjects s ON l.subject_id = s.id
          JOIN users u ON l.author_id = u.id
          WHERE l.id = :lesson_id AND l.status = 'approved'";
$stmt = $db->prepare($query);
$stmt->bindParam(':lesson_id', $lesson_id);
$stmt->execute();
$lesson = $stmt->fetch();

if (!$lesson) {
    header("Location: lessons.php");
    exit();
}

// جلب التمارين
$query = "SELECT * FROM exercises WHERE lesson_id = :lesson_id ORDER BY `order`";
$stmt = $db->prepare($query);
$stmt->bindParam(':lesson_id', $lesson_id);
$stmt->execute();
$exercises = $stmt->fetchAll();

// تحديث التقدم
$query = "INSERT INTO student_progress (student_id, lesson_id, completion_date) 
          VALUES (:student_id, :lesson_id, NULL)
          ON DUPLICATE KEY UPDATE student_id = student_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':student_id', $student_id);
$stmt->bindParam(':lesson_id', $lesson_id);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title']); ?> - Smart Education Hub</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <a href="lessons.php" class="btn btn-secondary">← العودة للدروس</a>
            </div>
            
            <div class="lesson-detail">
                <div class="card">
                    <div class="card-header">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <h1 class="card-title"><?php echo htmlspecialchars($lesson['title']); ?></h1>
                                <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($lesson['subject_name']); ?></span>
                                    <small style="color: var(--text-secondary);">👨‍🏫 <?php echo htmlspecialchars($lesson['teacher_name']); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="lesson-content-text">
                            <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                        </div>
                        
                        <?php if ($lesson['video_url']): ?>
                            <div class="lesson-video" style="margin-top: 2rem;">
                                <h3>📹 الفيديو التعليمي</h3>
                                <div style="margin-top: 1rem;">
                                    <a href="<?php echo htmlspecialchars($lesson['video_url']); ?>" target="_blank" class="btn btn-primary">
                                        مشاهدة الفيديو
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($lesson['pdf_url']): ?>
                            <div class="lesson-pdf" style="margin-top: 2rem;">
                                <h3>📄 ملف PDF</h3>
                                <div style="margin-top: 1rem;">
                                    <a href="<?php echo htmlspecialchars($lesson['pdf_url']); ?>" target="_blank" class="btn btn-primary">
                                        تحميل PDF
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($exercises)): ?>
                    <div class="card" style="margin-top: 2rem;">
                        <div class="card-header">
                            <h2 class="card-title">✍️ التمارين (<?php echo count($exercises); ?>)</h2>
                        </div>
                        <div class="card-body">
                            <div class="exercises-list">
                                <?php foreach ($exercises as $index => $exercise): ?>
                                    <div class="exercise-item">
                                        <h4>تمرين <?php echo $index + 1; ?></h4>
                                        <p><?php echo nl2br(htmlspecialchars($exercise['question'])); ?></p>
                                        <a href="exercise.php?id=<?php echo $exercise['id']; ?>" class="btn btn-accent btn-sm">
                                            حل التمرين
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <style>
        .lesson-content-text {
            font-size: 1.125rem;
            line-height: 1.8;
            color: var(--text-primary);
        }
        
        .exercises-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .exercise-item {
            padding: 1.5rem;
            background: #f9fafb;
            border-radius: 0.5rem;
            border-right: 4px solid var(--accent);
        }
        
        .exercise-item h4 {
            margin: 0 0 0.5rem 0;
            color: var(--accent);
        }
        
        .exercise-item p {
            margin: 0 0 1rem 0;
            color: var(--text-secondary);
        }
    </style>
</body>
</html>
