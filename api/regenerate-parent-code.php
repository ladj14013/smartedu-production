<?php
session_start();
header('Content-Type: application/json');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'parent') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول كولي أمر للقيام بهذا الإجراء'
    ]);
    exit;
}

require_once '../config/database.php';

try {
    // التحقق من طريقة الطلب
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة طلب غير صحيحة');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // توليد كود جديد فريد
    $new_code = null;
    $max_attempts = 100;
    $attempt = 0;
    
    while ($attempt < $max_attempts) {
        // توليد رقم عشوائي من 6 أرقام
        $random_number = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $new_code = 'PAR' . $random_number;
        
        // التحقق من عدم وجود الكود
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE parent_code = ? AND id != ?");
        $check_stmt->execute([$new_code, $user_id]);
        
        if (!$check_stmt->fetch()) {
            break; // الكود فريد
        }
        
        $attempt++;
    }
    
    if ($attempt >= $max_attempts) {
        throw new Exception('فشل في توليد كود فريد. حاول مرة أخرى.');
    }
    
    // بدء المعاملة
    $pdo->beginTransaction();
    
    try {
        // حذف جميع الارتباطات الحالية (إذا كان الجدول موجوداً)
        try {
            $delete_stmt = $pdo->prepare("DELETE FROM parent_children WHERE parent_id = ?");
            $delete_stmt->execute([$user_id]);
        } catch (PDOException $e) {
            // الجدول غير موجود - تجاهل الخطأ
        }
        
        // تحديث الكود
        $update_stmt = $pdo->prepare("UPDATE users SET parent_code = ? WHERE id = ?");
        $result = $update_stmt->execute([$new_code, $user_id]);
        
        if (!$result) {
            throw new Exception('فشل في تحديث الكود');
        }
        
        // تأكيد المعاملة
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'new_code' => $new_code,
            'message' => 'تم إعادة توليد الكود بنجاح'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
