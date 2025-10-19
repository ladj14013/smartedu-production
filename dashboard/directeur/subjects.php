<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/platform.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['directeur']);

// استخدام PDO مباشرة
global $pdo;

$success = '';
$error = '';

// جلب المواد مع المراحل (المنطق الجديد: المادة مرتبطة بالمرحلة فقط)
try {
    $subjects = $pdo->query("
        SELECT s.*, st.name as stage_name, st.`order` as stage_order
        FROM subjects s 
        LEFT JOIN stages st ON s.stage_id = st.id
        ORDER BY st.`order`, s.name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $subjects = [];
    $error = "خطأ في جلب المواد: " . $e->getMessage();
}

// جلب المراحل
try {
    $stages = $pdo->query("SELECT * FROM stages ORDER BY `order`, id")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stages = [];
    $error = "خطأ في جلب المراحل: " . $e->getMessage();
}

// إضافة مادة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');
    $stage_id = intval($_POST['stage_id']);
    
    if (!empty($name) && $stage_id > 0) {
        try {
            // التحقق من عدم وجود نفس المادة في نفس المرحلة
            $check = $pdo->prepare("SELECT id FROM subjects WHERE name = ? AND stage_id = ?");
            $check->execute([$name, $stage_id]);
            
            if ($check->rowCount() > 0) {
                $error = '❌ هذه المادة موجودة بالفعل في هذه المرحلة';
            } else {
                $stmt = $pdo->prepare("INSERT INTO subjects (name, description, stage_id, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$name, $description, $stage_id]);
                $success = '✅ تم إضافة المادة بنجاح';
                header("Location: subjects.php?success=1");
                exit();
            }
        } catch (PDOException $e) {
            $error = '❌ خطأ في إضافة المادة: ' . $e->getMessage();
        }
    } else {
        $error = '❌ يرجى إدخال اسم المادة واختيار المرحلة';
    }
}

// حذف مادة
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        // التحقق من عدد الدروس المرتبطة
        $lessons_count = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE subject_id = ?");
        $lessons_count->execute([$id]);
        $count = $lessons_count->fetchColumn();
        
        if ($count > 0) {
            $error = "❌ لا يمكن حذف المادة لأنها تحتوي على $count درس. احذف الدروس أولاً.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: subjects.php?deleted=1");
            exit();
        }
    } catch (PDOException $e) {
        $error = '❌ خطأ في حذف المادة: ' . $e->getMessage();
    }
}

// رسائل النجاح
if (isset($_GET['success'])) {
    $success = '✅ تم إضافة المادة بنجاح';
}
if (isset($_GET['deleted'])) {
    $success = '✅ تم حذف المادة بنجاح';
}

echo get_standard_html_head('إدارة المواد الدراسية - SmartEdu', ['../../assets/css/dashboard.css']);
echo get_role_css($_SESSION['user_role']);
?>

<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- رأس الصفحة -->
        <div class="page-header">
            <h1>📚 إدارة المواد الدراسية</h1>
            <p>إضافة وتنظيم المواد الدراسية لكل مرحلة تعليمية</p>
            <small style="opacity: 0.9; display: block; margin-top: 10px;">
                💡 كل مادة مرتبطة بمرحلة واحدة، ويمكن تدريسها في جميع مستويات تلك المرحلة
            </small>
        </div>

        <!-- رسائل التنبيه -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">❌</span>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <!-- إحصائيات سريعة -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-details">
                    <h3><?php echo count($subjects); ?></h3>
                    <p>إجمالي المواد</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎓</div>
                <div class="stat-details">
                    <h3><?php echo count($stages); ?></h3>
                    <p>المراحل التعليمية</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📖</div>
                <div class="stat-details">
                    <?php
                    $lessons_count = $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
                    ?>
                    <h3><?php echo $lessons_count; ?></h3>
                    <p>إجمالي الدروس</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-details">
                    <?php
                    $teachers_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('enseignant', 'teacher')")->fetchColumn();
                    ?>
                    <h3><?php echo $teachers_count; ?></h3>
                    <p>الأساتذة</p>
                </div>
            </div>
        </div>

        <!-- تحذير إذا لم توجد مراحل -->
        <?php if (empty($stages)): ?>
            <div class="warning-card">
                <div class="warning-icon">⚠️</div>
                <div class="warning-content">
                    <h3>يجب إضافة المراحل التعليمية أولاً</h3>
                    <p>لإضافة المواد الدراسية، تحتاج إلى إنشاء المراحل التعليمية أولاً</p>
                    <a href="stages.php" class="btn-warning">إدارة المراحل والمستويات</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- الشبكة الرئيسية -->
        <div class="content-grid">
            <!-- نموذج إضافة مادة -->
            <div class="form-section">
                <div class="section-header">
                    <h2>➕ إضافة مادة دراسية جديدة</h2>
                    <p>أضف مادة دراسية جديدة لإحدى المراحل التعليمية</p>
                </div>
                
                <div class="form-container">
                    <form method="POST" class="modern-form">
                        <div class="form-group">
                            <label for="name">
                                <span class="label-icon">📝</span>
                                اسم المادة
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="form-input"
                                   placeholder="مثال: الرياضيات"
                                   required>
                            <small style="color: #6b7280; font-size: 0.875rem; margin-top: 5px;">
                                سيتم استخدام هذا الاسم في جميع مستويات المرحلة
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="stage_id">
                                <span class="label-icon">🎓</span>
                                المرحلة التعليمية
                            </label>
                            <select id="stage_id" name="stage_id" class="form-input" required>
                                <option value="">-- اختر المرحلة --</option>
                                <?php foreach ($stages as $stage): ?>
                                    <option value="<?php echo $stage['id']; ?>">
                                        <?php echo htmlspecialchars($stage['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: #6b7280; font-size: 0.875rem; margin-top: 5px;">
                                الأستاذ الذي يدرس هذه المادة يمكنه تدريسها في جميع مستويات المرحلة
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="description">
                                <span class="label-icon">📄</span>
                                وصف المادة (اختياري)
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      class="form-input"
                                      rows="3"
                                      placeholder="وصف مختصر عن المادة الدراسية"></textarea>
                        </div>
                        
                        <button type="submit" name="add_subject" class="btn-add">
                            <span>➕</span> إضافة المادة
                        </button>
                    </form>
                </div>
            </div>

            <!-- قائمة المواد -->
            <div class="subjects-section">
                <div class="section-header">
                    <h2>📋 المواد الدراسية الحالية</h2>
                    <p>جميع المواد المضافة مرتبة حسب المراحل التعليمية</p>
                </div>

                <div class="subjects-container">
                    <?php if (empty($subjects)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📚</div>
                            <h4>لا توجد مواد دراسية بعد</h4>
                            <p>ابدأ بإضافة المواد الدراسية للمراحل المختلفة</p>
                        </div>
                    <?php else: ?>
                        <!-- تجميع المواد حسب المرحلة -->
                        <?php
                        $grouped_subjects = [];
                        foreach ($subjects as $subject) {
                            $stage_name = $subject['stage_name'] ?? 'غير محدد';
                            $grouped_subjects[$stage_name][] = $subject;
                        }
                        ?>

                        <div class="subjects-tree">
                            <?php foreach ($grouped_subjects as $stage_name => $stage_subjects): ?>
                                <div class="stage-group">
                                    <div class="stage-header">
                                        <h3>🎓 <?php echo htmlspecialchars($stage_name); ?></h3>
                                        <span class="stage-count">
                                            <?php echo count($stage_subjects); ?> مادة
                                        </span>
                                    </div>
                                    
                                    <div class="subjects-grid">
                                        <?php foreach ($stage_subjects as $subject): ?>
                                            <div class="subject-card">
                                                <div class="subject-header">
                                                    <div class="subject-icon">📚</div>
                                                    <div class="subject-info">
                                                        <h5><?php echo htmlspecialchars($subject['name']); ?></h5>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($subject['description']): ?>
                                                    <div class="subject-description">
                                                        <p><?php echo htmlspecialchars($subject['description']); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="subject-footer">
                                                    <div class="subject-stats">
                                                        <?php
                                                        // عدد الدروس لهذه المادة
                                                        $lessons_count = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE subject_id = ?");
                                                        $lessons_count->execute([$subject['id']]);
                                                        $lessons = $lessons_count->fetchColumn();
                                                        
                                                        // عدد الأساتذة الذين يدرسون هذه المادة
                                                        $teachers_count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE subject_id = ? AND role IN ('enseignant', 'teacher')");
                                                        $teachers_count->execute([$subject['id']]);
                                                        $teachers = $teachers_count->fetchColumn();
                                                        ?>
                                                        <span class="stat" title="عدد الدروس">📖 <?php echo $lessons; ?> درس</span>
                                                        <span class="stat" title="عدد الأساتذة">👨‍🏫 <?php echo $teachers; ?> أستاذ</span>
                                                    </div>
                                                    <div class="subject-actions">
                                                        <a href="subject-overview.php?subject_id=<?php echo $subject['id']; ?>" 
                                                           class="btn-view"
                                                           title="عرض المراحل والمستويات والدروس">
                                                            👁️ عرض
                                                        </a>
                                                        <a href="?delete=<?php echo $subject['id']; ?>" 
                                                           class="btn-delete"
                                                           onclick="return confirm('⚠️ هل أنت متأكد من حذف هذه المادة؟\n\nملاحظة: سيتم حذف جميع الدروس والتمارين المرتبطة بها!')">
                                                            🗑️ حذف
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- أزرار التنقل -->
        <div class="navigation-section">
            <div class="nav-card">
                <div class="nav-content">
                    <div class="nav-icon">🎯</div>
                    <div class="nav-text">
                        <h3>إدارة المراحل والمستويات</h3>
                        <p>إضافة وتعديل المراحل والمستويات التعليمية</p>
                    </div>
                    <a href="stages.php" class="btn-navigate">إدارة المراحل</a>
                </div>
            </div>

            <div class="nav-card">
                <div class="nav-content">
                    <div class="nav-icon">👨‍🏫</div>
                    <div class="nav-text">
                        <h3>إدارة الأساتذة</h3>
                        <p>إضافة الأساتذة وتخصيص المواد لهم</p>
                    </div>
                    <a href="index.php" class="btn-navigate">العودة للوحة التحكم</a>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="../../assets/css/subjects-enhanced.css">
</body>
</html>
