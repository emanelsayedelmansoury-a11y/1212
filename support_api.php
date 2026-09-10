<?php
/**
 * شات الدعم الفني - API
 * ================================================
 * نفس فكرة تخزين منتدى الطلاب (forum_api.php): تخزين في ملف JSON بدون قاعدة بيانات.
 * كل محادثة مرتبطة بالبريد الإلكتروني الخاص بصاحبها (conv_id).
 * حساب الأدمن المحدد أدناه فقط هو من يقدر يشوف كل المحادثات ويرد عليها.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ====================================================
// ⚙️ إعدادات الدعم - عدّل هنا فقط عند الحاجة
// ====================================================
define('SUPPORT_ADMIN_EMAIL', 'epicmohammed11@gmail.com'); // ← صاحب هذا البريد فقط يرى كل المحادثات
define('SUPPORT_DATA_FILE', __DIR__ . '/support_messages.json');
define('MAX_TEXT_LENGTH', 2000);
define('MAX_MESSAGES_PER_CONV', 500);
// ====================================================

ensureStorageReady();

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'list') {
    handleList($input);
    exit;
}

if ($action === 'list_conversations') {
    handleListConversations($input);
    exit;
}

if ($action === 'send') {
    handleSend($input);
    exit;
}

echo json_encode(['status' => false, 'message' => 'طلب غير معرّف']);
exit;

// =====================================================
// تخزين
// =====================================================

function ensureStorageReady() {
    if (!file_exists(SUPPORT_DATA_FILE)) {
        @file_put_contents(SUPPORT_DATA_FILE, '[]');
    }
}

function readMessages() {
    $raw  = @file_get_contents(SUPPORT_DATA_FILE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function writeMessages($messages) {
    $fp = fopen(SUPPORT_DATA_FILE, 'c+');
    if (!$fp) return false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return true;
}

function isAdminEmail($email) {
    return $email && strtolower(trim($email)) === strtolower(SUPPORT_ADMIN_EMAIL);
}

function convIdFromEmail($email) {
    return strtolower(trim($email));
}

// =====================================================
// المعالجات
// =====================================================

// جلب رسائل محادثة واحدة (يستخدمها الطالب لرؤية محادثته، ويستخدمها الأدمن لفتح محادثة معينة)
function handleList($input) {
    $requesterEmail = filter_var($input['requester_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $convEmail       = filter_var($input['conv_email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$requesterEmail || !$convEmail) {
        echo json_encode(['status' => false, 'message' => 'بيانات غير مكتملة']);
        return;
    }

    // لازم يكون صاحب المحادثة نفسه، أو حساب الأدمن
    if (!isAdminEmail($requesterEmail) && convIdFromEmail($requesterEmail) !== convIdFromEmail($convEmail)) {
        echo json_encode(['status' => false, 'message' => 'غير مصرح لك برؤية هذه المحادثة']);
        return;
    }

    $convId = convIdFromEmail($convEmail);
    $all = readMessages();
    $thread = array_values(array_filter($all, function ($m) use ($convId) {
        return isset($m['conv_id']) && $m['conv_id'] === $convId;
    }));
    usort($thread, function ($a, $b) { return $a['id'] <=> $b['id']; });
    $thread = array_slice($thread, -MAX_MESSAGES_PER_CONV);

    echo json_encode(['status' => true, 'messages' => $thread]);
}

// قائمة كل المحادثات (للأدمن فقط)
function handleListConversations($input) {
    $requesterEmail = filter_var($input['requester_email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!isAdminEmail($requesterEmail)) {
        echo json_encode(['status' => false, 'message' => 'غير مصرح لك']);
        return;
    }

    $all = readMessages();
    $convs = []; // conv_id => آخر رسالة + بيانات

    foreach ($all as $m) {
        if (!isset($m['conv_id'])) continue;
        $cid = $m['conv_id'];
        if (!isset($convs[$cid]) || $m['id'] > $convs[$cid]['last_id']) {
            $convs[$cid] = [
                'conv_id'      => $cid,
                'name'         => $m['user_name'] ?? $cid,
                'email'        => $m['user_email'] ?? $cid,
                'last_id'      => $m['id'],
                'last_text'    => $m['text'],
                'last_sender'  => $m['sender'],
                'last_time'    => $m['timestamp'],
            ];
        }
    }

    $list = array_values($convs);
    usort($list, function ($a, $b) { return $b['last_id'] <=> $a['last_id']; });

    echo json_encode(['status' => true, 'conversations' => $list]);
}

// إرسال رسالة (من طالب في محادثته، أو من الأدمن في أي محادثة)
function handleSend($input) {
    $senderEmail = filter_var($input['sender_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $senderName  = trim(strip_tags($input['sender_name'] ?? ''));
    $convEmail   = filter_var($input['conv_email'] ?? '', FILTER_VALIDATE_EMAIL); // صاحب المحادثة
    $text        = trim($input['text'] ?? '');

    if (!$senderEmail || !$senderName || !$convEmail) {
        echo json_encode(['status' => false, 'message' => 'يجب إدخال الاسم والبريد الإلكتروني']);
        return;
    }

    $text = mb_substr($text, 0, MAX_TEXT_LENGTH);
    $senderName = mb_substr($senderName, 0, 80);

    if ($text === '') {
        echo json_encode(['status' => false, 'message' => 'اكتب رسالة قبل الإرسال']);
        return;
    }

    $isAdmin = isAdminEmail($senderEmail);

    // طالب عادي لا يقدر يرسل إلا في محادثته هو
    if (!$isAdmin && convIdFromEmail($senderEmail) !== convIdFromEmail($convEmail)) {
        echo json_encode(['status' => false, 'message' => 'غير مصرح']);
        return;
    }

    $messages = readMessages();
    $newId = 1;
    foreach ($messages as $m) {
        if (isset($m['id']) && $m['id'] >= $newId) $newId = $m['id'] + 1;
    }

    $message = [
        'id'         => $newId,
        'conv_id'    => convIdFromEmail($convEmail),
        'user_name'  => $isAdmin ? ($input['conv_name'] ?? $convEmail) : $senderName,
        'user_email' => $convEmail,
        'sender'     => $isAdmin ? 'admin' : 'user',
        'name'       => $senderName,
        'text'       => $text,
        'timestamp'  => time(),
    ];

    $messages[] = $message;
    writeMessages($messages);

    echo json_encode(['status' => true, 'data' => $message]);
}
