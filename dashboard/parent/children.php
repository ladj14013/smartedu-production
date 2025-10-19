<?php
/**
 * Parent Dashboard - Children List
 * لوحة تحكم ولي الأمر - قائمة الأبناء
 */

session_start();

// تفعيل عرض الأخطاء للتشخيص
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole(['parent']);

$user_id = $_SESSION['user_id'];

// جلب معلومات ولي الأمر
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $parent = $stmt->fetch();
    
    if (!$parent) {
        die('خطأ: لم يتم العثور على بيانات ولي الأمر');
    }
} catch (PDOException $e) {
    die('خطأ في قاعدة البيانات: ' . $e->getMessage());
}

// جلب قائمة الأبناء من جدول parent_children
try {
    $stmt = $pdo->prepare("
        SELECT u.*, pc.relation_type, pc.is_primary
        FROM parent_children pc
        JOIN users u ON pc.child_id = u.id
        WHERE pc.parent_id = ?
        ORDER BY pc.is_primary DESC, u.name
    ");
    $stmt->execute([$user_id]);
    $children = $stmt->fetchAll();
} catch (PDOException $e) {
    // إذا كان الجدول غير موجود
    if (strpos($e->getMessage(), 'parent_children') !== false) {
        die('<div style="background: white; padding: 30px; margin: 30px; border-radius: 10px;">
            <h2 style="color: red;">خطأ: جدول parent_children غير موجود</h2>
            <p>يجب تنفيذ سكربت SQL أولاً لإنشاء الجدول</p>
            <p>الملف: <code>dashboard/parent/add_parent_child_relation.sql</code></p>
            </div>');
    }
    die('خطأ في قاعدة البيانات: ' . $e->getMessage());
}

// حساب الإحصائيات لكل ابن
$children_stats = [];
foreach ($children as $child) {
    $child_id = $child['id'];
    
    // إحصائيات الدروس والتمارين
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.lesson_id) as completed_lessons,
               COUNT(DISTINCT sa.exercise_id) as exercises_count,
               AVG(sa.score) as avg_score,
               MAX(sa.submitted_at) as last_activity
        FROM student_answers sa
        JOIN exercises e ON sa.exercise_id = e.id
        WHERE sa.student_id = ? AND sa.score IS NOT NULL
    ");
    $stmt->execute([$child_id]);
    $stats = $stmt->fetch();
    
    // جلب المرحلة والمستوى
    $stage_name = 'غير محدد';
    $level_name = '';
    
    if ($child['stage_id']) {
        $stmt = $pdo->prepare("SELECT name FROM stages WHERE id = ?");
        $stmt->execute([$child['stage_id']]);
        $stage = $stmt->fetch();
        $stage_name = $stage['name'] ?? 'غير محدد';
    }
    
    if ($child['level_id']) {
        $stmt = $pdo->prepare("SELECT name FROM levels WHERE id = ?");
        $stmt->execute([$child['level_id']]);
        $level = $stmt->fetch();
        $level_name = $level['name'] ?? '';
    }
    
    $children_stats[$child['id']] = [
        'completed_lessons' => $stats['completed_lessons'] ?? 0,
        'exercises_count' => $stats['exercises_count'] ?? 0,
        'avg_score' => round($stats['avg_score'] ?? 0),
        'last_activity' => $stats['last_activity'] ?? null,
        'stage_name' => $stage_name,
        'level_name' => $level_name
    ];
}

$total_children = count($children);

// رسالة في حالة عدم وجود أبناء
if ($total_children === 0) {
    $no_children_message = true;
} else {
    $no_children_message = false;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أبنائي - SmartEdu</title>
    <link rel="stylesheet" href="../../assets/css/rtl-parent.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.3);
        }
        
        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .page-header p {
            opacity: 0.95;
            font-size: 1.1rem;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-label {
            font-weight: 600;
            color: #1f2937;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 180px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .children-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }
        
        th {
            padding: 18px 15px;
            text-align: right;
            font-weight: 600;
            font-size: 1rem;
        }
        
        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        td {
            padding: 20px 15px;
            color: #1f2937;
        }
        
        .student-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .student-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .student-info h4 {
            font-size: 1.1rem;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .student-info p {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .score-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .score-excellent {
            background: #d4edda;
            color: #155724;
        }
        
        .score-good {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .score-average {
            background: #fff3cd;
            color: #856404;
        }
        
        .score-poor {
            background: #f8d7da;
            color: #721c24;
        }
        
        .stat-number {
            font-weight: 700;
            font-size: 1.1rem;
            color: #8b5cf6;
        }
        
        .btn-view {
            padding: 10px 20px;
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }
        
        .last-activity {
            font-size: 0.85rem;
            color: #9ca3af;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        @media (max-width: 968px) {
            .children-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1>👨‍👩‍👧‍👦 أبنائي</h1>
                <p>متابعة شاملة لأداء جميع الأبناء (<?php echo $total_children; ?>)</p>
                
                <a href="link-child.php" style="display: inline-block; margin-top: 15px; padding: 12px 25px; background: white; color: #8b5cf6; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    ➕ إضافة ابن جديد
                </a>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <span class="filter-label">🔍 تصفية حسب:</span>
                <select class="filter-select" id="filterStage">
                    <option value="">جميع المراحل</option>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM stages ORDER BY id");
                    while ($stage = $stmt->fetch()) {
                        echo '<option value="' . $stage['id'] . '">' . htmlspecialchars($stage['name']) . '</option>';
                    }
                    ?>
                </select>
                
                <select class="filter-select" id="filterLevel">
                    <option value="">جميع المستويات</option>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM levels ORDER BY id");
                    while ($level = $stmt->fetch()) {
                        echo '<option value="' . $level['id'] . '">' . htmlspecialchars($level['name']) . '</option>';
                    }
                    ?>
                </select>
                
                <select class="filter-select" id="filterPerformance">
                    <option value="">كل مستويات الأداء</option>
                    <option value="excellent">ممتاز (80% فأكثر)</option>
                    <option value="good">جيد (60-79%)</option>
                    <option value="average">متوسط (40-59%)</option>
                    <option value="poor">ضعيف (أقل من 40%)</option>
                </select>
            </div>
            
            <!-- Children Table -->
            <?php if (empty($children)): ?>
                <div class="children-table">
                    <div class="empty-state">
                        <div class="empty-state-icon">👨‍👩‍👧‍👦</div>
                        <h3>لم يتم إضافة أبناء بعد</h3>
                        <p style="font-size: 0.95rem;">تواصل مع الإدارة لإضافة حسابات الأبناء</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="children-table">
                    <table>
                        <thead>
                            <tr>
                                <th>الطالب</th>
                                <th>المرحلة/المستوى</th>
                                <th>الدروس المكتملة</th>
                                <th>التمارين</th>
                                <th>متوسط الأداء</th>
                                <th>آخر نشاط</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($children as $child): ?>
                                <?php
                                $child_id = $child['id'];
                                $stats = $children_stats[$child_id];
                                $performance = $stats['avg_score'];
                                
                                // تحديد تصنيف الأداء
                                $score_class = 'score-poor';
                                if ($performance >= 80) $score_class = 'score-excellent';
                                elseif ($performance >= 60) $score_class = 'score-good';
                                elseif ($performance >= 40) $score_class = 'score-average';
                                ?>
                                <tr data-stage="<?php echo $child['stage_id']; ?>" 
                                    data-level="<?php echo $child['level_id']; ?>" 
                                    data-performance="<?php 
                                        echo $performance >= 80 ? 'excellent' : 
                                            ($performance >= 60 ? 'good' : 
                                            ($performance >= 40 ? 'average' : 'poor')); 
                                    ?>">
                                    <td>
                                        <div class="student-cell">
                                            <div class="student-avatar">
                                                <?php echo mb_substr($child['name'], 0, 1); ?>
                                            </div>
                                            <div class="student-info">
                                                <h4><?php echo htmlspecialchars($child['name']); ?></h4>
                                                <p><?php echo htmlspecialchars($child['email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($stats['stage_name']); ?></strong>
                                        <?php if ($stats['level_name']): ?>
                                            <br><span style="color: #6b7280; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($stats['level_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="stat-number"><?php echo $stats['completed_lessons']; ?></span>
                                        <span style="color: #6b7280;"> درس</span>
                                    </td>
                                    <td>
                                        <span class="stat-number"><?php echo $stats['exercises_count']; ?></span>
                                        <span style="color: #6b7280;"> تمرين</span>
                                    </td>
                                    <td>
                                        <span class="score-badge <?php echo $score_class; ?>">
                                            <?php echo round($performance, 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($stats['last_activity']): ?>
                                            <span class="last-activity">
                                                <?php 
                                                $date = new DateTime($stats['last_activity']);
                                                echo $date->format('Y-m-d');
                                                ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="last-activity">لا يوجد نشاط</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="child-details.php?id=<?php echo $child_id; ?>" class="btn-view">
                                            عرض التفاصيل
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterStage = document.getElementById('filterStage');
            const filterLevel = document.getElementById('filterLevel');
            const filterPerformance = document.getElementById('filterPerformance');
            const rows = document.querySelectorAll('tbody tr');
            
            function applyFilters() {
                const stageValue = filterStage.value;
                const levelValue = filterLevel.value;
                const performanceValue = filterPerformance.value;
                
                rows.forEach(row => {
                    const rowStage = row.getAttribute('data-stage');
                    const rowLevel = row.getAttribute('data-level');
                    const rowPerformance = row.getAttribute('data-performance');
                    
                    let showRow = true;
                    
                    if (stageValue && rowStage !== stageValue) showRow = false;
                    if (levelValue && rowLevel !== levelValue) showRow = false;
                    if (performanceValue && rowPerformance !== performanceValue) showRow = false;
                    
                    row.style.display = showRow ? '' : 'none';
                });
            }
            
            filterStage.addEventListener('change', applyFilters);
            filterLevel.addEventListener('change', applyFilters);
            filterPerformance.addEventListener('change', applyFilters);
        });
    </script>
</body>
</html>
