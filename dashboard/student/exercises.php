<?php
/**
 * Student Exercises Page - صفحة التمارين المتاحة
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['student', 'etudiant']);

$user_id = $_SESSION['user_id'];

// جلب جميع التمارين المتاحة من الدروس المو افق عليها
$exercises = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.*, l.title as lesson_title, s.name as subject_name
        FROM exercises e
        JOIN lessons l ON e.lesson_id = l.id
        JOIN subjects s ON l.subject_id = s.id
        WHERE l.status = 'approved'
        ORDER BY e.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $exercises = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log("Error fetching exercises: " . $ex->getMessage());
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التمارين المتاحة - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="exercises-mobile.css">
    <style>
        @media (max-width: 968px) {
            .main-content {
                margin-right: 0 !important;
                padding: 20px;
            }
        }
    </style>
    <style>
        .exercises-header {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(34, 197, 94, 0.3);
        }
        
        .exercises-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .exercises-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .exercise-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .exercise-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .exercise-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .exercise-meta {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .exercise-description {
            color: #374151;
            margin: 15px 0;
            line-height: 1.6;
        }
        
        .exercise-link {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .exercise-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
        }
        
        .no-exercises {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        .no-exercises-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .no-exercises p {
            color: #6b7280;
            font-size: 1.1rem;
        }
        
        /* Content Type Badges */
        .content-badges {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
        }
        
        .content-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .content-badge.pdf {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        
        .content-badge.equation {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .content-badge:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="exercises-header">
                <h1>✍️ التمارين المتاحة</h1>
                <p>استعرض جميع التمارين المتاحة وابدأ في تحسين مهاراتك</p>
            </div>
            
            <?php if (empty($exercises)): ?>
                <div class="no-exercises">
                    <div class="no-exercises-icon">✍️</div>
                    <h2>لا توجد تمارين متاحة حالياً</h2>
                    <p>سيتم إضافة التمارين قريباً من قبل معلميك</p>
                </div>
            <?php else: ?>
                <div class="exercises-grid">
                    <?php foreach ($exercises as $exercise): ?>
                        <div class="exercise-card">
                            <div class="exercise-title">
                                <span>✍️</span>
                                <?php echo htmlspecialchars($exercise['title']); ?>
                            </div>
                            
                            <div class="exercise-meta">
                                <span>📚</span>
                                <span>الدرس: <?php echo htmlspecialchars($exercise['lesson_title']); ?></span>
                            </div>
                            
                            <div class="exercise-meta">
                                <span>🎓</span>
                                <span>المادة: <?php echo htmlspecialchars($exercise['subject_name']); ?></span>
                            </div>
                            
                            <?php if (!empty($exercise['description'])): ?>
                                <div class="exercise-description">
                                    <?php 
                                    $desc = htmlspecialchars($exercise['description']);
                                    echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                                <!-- Content Type Icons -->
                                <div class="content-badges">
                                    <?php if (!empty($exercise['pdf_url'])): ?>
                                        <span class="content-badge pdf" title="يحتوي على ملف PDF">📄</span>
                                    <?php endif; ?>
                                    
                                    <?php if (preg_match('/\$.*?\$|\$\$.*?\$\$/s', $exercise['question'] ?? '')): ?>
                                        <span class="content-badge equation" title="يحتوي على معادلات رياضية">🔢</span>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="lesson-view.php?id=<?php echo $exercise['lesson_id']; ?>" class="exercise-link" style="margin-top: 0;">
                                    عرض الدرس <span>←</span>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
