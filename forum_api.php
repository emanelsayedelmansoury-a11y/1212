<?php
/**
 * منتدى الطلاب - API الرسائل (نصوص + صور + فيديوهات)
 * ================================================
 * تخزين الرسائل في ملف JSON، والمرفقات في مجلد uploads/forum
 * لا يحتاج قاعدة بيانات.
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
// ⚙️ إعدادات المنتدى - عدّل هنا فقط عند الحاجة
// ====================================================
define('FORUM_DATA_FILE', __DIR__ . '/forum_messages.json');
define('FORUM_UPLOAD_DIR', __DIR__ . '/uploads/forum');
define('FORUM_UPLOAD_URL', 'uploads/forum'); // مسار نسبي يُستخدم من المتصفح لعرض الملفات
define('MAX_MESSAGES_RETURNED', 150);        // أقصى عدد رسائل تُرجَع دفعة واحدة
define('MAX_TEXT_LENGTH', 2000);             // أقصى طول للرسالة النصية (حرف)
define('MAX_IMAGE_MB', 8);                   // أقصى حجم للصورة
define('MAX_VIDEO_MB', 60);                  // أقصى حجم للفيديو
// ====================================================

$ALLOWED_IMAGE_EXT  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$ALLOWED_IMAGE_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$ALLOWED_VIDEO_EXT  = ['mp4', 'webm', 'mov'];
$ALLOWED_VIDEO_MIME = ['video/mp4', 'video/webm', 'video/quicktime'];

ensureStorageReady();

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    handleList();
    exit;
}

if ($action === 'post') {
    handlePost($ALLOWED_IMAGE_EXT, $ALLOWED_IMAGE_MIME, $ALLOWED_VIDEO_EXT, $ALLOWED_VIDEO_MIME);
    exit;
}

if ($action === 'delete') {
    handleDelete();
    exit;
}

echo json_encode(['status' => false, 'message' => 'طلب غير معرّف']);
exit;

// =====================================================
// دوال التخزين
// =====================================================

function ensureStorageReady() {
    if (!is_dir(FORUM_UPLOAD_DIR)) {
        @mkdir(FORUM_UPLOAD_DIR, 0755, true);
    }
    // طبقة حماية إضافية: منع تنفيذ أي ملف PHP داخل مجلد الرفع حتى لو تم رفعه بطريقة ما
    $htaccess = FORUM_UPLOAD_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents(
            $htaccess,
            "php_flag engine off\nAddHandler none .php .phtml .php3 .php4 .php5 .pht\nOptions -ExecCGI -Indexes\n"
        );
    }
    if (!file_exists(FORUM_DATA_FILE)) {
        @file_put_contents(FORUM_DATA_FILE, '[]');
    }
}

function readMessages() {
    $raw  = @file_get_contents(FORUM_DATA_FILE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function writeMessages($messages) {
    $fp = fopen(FORUM_DATA_FILE, 'c+');
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

// =====================================================
// المعالجات (Handlers)
// =====================================================

function handleList() {
    $messages = readMessages();
    usort($messages, function ($a, $b) { return $b['id'] <=> $a['id']; }); // الأحدث أولاً
    $messages = array_slice($messages, 0, MAX_MESSAGES_RETURNED);
    echo json_encode(['status' => true, 'messages' => $messages]);
}

function handlePost($allowedImgExt, $allowedImgMime, $allowedVidExt, $allowedVidMime) {
    $name  = trim(strip_tags($_POST['name'] ?? ''));
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $text  = trim($_POST['text'] ?? '');

    // لازم يكون المستخدم مسجل دخول (الاسم والبريد يصلان من حساب الطالب المسجل بالفعل)
    if (!$email || !$name) {
        echo json_encode(['status' => false, 'message' => 'يجب تسجيل الدخول أولاً لإرسال رسالة في المنتدى']);
        return;
    }

    $text = mb_substr($text, 0, MAX_TEXT_LENGTH);
    $name = mb_substr($name, 0, 80);

    $imageUrl = null;
    $videoUrl = null;

    // ─── الصورة (اختيارية) ───
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $result = saveUploadedFile($_FILES['image'], $allowedImgExt, $allowedImgMime, MAX_IMAGE_MB, 'img');
        if ($result['status'] === false) {
            echo json_encode($result);
            return;
        }
        $imageUrl = $result['url'];
    }

    // ─── الفيديو (اختياري) ───
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $result = saveUploadedFile($_FILES['video'], $allowedVidExt, $allowedVidMime, MAX_VIDEO_MB, 'vid');
        if ($result['status'] === false) {
            echo json_encode($result);
            return;
        }
        $videoUrl = $result['url'];
    }

    if ($text === '' && !$imageUrl && !$videoUrl) {
        echo json_encode(['status' => false, 'message' => 'اكتب رسالة أو أرفق صورة/فيديو قبل الإرسال']);
        return;
    }

    $messages = readMessages();
    $newId = 1;
    foreach ($messages as $m) {
        if (isset($m['id']) && $m['id'] >= $newId) $newId = $m['id'] + 1;
    }

    $message = [
        'id'        => $newId,
        'name'      => $name,
        'email'     => $email,
        'text'      => $text,
        'image_url' => $imageUrl,
        'video_url' => $videoUrl,
        'timestamp' => time(),
    ];

    $messages[] = $message;
    writeMessages($messages);

    echo json_encode(['status' => true, 'message' => 'تم نشر الرسالة بنجاح', 'data' => $message]);
}

function handleDelete() {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id    = intval($input['id'] ?? 0);
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$id || !$email) {
        echo json_encode(['status' => false, 'message' => 'بيانات غير مكتملة']);
        return;
    }

    $messages = readMessages();
    $found = null;
    foreach ($messages as $m) {
        if (isset($m['id']) && $m['id'] === $id) { $found = $m; break; }
    }

    if (!$found) {
        echo json_encode(['status' => false, 'message' => 'الرسالة غير موجودة (ربما تم حذفها بالفعل)']);
        return;
    }

    if (strtolower($found['email']) !== strtolower($email)) {
        echo json_encode(['status' => false, 'message' => 'لا يمكنك حذف رسالة شخص آخر']);
        return;
    }

    // حذف الملفات المرفقة من السيرفر إن وُجدت
    foreach (['image_url', 'video_url'] as $key) {
        if (!empty($found[$key])) {
            $path = __DIR__ . '/' . $found[$key];
            if (is_file($path)) @unlink($path);
        }
    }

    $messages = array_values(array_filter($messages, function ($m) use ($id) {
        return $m['id'] !== $id;
    }));
    writeMessages($messages);

    echo json_encode(['status' => true, 'message' => 'تم حذف الرسالة']);
}

// =====================================================
// مساعد: حفظ ملف مرفوع بأمان بعد التحقق منه
// =====================================================
function saveUploadedFile($file, $allowedExt, $allowedMime, $maxMb, $prefix) {
    if ($file['size'] > $maxMb * 1024 * 1024) {
        return ['status' => false, 'message' => "حجم الملف أكبر من الحد المسموح ({$maxMb} ميجابايت)"];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['status' => false, 'message' => 'صيغة الملف غير مدعومة'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMime, true)) {
        return ['status' => false, 'message' => 'نوع الملف الحقيقي لا يطابق الصيغة المسموح بها'];
    }

    $safeName    = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = FORUM_UPLOAD_DIR . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['status' => false, 'message' => 'تعذر حفظ الملف على السيرفر'];
    }

    return ['status' => true, 'url' => FORUM_UPLOAD_URL . '/' . $safeName];
}
