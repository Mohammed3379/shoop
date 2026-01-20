<?php
/**
 * ===========================================
 * صفحة الصيانة
 * ===========================================
 */

$maintenance_message = $maintenance_message ?? 'المتجر تحت الصيانة حالياً، سنعود قريباً!';
$maintenance_end = function_exists('getSetting') ? getSetting('maintenance_end_date', '') : '';
$store_name = function_exists('getSetting') ? getSetting('store_name', 'مشترياتي') : 'مشترياتي';
$contact_email = function_exists('getSetting') ? getSetting('contact_email', '') : '';
$contact_phone = function_exists('getSetting') ? getSetting('contact_phone', '') : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($store_name); ?> - تحت الصيانة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .maintenance-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .maintenance-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: pulse 2s infinite;
        }
        
        .maintenance-icon i {
            font-size: 50px;
            color: white;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        h1 {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .message {
            color: #6b7280;
            font-size: 16px;
            line-height: 1.7;
            margin-bottom: 30px;
        }
        
        .countdown {
            background: #f3f4f6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .countdown-label {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .countdown-time {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .countdown-item {
            text-align: center;
        }
        
        .countdown-number {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 24px;
            font-weight: bold;
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
        }
        
        .countdown-unit {
            color: #6b7280;
            font-size: 12px;
        }
        
        .contact-info {
            border-top: 1px solid #e5e7eb;
            padding-top: 25px;
        }
        
        .contact-info p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .contact-links {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .contact-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .contact-link:hover {
            color: #764ba2;
        }
        
        .store-name {
            margin-top: 30px;
            color: #9ca3af;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        
        <h1>نحن تحت الصيانة</h1>
        
        <p class="message"><?php echo nl2br(htmlspecialchars($maintenance_message)); ?></p>
        
        <?php if ($maintenance_end): ?>
        <div class="countdown">
            <div class="countdown-label">الوقت المتبقي المتوقع</div>
            <div class="countdown-time" id="countdown">
                <div class="countdown-item">
                    <div class="countdown-number" id="days">00</div>
                    <div class="countdown-unit">يوم</div>
                </div>
                <div class="countdown-item">
                    <div class="countdown-number" id="hours">00</div>
                    <div class="countdown-unit">ساعة</div>
                </div>
                <div class="countdown-item">
                    <div class="countdown-number" id="minutes">00</div>
                    <div class="countdown-unit">دقيقة</div>
                </div>
                <div class="countdown-item">
                    <div class="countdown-number" id="seconds">00</div>
                    <div class="countdown-unit">ثانية</div>
                </div>
            </div>
        </div>
        
        <script>
            const endDate = new Date('<?php echo $maintenance_end; ?>').getTime();
            
            const countdown = setInterval(function() {
                const now = new Date().getTime();
                const distance = endDate - now;
                
                if (distance < 0) {
                    clearInterval(countdown);
                    location.reload();
                    return;
                }
                
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById('days').textContent = String(days).padStart(2, '0');
                document.getElementById('hours').textContent = String(hours).padStart(2, '0');
                document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
                document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
            }, 1000);
        </script>
        <?php endif; ?>
        
        <?php if ($contact_email || $contact_phone): ?>
        <div class="contact-info">
            <p>للتواصل معنا:</p>
            <div class="contact-links">
                <?php if ($contact_email): ?>
                <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="contact-link">
                    <i class="fas fa-envelope"></i>
                    <?php echo htmlspecialchars($contact_email); ?>
                </a>
                <?php endif; ?>
                
                <?php if ($contact_phone): ?>
                <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>" class="contact-link">
                    <i class="fas fa-phone"></i>
                    <?php echo htmlspecialchars($contact_phone); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="store-name"><?php echo htmlspecialchars($store_name); ?></div>
    </div>
</body>
</html>
