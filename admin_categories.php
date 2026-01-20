<?php
/**
 * ===========================================
 * إدارة الفئات (التصنيفات)
 * ===========================================
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

// التحقق من صلاحية عرض الفئات
requirePermission('categories.view');

$page_title = 'إدارة الفئات';
$page_icon = 'fa-tags';

$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

// صلاحيات المستخدم الحالي
$can_create = hasPermission('categories.create');
$can_edit = hasPermission('categories.edit');
$can_delete = hasPermission('categories.delete');

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyAdminCSRF($_POST['csrf_token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        // إضافة فئة جديدة
        if (isset($_POST['add_category'])) {
            if (!$can_create) {
                $message = 'ليس لديك صلاحية إضافة فئات';
                $message_type = 'danger';
            } else {
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $icon = trim($_POST['icon'] ?? 'fa-folder');
                $image_url = trim($_POST['image_url'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sort_order = intval($_POST['sort_order'] ?? 0);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                // توليد slug تلقائي إذا كان فارغاً
                if (empty($slug)) {
                    $slug = strtolower(preg_replace('/[^a-zA-Z0-9\x{0600}-\x{06FF}]+/u', '-', $name));
                    $slug = trim($slug, '-');
                }
                
                if (empty($name)) {
                    $message = 'يرجى إدخال اسم الفئة';
                    $message_type = 'danger';
                } else {
                    // التحقق من عدم التكرار
                    $check = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
                    $check->bind_param("s", $slug);
                    $check->execute();
                    if ($check->get_result()->num_rows > 0) {
                        $message = 'هذا الاسم المختصر موجود مسبقاً!';
                        $message_type = 'danger';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO categories (name, slug, icon, image_url, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssii", $name, $slug, $icon, $image_url, $description, $sort_order, $is_active);
                        if ($stmt->execute()) {
                            $message = 'تم إضافة الفئة بنجاح! ✅';
                            $message_type = 'success';
                            logAdminAction('add_category', "تم إضافة فئة: $name");
                        } else {
                            $message = 'فشل إضافة الفئة!';
                            $message_type = 'danger';
                        }
                    }
                }
            }
        }
        
        // تعديل فئة
        if (isset($_POST['edit_category'])) {
            if (!$can_edit) {
                $message = 'ليس لديك صلاحية تعديل الفئات';
                $message_type = 'danger';
            } else {
                $id = intval($_POST['category_id']);
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $icon = trim($_POST['icon'] ?? 'fa-folder');
                $image_url = trim($_POST['image_url'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sort_order = intval($_POST['sort_order'] ?? 0);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name)) {
                    $message = 'يرجى إدخال اسم الفئة';
                    $message_type = 'danger';
                } else {
                    $stmt = $conn->prepare("UPDATE categories SET name=?, slug=?, icon=?, image_url=?, description=?, sort_order=?, is_active=? WHERE id=?");
                    $stmt->bind_param("sssssiii", $name, $slug, $icon, $image_url, $description, $sort_order, $is_active, $id);
                    if ($stmt->execute()) {
                        $message = 'تم تحديث الفئة بنجاح! ✅';
                        $message_type = 'success';
                        logAdminAction('edit_category', "تم تعديل فئة: $name");
                    } else {
                        $message = 'فشل تحديث الفئة!';
                        $message_type = 'danger';
                    }
                }
            }
        }
    }
}

// حذف فئة
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (!$can_delete) {
        $message = 'ليس لديك صلاحية حذف الفئات';
        $message_type = 'danger';
    } elseif (!verifyAdminCSRF($_GET['token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        $id = intval($_GET['delete']);
        // التحقق من عدم وجود منتجات مرتبطة
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products p JOIN categories c ON p.category COLLATE utf8mb4_general_ci = c.slug COLLATE utf8mb4_general_ci WHERE c.id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $products_count = $check_stmt->get_result()->fetch_assoc()['count'] ?? 0;
        $check_stmt->close();
        
        if ($products_count > 0) {
            $message = "لا يمكن حذف هذه الفئة! يوجد $products_count منتج مرتبط بها.";
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = 'تم حذف الفئة بنجاح! ✅';
                $message_type = 'success';
                logAdminAction('delete_category', "تم حذف فئة رقم: $id");
            }
        }
    }
}

// جلب جميع الفئات
$categories = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category COLLATE utf8mb4_general_ci = c.slug COLLATE utf8mb4_general_ci) as products_count FROM categories c ORDER BY sort_order, name");

// تضمين الهيدر
include 'includes/admin_header.php';
?>

<style>
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.category-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.3s;
}

.category-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.category-card.inactive {
    opacity: 0.6;
}

.category-header {
    background: var(--bg-hover);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-bottom: 1px solid var(--border-color);
}

.category-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary), #4f46e5);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.category-info h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
    color: var(--text-primary);
}

.category-info .slug {
    font-size: 12px;
    color: var(--text-muted);
    font-family: monospace;
}

.category-body {
    padding: 20px;
}

.category-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.stat-item {
    text-align: center;
}

.stat-item .value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
}

.stat-item .label {
    font-size: 11px;
    color: var(--text-muted);
}

.category-actions {
    display: flex;
    gap: 10px;
}

.category-actions .btn {
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

.btn-edit {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.btn-edit:hover {
    background: #3b82f6;
    color: white;
}

.btn-delete {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.btn-delete:hover {
    background: #ef4444;
    color: white;
}

.add-category-card {
    background: var(--bg-card);
    border: 2px dashed var(--border-color);
    border-radius: var(--radius-lg);
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.add-category-card:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.add-category-card i {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 15px;
}

.add-category-card p {
    color: var(--text-muted);
    margin: 0;
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-overlay.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h3 i {
    color: var(--primary);
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: var(--text-muted);
    cursor: pointer;
}

.modal-body {
    padding: 25px;
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

.form-control {
    width: 100%;
    padding: 12px 15px;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
}

.icon-picker {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 8px;
    max-height: 150px;
    overflow-y: auto;
    padding: 10px;
    background: var(--bg-hover);
    border-radius: var(--radius-md);
}

.icon-option {
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-card);
    border: 2px solid transparent;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.2s;
}

.icon-option:hover {
    border-color: var(--primary);
}

.icon-option.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
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
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary {
    background: var(--bg-hover);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    padding: 12px 25px;
    border-radius: var(--radius-md);
    font-size: 14px;
    cursor: pointer;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.status-active {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.status-inactive {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 60px;
    margin-bottom: 20px;
    opacity: 0.5;
}

/* تحسينات التجاوب */
@media (max-width: 768px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .category-card {
        margin-bottom: 0;
    }
    
    .modal-content {
        margin: 15px;
        max-width: calc(100% - 30px);
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .icon-picker {
        grid-template-columns: repeat(6, 1fr);
    }
}

@media (max-width: 576px) {
    .category-header {
        padding: 15px;
    }
    
    .category-body {
        padding: 15px;
    }
    
    .category-icon {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }
    
    .category-actions {
        flex-direction: column;
    }
    
    .add-category-card {
        padding: 30px;
    }
    
    .add-category-card i {
        font-size: 30px;
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


<!-- شبكة الفئات -->
<div class="categories-grid">
    
    <!-- بطاقة إضافة فئة جديدة -->
    <div class="add-category-card" onclick="openModal('add')">
        <i class="fas fa-plus-circle"></i>
        <p>إضافة فئة جديدة</p>
    </div>
    
    <?php if ($categories && $categories->num_rows > 0): ?>
        <?php while ($cat = $categories->fetch_assoc()): ?>
        <div class="category-card <?php echo $cat['is_active'] ? '' : 'inactive'; ?>">
            <div class="category-header">
                <div class="category-icon">
                    <i class="fas <?php echo htmlspecialchars($cat['icon'] ?? 'fa-folder'); ?>"></i>
                </div>
                <div class="category-info">
                    <h3>
                        <?php echo htmlspecialchars($cat['name']); ?>
                        <span class="status-badge <?php echo $cat['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $cat['is_active'] ? 'نشط' : 'معطل'; ?>
                        </span>
                    </h3>
                    <div class="slug"><?php echo htmlspecialchars($cat['slug']); ?></div>
                </div>
            </div>
            <div class="category-body">
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="value"><?php echo $cat['products_count']; ?></div>
                        <div class="label">منتج</div>
                    </div>
                    <div class="stat-item">
                        <div class="value"><?php echo $cat['sort_order']; ?></div>
                        <div class="label">الترتيب</div>
                    </div>
                </div>
                <?php if (!empty($cat['description'])): ?>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 15px;">
                    <?php echo htmlspecialchars(mb_substr($cat['description'], 0, 80)); ?>...
                </p>
                <?php endif; ?>
                <div class="category-actions">
                    <button class="btn btn-edit" onclick='openModal("edit", <?php echo json_encode($cat); ?>)'>
                        <i class="fas fa-edit"></i> تعديل
                    </button>
                    <a href="?delete=<?php echo $cat['id']; ?>&token=<?php echo $csrf_token; ?>" 
                       class="btn btn-delete"
                       onclick="return confirm('هل أنت متأكد من حذف هذه الفئة؟')">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
    
</div>


<!-- Modal إضافة/تعديل فئة -->
<div class="modal-overlay" id="categoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-tag"></i> <span id="modalTitle">إضافة فئة جديدة</span></h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="categoryForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="category_id" id="categoryId">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>اسم الفئة <span style="color:#ef4444">*</span></label>
                    <input type="text" name="name" id="catName" class="form-control" placeholder="مثال: إلكترونيات" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>الاسم المختصر (Slug)</label>
                        <input type="text" name="slug" id="catSlug" class="form-control" placeholder="electronics">
                        <small style="color: var(--text-muted);">يُستخدم في الروابط (اتركه فارغاً للتوليد التلقائي)</small>
                    </div>
                    <div class="form-group">
                        <label>الترتيب</label>
                        <input type="number" name="sort_order" id="catOrder" class="form-control" value="0" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>الوصف</label>
                    <textarea name="description" id="catDesc" class="form-control" rows="3" placeholder="وصف مختصر للفئة..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>رابط صورة الفئة</label>
                    <input type="url" name="image_url" id="catImage" class="form-control" placeholder="https://example.com/image.jpg">
                    <small style="color: var(--text-muted);">صورة تظهر في الصفحة الرئيسية (اتركه فارغاً لاستخدام صورة افتراضية)</small>
                </div>
                
                <div class="form-group">
                    <label>الأيقونة</label>
                    <input type="hidden" name="icon" id="catIcon" value="fa-folder">
                    <div class="icon-picker" id="iconPicker">
                        <?php
                        $icons = ['fa-folder', 'fa-mobile-alt', 'fa-laptop', 'fa-tv', 'fa-headphones', 'fa-camera', 'fa-gamepad', 'fa-tshirt', 'fa-shoe-prints', 'fa-watch', 'fa-gem', 'fa-ring', 'fa-glasses', 'fa-hat-cowboy', 'fa-home', 'fa-couch', 'fa-bed', 'fa-utensils', 'fa-blender', 'fa-baby', 'fa-football-ball', 'fa-dumbbell', 'fa-bicycle', 'fa-car', 'fa-book', 'fa-paint-brush', 'fa-music', 'fa-gift', 'fa-heart', 'fa-star', 'fa-tag', 'fa-tags', 'fa-box', 'fa-boxes', 'fa-shopping-bag', 'fa-store', 'fa-percent', 'fa-fire', 'fa-bolt', 'fa-crown'];
                        foreach ($icons as $icon): ?>
                        <div class="icon-option" data-icon="<?php echo $icon; ?>" onclick="selectIcon('<?php echo $icon; ?>')">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="catActive" checked>
                        <label for="catActive" style="margin: 0;">فئة نشطة (تظهر في المتجر)</label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">إلغاء</button>
                <button type="submit" name="add_category" id="submitBtn" class="btn-primary">
                    <i class="fas fa-plus"></i> إضافة الفئة
                </button>
            </div>
        </form>
    </div>
</div>


<script>
function openModal(mode, data = null) {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    const title = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    // إعادة تعيين النموذج
    form.reset();
    document.getElementById('catIcon').value = 'fa-folder';
    document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
    document.querySelector('.icon-option[data-icon="fa-folder"]').classList.add('selected');
    
    if (mode === 'edit' && data) {
        title.textContent = 'تعديل الفئة';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> حفظ التغييرات';
        submitBtn.name = 'edit_category';
        
        document.getElementById('categoryId').value = data.id;
        document.getElementById('catName').value = data.name;
        document.getElementById('catSlug').value = data.slug;
        document.getElementById('catOrder').value = data.sort_order;
        document.getElementById('catDesc').value = data.description || '';
        document.getElementById('catImage').value = data.image_url || '';
        document.getElementById('catIcon').value = data.icon || 'fa-folder';
        document.getElementById('catActive').checked = data.is_active == 1;
        
        // تحديد الأيقونة
        document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
        const iconEl = document.querySelector(`.icon-option[data-icon="${data.icon || 'fa-folder'}"]`);
        if (iconEl) iconEl.classList.add('selected');
    } else {
        title.textContent = 'إضافة فئة جديدة';
        submitBtn.innerHTML = '<i class="fas fa-plus"></i> إضافة الفئة';
        submitBtn.name = 'add_category';
        document.getElementById('categoryId').value = '';
    }
    
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('categoryModal').classList.remove('active');
}

function selectIcon(icon) {
    document.getElementById('catIcon').value = icon;
    document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
    document.querySelector(`.icon-option[data-icon="${icon}"]`).classList.add('selected');
}

// إغلاق Modal عند النقر خارجها
document.getElementById('categoryModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// توليد slug تلقائي
document.getElementById('catName').addEventListener('input', function() {
    const slugField = document.getElementById('catSlug');
    if (!slugField.value) {
        // لا نولد تلقائياً، نتركه للسيرفر
    }
});
</script>

<?php include 'includes/admin_footer.php'; ?>
