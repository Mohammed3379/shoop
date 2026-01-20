<?php
/**
 * API اشتراك Push Notifications
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once '../app/config/database.php';
include_once '../app/services/PushService.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pushService = new PushService($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'subscribe':
        handleSubscribe($pushService, $userId);
        break;
    case 'unsubscribe':
        handleUnsubscribe($pushService, $userId);
        break;
    case 'status':
        handleStatus($pushService, $userId);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'إجراء غير صالح']);
}

function handleSubscribe($pushService, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['subscription'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'بيانات الاشتراك مطلوبة']);
        return;
    }
    
    $result = $pushService->subscribe($userId, $input['subscription']);
    echo json_encode($result);
}

function handleUnsubscribe($pushService, $userId) {
    $result = $pushService->unsubscribe($userId);
    echo json_encode($result);
}

function handleStatus($pushService, $userId) {
    $isSubscribed = $pushService->isSubscribed($userId);
    echo json_encode([
        'success' => true,
        'subscribed' => $isSubscribed
    ]);
}
