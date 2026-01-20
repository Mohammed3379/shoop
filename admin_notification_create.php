<?php
/**
 * صفحة إنشاء/تعديل إشعار
 * Create/Edit Notification
 */

include '../app/config/database.php';
include 'admin_auth.php';
include '../app/services/NotificationService.php';
include '../app/services/TargetingService.php';

checkAdminAuth();

$notificationService = new NotificationService($conn);
$targetingService = new TargetingService($conn);

$isEdit = isset($_GET['id']);
$notification = null;

if ($isEdit) {
    if (!hasPermission('notifications.edit')) {
        include 'includes/access_denied.php';
        exit;
    }
    $notification = $notificationService->getById((int)$_GET['id']);
    if (!$notification || $notification['status'] === 'sent') {
        header("Location: admin_notifications.php");
        exit;
    }
    $page_title = 'تعديل إشعار';
} else {
    if (!hasPermission('notifications.create')) {
        include 'includes/access_denied.php';
        exit;
    }
    $page_title = 'إنشاء إشعار جديد';
}

$page_icon = 'fa-bell';
$csrf_token = getAdminCSRF();
$error = '';
$success = '';

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAdminCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'طلب غير صالح!';
    } else {
        $data = [
            'title' => $_POST['title'] ?? '',
            'content' => $_POST['content'] ?? '',
            'type' => $_POST['type'] ?? 'general',
            'category' => $_POST['category'] ?? 'promotional',
            'image' => $_POST['image'] ?? null,
            'link' => $_POST['link'] ?? null,
            'created_by' => $_SESSION['admin_id']
        ];
        
        // معايير الاستهداف للإشعارات المخصصة
        if ($data['type'] === 'custom') {
            $criteria = [];
            if (!empty($_POST['min_purchase'])) $criteria['min_purchase_amount'] = (float)$_POST['min_purchase'];
            if (!empty($_POST['inactive_days'])) $criteria['inactive_days'] = (int)$_POST['inactive_days'];
            if (!empty($_POST['new_days'])) $criteria['registered_within_days'] = (int)$_POST['new_days'];
            if (!empty($_POST['cart_hours'])) $criteria['abandoned_cart_hours'] = (int)$_POST['cart_hours'];
            $data['targeting_criteria'] = $criteria;
        }
        
        // المستخدم المستهدف للإشعارات الفردية
        if ($data['type'] === 'individual' && !empty($_POST['target_user_id'])) {
            $data['target_user_id'] = (int)$_POST['target_user_id'];
        }
        
        // تحديد الحالة
        $action = $_POST['action'] ?? 'draft';
        
        if ($action === 'send') {
            $data['status'] = 'draft'; // سيتم تغييرها بعد الإرسال
        } elseif ($action === 'schedule' && !empty($_POST['scheduled_at'])) {
            $data['status'] = 'scheduled';
        } else {
            $data['status'] = 'draft';
        }
        
        // الحفظ
        if ($isEdit) {
            $result = $notificationService->update($notification['id'], $data);
        } else {
            $result = $notificationService->create($data);
        }
        
        if ($result['success']) {
            $notifId = $isEdit ? $notification['id'] : $result['id'];
            
            // الجدولة
            if ($action === 'schedule' && !empty($_POST['scheduled_at'])) {
                $scheduleResult = $notificationService->schedule($notifId, $_POST['scheduled_at']);
                if (!$scheduleResult['success']) {
                    $error = $scheduleResult['message'];
                }
            }
            
            // الإرسال الفوري
            if ($action === 'send' && empty($error)) {
                $sendResult = $notificationService->send($notifId);
                if ($sendResult['success']) {
                    logAdminAction('send_notification', "تم إرسال الإشعار رقم: $notifId");
                    header("Location: admin_notifications.php?sent=1&count=" . ($sendResult['recipients_count'] ?? 0));
                    exit;
                } else {
                    $error = $sendResult['message'];
                }
            }
            
            if (empty($error)) {
                logAdminAction($isEdit ? 'edit_notification' : 'create_notification', "الإشعار رقم: $notifId");
                header("Location: admin_notifications.php?saved=1");
                exit;
            }
        } else {
            $error = $result['message'];
        }
    }
}

include 'includes/admin_header.php';
?>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" id="notificationForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <!-- العمود الأيسر - المحتوى -->
        <div>
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-edit"></i> محتوى الإشعار</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>العنوان <span style="color:red;">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="255"
                               value="<?php echo htmlspecialchars($notification['title'] ?? ''); ?>"
                               placeholder="عنوان الإشعار">
                    </div>
                    
                    <div class="form-group">
                        <label>المحتوى <span style="color:red;">*</span></label>
                        <textarea name="content" class="form-control" rows="5" required
                                  placeholder="محتوى الإشعار..."><?php echo htmlspecialchars($notification['content'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>رابط الصورة (اختياري)</label>
                        <input type="url" name="image" class="form-control"
                               value="<?php echo htmlspecialchars($notification['image'] ?? ''); ?>"
                               placeholder="https://example.com/image.jpg">
                    </div>
                    
                    <div class="form-group">
                        <label>الرابط المرفق (اختياري)</label>
                        <input type="text" name="link" class="form-control"
                               value="<?php echo htmlspecialchars($notification['link'] ?? ''); ?>"
                               placeholder="product.php?id=123">
                    </div>
                </div>
            </div>
            
            <!-- معايير الاستهداف -->
            <div class="card" id="targetingCard" style="margin-top: 20px; display: none;">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-crosshairs"></i> معايير الاستهداف</h3></div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>الحد الأدنى للمشتريات (ر.س)</label>
                            <input type="number" name="min_purchase" class="form-control" min="0" step="0.01"
                                   value="<?php echo $notification['targeting_criteria']['min_purchase_amount'] ?? ''; ?>"
                                   placeholder="مثال: 500">
                        </div>
                        <div class="form-group">
                            <label>غير نشط منذ (أيام)</label>
                            <input type="number" name="inactive_days" class="form-control" min="1"
                                   value="<?php echo $notification['targeting_criteria']['inactive_days'] ?? ''; ?>"
                                   placeholder="مثال: 30">
                        </div>
                        <div class="form-group">
                            <label>مسجل خلال (أيام)</label>
                            <input type="number" name="new_days" class="form-control" min="1"
                                   value="<?php echo $notification['targeting_criteria']['registered_within_days'] ?? ''; ?>"
                                   placeholder="مثال: 7">
                        </div>
                        <div class="form-group">
                            <label>سلة متروكة منذ (ساعات)</label>
                            <input type="number" name="cart_hours" class="form-control" min="1"
                                   value="<?php echo $notification['targeting_criteria']['abandoned_cart_hours'] ?? ''; ?>"
                                   placeholder="مثال: 24">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- اختيار المستخدم -->
            <div class="card" id="userSelectCard" style="margin-top: 20px; display: none;">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-user"></i> اختيار المستخدم</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>البحث عن مستخدم</label>
                        <input type="text" id="userSearch" class="form-control" placeholder="اكتب اسم أو بريد المستخدم...">
                        <input type="hidden" name="target_user_id" id="targetUserId" value="<?php echo $notification['target_user_id'] ?? ''; ?>">
                        <div id="userResults" style="margin-top: 10px;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- العمود الأيمن - الإعدادات -->
        <div>
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-cog"></i> إعدادات الإشعار</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>نوع الإشعار</label>
                        <select name="type" id="notifType" class="form-control">
                            <option value="general" <?php echo ($notification['type'] ?? '') === 'general' ? 'selected' : ''; ?>>عام (جميع المستخدمين)</option>
                            <option value="custom" <?php echo ($notification['type'] ?? '') === 'custom' ? 'selected' : ''; ?>>مخصص (حسب المعايير)</option>
                            <option value="individual" <?php echo ($notification['type'] ?? '') === 'individual' ? 'selected' : ''; ?>>فردي (مستخدم واحد)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>التصنيف</label>
                        <select name="category" class="form-control">
                            <option value="offer" <?php echo ($notification['category'] ?? '') === 'offer' ? 'selected' : ''; ?>>عرض/خصم</option>
                            <option value="new_product" <?php echo ($notification['category'] ?? '') === 'new_product' ? 'selected' : ''; ?>>منتج جديد</option>
                            <option value="cart_reminder" <?php echo ($notification['category'] ?? '') === 'cart_reminder' ? 'selected' : ''; ?>>تذكير سلة</option>
                            <option value="promotional" <?php echo ($notification['category'] ?? 'promotional') === 'promotional' ? 'selected' : ''; ?>>ترويجي</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>جدولة الإرسال</label>
                        <input type="datetime-local" name="scheduled_at" id="scheduledAt" class="form-control"
                               value="<?php echo $notification['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($notification['scheduled_at'])) : ''; ?>">
                        <small style="color: #888;">اتركه فارغاً للإرسال الفوري</small>
                    </div>
                </div>
            </div>
            
            <!-- أزرار الإجراءات -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-body">
                    <button type="submit" name="action" value="draft" class="btn btn-outline" style="width: 100%; margin-bottom: 10px;">
                        <i class="fas fa-save"></i> حفظ كمسودة
                    </button>
                    <button type="submit" name="action" value="schedule" class="btn btn-info" style="width: 100%; margin-bottom: 10px;" id="scheduleBtn">
                        <i class="fas fa-clock"></i> جدولة الإرسال
                    </button>
                    <button type="submit" name="action" value="send" class="btn btn-success" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> إرسال الآن
                    </button>
                </div>
            </div>
            
            <!-- المعاينة -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-eye"></i> معاينة</h3></div>
                <div class="card-body" id="previewArea">
                    <div style="background: #f9f9f9; border-radius: 8px; padding: 15px;">
                        <div style="display: flex; gap: 10px;">
                            <div style="width: 40px; height: 40px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div style="flex: 1;">
                                <h4 id="previewTitle" style="margin: 0 0 5px; font-size: 14px;">عنوان الإشعار</h4>
                                <p id="previewContent" style="margin: 0; font-size: 12px; color: #666;">محتوى الإشعار...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('notifType');
    const targetingCard = document.getElementById('targetingCard');
    const userSelectCard = document.getElementById('userSelectCard');
    const titleInput = document.querySelector('input[name="title"]');
    const contentInput = document.querySelector('textarea[name="content"]');
    
    // تبديل البطاقات حسب النوع
    function updateTypeCards() {
        const type = typeSelect.value;
        targetingCard.style.display = type === 'custom' ? 'block' : 'none';
        userSelectCard.style.display = type === 'individual' ? 'block' : 'none';
    }
    
    typeSelect.addEventListener('change', updateTypeCards);
    updateTypeCards();
    
    // تحديث المعاينة
    function updatePreview() {
        document.getElementById('previewTitle').textContent = titleInput.value || 'عنوان الإشعار';
        document.getElementById('previewContent').textContent = contentInput.value || 'محتوى الإشعار...';
    }
    
    titleInput.addEventListener('input', updatePreview);
    contentInput.addEventListener('input', updatePreview);
    updatePreview();
    
    // البحث عن المستخدمين
    const userSearch = document.getElementById('userSearch');
    const userResults = document.getElementById('userResults');
    let searchTimeout;
    
    userSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            userResults.innerHTML = '';
            return;
        }
        
        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch('../api/search_users.php?q=' + encodeURIComponent(query));
                const data = await response.json();
                
                if (data.success && data.users.length > 0) {
                    userResults.innerHTML = data.users.map(user => `
                        <div class="user-result" onclick="selectUser(${user.id}, '${user.full_name}')" 
                             style="padding: 10px; border: 1px solid #eee; border-radius: 5px; margin-bottom: 5px; cursor: pointer;">
                            <strong>${user.full_name}</strong><br>
                            <small style="color: #888;">${user.email}</small>
                        </div>
                    `).join('');
                } else {
                    userResults.innerHTML = '<p style="color: #888;">لا توجد نتائج</p>';
                }
            } catch (e) {
                console.error(e);
            }
        }, 300);
    });
});

function selectUser(id, name) {
    document.getElementById('targetUserId').value = id;
    document.getElementById('userSearch').value = name;
    document.getElementById('userResults').innerHTML = '<p style="color: green;">تم اختيار: ' + name + '</p>';
}
</script>

<style>
/* Responsive Styles for Notification Create */
@media (max-width: 992px) {
    form > div[style*="grid-template-columns: 2fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 768px) {
    .card {
        margin-bottom: 15px;
    }
    
    .card-header {
        padding: 15px;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-control {
        padding: 10px 12px;
        font-size: 14px;
    }
    
    /* Targeting Card Grid */
    #targetingCard .card-body > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    /* Buttons */
    .btn {
        padding: 12px 15px;
        font-size: 14px;
    }
    
    /* Preview Area */
    #previewArea > div {
        padding: 12px;
    }
    
    #previewArea h4 {
        font-size: 13px !important;
    }
    
    #previewArea p {
        font-size: 11px !important;
    }
}

@media (max-width: 480px) {
    .card-title {
        font-size: 14px;
    }
    
    .form-group label {
        font-size: 13px;
    }
    
    textarea.form-control {
        min-height: 100px;
    }
}
</style>

<?php include 'includes/admin_footer.php'; ?>
