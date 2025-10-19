<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('directeur');

$database = new Database();
$db = $database->getConnection();

// إحصائيات المستخدمين
$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_students = $db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_teachers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$total_parents = $db->query("SELECT COUNT(*) FROM users WHERE role = 'parent'")->fetchColumn();

// جلب جميع المستخدمين
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$query = "SELECT u.*, s.name as stage_name, l.name as level_name, subj.name as subject_name
          FROM users u 
          LEFT JOIN stages s ON u.stage_id = s.id
          LEFT JOIN levels l ON u.level_id = l.id
          LEFT JOIN subjects subj ON u.subject_id = subj.id
          WHERE 1=1";

if ($search) {
    $query .= " AND (u.name LIKE :search OR u.email LIKE :search)";
}

if ($role_filter) {
    $query .= " AND u.role = :role";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);

if ($search) {
    $search_param = '%' . $search . '%';
    $stmt->bindParam(':search', $search_param);
}

if ($role_filter) {
    $stmt->bindParam(':role', $role_filter);
}

$stmt->execute();
$users = $stmt->fetchAll();

// حذف مستخدم
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // عدم السماح بحذف المدير نفسه
    if ($user_id != $_SESSION['user_id']) {
        $query = "DELETE FROM users WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        set_flash_message('success', 'تم حذف المستخدم بنجاح.');
        header("Location: users.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - Smart Education Hub</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .users-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        
        .stat-box h3 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            color: #4285F4;
        }
        
        .stat-box p {
            margin: 0;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .table-container {
            margin-top: 1.5rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f9fafb;
        }
        
        th {
            padding: 1rem;
            text-align: right;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        tbody tr:hover {
            background: #f9fafb;
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .role-student { background: #dbeafe; color: #1e40af; }
        .role-teacher { background: #d1fae5; color: #065f46; }
        .role-directeur { background: #fef3c7; color: #92400e; }
        .role-supervisor { background: #e0e7ff; color: #3730a3; }
        .role-parent { background: #fce7f3; color: #831843; }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h1>👥 إدارة المستخدمين</h1>
                        <p>عرض وإدارة جميع مستخدمي المنصة</p>
                    </div>
                    <a href="create-user.php" class="btn btn-primary">
                        ➕ مستخدم جديد
                    </a>
                </div>
            </div>
            
            <?php
            $flash = get_flash_message();
            if ($flash):
            ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- فلاتر البحث -->
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                            <input 
                                type="text" 
                                name="search" 
                                placeholder="بحث بالاسم أو البريد الإلكتروني..."
                                value="<?php echo htmlspecialchars($search); ?>"
                            >
                        </div>
                        
                        <div class="form-group" style="flex: 1; min-width: 150px; margin: 0;">
                            <select name="role">
                                <option value="">جميع الأدوار</option>
                                <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>طالب</option>
                                <option value="teacher" <?php echo $role_filter == 'teacher' ? 'selected' : ''; ?>>معلم</option>
                                <option value="enseignant" <?php echo $role_filter == 'enseignant' ? 'selected' : ''; ?>>أستاذ</option>
                                <option value="directeur" <?php echo $role_filter == 'directeur' ? 'selected' : ''; ?>>مدير</option>
                                <option value="supervisor_general" <?php echo $role_filter == 'supervisor_general' ? 'selected' : ''; ?>>مشرف عام</option>
                                <option value="superviseur_general" <?php echo $role_filter == 'superviseur_general' ? 'selected' : ''; ?>>مشرف عام (فرنسي)</option>
                                <option value="supervisor_subject" <?php echo $role_filter == 'supervisor_subject' ? 'selected' : ''; ?>>مشرف مادة</option>
                                <option value="superviseur_matiere" <?php echo $role_filter == 'superviseur_matiere' ? 'selected' : ''; ?>>مشرف مادة (فرنسي)</option>
                                <option value="parent" <?php echo $role_filter == 'parent' ? 'selected' : ''; ?>>ولي أمر</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">بحث</button>
                        <a href="users.php" class="btn btn-secondary">إعادة تعيين</a>
                    </form>
                </div>
            </div>
            
            <!-- جدول المستخدمين -->
            <div class="table-container" style="margin-top: 1.5rem; background: white; border-radius: 0.75rem; box-shadow: var(--shadow-sm); overflow: hidden;">
                <table>
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>البريد الإلكتروني</th>
                            <th>الدور</th>
                            <th>المادة</th>
                            <th>المرحلة/المستوى</th>
                            <th>تاريخ التسجيل</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                    لا توجد نتائج
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($user['role']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($user['subject_name']): ?>
                                            <span style="background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: 600;">
                                                📚 <?php echo htmlspecialchars($user['subject_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['stage_name']): ?>
                                            <?php echo htmlspecialchars($user['stage_name']) . ($user['level_name'] ? ' - ' . htmlspecialchars($user['level_name']) : ''); ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-secondary">
                                                ✏️
                                            </a>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="?delete=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-secondary" 
                                                   onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')"
                                                   style="color: var(--error);">
                                                    🗑️
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
