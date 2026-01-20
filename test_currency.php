<?php
/**
 * ملف اختبار العملة
 * افتح هذا الملف في المتصفح للتحقق من إعدادات العملة
 */

// تحميل إعدادات العملة
include 'app/config/currency.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار العملة</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1a1a1a; color: white; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { color: #ff3e3e; text-align: center; }
        .test { background: #2a2a2a; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .test h3 { margin-top: 0; color: #ffc107; }
        .success { color: #28a745; }
        .info { color: #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: right; border-bottom: 1px solid #444; }
        th { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <h1>💰 اختبار إعدادات العملة</h1>
        
        <div class="test">
            <h3>1️⃣ العملة الحالية</h3>
            <table>
                <tr>
                    <th>الإعداد</th>
                    <th>القيمة</th>
                </tr>
                <tr>
                    <td>الكود</td>
                    <td class="success"><?php echo STORE_CURRENCY; ?></td>
                </tr>
                <tr>
                    <td>الاسم</td>
                    <td class="success"><?php echo getCurrencyName(); ?></td>
                </tr>
                <tr>
                    <td>الرمز</td>
                    <td class="success"><?php echo getCurrencySymbol(); ?></td>
                </tr>
                <tr>
                    <td>نسبة الضريبة</td>
                    <td class="success"><?php echo (getTaxRate() * 100); ?>%</td>
                </tr>
            </table>
        </div>
        
        <div class="test">
            <h3>2️⃣ أمثلة على تنسيق الأسعار</h3>
            <table>
                <tr>
                    <th>السعر</th>
                    <th>التنسيق</th>
                </tr>
                <tr>
                    <td>100</td>
                    <td class="success"><?php echo formatPrice(100); ?></td>
                </tr>
                <tr>
                    <td>1500.50</td>
                    <td class="success"><?php echo formatPrice(1500.50); ?></td>
                </tr>
                <tr>
                    <td>9900</td>
                    <td class="success"><?php echo formatPrice(9900); ?></td>
                </tr>
            </table>
        </div>
        
        <div class="test">
            <h3>3️⃣ حساب الضريبة</h3>
            <table>
                <tr>
                    <th>المبلغ</th>
                    <th>الضريبة</th>
                    <th>الإجمالي</th>
                </tr>
                <?php
                $amounts = [100, 1000, 9900];
                foreach ($amounts as $amount) {
                    $tax = calculateTax($amount);
                    $total = $amount + $tax;
                    echo "<tr>";
                    echo "<td>" . formatPrice($amount) . "</td>";
                    echo "<td>" . formatPrice($tax) . "</td>";
                    echo "<td class='success'>" . formatPrice($total) . "</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>
        
        <div class="test">
            <h3>4️⃣ متغيرات JavaScript</h3>
            <p class="info">هذه المتغيرات متاحة في JavaScript:</p>
            <pre style="background:#333; padding:10px; border-radius:5px; overflow-x:auto;">
const CURRENCY = {
    code: "<?php echo $CURRENT_CURRENCY['code']; ?>",
    symbol: "<?php echo $CURRENT_CURRENCY['symbol']; ?>",
    position: "<?php echo $CURRENT_CURRENCY['position']; ?>",
    decimals: <?php echo $CURRENT_CURRENCY['decimals']; ?>,
    taxRate: <?php echo $CURRENT_CURRENCY['tax_rate']; ?>
};
            </pre>
        </div>
        
        <div class="test" style="background: #1a3a1a; border: 1px solid #28a745;">
            <h3 style="color: #28a745;">✅ النتيجة</h3>
            <p>العملة الحالية: <strong><?php echo getCurrencyName(); ?> (<?php echo getCurrencySymbol(); ?>)</strong></p>
            <p>نسبة الضريبة: <strong><?php echo (getTaxRate() * 100); ?>%</strong></p>
            <p>مثال: 9900 = <strong><?php echo formatPrice(9900); ?></strong></p>
        </div>
    </div>
</body>
</html>
