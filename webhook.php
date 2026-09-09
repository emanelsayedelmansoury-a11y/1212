<?php
/**
 * استقبال إشعارات الويب هوك من بوابة دفع شحناوي (Webhook Receiver)
 */

require_once __DIR__ . '/config.php';

// قراءة محتوى الطلب (JSON Payload)
$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid JSON Payload']);
    exit;
}

// التحقق من نوع الحدث
if (isset($data['event']) && $data['event'] === 'transaction.updated') {
    $transaction = $data['transaction'];
    $status = $transaction['status'] ?? '';
    $refCode = $transaction['reference'] ?? '';
    $client = $transaction['client'] ?? '';
    $amount = $transaction['amount'] ?? '';
    $txId = $transaction['transaction_id'] ?? '';

    if ($status === 'completed') {
        // تم تأكيد الدفع بنجاح: تفعيل الاشتراك وإرسال بيانات الكورس للعميل
        // مثال: mail($client, "رابط كورس المونتاج بالذكاء الاصطناعي", "أهلاً بك، تم تفعيل اشتراكك بنجاح...");
        file_put_contents(__DIR__ . '/payments_log.txt', date('[Y-m-d H:i:s]') . " - نجاح العملية: {$txId} للعميل: {$client} بمبلغ: {$amount}\n", FILE_APPEND);
    } elseif ($status === 'rejected') {
        // فشلت المعاملة أو انتهى وقت التأكيد
        file_put_contents(__DIR__ . '/payments_log.txt', date('[Y-m-d H:i:s]') . " - فشل/إلغاء المعاملة: {$refCode}\n", FILE_APPEND);
    }
}

// الرد بكود 200 OK للبوابة لتأكيد الاستلام
http_response_code(200);
echo json_encode(['success' => true]);
