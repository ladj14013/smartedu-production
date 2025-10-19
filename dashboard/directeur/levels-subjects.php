<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/platform.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['directeur']);

$success = '';
$error = '';

// معالجة إضافة مرحلة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stage'])) {
    $stage_name = trim($_POST['stage_name']);
    $stage_order = intval($_POST['stage_order']);
    
    if (!empty($stage_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO stages (name, display_order) VALUES (?, ?)");
            $stmt->execute([$stage_name, $stage_order]);
            $success = "✅ تم إضافة المرحلة بنجاح";
        } catch (PDOException $e) {
            $error = "❌ خطأ في إضافة المرحلة: " . $e->getMessage();
        }
    } else {
        $error = "❌ يرجى إدخال اسم المرحلة";
    }
}

// معالجة إضافة مستوى جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_level'])) {
    $level_name = trim($_POST['level_name']);
    $stage_id = intval($_POST['stage_id']);
    $level_order = intval($_POST['level_order']);
    
    if (!empty($level_name) && $stage_id > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO levels (name, stage_id, display_order) VALUES (?, ?, ?)");
            $stmt->execute([$level_name, $stage_id, $level_order]);
            $success = "✅ تم إضافة المستوى بنجاح";
        } catch (PDOException $e) {
            $error = "❌ خطأ في إضافة المستوى: " . $e->getMessage();
        }
    } else {
        $error = "❌ يرجى إدخال جميع البيانات المطلوبة";
    }
}

// معالجة إضافة مادة دراسية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    $subject_code = trim($_POST['subject_code']);
    $level_id = intval($_POST['level_id']);
    $description = trim($_POST['description']);
    
    if (!empty($subject_name) && !empty($subject_code) && $level_id > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO subjects (name, code, level_id, description, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$subject_name, $subject_code, $level_id, $description]);
            $success = "✅ تم إضافة المادة الدراسية بنجاح";
        } catch (PDOException $e) {
            $error = "❌ خطأ في إضافة المادة: " . $e->getMessage();
        }
    } else {
        $error = "❌ يرجى إدخال جميع البيانات المطلوبة";
    }
}

// معالجة حذف مادة
if (isset($_GET['delete_subject'])) {
    $subject_id = intval($_GET['delete_subject']);
    try {
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $success = "✅ تم حذف المادة بنجاح";
    } catch (PDOException $e) {
        $error = "❌ خطأ في حذف المادة: " . $e->getMessage();
    }
}

// جلب جميع المراحل
$stages = $pdo->query("SELECT * FROM stages ORDER BY display_order, name")->fetchAll(PDO::FETCH_ASSOC);

// جلب جميع المستويات مع المراحل
$levels = $pdo->query("
    SELECT l.*, s.name as stage_name 
    FROM levels l 
    LEFT JOIN stages s ON l.stage_id = s.id 
    ORDER BY s.display_order, l.display_order, l.name
")->fetchAll(PDO::FETCH_ASSOC);

// جلب جميع المواد مع المستويات والمراحل
$subjects = $pdo->query("
    SELECT sub.*, l.name as level_name, s.name as stage_name 
    FROM subjects sub 
    LEFT JOIN levels l ON sub.level_id = l.id 
    LEFT JOIN stages s ON l.stage_id = s.id 
    ORDER BY s.display_order, l.display_order, sub.name
")->fetchAll(PDO::FETCH_ASSOC);

echo get_standard_html_head('إدارة المستويات والمواد - SmartEdu', ['../../assets/css/dashboard.css']);
echo get_role_css($_SESSION['user_role']);
?>

<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>📚 إدارة المستويات والمواد الدراسية</h1>
            <p>تنظيم المراحل والمستويات التعليمية والمواد الدراسية</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- علامات التبويب -->
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('stages')">🎓 المراحل</button>
            <button class="tab-btn" onclick="showTab('levels')">📊 المستويات</button>
            <button class="tab-btn" onclick="showTab('subjects')">📚 المواد الدراسية</button>
            <button class="tab-btn" onclick="showTab('overview')">📋 نظرة شاملة</button>
        </div>

        <!-- تبويب المراحل -->
        <div id="stages-tab" class="tab-content active">
            <div class="grid-2">
                <!-- نموذج إضافة مرحلة -->
                <div class="card">
                    <div class="card-header">
                        <h2>➕ إضافة مرحلة جديدة</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>اسم المرحلة:</label>
                                <input type="text" name="stage_name" class="form-control" placeholder="مثال: المرحلة الابتدائية" required>
                            </div>
                            
                            <div class="form-group">
                                <label>ترتيب العرض:</label>
                                <input type="number" name="stage_order" class="form-control" value="1" min="1" required>
                                <small class="form-text">يحدد ترتيب ظهور المرحلة</small>
                            </div>
                            
                            <button type="submit" name="add_stage" class="btn btn-primary">
                                ➕ إضافة المرحلة
                            </button>
                        </form>
                    </div>
                </div>

                <!-- قائمة المراحل -->
                <div class="card">
                    <div class="card-header">
                        <h2>📋 المراحل المضافة (<?php echo count($stages); ?>)</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($stages)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">📚</div>
                                <p>لا توجد مراحل مضافة بعد</p>
                                <small>ابدأ بإضافة المراحل التعليمية</small>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($stages as $stage): ?>
                                    <div class="list-item">
                                        <div class="item-icon">🎓</div>
                                        <div class="item-content">
                                            <strong><?php echo htmlspecialchars($stage['name']); ?></strong>
                                            <small>ترتيب: <?php echo $stage['display_order']; ?></small>
                                        </div>
                                        <div class="item-badge">
                                            <?php
                                            $level_count = $pdo->prepare("SELECT COUNT(*) FROM levels WHERE stage_id = ?");
                                            $level_count->execute([$stage['id']]);
                                            echo $level_count->fetchColumn() . ' مستوى';
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- تبويب المستويات -->
        <div id="levels-tab" class="tab-content">
            <div class="grid-2">
                <!-- نموذج إضافة مستوى -->
                <div class="card">
                    <div class="card-header">
                        <h2>➕ إضافة مستوى جديد</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>اسم المستوى:</label>
                                <input type="text" name="level_name" class="form-control" placeholder="مثال: الصف الأول الابتدائي" required>
                            </div>
                            
                            <div class="form-group">
                                <label>المرحلة:</label>
                                <select name="stage_id" class="form-control" required>
                                    <option value="">-- اختر المرحلة --</option>
                                    <?php foreach ($stages as $stage): ?>
                                        <option value="<?php echo $stage['id']; ?>">
                                            <?php echo htmlspecialchars($stage['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>ترتيب العرض:</label>
                                <input type="number" name="level_order" class="form-control" value="1" min="1" required>
                            </div>
                            
                            <button type="submit" name="add_level" class="btn btn-primary">
                                ➕ إضافة المستوى
                            </button>
                        </form>
                    </div>
                </div>

                <!-- قائمة المستويات -->
                <div class="card">
                    <div class="card-header">
                        <h2>📋 المستويات المضافة (<?php echo count($levels); ?>)</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($levels)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">📊</div>
                                <p>لا توجد مستويات مضافة بعد</p>
                                <small>أضف مرحلة أولاً ثم أضف المستويات</small>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($levels as $level): ?>
                                    <div class="list-item">
                                        <div class="item-icon">📊</div>
                                        <div class="item-content">
                                            <strong><?php echo htmlspecialchars($level['name']); ?></strong>
                                            <small><?php echo htmlspecialchars($level['stage_name']); ?></small>
                                        </div>
                                        <div class="item-badge">
                                            <?php
                                            $subject_count = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE level_id = ?");
                                            $subject_count->execute([$level['id']]);
                                            echo $subject_count->fetchColumn() . ' مادة';
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- تبويب المواد الدراسية -->
        <div id="subjects-tab" class="tab-content">
            <div class="grid-2">
                <!-- نموذج إضافة مادة -->
                <div class="card">
                    <div class="card-header">
                        <h2>➕ إضافة مادة دراسية</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>اسم المادة:</label>
                                <input type="text" name="subject_name" class="form-control" placeholder="مثال: اللغة العربية" required>
                            </div>
                            
                            <div class="form-group">
                                <label>رمز المادة:</label>
                                <input type="text" name="subject_code" class="form-control" placeholder="مثال: AR101" required>
                                <small class="form-text">رمز قصير لتعريف المادة</small>
                            </div>
                            
                            <div class="form-group">
                                <label>المستوى:</label>
                                <select name="level_id" class="form-control" required>
                                    <option value="">-- اختر المستوى --</option>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level['id']; ?>">
                                            <?php echo htmlspecialchars($level['name']) . ' - ' . htmlspecialchars($level['stage_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>الوصف:</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="وصف مختصر عن المادة"></textarea>
                            </div>
                            
                            <button type="submit" name="add_subject" class="btn btn-primary">
                                ➕ إضافة المادة
                            </button>
                        </form>
                    </div>
                </div>

                <!-- قائمة المواد -->
                <div class="card">
                    <div class="card-header">
                        <h2>📋 المواد الدراسية (<?php echo count($subjects); ?>)</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($subjects)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">📚</div>
                                <p>لا توجد مواد دراسية بعد</p>
                                <small>أضف مستوى أولاً ثم أضف المواد</small>
                            </div>
                        <?php else: ?>
                            <div class="subjects-grid">
                                <?php foreach ($subjects as $subject): ?>
                                    <div class="subject-card">
                                        <div class="subject-header">
                                            <h3>📘 <?php echo htmlspecialchars($subject['name']); ?></h3>
                                            <span class="subject-code"><?php echo htmlspecialchars($subject['code']); ?></span>
                                        </div>
                                        <div class="subject-body">
                                            <p class="subject-level">
                                                <strong>المستوى:</strong> <?php echo htmlspecialchars($subject['level_name']); ?>
                                            </p>
                                            <p class="subject-stage">
                                                <strong>المرحلة:</strong> <?php echo htmlspecialchars($subject['stage_name']); ?>
                                            </p>
                                            <?php if ($subject['description']): ?>
                                                <p class="subject-desc"><?php echo htmlspecialchars($subject['description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="subject-footer">
                                            <a href="?delete_subject=<?php echo $subject['id']; ?>" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('هل أنت متأكد من حذف هذه المادة؟')">
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
        </div>

        <!-- تبويب النظرة الشاملة -->
        <div id="overview-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>📊 نظرة شاملة على المنظومة التعليمية</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($stages)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📚</div>
                            <h3>ابدأ بإضافة المراحل التعليمية</h3>
                            <p>لا توجد بيانات لعرضها بعد</p>
                        </div>
                    <?php else: ?>
                        <div class="overview-tree">
                            <?php foreach ($stages as $stage): ?>
                                <div class="stage-section">
                                    <div class="stage-header">
                                        <h3>🎓 <?php echo htmlspecialchars($stage['name']); ?></h3>
                                    </div>
                                    
                                    <?php
                                    $stage_levels = array_filter($levels, function($l) use ($stage) {
                                        return $l['stage_id'] == $stage['id'];
                                    });
                                    ?>
                                    
                                    <?php if (empty($stage_levels)): ?>
                                        <div class="no-data">لا توجد مستويات في هذه المرحلة</div>
                                    <?php else: ?>
                                        <?php foreach ($stage_levels as $level): ?>
                                            <div class="level-section">
                                                <div class="level-header">
                                                    <h4>📊 <?php echo htmlspecialchars($level['name']); ?></h4>
                                                </div>
                                                
                                                <?php
                                                $level_subjects = array_filter($subjects, function($s) use ($level) {
                                                    return $s['level_id'] == $level['id'];
                                                });
                                                ?>
                                                
                                                <?php if (empty($level_subjects)): ?>
                                                    <div class="no-data">لا توجد مواد في هذا المستوى</div>
                                                <?php else: ?>
                                                    <div class="subjects-list">
                                                        <?php foreach ($level_subjects as $subject): ?>
                                                            <div class="subject-item">
                                                                📘 <strong><?php echo htmlspecialchars($subject['name']); ?></strong>
                                                                <span class="code">(<?php echo htmlspecialchars($subject['code']); ?>)</span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: #f0f0f0;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Amiri', serif;
            font-size: 16px;
            transition: all 0.3s;
        }

        .tab-btn:hover {
            background: #e0e0e0;
        }

        .tab-btn.active {
            background: var(--role-color, #667eea);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: var(--role-color, #667eea);
            color: white;
            padding: 20px;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.3rem;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Amiri', serif;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--role-color, #667eea);
        }

        .form-text {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
            display: block;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-family: 'Amiri', serif;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--role-color, #667eea);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }

        .list-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .list-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .list-item:hover {
            background: #e9ecef;
            transform: translateX(-5px);
        }

        .item-icon {
            font-size: 2rem;
        }

        .item-content {
            flex: 1;
        }

        .item-content strong {
            display: block;
            margin-bottom: 5px;
        }

        .item-content small {
            color: #666;
        }

        .item-badge {
            background: var(--role-color, #667eea);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }

        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }

        .subject-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }

        .subject-card:hover {
            border-color: var(--role-color, #667eea);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .subject-header {
            margin-bottom: 10px;
        }

        .subject-header h3 {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
        }

        .subject-code {
            background: var(--role-color, #667eea);
            color: white;
            padding: 3px 10px;
            border-radius: 5px;
            font-size: 12px;
        }

        .subject-body {
            margin: 10px 0;
            font-size: 14px;
        }

        .subject-body p {
            margin: 5px 0;
        }

        .subject-desc {
            color: #666;
            font-style: italic;
        }

        .subject-footer {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-family: 'Amiri', serif;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            color: var(--role-color, #667eea);
        }

        .page-header p {
            margin: 0;
            color: #666;
        }

        /* النظرة الشاملة */
        .overview-tree {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .stage-section {
            border: 2px solid var(--role-color, #667eea);
            border-radius: 15px;
            overflow: hidden;
        }

        .stage-header {
            background: var(--role-color, #667eea);
            color: white;
            padding: 15px 20px;
        }

        .stage-header h3 {
            margin: 0;
        }

        .level-section {
            margin: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .level-header {
            background: #f0f0f0;
            padding: 12px 15px;
        }

        .level-header h4 {
            margin: 0;
            color: #333;
        }

        .subjects-list {
            padding: 15px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .subject-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border-right: 3px solid var(--role-color, #667eea);
        }

        .subject-item .code {
            color: #666;
            font-size: 12px;
        }

        .no-data {
            padding: 20px;
            text-align: center;
            color: #999;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }

            .subjects-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        function showTab(tabName) {
            // إخفاء جميع التبويبات
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // إلغاء تفعيل جميع الأزرار
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // تفعيل التبويب المحدد
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>