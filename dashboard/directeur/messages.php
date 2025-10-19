<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_role('directeur');

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// معالجة توجيه الرسالة للمشرف العام
if (isset($_GET['forward']) && is_numeric($_GET['forward'])) {
    $message_id = (int)$_GET['forward'];
    
    try {
        $query = "UPDATE messages SET status = 'forwarded', forwarded_to_role = 'supervisor_general', updated_at = NOW()
                  WHERE id = :message_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':message_id' => $message_id]);
        
        set_flash_message('success', 'تم توجيه الرسالة للمشرف العام بنجاح.');
        header("Location: messages.php");
        exit();
    } catch (PDOException $e) {
        $error = 'خطأ في توجيه الرسالة: ' . $e->getMessage();
    }
}

// معالجة تحديث حالة الرسالة إلى مقروءة
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $message_id = (int)$_GET['mark_read'];
    
    try {
        $query = "UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = :message_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':message_id' => $message_id]);
    } catch (PDOException $e) {
        // تجاهل الأخطاء
    }
}

// معالجة حذف رسالة
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $message_id = (int)$_GET['delete'];
    
    try {
        $query = "DELETE FROM messages WHERE id = :message_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':message_id' => $message_id]);
        
        set_flash_message('success', 'تم حذف الرسالة بنجاح.');
        header("Location: messages.php");
        exit();
    } catch (PDOException $e) {
        $error = 'خطأ في حذف الرسالة: ' . $e->getMessage();
    }
}

// جلب جميع الرسائل الموجهة للمدير
$query = "SELECT m.*, u.name as sender_name, u.email as sender_email
          FROM messages m
          LEFT JOIN users u ON m.sender_id = u.id
          WHERE m.recipient_role = 'directeur'
          ORDER BY m.is_read ASC, m.created_at DESC";
$messages = $db->query($query)->fetchAll();

// إحصائيات
$total_messages = count($messages);
$unread_messages = count(array_filter($messages, fn($m) => !$m['is_read']));
$forwarded_messages = count(array_filter($messages, fn($m) => $m['status'] === 'forwarded'));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صندوق الرسائل - Smart Education Hub</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .messages-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card-message {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        
        .stat-card-message h3 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
        }
        
        .stat-card-message p {
            margin: 0;
            color: #6b7280;
        }
        
        .messages-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .message-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .message-item.unread {
            border-right: 4px solid #4285F4;
            background: #f0f9ff;
        }
        
        .message-header {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }
        
        .message-header:hover {
            background: #f9fafb;
        }
        
        .message-info {
            flex: 1;
        }
        
        .message-subject {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 0.5rem 0;
        }
        
        .message-meta {
            display: flex;
            gap: 1.5rem;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .message-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .message-badges {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .message-content {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        
        .message-content.active {
            max-height: 1000px;
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .message-text {
            color: #374151;
            line-height: 1.8;
            margin-bottom: 1.5rem;
            white-space: pre-wrap;
        }
        
        .message-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .badge-new {
            background: #dbeafe;
            color: #1e40af;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-forwarded {
            background: #fef3c7;
            color: #92400e;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>📨 صندوق الرسائل الواردة</h1>
                    <p>إدارة الرسائل الواردة من المستخدمين</p>
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
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- إحصائيات الرسائل -->
            <div class="messages-stats">
                <div class="stat-card-message">
                    <h3 style="color: #4285F4;"><?php echo $total_messages; ?></h3>
                    <p>إجمالي الرسائل</p>
                </div>
                <div class="stat-card-message">
                    <h3 style="color: #EA4335;"><?php echo $unread_messages; ?></h3>
                    <p>رسائل جديدة</p>
                </div>
                <div class="stat-card-message">
                    <h3 style="color: #FBBC04;"><?php echo $forwarded_messages; ?></h3>
                    <p>رسائل موجهة</p>
                </div>
            </div>
            
            <!-- قائمة الرسائل -->
            <?php if (!empty($messages)): ?>
                <div class="messages-container">
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item <?php echo !$message['is_read'] ? 'unread' : ''; ?>" id="message-<?php echo $message['id']; ?>">
                            <div class="message-header" onclick="toggleMessage(<?php echo $message['id']; ?>)">
                                <div class="message-info">
                                    <h3 class="message-subject">
                                        <?php echo htmlspecialchars($message['subject']); ?>
                                    </h3>
                                    <div class="message-meta">
                                        <div class="message-meta-item">
                                            <span>👤</span>
                                            <span><?php echo htmlspecialchars($message['sender_name'] ?: 'غير معروف'); ?></span>
                                        </div>
                                        <div class="message-meta-item">
                                            <span>📧</span>
                                            <span><?php echo htmlspecialchars($message['sender_email'] ?: 'لا يوجد'); ?></span>
                                        </div>
                                        <div class="message-meta-item">
                                            <span>📅</span>
                                            <span><?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="message-badges">
                                    <?php if (!$message['is_read']): ?>
                                        <span class="badge-new">جديد</span>
                                    <?php endif; ?>
                                    <?php if ($message['status'] === 'forwarded'): ?>
                                        <span class="badge-forwarded">موجهة</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="message-content" id="content-<?php echo $message['id']; ?>">
                                <div class="message-text">
                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                </div>
                                
                                <div class="message-actions">
                                    <?php if (!$message['is_read']): ?>
                                        <a href="?mark_read=<?php echo $message['id']; ?>" class="btn btn-sm btn-secondary">
                                            ✓ تحديد كمقروء
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($message['status'] !== 'forwarded'): ?>
                                        <a href="?forward=<?php echo $message['id']; ?>" class="btn btn-sm btn-primary"
                                           onclick="return confirm('هل تريد توجيه هذه الرسالة للمشرف العام؟')">
                                            ↗️ توجيه للمشرف العام
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="?delete=<?php echo $message['id']; ?>" class="btn btn-sm btn-danger"
                                       onclick="return confirm('هل أنت متأكد من حذف هذه الرسالة؟')">
                                        🗑️ حذف
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>لا توجد رسائل</h3>
                    <p>لم تستلم أي رسائل بعد</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function toggleMessage(messageId) {
            const content = document.getElementById('content-' + messageId);
            const message = document.getElementById('message-' + messageId);
            
            content.classList.toggle('active');
            
            // تحديد الرسالة كمقروءة
            if (!message.classList.contains('read')) {
                fetch('?mark_read=' + messageId)
                    .then(() => {
                        message.classList.remove('unread');
                        message.classList.add('read');
                        const badge = message.querySelector('.badge-new');
                        if (badge) badge.remove();
                    });
            }
        }
    </script>
</body>
</html>
