<?php
/**
 * Parent Dashboard - Messages
 * لوحة تحكم ولي الأمر - الرسائل
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// تمكين عرض الأخطاء
ini_set('display_errors', 1);
error_reporting(E_ALL);

requireLogin();
requireRole(['parent']);

$user_id = $_SESSION['user_id'];

// جلب معلومات ولي الأمر
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$parent = $stmt->fetch();

if (!$parent) {
    $parent = ['name' => $_SESSION['user_name'] ?? 'ولي الأمر'];
}

// معالجة الإجراءات
$message_sent = false;
$error_message = '';

// إرسال رسالة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    if ($receiver_id && $subject && $message) {
        $stmt = $pdo->prepare("
            INSERT INTO messages (subject, sender_name, sender_email, author_id, content, recipient_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $sender_name = $parent['nom'] . ' ' . $parent['prenom'];
        $sender_email = $parent['email'];
        if ($stmt->execute([$subject, $sender_name, $sender_email, $user_id, $message, $receiver_id])) {
            $message_sent = true;
        } else {
            $error_message = 'فشل إرسال الرسالة';
        }
    } else {
        $error_message = 'يرجى ملء جميع الحقول';
    }
}

// حذف رسالة
if (isset($_GET['delete'])) {
    $message_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND (author_id = ? OR recipient_id = ?)");
    $stmt->execute([$message_id, $user_id, $user_id]);
    header("Location: messages.php");
    exit();
}

// تحديد الرسالة كمقروءة
if (isset($_GET['read'])) {
    $message_id = (int)$_GET['read'];
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND recipient_id = ?");
    $stmt->execute([$message_id, $user_id]);
}

// جلب قائمة المعلمين والمشرفين المرتبطين بأبناء ولي الأمر
try {
    // جلب أبناء ولي الأمر من جدول parent_children
    $stmt = $pdo->prepare("
        SELECT u.id, u.connected_teacher_code 
        FROM parent_children pc
        JOIN users u ON pc.child_id = u.id
        WHERE pc.parent_id = ? AND u.role = 'etudiant'
    ");
    $stmt->execute([$user_id]);
    $children = $stmt->fetchAll();
    
    $contacts = [];
    $teacher_ids = []; // لتجنب التكرار
    
    // جلب المعلمين والمشرفين المرتبطين بكل ابن
    foreach ($children as $child) {
        if ($child['connected_teacher_code']) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.role, 
                       sub.name as subject_name,
                       st.name as stage_name
                FROM users u
                LEFT JOIN subjects sub ON u.subject_id = sub.id
                LEFT JOIN stages st ON u.stage_id = st.id
                WHERE u.teacher_code = ? 
                AND u.role IN ('enseignant', 'supervisor_subject')
            ");
            $stmt->execute([$child['connected_teacher_code']]);
            $teacher = $stmt->fetch();
            
            if ($teacher && !in_array($teacher['id'], $teacher_ids)) {
                $teacher_ids[] = $teacher['id'];
                $contacts[] = $teacher;
            }
        }
    }
    
    // إضافة المدير دائماً
    $stmt = $pdo->query("
        SELECT id, role, NULL as subject_name, NULL as stage_name 
        FROM users WHERE role = 'directeur' LIMIT 1
    ");
    $director = $stmt->fetch();
    if ($director) {
        $contacts[] = $director;
    }
    
} catch (PDOException $e) {
    $contacts = [];
}

// جلب الرسائل الواردة
try {
    $stmt = $pdo->prepare("
        SELECT m.*, CONCAT(u.nom, ' ', u.prenom) as sender_name, u.role as sender_role
        FROM messages m
        LEFT JOIN users u ON m.author_id = u.id
        WHERE m.recipient_id = ?
        ORDER BY m.id DESC
    ");
    $stmt->execute([$user_id]);
    $inbox = $stmt->fetchAll();
} catch (PDOException $e) {
    $inbox = [];
}

// جلب الرسائل المرسلة
try {
    $stmt = $pdo->prepare("
        SELECT m.*, CONCAT(u.nom, ' ', u.prenom) as receiver_name, u.role as receiver_role
        FROM messages m
        LEFT JOIN users u ON m.recipient_id = u.id
        WHERE m.author_id = ?
        ORDER BY m.id DESC
    ");
    $stmt->execute([$user_id]);
    $sent = $stmt->fetchAll();
} catch (PDOException $e) {
    $sent = [];
}

// الإحصائيات
$total_inbox = count($inbox);
$unread_count = count(array_filter($inbox, fn($m) => !$m['is_read']));
$total_sent = count($sent);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الرسائل - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.3);
        }
        
        .page-header h1 { font-size: 2rem; margin-bottom: 10px; }
        .page-header p { opacity: 0.95; font-size: 1.1rem; }
        
        .messages-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-right: 4px solid;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-card.purple { border-right-color: #8b5cf6; }
        .stat-card.pink { border-right-color: #ec4899; }
        .stat-card.blue { border-right-color: #4285F4; }
        
        .stat-icon { font-size: 2.5rem; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: #1f2937; }
        .stat-label { color: #6b7280; font-size: 0.9rem; }
        
        .action-buttons {
            margin-bottom: 25px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: #8b5cf6;
            border-bottom-color: #8b5cf6;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .messages-list {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .message-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
            cursor: pointer;
        }
        
        .message-item:hover { background: #f8f9fa; }
        .message-item.unread { background: #f0f4ff; border-right: 4px solid #8b5cf6; }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .message-sender {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sender-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }
        
        .sender-info h4 {
            font-size: 1.05rem;
            color: #1f2937;
            margin-bottom: 3px;
        }
        
        .sender-role {
            font-size: 0.85rem;
            color: #6b7280;
            padding: 2px 8px;
            background: #f3f4f6;
            border-radius: 4px;
            display: inline-block;
        }
        
        .message-date {
            font-size: 0.85rem;
            color: #9ca3af;
        }
        
        .message-subject {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            font-size: 1.05rem;
        }
        
        .message-preview {
            color: #6b7280;
            font-size: 0.95rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 6px;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active { display: flex; }
        
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
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .modal-header h2 {
            font-size: 1.5rem;
            color: #1f2937;
        }
        
        .btn-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
            padding: 5px 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
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
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 150px;
        }
        
        .alert {
            padding: 15px;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }
        
        .empty-state-icon { font-size: 4rem; margin-bottom: 20px; }
        
        @media (max-width: 968px) {
            .messages-stats {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1>📨 الرسائل</h1>
                <p>التواصل مع المعلمين والإدارة</p>
            </div>
            
            <!-- Statistics -->
            <div class="messages-stats">
                <div class="stat-card purple">
                    <div class="stat-icon">📥</div>
                    <div>
                        <div class="stat-value"><?php echo $total_inbox; ?></div>
                        <div class="stat-label">رسائل واردة</div>
                    </div>
                </div>
                
                <div class="stat-card pink">
                    <div class="stat-icon">🔔</div>
                    <div>
                        <div class="stat-value"><?php echo $unread_count; ?></div>
                        <div class="stat-label">غير مقروءة</div>
                    </div>
                </div>
                
                <div class="stat-card blue">
                    <div class="stat-icon">📤</div>
                    <div>
                        <div class="stat-value"><?php echo $total_sent; ?></div>
                        <div class="stat-label">رسائل مرسلة</div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="openComposeModal()">
                    ✉️ إرسال رسالة جديدة
                </button>
            </div>
            
            <!-- Alerts -->
            <?php if ($message_sent): ?>
                <div class="alert alert-success">
                    ✅ تم إرسال الرسالة بنجاح
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="switchTab('inbox')">
                    📥 الوارد (<?php echo $total_inbox; ?>)
                </button>
                <button class="tab" onclick="switchTab('sent')">
                    📤 المرسل (<?php echo $total_sent; ?>)
                </button>
            </div>
            
            <!-- Inbox Tab -->
            <div id="inbox" class="tab-content active">
                <?php if (empty($inbox)): ?>
                    <div class="messages-list">
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <h3>لا توجد رسائل واردة</h3>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="messages-list">
                        <?php foreach ($inbox as $msg): ?>
                            <div class="message-item <?php echo !$msg['is_read'] ? 'unread' : ''; ?>">
                                <div class="message-header">
                                    <div class="message-sender">
                                        <div class="sender-avatar">
                                            <?php echo mb_substr($msg['sender_name'], 0, 1); ?>
                                        </div>
                                        <div class="sender-info">
                                            <h4><?php echo htmlspecialchars($msg['sender_name']); ?></h4>
                                            <span class="sender-role">
                                                <?php
                                                $role_ar = [
                                                    'enseignant' => 'معلم',
                                                    'directeur' => 'مدير',
                                                    'supervisor_general' => 'مشرف عام',
                                                    'supervisor_subject' => 'مشرف مادة'
                                                ];
                                                echo $role_ar[$msg['sender_role']] ?? $msg['sender_role'];
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="message-date">
                                        <?php 
                                        $date = new DateTime($msg['created_at']);
                                        echo $date->format('Y-m-d H:i');
                                        ?>
                                    </div>
                                </div>
                                <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                <div class="message-preview"><?php echo htmlspecialchars(mb_substr($msg['content'] ?? '', 0, 150)) . '...'; ?></div>
                                <div class="message-actions">
                                    <button class="btn btn-small btn-primary" onclick="viewMessage(<?php echo $msg['id']; ?>, 'inbox')">
                                        قراءة
                                    </button>
                                    <a href="?delete=<?php echo $msg['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذه الرسالة؟')">
                                        حذف
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sent Tab -->
            <div id="sent" class="tab-content">
                <?php if (empty($sent)): ?>
                    <div class="messages-list">
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <h3>لا توجد رسائل مرسلة</h3>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="messages-list">
                        <?php foreach ($sent as $msg): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <div class="message-sender">
                                        <div class="sender-avatar">
                                            <?php echo mb_substr($msg['receiver_name'], 0, 1); ?>
                                        </div>
                                        <div class="sender-info">
                                            <h4>إلى: <?php echo htmlspecialchars($msg['receiver_name']); ?></h4>
                                            <span class="sender-role">
                                                <?php
                                                $role_ar = [
                                                    'enseignant' => 'معلم',
                                                    'directeur' => 'مدير',
                                                    'supervisor_general' => 'مشرف عام',
                                                    'supervisor_subject' => 'مشرف مادة'
                                                ];
                                                echo $role_ar[$msg['receiver_role']] ?? $msg['receiver_role'];
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="message-date">
                                        <?php 
                                        $date = new DateTime($msg['created_at']);
                                        echo $date->format('Y-m-d H:i');
                                        ?>
                                    </div>
                                </div>
                                <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                <div class="message-preview"><?php echo htmlspecialchars(mb_substr($msg['content'] ?? '', 0, 150)) . '...'; ?></div>
                                <div class="message-actions">
                                    <button class="btn btn-small btn-primary" onclick="viewMessage(<?php echo $msg['id']; ?>, 'sent')">
                                        عرض
                                    </button>
                                    <a href="?delete=<?php echo $msg['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذه الرسالة؟')">
                                        حذف
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Compose Modal -->
    <div id="composeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✉️ إرسال رسالة جديدة</h2>
                <button class="btn-close" onclick="closeComposeModal()">×</button>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">المرسل إليه:</label>
                    <select name="receiver_id" class="form-control" required>
                        <option value="">اختر المستلم</option>
                        <?php
                        $role_ar = [
                            'enseignant' => 'معلم',
                            'directeur' => 'مدير',
                            'supervisor_general' => 'مشرف عام',
                            'supervisor_subject' => 'مشرف مادة'
                        ];
                        foreach ($contacts as $contact) {
                            echo '<option value="' . $contact['id'] . '">';
                            
                            $role_name = $role_ar[$contact['role']] ?? $contact['role'];
                            
                            if ($contact['subject_name'] && $contact['stage_name']) {
                                echo $role_name . ' ' . htmlspecialchars($contact['subject_name']) . ' - ' . htmlspecialchars($contact['stage_name']);
                            } elseif ($contact['subject_name']) {
                                echo $role_name . ' ' . htmlspecialchars($contact['subject_name']);
                            } elseif ($contact['stage_name']) {
                                echo $role_name . ' - ' . htmlspecialchars($contact['stage_name']);
                            } else {
                                echo $role_name;
                            }
                            
                            echo '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">الموضوع:</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">الرسالة:</label>
                    <textarea name="message" class="form-control" required></textarea>
                </div>
                
                <button type="submit" name="send_message" class="btn btn-primary" style="width: 100%;">
                    📤 إرسال
                </button>
            </form>
        </div>
    </div>
    
    <!-- View Message Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="viewModalSubject"></h2>
                <button class="btn-close" onclick="closeViewModal()">×</button>
            </div>
            
            <div id="viewModalContent" style="padding: 20px 0;">
                <!-- Content will be injected by JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
        // Messages data for viewing
        const messages = {
            inbox: <?php echo json_encode($inbox); ?>,
            sent: <?php echo json_encode($sent); ?>
        };
        
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function openComposeModal() {
            document.getElementById('composeModal').classList.add('active');
        }
        
        function closeComposeModal() {
            document.getElementById('composeModal').classList.remove('active');
        }
        
        function viewMessage(messageId, type) {
            const message = messages[type].find(m => m.id == messageId);
            if (!message) return;
            
            const roleAr = {
                'enseignant': 'معلم',
                'directeur': 'مدير',
                'supervisor_general': 'مشرف عام',
                'supervisor_subject': 'مشرف مادة'
            };
            
            const senderName = type === 'inbox' ? message.sender_name : message.receiver_name;
            const senderRole = type === 'inbox' ? message.sender_role : message.receiver_role;
            
            document.getElementById('viewModalSubject').textContent = message.subject;
            document.getElementById('viewModalContent').innerHTML = `
                <div style="margin-bottom: 20px; padding: 15px; background: #f9fafb; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #8b5cf6, #ec4899); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                            ${senderName.charAt(0)}
                        </div>
                        <div>
                            <div style="font-weight: 600; color: #1f2937;">
                                ${type === 'inbox' ? 'من' : 'إلى'}: ${senderName}
                            </div>
                            <div style="font-size: 0.85rem; color: #6b7280;">
                                ${roleAr[senderRole] || senderRole}
                            </div>
                        </div>
                    </div>
                    <div style="font-size: 0.85rem; color: #9ca3af;">
                        📅 ${new Date(message.created_at).toLocaleString('ar-EG')}
                    </div>
                </div>
                
                <div style="padding: 20px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; line-height: 1.8; white-space: pre-wrap; color: #1f2937;">
                    ${message.content}
                </div>
            `;
            
            document.getElementById('viewModal').classList.add('active');
            
            // Mark as read if inbox message
            if (type === 'inbox' && !message.is_read) {
                fetch('?read=' + messageId)
                    .then(() => location.reload())
                    .catch(err => console.error(err));
            }
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.getElementById('composeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeComposeModal();
            }
        });
        
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewModal();
            }
        });
    </script>
</body>
</html>
