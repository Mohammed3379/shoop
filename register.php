<?php
include 'app/config/database.php';

// تأمين الـ session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// توليد CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // التحقق من CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "طلب غير صالح. يرجى المحاولة مرة أخرى.";
    } else {
        // تنظيف وتحقق من البيانات
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $password = $_POST['password'];

        // التحقق من صحة البيانات
        if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($password)) {
            $error = "جميع الحقول مطلوبة!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "البريد الإلكتروني غير صالح!";
        } elseif (strlen($password) < 6) {
            $error = "كلمة المرور يجب أن تكون 6 أحرف على الأقل!";
        } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            $error = "رقم الهاتف غير صالح!";
        } elseif (strlen($name) < 3 || strlen($name) > 100) {
            $error = "الاسم يجب أن يكون بين 3 و 100 حرف!";
        } else {
            // التحقق هل البريد مسجل مسبقاً؟ (Prepared Statement)
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "هذا البريد الإلكتروني مسجل بالفعل! 🚫";
            } else {
                // تشفير كلمة المرور
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // إدخال البيانات باستخدام Prepared Statement
                $insert_stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, address, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                $insert_stmt->bind_param("sssss", $name, $email, $hashed_password, $phone, $address);
                
                if ($insert_stmt->execute()) {
                    // إعادة توليد session ID لمنع session fixation
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $conn->insert_id;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['last_activity'] = time();
                    
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "حدث خطأ في التسجيل! يرجى المحاولة لاحقاً.";
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب - MyShop</title>
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
            overflow-x: hidden;
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
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(139, 92, 246, 0.1));
            animation: float 20s infinite ease-in-out;
        }
        
        .bg-shapes span:nth-child(1) { width: 300px; height: 300px; top: -100px; left: -100px; animation-delay: 0s; }
        .bg-shapes span:nth-child(2) { width: 200px; height: 200px; bottom: -50px; right: -50px; animation-delay: -5s; }
        .bg-shapes span:nth-child(3) { width: 150px; height: 150px; top: 40%; right: 10%; animation-delay: -10s; }
        .bg-shapes span:nth-child(4) { width: 100px; height: 100px; bottom: 30%; left: 15%; animation-delay: -15s; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.5; }
            50% { transform: translateY(-30px) rotate(180deg); opacity: 0.8; }
        }
        
        /* الحاوية */
        .register-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
        }
        
        /* الكارت */
        .register-card {
            background: linear-gradient(145deg, rgba(30, 30, 46, 0.9), rgba(37, 37, 56, 0.9));
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 35px 30px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }
        
        /* الشعار */
        .register-logo {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .register-logo .logo-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 28px;
            color: white;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .register-logo h1 {
            color: white;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        
        .register-logo p {
            color: #888;
            font-size: 13px;
        }
        
        /* خطوات التسجيل */
        .steps-indicator {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 25px;
        }
        
        .step {
            width: 30px;
            height: 4px;
            background: #333;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .step.active {
            background: linear-gradient(90deg, #10b981, #059669);
            width: 50px;
        }
        
        /* رسائل */
        .error-msg, .success-msg {
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-msg {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        
        .success-msg {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }
        
        /* حقول الإدخال */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            color: #aaa;
            font-size: 12px;
            margin-bottom: 6px;
            font-weight: 500;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 13px 18px 13px 45px;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            color: white;
            font-size: 14px;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .input-wrapper input::placeholder {
            color: #555;
        }
        
        .input-wrapper input:focus {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.05);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
        }
        
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #555;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .input-wrapper input:focus + i {
            color: #10b981;
        }
        
        /* مؤشر قوة كلمة المرور */
        .password-strength {
            display: flex;
            gap: 4px;
            margin-top: 8px;
        }
        
        .strength-bar {
            flex: 1;
            height: 3px;
            background: #333;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .strength-bar.weak { background: #ef4444; }
        .strength-bar.medium { background: #f59e0b; }
        .strength-bar.strong { background: #10b981; }
        
        .strength-text {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        
        /* الشروط */
        .terms {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .terms input {
            display: none;
        }
        
        .terms .checkmark {
            width: 18px;
            height: 18px;
            min-width: 18px;
            border: 2px solid #444;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 2px;
        }
        
        .terms input:checked + .checkmark {
            background: #10b981;
            border-color: #10b981;
        }
        
        .terms .checkmark i {
            color: white;
            font-size: 9px;
            opacity: 0;
        }
        
        .terms input:checked + .checkmark i {
            opacity: 1;
        }
        
        .terms span {
            color: #888;
            font-size: 12px;
            line-height: 1.5;
        }
        
        .terms a {
            color: #10b981;
            text-decoration: none;
        }
        
        /* زر التسجيل */
        .register-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .register-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(16, 185, 129, 0.4);
        }
        
        .register-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* الفاصل */
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
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
            color: #555;
            font-size: 12px;
        }
        
        /* رابط تسجيل الدخول */
        .login-link {
            text-align: center;
            color: #888;
            font-size: 13px;
        }
        
        .login-link a {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
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
        
        /* المميزات */
        .features {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .feature {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-size: 11px;
        }
        
        .feature i {
            color: #10b981;
            font-size: 12px;
        }
        
        /* التجاوب */
        @media (max-width: 500px) {
            body {
                padding: 15px;
            }
            
            .register-card {
                padding: 25px 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .register-logo .logo-icon {
                width: 55px;
                height: 55px;
                font-size: 24px;
            }
            
            .register-logo h1 {
                font-size: 18px;
            }
            
            .input-wrapper input {
                padding: 12px 15px 12px 40px;
                font-size: 13px;
            }
            
            .features {
                gap: 12px;
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
    
    <div class="register-container">
        <div class="register-card">
            <!-- الشعار -->
            <div class="register-logo">
                <div class="logo-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>إنشاء حساب جديد</h1>
                <p>انضم إلينا واستمتع بتجربة تسوق مميزة</p>
            </div>
            
            <!-- خطوات -->
            <div class="steps-indicator">
                <div class="step active"></div>
                <div class="step"></div>
                <div class="step"></div>
            </div>
            
            <!-- رسالة الخطأ -->
            <?php if($error): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- النموذج -->
            <form method="POST" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>الاسم الكامل</label>
                        <div class="input-wrapper">
                            <input type="text" name="name" placeholder="أحمد محمد" required maxlength="100" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>رقم الهاتف</label>
                        <div class="input-wrapper">
                            <input type="tel" name="phone" placeholder="777123456" required pattern="[0-9]{9,15}" maxlength="15" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" placeholder="example@email.com" required maxlength="100" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>العنوان</label>
                    <div class="input-wrapper">
                        <input type="text" name="address" placeholder="المدينة، الحي، الشارع" required maxlength="255" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" placeholder="••••••••" required minlength="6" maxlength="255" oninput="checkPasswordStrength(this.value)">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar" id="bar1"></div>
                        <div class="strength-bar" id="bar2"></div>
                        <div class="strength-bar" id="bar3"></div>
                        <div class="strength-bar" id="bar4"></div>
                    </div>
                    <div class="strength-text" id="strengthText">أدخل كلمة مرور قوية</div>
                </div>
                
                <label class="terms">
                    <input type="checkbox" name="terms" required>
                    <span class="checkmark"><i class="fas fa-check"></i></span>
                    <span>أوافق على <a href="#">الشروط والأحكام</a> و <a href="#">سياسة الخصوصية</a></span>
                </label>
                
                <button type="submit" class="register-btn">
                    <i class="fas fa-user-check"></i>
                    إنشاء الحساب
                </button>
            </form>
            
            <div class="divider">
                <span>أو</span>
            </div>
            
            <div class="login-link">
                لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a>
            </div>
        </div>
        
        <div class="back-home">
            <a href="index.php">
                <i class="fas fa-arrow-right"></i>
                العودة للرئيسية
            </a>
        </div>
        
        <!-- المميزات -->
        <div class="features">
            <div class="feature">
                <i class="fas fa-shield-alt"></i>
                <span>حماية بياناتك</span>
            </div>
            <div class="feature">
                <i class="fas fa-truck"></i>
                <span>توصيل سريع</span>
            </div>
            <div class="feature">
                <i class="fas fa-undo"></i>
                <span>إرجاع مجاني</span>
            </div>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength(password) {
            const bars = [
                document.getElementById('bar1'),
                document.getElementById('bar2'),
                document.getElementById('bar3'),
                document.getElementById('bar4')
            ];
            const text = document.getElementById('strengthText');
            
            // إعادة تعيين
            bars.forEach(bar => bar.className = 'strength-bar');
            
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const levels = ['', 'weak', 'weak', 'medium', 'strong', 'strong'];
            const texts = ['أدخل كلمة مرور', 'ضعيفة جداً', 'ضعيفة', 'متوسطة', 'قوية', 'قوية جداً'];
            const colors = ['#666', '#ef4444', '#ef4444', '#f59e0b', '#10b981', '#10b981'];
            
            for (let i = 0; i < Math.min(strength, 4); i++) {
                bars[i].classList.add(levels[strength]);
            }
            
            text.textContent = texts[strength];
            text.style.color = colors[strength];
        }
    </script>
</body>
</html>