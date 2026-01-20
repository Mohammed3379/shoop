<?php
include 'header.php';
include 'app/config/database.php';

// تأمين الـ session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success = "";
$error = "";

// التحقق من محاولات الإرسال (Rate Limiting)
if (!isset($_SESSION['contact_attempts'])) {
    $_SESSION['contact_attempts'] = 0;
    $_SESSION['last_contact_time'] = time();
}

// إعادة تعيين العداد بعد 30 دقيقة
if (time() - $_SESSION['last_contact_time'] > 1800) {
    $_SESSION['contact_attempts'] = 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // التحقق من CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "طلب غير صالح. يرجى المحاولة مرة أخرى.";
    }
    // التحقق من عدد المحاولات
    elseif ($_SESSION['contact_attempts'] >= 3) {
        $error = "تم تجاوز عدد الرسائل المسموحة. يرجى المحاولة بعد 30 دقيقة.";
    }
    else {
        // التحقق من البيانات
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $error = "جميع الحقول مطلوبة";
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "البريد الإلكتروني غير صالح";
        }
        elseif (strlen($message) < 10) {
            $error = "الرسالة يجب أن تكون 10 أحرف على الأقل";
        }
        else {
            // حفظ الرسالة في قاعدة البيانات
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            
            if ($stmt->execute()) {
                $_SESSION['contact_attempts']++;
                $_SESSION['last_contact_time'] = time();
                $success = "تم إرسال رسالتك بنجاح. سنتواصل معك قريباً!";
                
                // إعادة تعيين الحقول
                $_POST = array();
            } else {
                $error = "حدث خطأ أثناء إرسال الرسالة. يرجى المحاولة لاحقاً.";
            }
            
            $stmt->close();
        }
    }
}

// توليد CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>



<div class="contact-container">
    <div class="contact-card">
        <div class="contact-header">
            <h1>
                <i class="fas fa-envelope"></i>
                تواصل معنا
            </h1>
            <p>نحن هنا للإجابة على استفساراتك ومساعدتك</p>
        </div>
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-group">
                <label for="name">
                    <i class="fas fa-user"></i>
                    الاسم الكامل
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    placeholder="أدخل اسمك الكامل"
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                    required 
                    maxlength="100"
                >
            </div>
            
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i>
                    البريد الإلكتروني
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="example@email.com"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    required 
                    maxlength="100"
                >
            </div>
            
            <div class="form-group">
                <label for="subject">
                    <i class="fas fa-tag"></i>
                    الموضوع
                </label>
                <input 
                    type="text" 
                    id="subject" 
                    name="subject" 
                    placeholder="موضوع الرسالة"
                    value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>"
                    required 
                    maxlength="200"
                >
            </div>
            
            <div class="form-group">
                <label for="message">
                    <i class="fas fa-comment-dots"></i>
                    الرسالة
                </label>
                <textarea 
                    id="message" 
                    name="message" 
                    placeholder="اكتب رسالتك هنا..."
                    required
                    maxlength="1000"
                ><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
            </div>
            
            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i>
                إرسال الرسالة
            </button>
        </form>
        
        <div class="contact-info">
            <div class="info-item">
                <i class="fas fa-phone"></i>
                <h3>الهاتف</h3>
                <p>+966 XX XXX XXXX</p>
            </div>
            
            <div class="info-item">
                <i class="fas fa-envelope"></i>
                <h3>البريد الإلكتروني</h3>
                <p>info@example.com</p>
            </div>
            
            <div class="info-item">
                <i class="fas fa-map-marker-alt"></i>
                <h3>العنوان</h3>
                <p>الرياض، المملكة العربية السعودية</p>
            </div>
        </div>
    </div>
</div>

<script src="public/js/script.js"></script>
<?php include 'footer.php'; ?>
