<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['enseignant', 'teacher']);

global $pdo;
$teacher_id = $_SESSION['user_id'];

// Get teacher info
$teacher_stmt = $pdo->prepare("
    SELECT u.*, s.name as subject_name 
    FROM users u
    LEFT JOIN subjects s ON u.subject_id = s.id
    WHERE u.id = ?
");
$teacher_stmt->execute([$teacher_id]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

// Handle grading submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_answer'])) {
    $answer_id = intval($_POST['answer_id']);
    $score = floatval($_POST['score']);
    
    // حفظ النسبة فقط (الملاحظات غير مستخدمة حالياً)
    $update = $pdo->prepare("UPDATE student_answers SET score = ? WHERE id = ?");
    $update->execute([$score, $answer_id]);
    
    $success_message = "تم حفظ الدرجة بنجاح!";
}

// Get filter parameters
$filter_lesson = $_GET['lesson'] ?? '';
$filter_student = $_GET['student'] ?? '';
$filter_status = $_GET['status'] ?? 'all'; // all, graded, ungraded

// Build query
$where_conditions = ["l.author_id = :teacher_id"];
$params = [':teacher_id' => $teacher_id];

if ($filter_lesson) {
    $where_conditions[] = "l.id = :lesson_id";
    $params[':lesson_id'] = $filter_lesson;
}

if ($filter_student) {
    $where_conditions[] = "u.id = :student_id";
    $params[':student_id'] = $filter_student;
}

if ($filter_status === 'graded') {
    $where_conditions[] = "sa.score IS NOT NULL";
} elseif ($filter_status === 'ungraded') {
    $where_conditions[] = "sa.score IS NULL";
}

$where_clause = implode(' AND ', $where_conditions);

// Get student answers
$query = "SELECT 
    sa.id as answer_id,
    sa.answer,
    sa.score,
    sa.submitted_at,
    e.question,
    e.model_answer,
    l.title as lesson_title,
    CONCAT(u.nom, ' ', u.prenom) as student_name,
    u.email as student_email,
    u.id as student_id
FROM student_answers sa
JOIN exercises e ON sa.exercise_id = e.id
JOIN lessons l ON e.lesson_id = l.id
JOIN users u ON sa.student_id = u.id
WHERE $where_clause
ORDER BY sa.submitted_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get lessons for filter
$lessons_query = $pdo->prepare("SELECT id, title FROM lessons WHERE author_id = ? ORDER BY created_at DESC");
$lessons_query->execute([$teacher_id]);
$lessons = $lessons_query->fetchAll(PDO::FETCH_ASSOC);

// Get students for filter
$students_query = $pdo->prepare("
    SELECT DISTINCT u.id, CONCAT(u.nom, ' ', u.prenom) as full_name
    FROM users u
    JOIN student_answers sa ON u.id = sa.student_id
    JOIN exercises e ON sa.exercise_id = e.id
    JOIN lessons l ON e.lesson_id = l.id
    WHERE l.author_id = ?
    ORDER BY u.nom, u.prenom
");
$students_query->execute([$teacher_id]);
$students = $students_query->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_answers = count($answers);
$graded_count = count(array_filter($answers, fn($a) => $a['score'] !== null));
$ungraded_count = $total_answers - $graded_count;
$avg_score = $graded_count > 0 ? array_sum(array_column(array_filter($answers, fn($a) => $a['score'] !== null), 'score')) / $graded_count : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تصحيح التمارين - لوحة تحكم الأستاذ</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 12px;
            color: white;
            text-align: center;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .filters h3 {
            margin-bottom: 15px;
            color: #2d3748;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #4a5568;
        }
        .filter-group select {
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .btn-filter {
            padding: 10px 25px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .btn-filter:hover { background: #5568d3; }
        .answers-list {
            display: grid;
            gap: 20px;
        }
        .answer-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .answer-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f7fafc;
        }
        .student-info h3 {
            color: #2d3748;
            margin-bottom: 5px;
        }
        .student-info p {
            color: #718096;
            font-size: 0.9rem;
        }
        .answer-date {
            text-align: left;
            color: #a0aec0;
            font-size: 0.85rem;
        }
        .lesson-info {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .lesson-info h4 {
            color: #4a5568;
            margin-bottom: 8px;
        }
        .question-section {
            margin: 20px 0;
        }
        .section-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            padding: 8px 12px;
            background: #edf2f7;
            border-radius: 6px;
            display: inline-block;
        }
        .question-text, .model-answer, .student-answer {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            line-height: 1.8;
        }
        .question-text {
            background: #fff5f5;
            border-right: 4px solid #fc8181;
        }
        .model-answer {
            background: #f0fff4;
            border-right: 4px solid #48bb78;
        }
        .student-answer {
            background: #ebf8ff;
            border-right: 4px solid #4299e1;
        }
        .grading-form {
            background: #faf5ff;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group label {
            font-weight: 600;
            color: #553c9a;
            display: block;
            margin-bottom: 5px;
        }
        .form-group input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #d6bcfa;
            border-radius: 8px;
            font-size: 1.1rem;
        }
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #d6bcfa;
            border-radius: 8px;
            min-height: 100px;
            font-family: inherit;
            resize: vertical;
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .score-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .score-excellent { background: #c6f6d5; color: #22543d; }
        .score-good { background: #bee3f8; color: #2c5282; }
        .score-average { background: #feebc8; color: #7c2d12; }
        .score-poor { background: #fed7d7; color: #742a2a; }
        .success-message {
            background: #c6f6d5;
            color: #22543d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        .no-data {
            text-align: center;
            padding: 60px;
            color: #a0aec0;
        }
        .no-data i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4a5568;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .back-btn:hover { background: #2d3748; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">← العودة للوحة التحكم</a>
        
        <div class="header">
            <h1>📝 تصحيح التمارين</h1>
            <p>مرحباً أ. <?php echo htmlspecialchars($teacher['nom'] . ' ' . $teacher['prenom']); ?> - <?php echo htmlspecialchars($teacher['subject_name'] ?? 'غير محدد'); ?></p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="success-message">✓ <?php echo $success_message; ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_answers; ?></div>
                <div class="stat-label">إجمالي الإجابات</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $ungraded_count; ?></div>
                <div class="stat-label">بانتظار التصحيح</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $graded_count; ?></div>
                <div class="stat-label">تم تصحيحها</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($avg_score, 1); ?>%</div>
                <div class="stat-label">متوسط الدرجات</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <h3>🔍 فلترة النتائج</h3>
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>الدرس:</label>
                        <select name="lesson">
                            <option value="">جميع الدروس</option>
                            <?php foreach ($lessons as $lesson): ?>
                                <option value="<?php echo $lesson['id']; ?>" <?php echo $filter_lesson == $lesson['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lesson['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>الطالب:</label>
                        <select name="student">
                            <option value="">جميع الطلاب</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $filter_student == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>الحالة:</label>
                        <select name="status">
                            <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>الكل</option>
                            <option value="ungraded" <?php echo $filter_status == 'ungraded' ? 'selected' : ''; ?>>بانتظار التصحيح</option>
                            <option value="graded" <?php echo $filter_status == 'graded' ? 'selected' : ''; ?>>تم التصحيح</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="align-self: end;">
                        <button type="submit" class="btn-filter">تطبيق الفلتر</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Answers List -->
        <div class="answers-list">
            <?php if (empty($answers)): ?>
                <div class="no-data">
                    <div style="font-size: 4rem; margin-bottom: 20px;">📭</div>
                    <h3>لا توجد إجابات حالياً</h3>
                    <p>لم يقم أي طالب بحل التمارين بعد</p>
                </div>
            <?php else: ?>
                <?php foreach ($answers as $answer): 
                    $score = $answer['score'];
                    $score_class = '';
                    if ($score !== null) {
                        if ($score >= 85) $score_class = 'score-excellent';
                        elseif ($score >= 70) $score_class = 'score-good';
                        elseif ($score >= 50) $score_class = 'score-average';
                        else $score_class = 'score-poor';
                    }
                ?>
                    <div class="answer-card">
                        <div class="answer-header">
                            <div class="student-info">
                                <h3><?php echo htmlspecialchars($answer['student_name']); ?></h3>
                                <p><?php echo htmlspecialchars($answer['student_email']); ?></p>
                            </div>
                            <div class="answer-date">
                                <?php echo date('Y/m/d H:i', strtotime($answer['submitted_at'])); ?>
                            </div>
                        </div>

                        <div class="lesson-info">
                            <h4>📚 الدرس: <?php echo htmlspecialchars($answer['lesson_title']); ?></h4>
                        </div>

                        <div class="question-section">
                            <span class="section-title">❓ السؤال</span>
                            <div class="question-text">
                                <?php echo nl2br(htmlspecialchars($answer['question'])); ?>
                            </div>
                        </div>

                        <div class="question-section">
                            <span class="section-title">✅ الإجابة النموذجية</span>
                            <div class="model-answer">
                                <?php echo nl2br(htmlspecialchars($answer['model_answer'])); ?>
                            </div>
                        </div>

                        <div class="question-section">
                            <span class="section-title">✍️ إجابة الطالب</span>
                            <div class="student-answer">
                                <?php echo nl2br(htmlspecialchars($answer['answer'])); ?>
                            </div>
                        </div>

                        <?php if ($answer['score'] !== null): ?>
                            <div class="grading-form" style="background: #f0fff4;">
                                <h4 style="color: #22543d; margin-bottom: 15px;">✓ تم التصحيح</h4>
                                <p><strong>الدرجة:</strong> 
                                    <span class="score-badge <?php echo $score_class; ?>">
                                        <?php echo number_format($answer['score'], 1); ?>%
                                    </span>
                                </p>
                                <form method="POST" action="" style="margin-top: 15px;">
                                    <input type="hidden" name="answer_id" value="<?php echo $answer['answer_id']; ?>">
                                    <button type="submit" name="grade_answer" class="btn-submit" style="background: #48bb78;">
                                        تعديل التقييم
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" class="grading-form">
                                <h4 style="color: #553c9a; margin-bottom: 15px;">📊 تقييم الإجابة</h4>
                                <input type="hidden" name="answer_id" value="<?php echo $answer['answer_id']; ?>">
                                
                                <div class="form-group">
                                    <label>الدرجة (%):</label>
                                    <input type="number" name="score" min="0" max="100" step="0.1" required 
                                           style="max-width: 200px; font-size: 1.2rem; text-align: center;">
                                    <p style="color: #718096; font-size: 0.9rem; margin-top: 8px;">
                                        أدخل الدرجة من 0 إلى 100
                                    </p>
                                </div>
                                
                                <button type="submit" name="grade_answer" class="btn-submit">
                                    💾 حفظ الدرجة
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
