<?php
/**
 * إعدادات بوابة دفع شحناوي (Sha7nawy Gate Configuration)
 */

define('SHA7NAWY_BASE_URL', 'https://gate.sha7nawy.com');

// المفتاح العام (Public Key) - يستخدم لإنشاء وتأكيد طلبات الدفع
define('SHA7NAWY_PUBLIC_KEY', 'IQmQxdTxwqVr2CyJEaucYRUuETL3zEnTIcJmyAvUG8DZH84PFkuAbLK5yIv0K32VoO7B7kwRFNGi5QWPXUhalC1inLOcuv1GCU4H');

// المفتاح السري (Secret Key) - يستخدم للتحقق من المعاملات من جانب السيرفر فقط
define('SHA7NAWY_SECRET_KEY', 'uoH00BByhYJwVZGrLcxC0H4M0EpbFaeCWtA7PXqDOaIGsBaCQLku0v4IVP6xmhda7eTweckJBcLcdHYB6UofMzT1pbs1rqetN9yS');

/**
 * دالة للتحقق من حالة معاملة معينة عبر السيرفر باستخدام المفتاح السري
 * GET https://gate.sha7nawy.com/api/payment/{transaction_id}
 */
function checkSha7nawyTransaction($transactionId) {
    $url = SHA7NAWY_BASE_URL . '/api/payment/' . urlencode($transactionId);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: ' . SHA7NAWY_SECRET_KEY
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['status' => false, 'message' => $error];
    }
    
    return json_decode($response, true);
}
