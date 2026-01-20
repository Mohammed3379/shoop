<?php
/**
 * خدمة إدارة الإشعارات
 * NotificationService - Notification Management Service
 * 
 * @package MyShop
 * @version 1.0
 */

class NotificationService {
    
    private $conn;
    
    // أكواد الأخطاء
    const ERROR_EMPTY_CONTENT = 'EMPTY_CONTENT';
    const ERROR_INVALID_SCHEDULE = 'INVALID_SCHEDULE';
    const ERROR_NOT_FOUND = 'NOT_FOUND';
    const ERROR_ALREADY_SENT = 'ALREADY_SENT';
    const ERROR_NO_TARGETS = 'NO_TARGETS';
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * إنشاء إشعار جديد
     * @param array $data بيانات الإشعار
     * @return array نتيجة العملية
     */
    public function create(array $data): array {
        // التحقق من المحتوى
        if (empty(trim($data['title'] ?? '')) || empty(trim($data['content'] ?? ''))) {
            return [
                'success' => false,
                'error' => self::ERROR_EMPTY_CONTENT,
                'message' => 'العنوان والمحتوى مطلوبان'
            ];
        }
        
        $title = trim($data['title']);
        $content = trim($data['content']);
        $type = $data['type'] ?? 'general';
        $category = $data['category'] ?? 'promotional';
        $image = $data['image'] ?? null;
        $link = $data['link'] ?? null;
        $status = $data['status'] ?? 'draft';
        $targetingCriteria = isset($data['targeting_criteria']) ? json_encode($data['targeting_criteria']) : null;
        $targetUserId = $data['target_user_id'] ?? null;
        $createdBy = $data['created_by'];
        
        $stmt = $this->conn->prepare("
            INSERT INTO notifications 
            (title, content, type, category, image, link, status, targeting_criteria, target_user_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "ssssssssii",
            $title, $content, $type, $category, $image, $link, $status, $targetingCriteria, $targetUserId, $createdBy
        );
        
        if ($stmt->execute()) {
            $id = $this->conn->insert_id;
            $stmt->close();
            return [
                'success' => true,
                'id' => $id,
                'message' => 'تم إنشاء الإشعار بنجاح'
            ];
        }
        
        $error = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'error' => 'DB_ERROR',
            'message' => 'حدث خطأ في قاعدة البيانات: ' . $error
        ];
    }
    
    /**
     * تعديل إشعار
     * @param int $id معرف الإشعار
     * @param array $data البيانات الجديدة
     * @return array نتيجة العملية
     */
    public function update(int $id, array $data): array {
        // التحقق من وجود الإشعار
        $notification = $this->getById($id);
        if (!$notification) {
            return [
                'success' => false,
                'error' => self::ERROR_NOT_FOUND,
                'message' => 'الإشعار غير موجود'
            ];
        }
        
        // منع تعديل الإشعارات المرسلة
        if ($notification['status'] === 'sent') {
            return [
                'success' => false,
                'error' => self::ERROR_ALREADY_SENT,
                'message' => 'لا يمكن تعديل إشعار تم إرساله'
            ];
        }
        
        // التحقق من المحتوى
        if (isset($data['title']) && empty(trim($data['title']))) {
            return [
                'success' => false,
                'error' => self::ERROR_EMPTY_CONTENT,
                'message' => 'العنوان لا يمكن أن يكون فارغاً'
            ];
        }
        
        if (isset($data['content']) && empty(trim($data['content']))) {
            return [
                'success' => false,
                'error' => self::ERROR_EMPTY_CONTENT,
                'message' => 'المحتوى لا يمكن أن يكون فارغاً'
            ];
        }
        
        $updates = [];
        $params = [];
        $types = '';
        
        $allowedFields = ['title', 'content', 'type', 'category', 'image', 'link', 'status', 'targeting_criteria', 'target_user_id'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                if ($field === 'targeting_criteria' && is_array($data[$field])) {
                    $params[] = json_encode($data[$field]);
                } else {
                    $params[] = $data[$field];
                }
                $types .= ($field === 'target_user_id') ? 'i' : 's';
            }
        }
        
        if (empty($updates)) {
            return [
                'success' => false,
                'error' => 'NO_CHANGES',
                'message' => 'لا توجد تغييرات للحفظ'
            ];
        }
        
        $params[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE notifications SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $stmt->close();
            return [
                'success' => true,
                'message' => 'تم تحديث الإشعار بنجاح'
            ];
        }
        
        $error = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'error' => 'DB_ERROR',
            'message' => 'حدث خطأ في قاعدة البيانات: ' . $error
        ];
    }
    
    /**
     * حذف إشعار
     * @param int $id معرف الإشعار
     * @return array نتيجة العملية
     */
    public function delete(int $id): array {
        $notification = $this->getById($id);
        if (!$notification) {
            return [
                'success' => false,
                'error' => self::ERROR_NOT_FOUND,
                'message' => 'الإشعار غير موجود'
            ];
        }
        
        $stmt = $this->conn->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return [
                'success' => true,
                'message' => 'تم حذف الإشعار بنجاح'
            ];
        }
        
        $error = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'error' => 'DB_ERROR',
            'message' => 'حدث خطأ في قاعدة البيانات: ' . $error
        ];
    }
    
    /**
     * جلب إشعار بالمعرف
     * @param int $id معرف الإشعار
     * @return array|null بيانات الإشعار
     */
    public function getById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $notification = $result->fetch_assoc();
        $stmt->close();
        
        if ($notification && $notification['targeting_criteria']) {
            $notification['targeting_criteria'] = json_decode($notification['targeting_criteria'], true);
        }
        
        return $notification;
    }
    
    /**
     * جلب جميع الإشعارات مع الفلترة
     * @param array $filters معايير الفلترة
     * @return array قائمة الإشعارات
     */
    public function getAll(array $filters = []): array {
        $where = [];
        $params = [];
        $types = '';
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['type'])) {
            $where[] = "type = ?";
            $params[] = $filters['type'];
            $types .= 's';
        }
        
        if (!empty($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE ? OR content LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        if (!empty($filters['created_by'])) {
            $where[] = "created_by = ?";
            $params[] = $filters['created_by'];
            $types .= 'i';
        }
        
        $sql = "SELECT * FROM notifications";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . intval($filters['offset']);
            }
        }
        
        if (!empty($params)) {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->conn->query($sql);
        }
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['targeting_criteria']) {
                $row['targeting_criteria'] = json_decode($row['targeting_criteria'], true);
            }
            $notifications[] = $row;
        }
        
        if (isset($stmt)) {
            $stmt->close();
        }
        
        return $notifications;
    }
    
    /**
     * إرسال إشعار فوري
     * @param int $id معرف الإشعار
     * @return array نتيجة العملية
     */
    public function send(int $id): array {
        $notification = $this->getById($id);
        if (!$notification) {
            return [
                'success' => false,
                'error' => self::ERROR_NOT_FOUND,
                'message' => 'الإشعار غير موجود'
            ];
        }
        
        if ($notification['status'] === 'sent') {
            return [
                'success' => false,
                'error' => self::ERROR_ALREADY_SENT,
                'message' => 'تم إرسال هذا الإشعار مسبقاً'
            ];
        }
        
        // تحديد المستخدمين المستهدفين
        $targetUsers = $this->getTargetUsers($notification);
        
        if (empty($targetUsers)) {
            return [
                'success' => false,
                'error' => self::ERROR_NO_TARGETS,
                'message' => 'لا يوجد مستخدمين مستهدفين لهذا الإشعار'
            ];
        }
        
        // ربط الإشعار بالمستخدمين
        $linkedCount = $this->linkNotificationToUsers($id, $targetUsers);
        
        // تحديث حالة الإشعار
        $stmt = $this->conn->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        return [
            'success' => true,
            'message' => 'تم إرسال الإشعار بنجاح',
            'recipients_count' => $linkedCount
        ];
    }
    
    /**
     * جدولة إشعار
     * @param int $id معرف الإشعار
     * @param string $scheduledAt وقت الجدولة
     * @return array نتيجة العملية
     */
    public function schedule(int $id, string $scheduledAt): array {
        $notification = $this->getById($id);
        if (!$notification) {
            return [
                'success' => false,
                'error' => self::ERROR_NOT_FOUND,
                'message' => 'الإشعار غير موجود'
            ];
        }
        
        if ($notification['status'] === 'sent') {
            return [
                'success' => false,
                'error' => self::ERROR_ALREADY_SENT,
                'message' => 'لا يمكن جدولة إشعار تم إرساله'
            ];
        }
        
        // التحقق من أن التاريخ في المستقبل
        $scheduleTime = strtotime($scheduledAt);
        if ($scheduleTime === false || $scheduleTime <= time()) {
            return [
                'success' => false,
                'error' => self::ERROR_INVALID_SCHEDULE,
                'message' => 'يجب أن يكون وقت الجدولة في المستقبل'
            ];
        }
        
        $stmt = $this->conn->prepare("UPDATE notifications SET status = 'scheduled', scheduled_at = ? WHERE id = ?");
        $stmt->bind_param("si", $scheduledAt, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return [
                'success' => true,
                'message' => 'تم جدولة الإشعار بنجاح',
                'scheduled_at' => $scheduledAt
            ];
        }
        
        $error = $stmt->error;
        $stmt->close();
        return [
            'success' => false,
            'error' => 'DB_ERROR',
            'message' => 'حدث خطأ في قاعدة البيانات: ' . $error
        ];
    }
    
    /**
     * معالجة الإشعارات المجدولة
     * @return array نتيجة المعالجة
     */
    public function processScheduled(): array {
        $result = $this->conn->query("
            SELECT id FROM notifications 
            WHERE status = 'scheduled' AND scheduled_at <= NOW()
        ");
        
        $processed = 0;
        $failed = 0;
        
        while ($row = $result->fetch_assoc()) {
            $sendResult = $this->send($row['id']);
            if ($sendResult['success']) {
                $processed++;
            } else {
                $failed++;
            }
        }
        
        return [
            'success' => true,
            'processed' => $processed,
            'failed' => $failed
        ];
    }
    
    /**
     * جلب إحصائيات إشعار
     * @param int $id معرف الإشعار
     * @return array الإحصائيات
     */
    public function getStats(int $id): array {
        $notification = $this->getById($id);
        if (!$notification) {
            return [
                'success' => false,
                'error' => self::ERROR_NOT_FOUND
            ];
        }
        
        // عدد المستلمين
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM user_notifications WHERE notification_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $totalResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // عدد القراءات
        $stmt = $this->conn->prepare("SELECT COUNT(*) as read_count FROM user_notifications WHERE notification_id = ? AND is_read = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $readResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $total = $totalResult['total'] ?? 0;
        $readCount = $readResult['read_count'] ?? 0;
        $openRate = $total > 0 ? round(($readCount / $total) * 100, 2) : 0;
        
        return [
            'success' => true,
            'recipients_count' => $total,
            'read_count' => $readCount,
            'open_rate' => $openRate
        ];
    }
    
    /**
     * تحديد المستخدمين المستهدفين
     * @param array $notification بيانات الإشعار
     * @return array قائمة معرفات المستخدمين
     */
    private function getTargetUsers(array $notification): array {
        $type = $notification['type'];
        
        // إشعار فردي
        if ($type === 'individual' && $notification['target_user_id']) {
            return [$notification['target_user_id']];
        }
        
        // إشعار عام - جميع المستخدمين
        if ($type === 'general') {
            $result = $this->conn->query("SELECT id FROM users");
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row['id'];
            }
            return $users;
        }
        
        // إشعار مخصص - يحتاج TargetingService
        // سيتم تنفيذه لاحقاً
        return [];
    }
    
    /**
     * ربط الإشعار بالمستخدمين
     * @param int $notificationId معرف الإشعار
     * @param array $userIds قائمة معرفات المستخدمين
     * @return int عدد المستخدمين المربوطين
     */
    private function linkNotificationToUsers(int $notificationId, array $userIds): int {
        $count = 0;
        $stmt = $this->conn->prepare("
            INSERT IGNORE INTO user_notifications (notification_id, user_id) VALUES (?, ?)
        ");
        
        foreach ($userIds as $userId) {
            $stmt->bind_param("ii", $notificationId, $userId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $count++;
            }
        }
        
        $stmt->close();
        return $count;
    }
}
