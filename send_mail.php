<?php
// دالة بسيطة لإرسال الإيميلات
function sendOrderEmail($to, $subject, $message) {
    // إعدادات الرأس (مهمة لدعم العربية)
    $headers  = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: متجر مشترياتي <no-reply@myshop.com>" . "\r\n";

    // محاولة الإرسال (ستعمل فقط على سيرفر حقيقي)
    // الـ @ قبل الدالة تمنع ظهور أخطاء في السيرفر المحلي
    @mail($to, $subject, $message, $headers);
}
?>