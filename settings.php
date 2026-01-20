<?php
/**
 * ===========================================
 * ملف إدارة إعدادات المتجر
 * ===========================================
 */

// منع التحميل المتكرر
if (defined('SETTINGS_LOADED')) {
    return;
}
define('SETTINGS_LOADED', true);

// تخزين الإعدادات في متغير عام
$STORE_SETTINGS = [];

/**
 * تحميل جميع الإعدادات من قاعدة البيانات
 */
function loadAllSettings($conn) {
    global $STORE_SETTINGS;
    
    if (!empty($STORE_SETTINGS)) {
        return $STORE_SETTINGS;
    }
    
    try {
        $result = $conn->query("SELECT setting_key, setting_value, setting_type FROM store_settings");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $value = $row['setting_value'];
                
                // تحويل القيم حسب النوع
                switch ($row['setting_type']) {
                    case 'boolean':
                        $value = (bool)$value;
                        break;
                    case 'number':
                        $value = is_numeric($value) ? floatval($value) : 0;
                        break;
                    case 'json':
                        $value = json_decode($value, true) ?? [];
                        break;
                }
                
                $STORE_SETTINGS[$row['setting_key']] = $value;
            }
        }
    } catch (Exception $e) {
        // في حالة عدم وجود الجدول، استخدم القيم الافتراضية
        $STORE_SETTINGS = getDefaultSettings();
    }
    
    return $STORE_SETTINGS;
}

/**
 * الحصول على قيمة إعداد معين
 */
function getSetting($key, $default = '') {
    global $STORE_SETTINGS;
    return $STORE_SETTINGS[$key] ?? $default;
}

/**
 * تحديث قيمة إعداد
 */
function updateSetting($conn, $key, $value) {
    global $STORE_SETTINGS;
    
    $stmt = $conn->prepare("UPDATE store_settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param("ss", $value, $key);
    $result = $stmt->execute();
    
    if ($result) {
        $STORE_SETTINGS[$key] = $value;
    }
    
    return $result;
}

/**
 * تحديث مجموعة من الإعدادات
 */
function updateSettings($conn, $settings) {
    global $STORE_SETTINGS;
    
    $success = true;
    foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("
            INSERT INTO store_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->bind_param("sss", $key, $value, $value);
        if (!$stmt->execute()) {
            $success = false;
        } else {
            $STORE_SETTINGS[$key] = $value;
        }
    }
    
    return $success;
}

/**
 * الحصول على إعدادات مجموعة معينة
 */
function getSettingsByGroup($conn, $group) {
    $settings = [];
    
    $stmt = $conn->prepare("SELECT setting_key, setting_value, setting_type FROM store_settings WHERE setting_group = ?");
    $stmt->bind_param("s", $group);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'type' => $row['setting_type']
        ];
    }
    
    return $settings;
}

/**
 * القيم الافتراضية للإعدادات
 */
function getDefaultSettings() {
    return [
        'store_name' => 'مشترياتي',
        'store_description' => 'متجرك الإلكتروني المفضل للتسوق',
        'store_logo' => '',
        'store_favicon' => '',
        'contact_phone' => '+966500000000',
        'contact_email' => 'info@myshop.com',
        'contact_address' => 'الرياض، المملكة العربية السعودية',
        'contact_whatsapp' => '+966500000000',
        'social_facebook' => '',
        'social_twitter' => '',
        'social_instagram' => '',
        'social_snapchat' => '',
        'social_tiktok' => '',
        'social_youtube' => '',
        'currency_code' => 'SAR',
        'currency_symbol' => 'ر.س',
        'currency_position' => 'after',
        'currency_decimals' => 2,
        'tax_rate' => 15,
        'tax_enabled' => true,
        'default_language' => 'ar',
        'timezone' => 'Asia/Riyadh',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
        'store_status' => 'open',
        'maintenance_message' => 'المتجر تحت الصيانة حالياً، سنعود قريباً!',
        'maintenance_end_date' => '',
        'free_shipping_threshold' => 200,
        'default_shipping_cost' => 25,
        'shipping_enabled' => true,
        'min_order_amount' => 50,
        'max_order_items' => 50,
        'order_prefix' => 'ORD-',
        'meta_title' => 'مشترياتي - متجرك الإلكتروني',
        'meta_description' => 'تسوق أفضل المنتجات بأسعار منافسة',
        'meta_keywords' => 'تسوق, متجر, منتجات, عروض'
    ];
}

/**
 * التحقق من حالة المتجر
 */
function isStoreOpen() {
    return getSetting('store_status', 'open') === 'open';
}

/**
 * الحصول على اسم المتجر
 */
function getStoreName() {
    return getSetting('store_name', 'مشترياتي');
}

/**
 * الحصول على شعار المتجر
 */
function getStoreLogo() {
    return getSetting('store_logo', '');
}

/**
 * الحصول على معلومات التواصل
 */
function getContactInfo() {
    return [
        'phone' => getSetting('contact_phone'),
        'email' => getSetting('contact_email'),
        'address' => getSetting('contact_address'),
        'whatsapp' => getSetting('contact_whatsapp')
    ];
}

/**
 * الحصول على روابط التواصل الاجتماعي
 */
function getSocialLinks() {
    return [
        'facebook' => getSetting('social_facebook'),
        'twitter' => getSetting('social_twitter'),
        'instagram' => getSetting('social_instagram'),
        'snapchat' => getSetting('social_snapchat'),
        'tiktok' => getSetting('social_tiktok'),
        'youtube' => getSetting('social_youtube')
    ];
}
?>
