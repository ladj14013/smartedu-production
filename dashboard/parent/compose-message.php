<?php
/**
 * Parent Dashboard - Compose Message to Teacher
 * لوحة تحكم ولي الأمر - إرسال رسالة للمعلم
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

requireLogin();
requireRole(['parent']);

$user_id = $_SESSION['user_id'];
global $pdo;

// جلب معلومات المعلم والمادة
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
$subject_name = isset($_GET['subject_name']) ? $_GET['subject_name'] : '';
$child_id = isset($_GET['child_id']) ? intval($_GET['child_id']) : 0;

if (!$teacher_id || !$child_id) {
    header('Location: children.php');
    exit();
}

// جلب معلومات المعلم
$teacher_stmt = $pdo->prepare("
    SELECT u.*, s.name as subject_name, st.name as stage_name
    FROM users u
    LEFT JOIN subjects s ON u.subject_id = s.id
    LEFT JOIN stages st ON u.stage_id = st.id
    WHERE u.id = ?
");
$teacher_stmt->execute([$teacher_id]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

// جلب معلومات الابن
$child_stmt = $pdo->prepare("SELECT nom, prenom FROM users WHERE id = ?");
$child_stmt->execute([$child_id]);
$child = $child_stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher || !$child) {
    header('Location: children.php');
    exit();
}

// جلب معلومات ولي الأمر
$parent_stmt = $pdo->prepare("SELECT nom, prenom, email FROM users WHERE id = ?");
$parent_stmt->execute([$user_id]);
$parent = $parent_stmt->fetch(PDO::FETCH_ASSOC);

$message_sent = false;
$error_message = '';

// معالجة إرسال الرسالة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $subject = trim($_POST['subject']) ?? '';
    $message = trim($_POST['message']) ?? '';
    
    if ($subject && $message) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (subject, sender_name, sender_email, author_id, content, recipient_id, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            
            $sender_name = $parent['nom'] . ' ' . $parent['prenom'];
            $stmt->execute([
                $subject,
                $sender_name,
                $parent['email'],
                $user_id,
                $message,
                $teacher_id
            ]);
            
            $message_sent = true;
        } catch (PDOException $e) {
            $error_message = 'حدث خطأ أثناء إرسال الرسالة: ' . $e->getMessage();
        }
    } else {
        $error_message = 'يرجى ملء جميع الحقول';
    }
}

$role_ar = [
    'enseignant' => 'معلم',
    'supervisor_subject' => 'مشرف مادة'
];

$page_title = "إرسال رسالة";
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .message-form-container {
            max-width: 800px;
            margin: 2rem auto;
        }

        .recipient-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .recipient-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.3rem;
        }

        .recipient-card p {
            margin: 0.25rem 0;
            opacity: 0.95;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-envelope"></i> <?php echo $page_title; ?></h1>
                    <p>تواصل مع <?php echo $role_ar[$teacher['role']] ?? $teacher['role']; ?> حول ابنك</p>
                </div>
            </div>
        </header>

        <div class="message-form-container">
            <?php if ($message_sent): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>تم إرسال الرسالة بنجاح!</strong>
                    <p>سيتم الرد عليك في أقرب وقت ممكن.</p>
                    <div style="margin-top: 1rem;">
                        <a href="child-details.php?id=<?php echo $child_id; ?>" class="btn btn-primary">
                            العودة لتفاصيل الابن
                        </a>
                        <a href="messages.php" class="btn btn-secondary">
                            عرض الرسائل
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="recipient-card">
                    <h3>
                        <i class="fas fa-user"></i>
                        <?php echo $role_ar[$teacher['role']] ?? $teacher['role']; ?>
                        <?php echo htmlspecialchars($teacher['subject_name'] ?? $subject_name); ?>
                    </h3>
                    <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($teacher['stage_name'] ?? ''); ?></p>
                    <p><i class="fas fa-child"></i> بخصوص: <?php echo htmlspecialchars($child['nom'] . ' ' . $child['prenom']); ?></p>
                </div>

                <div class="card">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="subject">
                                <i class="fas fa-heading"></i> موضوع الرسالة
                            </label>
                            <input 
                                type="text" 
                                id="subject" 
                                name="subject" 
                                class="form-control"
                                placeholder="مثال: استفسار عن مستوى ابني في المادة"
                                value="استفسار عن <?php echo htmlspecialchars($child['nom'] . ' ' . $child['prenom']); ?> في مادة <?php echo htmlspecialchars($subject_name); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="message">
                                <i class="fas fa-comment-alt"></i> الرسالة
                            </label>
                            <textarea 
                                id="message" 
                                name="message" 
                                class="form-control"
                                placeholder="اكتب رسالتك هنا..."
                                required
                            ></textarea>
                        </div>

                        <div class="btn-group">
                            <button type="submit" name="send_message" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                إرسال الرسالة
                            </button>
                            <a href="child-details.php?id=<?php echo $child_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // تلقائي focus على textarea
        document.addEventListener('DOMContentLoaded', function() {
            const messageField = document.getElementById('message');
            if (messageField) {
                messageField.focus();
            }
        });
    </script>
</body>
</html>
