<?php
// تفعيل عرض الأخطاء للتطوير
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../includes/auth.php';

// التحقق من تسجيل الدخول والصلاحيات
require_auth();
if (!has_any_role(['enseignant', 'teacher'])) {
    header("Location: ../../dashboard/index.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$teacher_subject_id = $_SESSION['subject_id'] ?? null;
$teacher_stage_id = $_SESSION['stage_id'] ?? null;

// إذا لم يكن للأستاذ مادة مسجلة، إعادة توجيه
if (!$teacher_subject_id) {
    header("Location: ../../dashboard/teacher/index.php?error=no_subject");
    exit();
}

$db = getDB();

// جلب معلومات المادة والمرحلة للأستاذ
$stmt = $db->prepare("
    SELECT s.name as subject_name, st.name as stage_name, s.id as subject_id
    FROM subjects s
    LEFT JOIN stages st ON s.stage_id = st.id
    WHERE s.id = ?
");
$stmt->execute([$teacher_subject_id]);
$subject_info = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب السنوات الدراسية للمرحلة
$stmt = $db->prepare("SELECT * FROM levels WHERE stage_id = ? ORDER BY `order`");
$stmt->execute([$teacher_stage_id]);
$levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $level_id = intval($_POST['level_id'] ?? 0);
    $video_url = trim($_POST['video_url'] ?? '');
    $type = $_POST['type'] ?? 'public';
    $is_locked = isset($_POST['is_locked']) ? 1 : 0;
    
    if (empty($title)) {
        $error = 'يرجى إدخال عنوان الدرس';
    } elseif (empty($content)) {
        $error = 'يرجى إدخال محتوى الدرس';
    } elseif ($level_id <= 0) {
        $error = 'يرجى اختيار السنة الدراسية';
    } else {
        // التحقق من أن السنة الدراسية تنتمي للمرحلة الصحيحة
        $stmt = $db->prepare("SELECT id FROM levels WHERE id = ? AND stage_id = ?");
        $stmt->execute([$level_id, $teacher_stage_id]);
        if (!$stmt->fetch()) {
            $error = 'السنة الدراسية المختارة غير صحيحة';
        } else {
            // رفع ملف PDF إذا وجد
            $pdf_url = null;
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                // --- جلب معلومات المستوى والمادة والمرحلة ---
                $level_stmt = $db->prepare("SELECT name FROM levels WHERE id = ?");
                $level_stmt->execute([$level_id]);
                $level_row = $level_stmt->fetch(PDO::FETCH_ASSOC);
                $level_name = $level_row ? $level_row['name'] : 'level';

                $subject_name = $subject_info['subject_name'] ?? 'subject';
                $stage_name = $subject_info['stage_name'] ?? 'stage';
                $teacher_name = $_SESSION['user_name'] ?? 'teacher';

                // transliteration بسيط (استبدال المسافات وحروف عربية)
                function slugify($text) {
                    $text = preg_replace('~[\s]+~u', '-', $text);
                    $text = str_replace(
                        ['أ','إ','آ','ا','ب','ت','ث','ج','ح','خ','د','ذ','ر','ز','س','ش','ص','ض','ط','ظ','ع','غ','ف','ق','ك','ل','م','ن','ه','و','ي','ء','ى','ة'],
                        ['a','i','a','a','b','t','th','j','h','kh','d','dh','r','z','s','sh','s','d','t','z','a','gh','f','q','k','l','m','n','h','w','y','a','a','h'],
                        $text
                    );
                    $text = preg_replace('/[^A-Za-z0-9\-]/', '', $text);
                    return strtolower($text);
                }

                // جلب بيانات المستخدم (الاسم واللقب)
                $user_stmt = $db->prepare("SELECT nom, prenom FROM users WHERE id = ?");
                $user_stmt->execute([$teacher_id]);
                $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
                $nom = isset($user_row['nom']) ? slugify($user_row['nom']) : 'nom';
                $prenom = isset($user_row['prenom']) ? slugify($user_row['prenom']) : 'prenom';

                $stage_id = $teacher_stage_id;
                $level_id = $level_id;
                $subject_id = $teacher_subject_id;

                // سنحدد lesson_id بعد الإدخال
                $lesson_slug = slugify($title);

                // هل الملف تمرين؟
                $is_exercise = false;
                if (isset($_POST['is_exercise']) && $_POST['is_exercise'] == '1') {
                    $is_exercise = true;
                } elseif (stripos($lesson_slug, 'tamreen') !== false || stripos($lesson_slug, 'exercise') !== false) {
                    $is_exercise = true;
                }

                $file_extension = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
                if ($file_extension !== 'pdf') {
                    $error = 'يرجى رفع ملف PDF فقط';
                } else {
                    // إدخال الدرس أولاً للحصول على lesson_id
                    $pdf_url = null;
                    $status = ($type === 'private') ? 'approved' : 'pending';
                    $stmt = $db->prepare("
                        INSERT INTO lessons (title, content, video_url, pdf_url, subject_id, level_id, author_id, type, is_locked, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title,
                        $content,
                        !empty($video_url) ? $video_url : null,
                        null, // pdf_url مؤقتاً
                        $teacher_subject_id,
                        $level_id,
                        $teacher_id,
                        $type,
                        $is_locked,
                        $status
                    ]);
                    $lesson_id = $db->lastInsertId();

                    // بناء اسم الملف حسب الهيكل المقترح
                    $filename_parts = [$stage_id, $level_id, $subject_id, $lesson_id, $lesson_slug];
                    if ($is_exercise) $filename_parts[] = 'exercise';
                    $filename_parts[] = $teacher_id . '[' . $nom . ',' . $prenom . ']';
                    $new_filename = implode('/', $filename_parts) . '.pdf';

                    // بناء المسار الكامل
                    $upload_dir = '../../uploads/' . $stage_id . '/' . $level_id . '/' . $subject_id . '/' . $lesson_id . '/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $upload_path = $upload_dir . $lesson_slug . '-' . $teacher_id . '[' . $nom . ',' . $prenom . '].pdf';

                    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_path)) {
                        $pdf_url = 'uploads/' . $stage_id . '/' . $level_id . '/' . $subject_id . '/' . $lesson_id . '/' . $lesson_slug . '-' . $teacher_id . '[' . $nom . ',' . $prenom . '].pdf';
                        // تحديث pdf_url في الدرس
                        $update_stmt = $db->prepare("UPDATE lessons SET pdf_url = ? WHERE id = ?");
                        $update_stmt->execute([$pdf_url, $lesson_id]);
                    } else {
                        $error = 'فشل رفع الملف، يرجى المحاولة مرة أخرى';
                    }
                }
                // رسالة مختلفة حسب نوع الدرس
                if (empty($error)) {
                    if ($type === 'private') {
                        set_flash_message('success', 'تم إضافة الدرس الخاص بنجاح! الدرس متاح الآن لطلابك.');
                    } else {
                        set_flash_message('success', 'تم إرسال الدرس للمراجعة! سيتم نشره بعد موافقة مشرف المادة.');
                    }
                    header("Location: lessons.php");
                    exit();
                }
            }
            
            if (empty($error)) {
                try {
                    // تحديد حالة الدرس: الدروس الخاصة تُنشر مباشرة، العامة تحتاج موافقة
                    $status = ($type === 'private') ? 'approved' : 'pending';
                    
                    $stmt = $db->prepare("
                        INSERT INTO lessons (title, content, video_url, pdf_url, subject_id, level_id, author_id, type, is_locked, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title,
                        $content,
                        !empty($video_url) ? $video_url : null,
                        $pdf_url,
                        $teacher_subject_id,
                        $level_id,
                        $teacher_id,
                        $type,
                        $is_locked,
                        $status
                    ]);
                    
                    $lesson_id = $db->lastInsertId();
                    
                    // رسالة مختلفة حسب نوع الدرس
                    if ($type === 'private') {
                        set_flash_message('success', 'تم إضافة الدرس الخاص بنجاح! الدرس متاح الآن لطلابك.');
                    } else {
                        set_flash_message('success', 'تم إرسال الدرس للمراجعة! سيتم نشره بعد موافقة مشرف المادة.');
                    }
                    header("Location: lessons.php");
                    exit();
                } catch (PDOException $e) {
                    $error = 'خطأ في إضافة الدرس: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة درس جديد - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4CAF50;
        }
        
        .subject-info {
            background: linear-gradient(135deg, #f0f9f0 0%, #e8f5e9 100%);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #4CAF50;
        }
        
        .subject-info h3 {
            color: #2e7d32;
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }
        
        .subject-info p {
            margin: 5px 0;
            color: #424242;
        }
        
        .subject-info strong {
            color: #1b5e20;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .form-group textarea {
            min-height: 300px;
            resize: vertical;
            line-height: 1.6;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #757575;
            font-size: 0.875rem;
        }
        
        .file-input {
            padding: 12px;
            border: 2px dashed #bdbdbd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-input:hover {
            border-color: #4CAF50;
            background: #fafafa;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .radio-option input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .radio-option label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #fff3e0;
            border-radius: 8px;
            border-left: 4px solid #ff9800;
        }
        
        .checkbox-group input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
            color: #e65100;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            background: #757575;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #616161;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #e53935;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1>📝 إضافة درس جديد</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </div>
            </div>
            
            <div class="content">
                <div class="form-container">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <span style="font-size: 1.5rem;">⚠️</span>
                            <span><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-header">
                        <h2 style="color: #2c3e50; margin: 0;">إنشاء محتوى تعليمي جديد</h2>
                    </div>
                    
                    <div class="subject-info">
                        <h3>📚 معلومات المادة</h3>
                        <p><strong>المادة:</strong> <?php echo htmlspecialchars($subject_info['subject_name'] ?? 'غير محدد'); ?></p>
                        <p><strong>المرحلة:</strong> <?php echo htmlspecialchars($subject_info['stage_name'] ?? 'غير محدد'); ?></p>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="title">عنوان الدرس <span class="required">*</span></label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                placeholder="مثال: مقدمة في الجبر - المعادلات من الدرجة الأولى"
                                value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                required
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="level_id">السنة الدراسية <span class="required">*</span></label>
                            <select id="level_id" name="level_id" required>
                                <option value="">-- اختر السنة الدراسية --</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo $level['id']; ?>" 
                                        <?php echo (isset($_POST['level_id']) && $_POST['level_id'] == $level['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($level['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="content">محتوى الدرس <span class="required">*</span></label>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <button type="button" class="btn-math-toolbar open-math-toolbar" onclick="showMathToolbar('content')">
                                    🔢 لوحة الرموز الرياضية
                                </button>
                            </div>
                            <textarea 
                                id="content" 
                                name="content" 
                                placeholder="اكتب محتوى الدرس بالتفصيل...&#10;&#10;يمكنك تقسيم الدرس إلى أجزاء مع شرح مفصل لكل جزء.&#10;&#10;للمعادلات الرياضية: استخدم $x^2$ للمعادلات السطرية أو $$\int x dx$$ للمعادلات المنفصلة"
                                required
                            ><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                            <small>💡 نصيحة: اكتب المحتوى بشكل واضح ومنظم. استخدم لوحة الرموز لإدراج المعادلات الرياضية</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="video_url">رابط فيديو YouTube (اختياري)</label>
                            <input 
                                type="text" 
                                id="video_url" 
                                name="video_url" 
                                placeholder="https://www.youtube.com/watch?v=..."
                                value="<?php echo htmlspecialchars($_POST['video_url'] ?? ''); ?>"
                            >
                            <small>📹 يمكنك إضافة فيديو تعليمي لدعم الشرح النصي</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="pdf_file">ملف PDF مرفق (اختياري)</label>
                            <input 
                                type="file" 
                                id="pdf_file" 
                                name="pdf_file" 
                                accept=".pdf"
                                class="file-input"
                            >
                            <small>📄 يمكنك إرفاق ملف PDF يحتوي على ملخص أو تمارين إضافية (الحد الأقصى: 10 ميجابايت)</small>
                        </div>
                        
                        <div class="form-group">
                            <label>نوع الدرس</label>
                            <div class="radio-group">
                                <div class="radio-option">
                                    <input 
                                        type="radio" 
                                        id="type_public" 
                                        name="type" 
                                        value="public" 
                                        <?php echo (!isset($_POST['type']) || $_POST['type'] === 'public') ? 'checked' : ''; ?>
                                    >
                                    <label for="type_public">🌍 عام - متاح لجميع الطلاب</label>
                                </div>
                                <div class="radio-option">
                                    <input 
                                        type="radio" 
                                        id="type_private" 
                                        name="type" 
                                        value="private"
                                        <?php echo (isset($_POST['type']) && $_POST['type'] === 'private') ? 'checked' : ''; ?>
                                    >
                                    <label for="type_private">🔒 خاص - لطلاب محددين فقط</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input 
                                    type="checkbox" 
                                    id="is_locked" 
                                    name="is_locked"
                                    <?php echo (isset($_POST['is_locked'])) ? 'checked' : ''; ?>
                                >
                                <label for="is_locked">🔐 درس مقفل - يتطلب إذن خاص للوصول إليه</label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                ✅ حفظ الدرس
                            </button>
                            <a href="manage-lessons.php" class="btn btn-secondary">
                                ❌ إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Math Symbols Toolbar -->
    <?php include '../../includes/math-toolbar.php'; ?>
    
    <style>
        .btn-math-toolbar {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .btn-math-toolbar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5);
        }
    </style>
</body>
</html>
