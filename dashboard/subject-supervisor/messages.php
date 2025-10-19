<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/auth.php';
require_once '../../config/database.php';
requireLogin();
requireRole(['superviseur_matiere', 'supervisor_subject', 'subject_supervisor']);

global $pdo;

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

// التبويب النشط
$active_tab = $_GET['tab'] ?? 'inbox';

// حذف رسالة (إخفاؤها فقط عن هذا المستخدم)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $message_id = intval($_GET['delete']);
    
    // التحقق من أن الرسالة موجهة لهذا المشرف
    $check_stmt = $pdo->prepare("
        SELECT id, deleted_by FROM messages 
        WHERE id = ? 
        AND ((recipient_type = 'director' AND recipient_id = ?) 
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
        
        header("Location: messages.php?tab=inbox&deleted=1");
        exit;
    }
}

// تحديث حالة الرسالة إلى "مقروءة"
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $message_id = intval($_GET['mark_read']);
    
    $check_stmt = $pdo->prepare("
        SELECT id, recipient_type, read_by FROM messages 
        WHERE id = ? 
        AND (recipient_id = ? OR recipient_type = 'general')
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
            $update_stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
            $update_stmt->execute([$message_id]);
        }
        
        header("Location: messages.php?tab=inbox");
        exit;
    }
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_subject = trim($_POST['subject'] ?? '');
    $message_content = trim($_POST['content'] ?? '');
    $recipient_type = $_POST['recipient_type'] ?? 'general';
    
    // Get recipient_id based on type
    $recipient_id = null;
    if ($recipient_type === 'teacher' && isset($_POST['recipient_id_teacher'])) {
        $recipient_id = intval($_POST['recipient_id_teacher']);
    } elseif ($recipient_type === 'director' && isset($_POST['recipient_id_director'])) {
        $recipient_id = intval($_POST['recipient_id_director']);
    }
    
    if ($message_subject && $message_content) {
        $insert = "INSERT INTO messages (subject, sender_name, sender_email, author_id, content, recipient_type, recipient_id, created_at) 
                   VALUES (:subject, :sender_name, :sender_email, :author_id, :content, :recipient_type, :recipient_id, NOW())";
        $stmt = $pdo->prepare($insert);
        $stmt->execute([
            ':subject' => $message_subject,
            ':sender_name' => $supervisor['nom'] . ' ' . $supervisor['prenom'],
            ':sender_email' => $supervisor['email'] ?? '',
            ':author_id' => $user_id,
            ':content' => $message_content,
            ':recipient_type' => $recipient_type,
            ':recipient_id' => $recipient_id
        ]);
        
        $success = "تم إرسال الرسالة بنجاح!";
    }
}

// Get teachers
$teachers_query = "SELECT DISTINCT u.id, CONCAT(u.nom, ' ', u.prenom) as full_name, u.email FROM users u
                   JOIN lessons l ON u.id = l.author_id
                   WHERE l.subject_id = :subject_id
                   ORDER BY u.nom, u.prenom";
$stmt = $pdo->prepare($teachers_query);
$stmt->execute([':subject_id' => $subject_id]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get directors/admins
$directors_query = "SELECT id, CONCAT(nom, ' ', prenom) as full_name, email 
                    FROM users 
                    WHERE role IN ('directeur', 'director', 'supervisor_general')
                    ORDER BY nom, prenom";
$stmt = $pdo->query($directors_query);
$directors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get inbox messages (received by this supervisor)
$inbox_query = "SELECT m.*, 
                CONCAT(u.nom, ' ', u.prenom) as sender_full_name,
                u.role as sender_role
                FROM messages m
                LEFT JOIN users u ON m.author_id = u.id
                WHERE (m.recipient_id = ? OR m.recipient_type = 'general')
                AND (m.deleted_by IS NULL OR m.deleted_by NOT LIKE CONCAT('%', ?, '%'))
                ORDER BY m.created_at DESC";
$stmt = $pdo->prepare($inbox_query);
$stmt->execute([$user_id, $user_id]);
$inbox_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sent messages by this supervisor with recipient info
$messages_query = "SELECT m.*, 
                   CONCAT(u.nom, ' ', u.prenom) as recipient_name
                   FROM messages m
                   LEFT JOIN users u ON m.recipient_id = u.id
                   WHERE m.author_id = :author_id
                   ORDER BY m.created_at DESC LIMIT 20";
$stmt = $pdo->prepare($messages_query);
$stmt->execute([':author_id' => $user_id]);
$sent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// حساب الرسائل
$total_inbox = count($inbox_messages);
$total_sent = count($sent_messages);
$total_unread = count(array_filter($inbox_messages, function($m) use ($user_id) {
    if ($m['recipient_type'] === 'general') {
        $read_by = $m['read_by'] ? explode(',', $m['read_by']) : [];
        return !in_array($user_id, $read_by);
    } else {
        return !$m['is_read'];
    }
}));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الرسائل - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/rtl-sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
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
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .header p { font-size: 1.1em; opacity: 0.9; }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            margin: 20px 30px;
            border-radius: 10px;
            border-left: 4px solid #28a745;
        }
        .controls {
            padding: 20px 30px;
            background: white;
            border-bottom: 2px solid #e0e0e0;
            text-align: center;
        }
        .btn-back {
            padding: 10px 25px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
        }
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
        }
        .section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
        }
        .section-title {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
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
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
        }
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        .form-group select:focus,
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-send {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .messages-list {
            max-height: 600px;
            overflow-y: auto;
        }
        .message-card {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            cursor: pointer;
            transition: all 0.3s;
        }
        .message-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .message-card.unread {
            background: #e3f2fd;
            border-left-color: #2196F3;
            font-weight: 600;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            border-radius: 12px;
            padding: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .tab {
            flex: 1;
            padding: 15px 20px;
            background: transparent;
            border: none;
            color: #667eea;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
            font-weight: 600;
            text-align: center;
            font-size: 1em;
        }
        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .tab:hover {
            background: rgba(102, 126, 234, 0.1);
        }
        .tab.active:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 2px solid #e5e7eb;
            transition: all 0.3s;
        }
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            border-color: #667eea;
        }
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-label {
            font-size: 1em;
            color: #6b7280;
            margin-top: 8px;
            font-weight: 600;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .message-recipient {
            font-weight: bold;
            color: #333;
        }
        .message-date {
            font-size: 0.85em;
            color: #999;
        }
        .message-text {
            color: #555;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .empty-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .content { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💬 الرسائل</h1>
            <p>مادة: <?php echo htmlspecialchars($supervisor['subject_name']); ?></p>
        </div>
        <?php if (isset($success)): ?>
            <div class="success-message">✓ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="success-message">✓ تم حذف الرسالة بنجاح</div>
        <?php endif; ?>
        
        <div class="controls">
            <a href="index.php" class="btn-back">🏠 العودة للرئيسية</a>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-value"><?php echo $total_inbox; ?></div>
                <div class="stat-label">📥 الرسائل الواردة</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo $total_unread; ?></div>
                <div class="stat-label">🔴 غير مقروءة</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo $total_sent; ?></div>
                <div class="stat-label">📤 الرسائل المرسلة</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=inbox" class="tab <?php echo $active_tab === 'inbox' ? 'active' : ''; ?>">
                📥 الوارد (<?php echo $total_inbox; ?>)
            </a>
            <a href="?tab=compose" class="tab <?php echo $active_tab === 'compose' ? 'active' : ''; ?>">
                ✉️ إرسال رسالة
            </a>
            <a href="?tab=sent" class="tab <?php echo $active_tab === 'sent' ? 'active' : ''; ?>">
                📤 المرسل (<?php echo $total_sent; ?>)
            </a>
        </div>
        
        <?php if ($active_tab === 'inbox'): ?>
        <!-- Inbox Tab -->
        <div class="content">
            <div class="section">
                <h2 class="section-title">📥 الرسائل الواردة</h2>
                <?php if (empty($inbox_messages)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>لا توجد رسائل واردة</p>
                    </div>
                <?php else: ?>
                    <div class="messages-list">
                        <?php foreach ($inbox_messages as $msg): 
                            $is_read_by_user = false;
                            if ($msg['recipient_type'] === 'general') {
                                $read_by = $msg['read_by'] ? explode(',', $msg['read_by']) : [];
                                $is_read_by_user = in_array($user_id, $read_by);
                            } else {
                                $is_read_by_user = $msg['is_read'];
                            }
                        ?>
                            <div class="message-card <?php echo !$is_read_by_user ? 'unread' : ''; ?>" 
                                 onclick="openMessage(<?php echo $msg['id']; ?>)">
                                <div class="message-header">
                                    <div class="message-recipient">
                                        <?php if (!$is_read_by_user): ?>
                                            <span style="color: #2196F3;">● </span>
                                        <?php endif; ?>
                                        من: <?php echo htmlspecialchars($msg['sender_full_name'] ?? $msg['sender_name']); ?>
                                        <?php if ($msg['sender_role'] === 'teacher' || $msg['sender_role'] === 'enseignant'): ?>
                                            <span style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-size: 0.8em; margin-right: 5px;">أستاذ</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="message-date">
                                        <?php echo date('Y/m/d H:i', strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                                <div style="margin-top: 10px;">
                                    <strong>الموضوع:</strong> <?php echo htmlspecialchars($msg['subject']); ?>
                                </div>
                                <div class="message-text" style="margin-top: 5px; color: #777;">
                                    <?php echo nl2br(htmlspecialchars(mb_substr($msg['content'], 0, 100))); ?>...
                                </div>
                                
                                <!-- بيانات الرسالة المخفية -->
                                <div style="display:none;" 
                                     data-id="<?php echo $msg['id']; ?>"
                                     data-sender="<?php echo htmlspecialchars($msg['sender_full_name'] ?? $msg['sender_name']); ?>"
                                     data-subject="<?php echo htmlspecialchars($msg['subject']); ?>"
                                     data-content="<?php echo htmlspecialchars($msg['content']); ?>"
                                     data-date="<?php echo date('Y/m/d H:i', strtotime($msg['created_at'])); ?>"
                                     data-read="<?php echo $is_read_by_user ? '1' : '0'; ?>"
                                     class="message-data">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php elseif ($active_tab === 'compose'): ?>
        <!-- Compose Tab -->
        <div class="content">
            <div class="section">
                <h2 class="section-title">✉️ إرسال إعلان أو ملاحظة</h2>
                <form method="POST" action="" id="messageForm">
                    <div class="form-group">
                        <label>📋 الموضوع:</label>
                        <input type="text" name="subject" required placeholder="موضوع الرسالة...">
                    </div>
                    
                    <div class="form-group">
                        <label>� نوع المستلم:</label>
                        <select name="recipient_type" id="recipientType" required>
                            <option value="general">إعلان عام لجميع الأساتذة</option>
                            <option value="teacher">أستاذ محدد</option>
                            <option value="director">مدير الموقع</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="teacherSelect" style="display: none;">
                        <label>👨‍🏫 اختر الأستاذ:</label>
                        <select name="recipient_id_teacher">
                            <option value="">-- اختر أستاذاً --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['full_name']) ?> (<?= htmlspecialchars($teacher['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="directorSelect" style="display: none;">
                        <label>👨‍💼 اختر المدير:</label>
                        <select name="recipient_id_director">
                            <option value="">-- اختر مديراً --</option>
                            <?php foreach ($directors as $director): ?>
                                <option value="<?= $director['id'] ?>"><?= htmlspecialchars($director['full_name']) ?> (<?= htmlspecialchars($director['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>�📝 المحتوى:</label>
                        <textarea name="content" required placeholder="اكتب محتوى الرسالة هنا...&#10;&#10;مثال:&#10;السلام عليكم،&#10;&#10;يرجى من جميع الأساتذة...&#10;&#10;مع التقدير"></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn-send">📤 إرسال الرسالة</button>
                </form>
            </div>
            <div class="section">
                <h2 class="section-title">📬 الرسائل المرسلة (<?php echo count($sent_messages); ?>)</h2>
                <div class="messages-list">
                    <?php if (empty($sent_messages)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <p>لم ترسل أي رسائل بعد</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sent_messages as $msg): ?>
                            <div class="message-card">
                                <div class="message-header">
                                    <div class="message-recipient">
                                        <strong><?php echo htmlspecialchars($msg['subject']); ?></strong>
                                        <br>
                                        <small style="color: #666;">
                                            <?php 
                                            if ($msg['recipient_type'] === 'teacher') {
                                                echo '👨‍🏫 إلى: ' . htmlspecialchars($msg['recipient_name'] ?? 'غير محدد');
                                            } elseif ($msg['recipient_type'] === 'director') {
                                                echo '👨‍💼 إلى: ' . htmlspecialchars($msg['recipient_name'] ?? 'غير محدد');
                                            } else {
                                                echo '📢 إعلان عام';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <div class="message-date">
                                        <?php echo date('Y/m/d H:i', strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="message-text">
                                    <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal لعرض الرسالة -->
    <div id="messageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 16px; padding: 30px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; padding-bottom: 15px;">
                <h2 id="modalSubject" style="margin: 0;"></h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 2rem; cursor: pointer; color: #6b7280;">&times;</button>
            </div>
            <div style="margin-bottom: 15px;">
                <div style="color: #6b7280; margin-bottom: 5px;">
                    <strong>من:</strong> <span id="modalSender"></span>
                </div>
                <div style="color: #6b7280; font-size: 0.9rem;">
                    <strong>التاريخ:</strong> <span id="modalDate"></span>
                </div>
            </div>
            <div id="modalContent" style="line-height: 1.8; white-space: pre-wrap;"></div>
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="confirmDelete()" id="btnDelete" style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 8px; cursor: pointer;">🗑️ حذف</button>
                <button onclick="closeModal()" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 8px; cursor: pointer;">إغلاق</button>
            </div>
        </div>
    </div>
    
    <script>
    let currentMessageId = null;
    
    function openMessage(messageId) {
        currentMessageId = messageId;
        const messageData = document.querySelector(`[data-id="${messageId}"]`);
        if (!messageData) return;
        
        const sender = messageData.getAttribute('data-sender');
        const subject = messageData.getAttribute('data-subject');
        const content = messageData.getAttribute('data-content');
        const date = messageData.getAttribute('data-date');
        const isRead = messageData.getAttribute('data-read');
        
        document.getElementById('modalSender').textContent = sender;
        document.getElementById('modalSubject').textContent = subject;
        document.getElementById('modalContent').textContent = content;
        document.getElementById('modalDate').textContent = date;
        
        const modal = document.getElementById('messageModal');
        modal.style.display = 'flex';
        
        if (isRead == '0') {
            window.location.href = `?mark_read=${messageId}&tab=inbox`;
        }
    }
    
    function closeModal() {
        document.getElementById('messageModal').style.display = 'none';
        currentMessageId = null;
    }
    
    function confirmDelete() {
        if (currentMessageId && confirm('هل أنت متأكد من حذف هذه الرسالة؟')) {
            window.location.href = `?delete=${currentMessageId}&tab=inbox`;
        }
    }
    
    document.getElementById('recipientType').addEventListener('change', function() {
        const teacherSelect = document.getElementById('teacherSelect');
        const directorSelect = document.getElementById('directorSelect');
        const teacherField = document.querySelector('[name="recipient_id_teacher"]');
        const directorField = document.querySelector('[name="recipient_id_director"]');
        
        // Hide all recipient selects
        teacherSelect.style.display = 'none';
        directorSelect.style.display = 'none';
        
        // Remove required from all
        teacherField.required = false;
        directorField.required = false;
        
        // Show appropriate select based on type
        if (this.value === 'teacher') {
            teacherSelect.style.display = 'block';
            teacherField.required = true;
        } else if (this.value === 'director') {
            directorSelect.style.display = 'block';
            directorField.required = true;
        }
    });
    </script>
</body>
</html>
