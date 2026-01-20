<?php
/**
 * معالج حفظ الطلبات - نسخة محدثة مع دعم الشحن والدفع
 */

// تعيين رأس JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين قاعدة البيانات
include 'app/config/database.php';

// جلب البيانات
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// التحقق من البيانات الأساسية
if (!$data) {
    echo json_encode(["status" => "error", "message" => "لم يتم استقبال بيانات"]);
    exit;
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "يجب تسجيل الدخول أولاً"]);
    exit;
}

try {
    // التحقق من وجود الأعمدة الجديدة وإضافتها إذا لم تكن موجودة
    $columns_to_add = [
        'payment_method' => "VARCHAR(50) DEFAULT 'cod'",
        'shipping_method' => "VARCHAR(50) DEFAULT 'standard'",
        'shipping_cost' => "DECIMAL(10,2) DEFAULT 0.00",
        'payment_fee' => "DECIMAL(10,2) DEFAULT 0.00",
        'subtotal' => "DECIMAL(10,2) DEFAULT 0.00",
        'tax' => "DECIMAL(10,2) DEFAULT 0.00"
    ];
    
    // جلب الأعمدة الموجودة
    $existing_columns = [];
    $result = $conn->query("SHOW COLUMNS FROM orders");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }
    }
    
    // إضافة الأعمدة المفقودة
    foreach ($columns_to_add as $col_name => $col_def) {
        if (!in_array($col_name, $existing_columns)) {
            @$conn->query("ALTER TABLE orders ADD COLUMN $col_name $col_def");
        }
    }
    
    // تنظيف البيانات الأساسية
    $user_id = (int)$_SESSION['user_id'];
    $name = isset($data['name']) ? $conn->real_escape_string(trim($data['name'])) : '';
    $phone = isset($data['phone']) ? $conn->real_escape_string(trim($data['phone'])) : '';
    $address = isset($data['address']) ? $conn->real_escape_string(trim($data['address'])) : '';
    $lat = isset($data['lat']) ? $conn->real_escape_string(trim($data['lat'])) : '0';
    $lng = isset($data['lng']) ? $conn->real_escape_string(trim($data['lng'])) : '0';
    
    // بيانات الشحن والدفع
    $shipping_method = isset($data['shipping_method']) ? $conn->real_escape_string(trim($data['shipping_method'])) : 'standard';
    $payment_method = isset($data['payment_method']) ? $conn->real_escape_string(trim($data['payment_method'])) : 'cod';
    $shipping_cost = isset($data['shipping_cost']) ? (float)$data['shipping_cost'] : 0;
    $payment_fee = isset($data['payment_fee']) ? (float)$data['payment_fee'] : 0;
    $subtotal = isset($data['subtotal']) ? (float)$data['subtotal'] : 0;
    $tax = isset($data['tax']) ? (float)$data['tax'] : 0;
    $total = isset($data['total']) ? (float)$data['total'] : 0;

    // تجهيز قائمة المنتجات
    $products_list = [];
    if (isset($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $item) {
            if (isset($item['name'])) {
                $item_name = $conn->real_escape_string($item['name']);
                $item_qty = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                $products_list[] = "$item_name (x$item_qty)";
            }
        }
    }
    
    $products_str = !empty($products_list) ? implode(", ", $products_list) : "منتجات";

    // إعادة جلب الأعمدة بعد الإضافة
    $existing_columns = [];
    $result = $conn->query("SHOW COLUMNS FROM orders");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }
    }
    
    // بناء الاستعلام بناءً على الأعمدة الموجودة
    $columns = ['user_id', 'customer_name', 'phone', 'address', 'lat', 'lng', 'products', 'total_price'];
    $values = ["'$user_id'", "'$name'", "'$phone'", "'$address'", "'$lat'", "'$lng'", "'$products_str'", "'$total'"];
    
    // إضافة الأعمدة الاختيارية إذا كانت موجودة
    if (in_array('subtotal', $existing_columns)) {
        $columns[] = 'subtotal';
        $values[] = "'$subtotal'";
    }
    if (in_array('shipping_method', $existing_columns)) {
        $columns[] = 'shipping_method';
        $values[] = "'$shipping_method'";
    }
    if (in_array('shipping_cost', $existing_columns)) {
        $columns[] = 'shipping_cost';
        $values[] = "'$shipping_cost'";
    }
    if (in_array('payment_method', $existing_columns)) {
        $columns[] = 'payment_method';
        $values[] = "'$payment_method'";
    }
    if (in_array('payment_fee', $existing_columns)) {
        $columns[] = 'payment_fee';
        $values[] = "'$payment_fee'";
    }
    if (in_array('tax', $existing_columns)) {
        $columns[] = 'tax';
        $values[] = "'$tax'";
    }
    
    // إنشاء الطلب
    $sql = "INSERT INTO orders (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";

    if ($conn->query($sql) === TRUE) {
        $order_id = $conn->insert_id;
        echo json_encode([
            "status" => "success",
            "message" => "تم حفظ الطلب بنجاح",
            "order_id" => $order_id
        ]);
    } else {
        throw new Exception("خطأ في حفظ الطلب: " . $conn->error);
    }

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>
