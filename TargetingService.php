<?php
/**
 * خدمة استهداف المستخدمين
 * TargetingService - User Targeting Service
 * 
 * @package MyShop
 * @version 1.0
 */

class TargetingService {
    
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * جلب المستخدمين المستهدفين بناءً على معايير متعددة
     * @param array $criteria معايير الاستهداف
     * @return array قائمة معرفات المستخدمين
     */
    public function getTargetUsers(array $criteria): array {
        $userSets = [];
        
        // العملاء الأكثر شراءً
        if (isset($criteria['min_purchase_amount'])) {
            $userSets[] = $this->getTopBuyers($criteria['min_purchase_amount']);
        }
        
        // العملاء غير النشطين
        if (isset($criteria['inactive_days'])) {
            $userSets[] = $this->getInactiveUsers($criteria['inactive_days']);
        }
        
        // العملاء الجدد
        if (isset($criteria['registered_within_days'])) {
            $userSets[] = $this->getNewUsers($criteria['registered_within_days']);
        }
        
        // السلات المتروكة
        if (isset($criteria['abandoned_cart_hours'])) {
            $userSets[] = $this->getAbandonedCartUsers($criteria['abandoned_cart_hours']);
        }
        
        // إذا لم تكن هناك معايير، أرجع مصفوفة فارغة
        if (empty($userSets)) {
            return [];
        }
        
        // تطبيق AND logic - التقاطع بين جميع المجموعات
        $result = $userSets[0];
        for ($i = 1; $i < count($userSets); $i++) {
            $result = array_intersect($result, $userSets[$i]);
        }
        
        return array_values($result);
    }
    
    /**
     * جلب العملاء الأكثر شراءً
     * @param float $minAmount الحد الأدنى لإجمالي المشتريات
     * @return array قائمة معرفات المستخدمين
     */
    public function getTopBuyers(float $minAmount): array {
        $stmt = $this->conn->prepare("
            SELECT u.id, COALESCE(SUM(o.total_price), 0) as total_purchases
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id AND o.status != 'cancelled'
            GROUP BY u.id
            HAVING total_purchases >= ?
        ");
        $stmt->bind_param("d", $minAmount);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row['id'];
        }
        $stmt->close();
        
        return $users;
    }
    
    /**
     * جلب العملاء غير النشطين
     * @param int $days عدد أيام عدم النشاط
     * @return array قائمة معرفات المستخدمين
     */
    public function getInactiveUsers(int $days): array {
        $stmt = $this->conn->prepare("
            SELECT u.id
            FROM users u
            WHERE u.id NOT IN (
                SELECT DISTINCT user_id 
                FROM orders 
                WHERE order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            )
        ");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row['id'];
        }
        $stmt->close();
        
        return $users;
    }
    
    /**
     * جلب العملاء الجدد
     * @param int $days عدد الأيام منذ التسجيل
     * @return array قائمة معرفات المستخدمين
     */
    public function getNewUsers(int $days): array {
        $stmt = $this->conn->prepare("
            SELECT id
            FROM users
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row['id'];
        }
        $stmt->close();
        
        return $users;
    }
    
    /**
     * جلب المستخدمين الذين لديهم سلات متروكة
     * @param int $hours عدد الساعات منذ آخر تحديث للسلة
     * @return array قائمة معرفات المستخدمين
     */
    public function getAbandonedCartUsers(int $hours): array {
        $stmt = $this->conn->prepare("
            SELECT ac.user_id
            FROM abandoned_carts ac
            LEFT JOIN orders o ON ac.user_id = o.user_id 
                AND o.order_date > ac.last_updated
            WHERE ac.last_updated <= DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND ac.items_count > 0
                AND o.id IS NULL
        ");
        $stmt->bind_param("i", $hours);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row['user_id'];
        }
        $stmt->close();
        
        return $users;
    }
    
    /**
     * البحث عن مستخدمين بالاسم أو البريد الإلكتروني
     * @param string $query نص البحث
     * @return array قائمة المستخدمين المطابقين
     */
    public function searchUsers(string $query): array {
        $searchTerm = '%' . trim($query) . '%';
        
        $stmt = $this->conn->prepare("
            SELECT id, full_name, email, phone
            FROM users
            WHERE full_name LIKE ? OR email LIKE ?
            ORDER BY full_name ASC
            LIMIT 20
        ");
        $stmt->bind_param("ss", $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
        
        return $users;
    }
    
    /**
     * جلب إحصائيات الاستهداف
     * @param array $criteria معايير الاستهداف
     * @return array الإحصائيات
     */
    public function getTargetingStats(array $criteria): array {
        $targetUsers = $this->getTargetUsers($criteria);
        
        // إجمالي المستخدمين
        $totalResult = $this->conn->query("SELECT COUNT(*) as total FROM users");
        $totalUsers = $totalResult->fetch_assoc()['total'];
        
        return [
            'target_count' => count($targetUsers),
            'total_users' => $totalUsers,
            'percentage' => $totalUsers > 0 ? round((count($targetUsers) / $totalUsers) * 100, 2) : 0
        ];
    }
}
