<?php
/**
 * ===========================================
 * الإعدادات العامة للمتجر - لوحة التحكم
 * ===========================================
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

// التحقق من صلاحية عرض الإعدادات
requirePermission('settings.view');

// تحميل ملف الإعدادات
include '../app/config/settings.php';
loadAllSettings($conn);

$page_title = 'الإعدادات العامة';
$page_icon = 'fa-cog';

$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

// صلاحية التعديل
$can_edit = hasPermission('settings.edit');

// قائمة العملات المتاحة
$available_currencies = [
    'SAR' => ['name' => 'ريال سعودي', 'symbol' => 'ر.س'],
    'EGP' => ['name' => 'جنيه مصري', 'symbol' => 'ج.م'],
    'USD' => ['name' => 'دولار أمريكي', 'symbol' => '$'],
    'AED' => ['name' => 'درهم إماراتي', 'symbol' => 'د.إ'],
    'KWD' => ['name' => 'دينار كويتي', 'symbol' => 'د.ك'],
    'QAR' => ['name' => 'ريال قطري', 'symbol' => 'ر.ق'],
    'BHD' => ['name' => 'دينار بحريني', 'symbol' => 'د.ب'],
    'OMR' => ['name' => 'ريال عماني', 'symbol' => 'ر.ع'],
    'JOD' => ['name' => 'دينار أردني', 'symbol' => 'د.أ'],
    'EUR' => ['name' => 'يورو', 'symbol' => '€'],
    'GBP' => ['name' => 'جنيه إسترليني', 'symbol' => '£'],
];

// قائمة اللغات
$available_languages = [
    'ar' => 'العربية',
    'en' => 'English',
];

// قائمة المناطق الزمنية
$timezones = [
    'Asia/Riyadh' => 'الرياض (GMT+3)',
    'Asia/Dubai' => 'دبي (GMT+4)',
    'Asia/Kuwait' => 'الكويت (GMT+3)',
    'Africa/Cairo' => 'القاهرة (GMT+2)',
    'Europe/London' => 'لندن (GMT+0)',
    'America/New_York' => 'نيويورك (GMT-5)',
];

// معالجة حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyAdminCSRF($_POST['csrf_token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        $settings_to_update = [];
        
        // معلومات المتجر الأساسية
        if (isset($_POST['save_general'])) {
            $settings_to_update['store_name'] = trim($_POST['store_name'] ?? '');
            $settings_to_update['store_description'] = trim($_POST['store_description'] ?? '');
            
            // رفع الشعار
            if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
                $logo_path = handleLogoUpload($_FILES['store_logo'], 'logo');
                if ($logo_path) {
                    $settings_to_update['store_logo'] = $logo_path;
                }
            }
        }
        
        // معلومات التواصل
        if (isset($_POST['save_contact'])) {
            $settings_to_update['contact_phone'] = trim($_POST['contact_phone'] ?? '');
            $settings_to_update['contact_email'] = trim($_POST['contact_email'] ?? '');
            $settings_to_update['contact_address'] = trim($_POST['contact_address'] ?? '');
            $settings_to_update['contact_whatsapp'] = trim($_POST['contact_whatsapp'] ?? '');
        }
        
        // وسائل التواصل الاجتماعي
        if (isset($_POST['save_social'])) {
            $settings_to_update['social_facebook'] = trim($_POST['social_facebook'] ?? '');
            $settings_to_update['social_twitter'] = trim($_POST['social_twitter'] ?? '');
            $settings_to_update['social_instagram'] = trim($_POST['social_instagram'] ?? '');
            $settings_to_update['social_snapchat'] = trim($_POST['social_snapchat'] ?? '');
            $settings_to_update['social_tiktok'] = trim($_POST['social_tiktok'] ?? '');
            $settings_to_update['social_youtube'] = trim($_POST['social_youtube'] ?? '');
        }
        
        // إعدادات العملة
        if (isset($_POST['save_currency'])) {
            $settings_to_update['currency_code'] = $_POST['currency_code'] ?? 'SAR';
            $settings_to_update['currency_symbol'] = trim($_POST['currency_symbol'] ?? 'ر.س');
            $settings_to_update['currency_position'] = $_POST['currency_position'] ?? 'after';
            $settings_to_update['currency_decimals'] = intval($_POST['currency_decimals'] ?? 2);
            $settings_to_update['tax_rate'] = floatval($_POST['tax_rate'] ?? 15);
            $settings_to_update['tax_enabled'] = isset($_POST['tax_enabled']) ? '1' : '0';
            
            // تحديث ملف العملة أيضاً
            updateCurrencyFile($settings_to_update);
        }
        
        // إعدادات اللغة والمنطقة
        if (isset($_POST['save_locale'])) {
            $settings_to_update['default_language'] = $_POST['default_language'] ?? 'ar';
            $settings_to_update['timezone'] = $_POST['timezone'] ?? 'Asia/Riyadh';
            $settings_to_update['date_format'] = $_POST['date_format'] ?? 'd/m/Y';
            $settings_to_update['time_format'] = $_POST['time_format'] ?? 'H:i';
        }
        
        // حالة المتجر
        if (isset($_POST['save_status'])) {
            $settings_to_update['store_status'] = $_POST['store_status'] ?? 'open';
            $settings_to_update['maintenance_message'] = trim($_POST['maintenance_message'] ?? '');
            $settings_to_update['maintenance_end_date'] = $_POST['maintenance_end_date'] ?? '';
        }
        
        // حفظ الإعدادات
        if (!empty($settings_to_update)) {
            if (updateSettings($conn, $settings_to_update)) {
                $message = 'تم حفظ الإعدادات بنجاح! ✅';
                $message_type = 'success';
                logAdminAction('update_settings', 'تم تحديث إعدادات المتجر');
                
                // إعادة تحميل الإعدادات
                $STORE_SETTINGS = [];
                loadAllSettings($conn);
            } else {
                $message = 'حدث خطأ أثناء حفظ الإعدادات!';
                $message_type = 'danger';
            }
        }
    }
}

/**
 * رفع الشعار
 */
function handleLogoUpload($file, $type) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    $upload_dir = __DIR__ . '/../public/uploads/settings/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $type . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'public/uploads/settings/' . $filename;
    }
    
    return false;
}

/**
 * تحديث ملف العملة
 */
function updateCurrencyFile($settings) {
    $code = $settings['currency_code'];
    $symbol = $settings['currency_symbol'];
    $position = $settings['currency_position'];
    $decimals = $settings['currency_decimals'];
    $tax_rate = $settings['tax_rate'] / 100;
    
    $config_content = "<?php
/**
 * ===========================================
 * إعدادات العملة الموحدة للمتجر
 * ===========================================
 * تم التحديث: " . date('Y-m-d H:i:s') . "
 */

if (defined('CURRENCY_LOADED')) { return; }
define('CURRENCY_LOADED', true);

if (!defined('STORE_CURRENCY')) {
    define('STORE_CURRENCY', '$code');
}

\$CURRENCIES = [
    '$code' => [
        'code' => '$code',
        'name' => '',
        'symbol' => '$symbol',
        'position' => '$position',
        'decimals' => $decimals,
        'tax_rate' => $tax_rate
    ]
];

\$CURRENT_CURRENCY = \$CURRENCIES[STORE_CURRENCY] ?? \$CURRENCIES['$code'];

if (!function_exists('formatPrice')) {
    function formatPrice(\$price, \$showSymbol = true) {
        global \$CURRENT_CURRENCY;
        \$formatted = number_format(\$price, \$CURRENT_CURRENCY['decimals']);
        if (!\$showSymbol) return \$formatted;
        if (\$CURRENT_CURRENCY['position'] === 'before') {
            return \$CURRENT_CURRENCY['symbol'] . ' ' . \$formatted;
        }
        return \$formatted . ' ' . \$CURRENT_CURRENCY['symbol'];
    }
}

if (!function_exists('getCurrencySymbol')) {
    function getCurrencySymbol() {
        global \$CURRENT_CURRENCY;
        return \$CURRENT_CURRENCY['symbol'];
    }
}

if (!function_exists('getTaxRate')) {
    function getTaxRate() {
        global \$CURRENT_CURRENCY;
        return \$CURRENT_CURRENCY['tax_rate'];
    }
}

if (!function_exists('calculateTax')) {
    function calculateTax(\$amount) {
        global \$CURRENT_CURRENCY;
        return \$amount * \$CURRENT_CURRENCY['tax_rate'];
    }
}

if (!function_exists('getCurrencyName')) {
    function getCurrencyName() {
        global \$CURRENT_CURRENCY;
        return \$CURRENT_CURRENCY['name'];
    }
}

if (!function_exists('getCurrencyCode')) {
    function getCurrencyCode() {
        global \$CURRENT_CURRENCY;
        return \$CURRENT_CURRENCY['code'];
    }
}
?>";
    
    $file_path = __DIR__ . '/../app/config/currency.php';
    return file_put_contents($file_path, $config_content);
}

// تضمين الهيدر
include 'includes/admin_header.php';
?>

<style>
.settings-tabs {
    display: flex;
    gap: 5px;
    background: var(--bg-card);
    padding: 10px;
    border-radius: var(--radius-lg);
    margin-bottom: 25px;
    flex-wrap: wrap;
    border: 1px solid var(--border-color);
}

.settings-tab {
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.settings-tab:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.settings-tab.active {
    background: var(--primary);
    color: white;
}

.settings-tab i {
    font-size: 16px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.settings-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    margin-bottom: 25px;
    overflow: hidden;
}

.settings-card-header {
    background: var(--bg-hover);
    padding: 20px 25px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 12px;
}

.settings-card-header i {
    font-size: 20px;
    color: var(--primary);
}

.settings-card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.settings-card-body {
    padding: 25px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-primary);
}

.form-group label small {
    color: var(--text-muted);
    font-weight: 400;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.btn-save {
    background: linear-gradient(135deg, var(--primary), #4f46e5);
    color: white;
    border: none;
    padding: 14px 30px;
    border-radius: var(--radius-md);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(99, 102, 241, 0.4);
}

.logo-preview {
    width: 150px;
    height: 150px;
    border: 2px dashed var(--border-color);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    overflow: hidden;
    background: var(--bg-hover);
}

.logo-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.logo-preview i {
    font-size: 40px;
    color: var(--text-muted);
}

.status-toggle {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.status-option {
    flex: 1;
    min-width: 200px;
    padding: 20px;
    border: 2px solid var(--border-color);
    border-radius: var(--radius-lg);
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.status-option:hover {
    border-color: var(--primary);
}

.status-option.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.status-option.maintenance.selected {
    border-color: #f59e0b;
    background: rgba(245, 158, 11, 0.1);
}

.status-option i {
    font-size: 30px;
    margin-bottom: 10px;
    display: block;
}

.status-option.open i { color: #10b981; }
.status-option.maintenance i { color: #f59e0b; }

.status-option h4 {
    margin: 0 0 5px 0;
    font-size: 16px;
}

.status-option p {
    margin: 0;
    font-size: 12px;
    color: var(--text-muted);
}

.social-input {
    position: relative;
}

.social-input i {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 18px;
}

.social-input input {
    padding-right: 45px;
}

.currency-preview {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    padding: 25px;
    border-radius: var(--radius-lg);
    text-align: center;
    color: white;
    margin-bottom: 20px;
}

.currency-preview .preview-label {
    font-size: 12px;
    opacity: 0.8;
    margin-bottom: 5px;
}

.currency-preview .preview-price {
    font-size: 32px;
    font-weight: 700;
}

.switch-container {
    display: flex;
    align-items: center;
    gap: 12px;
}

.switch {
    position: relative;
    width: 50px;
    height: 26px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border-color);
    transition: 0.3s;
    border-radius: 26px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--primary);
}

input:checked + .slider:before {
    transform: translateX(24px);
}

.info-box {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: var(--radius-md);
    padding: 15px;
    margin-bottom: 20px;
}

.info-box i {
    color: #3b82f6;
    margin-left: 8px;
}

.info-box p {
    margin: 0;
    font-size: 13px;
    color: var(--text-secondary);
}

.maintenance-fields {
    display: none;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px dashed var(--border-color);
}

.maintenance-fields.show {
    display: block;
}

/* تحسينات التجاوب */
@media (max-width: 768px) {
    .settings-tabs {
        flex-direction: column;
        gap: 5px;
    }
    
    .settings-tab {
        width: 100%;
        justify-content: center;
    }
    
    .form-row {
        grid-template-columns: 1fr !important;
    }
    
    .status-toggle {
        flex-direction: column;
    }
    
    .status-option {
        min-width: 100%;
    }
    
    .currency-preview {
        padding: 20px;
    }
    
    .currency-preview .preview-price {
        font-size: 24px;
    }
    
    .logo-preview {
        width: 120px;
        height: 120px;
    }
}

@media (max-width: 576px) {
    .settings-card-header {
        padding: 15px;
    }
    
    .settings-card-body {
        padding: 15px;
    }
    
    .btn-save {
        width: 100%;
        justify-content: center;
    }
    
    .social-input input {
        font-size: 13px;
    }
}
</style>

<!-- رسائل التنبيه -->
<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- التبويبات -->
<div class="settings-tabs">
    <button class="settings-tab active" onclick="showTab('general')">
        <i class="fas fa-store"></i>
        معلومات المتجر
    </button>
    <button class="settings-tab" onclick="showTab('contact')">
        <i class="fas fa-phone-alt"></i>
        التواصل
    </button>
    <button class="settings-tab" onclick="showTab('social')">
        <i class="fas fa-share-alt"></i>
        التواصل الاجتماعي
    </button>
    <button class="settings-tab" onclick="showTab('currency')">
        <i class="fas fa-coins"></i>
        العملة والضريبة
    </button>
    <button class="settings-tab" onclick="showTab('locale')">
        <i class="fas fa-globe"></i>
        اللغة والمنطقة
    </button>
    <button class="settings-tab" onclick="showTab('status')">
        <i class="fas fa-power-off"></i>
        حالة المتجر
    </button>
</div>

<!-- تبويب معلومات المتجر -->
<div id="tab-general" class="tab-content active">
    <div class="settings-card">
        <div class="settings-card-header">
            <i class="fas fa-store"></i>
            <h3>معلومات المتجر الأساسية</h3>
        </div>
        <div class="settings-card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>شعار المتجر</label>
                        <div class="logo-preview" id="logo-preview">
                            <?php if (getSetting('store_logo')): ?>
                                <img src="../<?php echo getSetting('store_logo'); ?>" alt="شعار المتجر">
                            <?php else: ?>
                                <i class="fas fa-image"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="store_logo" id="store_logo" class="form-control" 
                               accept="image/*" onchange="previewLogo(this)">
                        <small style="color: var(--text-muted);">الحجم الأقصى: 2MB | الأنواع: JPG, PNG, GIF, SVG</small>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label>اسم المتجر</label>
                            <input type="text" name="store_name" class="form-control" 
                                   value="<?php echo htmlspecialchars(getSetting('store_name')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>وصف مختصر عن المتجر</label>
                            <textarea name="store_description" class="form-control" rows="3"><?php echo htmlspecialchars(getSetting('store_description')); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="save_general" class="btn-save">
                    <i class="fas fa-save"></i>
                    حفظ معلومات المتجر
                </button>
            </form>
        </div>
    </div>
</div>

<!-- تبويب التواصل -->
<div id="tab-contact" class="tab-content">
    <div class="settings-card">
        <div class="settings-card-header">
            <i class="fas fa-phone-alt"></i>
            <h3>معلومات التواصل</h3>
        </div>
        <div class="settings-card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                        <input type="tel" name="contact_phone" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('contact_phone')); ?>" 
                               placeholder="+966500000000">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fab fa-whatsapp"></i> رقم الواتساب</label>
                        <input type="tel" name="contact_whatsapp" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('contact_whatsapp')); ?>" 
                               placeholder="+966500000000">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                    <input type="email" name="contact_email" class="form-control" 
                           value="<?php echo htmlspecialchars(getSetting('contact_email')); ?>" 
                           placeholder="info@myshop.com">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> العنوان</label>
                    <textarea name="contact_address" class="form-control" rows="2"><?php echo htmlspecialchars(getSetting('contact_address')); ?></textarea>
                </div>
                
                <button type="submit" name="save_contact" class="btn-save">
                    <i class="fas fa-save"></i>
                    حفظ معلومات التواصل
                </button>
            </form>
        </div>
    </div>
</div>


<!-- تبويب التواصل الاجتماعي -->
<div id="tab-social" class="tab-content">
    <div class="settings-card">
        <div class="settings-card-header">
            <i class="fas fa-share-alt"></i>
            <h3>وسائل التواصل الاجتماعي</h3>
        </div>
        <div class="settings-card-body">
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <p>أدخل روابط صفحاتك على وسائل التواصل الاجتماعي. اترك الحقل فارغاً إذا لم يكن لديك حساب.</p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>فيسبوك</label>
                        <div class="social-input">
                            <i class="fab fa-facebook"></i>
                            <input type="url" name="social_facebook" class="form-control" 
                                   value="<?php echo htmlspecialchars(getSetting('social_facebook')); ?>" 
                                   placeholder="https://facebook.com/yourpage">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>تويتر / X</label>
                        <div class="social-input">
                            <i class="fab fa-twitter"></i>
                            <input type="url" name="social_twitter" class="form-control" 
                                   value="<?php echo htmlspecialchars(getSetting('social_twitter')); ?>" 
                                   placeholder="https://twitter.com/yourpage">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>انستغرام</label>
                        <div class="social-input">
                            <i class="fab fa-instagram"></i>
                            <input type="url" name="social_instagram" class="form-control" 
                                   value="<?php echo htmlspecialchars(getSetting('social_instagram')); ?>" 
                                   placeholder="https://instagram.com/yourpage">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>سناب شات</label>
                        <div class="social-input">
                            <i class="fab fa-snapchat"></i>
                            <input type="url" name="social_snapchat" class="form-control" 
                                   value="<?php echo htmlspecialchars(getSetting('social_snapchat')); ?>" 
                                   placeholder="https://snapchat.com/add/yourpage">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تيك توك</label>
                        <div class="social-input">
                            <i class="fab fa-tiktok"></i>
                            <input type="url" name="social_tiktok" class="form-control" 
                                   value="<?php echo htmlspecialchars(getSetting('social_tiktok')); ?>" 
                                   placeholder="https://tiktok.com/@yourpage">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>يوتيوب</label>
                        <div class="social-input">
                            <i class="fab fa-youtube"></i>
                            <input type="url" name="social_youtube" class="form-control" 
                                   value="<?php echo htmlspecialchars(getSetting('social_youtube')); ?>" 
                                   placeholder="https://youtube.com/c/yourchannel">
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="save_social" class="btn-save">
                    <i class="fas fa-save"></i>
                    حفظ روابط التواصل
                </button>
            </form>
        </div>
    </div>
</div>

<!-- تبويب العملة والضريبة -->
<div id="tab-currency" class="tab-content">
    <div class="settings-card">
        <div class="settings-card-header">
            <i class="fas fa-coins"></i>
            <h3>إعدادات العملة والضريبة</h3>
        </div>
        <div class="settings-card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <!-- معاينة السعر -->
                <div class="currency-preview">
                    <div class="preview-label">معاينة السعر</div>
                    <div class="preview-price" id="price-preview">
                        <?php 
                        $sample_price = 1250.50;
                        $symbol = getSetting('currency_symbol', 'ر.س');
                        $position = getSetting('currency_position', 'after');
                        $decimals = getSetting('currency_decimals', 2);
                        $formatted = number_format($sample_price, $decimals);
                        echo $position === 'before' ? $symbol . ' ' . $formatted : $formatted . ' ' . $symbol;
                        ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>العملة</label>
                        <select name="currency_code" id="currency_code" class="form-control" onchange="updateCurrencySymbol()">
                            <?php foreach ($available_currencies as $code => $curr): ?>
                            <option value="<?php echo $code; ?>" 
                                    data-symbol="<?php echo $curr['symbol']; ?>"
                                    <?php echo getSetting('currency_code') === $code ? 'selected' : ''; ?>>
                                <?php echo $curr['name']; ?> (<?php echo $code; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>رمز العملة</label>
                        <input type="text" name="currency_symbol" id="currency_symbol" class="form-control" 
                               value="<?php echo htmlspecialchars(getSetting('currency_symbol', 'ر.س')); ?>" 
                               maxlength="5" onchange="updatePreview()">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>موضع الرمز</label>
                        <select name="currency_position" id="currency_position" class="form-control" onchange="updatePreview()">
                            <option value="after" <?php echo getSetting('currency_position') === 'after' ? 'selected' : ''; ?>>
                                بعد الرقم (100 ر.س)
                            </option>
                            <option value="before" <?php echo getSetting('currency_position') === 'before' ? 'selected' : ''; ?>>
                                قبل الرقم (ر.س 100)
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>الكسور العشرية</label>
                        <select name="currency_decimals" id="currency_decimals" class="form-control" onchange="updatePreview()">
                            <option value="0" <?php echo getSetting('currency_decimals') == 0 ? 'selected' : ''; ?>>بدون (100)</option>
                            <option value="2" <?php echo getSetting('currency_decimals') == 2 ? 'selected' : ''; ?>>خانتين (100.00)</option>
                            <option value="3" <?php echo getSetting('currency_decimals') == 3 ? 'selected' : ''; ?>>ثلاث خانات (100.000)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>نسبة الضريبة (VAT) %</label>
                        <input type="number" name="tax_rate" class="form-control" 
                               value="<?php echo getSetting('tax_rate', 15); ?>" 
                               min="0" max="100" step="0.1">
                    </div>
                    
                    <div class="form-group">
                        <label>تفعيل الضريبة</label>
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="tax_enabled" <?php echo getSetting('tax_enabled') ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span>إضافة الضريبة للأسعار</span>
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="save_currency" class="btn-save">
                    <i class="fas fa-save"></i>
                    حفظ إعدادات العملة
                </button>
            </form>
        </div>
    </div>
</div>

<!-- تبويب اللغة والمنطقة -->
<div id="tab-locale" class="tab-content">
    <div class="settings-card">
        <div class="settings-card-header">
            <i class="fas fa-globe"></i>
            <h3>إعدادات اللغة والمنطقة الزمنية</h3>
        </div>
        <div class="settings-card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>اللغة الافتراضية</label>
                        <select name="default_language" class="form-control">
                            <?php foreach ($available_languages as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo getSetting('default_language') === $code ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>المنطقة الزمنية</label>
                        <select name="timezone" class="form-control">
                            <?php foreach ($timezones as $tz => $name): ?>
                            <option value="<?php echo $tz; ?>" <?php echo getSetting('timezone') === $tz ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>تنسيق التاريخ</label>
                        <select name="date_format" class="form-control">
                            <option value="d/m/Y" <?php echo getSetting('date_format') === 'd/m/Y' ? 'selected' : ''; ?>>
                                <?php echo date('d/m/Y'); ?> (يوم/شهر/سنة)
                            </option>
                            <option value="Y-m-d" <?php echo getSetting('date_format') === 'Y-m-d' ? 'selected' : ''; ?>>
                                <?php echo date('Y-m-d'); ?> (سنة-شهر-يوم)
                            </option>
                            <option value="m/d/Y" <?php echo getSetting('date_format') === 'm/d/Y' ? 'selected' : ''; ?>>
                                <?php echo date('m/d/Y'); ?> (شهر/يوم/سنة)
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>تنسيق الوقت</label>
                        <select name="time_format" class="form-control">
                            <option value="H:i" <?php echo getSetting('time_format') === 'H:i' ? 'selected' : ''; ?>>
                                <?php echo date('H:i'); ?> (24 ساعة)
                            </option>
                            <option value="h:i A" <?php echo getSetting('time_format') === 'h:i A' ? 'selected' : ''; ?>>
                                <?php echo date('h:i A'); ?> (12 ساعة)
                            </option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="save_locale" class="btn-save">
                    <i class="fas fa-save"></i>
                    حفظ إعدادات اللغة
                </button>
            </form>
        </div>
    </div>
</div>

<!-- تبويب حالة المتجر -->
<div id="tab-status" class="tab-content">
    <div class="settings-card">
        <div class="settings-card-header">
            <i class="fas fa-power-off"></i>
            <h3>حالة المتجر</h3>
        </div>
        <div class="settings-card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="info-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>عند تفعيل وضع الصيانة، لن يتمكن الزوار من تصفح المتجر وسيظهر لهم رسالة الصيانة فقط.</p>
                </div>
                
                <div class="status-toggle">
                    <div class="status-option open <?php echo getSetting('store_status') === 'open' ? 'selected' : ''; ?>" 
                         onclick="selectStatus('open')">
                        <i class="fas fa-store"></i>
                        <h4>المتجر مفتوح</h4>
                        <p>المتجر يعمل بشكل طبيعي</p>
                    </div>
                    
                    <div class="status-option maintenance <?php echo getSetting('store_status') === 'maintenance' ? 'selected' : ''; ?>" 
                         onclick="selectStatus('maintenance')">
                        <i class="fas fa-tools"></i>
                        <h4>وضع الصيانة</h4>
                        <p>المتجر مغلق مؤقتاً للصيانة</p>
                    </div>
                </div>
                
                <input type="hidden" name="store_status" id="store_status" value="<?php echo getSetting('store_status', 'open'); ?>">
                
                <div class="maintenance-fields <?php echo getSetting('store_status') === 'maintenance' ? 'show' : ''; ?>" id="maintenance-fields">
                    <div class="form-group">
                        <label>رسالة الصيانة</label>
                        <textarea name="maintenance_message" class="form-control" rows="3"><?php echo htmlspecialchars(getSetting('maintenance_message')); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>تاريخ انتهاء الصيانة المتوقع <small>(اختياري)</small></label>
                        <input type="datetime-local" name="maintenance_end_date" class="form-control" 
                               value="<?php echo getSetting('maintenance_end_date'); ?>">
                    </div>
                </div>
                
                <button type="submit" name="save_status" class="btn-save" style="margin-top: 20px;">
                    <i class="fas fa-save"></i>
                    حفظ حالة المتجر
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// التبديل بين التبويبات
function showTab(tabName) {
    // إخفاء جميع التبويبات
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.settings-tab').forEach(btn => btn.classList.remove('active'));
    
    // إظهار التبويب المحدد
    document.getElementById('tab-' + tabName).classList.add('active');
    event.currentTarget.classList.add('active');
}

// معاينة الشعار
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logo-preview').innerHTML = '<img src="' + e.target.result + '" alt="معاينة">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// تحديث رمز العملة
function updateCurrencySymbol() {
    const select = document.getElementById('currency_code');
    const option = select.options[select.selectedIndex];
    document.getElementById('currency_symbol').value = option.dataset.symbol;
    updatePreview();
}

// تحديث معاينة السعر
function updatePreview() {
    const symbol = document.getElementById('currency_symbol').value;
    const position = document.getElementById('currency_position').value;
    const decimals = parseInt(document.getElementById('currency_decimals').value);
    
    let price = (1250.50).toFixed(decimals);
    let formatted = position === 'before' ? symbol + ' ' + price : price + ' ' + symbol;
    
    document.getElementById('price-preview').textContent = formatted;
}

// اختيار حالة المتجر
function selectStatus(status) {
    document.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelector('.status-option.' + status).classList.add('selected');
    document.getElementById('store_status').value = status;
    
    // إظهار/إخفاء حقول الصيانة
    const maintenanceFields = document.getElementById('maintenance-fields');
    if (status === 'maintenance') {
        maintenanceFields.classList.add('show');
    } else {
        maintenanceFields.classList.remove('show');
    }
}
</script>

<?php include 'includes/admin_footer.php'; ?>
