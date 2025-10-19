<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('directeur');

$database = new Database();
$db = $database->getConnection();

$subject_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($subject_id === 0) {
    header('Location: content.php');
    exit();
}

// جلب معلومات المادة
$query = "SELECT s.*, l.name as level_name, st.name as stage_name 
          FROM subjects s 
          JOIN levels l ON s.level_id = l.id 
          JOIN stages st ON l.stage_id = st.id 
          WHERE s.id = :subject_id";
$stmt = $db->prepare($query);
$stmt->execute([':subject_id' => $subject_id]);
$subject = $stmt->fetch();

if (!$subject) {
    header('Location: content.php');
    exit();
}

// جلب جميع الدروس في هذه المادة (عامة وخاصة)
$query = "SELECT l.*, u.name as author_name, u.role as author_role,
          (SELECT COUNT(*) FROM exercises WHERE lesson_id = l.id) as exercises_count
          FROM lessons l 
          JOIN users u ON l.author_id = u.id 
          WHERE l.subject_id = :subject_id 
          ORDER BY l.type DESC, l.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([':subject_id' => $subject_id]);
$lessons = $stmt->fetchAll();

// تجميع الدروس حسب النوع
$public_lessons = array_filter($lessons, function($lesson) {
    return $lesson['type'] === 'public';
});

$private_lessons = array_filter($lessons, function($lesson) {
    return $lesson['type'] === 'private';
});
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محتوى المادة: <?php echo htmlspecialchars($subject['name']); ?> - Smart Education Hub</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .breadcrumb a {
            color: #4285F4;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb span {
            color: #6b7280;
        }
        
        .subject-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .subject-info h2 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
        }
        
        .subject-info p {
            margin: 0;
            opacity: 0.9;
        }
        
        .lessons-section {
            margin-bottom: 2rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .section-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .lesson-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s;
            position: relative;
        }
        
        .lesson-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .lesson-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .lesson-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .lesson-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .lesson-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-public {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-private {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-locked {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-unlocked {
            background: #d1fae5;
            color: #065f46;
        }
        
        .lesson-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: #f9fafb;
            border-radius: 12px;
            color: #6b7280;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="content.php">إدارة المحتوى</a>
                <span>›</span>
                <span><?php echo htmlspecialchars($subject['stage_name']); ?></span>
                <span>›</span>
                <span><?php echo htmlspecialchars($subject['level_name']); ?></span>
                <span>›</span>
                <strong><?php echo htmlspecialchars($subject['name']); ?></strong>
            </div>
            
            <!-- Subject Info -->
            <div class="subject-info">
                <h2>📖 <?php echo htmlspecialchars($subject['name']); ?></h2>
                <p><?php echo htmlspecialchars($subject['stage_name'] . ' - ' . $subject['level_name']); ?></p>
                <?php if (!empty($subject['description'])): ?>
                    <p style="margin-top: 0.5rem;"><?php echo htmlspecialchars($subject['description']); ?></p>
                <?php endif; ?>
                <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                    <div>
                        <strong>إجمالي الدروس:</strong> <?php echo count($lessons); ?>
                    </div>
                    <div>
                        <strong>دروس عامة:</strong> <?php echo count($public_lessons); ?>
                    </div>
                    <div>
                        <strong>دروس خاصة:</strong> <?php echo count($private_lessons); ?>
                    </div>
                </div>
            </div>
            
            <!-- الدروس العامة -->
            <div class="lessons-section">
                <div class="section-header">
                    <h3>
                        <span>🌐</span>
                        الدروس العامة
                        <span class="badge badge-public"><?php echo count($public_lessons); ?></span>
                    </h3>
                </div>
                
                <?php if (!empty($public_lessons)): ?>
                    <div class="lessons-grid">
                        <?php foreach ($public_lessons as $lesson): ?>
                            <div class="lesson-card">
                                <div class="lesson-header">
                                    <h4 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h4>
                                </div>
                                
                                <div class="lesson-meta">
                                    <div class="lesson-meta-item">
                                        <span>👤</span>
                                        <span>بواسطة: <?php echo htmlspecialchars($lesson['author_name']); ?></span>
                                    </div>
                                    <div class="lesson-meta-item">
                                        <span>📅</span>
                                        <span><?php echo date('Y-m-d', strtotime($lesson['created_at'])); ?></span>
                                    </div>
                                    <div class="lesson-meta-item">
                                        <span>✍️</span>
                                        <span><?php echo $lesson['exercises_count']; ?> تمارين</span>
                                    </div>
                                </div>
                                
                                <div class="lesson-badges">
                                    <span class="badge badge-public">عام</span>
                                    <span class="badge <?php echo $lesson['is_locked'] ? 'badge-locked' : 'badge-unlocked'; ?>">
                                        <?php echo $lesson['is_locked'] ? '🔒 مقفل' : '🔓 مفتوح'; ?>
                                    </span>
                                </div>
                                
                                <div class="lesson-actions">
                                    <a href="edit-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-primary">
                                        ✏️ تعديل
                                    </a>
                                    <a href="../../dashboard/student/lesson.php?id=<?php echo $lesson['id']; ?>" 
                                       class="btn btn-sm btn-outline" target="_blank">
                                        👁️ معاينة
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>لا توجد دروس عامة</h3>
                        <p>لم يتم إضافة أي دروس عامة لهذه المادة بعد</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- الدروس الخاصة -->
            <div class="lessons-section">
                <div class="section-header">
                    <h3>
                        <span>🔐</span>
                        الدروس الخاصة
                        <span class="badge badge-private"><?php echo count($private_lessons); ?></span>
                    </h3>
                </div>
                
                <?php if (!empty($private_lessons)): ?>
                    <div class="lessons-grid">
                        <?php foreach ($private_lessons as $lesson): ?>
                            <div class="lesson-card">
                                <div class="lesson-header">
                                    <h4 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h4>
                                </div>
                                
                                <div class="lesson-meta">
                                    <div class="lesson-meta-item">
                                        <span>👤</span>
                                        <span>بواسطة: <?php echo htmlspecialchars($lesson['author_name']); ?></span>
                                    </div>
                                    <div class="lesson-meta-item">
                                        <span>📅</span>
                                        <span><?php echo date('Y-m-d', strtotime($lesson['created_at'])); ?></span>
                                    </div>
                                    <div class="lesson-meta-item">
                                        <span>✍️</span>
                                        <span><?php echo $lesson['exercises_count']; ?> تمارين</span>
                                    </div>
                                </div>
                                
                                <div class="lesson-badges">
                                    <span class="badge badge-private">خاص</span>
                                    <span class="badge <?php echo $lesson['is_locked'] ? 'badge-locked' : 'badge-unlocked'; ?>">
                                        <?php echo $lesson['is_locked'] ? '🔒 مقفل' : '🔓 مفتوح'; ?>
                                    </span>
                                </div>
                                
                                <div class="lesson-actions">
                                    <a href="edit-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-primary">
                                        ✏️ تعديل
                                    </a>
                                    <a href="../../dashboard/student/lesson.php?id=<?php echo $lesson['id']; ?>" 
                                       class="btn btn-sm btn-outline" target="_blank">
                                        👁️ معاينة
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>لا توجد دروس خاصة</h3>
                        <p>لم يقم أي معلم بإضافة دروس خاصة لهذه المادة بعد</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($lessons)): ?>
                <div class="card">
                    <div class="card-body" style="text-align: center; padding: 4rem;">
                        <div style="font-size: 5rem;">📚</div>
                        <h2>لا توجد دروس في هذه المادة</h2>
                        <p style="color: #6b7280; margin: 1rem 0;">ستظهر الدروس هنا بمجرد إضافتها من قبل المشرفين أو المعلمين</p>
                        <a href="content.php" class="btn btn-outline" style="margin-top: 1rem;">
                            العودة لإدارة المحتوى
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
