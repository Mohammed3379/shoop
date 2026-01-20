<?php
/**
 * تحديث جدول الفئات - إضافة عمود image_url
 * قم بتشغيل هذا الملف مرة واحدة ثم احذفه
 */
include 'app/config/database.php';

echo "<h2>تحديث جدول الفئات</h2>";

// التحقق من وجود عمود image_url
$check = $conn->query("SHOW COLUMNS FROM categories LIKE 'image_url'");

if ($check->num_rows == 0) {
    // إضافة العمود
    $sql = "ALTER TABLE categories ADD COLUMN image_url VARCHAR(500) DEFAULT NULL AFTER icon";
    if ($conn->query($sql)) {
        echo "<p style='color:green'>✅ تم إضافة عمود image_url بنجاح!</p>";
    } else {
        echo "<p style='color:red'>❌ فشل إضافة العمود: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:blue'>ℹ️ عمود image_url موجود مسبقاً</p>";
}

echo "<p><a href='admin/admin_categories.php'>العودة لإدارة الفئات</a></p>";
echo "<p style='color:orange'>⚠️ يرجى حذف هذا الملف بعد التشغيل</p>";
?>
