<?php
/**
 * API مزامنة السلة
 * Cart Sync API
 * 
 * يحفظ السلة من localStorage إلى قاعدة البيانات
 * لتتبع السلات المتروكة
 * 
 * @package MyShop
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// معالجة طلبات OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();
require_once __DIR__ . '/../app/config/database.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'غير مسجل الدخول'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = $_SESSION['user_id'];

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);
$cartData = $input['cart'] ?? [];
$action = $input['action'] ?? 'sync';

try {
    switch ($action) {
        case 'sync':
            // مزامنة السلة
            $result = syncCart($conn, $userId, $cartData);
            break;
            
        case 'get':
            // جلب السلة المحفوظة
            $result = getCart($conn, $userId);
            break;
            
        case 'clear':
            // مسح السلة (بعد إتمام الطلب)
            $result = clearCart($conn, $userId);
            break;
            
        default:
            $result = ['success' => false, 'error' => 'إجراء غير معروف'];
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في الخادم'
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * مزامنة السلة
 */
function syncCart($conn, $userId, $cartData) {
    if (empty($cartData)) {
        // إذا كانت السلة فارغة، احذف السجل
        $stmt = $conn->prepare("DELETE FROM abandoned_carts WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        
        return ['success' => true, 'message' => 'تم مسح السلة'];
    }
    
    // حساب إجمالي السلة
    $totalAmount = 0;
    foreach ($cartData as $item) {
        $totalAmount += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
    }
    
    $cartJson = json_encode($cartData, JSON_UNESCAPED_UNICODE);
    
    // التحقق من وجود سلة سابقة
    $stmt = $conn->prepare("SELECT id FROM abandoned_carts WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        // تحديث السلة الموجودة
        $stmt = $conn->prepare("
            UPDATE abandoned_carts 
            SET cart_data = ?, total_amount = ?, updated_at = NOW(), reminder_sent = 0
            WHERE user_id = ?
        ");
        $stmt->bind_param("sdi", $cartJson, $totalAmount, $userId);
    } else {
        // إنشاء سلة جديدة
        $stmt = $conn->prepare("
            INSERT INTO abandoned_carts (user_id, cart_data, total_amount)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("isd", $userId, $cartJson, $totalAmount);
    }
    
    if ($stmt->execute()) {
        $stmt->close();
        return [
            'success' => true,
            'message' => 'تم حفظ السلة',
            'items_count' => count($cartData),
            'total_amount' => $totalAmount
        ];
    }
    
    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}

/**
 * جلب السلة المحفوظة
 */
function getCart($conn, $userId) {
    $stmt = $conn->prepare("SELECT cart_data, total_amount, updated_at FROM abandoned_carts WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart = $result->fetch_assoc();
    $stmt->close();
    
    if ($cart) {
        return [
            'success' => true,
            'cart' => json_decode($cart['cart_data'], true),
            'total_amount' => $cart['total_amount'],
            'updated_at' => $cart['updated_at']
        ];
    }
    
    return [
        'success' => true,
        'cart' => [],
        'total_amount' => 0
    ];
}

/**
 * مسح السلة
 */
function clearCart($conn, $userId) {
    $stmt = $conn->prepare("DELETE FROM abandoned_carts WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    
    return ['success' => true, 'message' => 'تم مسح السلة'];
}
