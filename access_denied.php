<?php
/**
 * صفحة رفض الوصول - غير مصرح
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>غير مصرح - لوحة الإدارة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .access-denied {
            background: #1e1e2d;
            border-radius: 20px;
            padding: 50px 40px;
            max-width: 450px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            border: 1px solid #2d2d3a;
        }
        .icon-container {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #dc3545, #c82333);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .icon-container i {
            font-size: 45px;
            color: white;
        }
        h1 {
            color: #dc3545;
            font-size: 28px;
            margin-bottom: 15px;
        }
        p {
            color: #a0a0a0;
            font-size: 16px;
            line-height: 1.7;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(99, 102, 241, 0.4);
        }
        .btn-secondary {
            background: #2d2d3a;
            color: #a0a0a0;
            margin-right: 10px;
        }
        .btn-secondary:hover {
            background: #3d3d4a;
            color: white;
        }
        .error-code {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #2d2d3a;
            color: #666;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="access-denied">
        <div class="icon-container">
            <i class="fas fa-ban"></i>
        </div>
        <h1>غير مصرح بالوصول</h1>
        <p>عذراً، ليس لديك الصلاحيات الكافية للوصول إلى هذه الصفحة. إذا كنت تعتقد أن هذا خطأ، يرجى التواصل مع مدير النظام.</p>
        <div>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i>
                رجوع
            </a>
            <a href="admin.php" class="btn btn-primary">
                <i class="fas fa-home"></i>
                لوحة التحكم
            </a>
        </div>
        <div class="error-code">
            رمز الخطأ: 403 Forbidden
        </div>
    </div>
</body>
</html>
