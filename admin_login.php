<?php
/**
 * صفحة تسجيل دخول الإدارة
 */
include '../app/config/database.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// ========================================
// بيانات الأدمن الافتراضية
// تحذير: يجب تغيير هذه البيانات فوراً!
// ========================================
define('ADMIN_USERNAME', 'admin');
// كلمة المرور مشفرة بـ password_hash - القيمة الافتراضية: admin123
// لتغييرها، استخدم: echo password_hash('كلمة_المرور_الجديدة', PASSWORD_BCRYPT);
define('ADMIN_PASSWORD_HASH', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); // admin123
define('ADMIN_NAME', 'مدير النظام');

// إذا كان مسجل دخول، توجيه للوحة التحكم
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit();
}

// توليد CSRF token
if (!isset($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}

// Rate Limiting
if (!isset($_SESSION['admin_login_attempts'])) {
    $_SESSION['admin_login_attempts'] = 0;
    $_SESSION['admin_last_attempt'] = time();
}

if (time() - $_SESSION['admin_last_attempt'] > 900) {
    $_SESSION['admin_login_attempts'] = 0;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // التحقق من CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_login_csrf']) {
        $error = "طلب غير صالح!";
    }
    elseif ($_SESSION['admin_login_attempts'] >= 5) {
        $error = "تم تجاوز عدد المحاولات. انتظر 15 دقيقة.";
    }
    else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $login_success = false;

        // ========================================
        // طريقة 1: التحقق من البيانات الافتراضية أولاً
        // ========================================
        if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
            $login_success = true;
            $_SESSION['admin_id'] = 1;
            $_SESSION['admin_name'] = ADMIN_NAME;
            $_SESSION['admin_role'] = ['id' => 1, 'name' => 'super_admin', 'name_ar' => 'المدير العام'];
            $_SESSION['admin_permissions'] = ['*'];
        }
        
        // ========================================
        // طريقة 2: التحقق من جدول admins (إذا فشلت الطريقة الأولى)
        // ========================================
        if (!$login_success && isset($conn)) {
            try {
                // استعلام بسيط يعمل مع أي هيكل جدول
                $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $admin = $result->fetch_assoc();
                        
                        // التحقق من كلمة المرور (مشفرة أو عادية)
                        if (password_verify($password, $admin['password']) || $password === $admin['password']) {
                            $login_success = true;
                            $_SESSION['admin_id'] = $admin['id'];
                            $_SESSION['admin_name'] = $admin['username'];
                            // سيتم تحميل الصلاحيات لاحقاً من loadAdminPermissions
                            $_SESSION['admin_role'] = null;
                            $_SESSION['admin_permissions'] = null;
                        }
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                // تجاهل أخطاء قاعدة البيانات
            }
        }

        // ========================================
        // معالجة النتيجة
        // ========================================
        if ($login_success) {
            $_SESSION['admin_login_attempts'] = 0;
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_last_activity'] = time();
            
            header("Location: admin.php");
            exit();
        } else {
            if (empty($error)) {
                $_SESSION['admin_login_attempts']++;
                $_SESSION['admin_last_attempt'] = time();
                $error = "اسم المستخدم أو كلمة المرور خطأ!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دخول لوحة الإدارة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/main.css">
    <style>
        .admin-login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }
        .admin-login-card {
            background: #1e1e1e;
            padding: 40px;
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
            border: 1px solid #333;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .admin-login-card h1 {
            color: #ff3e3e;
            text-align: center;
            margin-bottom: 30px;
        }
        .admin-login-card h1 i {
            display: block;
            font-size: 50px;
            margin-bottom: 15px;
        }
        .input-group { margin-bottom: 20px; }
        .input-group label { color: #aaa; display: block; margin-bottom: 8px; }
        .input-group input {
            width: 100%;
            padding: 15px;
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 10px;
            color: white;
            font-size: 16px;
        }
        .input-group input:focus {
            border-color: #ff3e3e;
            outline: none;
        }
        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ff3e3e, #ff6b6b);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255,62,62,0.3);
        }
        .error-msg {
            background: rgba(220,53,69,0.1);
            color: #dc3545;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* Responsive Styles */
        @media (max-width: 480px) {
            .admin-login-container {
                padding: 15px;
            }
            
            .admin-login-card {
                padding: 25px 20px;
                border-radius: 15px;
            }
            
            .admin-login-card h1 {
                margin-bottom: 25px;
            }
            
            .admin-login-card h1 i {
                font-size: 40px;
                margin-bottom: 10px;
            }
            
            .input-group {
                margin-bottom: 15px;
            }
            
            .input-group input {
                padding: 12px;
                font-size: 14px;
            }
            
            .login-btn {
                padding: 12px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-card">
            <h1>
                <i class="fas fa-shield-alt"></i>
                لوحة الإدارة
            </h1>
            
            <?php if($error): ?>
                <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if(isset($_GET['expired'])): ?>
                <div class="error-msg"><i class="fas fa-clock"></i> انتهت الجلسة. سجل دخولك مجدداً.</div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_login_csrf']); ?>">
                
                <div class="input-group">
                    <label><i class="fas fa-user"></i> اسم المستخدم أو البريد</label>
                    <input type="text" name="username" required autocomplete="username" maxlength="100">
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-lock"></i> كلمة المرور</label>
                    <input type="password" name="password" required autocomplete="current-password" maxlength="255">
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> دخول
                </button>
            </form>
        </div>
    </div>
</body>
</html>
