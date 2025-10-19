<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
requireLogin();
requireRole(['superviseur_matiere', 'supervisor_subject', 'subject_supervisor']);

global $pdo;

// Get supervisor info
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, s.name as subject_name FROM users u 
          LEFT JOIN subjects s ON u.subject_id = s.id 
          WHERE u.id = :user_id";
$stmt = $pdo->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supervisor['subject_id']) {
    die('لم يتم تعيين مادة لهذا المشرف');
}

$subject_id = $supervisor['subject_id'];

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $lesson_id = $_POST['lesson_id'] ?? 0;
    $new_status = $_POST['new_status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (in_array($new_status, ['pending', 'approved', 'rejected'])) {
        $update = "UPDATE lessons SET status = :status, supervisor_notes = :notes, updated_at = NOW() 
                   WHERE id = :lesson_id AND subject_id = :subject_id";
        $stmt = $pdo->prepare($update);
        $stmt->execute([
            ':status' => $new_status,
            ':notes' => $notes,
            ':lesson_id' => $lesson_id,
            ':subject_id' => $subject_id
        ]);
        
        $success = "تم تغيير حالة الدرس بنجاح";
    }
}

// Filters
$status_filter = $_GET['status'] ?? 'all';
$teacher_filter = $_GET['teacher'] ?? '';
$type_filter = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query
$where_conditions = ["l.subject_id = :subject_id"];
$params = [':subject_id' => $subject_id];

if ($status_filter !== 'all') {
    $where_conditions[] = "l.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($teacher_filter)) {
    $where_conditions[] = "l.author_id = :teacher_id";
    $params[':teacher_id'] = $teacher_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "l.lesson_type = :type";
    $params[':type'] = $type_filter;
}

if (!empty($search)) {
    $where_conditions[] = "l.title LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

$where_clause = implode(' AND ', $where_conditions);

// Sort
$order_by = match($sort) {
    'oldest' => 'l.created_at ASC',
    'title' => 'l.title ASC',
    'teacher' => 'u.nom ASC',
    default => 'l.created_at DESC'
};

$query = "SELECT l.*, CONCAT(u.nom, ' ', u.prenom) as teacher_name, u.email as teacher_email,
          (SELECT COUNT(*) FROM exercises WHERE lesson_id = l.id) as exercises_count
          FROM lessons l
          JOIN users u ON l.author_id = u.id
          WHERE $where_clause
          ORDER BY $order_by";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teachers list for filter
$teachers_query = "SELECT DISTINCT u.id, CONCAT(u.nom, ' ', u.prenom) as full_name FROM users u
                   JOIN lessons l ON u.id = l.author_id
                   WHERE l.subject_id = :subject_id
                   ORDER BY u.nom, u.prenom";
$stmt = $pdo->prepare($teachers_query);
$stmt->execute([':subject_id' => $subject_id]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM lessons WHERE subject_id = :subject_id";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([':subject_id' => $subject_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جميع الدروس - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            margin: 20px 30px;
            border-radius: 10px;
            border-left: 4px solid #28a745;
        }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 3px solid #667eea;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.total { border-top: 4px solid #667eea; }
        .stat-card.pending { border-top: 4px solid #FF9800; }
        .stat-card.approved { border-top: 4px solid #4CAF50; }
        .stat-card.rejected { border-top: 4px solid #f44336; }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .filters-section {
            padding: 30px;
            background: white;
            border-bottom: 2px solid #e0e0e0;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .lessons-section {
            padding: 30px;
        }

        .section-title {
            font-size: 1.8em;
            margin-bottom: 20px;
            color: #333;
        }

        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .lesson-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border: 2px solid #e0e0e0;
        }

        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .lesson-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            flex: 1;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            white-space: nowrap;
            margin-right: 10px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #28a745;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
        }

        .lesson-meta {
            display: grid;
            gap: 10px;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9em;
            color: #555;
        }

        .meta-icon {
            font-size: 1.2em;
        }

        .lesson-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 15px 0;
        }

        .badge {
            padding: 5px 12px;
            background: #e9ecef;
            border-radius: 15px;
            font-size: 0.85em;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge.has-video {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge.has-pdf {
            background: #fff3e0;
            color: #e65100;
        }

        .lesson-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 0.9em;
            text-decoration: none;
            text-align: center;
            border-radius: 6px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-review {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-review:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-approve {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }

        .btn-reject {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }

        .btn-pending {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 5em;
            margin-bottom: 20px;
        }

        .empty-text {
            font-size: 1.5em;
            color: #666;
            margin-bottom: 10px;
        }

        .empty-subtext {
            color: #999;
        }

        /* Status change modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        @media (max-width: 768px) {
            .lessons-grid {
                grid-template-columns: 1fr;
            }

            .stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 جميع الدروس</h1>
            <p>مادة: <?php echo htmlspecialchars($supervisor['subject_name']); ?></p>
        </div>

        <?php if (isset($success)): ?>
            <div class="success-message">
                ✓ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Bar -->
        <div class="stats-bar">
            <div class="stat-card total">
                <div class="stat-label">إجمالي الدروس</div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label">⏳ معلقة</div>
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card approved">
                <div class="stat-label">✅ معتمدة</div>
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-label">❌ مرفوضة</div>
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>🔍 الحالة</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>الكل</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>⏳ معلقة</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>✅ معتمدة</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>❌ مرفوضة</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>👨‍🏫 الأستاذ</label>
                        <select name="teacher">
                            <option value="">الكل</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" 
                                    <?php echo $teacher_filter == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>📖 نوع الدرس</label>
                        <select name="type">
                            <option value="">الكل</option>
                            <option value="interactive" <?php echo $type_filter === 'interactive' ? 'selected' : ''; ?>>تفاعلي</option>
                            <option value="مقروء" <?php echo $type_filter === 'مقروء' ? 'selected' : ''; ?>>مقروء</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>🔄 الترتيب</label>
                        <select name="sort">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>الأحدث</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>الأقدم</option>
                            <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>حسب العنوان</option>
                            <option value="teacher" <?php echo $sort === 'teacher' ? 'selected' : ''; ?>>حسب الأستاذ</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>🔎 بحث في العنوان</label>
                        <input type="text" name="search" placeholder="ابحث عن درس..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">🔍 تطبيق الفلاتر</button>
                    <a href="all-lessons.php" class="btn btn-secondary">🔄 إعادة تعيين</a>
                    <a href="index.php" class="btn btn-secondary">🏠 العودة للرئيسية</a>
                </div>
            </form>
        </div>

        <!-- Lessons Section -->
        <div class="lessons-section">
            <h2 class="section-title">
                📋 النتائج (<?php echo count($lessons); ?> درس)
            </h2>

            <?php if (empty($lessons)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <div class="empty-text">لا توجد دروس</div>
                    <div class="empty-subtext">جرب تغيير الفلاتر أو البحث</div>
                </div>
            <?php else: ?>
                <div class="lessons-grid">
                    <?php foreach ($lessons as $lesson): ?>
                        <div class="lesson-card">
                            <div class="lesson-header">
                                <div class="lesson-title">
                                    <?php echo htmlspecialchars($lesson['title']); ?>
                                </div>
                                <span class="status-badge status-<?php echo $lesson['status']; ?>">
                                    <?php 
                                        echo match($lesson['status']) {
                                            'pending' => '⏳ معلق',
                                            'approved' => '✅ معتمد',
                                            'rejected' => '❌ مرفوض',
                                            default => $lesson['status']
                                        };
                                    ?>
                                </span>
                            </div>

                            <div class="lesson-meta">
                                <div class="meta-item">
                                    <span class="meta-icon">👨‍🏫</span>
                                    <span><?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-icon">📅</span>
                                    <span><?php echo date('Y/m/d', strtotime($lesson['created_at'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-icon">📖</span>
                                    <span><?php echo $lesson['lesson_type'] === 'interactive' ? 'تفاعلي' : 'مقروء'; ?></span>
                                </div>
                            </div>

                            <div class="lesson-badges">
                                <?php if ($lesson['exercises_count'] > 0): ?>
                                    <span class="badge">📝 <?php echo $lesson['exercises_count']; ?> تمرين</span>
                                <?php endif; ?>
                                <?php if (!empty($lesson['video_url'])): ?>
                                    <span class="badge has-video">🎥 فيديو</span>
                                <?php endif; ?>
                                <?php if (!empty($lesson['pdf_url'])): ?>
                                    <span class="badge has-pdf">📄 PDF</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($lesson['supervisor_notes'])): ?>
                                <div style="background: #fff3cd; padding: 10px; border-radius: 8px; margin: 10px 0; font-size: 0.9em;">
                                    <strong>📝 ملاحظات:</strong>
                                    <p style="margin-top: 5px;"><?php echo nl2br(htmlspecialchars($lesson['supervisor_notes'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="lesson-actions">
                                <a href="review-lesson.php?id=<?php echo $lesson['id']; ?>" 
                                   class="btn-small btn-review">
                                    👁️ مراجعة
                                </a>
                                <button type="button" 
                                        class="btn-small <?php echo $lesson['status'] === 'approved' ? 'btn-pending' : 'btn-approve'; ?>"
                                        onclick="openStatusModal(<?php echo $lesson['id']; ?>, '<?php echo htmlspecialchars($lesson['title']); ?>', '<?php echo $lesson['status']; ?>')">
                                    <?php echo $lesson['status'] === 'approved' ? '⏳ إلغاء الموافقة' : '✅ موافقة سريعة'; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-header">تغيير حالة الدرس</h3>
            <form method="POST" action="">
                <input type="hidden" name="change_status" value="1">
                <input type="hidden" name="lesson_id" id="modal_lesson_id">
                
                <div class="form-group">
                    <label>عنوان الدرس:</label>
                    <div id="modal_lesson_title" style="padding: 10px; background: #f8f9fa; border-radius: 8px; font-weight: bold;"></div>
                </div>

                <div class="form-group">
                    <label>الحالة الجديدة:</label>
                    <select name="new_status" id="modal_new_status" required>
                        <option value="pending">⏳ معلق</option>
                        <option value="approved">✅ معتمد</option>
                        <option value="rejected">❌ مرفوض</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>ملاحظات (اختياري):</label>
                    <textarea name="notes" id="modal_notes" placeholder="أضف ملاحظات إذا أردت..."></textarea>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeStatusModal()">إلغاء</button>
                    <button type="submit" class="btn btn-primary">✓ حفظ التغيير</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openStatusModal(lessonId, lessonTitle, currentStatus) {
            document.getElementById('modal_lesson_id').value = lessonId;
            document.getElementById('modal_lesson_title').textContent = lessonTitle;
            
            // Set default new status based on current
            const newStatusSelect = document.getElementById('modal_new_status');
            if (currentStatus === 'pending') {
                newStatusSelect.value = 'approved';
            } else if (currentStatus === 'approved') {
                newStatusSelect.value = 'pending';
            } else {
                newStatusSelect.value = 'pending';
            }
            
            document.getElementById('statusModal').classList.add('active');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
            document.getElementById('modal_notes').value = '';
        }

        // Close modal when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });
    </script>
</body>
</html>
