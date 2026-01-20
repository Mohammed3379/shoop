<?php
/**
 * دوال مساعدة لطرق الدفع والشحن
 */

/**
 * جلب طرق الدفع المفعّلة
 */
function getActivePaymentMethods($conn) {
    $methods = [];
    $result = $conn->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $methods[] = $row;
        }
    }
    return $methods;
}

/**
 * جلب طرق الشحن المفعّلة
 */
function getActiveShippingMethods($conn) {
    $methods = [];
    $result = $conn->query("SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY sort_order, id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $methods[] = $row;
        }
    }
    return $methods;
}

/**
 * حساب تكلفة الشحن
 */
function calculateShippingCost($conn, $shipping_code, $order_total, $items_count = 1, $total_weight = 0) {
    $stmt = $conn->prepare("SELECT * FROM shipping_methods WHERE code = ? AND is_active = 1");
    $stmt->bind_param("s", $shipping_code);
    $stmt->execute();
    $method = $stmt->get_result()->fetch_assoc();
    
    if (!$method) return 0;
    
    // شحن مجاني
    if ($method['type'] === 'free') {
        return 0;
    }
    
    // التحقق من الحد الأدنى للشحن المجاني
    if ($method['free_shipping_min'] > 0 && $order_total >= $method['free_shipping_min']) {
        return 0;
    }
    
    switch ($method['type']) {
        case 'flat':
            return $method['cost'];
        case 'per_item':
            return $method['cost'] * $items_count;
        case 'per_weight':
            return $method['cost'] * $total_weight;
        default:
            return $method['cost'];
    }
}

/**
 * حساب رسوم طريقة الدفع
 */
function calculatePaymentFee($conn, $payment_code, $order_total) {
    $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE code = ? AND is_active = 1");
    $stmt->bind_param("s", $payment_code);
    $stmt->execute();
    $method = $stmt->get_result()->fetch_assoc();
    
    if (!$method || $method['fee_type'] === 'none') return 0;
    
    if ($method['fee_type'] === 'fixed') {
        return $method['fee_amount'];
    }
    
    if ($method['fee_type'] === 'percentage') {
        return ($order_total * $method['fee_amount']) / 100;
    }
    
    return 0;
}

/**
 * التحقق من صلاحية طريقة الدفع للطلب
 */
function isPaymentMethodValid($conn, $payment_code, $order_total) {
    $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE code = ? AND is_active = 1");
    $stmt->bind_param("s", $payment_code);
    $stmt->execute();
    $method = $stmt->get_result()->fetch_assoc();
    
    if (!$method) return false;
    
    // التحقق من الحد الأدنى
    if ($method['min_order'] > 0 && $order_total < $method['min_order']) {
        return false;
    }
    
    // التحقق من الحد الأقصى
    if ($method['max_order'] > 0 && $order_total > $method['max_order']) {
        return false;
    }
    
    return true;
}

/**
 * جلب تفاصيل طريقة الدفع
 */
function getPaymentMethod($conn, $code) {
    $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * جلب تفاصيل طريقة الشحن
 */
function getShippingMethod($conn, $code) {
    $stmt = $conn->prepare("SELECT * FROM shipping_methods WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
?>
