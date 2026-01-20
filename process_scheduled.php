<?php
/**
 * معالج الإشعارات المجدولة
 * Scheduled Notifications Processor
 * 
 * يتم تشغيل هذا الملف عبر Cron Job كل دقيقة أو 5 دقائق
 * مثال: * * * * * php /path/to/myshop/cron/process_scheduled.php
 * 
 * @package MyShop
 * @version 1.0
 */

// منع الوصول المباشر من المتصفح (اختياري)
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    // يمكن السماح بالوصول عبر HTTP للاختبار
    // die('Access denied');
}

// تضمين ملفات الإعداد
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/NotificationService.php';
require_once __DIR__ . '/../app/services/PushService.php';

// تسجيل وقت البدء
$startTime = microtime(true);
$logFile = __DIR__ . '/logs/scheduled_' . date('Y-m-d') . '.log';

// إنشاء مجلد السجلات إذا لم يكن موجوداً
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

/**
 * تسجيل رسالة في ملف السجل
 */
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== بدء معالجة الإشعارات المجدولة ===");

try {
    $notificationService = new NotificationService($conn);
    $pushService = new PushService($conn);
    
    // معالجة الإشعارات المجدولة
    $result = $notificationService->processScheduled();
    
    logMessage("تمت المعالجة: {$result['processed']} إشعار");
    if ($result['failed'] > 0) {
        logMessage("فشل: {$result['failed']} إشعار");
    }
    
    // إرسال Push Notifications للإشعارات المرسلة حديثاً
    $recentNotifications = $conn->query("
        SELECT n.*, 
               (SELECT COUNT(*) FROM user_notifications un WHERE un.notification_id = n.id) as recipients_count
        FROM notifications n 
        WHERE n.status = 'sent' 
        AND n.sent_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    
    $pushSent = 0;
    while ($notification = $recentNotifications->fetch_assoc()) {
        // جلب المستخدمين المستهدفين الذين لديهم اشتراك Push
        $users = $conn->query("
            SELECT DISTINCT ps.* 
            FROM push_subscriptions ps
            INNER JOIN user_notifications un ON ps.user_id = un.user_id
            WHERE un.notification_id = {$notification['id']}
        ");
        
        while ($subscription = $users->fetch_assoc()) {
            $pushResult = $pushService->sendPush($subscription, [
                'title' => $notification['title'],
                'body' => $notification['content'],
                'icon' => $notification['image'] ?? '/public/images/logo.png',
                'url' => $notification['link'] ?? '/notifications.php'
            ]);
            
            if ($pushResult['success']) {
                $pushSent++;
            }
        }
    }
    
    if ($pushSent > 0) {
        logMessage("تم إرسال $pushSent إشعار Push");
    }
    
    // معالجة السلات المتروكة (إرسال تذكيرات)
    processAbandonedCarts($conn, $notificationService);
    
} catch (Exception $e) {
    logMessage("خطأ: " . $e->getMessage());
}

/**
 * معالجة السلات المتروكة
 */
function processAbandonedCarts($conn, $notificationService) {
    // جلب السلات المتروكة منذ أكثر من ساعة ولم يتم إرسال تذكير لها
    $abandonedCarts = $conn->query("
        SELECT ac.*, u.name as user_name
        FROM abandoned_carts ac
        INNER JOIN users u ON ac.user_id = u.id
        WHERE ac.reminder_sent = 0
        AND ac.updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND ac.updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    
    $reminders = 0;
    while ($cart = $abandonedCarts->fetch_assoc()) {
        $cartItems = json_decode($cart['cart_data'], true);
        $itemCount = count($cartItems);
        
        if ($itemCount > 0) {
            // إنشاء إشعار تذكير
            $result = $notificationService->create([
                'title' => 'لا تنسَ سلة مشترياتك! 🛒',
                'content' => "لديك $itemCount منتج في سلة المشتريات بانتظارك. أكمل طلبك الآن!",
                'type' => 'individual',
                'category' => 'cart_reminder',
                'link' => '/cart.php',
                'target_user_id' => $cart['user_id'],
                'created_by' => 0 // النظام
            ]);
            
            if ($result['success']) {
                // إرسال الإشعار مباشرة
                $notificationService->send($result['id']);
                
                // تحديث حالة التذكير
                $stmt = $conn->prepare("UPDATE abandoned_carts SET reminder_sent = 1 WHERE id = ?");
                $stmt->bind_param("i", $cart['id']);
                $stmt->execute();
                $stmt->close();
                
                $reminders++;
            }
        }
    }
    
    if ($reminders > 0) {
        logMessage("تم إرسال $reminders تذكير سلة متروكة");
    }
}

// حساب وقت التنفيذ
$executionTime = round((microtime(true) - $startTime) * 1000, 2);
logMessage("وقت التنفيذ: {$executionTime}ms");
logMessage("=== انتهاء المعالجة ===\n");

// إخراج النتيجة (للاختبار)
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'processed' => $result['processed'] ?? 0,
        'failed' => $result['failed'] ?? 0,
        'execution_time' => $executionTime . 'ms'
    ], JSON_UNESCAPED_UNICODE);
}
