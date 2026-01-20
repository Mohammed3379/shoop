<?php
include 'app/config/database.php';

// تأمين الـ session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// إعادة توليد session ID لمنع session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

$error = "";

// التحقق من محاولات تسجيل الدخول الفاشلة (Rate Limiting)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// إعادة تعيين العداد بعد 15 دقيقة
if (time() - $_SESSION['last_attempt_time'] > 900) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // التحقق من CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "طلب غير صالح. يرجى المحاولة مرة أخرى.";
    }
    // التحقق من عدد المحاولات
    elseif ($_SESSION['login_attempts'] >= 5) {
        $error = "تم تجاوز عدد المحاولات المسموحة. يرجى المحاولة بعد 15 دقيقة.";
    }
    else {
        // استخدام prepared statements لمنع SQL injection
        $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $_POST['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($_POST['password'], $user['password'])) {
                // نجح تسجيل الدخول - إعادة تعيين المحاولات
                $_SESSION['login_attempts'] = 0;
                
                // إعادة توليد session ID لمنع session fixation
                session_regenerate_id(true);
                
                // حفظ بيانات المستخدم
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['show_welcome_alert'] = true;
                $_SESSION['last_activity'] = time();
                
                $stmt->close();
                header("Location: index.php");
                exit();
            }
        }
        
        // فشل تسجيل الدخول - رسالة عامة لعدم إعطاء معلومات للمهاجمين
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();
        $error = "البريد الإلكتروني أو كلمة المرور غير صحيحة";
        
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

// توليد CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - MyShop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #16213e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* خلفية متحركة */
        .bg-shapes {
            position: fixed;
            inset: 0;
            overflow: hidden;
            z-index: 0;
        }
        
        .bg-shapes span {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 62, 62, 0.1), rgba(139, 92, 246, 0.1));
            animation: float 20s infinite ease-in-out;
        }
        
        .bg-shapes span:nth-child(1) { width: 300px; height: 300px; top: -100px; right: -100px; animation-delay: 0s; }
        .bg-shapes span:nth-child(2) { width: 200px; height: 200px; bottom: -50px; left: -50px; animation-delay: -5s; }
        .bg-shapes span:nth-child(3) { width: 150px; height: 150px; top: 50%; left: 10%; animation-delay: -10s; }
        .bg-shapes span:nth-child(4) { width: 100px; height: 100px; bottom: 20%; right: 15%; animation-delay: -15s; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.5; }
            50% { transform: translateY(-30px) rotate(180deg); opacity: 0.8; }
        }
        
        /* الحاوية الرئيسية */
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
        }
        
        /* الكارت */
        .login-card {
            background: linear-gradient(145deg, rgba(30, 30, 46, 0.9), rgba(37, 37, 56, 0.9));
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px 35px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }
        
        /* الشعار */
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #ff3e3e, #ff6b6b);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 30px;
            color: white;
            box-shadow: 0 10px 30px rgba(255, 62, 62, 0.3);
        }
        
        .login-logo h1 {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .login-logo p {
            color: #888;
            font-size: 14px;
        }
        
        /* رسالة الخطأ */
        .error-msg {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-msg i {
            font-size: 18px;
        }
        
        /* حقول الإدخال */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #aaa;
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            color: white;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .input-wrapper input::placeholder {
            color: #666;
        }
        
        .input-wrapper input:focus {
            border-color: #ff3e3e;
            background: rgba(255, 62, 62, 0.05);
            box-shadow: 0 0 20px rgba(255, 62, 62, 0.15);
        }
        
        .input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 16px;
            transition: color 0.3s ease;
        }
        
        .input-wrapper input:focus + i {
            color: #ff3e3e;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
            font-size: 16px;
            transition: color 0.3s ease;
        }
        
        .toggle-password:hover {
            color: #ff3e3e;
        }
        
        /* تذكرني */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .remember-me input {
            display: none;
        }
        
        .remember-me .checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid #444;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .remember-me input:checked + .checkmark {
            background: #ff3e3e;
            border-color: #ff3e3e;
        }
        
        .remember-me .checkmark i {
            color: white;
            font-size: 10px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .remember-me input:checked + .checkmark i {
            opacity: 1;
        }
        
        .remember-me span {
            color: #888;
            font-size: 13px;
        }
        
        .forgot-password {
            color: #ff3e3e;
            font-size: 13px;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: #ff6b6b;
        }
        
        /* زر الدخول */
        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #ff3e3e, #ff6b6b);
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(255, 62, 62, 0.3);
        }
        
        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(255, 62, 62, 0.4);
        }
        
        .login-btn:active {
            transform: translateY(-1px);
        }
        
        .login-btn i {
            font-size: 18px;
        }
        
        /* الفاصل */
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            gap: 15px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        }
        
        .divider span {
            color: #666;
            font-size: 12px;
        }
        
        /* رابط التسجيل */
        .register-link {
            text-align: center;
            color: #888;
            font-size: 14px;
        }
        
        .register-link a {
            color: #ff3e3e;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .register-link a:hover {
            color: #ff6b6b;
        }
        
        /* رابط العودة */
        .back-home {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-home a {
            color: #666;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }
        
        .back-home a:hover {
            color: #fff;
        }
        
        /* التجاوب */
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .login-card {
                padding: 30px 25px;
                border-radius: 20px;
            }
            
            .login-logo .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 26px;
            }
            
            .login-logo h1 {
                font-size: 20px;
            }
            
            .input-wrapper input {
                padding: 14px 18px 14px 45px;
                font-size: 14px;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .login-btn {
                padding: 14px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- خلفية متحركة -->
    <div class="bg-shapes">
        <span></span>
        <span></span>
        <span></span>
        <span></span>
    </div>
    
    <div class="login-container">
        <div class="login-card">
            <!-- الشعار -->
            <div class="login-logo">
                <div class="logo-icon">
                    <i class="fas fa-store"></i>
                </div>
                <h1>مرحباً </h1>
                <p>سجل دخولك للمتابعة</p>
            </div>
            
            <!-- رسالة الخطأ -->
            <?php if($error): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- النموذج -->
            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" placeholder="example@email.com" required autocomplete="email" maxlength="100">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" placeholder="••••••••" required autocomplete="current-password" maxlength="255">
                        <i class="fas fa-lock"></i>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span class="checkmark"><i class="fas fa-check"></i></span>
                        <span>تذكرني</span>
                    </label>
                    <a href="#" class="forgot-password">نسيت كلمة المرور؟</a>
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    تسجيل الدخول
                </button>
            </form>
            
            <div class="divider">
                <span>أو</span>
            </div>
            
            <div class="register-link">
                ليس لديك حساب؟ <a href="register.php">إنشاء حساب جديد</a>
            </div>
        </div>
        
        <div class="back-home">
            <a href="index.php">
                <i class="fas fa-arrow-right"></i>
                العودة للرئيسية
            </a>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>