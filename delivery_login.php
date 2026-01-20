<?php
include 'app/config/database.php';

// تأمين الـ session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// توليد CSRF token
if (!isset($_SESSION['csrf_token_delivery'])) {
    $_SESSION['csrf_token_delivery'] = bin2hex(random_bytes(32));
}

// Rate Limiting
if (!isset($_SESSION['delivery_login_attempts'])) {
    $_SESSION['delivery_login_attempts'] = 0;
    $_SESSION['delivery_last_attempt'] = time();
}

// إعادة تعيين بعد 15 دقيقة
if (time() - $_SESSION['delivery_last_attempt'] > 900) {
    $_SESSION['delivery_login_attempts'] = 0;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // التحقق من CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token_delivery']) {
        $error = "طلب غير صالح!";
    }
    // التحقق من Rate Limiting
    elseif ($_SESSION['delivery_login_attempts'] >= 5) {
        $error = "تم تجاوز عدد المحاولات. انتظر 15 دقيقة.";
    }
    else {
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];

        // استخدام Prepared Statement
        $stmt = $conn->prepare("SELECT id, name, password, status FROM delivery_agents WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $agent = $result->fetch_assoc();
            
            // التحقق من حالة الحساب
            if ($agent['status'] == 'banned') {
                $error = "حسابك محظور. تواصل مع الإدارة.";
            }
            // التحقق من كلمة المرور (تدعم المشفرة والقديمة)
            elseif (password_verify($password, $agent['password']) || $agent['password'] === $password) {
                // إعادة تعيين المحاولات
                $_SESSION['delivery_login_attempts'] = 0;
                
                // إعادة توليد session ID
                session_regenerate_id(true);
                
                $_SESSION['agent_id'] = $agent['id'];
                $_SESSION['agent_name'] = $agent['name'];
                $_SESSION['agent_logged_in'] = true;
                
                // تحديث كلمة المرور للتشفير إذا كانت قديمة
                if ($agent['password'] === $password) {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $update = $conn->prepare("UPDATE delivery_agents SET password = ? WHERE id = ?");
                    $update->bind_param("si", $hashed, $agent['id']);
                    $update->execute();
                }
                
                header("Location: delivery.php");
                exit();
            } else {
                $_SESSION['delivery_login_attempts']++;
                $_SESSION['delivery_last_attempt'] = time();
                $error = "رقم الهاتف أو كلمة المرور خطأ ❌";
            }
        } else {
            $_SESSION['delivery_login_attempts']++;
            $_SESSION['delivery_last_attempt'] = time();
            $error = "رقم الهاتف أو كلمة المرور خطأ ❌";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة السائقين</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="public/css/main.css">
</head>
<body>

    <div class="login-card">
        <div class="login-icon"><i class="fas fa-motorcycle"></i></div>
        <h2 class="login-title">بوابة السائقين</h2>
        <p class="sub-title">أدخل بياناتك لاستلام المهام</p>

        <?php if($error): ?>
            <div class="error-msg"><i class="fas fa-times-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token_delivery']); ?>">
            
            <div class="input-wrapper">
                <input type="tel" name="phone" placeholder="رقم الهاتف" required autocomplete="off" maxlength="15">
                <i class="fas fa-phone-alt"></i>
            </div>

            <div class="input-wrapper">
                <input type="password" name="password" placeholder="كلمة المرور" required maxlength="255">
                <i class="fas fa-lock"></i>
            </div>

            <button type="submit" class="login-btn">
                تسجيل الدخول <i class="fas fa-sign-in-alt"></i>
            </button>
        </form>
        
        <p class="footer-note">
            نسيت كلمة المرور؟ تواصل مع الإدارة.
        </p>
    </div>

</body>
</html>