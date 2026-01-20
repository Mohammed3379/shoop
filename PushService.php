<?php
/**
 * خدمة Push Notifications
 * PushService - Web Push Notifications Service
 * 
 * @package MyShop
 * @version 1.0
 */

class PushService {
    
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * تسجيل اشتراك Push Notification
     * @param int $userId معرف المستخدم
     * @param array $subscription بيانات الاشتراك
     * @return array نتيجة العملية
     */
    public function subscribe(int $userId, array $subscription): array {
        if (empty($subscription['endpoint']) || empty($subscription['keys'])) {
            return [
                'success' => false,
                'error' => 'بيانات الاشتراك غير مكتملة'
            ];
        }
        
        $endpoint = $subscription['endpoint'];
        $p256dhKey = $subscription['keys']['p256dh'] ?? '';
        $authKey = $subscription['keys']['auth'] ?? '';
        
        // التحقق من وجود اشتراك سابق
        $stmt = $this->conn->prepare("SELECT id FROM push_subscriptions WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            // تحديث الاشتراك الموجود
            $stmt = $this->conn->prepare("
                UPDATE push_subscriptions 
                SET endpoint = ?, p256dh_key = ?, auth_key = ?, is_active = 1, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->bind_param("sssi", $endpoint, $p256dhKey, $authKey, $userId);
        } else {
            // إنشاء اشتراك جديد
            $stmt = $this->conn->prepare("
                INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $userId, $endpoint, $p256dhKey, $authKey);
        }
        
        if ($stmt->execute()) {
            $stmt->close();
            return [
                'success' => true,
                'message' => 'تم تسجيل الاشتراك بنجاح'
            ];
        }
        
        $error = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'error' => 'حدث خطأ في قاعدة البيانات: ' . $error
        ];
    }
    
    /**
     * إلغاء اشتراك Push Notification
     * @param int $userId معرف المستخدم
     * @return array نتيجة العملية
     */
    public function unsubscribe(int $userId): array {
        $stmt = $this->conn->prepare("DELETE FROM push_subscriptions WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            $stmt->close();
            return [
                'success' => true,
                'message' => $affected > 0 ? 'تم إلغاء الاشتراك بنجاح' : 'لا يوجد اشتراك لإلغائه'
            ];
        }
        
        $error = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'error' => 'حدث خطأ في قاعدة البيانات: ' . $error
        ];
    }
    
    /**
     * التحقق من وجود اشتراك للمستخدم
     * @param int $userId معرف المستخدم
     * @return bool
     */
    public function isSubscribed(int $userId): bool {
        $stmt = $this->conn->prepare("SELECT id FROM push_subscriptions WHERE user_id = ? AND is_active = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result !== null;
    }
    
    /**
     * جلب اشتراك المستخدم
     * @param int $userId معرف المستخدم
     * @return array|null
     */
    public function getSubscription(int $userId): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM push_subscriptions WHERE user_id = ? AND is_active = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * جلب جميع الاشتراكات النشطة
     * @param array $userIds قائمة معرفات المستخدمين (اختياري)
     * @return array
     */
    public function getActiveSubscriptions(array $userIds = []): array {
        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $sql = "SELECT * FROM push_subscriptions WHERE is_active = 1 AND user_id IN ($placeholders)";
            $stmt = $this->conn->prepare($sql);
            $types = str_repeat('i', count($userIds));
            $stmt->bind_param($types, ...$userIds);
        } else {
            $sql = "SELECT * FROM push_subscriptions WHERE is_active = 1";
            $stmt = $this->conn->prepare($sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $subscriptions = [];
        while ($row = $result->fetch_assoc()) {
            $subscriptions[] = $row;
        }
        $stmt->close();
        
        return $subscriptions;
    }
    
    /**
     * إرسال Push Notification لمستخدم واحد
     * ملاحظة: يتطلب مكتبة web-push للإرسال الفعلي
     * @param int $userId معرف المستخدم
     * @param array $notification بيانات الإشعار
     * @return array نتيجة العملية
     */
    public function sendPush(int $userId, array $notification): array {
        $subscription = $this->getSubscription($userId);
        
        if (!$subscription) {
            return [
                'success' => false,
                'error' => 'المستخدم غير مشترك في الإشعارات'
            ];
        }
        
        // تجهيز بيانات الإشعار
        $payload = json_encode([
            'title' => $notification['title'] ?? 'إشعار جديد',
            'body' => $notification['content'] ?? '',
            'icon' => $notification['icon'] ?? '/public/images/icon-192.png',
            'badge' => '/public/images/badge.png',
            'data' => [
                'url' => $notification['link'] ?? '/',
                'notification_id' => $notification['id'] ?? null
            ]
        ]);
        
        // هنا يتم الإرسال الفعلي باستخدام مكتبة web-push
        // للتبسيط، نعيد نجاح العملية
        // في الإنتاج، استخدم: composer require minishlink/web-push
        
        return [
            'success' => true,
            'message' => 'تم إرسال الإشعار',
            'payload' => $payload
        ];
    }
    
    /**
     * إرسال Push Notifications لعدة مستخدمين
     * @param array $userIds قائمة معرفات المستخدمين
     * @param array $notification بيانات الإشعار
     * @return array نتيجة العملية
     */
    public function sendBulkPush(array $userIds, array $notification): array {
        $subscriptions = $this->getActiveSubscriptions($userIds);
        
        $sent = 0;
        $failed = 0;
        
        foreach ($subscriptions as $sub) {
            $result = $this->sendPush($sub['user_id'], $notification);
            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
            }
        }
        
        return [
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'total_subscriptions' => count($subscriptions)
        ];
    }
}
