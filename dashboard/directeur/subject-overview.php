<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['directeur']);

global $pdo;

// الحصول على معرف المادة
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if ($subject_id == 0) {
    header('Location: subjects.php');
    exit;
}

// جلب معلومات المادة
try {
    $subject_query = $pdo->prepare("
        SELECT s.*, st.name as stage_name 
        FROM subjects s 
        LEFT JOIN stages st ON s.stage_id = st.id 
        WHERE s.id = ?
    ");
    $subject_query->execute([$subject_id]);
    $subject = $subject_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$subject) {
        header('Location: subjects.php');
        exit;
    }
} catch (PDOException $e) {
    die("خطأ في جلب المادة: " . $e->getMessage());
}

// جلب جميع المراحل مع مستوياتها
try {
    $stages = $pdo->query("
        SELECT * FROM stages 
        ORDER BY `order`, id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // لكل مرحلة، جلب مستوياتها
    foreach ($stages as &$stage) {
        $levels = $pdo->prepare("
            SELECT * FROM levels 
            WHERE stage_id = ? 
            ORDER BY `order`, id
        ");
        $levels->execute([$stage['id']]);
        $stage['levels'] = $levels->fetchAll(PDO::FETCH_ASSOC);
        
        // لكل مستوى، جلب المواد (مع عدد الدروس)
        foreach ($stage['levels'] as &$level) {
            $subjects_query = $pdo->prepare("
                SELECT s.*, 
                       (SELECT COUNT(*) FROM lessons WHERE subject_id = s.id AND level_id = ?) as lessons_count
                FROM subjects s
                WHERE s.stage_id = ?
                ORDER BY s.name
            ");
            $subjects_query->execute([$level['id'], $stage['id']]);
            $level['subjects'] = $subjects_query->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    die("خطأ في جلب البيانات: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظرة عامة - <?php echo htmlspecialchars($subject['name']); ?></title>
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
        
        .overview-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .stage-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .stage-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            font-size: 1.4rem;
            font-weight: 700;
        }
        
        .levels-container {
            padding: 20px;
        }
        
        .level-section {
            margin-bottom: 25px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .level-section:last-child {
            margin-bottom: 0;
        }
        
        .level-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px 20px;
            border-bottom: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .level-header h4 {
            margin: 0;
            color: #333;
            font-size: 1.2rem;
        }
        
        .subjects-list {
            padding: 15px 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .subject-item {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .subject-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        
        .subject-item.current {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-color: #1976d2;
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
        }
        
        .subject-item-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .subject-item-icon {
            font-size: 1.5rem;
        }
        
        .subject-item-name {
            font-weight: 700;
            color: #333;
            font-size: 1.1rem;
        }
        
        .subject-item-stats {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
        }
        
        .stat-badge {
            background: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #666;
        }
        
        .view-lessons-btn {
            display: block;
            text-align: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .view-lessons-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .current-badge {
            background: #1976d2;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
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
            <?php echo htmlspecialchars($subject['name']); ?>
        </div>
        
        <!-- Page Header -->
        <div class="page-header">
            <h1>📖 نظرة عامة - <?php echo htmlspecialchars($subject['name']); ?></h1>
            <p>المرحلة: <?php echo htmlspecialchars($subject['stage_name']); ?></p>
            <small>استعرض جميع المراحل والمستويات والدروس المتعلقة بهذه المادة</small>
        </div>
        
        <!-- Overview Container -->
        <div class="overview-container">
            <?php if (empty($stages)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📂</div>
                    <h4>لا توجد مراحل دراسية</h4>
                    <p>يجب إضافة المراحل والمستويات أولاً</p>
                    <a href="stages.php" class="btn-add">إضافة المراحل</a>
                </div>
            <?php else: ?>
                <?php foreach ($stages as $stage): ?>
                    <div class="stage-card">
                        <div class="stage-card-header">
                            🎓 <?php echo htmlspecialchars($stage['name']); ?>
                        </div>
                        
                        <div class="levels-container">
                            <?php if (empty($stage['levels'])): ?>
                                <div class="empty-state">
                                    <p style="color: #999;">لا توجد مستويات في هذه المرحلة</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($stage['levels'] as $level): ?>
                                    <div class="level-section">
                                        <div class="level-header">
                                            <span style="font-size: 1.3rem;">📚</span>
                                            <h4><?php echo htmlspecialchars($level['name']); ?></h4>
                                            <span style="margin-right: auto; color: #999; font-size: 0.9rem;">
                                                (<?php echo count($level['subjects']); ?> مادة)
                                            </span>
                                        </div>
                                        
                                        <div class="subjects-list">
                                            <?php if (empty($level['subjects'])): ?>
                                                <div style="grid-column: 1 / -1; text-align: center; padding: 20px; color: #999;">
                                                    لا توجد مواد في هذا المستوى
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($level['subjects'] as $subj): ?>
                                                    <div class="subject-item <?php echo ($subj['id'] == $subject_id) ? 'current' : ''; ?>">
                                                        <div class="subject-item-header">
                                                            <div class="subject-item-icon">📖</div>
                                                            <div class="subject-item-name">
                                                                <?php echo htmlspecialchars($subj['name']); ?>
                                                                <?php if ($subj['id'] == $subject_id): ?>
                                                                    <br><span class="current-badge">المادة الحالية</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="subject-item-stats">
                                                            <span class="stat-badge" title="عدد الدروس">
                                                                📚 <?php echo $subj['lessons_count']; ?> درس
                                                            </span>
                                                        </div>
                                                        
                                                        <?php if ($subj['lessons_count'] > 0): ?>
                                                            <a href="subject-lessons.php?subject_id=<?php echo $subj['id']; ?>&level_id=<?php echo $level['id']; ?>" 
                                                               class="view-lessons-btn">
                                                                👁️ عرض الدروس
                                                            </a>
                                                        <?php else: ?>
                                                            <div style="text-align: center; padding: 10px; margin-top: 10px; color: #999; font-size: 0.9rem;">
                                                                لا توجد دروس بعد
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
