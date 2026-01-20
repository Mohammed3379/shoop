<?php
/**
 * API إشعارات المستخدم
 * User Notifications API
 * 
 * @package MyShop
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين قاعدة البيانات
include_once '../app/config/database.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getNotifications($conn, $userId);
        break;
    case 'count':
        getUnreadCount($conn, $userId);
        break;
    case 'read':
        markAsRead($conn, $userId);
        break;
    case 'read_all':
        markAllAsRead($conn, $userId);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'إجراء غير صالح']);
}

/**
 * جلب إشعارات المستخدم
 */
function getNotifications($conn, $userId) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $stmt = $conn->prepare("
        SELECT 
            un.id,
            un.notification_id,
            un.is_read,
            un.read_at,
            un.created_at,
            n.title,
            n.content,
            n.category,
            n.image,
            n.link
        FROM user_notifications un
        JOIN notifications n ON un.notification_id = n.id
        WHERE un.user_id = ?
        ORDER BY un.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $userId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_read'] = (bool)$row['is_read'];
        $notifications[] = $row;
    }
    $stmt->close();
    
    // جلب العدد الإجمالي
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM user_notifications WHERE user_id = ?");
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * جلب عدد الإشعارات غير المقروءة
 */
function getUnreadCount($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM user_notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
    ]);
}

/**
 * تحديد إشعار كمقروء
 */
function markAsRead($conn, $userId) {
    $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    
    if ($notificationId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الإشعار مطلوب']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE user_notifications 
        SET is_read = 1, read_at = NOW() 
        WHERE user_id = ? AND notification_id = ?
    ");
    $stmt->bind_param("ii", $userId, $notificationId);
    $success = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($success && $affected > 0) {
        // جلب الرابط المرفق
        $linkStmt = $conn->prepare("SELECT link FROM notifications WHERE id = ?");
        $linkStmt->bind_param("i", $notificationId);
        $linkStmt->execute();
        $linkResult = $linkStmt->get_result()->fetch_assoc();
        $linkStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديد الإشعار كمقروء',
            'link' => $linkResult['link'] ?? null
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'الإشعار غير موجود أو تم قراءته مسبقاً'
        ]);
    }
}

/**
 * تحديد جميع الإشعارات كمقروءة
 */
function markAllAsRead($conn, $userId) {
    $stmt = $conn->prepare("
        UPDATE user_notifications 
        SET is_read = 1, read_at = NOW() 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $userId);
    $success = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'success' => $success,
        'message' => 'تم تحديد جميع الإشعارات كمقروءة',
        'updated_count' => $affected
    ]);
}
