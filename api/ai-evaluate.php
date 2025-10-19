<?php
/**
 * AI Evaluation API
 * يوفر تقييماً ذكياً لإجابات الطلاب باستخدام الذكاء الاصطناعي
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مصرح']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$exercise_id = $input['exercise_id'] ?? null;
$student_answer = $input['answer'] ?? null;

if (!$exercise_id || !$student_answer) {
    http_response_code(400);
    echo json_encode(['error' => 'بيانات غير مكتملة']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// جلب التمرين والإجابة النموذجية
$query = "SELECT * FROM exercises WHERE id = :exercise_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':exercise_id', $exercise_id);
$stmt->execute();
$exercise = $stmt->fetch();

if (!$exercise) {
    http_response_code(404);
    echo json_encode(['error' => 'التمرين غير موجود']);
    exit();
}

/**
 * دالة التقييم بالذكاء الاصطناعي
 * ملاحظة: هذه نسخة تجريبية - يجب استبدالها بتكامل حقيقي مع OpenAI أو Gemini
 */
function evaluateWithAI($question, $model_answer, $student_answer) {
    // TODO: تكامل مع OpenAI API أو Google Gemini API
    
    // مثال على طلب OpenAI (يتطلب API key):
    /*
    $api_key = getenv('OPENAI_API_KEY');
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $prompt = "أنت معلم خبير. قيّم إجابة الطالب التالية:\n\n";
    $prompt .= "السؤال: {$question}\n";
    $prompt .= "الإجابة النموذجية: {$model_answer}\n";
    $prompt .= "إجابة الطالب: {$student_answer}\n\n";
    $prompt .= "أعط تقييماً شاملاً يتضمن:\n";
    $prompt .= "1. درجة من 0 إلى 100\n";
    $prompt .= "2. نقاط القوة\n";
    $prompt .= "3. نقاط التحسين\n";
    $prompt .= "4. ملاحظات بناءة\n";
    
    $data = [
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => 'أنت معلم خبير يقيم إجابات الطلاب بدقة وموضوعية.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? null;
    */
    
    // تقييم تجريبي بسيط (يجب استبداله)
    $similarity = similar_text(
        strtolower($model_answer), 
        strtolower($student_answer), 
        $percent
    );
    
    $score = round($percent);
    
    $feedback = "تقييم تلقائي:\n\n";
    
    if ($score >= 90) {
        $feedback .= "✅ ممتاز! إجابتك صحيحة وشاملة.\n";
        $feedback .= "• أظهرت فهماً عميقاً للموضوع\n";
        $feedback .= "• التعبير واضح ومنظم\n";
    } elseif ($score >= 70) {
        $feedback .= "✅ جيد! إجابتك صحيحة إلى حد كبير.\n";
        $feedback .= "• معظم النقاط الرئيسية مغطاة\n";
        $feedback .= "• يمكن إضافة بعض التفاصيل للإجابة الكاملة\n";
    } elseif ($score >= 50) {
        $feedback .= "⚠️ مقبول. إجابتك تحتاج إلى تحسين.\n";
        $feedback .= "• بعض النقاط الرئيسية مفقودة\n";
        $feedback .= "• راجع المادة مرة أخرى وحاول إضافة المزيد من التفاصيل\n";
    } else {
        $feedback .= "❌ يحتاج إلى مراجعة. الإجابة غير كاملة.\n";
        $feedback .= "• معظم النقاط الرئيسية مفقودة\n";
        $feedback .= "• يُنصح بمراجعة الدرس والإجابة النموذجية\n";
    }
    
    $feedback .= "\n💡 نصيحة: راجع الإجابة النموذجية وقارنها بإجابتك لتحديد نقاط التحسين.";
    
    return [
        'score' => $score,
        'feedback' => $feedback
    ];
}

// تقييم الإجابة
$evaluation = evaluateWithAI(
    $exercise['question'],
    $exercise['model_answer'],
    $student_answer
);

// حفظ التقييم في قاعدة البيانات
$student_id = $_SESSION['user_id'];
$query = "UPDATE student_answers 
          SET ai_feedback = :feedback, score = :score 
          WHERE student_id = :student_id AND exercise_id = :exercise_id 
          ORDER BY submitted_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':feedback', $evaluation['feedback']);
$stmt->bindParam(':score', $evaluation['score']);
$stmt->bindParam(':student_id', $student_id);
$stmt->bindParam(':exercise_id', $exercise_id);
$stmt->execute();

// إرجاع النتيجة
echo json_encode([
    'success' => true,
    'score' => $evaluation['score'],
    'feedback' => $evaluation['feedback']
]);
