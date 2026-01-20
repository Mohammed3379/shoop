<?php
/**
 * ملف اختبار save_order.php
 * افتح هذا الملف في المتصفح للتحقق من أن كل شيء يعمل
 */

session_start();

// محاكاة تسجيل الدخول للاختبار
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // مستخدم اختباري
}

include 'app/config/database.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار save_order.php</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1a1a1a; color: white; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { color: #ff3e3e; text-align: center; }
        .test { background: #2a2a2a; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .test h3 { margin-top: 0; color: #ffc107; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        button { background: #ff3e3e; color: white; border: none; padding: 15px 30px; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #ff5555; }
        pre { background: #333; padding: 10px; border-radius: 5px; overflow-x: auto; }
        #result { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 اختبار save_order.php</h1>
        
        <!-- اختبار 1: الاتصال بقاعدة البيانات -->
        <div class="test">
            <h3>1️⃣ اختبار الاتصال بقاعدة البيانات</h3>
            <?php
            if ($conn && !$conn->connect_error) {
                echo "<p class='success'>✅ الاتصال بقاعدة البيانات: نجح</p>";
            } else {
                echo "<p class='error'>❌ الاتصال بقاعدة البيانات: فشل</p>";
                echo "<p class='error'>الخطأ: " . ($conn->connect_error ?? 'غير معروف') . "</p>";
            }
            ?>
        </div>
        
        <!-- اختبار 2: جدول orders -->
        <div class="test">
            <h3>2️⃣ اختبار جدول orders</h3>
            <?php
            $result = $conn->query("SHOW TABLES LIKE 'orders'");
            if ($result && $result->num_rows > 0) {
                echo "<p class='success'>✅ جدول orders: موجود</p>";
                
                // عرض أعمدة الجدول
                $columns = $conn->query("DESCRIBE orders");
                if ($columns) {
                    echo "<p class='info'>الأعمدة:</p><ul>";
                    while ($col = $columns->fetch_assoc()) {
                        echo "<li>" . $col['Field'] . " (" . $col['Type'] . ")</li>";
                    }
                    echo "</ul>";
                }
            } else {
                echo "<p class='error'>❌ جدول orders: غير موجود</p>";
                echo "<p class='info'>💡 شغّل ملف orders_table.sql لإنشاء الجدول</p>";
            }
            ?>
        </div>
        
        <!-- اختبار 3: الجلسة -->
        <div class="test">
            <h3>3️⃣ اختبار الجلسة</h3>
            <?php
            if (isset($_SESSION['user_id'])) {
                echo "<p class='success'>✅ الجلسة: نشطة (user_id: " . $_SESSION['user_id'] . ")</p>";
            } else {
                echo "<p class='error'>❌ الجلسة: غير نشطة</p>";
            }
            ?>
        </div>
        
        <!-- اختبار 4: إرسال طلب تجريبي -->
        <div class="test">
            <h3>4️⃣ اختبار إرسال طلب تجريبي</h3>
            <p class="info">اضغط الزر لإرسال طلب تجريبي:</p>
            <button onclick="testOrder()">🚀 إرسال طلب تجريبي</button>
            <div id="result"></div>
        </div>
    </div>
    
    <script>
    function testOrder() {
        const resultDiv = document.getElementById('result');
        resultDiv.innerHTML = '<p class="info">⏳ جاري الإرسال...</p>';
        
        const testData = {
            name: "اختبار",
            phone: "0123456789",
            address: "عنوان اختباري",
            lat: "15.3694",
            lng: "44.1910",
            total: 100.50,
            items: [
                { name: "منتج اختباري 1", quantity: 2 },
                { name: "منتج اختباري 2", quantity: 1 }
            ]
        };
        
        console.log("Sending data:", testData);
        
        fetch('save_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(testData)
        })
        .then(res => {
            console.log("Response status:", res.status);
            return res.text();
        })
        .then(text => {
            console.log("Response text:", text);
            try {
                const data = JSON.parse(text);
                if (data.status === 'success') {
                    resultDiv.innerHTML = `
                        <p class="success">✅ نجح الطلب!</p>
                        <p class="info">رقم الطلب: ${data.order_id}</p>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <p class="error">❌ فشل الطلب</p>
                        <p class="error">الخطأ: ${data.message}</p>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                }
            } catch (e) {
                resultDiv.innerHTML = `
                    <p class="error">❌ خطأ في تحليل الرد</p>
                    <pre>${text}</pre>
                `;
            }
        })
        .catch(err => {
            console.error("Error:", err);
            resultDiv.innerHTML = `
                <p class="error">❌ خطأ في الاتصال</p>
                <p class="error">${err.message}</p>
            `;
        });
    }
    </script>
</body>
</html>
