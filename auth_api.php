<?php
/**
 * نظام إرسال رمز التحقق (OTP) عبر Gmail SMTP
 * ================================================
 * ضع بيانات Gmail الخاصة بك في القسم أدناه فقط
 */

// ====================================================
// ⚙️ إعدادات Gmail - عدّل هنا فقط
// ====================================================
define('GMAIL_ADDRESS',  'your_email@gmail.com');     // ← بريدك على Gmail
define('GMAIL_APP_PASS', 'xxxx xxxx xxxx xxxx');      // ← كلمة مرور التطبيق (App Password)
define('SENDER_NAME',    'كورس مونتاج AI');           // ← الاسم الذي يظهر في الإيميل
// ====================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// -------------------------------------------------------
// إرسال رمز OTP عبر Gmail
// -------------------------------------------------------
if ($action === 'send_otp') {
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $name  = htmlspecialchars($input['name'] ?? 'عزيزي المشترك');

    if (!$email) {
        echo json_encode(['status' => false, 'message' => 'البريد الإلكتروني غير صحيح']);
        exit;
    }

    $otp = strval(rand(100000, 999999));

    session_start();
    $_SESSION['otp_' . md5($email)] = [
        'code'    => $otp,
        'expires' => time() + 600,
        'email'   => $email
    ];

    $htmlBody = buildEmailTemplate($name, $otp);
    $sent = sendViaSocket($email, $name, $otp, $htmlBody);

    $logLine = date('[Y-m-d H:i:s]') . " - OTP for {$email}: {$otp} | Sent: " . ($sent ? 'YES' : 'NO') . "\n";
    @file_put_contents(__DIR__ . '/otp_log.txt', $logLine, FILE_APPEND);

    if ($sent) {
        echo json_encode([
            'status'  => true,
            'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني بنجاح'
        ]);
    } else {
        echo json_encode([
            'status'  => true,
            'message' => 'تم إرسال الرمز (وضع التطوير)',
            'otp'     => $otp
        ]);
    }
    exit;
}

// -------------------------------------------------------
// التحقق من رمز OTP
// -------------------------------------------------------
if ($action === 'verify_otp') {
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $code  = trim($input['code'] ?? '');

    session_start();
    $key = 'otp_' . md5($email);

    if (!isset($_SESSION[$key])) {
        echo json_encode(['status' => false, 'message' => 'لا يوجد رمز تحقق نشط، يرجى إعادة الإرسال']);
        exit;
    }

    $stored = $_SESSION[$key];

    if (time() > $stored['expires']) {
        unset($_SESSION[$key]);
        echo json_encode(['status' => false, 'message' => 'انتهت صلاحية رمز التحقق، يرجى إعادة الإرسال']);
        exit;
    }

    if ($code !== $stored['code']) {
        echo json_encode(['status' => false, 'message' => 'رمز التحقق غير صحيح']);
        exit;
    }

    unset($_SESSION[$key]);
    echo json_encode(['status' => true, 'message' => 'تم التحقق بنجاح']);
    exit;
}

echo json_encode(['status' => false, 'message' => 'طلب غير معرّف']);

function buildEmailTemplate($name, $otp) {
    return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; background: #f0f7ff; margin: 0; padding: 20px; }
  .container { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 20px;
               padding: 36px 32px; border: 2px solid #bae6fd; }
  .logo { text-align: center; margin-bottom: 24px; }
  .otp-box { background: linear-gradient(135deg, #e0f2fe, #f0f9ff); border: 2px dashed #38bdf8;
             border-radius: 16px; padding: 20px; text-align: center; margin: 24px 0; }
  .otp-code { font-size: 2.8rem; font-weight: 900; letter-spacing: 10px;
              color: #0066FF; direction: ltr; display: block; }
  .note { font-size: 0.85rem; color: #64748b; margin-top: 8px; }
  h2 { color: #0f172a; margin-bottom: 8px; }
  p { color: #475569; line-height: 1.8; }
  .footer { margin-top: 28px; text-align: center; font-size: 0.8rem; color: #94a3b8; }
</style>
</head>
<body>
<div class="container">
  <div class="logo"><strong style="font-size:1.4rem;color:#0066FF;">✨ كورس مونتاج AI</strong></div>
  <h2>مرحباً {$name}! 👋</h2>
  <p>شكراً لتسجيلك في <strong>كورس المونتاج بالذكاء الاصطناعي</strong>.<br>
  استخدم الرمز التالي لتأكيد بريدك الإلكتروني وتفعيل حسابك:</p>
  <div class="otp-box">
    <span class="otp-code">{$otp}</span>
    <p class="note">⏱️ هذا الرمز صالح لمدة <strong>10 دقائق</strong> فقط</p>
  </div>
  <p>إذا لم تقم بطلب هذا الرمز، يرجى تجاهل هذه الرسالة.</p>
  <div class="footer">© كورس مونتاج AI – جميع الحقوق محفوظة</div>
</div>
</body>
</html>
HTML;
}

function sendViaSocket($toEmail, $toName, $otp, $htmlBody) {
    $smtpHost = 'ssl://smtp.gmail.com';
    $smtpPort = 465;
    $from     = GMAIL_ADDRESS;
    $fromName = SENDER_NAME;
    $pass     = GMAIL_APP_PASS;
    $subject  = '=?UTF-8?B?' . base64_encode('رمز التحقق من حسابك - كورس مونتاج AI') . '?=';
    $boundary = md5(uniqid());

    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
    $headers .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $plainText = "مرحباً {$toName}،\n\nرمز التحقق: {$otp}\n\nصالح 10 دقائق.";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($plainText)) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    try {
        $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 10);
        if (!$socket) return false;

        $read = function() use ($socket) { return fgets($socket, 515); };
        $send = function($cmd) use ($socket) { fputs($socket, $cmd . "\r\n"); };

        $read();
        $send("EHLO localhost");
        while (($line = $read()) && substr($line, 3, 1) === '-');

        $send("AUTH LOGIN");
        $read();
        $send(base64_encode($from));
        $read();
        $send(base64_encode($pass));
        $r = $read();
        if (strpos($r, '235') === false) { fclose($socket); return false; }

        $send("MAIL FROM:<{$from}>");
        $read();
        $send("RCPT TO:<{$toEmail}>");
        $read();
        $send("DATA");
        $read();
        $send($headers . "\r\n" . $body . "\r\n.");
        $r2 = $read();
        $send("QUIT");
        fclose($socket);

        return strpos($r2, '250') !== false;
    } catch (Exception $e) {
        return false;
    }
}
