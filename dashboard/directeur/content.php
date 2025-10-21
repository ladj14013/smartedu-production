<?php


ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('directeur');

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// معالجة إضافة مرحلة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_stage') {
    $stage_name = sanitize_input($_POST['stage_name']);
    $order = (int)$_POST['stage_order'];
    
    if (!empty($stage_name)) {
        try {
            $query = "INSERT INTO stages (name, `order`) VALUES (:name, :order)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $stage_name);
            $stmt->bindParam(':order', $order);
            $stmt->execute();
            $success = 'تمت إضافة المرحلة بنجاح';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة المرحلة: ' . $e->getMessage();
        }
    }
}

// معالجة إضافة مستوى جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_level') {
    $level_name = sanitize_input($_POST['level_name']);
    $stage_id = (int)$_POST['stage_id'];
    $order = (int)$_POST['level_order'];
    
    if (!empty($level_name) && $stage_id > 0) {
        try {
            $query = "INSERT INTO levels (name, stage_id, `order`) VALUES (:name, :stage_id, :order)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $level_name);
            $stmt->bindParam(':stage_id', $stage_id);
            $stmt->bindParam(':order', $order);
            $stmt->execute();
            $success = 'تمت إضافة المستوى بنجاح';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة المستوى: ' . $e->getMessage();
        }
    }
}

// معالجة إضافة مادة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subject') {
    $subject_name = sanitize_input($_POST['subject_name']);
    $subject_description = sanitize_input($_POST['subject_description']);
    $level_id = (int)$_POST['level_id'];
    
    if (!empty($subject_name) && $level_id > 0) {
        try {
            $query = "INSERT INTO subjects (name, description, level_id) VALUES (:name, :description, :level_id)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $subject_name);
            $stmt->bindParam(':description', $subject_description);
            $stmt->bindParam(':level_id', $level_id);
            $stmt->execute();
            $success = 'تمت إضافة المادة بنجاح';
        } catch (PDOException $e) {
            $error = 'خطأ في إضافة المادة: ' . $e->getMessage();
        }
    }
}

// معالجة الحذف
if (isset($_GET['delete'])) {
    $type = $_GET['delete'];
    $id = (int)$_GET['id'];
    
    try {
        if ($type === 'stage') {
            $db->prepare("DELETE FROM stages WHERE id = ?")->execute([$id]);
            $success = 'تم حذف المرحلة بنجاح';
        } elseif ($type === 'level') {
            $db->prepare("DELETE FROM levels WHERE id = ?")->execute([$id]);
            $success = 'تم حذف المستوى بنجاح';
        } elseif ($type === 'subject') {
            $db->prepare("DELETE FROM subjects WHERE id = ?")->execute([$id]);
            $success = 'تم حذف المادة بنجاح';
        }
    } catch (PDOException $e) {
        $error = 'خطأ في الحذف: ' . $e->getMessage();
    }
}

// جلب جميع المراحل مع المستويات والمواد
$query = "SELECT * FROM stages ORDER BY `order`";
$stages = $db->query($query)->fetchAll();

foreach ($stages as &$stage) {
    $query = "SELECT * FROM levels WHERE stage_id = :stage_id ORDER BY `order`";
    $stmt = $db->prepare($query);
    $stmt->execute([':stage_id' => $stage['id']]);
    $stage['levels'] = $stmt->fetchAll();
    
    foreach ($stage['levels'] as &$level) {
        $query = "SELECT * FROM subjects WHERE level_id = :level_id ORDER BY name";
        $stmt = $db->prepare($query);
        $stmt->execute([':level_id' => $level['id']]);
        $level['subjects'] = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة هيكل المحتوى - Smart Education Hub</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .content-tree {
            margin-top: 2rem;
        }
        
        .tree-item {
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .tree-header {
            padding: 1rem 1.5rem;
            background: #f9fafb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .tree-header:hover {
            background: #f3f4f6;
        }
        
        .tree-header.active {
            background: #e0e7ff;
        }
        
        .tree-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .tree-icon {
            font-size: 1.5rem;
        }
        
        .tree-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .tree-content {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        
        .tree-content.active {
            max-height: 2000px;
            padding: 1.5rem;
        }
        
        .nested-item {
            margin: 0.75rem 0;
            padding: 1rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        
        .nested-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            margin-bottom: 1.5rem;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #1f2937;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .form-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>إدارة هيكل المحتوى</h1>
                    <p>إدارة المراحل والمستويات والمواد الدراسية</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('addStageModal')">
                    ➕ أضف مرحلة جديدة
                </button>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- شجرة المحتوى -->
            <div class="content-tree">
                <?php foreach ($stages as $stage): ?>
                    <div class="tree-item">
                        <div class="tree-header" onclick="toggleTree(this)">
                            <div class="tree-title">
                                <span class="tree-icon">📚</span>
                                <span><?php echo htmlspecialchars($stage['name']); ?></span>
                                <span class="badge badge-primary"><?php echo count($stage['levels']); ?> مستويات</span>
                            </div>
                            <div class="tree-actions" onclick="event.stopPropagation()">
                                <button class="btn btn-sm btn-secondary" onclick="openAddLevelModal(<?php echo $stage['id']; ?>, '<?php echo htmlspecialchars($stage['name']); ?>')">
                                    ➕ مستوى
                                </button>
                                <button class="btn btn-sm btn-outline" onclick="editStage(<?php echo $stage['id']; ?>)">
                                    ✏️
                                </button>
                                <a href="?delete=stage&id=<?php echo $stage['id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('هل أنت متأكد من حذف هذه المرحلة وجميع محتوياتها؟')">
                                    🗑️
                                </a>
                            </div>
                        </div>
                        
                        <div class="tree-content">
                            <?php foreach ($stage['levels'] as $level): ?>
                                <div class="nested-item">
                                    <div class="nested-header">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <span>📖</span>
                                            <strong><?php echo htmlspecialchars($level['name']); ?></strong>
                                            <span class="badge badge-success"><?php echo count($level['subjects']); ?> مواد</span>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button class="btn btn-sm btn-secondary" onclick="openAddSubjectModal(<?php echo $level['id']; ?>, '<?php echo htmlspecialchars($level['name']); ?>')">
                                                ➕ مادة
                                            </button>
                                            <a href="?delete=level&id=<?php echo $level['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('هل أنت متأكد من حذف هذا المستوى؟')">
                                                🗑️
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($level['subjects'])): ?>
                                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                                            <div style="display: grid; gap: 0.5rem;">
                                                <?php foreach ($level['subjects'] as $subject): ?>
                                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: #f9fafb; border-radius: 4px;">
                                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                            <span>📄</span>
                                                            <span><?php echo htmlspecialchars($subject['name']); ?></span>
                                                        </div>
                                                        <div style="display: flex; gap: 0.5rem;">
                                                            <a href="subject-content.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-outline">
                                                                👁️ عرض
                                                            </a>
                                                            <a href="?delete=subject&id=<?php echo $subject['id']; ?>" 
                                                               class="btn btn-sm btn-danger" 
                                                               onclick="return confirm('هل أنت متأكد من حذف هذه المادة؟')">
                                                                🗑️
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($stage['levels'])): ?>
                                <p style="text-align: center; color: #6b7280; padding: 2rem;">لا توجد مستويات في هذه المرحلة</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($stages)): ?>
                    <div class="card">
                        <div class="card-body" style="text-align: center; padding: 3rem;">
                            <p style="font-size: 3rem;">📚</p>
                            <h3>لا توجد مراحل دراسية</h3>
                            <p style="color: #6b7280;">ابدأ بإضافة مرحلة دراسية جديدة</p>
                            <button class="btn btn-primary" onclick="openModal('addStageModal')" style="margin-top: 1rem;">
                                ➕ أضف مرحلة الآن
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Modal إضافة مرحلة -->
    <div id="addStageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ إضافة مرحلة دراسية جديدة</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_stage">
                
                <div class="form-group">
                    <label>اسم المرحلة *</label>
                    <input type="text" name="stage_name" required placeholder="مثال: المرحلة الابتدائية">
                </div>
                
                <div class="form-group">
                    <label>الترتيب *</label>
                    <input type="number" name="stage_order" required value="1" min="1">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">حفظ</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('addStageModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal إضافة مستوى -->
    <div id="addLevelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ إضافة مستوى دراسي</h2>
                <p id="stageName" style="color: #6b7280; margin: 0.5rem 0 0 0;"></p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_level">
                <input type="hidden" name="stage_id" id="levelStageId">
                
                <div class="form-group">
                    <label>اسم المستوى *</label>
                    <input type="text" name="level_name" required placeholder="مثال: السنة الأولى">
                </div>
                
                <div class="form-group">
                    <label>الترتيب *</label>
                    <input type="number" name="level_order" required value="1" min="1">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">حفظ</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('addLevelModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal إضافة مادة -->
    <div id="addSubjectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>➕ إضافة مادة دراسية</h2>
                <p id="levelName" style="color: #6b7280; margin: 0.5rem 0 0 0;"></p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_subject">
                <input type="hidden" name="level_id" id="subjectLevelId">
                
                <div class="form-group">
                    <label>اسم المادة *</label>
                    <input type="text" name="subject_name" required placeholder="مثال: الرياضيات">
                </div>
                
                <div class="form-group">
                    <label>الوصف</label>
                    <textarea name="subject_description" rows="3" placeholder="وصف المادة (اختياري)"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">حفظ</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('addSubjectModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleTree(element) {
            const content = element.nextElementSibling;
            const header = element;
            
            content.classList.toggle('active');
            header.classList.toggle('active');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function openAddLevelModal(stageId, stageName) {
            document.getElementById('levelStageId').value = stageId;
            document.getElementById('stageName').textContent = 'المرحلة: ' + stageName;
            openModal('addLevelModal');
        }
        
        function openAddSubjectModal(levelId, levelName) {
            document.getElementById('subjectLevelId').value = levelId;
            document.getElementById('levelName').textContent = 'المستوى: ' + levelName;
            openModal('addSubjectModal');
        }
        
        // إغلاق Modal عند النقر خارجه
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
