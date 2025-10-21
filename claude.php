
<?php
// إعداد متغيرات البيئة (يمكنك تعيينها في النظام أو ملف .env)
$baseUrl = getenv('ANTHROPIC_BASE_URL') ?: 'https://agentrouter.org/';
$apiKey = getenv('ANTHROPIC_API_KEY') ?: 'sk-7XAK3l30k6iX5ZIvCmFFS2vXEGVC68jHCewusC76kjHxarLz';

// endpoint الصحيح حسب التوثيق (عدّل إذا وجدت endpoint مختلف)
$endpoint = 'api/v1/claude-sonnet-4.5/chat/completions';
$url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

$prompt = "اكتب كود JavaScript لتصميم قائمة منسدلة جذابة.";

$data = [
  "model" => "claude-sonnet-4.5",
  "messages" => [
    ["role" => "user", "content" => $prompt]
  ],
  "max_tokens" => 1024
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Authorization: Bearer $apiKey",
  "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    echo "❌ خطأ في الاتصال: " . curl_error($ch);
    curl_close($ch);
    exit;
}
curl_close($ch);

$result = json_decode($response, true);
if ($httpCode !== 200 || !isset($result['choices'][0]['message']['content'])) {
    echo "❌ حدث خطأ في الاستجابة من API.<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    exit;
}
echo $result['choices'][0]['message']['content'];
?>