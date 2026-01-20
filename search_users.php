<?php
/**
 * API البحث عن المستخدمين
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once '../app/config/database.php';

// التحقق من صلاحيات الأدمن
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

$query = $_GET['q'] ?? '';

if (strlen(trim($query)) < 2) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

include_once '../app/services/TargetingService.php';

$targetingService = new TargetingService($conn);
$users = $targetingService->searchUsers($query);

echo json_encode([
    'success' => true,
    'users' => $users
]);
