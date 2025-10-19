<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// جلب المراحل
try {
    $stages = $pdo->query("SELECT * FROM stages ORDER BY `order`, id")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stages = [];
    $error = "خطأ في جلب المراحل: " . $e->getMessage();
}

// جلب المستويات
try {
    $levels = $pdo->query("
        SELECT l.*, s.name as stage_name 
        FROM levels l 
        LEFT JOIN stages s ON l.stage_id = s.id 
        ORDER BY s.`order`, l.`order`, l.id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $levels = [];
    $error = "خطأ في جلب المستويات: " . $e->getMessage();
}

// إضافة مرحلة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stage'])) {
    $name = trim($_POST['stage_name']);
    $order = intval($_POST['stage_order']);
    
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO stages (name, `order`) VALUES (?, ?)");
            $stmt->execute([$name, $order]);
            $success = '✅ تم إضافة المرحلة بنجاح';
            header("Location: stages.php?success=1");
            exit();
        } catch (PDOException $e) {
            $error = '❌ خطأ في إضافة المرحلة: ' . $e->getMessage();
        }
    } else {
        $error = '❌ يرجى إدخال اسم المرحلة';
    }
}

// إضافة مستوى
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_level'])) {
    $name = trim($_POST['level_name']);
    $stage_id = intval($_POST['stage_id']);
    $order = intval($_POST['level_order']);
    
    if (!empty($name) && $stage_id > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO levels (name, stage_id, `order`) VALUES (?, ?, ?)");
            $stmt->execute([$name, $stage_id, $order]);
            $success = '✅ تم إضافة المستوى بنجاح';
            header("Location: stages.php?success=2");
            exit();
        } catch (PDOException $e) {
            $error = '❌ خطأ في إضافة المستوى: ' . $e->getMessage();
        }
    } else {
        $error = '❌ يرجى إدخال جميع البيانات المطلوبة';
    }
}

// حذف مرحلة
if (isset($_GET['delete_stage'])) {
    $id = intval($_GET['delete_stage']);
    try {
        $stmt = $pdo->prepare("DELETE FROM stages WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: stages.php?deleted=1");
        exit();
    } catch (PDOException $e) {
        $error = '❌ خطأ في حذف المرحلة: ' . $e->getMessage();
    }
}

// حذف مستوى
if (isset($_GET['delete_level'])) {
    $id = intval($_GET['delete_level']);
    try {
        $stmt = $pdo->prepare("DELETE FROM levels WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: stages.php?deleted=2");
        exit();
    } catch (PDOException $e) {
        $error = '❌ خطأ في حذف المستوى: ' . $e->getMessage();
    }
}

// رسائل النجاح
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) $success = '✅ تم إضافة المرحلة بنجاح';
    if ($_GET['success'] == 2) $success = '✅ تم إضافة المستوى بنجاح';
}
if (isset($_GET['deleted'])) {
    if ($_GET['deleted'] == 1) $success = '✅ تم حذف المرحلة بنجاح';
    if ($_GET['deleted'] == 2) $success = '✅ تم حذف المستوى بنجاح';
}

echo get_standard_html_head('إدارة المراحل والمستويات - SmartEdu', ['../../assets/css/dashboard.css']);
echo get_role_css($_SESSION['user_role']);
?>

<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🎯 إدارة المراحل والمستويات الدراسية</h1>
            <p>تنظيم وإدارة المراحل التعليمية والمستويات الدراسية</p>
        </div>

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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🎓</div>
                <div class="stat-details">
                    <h3><?php echo count($stages); ?></h3>
                    <p>المراحل الدراسية</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-details">
                    <h3><?php echo count($levels); ?></h3>
                    <p>المستويات الدراسية</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-details">
                    <?php
                    $subjects_count = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
                    ?>
                    <h3><?php echo $subjects_count; ?></h3>
                    <p>المواد الدراسية</p>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <!-- قسم المراحل -->
            <div class="section-card">
                <div class="section-header">
                    <h2>🎓 المراحل الدراسية</h2>
                    <p>إضافة وإدارة المراحل التعليمية</p>
                </div>
                
                <div class="form-container">
                    <h3 class="form-title">➕ إضافة مرحلة جديدة</h3>
                    <form method="POST" class="modern-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="stage_name">
                                    <span class="label-icon">📝</span>
                                    اسم المرحلة
                                </label>
                                <input type="text" 
                                       id="stage_name" 
                                       name="stage_name" 
                                       class="form-input"
                                       placeholder="مثال: المرحلة الابتدائية"
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="stage_order">
                                    <span class="label-icon">🔢</span>
                                    ترتيب العرض
                                </label>
                                <input type="number" 
                                       id="stage_order" 
                                       name="stage_order" 
                                       class="form-input"
                                       value="<?php echo count($stages) + 1; ?>"
                                       min="1"
                                       required>
                            </div>
                        </div>
                        <button type="submit" name="add_stage" class="btn-add">
                            <span>➕</span> إضافة المرحلة
                        </button>
                    </form>
                </div>
                
                <div class="items-list">
                    <h3 class="list-title">📋 المراحل المضافة (<?php echo count($stages); ?>)</h3>
                    <?php if (empty($stages)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">🎓</div>
                            <h4>لا توجد مراحل بعد</h4>
                            <p>ابدأ بإضافة المراحل التعليمية الأساسية</p>
                        </div>
                    <?php else: ?>
                        <div class="items-container">
                            <?php foreach ($stages as $index => $stage): ?>
                                <div class="item-card stage-item">
                                    <div class="item-number"><?php echo $index + 1; ?></div>
                                    <div class="item-icon">🎓</div>
                                    <div class="item-content">
                                        <h4><?php echo htmlspecialchars($stage['name']); ?></h4>
                                        <div class="item-meta">
                                            <span class="meta-tag">
                                                <span>🔢</span> ترتيب: <?php echo $stage['order']; ?>
                                            </span>
                                            <?php
                                            $level_count = $pdo->prepare("SELECT COUNT(*) FROM levels WHERE stage_id = ?");
                                            $level_count->execute([$stage['id']]);
                                            $count = $level_count->fetchColumn();
                                            ?>
                                            <span class="meta-tag">
                                                <span>📊</span> <?php echo $count; ?> مستوى
                                            </span>
                                        </div>
                                    </div>
                                    <div class="item-actions">
                                        <a href="?delete_stage=<?php echo $stage['id']; ?>" 
                                           class="btn-delete"
                                           onclick="return confirm('⚠️ هل أنت متأكد من حذف هذه المرحلة؟\n\nسيتم حذف جميع المستويات المرتبطة بها!')">
                                            🗑️ حذف
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- قسم المستويات -->
            <div class="section-card">
                <div class="section-header">
                    <h2>📊 المستويات الدراسية</h2>
                    <p>إضافة وإدارة المستويات داخل كل مرحلة</p>
                </div>
                
                <div class="form-container">
                    <h3 class="form-title">➕ إضافة مستوى جديد</h3>
                    <form method="POST" class="modern-form">
                        <div class="form-group">
                            <label for="level_name">
                                <span class="label-icon">📝</span>
                                اسم المستوى
                            </label>
                            <input type="text" 
                                   id="level_name" 
                                   name="level_name" 
                                   class="form-input"
                                   placeholder="مثال: الصف الأول الابتدائي"
                                   required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="stage_id">
                                    <span class="label-icon">🎓</span>
                                    المرحلة
                                </label>
                                <select id="stage_id" name="stage_id" class="form-input" required>
                                    <option value="">-- اختر المرحلة --</option>
                                    <?php foreach ($stages as $stage): ?>
                                        <option value="<?php echo $stage['id']; ?>">
                                            <?php echo htmlspecialchars($stage['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="level_order">
                                    <span class="label-icon">🔢</span>
                                    ترتيب العرض
                                </label>
                                <input type="number" 
                                       id="level_order" 
                                       name="level_order" 
                                       class="form-input"
                                       value="1"
                                       min="1"
                                       required>
                            </div>
                        </div>
                        <button type="submit" name="add_level" class="btn-add">
                            <span>➕</span> إضافة المستوى
                        </button>
                    </form>
                </div>
                
                <div class="items-list">
                    <h3 class="list-title">📋 المستويات المضافة (<?php echo count($levels); ?>)</h3>
                    <?php if (empty($levels)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <h4>لا توجد مستويات بعد</h4>
                            <p>أضف مرحلة أولاً ثم ابدأ بإضافة المستويات</p>
                        </div>
                    <?php else: ?>
                        <div class="items-container">
                            <?php foreach ($levels as $index => $level): ?>
                                <div class="item-card level-item">
                                    <div class="item-number"><?php echo $index + 1; ?></div>
                                    <div class="item-icon">📊</div>
                                    <div class="item-content">
                                        <h4><?php echo htmlspecialchars($level['name']); ?></h4>
                                        <div class="item-meta">
                                            <span class="meta-tag stage-tag">
                                                <span>🎓</span> <?php echo htmlspecialchars($level['stage_name'] ?? 'غير محدد'); ?>
                                            </span>
                                            <span class="meta-tag">
                                                <span>🔢</span> ترتيب: <?php echo $level['order']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="item-actions">
                                        <a href="?delete_level=<?php echo $level['id']; ?>" 
                                           class="btn-delete"
                                           onclick="return confirm('⚠️ هل أنت متأكد من حذف هذا المستوى؟')">
                                            🗑️ حذف
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="navigation-card">
            <div class="nav-content">
                <div class="nav-icon">📚</div>
                <div class="nav-text">
                    <h3>الخطوة التالية</h3>
                    <p>بعد إضافة المراحل والمستويات، يمكنك الآن إضافة المواد الدراسية لكل مستوى</p>
                </div>
                <a href="subjects.php" class="btn-navigate">
                    إدارة المواد الدراسية ←
                </a>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        <?php echo file_get_contents(dirname(__FILE__) . '/../../assets/css/stages-enhanced.css'); ?>
    </style>
</body>
</html>
