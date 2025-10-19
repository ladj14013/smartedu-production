<?php
/**
 * Teacher Messages - نظام الرسائل للمعلم
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['enseignant', 'teacher']);

global $pdo;
$user_id = $_SESSION['user_id'];

// جلب معلومات المعلم
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

$error_message = '';
$success_message = '';

// حذف رسالة (إخفاؤها فقط عن هذا المستخدم)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $message_id = intval($_GET['delete']);
    
    // التحقق من أن الرسالة موجهة لهذا الأستاذ
    $check_stmt = $pdo->prepare("
        SELECT id, deleted_by FROM messages 
        WHERE id = ? 
        AND ((recipient_type = 'teacher' AND recipient_id = ?) 
             OR recipient_type = 'general')
    ");
    $check_stmt->execute([$message_id, $user_id]);
    $msg = $check_stmt->fetch();
    
    if ($msg) {
        // إضافة ID المستخدم لقائمة من حذفوا الرسالة
        $deleted_by = $msg['deleted_by'] ? explode(',', $msg['deleted_by']) : [];
        if (!in_array($user_id, $deleted_by)) {
            $deleted_by[] = $user_id;
        }
        $deleted_by_str = implode(',', $deleted_by);
        
        // تحديث قائمة deleted_by
        $update_stmt = $pdo->prepare("UPDATE messages SET deleted_by = ? WHERE id = ?");
        $update_stmt->execute([$deleted_by_str, $message_id]);
        
        $success_message = "تم حذف الرسالة بنجاح";
        header("Location: messages.php?tab=inbox&deleted=1");
        exit;
    }
}

// معالجة إرسال الرد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply_to_id = intval($_POST['reply_to_id']);
    $reply_subject = trim($_POST['reply_subject']);
    $reply_content = trim($_POST['reply_content']);
    
    // جلب معلومات الرسالة الأصلية
    $original_msg_stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
    $original_msg_stmt->execute([$reply_to_id]);
    $original_msg = $original_msg_stmt->fetch();
    
    if ($original_msg && $reply_subject && $reply_content) {
        // إرسال الرد
        $insert_stmt = $pdo->prepare("
            INSERT INTO messages 
            (subject, sender_name, sender_email, author_id, content, recipient_type, recipient_id, created_at) 
            VALUES (?, ?, ?, ?, ?, 'teacher', ?, NOW())
        ");
        
        $teacher_name = $teacher['nom'] . ' ' . $teacher['prenom'];
        $teacher_email = $teacher['email'] ?? '';
        
        $insert_stmt->execute([
            $reply_subject,
            $teacher_name,
            $teacher_email,
            $user_id,
            $reply_content,
            $original_msg['author_id'] // إرسال الرد للمرسل الأصلي
        ]);
        
        $success_message = "تم إرسال الرد بنجاح";
        header("Location: messages.php?tab=inbox&replied=1");
        exit;
    }
}

// تحديث حالة الرسالة إلى "مقروءة" عند النقر عليها
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $message_id = intval($_GET['mark_read']);
    
    // التحقق من أن الرسالة موجهة لهذا الأستاذ
    $check_stmt = $pdo->prepare("
        SELECT id, recipient_type, read_by FROM messages 
        WHERE id = ? 
        AND ((recipient_type = 'teacher' AND recipient_id = ?) 
             OR recipient_type = 'general')
    ");
    $check_stmt->execute([$message_id, $user_id]);
    $msg = $check_stmt->fetch();
    
    if ($msg) {
        if ($msg['recipient_type'] === 'general') {
            // للرسائل العامة: إضافة ID للقائمة read_by
            $read_by = $msg['read_by'] ? explode(',', $msg['read_by']) : [];
            if (!in_array($user_id, $read_by)) {
                $read_by[] = $user_id;
            }
            $read_by_str = implode(',', $read_by);
            $update_stmt = $pdo->prepare("UPDATE messages SET read_by = ? WHERE id = ?");
            $update_stmt->execute([$read_by_str, $message_id]);
        } else {
            // للرسائل الخاصة: تحديث is_read مباشرة
            $update_stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
            $update_stmt->execute([$message_id]);
        }
        
        // إعادة التوجيه لإزالة المعامل من URL
        header("Location: messages.php?tab=inbox");
        exit;
    }
}

// التبويب النشط
$active_tab = $_GET['tab'] ?? 'inbox';

// جلب الرسائل الواردة للأستاذ (من مشرف المادة أو الإدارة)
// مع استبعاد الرسائل التي حذفها هذا المستخدم
$stmt = $pdo->prepare("
    SELECT m.*, 
           CONCAT(u.nom, ' ', u.prenom) as sender_full_name,
           u.role as sender_role
    FROM messages m
    LEFT JOIN users u ON m.author_id = u.id
    WHERE ((m.recipient_type = 'teacher' AND m.recipient_id = ?)
           OR (m.recipient_type = 'general'))
    AND (m.deleted_by IS NULL OR m.deleted_by NOT LIKE CONCAT('%', ?, '%'))
    ORDER BY m.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$inbox_messages = $stmt->fetchAll();

// جلب الرسائل التي أرسلها الأستاذ
$stmt = $pdo->prepare("
    SELECT m.* 
    FROM messages m
    WHERE m.author_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$user_id]);
$sent_messages = $stmt->fetchAll();

// عدد الرسائل
$total_inbox = count($inbox_messages);
$total_sent = count($sent_messages);

// حساب الرسائل غير المقروءة (مع مراعاة read_by للرسائل العامة)
$total_unread = count(array_filter($inbox_messages, function($m) use ($user_id) {
    if ($m['recipient_type'] === 'general') {
        // للرسائل العامة: تحقق من read_by
        $read_by = $m['read_by'] ? explode(',', $m['read_by']) : [];
        return !in_array($user_id, $read_by);
    } else {
        // للرسائل الخاصة: تحقق من is_read
        return !$m['is_read'];
    }
}));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الرسائل - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .messages-header {
            background: linear-gradient(135deg, #4285F4 0%, #0066cc 100%);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(66, 133, 244, 0.3);
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #4285F4;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .tab {
            padding: 12px 25px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .tab.active {
            color: #4285F4;
            border-bottom-color: #4285F4;
        }
        
        .tab:hover {
            color: #4285F4;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .messages-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message-item {
            padding: 20px;
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .message-item:hover {
            border-color: #4285F4;
            background: #f0f7ff;
        }
        
        .message-item.unread {
            background: #dbeafe;
            border-color: #4285F4;
            font-weight: 600;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            justify-content: flex-start;
        }
        
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }
        
        .btn-reply {
            background: linear-gradient(135deg, #4285F4, #0066cc);
            color: white;
        }
        
        .btn-reply:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.4);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
            margin-bottom: 10px;
        }
        
        .message-from {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .message-subject {
            color: #374151;
            margin-bottom: 8px;
        }
        
        .message-preview {
            color: #6b7280;
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .message-date {
            font-size: 0.85rem;
            color: #9ca3af;
        }
        
        .role-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .role-student {
            background: #d1fae5;
            color: #065f46;
        }
        
        .role-parent {
            background: #fce7f3;
            color: #831843;
        }
        
        .compose-form {
            display: grid;
            gap: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Tajawal', sans-serif;
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
        
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4285F4, #0066cc);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 133, 244, 0.3);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #22c55e;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
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
            margin-bottom: 20px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="messages-header">
                <h1>💬 الرسائل</h1>
                <p>تواصل مع الطلاب وأولياء الأمور</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">✗ <?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">✓ تم حذف الرسالة بنجاح</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['replied'])): ?>
                <div class="alert alert-success">✓ تم إرسال الرد بنجاح</div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $total_inbox; ?></div>
                    <div class="stat-label">الرسائل الواردة</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-value"><?php echo $total_unread; ?></div>
                    <div class="stat-label">غير مقروءة</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-value"><?php echo $total_sent; ?></div>
                    <div class="stat-label">الرسائل المرسلة</div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <a href="?tab=inbox" class="tab <?php echo $active_tab === 'inbox' ? 'active' : ''; ?>">
                    📥 الوارد (<?php echo $total_inbox; ?>)
                </a>
                <a href="?tab=sent" class="tab <?php echo $active_tab === 'sent' ? 'active' : ''; ?>">
                    📤 المرسل (<?php echo $total_sent; ?>)
                </a>
            </div>
            
            <!-- Inbox Tab -->
            <?php if ($active_tab === 'inbox'): ?>
            <div class="card">
                <h3>📥 الرسائل الواردة</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    الرسائل المرسلة إليك من مشرف المادة أو الإدارة
                </p>
                
                <?php if (empty($inbox_messages)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <h3>لا توجد رسائل واردة</h3>
                        <p>لم تستلم أي رسائل بعد</p>
                    </div>
                <?php else: ?>
                    <div class="messages-list">
                        <?php foreach ($inbox_messages as $msg): 
                            // تحديد حالة القراءة لهذا المستخدم
                            $is_read_by_user = false;
                            if ($msg['recipient_type'] === 'general') {
                                $read_by = $msg['read_by'] ? explode(',', $msg['read_by']) : [];
                                $is_read_by_user = in_array($user_id, $read_by);
                            } else {
                                $is_read_by_user = $msg['is_read'];
                            }
                        ?>
                            <div class="message-item <?php echo !$is_read_by_user ? 'unread' : ''; ?>">
                                <div class="message-header" onclick="openMessage(<?php echo $msg['id']; ?>)" style="cursor: pointer;">
                                    <div>
                                        <div class="message-from">
                                            <?php if (!$is_read_by_user): ?>
                                                <span style="color: #ef4444;">● </span>
                                            <?php endif; ?>
                                            من: <?php echo htmlspecialchars($msg['sender_full_name'] ?? $msg['sender_name']); ?>
                                            <?php if ($msg['sender_role'] === 'supervisor_subject'): ?>
                                                <span class="role-badge" style="background: #dbeafe; color: #1e40af;">مشرف مادة</span>
                                            <?php elseif ($msg['sender_role'] === 'directeur'): ?>
                                                <span class="role-badge" style="background: #fef3c7; color: #92400e;">مدير</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-subject">
                                            <strong>الموضوع:</strong> <?php echo htmlspecialchars($msg['subject']); ?>
                                        </div>
                                        <div class="message-preview"><?php echo nl2br(htmlspecialchars(mb_substr($msg['content'], 0, 100))); ?>...</div>
                                    </div>
                                    <div class="message-date">
                                        <?php echo date('Y/m/d H:i', strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <!-- أزرار الإجراءات -->
                                <div class="message-actions">
                                    <button class="btn-action btn-reply" onclick="event.stopPropagation(); openReplyModal(<?php echo $msg['id']; ?>)">
                                        <span>↩️</span> رد
                                    </button>
                                    <button class="btn-action btn-delete" onclick="event.stopPropagation(); confirmDelete(<?php echo $msg['id']; ?>)">
                                        <span>🗑️</span> حذف
                                    </button>
                                </div>
                                
                                <!-- بيانات الرسالة الكاملة (مخفية) -->
                                <div style="display:none;" 
                                     data-id="<?php echo $msg['id']; ?>"
                                     data-sender="<?php echo htmlspecialchars($msg['sender_full_name'] ?? $msg['sender_name']); ?>"
                                     data-subject="<?php echo htmlspecialchars($msg['subject']); ?>"
                                     data-content="<?php echo htmlspecialchars($msg['content']); ?>"
                                     data-date="<?php echo date('Y/m/d H:i', strtotime($msg['created_at'])); ?>"
                                     data-read="<?php echo $is_read_by_user ? '1' : '0'; ?>"
                                     data-author-id="<?php echo $msg['author_id']; ?>"
                                     class="message-data">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sent Tab -->
            <?php else: ?>
            <div class="card">
                <h3>📤 رسائلي المرسلة</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    الرسائل التي أرسلتها إلى إدارة المنصة
                </p>
                
                <?php if (empty($sent_messages)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <h3>لم ترسل أي رسائل بعد</h3>
                        <p>يمكنك إرسال رسائل إلى إدارة المنصة من صفحة الرئيسية</p>
                    </div>
                <?php else: ?>
                    <div class="messages-list">
                        <?php foreach ($sent_messages as $msg): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <div>
                                        <div class="message-from">
                                            إلى: الإدارة
                                        </div>
                                        <div class="message-subject"><strong>الموضوع:</strong> <?php echo htmlspecialchars($msg['subject']); ?></div>
                                        <div class="message-preview"><?php echo nl2br(htmlspecialchars($msg['content'])); ?></div>
                                    </div>
                                    <div class="message-date">
                                        <?php echo date('Y/m/d H:i', strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal لعرض الرسالة كاملة -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalSubject">عنوان الرسالة</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #e5e7eb;">
                <div style="color: #6b7280; margin-bottom: 5px;">
                    <strong>من:</strong> <span id="modalSender"></span>
                </div>
                <div style="color: #6b7280; font-size: 0.9rem;">
                    <strong>التاريخ:</strong> <span id="modalDate"></span>
                </div>
            </div>
            <div id="modalContent" style="line-height: 1.8; white-space: pre-wrap;"></div>
        </div>
    </div>
    
    <!-- Modal للرد على الرسالة -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📨 الرد على الرسالة</h2>
                <button class="modal-close" onclick="closeReplyModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="reply_to_id" id="replyToId">
                
                <div class="form-group">
                    <label>الموضوع:</label>
                    <input type="text" name="reply_subject" id="replySubject" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>المحتوى:</label>
                    <textarea name="reply_content" class="form-control" rows="6" required placeholder="اكتب ردك هنا..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeReplyModal()">إلغاء</button>
                    <button type="submit" name="send_reply" class="btn btn-primary">إرسال الرد</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function openMessage(messageId) {
        // جلب بيانات الرسالة
        const messageData = document.querySelector(`[data-id="${messageId}"]`);
        if (!messageData) return;
        
        const sender = messageData.getAttribute('data-sender');
        const subject = messageData.getAttribute('data-subject');
        const content = messageData.getAttribute('data-content');
        const date = messageData.getAttribute('data-date');
        const isRead = messageData.getAttribute('data-read');
        
        // ملء Modal
        document.getElementById('modalSender').textContent = sender;
        document.getElementById('modalSubject').textContent = subject;
        document.getElementById('modalContent').textContent = content;
        document.getElementById('modalDate').textContent = date;
        
        // عرض Modal
        document.getElementById('messageModal').classList.add('active');
        
        // تحديث حالة القراءة إذا كانت غير مقروءة
        if (isRead == '0') {
            window.location.href = `?mark_read=${messageId}`;
        }
    }
    
    function closeModal() {
        document.getElementById('messageModal').classList.remove('active');
    }
    
    function openReplyModal(messageId) {
        // جلب بيانات الرسالة
        const messageData = document.querySelector(`[data-id="${messageId}"]`);
        if (!messageData) return;
        
        const subject = messageData.getAttribute('data-subject');
        
        // ملء نموذج الرد
        document.getElementById('replyToId').value = messageId;
        document.getElementById('replySubject').value = 'رد: ' + subject;
        
        // عرض Modal
        document.getElementById('replyModal').classList.add('active');
    }
    
    function closeReplyModal() {
        document.getElementById('replyModal').classList.remove('active');
    }
    
    function confirmDelete(messageId) {
        if (confirm('هل أنت متأكد من حذف هذه الرسالة؟\n\nلن تتمكن من استرجاعها بعد الحذف.')) {
            window.location.href = `?delete=${messageId}&tab=inbox`;
        }
    }
    
    // إغلاق عند النقر خارج Modal
    window.onclick = function(event) {
        const messageModal = document.getElementById('messageModal');
        const replyModal = document.getElementById('replyModal');
        
        if (event.target == messageModal) {
            closeModal();
        }
        if (event.target == replyModal) {
            closeReplyModal();
        }
    }
    </script>
</body>
</html>
