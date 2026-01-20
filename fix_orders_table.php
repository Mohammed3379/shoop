<?php
/**
 * إصلاح جدول الطلبات - إضافة الأعمدة المفقودة
 */
include 'app/config/database.php';

echo "<h2>إصلاح جدول الطلبات</h2>";

// الأعمدة المطلوبة
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

echo "<h3>الأعمدة الموجودة حالياً:</h3>";
echo "<pre>" . implode(", ", $existing_columns) . "</pre>";

echo "<h3>إضافة الأعمدة المفقودة:</h3>";

foreach ($columns_to_add as $col_name => $col_def) {
    if (!in_array($col_name, $existing_columns)) {
        $sql = "ALTER TABLE orders ADD COLUMN $col_name $col_def";
        if ($conn->query($sql)) {
            echo "<p style='color:green;'>✅ تم إضافة عمود: $col_name</p>";
        } else {
            echo "<p style='color:red;'>❌ فشل إضافة عمود $col_name: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:blue;'>ℹ️ العمود موجود مسبقاً: $col_name</p>";
    }
}

// التحقق مرة أخرى
echo "<h3>الأعمدة بعد الإصلاح:</h3>";
$result = $conn->query("SHOW COLUMNS FROM orders");
$final_columns = [];
while ($row = $result->fetch_assoc()) {
    $final_columns[] = $row['Field'];
}
echo "<pre>" . implode(", ", $final_columns) . "</pre>";

echo "<br><a href='checkout.php'>العودة لصفحة الدفع</a>";

$conn->close();
?>
