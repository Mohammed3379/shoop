<?php
/**
 * ===========================================
 * إدارة البانرات الإعلانية (السلايدر)
 * ===========================================
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

// التحقق من صلاحية عرض البانرات
requirePermission('banners.view');

$page_title = 'إدارة البانرات';
$page_icon = 'fa-images';

$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

// صلاحيات المستخدم الحالي
$can_create = hasPermission('banners.create');
$can_edit = hasPermission('banners.edit');
$can_delete = hasPermission('banners.delete');

// إنشاء جدول البانرات إذا لم يكن موجوداً
$conn->query("CREATE TABLE IF NOT EXISTS `banners` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(200) NOT NULL,
    `subtitle` text,
    `badge_text` varchar(100),
    `badge_color` varchar(20) DEFAULT 'red',
    `button_text` varchar(100) DEFAULT 'تسوق الآن',
    `button_link` varchar(500),
    `image_url` varchar(500),
    `bg_gradient` varchar(200) DEFAULT 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)',
    `sort_order` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyAdminCSRF($_POST['csrf_token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $badge_text = trim($_POST['badge_text'] ?? '');
        $badge_color = $_POST['badge_color'] ?? 'red';
        $button_text = trim($_POST['button_text'] ?? 'تسوق الآن');
        $button_link = trim($_POST['button_link'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $bg_gradient = trim($_POST['bg_gradient'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // إضافة بانر جديد
        if (isset($_POST['add_banner'])) {
            if (!$can_create) {
                $message = 'ليس لديك صلاحية إضافة بانرات';
                $message_type = 'danger';
            } elseif (empty($title)) {
                $message = 'يرجى إدخال عنوان البانر';
                $message_type = 'danger';
            } else {
                $stmt = $conn->prepare("INSERT INTO banners (title, subtitle, badge_text, badge_color, button_text, button_link, image_url, bg_gradient, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssii", $title, $subtitle, $badge_text, $badge_color, $button_text, $button_link, $image_url, $bg_gradient, $sort_order, $is_active);
                if ($stmt->execute()) {
                    $message = 'تم إضافة البانر بنجاح! ✅';
                    $message_type = 'success';
                    logAdminAction('add_banner', "تم إضافة بانر: $title");
                } else {
                    $message = 'فشل إضافة البانر!';
                    $message_type = 'danger';
                }
            }
        }
        
        // تعديل بانر
        if (isset($_POST['edit_banner'])) {
            if (!$can_edit) {
                $message = 'ليس لديك صلاحية تعديل البانرات';
                $message_type = 'danger';
            } else {
                $id = intval($_POST['banner_id']);
                $stmt = $conn->prepare("UPDATE banners SET title=?, subtitle=?, badge_text=?, badge_color=?, button_text=?, button_link=?, image_url=?, bg_gradient=?, sort_order=?, is_active=? WHERE id=?");
                $stmt->bind_param("ssssssssiis", $title, $subtitle, $badge_text, $badge_color, $button_text, $button_link, $image_url, $bg_gradient, $sort_order, $is_active, $id);
                if ($stmt->execute()) {
                    $message = 'تم تحديث البانر بنجاح! ✅';
                    $message_type = 'success';
                    logAdminAction('edit_banner', "تم تعديل بانر: $title");
                }
            }
        }
    }
}

// حذف بانر
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (!$can_delete) {
        $message = 'ليس لديك صلاحية حذف البانرات';
        $message_type = 'danger';
    } elseif (verifyAdminCSRF($_GET['token'])) {
        $id = intval($_GET['delete']);
        $stmt = $conn->prepare("DELETE FROM banners WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $message = 'تم حذف البانر بنجاح! ✅';
        $message_type = 'success';
        logAdminAction('delete_banner', "تم حذف بانر رقم: $id");
    }
}

// جلب البانرات
$banners = $conn->query("SELECT * FROM banners ORDER BY sort_order, id");

// جلب الفئات للقائمة المنسدلة
$categories = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

include 'includes/admin_header.php';
?>

<style>
.banners-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.banner-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.banner-card.inactive { opacity: 0.6; }

.banner-preview {
    height: 180px;
    position: relative;
    display: flex;
    align-items: center;
    padding: 20px;
    overflow: hidden;
}

.banner-preview .preview-content {
    flex: 1;
    z-index: 2;
}

.banner-preview .preview-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 10px;
}

.banner-preview .preview-badge.red { background: #ef4444; color: white; }
.banner-preview .preview-badge.blue { background: #3b82f6; color: white; }
.banner-preview .preview-badge.green { background: #10b981; color: white; }
.banner-preview .preview-badge.yellow { background: #f59e0b; color: white; }
.banner-preview .preview-badge.purple { background: #8b5cf6; color: white; }

.banner-preview h3 {
    color: white;
    font-size: 18px;
    margin: 0 0 8px 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.banner-preview p {
    color: rgba(255,255,255,0.8);
    font-size: 12px;
    margin: 0;
}

.banner-preview .preview-image {
    position: absolute;
    left: 20px;
    bottom: 0;
    height: 140px;
    opacity: 0.9;
}

.banner-info {
    padding: 20px;
    border-top: 1px solid var(--border-color);
}

.banner-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.banner-meta span {
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 5px;
}

.banner-meta span i { color: var(--primary); }

.banner-actions {
    display: flex;
    gap: 10px;
}

.banner-actions .btn {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    transition: all 0.2s;
}

.btn-edit { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.btn-edit:hover { background: #3b82f6; color: white; }
.btn-delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.btn-delete:hover { background: #ef4444; color: white; }

.add-banner-card {
    background: var(--bg-card);
    border: 2px dashed var(--border-color);
    border-radius: var(--radius-lg);
    padding: 60px 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    min-height: 280px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.add-banner-card:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.add-banner-card i { font-size: 50px; color: var(--primary); margin-bottom: 15px; }
.add-banner-card p { color: var(--text-muted); margin: 0; }

/* Modal */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }

.modal-content {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 { margin: 0; font-size: 18px; }
.modal-header h3 i { color: var(--primary); margin-left: 10px; }
.modal-close { background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer; }

.modal-body { padding: 25px; }

.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
.form-control {
    width: 100%;
    padding: 12px 15px;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: 14px;
}
.form-control:focus { outline: none; border-color: var(--primary); }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }

.color-options {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.color-option {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    cursor: pointer;
    border: 3px solid transparent;
    transition: all 0.2s;
}
.color-option:hover { transform: scale(1.1); }
.color-option.selected { border-color: white; box-shadow: 0 0 0 2px var(--primary); }
.color-option.red { background: #ef4444; }
.color-option.blue { background: #3b82f6; }
.color-option.green { background: #10b981; }
.color-option.yellow { background: #f59e0b; }
.color-option.purple { background: #8b5cf6; }

.gradient-options {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}

.gradient-option {
    height: 50px;
    border-radius: var(--radius-md);
    cursor: pointer;
    border: 3px solid transparent;
    transition: all 0.2s;
}
.gradient-option:hover { transform: scale(1.02); }
.gradient-option.selected { border-color: white; box-shadow: 0 0 0 2px var(--primary); }

.link-helper {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.link-helper select {
    flex: 1;
    padding: 8px;
    background: var(--bg-hover);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    font-size: 12px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), #4f46e5);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.btn-secondary {
    background: var(--bg-hover);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    padding: 12px 25px;
    border-radius: var(--radius-md);
    cursor: pointer;
}

.live-preview {
    background: var(--bg-hover);
    border-radius: var(--radius-md);
    padding: 15px;
    margin-bottom: 20px;
}

.live-preview h4 {
    margin: 0 0 10px 0;
    font-size: 13px;
    color: var(--text-muted);
}

.preview-box {
    height: 150px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    padding: 20px;
    position: relative;
    overflow: hidden;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.status-active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-inactive { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

/* Responsive Styles */
@media (max-width: 768px) {
    .banners-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .banner-preview {
        height: 150px;
        padding: 15px;
    }
    
    .banner-preview h3 {
        font-size: 15px;
    }
    
    .banner-preview .preview-image {
        height: 100px;
        left: 10px;
    }
    
    .banner-info {
        padding: 15px;
    }
    
    .banner-meta {
        gap: 10px;
    }
    
    .banner-actions {
        flex-direction: column;
    }
    
    .add-banner-card {
        padding: 40px 20px;
        min-height: 200px;
    }
    
    .add-banner-card i {
        font-size: 35px;
    }
    
    /* Modal Responsive */
    .modal-content {
        max-width: 95%;
        max-height: 95vh;
        margin: 10px;
    }
    
    .modal-header {
        padding: 15px;
    }
    
    .modal-body {
        padding: 15px;
    }
    
    .modal-footer {
        padding: 15px;
        flex-direction: column;
    }
    
    .modal-footer .btn-primary,
    .modal-footer .btn-secondary {
        width: 100%;
    }
    
    .form-row,
    .form-row-3 {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .gradient-options {
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
    }
    
    .gradient-option {
        height: 40px;
    }
    
    .color-options {
        justify-content: center;
    }
    
    .live-preview {
        padding: 10px;
    }
    
    .preview-box {
        height: 120px;
        padding: 15px;
    }
    
    .preview-box h3 {
        font-size: 14px !important;
    }
    
    .preview-box p {
        font-size: 11px !important;
    }
    
    #previewImage {
        height: 90px !important;
    }
    
    .link-helper {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .gradient-options {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .banner-preview {
        height: 130px;
    }
    
    .banner-preview .preview-badge {
        font-size: 10px;
        padding: 4px 8px;
    }
    
    .banner-preview h3 {
        font-size: 14px;
    }
    
    .banner-preview p {
        font-size: 11px;
    }
}
</style>


<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="banners-grid">
    <?php if ($can_create): ?>
    <div class="add-banner-card" onclick="openModal('add')">
        <i class="fas fa-plus-circle"></i>
        <p>إضافة بانر إعلاني جديد</p>
    </div>
    <?php endif; ?>
    
    <?php if ($banners && $banners->num_rows > 0): ?>
        <?php while ($banner = $banners->fetch_assoc()): ?>
        <div class="banner-card <?php echo $banner['is_active'] ? '' : 'inactive'; ?>">
            <div class="banner-preview" style="background: <?php echo htmlspecialchars($banner['bg_gradient']); ?>">
                <div class="preview-content">
                    <?php if ($banner['badge_text']): ?>
                    <span class="preview-badge <?php echo $banner['badge_color']; ?>">
                        <?php echo htmlspecialchars($banner['badge_text']); ?>
                    </span>
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($banner['title']); ?></h3>
                    <p><?php echo htmlspecialchars(mb_substr($banner['subtitle'], 0, 60)); ?>...</p>
                </div>
                <?php if ($banner['image_url']): ?>
                <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" class="preview-image" alt="">
                <?php endif; ?>
            </div>
            <div class="banner-info">
                <div class="banner-meta">
                    <span>
                        <i class="fas fa-sort"></i>
                        الترتيب: <?php echo $banner['sort_order']; ?>
                    </span>
                    <span>
                        <i class="fas fa-link"></i>
                        <?php echo $banner['button_link'] ? 'يوجد رابط' : 'بدون رابط'; ?>
                    </span>
                    <span class="status-badge <?php echo $banner['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $banner['is_active'] ? 'نشط' : 'معطل'; ?>
                    </span>
                </div>
                <?php if ($can_edit || $can_delete): ?>
                <div class="banner-actions">
                    <?php if ($can_edit): ?>
                    <button class="btn btn-edit" onclick='openModal("edit", <?php echo json_encode($banner); ?>)'>
                        <i class="fas fa-edit"></i> تعديل
                    </button>
                    <?php endif; ?>
                    <?php if ($can_delete): ?>
                    <a href="?delete=<?php echo $banner['id']; ?>&token=<?php echo $csrf_token; ?>" 
                       class="btn btn-delete"
                       onclick="return confirm('هل أنت متأكد من حذف هذا البانر؟')">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>


<!-- Modal -->
<div class="modal-overlay" id="bannerModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-image"></i> <span id="modalTitle">إضافة بانر جديد</span></h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="bannerForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="banner_id" id="bannerId">
            <input type="hidden" name="bg_gradient" id="bgGradient" value="linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)">
            <input type="hidden" name="badge_color" id="badgeColor" value="red">
            
            <div class="modal-body">
                <!-- معاينة مباشرة -->
                <div class="live-preview">
                    <h4><i class="fas fa-eye"></i> معاينة مباشرة</h4>
                    <div class="preview-box" id="livePreview" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
                        <div class="preview-content">
                            <span class="preview-badge red" id="previewBadge">🔥 عرض حصري</span>
                            <h3 id="previewTitle" style="color:white; margin:10px 0 5px; font-size:16px;">عنوان البانر</h3>
                            <p id="previewSubtitle" style="color:rgba(255,255,255,0.8); font-size:12px; margin:0;">وصف البانر</p>
                        </div>
                        <img id="previewImage" src="" style="position:absolute; left:20px; bottom:0; height:120px; display:none;">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>العنوان الرئيسي <span style="color:#ef4444">*</span></label>
                        <input type="text" name="title" id="bannerTitle" class="form-control" placeholder="مثال: عروض الجمعة البيضاء" required oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label>نص الشارة</label>
                        <input type="text" name="badge_text" id="bannerBadge" class="form-control" placeholder="مثال: 🔥 عرض حصري" oninput="updatePreview()">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>الوصف</label>
                    <textarea name="subtitle" id="bannerSubtitle" class="form-control" rows="2" placeholder="وصف مختصر للعرض..." oninput="updatePreview()"></textarea>
                </div>
                
                <div class="form-row-3">
                    <div class="form-group">
                        <label>نص الزر</label>
                        <input type="text" name="button_text" id="bannerButton" class="form-control" value="تسوق الآن">
                    </div>
                    <div class="form-group">
                        <label>الترتيب</label>
                        <input type="number" name="sort_order" id="bannerOrder" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>الحالة</label>
                        <select name="is_active" id="bannerActive" class="form-control">
                            <option value="1">نشط</option>
                            <option value="0">معطل</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>رابط الزر</label>
                    <input type="text" name="button_link" id="bannerLink" class="form-control" placeholder="index.php?cat=electronics">
                    <div class="link-helper">
                        <select onchange="setLink(this.value)">
                            <option value="">-- اختر رابط سريع --</option>
                            <option value="index.php">الصفحة الرئيسية</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): ?>
                            <option value="index.php?cat=<?php echo $cat['slug']; ?>">فئة: <?php echo $cat['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>رابط الصورة</label>
                    <input type="text" name="image_url" id="bannerImage" class="form-control" placeholder="https://example.com/image.png" oninput="updatePreview()">
                    <small style="color: var(--text-muted);">يفضل صورة PNG بخلفية شفافة</small>
                </div>
                
                <div class="form-group">
                    <label>لون الشارة</label>
                    <div class="color-options">
                        <div class="color-option red selected" data-color="red" onclick="selectColor('red')"></div>
                        <div class="color-option blue" data-color="blue" onclick="selectColor('blue')"></div>
                        <div class="color-option green" data-color="green" onclick="selectColor('green')"></div>
                        <div class="color-option yellow" data-color="yellow" onclick="selectColor('yellow')"></div>
                        <div class="color-option purple" data-color="purple" onclick="selectColor('purple')"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>خلفية البانر</label>
                    <div class="gradient-options">
                        <div class="gradient-option selected" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);" data-gradient="linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)" onclick="selectGradient(this)"></div>
                        <div class="gradient-option" style="background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);" data-gradient="linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%)" onclick="selectGradient(this)"></div>
                        <div class="gradient-option" style="background: linear-gradient(135deg, #134e5e 0%, #71b280 100%);" data-gradient="linear-gradient(135deg, #134e5e 0%, #71b280 100%)" onclick="selectGradient(this)"></div>
                        <div class="gradient-option" style="background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);" data-gradient="linear-gradient(135deg, #ee0979 0%, #ff6a00 100%)" onclick="selectGradient(this)"></div>
                        <div class="gradient-option" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);" data-gradient="linear-gradient(135deg, #667eea 0%, #764ba2 100%)" onclick="selectGradient(this)"></div>
                        <div class="gradient-option" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);" data-gradient="linear-gradient(135deg, #f093fb 0%, #f5576c 100%)" onclick="selectGradient(this)"></div>
                        <div class="gradient-option" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);" data-gradient="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)" onclick="selectGradient(this)"></div>
                        <div class="gradient-option" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);" data-gradient="linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)" onclick="selectGradient(this)"></div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">إلغاء</button>
                <button type="submit" name="add_banner" id="submitBtn" class="btn-primary">
                    <i class="fas fa-plus"></i> إضافة البانر
                </button>
            </div>
        </form>
    </div>
</div>


<script>
function openModal(mode, data = null) {
    const modal = document.getElementById('bannerModal');
    const form = document.getElementById('bannerForm');
    const title = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    form.reset();
    document.getElementById('bgGradient').value = 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)';
    document.getElementById('badgeColor').value = 'red';
    
    document.querySelectorAll('.gradient-option').forEach(el => el.classList.remove('selected'));
    document.querySelector('.gradient-option').classList.add('selected');
    document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
    document.querySelector('.color-option.red').classList.add('selected');
    
    if (mode === 'edit' && data) {
        title.textContent = 'تعديل البانر';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> حفظ التغييرات';
        submitBtn.name = 'edit_banner';
        
        document.getElementById('bannerId').value = data.id;
        document.getElementById('bannerTitle').value = data.title;
        document.getElementById('bannerSubtitle').value = data.subtitle || '';
        document.getElementById('bannerBadge').value = data.badge_text || '';
        document.getElementById('bannerButton').value = data.button_text || 'تسوق الآن';
        document.getElementById('bannerLink').value = data.button_link || '';
        document.getElementById('bannerImage').value = data.image_url || '';
        document.getElementById('bannerOrder').value = data.sort_order || 0;
        document.getElementById('bannerActive').value = data.is_active;
        document.getElementById('bgGradient').value = data.bg_gradient || '';
        document.getElementById('badgeColor').value = data.badge_color || 'red';
        
        // تحديد اللون
        document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
        const colorEl = document.querySelector(`.color-option.${data.badge_color || 'red'}`);
        if (colorEl) colorEl.classList.add('selected');
        
        // تحديد التدرج
        document.querySelectorAll('.gradient-option').forEach(el => {
            el.classList.remove('selected');
            if (el.dataset.gradient === data.bg_gradient) el.classList.add('selected');
        });
    } else {
        title.textContent = 'إضافة بانر جديد';
        submitBtn.innerHTML = '<i class="fas fa-plus"></i> إضافة البانر';
        submitBtn.name = 'add_banner';
        document.getElementById('bannerId').value = '';
    }
    
    updatePreview();
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('bannerModal').classList.remove('active');
}

function selectColor(color) {
    document.getElementById('badgeColor').value = color;
    document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
    document.querySelector(`.color-option.${color}`).classList.add('selected');
    updatePreview();
}

function selectGradient(el) {
    document.getElementById('bgGradient').value = el.dataset.gradient;
    document.querySelectorAll('.gradient-option').forEach(e => e.classList.remove('selected'));
    el.classList.add('selected');
    updatePreview();
}

function setLink(value) {
    if (value) document.getElementById('bannerLink').value = value;
}

function updatePreview() {
    const title = document.getElementById('bannerTitle').value || 'عنوان البانر';
    const subtitle = document.getElementById('bannerSubtitle').value || 'وصف البانر';
    const badge = document.getElementById('bannerBadge').value || '🔥 عرض حصري';
    const image = document.getElementById('bannerImage').value;
    const gradient = document.getElementById('bgGradient').value;
    const color = document.getElementById('badgeColor').value;
    
    document.getElementById('previewTitle').textContent = title;
    document.getElementById('previewSubtitle').textContent = subtitle;
    document.getElementById('previewBadge').textContent = badge;
    document.getElementById('previewBadge').className = 'preview-badge ' + color;
    document.getElementById('livePreview').style.background = gradient;
    
    const imgEl = document.getElementById('previewImage');
    if (image) {
        imgEl.src = image;
        imgEl.style.display = 'block';
    } else {
        imgEl.style.display = 'none';
    }
}

document.getElementById('bannerModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include 'includes/admin_footer.php'; ?>
