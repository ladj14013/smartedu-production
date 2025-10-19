<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// التحقق من تسجيل الدخول والصلاحيات
require_auth();
if (!has_any_role(['etudiant', 'student'])) {
    header("Location: ../../dashboard/index.php");
    exit();
}

global $pdo;
$student_id = $_SESSION['user_id'];
$student_level_id = $_SESSION['level_id'] ?? null;
$student_stage_id = $_SESSION['stage_id'] ?? null;

// إذا لم يكن stage_id موجود في session، جلبه من قاعدة البيانات
if (!$student_stage_id) {
    try {
        $stmt = $pdo->prepare("SELECT stage_id FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $user_data = $stmt->fetch();
        if ($user_data) {
            $student_stage_id = $user_data['stage_id'];
            $_SESSION['stage_id'] = $student_stage_id;
        }
    } catch (PDOException $e) {
        // ignore
    }
}

// جلب المواد المتاحة للطالب (التي لديه ربط مع أساتذتها أو التي تحتوي على دروس عامة)
try {
    $query = "
        SELECT DISTINCT 
            s.*,
            (
                SELECT COUNT(*) 
                FROM lessons l 
                WHERE l.subject_id = s.id 
                AND l.status = 'approved'
                AND (
                    l.type = 'public'
                    OR (
                        l.type = 'private'
                        AND l.author_id IN (
                            SELECT teacher_id 
                            FROM student_teacher_links 
                            WHERE student_id = ? AND status = 'active'
                        )
                    )
                )
            ) as lessons_count,
            (
                SELECT GROUP_CONCAT(DISTINCT u.name)
                FROM student_teacher_links stl
                JOIN users u ON stl.teacher_id = u.id
                WHERE stl.student_id = ?
                AND stl.status = 'active'
                AND u.subject_id = s.id
            ) as linked_teachers
        FROM subjects s
        WHERE s.stage_id = ?
        AND (
            -- المواد التي لديها دروس عامة
            EXISTS (
                SELECT 1 FROM lessons l 
                WHERE l.subject_id = s.id 
                AND l.type = 'public'
                AND l.status = 'approved'
            )
            OR
            -- المواد التي لديها أساتذة مرتبطين بالطالب
            EXISTS (
                SELECT 1 FROM student_teacher_links stl
                JOIN users u ON stl.teacher_id = u.id
                WHERE stl.student_id = ?
                AND stl.status = 'active'
                AND u.subject_id = s.id
            )
        )
        ORDER BY s.name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$student_id, $student_id, $student_stage_id, $student_id]);
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $subjects = [];
    $error_message = "حدث خطأ في جلب المواد: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المواد المتاحة - SmartEdu Hub</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        @media (max-width: 968px) {
            .main-content {
                margin-right: 0 !important;
                padding: 20px;
            }
        }
    </style>
    <style>
        :root {
            --student-primary: #2196F3;
            --student-secondary: #1976D2;
            --student-light: #E3F2FD;
        }
        
        .main-content {
            margin-right: 280px;
            padding: 30px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--student-primary) 0%, var(--student-secondary) 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
            padding: 0 20px;
        }
        
        .subject-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .subject-header {
            background: var(--student-light);
            padding: 25px;
            border-bottom: 2px solid #E3F2FD;
        }
        
        .subject-title {
            font-size: 1.4rem;
            color: var(--student-primary);
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .subject-body {
            padding: 20px;
            flex: 1;
        }
        
        .info-grid {
            display: grid;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-icon {
            width: 35px;
            height: 35px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--student-primary);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .info-content strong {
            display: block;
            color: #333;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
        
        .info-content span {
            color: #666;
            font-size: 0.85rem;
        }
        
        .subject-footer {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-view {
            background: var(--student-primary);
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-view:hover {
            background: var(--student-secondary);
            transform: translateY(-2px);
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-public {
            background: #E8F5E9;
            color: #2E7D32;
        }
        
        .badge-private {
            background: #FFF3E0;
            color: #E65100;
        }
        
        .teachers-list {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
        }
        
        .teacher-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: var(--student-primary);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #666;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-right: 0;
                padding: 10px;
                width: 100%;
            }
            
            .subjects-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                padding: 0 10px;
            }
            
            .page-header {
                padding: 20px 15px;
                border-radius: 10px;
                margin-bottom: 20px;
            }

            .page-header h1 {
                font-size: 1.8rem;
                margin-bottom: 10px;
            }

            .page-header p {
                font-size: 1rem;
            }

            .subject-card {
                border-radius: 10px;
            }

            .subject-header {
                padding: 15px;
            }

            .subject-title {
                font-size: 1.2rem;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .subject-body {
                padding: 15px;
            }

            .info-grid {
                gap: 10px;
            }

            .info-item {
                padding: 8px;
            }

            .info-icon {
                width: 30px;
                height: 30px;
                font-size: 1rem;
            }

            .subject-footer {
                padding: 15px;
                flex-direction: column;
                gap: 10px;
            }

            .btn-view {
                width: 100%;
                text-align: center;
                padding: 12px;
                font-size: 1rem;
            }

            .badge {
                padding: 4px 10px;
                font-size: 0.75rem;
            }

            .teachers-list {
                margin-top: 8px;
                padding-top: 8px;
            }

            .teacher-item {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .subject-card {
                margin: 0 5px;
            }

            .empty-state {
                padding: 30px 20px;
            }

            .empty-icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>📚 المواد المتاحة</h1>
            <p>استعرض المواد التي يمكنك الوصول لدروسها</p>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($subjects)): ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3>لا توجد مواد متاحة حالياً</h3>
                <p>قم بربط حسابك مع أساتذتك للوصول إلى دروسهم</p>
            </div>
        <?php else: ?>
            <div class="subjects-grid">
                <?php foreach ($subjects as $subject): ?>
                    <div class="subject-card">
                        <div class="subject-header">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div class="subject-title">
                                    📚 <?php echo htmlspecialchars($subject['name']); ?>
                                </div>
                                <?php if ($subject['linked_teachers']): ?>
                                    <span class="badge badge-private">الأستاذ المرتبط</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="subject-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-icon">📖</div>
                                    <div class="info-content">
                                        <strong>عدد الدروس</strong>
                                        <span><?php echo $subject['lessons_count']; ?> درس</span>
                                    </div>
                                </div>
                                
                                <?php if ($subject['linked_teachers']): ?>
                                    <div class="teachers-list">
                                        <div style="color: #666; font-size: 0.9rem; margin-bottom: 5px;">
                                            👨‍🏫 الأساتذة المرتبطين:
                                        </div>
                                        <?php foreach (explode(',', $subject['linked_teachers']) as $teacher): ?>
                                            <div class="teacher-item">
                                                <span>👤</span>
                                                <?php echo htmlspecialchars($teacher); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="subject-footer">
                            <a href="lessons.php?subject_id=<?php echo $subject['id']; ?>" class="btn-view">
                                عرض الدروس ←
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>