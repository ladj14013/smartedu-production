<?php
/**
 * Student Lesson View - عرض تفاصيل الدرس
 */

// اختبار الإخراج
echo "<!-- DEBUG: File loaded -->\n";

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['student', 'etudiant']); // دعم كلا الدورين

$user_id = $_SESSION['user_id'];
$student_id = $user_id; // استخدام student_id لوضوح الكود
$lesson_id = $_GET['id'] ?? 0;

if (!$lesson_id) {
    header('Location: lessons.php');
    exit;
}

// جلب معلومات الطالب
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

// جلب معلومات الدرس
$stmt = $pdo->prepare("
    SELECT l.*, 
           s.name as subject_name,
           lv.name as level_name,
           st.name as stage_name,
           u.name as teacher_name, u.email as teacher_email
    FROM lessons l
    JOIN subjects s ON l.subject_id = s.id
    LEFT JOIN levels lv ON l.level_id = lv.id
    LEFT JOIN stages st ON lv.stage_id = st.id
    JOIN users u ON l.author_id = u.id
    WHERE l.id = ? AND l.status = 'approved'
");
$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header('Location: lessons.php');
    exit;
}

// جلب التمارين المتعلقة بالدرس مع معلومات الإنجاز
$stmt = $pdo->prepare("
    SELECT e.*,
           sa.id as answer_id,
           sa.score as last_score,
           CASE WHEN sa.id IS NOT NULL THEN 1 ELSE 0 END as is_completed
    FROM exercises e
    LEFT JOIN student_answers sa ON e.id = sa.exercise_id AND sa.student_id = ?
    WHERE e.lesson_id = ?
    ORDER BY e.`order` ASC, e.created_at ASC
");
$stmt->execute([$student_id, $lesson_id]);
$exercises = $stmt->fetchAll();

// حساب نسبة الإنجاز
$total_exercises = count($exercises);
$completed_exercises = 0;
$total_score = 0;

foreach ($exercises as $exercise) {
    if ($exercise['is_completed']) {
        $completed_exercises++;
        $total_score += $exercise['last_score'] ?? 0;
    }
}

$completion_percent = $total_exercises > 0 ? round(($completed_exercises / $total_exercises) * 100) : 0;
$average_score = $completed_exercises > 0 ? round($total_score / $completed_exercises) : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title']); ?> - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="lesson-view-mobile.css">
    <style>
        .lesson-header-section {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(34, 197, 94, 0.3);
        }
        
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .breadcrumb a {
            color: white;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .subject-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .lesson-header-section h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .lesson-meta {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            font-size: 0.95rem;
            opacity: 0.95;
        }
        
        .lesson-meta span {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .content-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card h3 {
            font-size: 1.3rem;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .video-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 */
            height: 0;
            overflow: hidden;
            border-radius: 12px;
            margin-bottom: 25px;
            background: #000;
        }
        
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .lesson-description {
            color: #374151;
            line-height: 1.8;
            font-size: 1.05rem;
        }
        
        .progress-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #22c55e;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .progress-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .progress-stat {
            text-align: center;
        }
        
        .progress-stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #16a34a;
        }
        
        .progress-stat-label {
            color: #374151;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .progress-bar-container {
            background: rgba(255,255,255,0.5);
            height: 12px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #22c55e, #16a34a);
            transition: width 0.5s ease;
            border-radius: 10px;
        }
        
        .progress-text {
            text-align: center;
            font-weight: 600;
            color: #16a34a;
        }
        
        .exercises-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .exercise-item {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .exercise-item:hover {
            border-color: #22c55e;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.15);
        }
        
        .exercise-item.completed {
            background: #f0fdf4;
            border-color: #22c55e;
        }
        
        .exercise-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .exercise-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            flex: 1;
        }
        
        .exercise-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .exercise-description {
            color: #6b7280;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .exercise-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .score-display {
            font-size: 1.1rem;
            font-weight: 700;
            color: #16a34a;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #22c55e;
            color: #22c55e;
        }
        
        .teacher-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 12px;
        }
        
        .teacher-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }
        
        .teacher-details h4 {
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .teacher-details p {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .btn-pdf-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.5);
        }
        
        .btn-download {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.5);
        }
        
        .btn-print {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.5);
        }
        
        .btn-pdf-action i {
            font-size: 1rem;
        }
        
        .lesson-content {
            line-height: 1.8;
            color: #374151;
            font-size: 1.05rem;
        }
        
        .images-grid {
            display: grid;
            gap: 15px;
        }
        
        @media (max-width: 968px) {
            .content-layout {
                grid-template-columns: 1fr;
            }
            
            .lesson-header-section h1 {
                font-size: 1.8rem;
            }
        }
    </style>
    
    <!-- KaTeX CSS for Math Equations -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" integrity="sha384-n8MVd4RsNIU0tAv4ct0nTaAbDJwPJzDEaqSD1odI+WdtXRGWt2kTvGFasHpSy3SV" crossorigin="anonymous">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <!-- Lesson Header -->
            <div class="lesson-header-section">
                <div class="breadcrumb">
                    <a href="index.php">الرئيسية</a> / 
                    <a href="lessons.php">الدروس</a> / 
                    <?php echo htmlspecialchars($lesson['title']); ?>
                </div>
                
                <span class="subject-badge">
                    <span>📚</span>
                    <span><?php echo htmlspecialchars($lesson['subject_name']); ?></span>
                </span>
                
                <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
                
                <div class="lesson-meta">
                    <span>👨‍🏫 <?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                    <?php if ($lesson['stage_name']): ?>
                        <span>🎓 <?php echo htmlspecialchars($lesson['stage_name']); ?></span>
                    <?php endif; ?>
                    <?php if ($total_exercises > 0): ?>
                        <span>✍️ <?php echo $total_exercises; ?> تمرين</span>
                    <?php endif; ?>
                    <span>📅 <?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?></span>
                </div>
            </div>
            
            <!-- Content Layout -->
            <div class="content-layout">
                <!-- Main Content -->
                <div>
                    <!-- Video -->
                    <?php if ($lesson['video_url']): ?>
                        <div class="video-container">
                            <?php
                            $video_url = $lesson['video_url'];
                            // تحويل رابط YouTube إلى رابط embed
                            if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                                preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video_url, $matches);
                                if (!empty($matches[1])) {
                                    $video_url = 'https://www.youtube.com/embed/' . $matches[1];
                                }
                            }
                            ?>
                            <iframe src="<?php echo htmlspecialchars($video_url); ?>" 
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen></iframe>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Description -->
                    <div class="card">
                        <h3>📝 وصف الدرس</h3>
                        <div class="lesson-description">
                            <?php echo nl2br(htmlspecialchars($lesson['description'] ?? 'لا يوجد وصف')); ?>
                        </div>
                    </div>
                    
                    <!-- Lesson Content -->
                    <div class="card">
                        <h3>📚 محتوى الدرس</h3>
                        <div class="lesson-content">
                            <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                        </div>
                    </div>
                    
                    <!-- PDF File -->
                    <?php if (!empty($lesson['pdf_url'])): ?>
                        <div class="card">
                            <h3>📄 ملف PDF مرفق</h3>
                            
                            <!-- PDF Actions -->
                            <div style="margin-top: 20px;">
                                <div style="background: #f9fafb; padding: 15px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e5e7eb; flex-wrap: wrap; gap: 10px;">
                                    <span style="font-weight: 600; color: #374151;">
                                        <i class="fas fa-file-pdf" style="color: #ef4444; margin-left: 8px;"></i>
                                        ملف PDF التعليمي
                                    </span>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <a href="../../<?php echo htmlspecialchars($lesson['pdf_url']); ?>" 
                                           target="_blank" 
                                           class="btn-pdf-action btn-view">
                                            <i class="fas fa-eye"></i>
                                            عرض
                                        </a>
                                        <a href="../../<?php echo htmlspecialchars($lesson['pdf_url']); ?>" 
                                           download 
                                           class="btn-pdf-action btn-download">
                                            <i class="fas fa-download"></i>
                                            تحميل
                                        </a>
                                        <button onclick="printPDF()" class="btn-pdf-action btn-print">
                                            <i class="fas fa-print"></i>
                                            طباعة
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- PDF Viewer -->
                                <div style="position: relative; background: #f3f4f6;">
                                    <iframe 
                                        id="pdfViewer"
                                        src="../../<?php echo htmlspecialchars($lesson['pdf_url']); ?>" 
                                        style="width: 100%; height: 700px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; background: white; display: block;"
                                        frameborder="0"
                                        onload="checkPdfLoad(this)">
                                    </iframe>
                                    <!-- Fallback message if PDF doesn't load -->
                                    <div id="pdfFallback" style="display: none; padding: 60px 20px; text-align: center; background: white; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
                                        <div style="font-size: 4rem; margin-bottom: 20px;">📄</div>
                                        <h3 style="color: #374151; margin-bottom: 15px;">متصفحك لا يدعم عرض PDF مباشرة</h3>
                                        <p style="color: #6b7280; margin-bottom: 25px;">يمكنك تحميل الملف أو فتحه في تبويب جديد</p>
                                        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                                            <a href="../../<?php echo htmlspecialchars($lesson['pdf_url']); ?>" 
                                               target="_blank" 
                                               class="btn-pdf-action btn-view"
                                               style="display: inline-flex;">
                                                <i class="fas fa-external-link-alt"></i>
                                                فتح في تبويب جديد
                                            </a>
                                            <a href="../../<?php echo htmlspecialchars($lesson['pdf_url']); ?>" 
                                               download 
                                               class="btn-pdf-action btn-download"
                                               style="display: inline-flex;">
                                                <i class="fas fa-download"></i>
                                                تحميل الملف
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <p style="text-align: center; color: #9ca3af; font-size: 0.85rem; margin-top: 10px; padding: 10px; background: #f9fafb; border-radius: 8px;">
                                    <i class="fas fa-info-circle"></i>
                                    يمكنك عرض وتحميل وطباعة الملف
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Images -->
                    <?php if (!empty($lesson['images'])): ?>
                        <div class="card">
                            <h3>🖼️ صور توضيحية</h3>
                            <div class="images-grid">
                                <?php
                                $images = json_decode($lesson['images'], true);
                                if (is_array($images)) {
                                    foreach ($images as $image) {
                                        echo '<img src="' . htmlspecialchars($image) . '" alt="صورة توضيحية" style="width: 100%; border-radius: 8px; margin-bottom: 10px;">';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div>
                    <!-- Progress -->
                    <div class="progress-card">
                        <h3 style="color: #16a34a; margin-bottom: 15px;">📊 تقدمك في هذا الدرس</h3>
                        
                        <div class="progress-stats">
                            <div class="progress-stat">
                                <div class="progress-stat-value"><?php echo $completed_exercises; ?>/<?php echo $total_exercises; ?></div>
                                <div class="progress-stat-label">تمارين منجزة</div>
                            </div>
                            
                            <div class="progress-stat">
                                <div class="progress-stat-value"><?php echo $average_score; ?>%</div>
                                <div class="progress-stat-label">متوسط الدرجات</div>
                            </div>
                        </div>
                        
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?php echo $completion_percent; ?>%"></div>
                        </div>
                        <div class="progress-text"><?php echo $completion_percent; ?>% مكتمل</div>
                    </div>
                    
                    <!-- Teacher Info -->
                    <div class="card">
                        <h3>👨‍🏫 المعلم</h3>
                        <div class="teacher-info">
                            <div class="teacher-avatar">👨‍🏫</div>
                            <div class="teacher-details">
                                <h4><?php echo htmlspecialchars($lesson['teacher_name']); ?></h4>
                                <p><?php echo htmlspecialchars($lesson['teacher_email']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Exercises Section -->
            <?php if (!empty($exercises)): ?>
                <div class="card">
                    <h3>✍️ تمارين الدرس (<?php echo $total_exercises; ?>)</h3>
                    
                    <div class="exercises-list">
                        <?php foreach ($exercises as $index => $exercise): ?>
                            <div class="exercise-item <?php echo $exercise['is_completed'] ? 'completed' : ''; ?>">
                                <div class="exercise-header">
                                    <div class="exercise-title">
                                        <?php echo ($index + 1) . '. ' . htmlspecialchars(mb_substr($exercise['question'], 0, 100)) . (mb_strlen($exercise['question']) > 100 ? '...' : ''); ?>
                                    </div>
                                    <span class="exercise-status <?php echo $exercise['is_completed'] ? 'status-completed' : 'status-pending'; ?>">
                                        <?php echo $exercise['is_completed'] ? '✓ مكتمل' : '⏳ لم يتم'; ?>
                                    </span>
                                </div>
                                
                                <?php if ($exercise['model_answer'] && $exercise['is_completed']): ?>
                                    <div class="exercise-description">
                                        <strong>الإجابة النموذجية:</strong> <?php echo htmlspecialchars(mb_substr($exercise['model_answer'], 0, 150)) . (mb_strlen($exercise['model_answer']) > 150 ? '...' : ''); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="exercise-footer">
                                    <?php if ($exercise['is_completed']): ?>
                                        <div class="score-display">
                                            🏆 النتيجة: <?php echo $exercise['last_score']; ?>%
                                        </div>
                                        <a href="exercise-solve.php?id=<?php echo $exercise['id']; ?>" class="btn btn-outline">
                                            إعادة المحاولة
                                        </a>
                                    <?php else: ?>
                                        <div></div>
                                        <a href="exercise-solve.php?id=<?php echo $exercise['id']; ?>" class="btn btn-primary">
                                            بدء التمرين →
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 40px;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">✍️</div>
                    <h3>لا توجد تمارين بعد</h3>
                    <p style="color: #6b7280;">سيتم إضافة التمارين قريباً</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- KaTeX JS for Math Equations -->
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js" integrity="sha384-XjKyOOlGwcjNTAIQHIpgOno0Hl1YQqzUOEleOLALmuqehneUG+vnGctmUb0ZY0l8" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js" integrity="sha384-+VBxd3r6XgURycqtZ117nYw44OOcIax56Z4dCRWbxyPt0Koah1uHoK0o4+/RRE05" crossorigin="anonymous"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Render all math equations in the page
            renderMathInElement(document.body, {
                delimiters: [
                    {left: '$$', right: '$$', display: true},   // Display math (centered)
                    {left: '$', right: '$', display: false},    // Inline math
                    {left: '\\[', right: '\\]', display: true}, // LaTeX display
                    {left: '\\(', right: '\\)', display: false} // LaTeX inline
                ],
                throwOnError: false,
                errorColor: '#cc0000',
                trust: true
            });
        });
        
        // Print PDF function
        function printPDF() {
            var iframe = document.getElementById('pdfViewer');
            if (iframe) {
                try {
                    // Try to print the iframe content
                    iframe.contentWindow.print();
                } catch (e) {
                    // If failed, open PDF in new window and print
                    var pdfUrl = iframe.src;
                    var printWindow = window.open(pdfUrl, '_blank');
                    if (printWindow) {
                        printWindow.onload = function() {
                            printWindow.print();
                        };
                    } else {
                        alert('يرجى السماح بفتح النوافذ المنبثقة للطباعة');
                    }
                }
            }
        }
        
        // Check if PDF loaded successfully
        function checkPdfLoad(iframe) {
            setTimeout(function() {
                try {
                    // Try to access iframe content
                    var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    
                    // If iframe is empty or shows error, show fallback
                    if (!iframeDoc || iframeDoc.body.innerHTML === '' || 
                        iframeDoc.querySelector('embed') === null) {
                        document.getElementById('pdfViewer').style.display = 'none';
                        document.getElementById('pdfFallback').style.display = 'block';
                    }
                } catch (e) {
                    // Cross-origin or other error - PDF might be loading fine
                    // Don't show fallback if it's just a security error
                    console.log('PDF viewer security restriction (this is normal)');
                }
            }, 2000); // Wait 2 seconds for PDF to load
        }
    </script>
</body>
</html>
