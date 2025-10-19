<?php
/**
 * Supervisor General Messages Page
 * صفحة الرسائل للمشرف العام
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['supervisor_general']);

$user_id = $_SESSION['user_id'];

// جلب معلومات المستخدم
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// معالجة إرسال رسالة جديدة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, subject, message, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    if ($stmt->execute([$user_id, $receiver_id, $subject, $message])) {
        $success_message = "تم إرسال الرسالة بنجاح";
    } else {
        $error_message = "حدث خطأ أثناء إرسال الرسالة";
    }
}

// معالجة تعليم الرسالة كمقروءة
if (isset($_GET['mark_read'])) {
    $message_id = $_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$message_id, $user_id]);
    header("Location: messages.php");
    exit;
}

// معالجة حذف الرسالة
if (isset($_GET['delete'])) {
    $message_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$message_id, $user_id]);
    header("Location: messages.php");
    exit;
}

// جلب الرسائل الواردة
$stmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name, u.role as sender_role
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll();

// جلب الرسائل المرسلة
$stmt = $pdo->prepare("
    SELECT m.*, u.name as receiver_name, u.role as receiver_role
    FROM messages m
    JOIN users u ON m.receiver_id = u.id
    WHERE m.sender_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$user_id]);
$sent_messages = $stmt->fetchAll();

// إحصائيات الرسائل
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ?");
$stmt->execute([$user_id]);
$total_messages = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ?");
$stmt->execute([$user_id]);
$sent_count = $stmt->fetchColumn();

// جلب قائمة المستخدمين للإرسال إليهم
$stmt = $pdo->query("
    SELECT id, name, role, email 
    FROM users 
    WHERE role IN ('directeur', 'supervisor_general', 'supervisor_subject', 'teacher')
    AND id != $user_id
    ORDER BY name
");
$users_list = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الرسائل - المشرف العام</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .messages-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
            margin-top: 20px;
        }
        
        .messages-sidebar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            height: fit-content;
        }
        
        .message-stats {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .btn-compose {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #4285F4, #22c55e);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-compose:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 133, 244, 0.3);
        }
        
        .messages-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: none;
            border: none;
            color: #6b7280;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            color: #4285F4;
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #4285F4, #22c55e);
        }
        
        .messages-list {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .message-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .message-item:hover {
            background: #f8f9fa;
        }
        
        .message-item.unread {
            background: #f0f7ff;
            border-right: 4px solid #4285F4;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .message-sender {
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .role-tag {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            background: #e5e7eb;
            color: #6b7280;
            font-weight: 500;
        }
        
        .message-date {
            font-size: 0.85rem;
            color: #9ca3af;
        }
        
        .message-subject {
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        
        .message-preview {
            font-size: 0.9rem;
            color: #6b7280;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-read {
            background: #4285F4;
            color: white;
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: #4285F4;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4285F4;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4285F4, #22c55e);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 133, 244, 0.3);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        
        @media (max-width: 968px) {
            .messages-container {
                grid-template-columns: 1fr;
            }
            
            .messages-sidebar {
                order: 2;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>الرسائل 📨</h1>
                        <p>إدارة الرسائل الواردة والمرسلة</p>
                    </div>
                </div>
            </header>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="messages-container">
                <div class="messages-sidebar">
                    <button class="btn-compose" onclick="openComposeModal()">
                        <span>✉️</span>
                        رسالة جديدة
                    </button>
                    
                    <div class="message-stats">
                        <div class="stat-item">
                            <span class="stat-label">الإجمالي</span>
                            <span class="stat-value"><?php echo $total_messages; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">غير مقروءة</span>
                            <span class="stat-value" style="color: #4285F4;"><?php echo $unread_messages; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">مُرسلة</span>
                            <span class="stat-value" style="color: #22c55e;"><?php echo $sent_count; ?></span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="messages-tabs">
                        <button class="tab-btn active" onclick="switchTab('inbox')">
                            الوارد (<?php echo count($messages); ?>)
                        </button>
                        <button class="tab-btn" onclick="switchTab('sent')">
                            المُرسل (<?php echo count($sent_messages); ?>)
                        </button>
                    </div>
                    
                    <!-- Inbox -->
                    <div id="inbox-tab" class="messages-list">
                        <?php if (empty($messages)): ?>
                            <p style="text-align: center; color: #9ca3af; padding: 40px;">
                                لا توجد رسائل واردة
                            </p>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="message-item <?php echo $msg['is_read'] ? '' : 'unread'; ?>">
                                    <div class="message-header">
                                        <div class="message-sender">
                                            <span><?php echo htmlspecialchars($msg['sender_name']); ?></span>
                                            <span class="role-tag"><?php echo getRoleLabel($msg['sender_role']); ?></span>
                                        </div>
                                        <div class="message-date">
                                            <?php echo date('Y-m-d H:i', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="message-subject">
                                        <?php echo htmlspecialchars($msg['subject']); ?>
                                    </div>
                                    <div class="message-preview">
                                        <?php echo htmlspecialchars(substr($msg['message'], 0, 100)) . '...'; ?>
                                    </div>
                                    <div class="message-actions">
                                        <?php if (!$msg['is_read']): ?>
                                            <a href="?mark_read=<?php echo $msg['id']; ?>" class="btn-action btn-read">
                                                تعليم كمقروءة
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn-action btn-read" onclick="viewMessage(<?php echo htmlspecialchars(json_encode($msg)); ?>)">
                                            عرض
                                        </button>
                                        <a href="?delete=<?php echo $msg['id']; ?>" 
                                           class="btn-action btn-delete"
                                           onclick="return confirm('هل أنت متأكد من حذف هذه الرسالة؟')">
                                            حذف
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sent Messages -->
                    <div id="sent-tab" class="messages-list" style="display: none;">
                        <?php if (empty($sent_messages)): ?>
                            <p style="text-align: center; color: #9ca3af; padding: 40px;">
                                لا توجد رسائل مُرسلة
                            </p>
                        <?php else: ?>
                            <?php foreach ($sent_messages as $msg): ?>
                                <div class="message-item">
                                    <div class="message-header">
                                        <div class="message-sender">
                                            <span>إلى: <?php echo htmlspecialchars($msg['receiver_name']); ?></span>
                                            <span class="role-tag"><?php echo getRoleLabel($msg['receiver_role']); ?></span>
                                        </div>
                                        <div class="message-date">
                                            <?php echo date('Y-m-d H:i', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="message-subject">
                                        <?php echo htmlspecialchars($msg['subject']); ?>
                                    </div>
                                    <div class="message-preview">
                                        <?php echo htmlspecialchars(substr($msg['message'], 0, 100)) . '...'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Compose Modal -->
    <div id="composeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">رسالة جديدة</h2>
                <span class="close-modal" onclick="closeComposeModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">المُرسل إليه</label>
                    <select name="receiver_id" class="form-control" required>
                        <option value="">اختر المستلم</option>
                        <?php foreach ($users_list as $u): ?>
                            <option value="<?php echo $u['id']; ?>">
                                <?php echo htmlspecialchars($u['name']); ?> - <?php echo getRoleLabel($u['role']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">الموضوع</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الرسالة</label>
                    <textarea name="message" class="form-control" required></textarea>
                </div>
                <button type="submit" name="send_message" class="btn-submit">إرسال الرسالة</button>
            </form>
        </div>
    </div>
    
    <!-- View Message Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="viewSubject"></h2>
                <span class="close-modal" onclick="closeViewModal()">&times;</span>
            </div>
            <div id="viewContent" style="line-height: 1.8; color: #374151;"></div>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            const tabs = document.querySelectorAll('.tab-btn');
            const inboxTab = document.getElementById('inbox-tab');
            const sentTab = document.getElementById('sent-tab');
            
            tabs.forEach(t => t.classList.remove('active'));
            
            if (tab === 'inbox') {
                tabs[0].classList.add('active');
                inboxTab.style.display = 'block';
                sentTab.style.display = 'none';
            } else {
                tabs[1].classList.add('active');
                inboxTab.style.display = 'none';
                sentTab.style.display = 'block';
            }
        }
        
        function openComposeModal() {
            document.getElementById('composeModal').classList.add('active');
        }
        
        function closeComposeModal() {
            document.getElementById('composeModal').classList.remove('active');
        }
        
        function viewMessage(msg) {
            document.getElementById('viewSubject').textContent = msg.subject;
            document.getElementById('viewContent').innerHTML = `
                <p><strong>من:</strong> ${msg.sender_name}</p>
                <p><strong>التاريخ:</strong> ${msg.created_at}</p>
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">
                <p style="white-space: pre-wrap;">${msg.message}</p>
            `;
            document.getElementById('viewModal').classList.add('active');
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>

<?php
function getRoleLabel($role) {
    $roles = [
        'directeur' => 'مدير',
        'supervisor_general' => 'مشرف عام',
        'supervisor_subject' => 'مشرف مادة',
        'teacher' => 'معلم',
        'student' => 'طالب',
        'parent' => 'ولي أمر'
    ];
    return $roles[$role] ?? $role;
}
?>
