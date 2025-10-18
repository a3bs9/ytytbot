<?php
// ---------------------------------------------
// اعدادات البوت
// ---------------------------------------------
$botToken = '8089342552:AAFITpAd2kI_zTFnuTiRgz20Na3tyHI2z1o'; // <== تم التحديث
$apiUrl = 'https://api.telegram.org/bot' . $botToken . '/';
$youtubeApiKey = 'AIzaSyArghTT46ER-KxypZp0R6W0wjcUdYWe-zw'; 

// **الرجاء تعبئة هذه الحقول يدوياً من RapidAPI لاحقاً**
$rapidApiKey = 'YOUR_RAPIDAPI_KEY_HERE'; // <== ضع المفتاح هنا
$rapidApiHost = 'YOUR_RAPIDAPI_HOST_HERE'; // <== ضع المضيف هنا 
$conversionApiEndpoint = 'https://' . $rapidApiHost . '/dl'; // قد يتغير حسب الواجهة

// ---------------------------------------------
// الدوال المساعدة
// ---------------------------------------------

function sendMessage($chat_id, $text, $reply_to_message_id = null) {
    global $apiUrl;
    $parameters = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($reply_to_message_id) {
        $parameters['reply_to_message_id'] = $reply_to_message_id;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl . 'sendMessage');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function sendAudioFile($chat_id, $audio_url, $title, $caption, $reply_to_message_id = null) {
    global $apiUrl;
    
    $parameters = [
        'chat_id' => $chat_id,
        'audio' => $audio_url,
        'title' => $title,
        'caption' => $caption,
        'reply_to_message_id' => $reply_to_message_id
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl . 'sendAudio');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// ---------------------------------------------
// معالجة الرسالة وتحديد أمر البحث
// ---------------------------------------------
$update = json_decode(file_get_contents('php://input'), true);

if (!isset($update['message']['text'])) { exit; }

$messageText = trim($update['message']['text']);
$chatId = $update['message']['chat']['id'];
$messageId = $update['message']['message_id'];
$isGroup = ($update['message']['chat']['type'] == 'group' || $update['message']['chat']['type'] == 'supergroup');
$query = '';
$lowerCaseMessage = mb_strtolower($messageText, 'UTF-8');

if ($isGroup) {
    if (strpos($lowerCaseMessage, 'يوت') === 0) {
        $query = trim(substr($messageText, 6)); 
    } elseif (strpos($lowerCaseMessage, 'yt') === 0) {
        $query = trim(substr($messageText, 3)); 
    } else {
        exit;
    }
} else {
    $query = $messageText;
}

if (empty($query)) {
    sendMessage($chatId, "الرجاء كتابة أمر البحث بعد 'يوت' أو 'yt'.\n\nمثال: `يوت اسم الأغنية`", $messageId);
    exit;
}
// ---------------------------------------------
// 1. إرسال رسالة "جاري البحث..."
// ---------------------------------------------
sendMessage($chatId, "جاري البحث عن **" . htmlspecialchars($query) . "** وتحويلها إلى MP3، يرجى الانتظار...", $messageId);

// ---------------------------------------------
// 2. البحث في يوتيوب والحصول على أول نتيجة
// ---------------------------------------------

$searchUrl = "https://www.googleapis.com/youtube/v3/search?part=snippet&q=" . urlencode($query) . "&type=video&maxResults=1&key=" . $youtubeApiKey;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $searchUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$searchResult = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($searchResult, true);

if ($httpCode !== 200 || !isset($data['items'][0])) {
    sendMessage($chatId, "لم يتم العثور على نتائج لـ **" . htmlspecialchars($query) . "** أو حدث خطأ في الاتصال بواجهة يوتيوب.", $messageId);
    exit;
}

$videoTitle = $data['items'][0]['snippet']['title'];
$videoId = $data['items'][0]['id']['videoId'];
$videoUrl = "https://www.youtube.com/watch?v=" . $videoId; 


// ---------------------------------------------
// 3. التحويل إلى MP3 باستخدام RapidAPI (POST Request)
// ---------------------------------------------

// بيانات الطلب
$postData = json_encode([
    'url' => $videoUrl,
    'q' => 'mp3' 
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $conversionApiEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-RapidAPI-Key: ' . $rapidApiKey, 
    'X-RapidAPI-Host: ' . $rapidApiHost, 
    'Content-Type: application/json',
    'Content-Length: ' . strlen($postData)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 90); 
$api_response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$mp3Data = json_decode($api_response, true);
$mp3Link = null;

// هذا الجزء يجب تكييفه حسب طريقة استجابة الواجهة
if (isset($mp3Data['link'])) {
    $mp3Link = $mp3Data['link'];
} elseif (isset($mp3Data['file'])) {
    $mp3Link = $mp3Data['file'];
} 

// التحقق النهائي من الرابط
if (empty($mp3Link) || !filter_var($mp3Link, FILTER_VALIDATE_URL)) {
    $errorMessage = 'فشل التحويل باستخدام RapidAPI. تأكد من صحة المفتاح ورصيد الاستخدام.';
    sendMessage($chatId, "عفواً، فشل التحويل. الخطأ: " . $errorMessage . " رمز استجابة الواجهة: " . $httpCode, $messageId);
    exit;
}

// ---------------------------------------------
// 4. إرسال الصوتية
// ---------------------------------------------

$captionText = "**" . htmlspecialchars($videoTitle) . "**\n_تم التحويل عبر RapidAPI_"; 

$sendResult = sendAudioFile(
    $chatId, 
    $mp3Link, 
    $videoTitle, 
    $captionText, 
    $messageId
);

$response = json_decode($sendResult, true);
if (isset($response['ok']) && $response['ok'] === false) {
    sendMessage($chatId, "حدث خطأ غير متوقع أثناء إرسال الصوتية. قد يكون رابط MP3 منتهي الصلاحية أو غير متاح حاليًا.", $messageId);
}
?>
