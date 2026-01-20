<?php
/**
 * إدارة المنتجات - عرض شامل
 * صلاحيات المدير الكاملة
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

// التحقق من صلاحية عرض المنتجات
requirePermission('products.view');

$csrf_token = getAdminCSRF();

$page_title = 'إدارة المنتجات';
$page_icon = 'fa-boxes';

// صلاحيات المستخدم الحالي
$can_create = hasPermission('products.create');
$can_edit = hasPermission('products.edit');
$can_delete = hasPermission('products.delete');

// الفلاتر
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// بناء الاستعلام
$where = [];
$params = [];
$types = "";

if (!empty($status_filter)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($category_filter)) {
    $where[] = "category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if (!empty($search)) {
    $where[] = "(name LIKE ? OR sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$sql = "SELECT * FROM products";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

// جلب الفئات للفلتر
$categories = $conn->query("SELECT DISTINCT category FROM products ORDER BY category");

// حذف منتج
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (!$can_delete) {
        header("Location: admin_products.php?error=no_permission");
        exit();
    }
    if (verifyAdminCSRF($_GET['token'])) {
        $id = intval($_GET['delete']);
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        logAdminAction('delete_product', "تم حذف المنتج رقم: $id");
        header("Location: admin_products.php?deleted=1");
        exit();
    }
}

// تغيير الحالة السريع
if (isset($_POST['quick_status'])) {
    if (!$can_edit) {
        header("Location: admin_products.php?error=no_permission");
        exit();
    }
    if (verifyAdminCSRF($_POST['csrf_token'])) {
        $id = intval($_POST['product_id']);
        $new_status = $_POST['new_status'];
        $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_products.php?updated=1");
        exit();
    }
}

// تغيير الظهور السريع
if (isset($_POST['toggle_visibility'])) {
    if (verifyAdminCSRF($_POST['csrf_token'])) {
        $id = intval($_POST['product_id']);
        $stmt = $conn->prepare("UPDATE products SET is_visible = NOT is_visible WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_products.php?updated=1");
        exit();
    }
}

include 'includes/admin_header.php';
?>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم حذف المنتج بنجاح!</div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم تحديث المنتج بنجاح!</div>
<?php endif; ?>

<!-- شريط الأدوات -->
<div class="toolbar">
    <div class="toolbar-search">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="بحث بالاسم أو SKU..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    
    <div class="toolbar-filters">
        <select onchange="applyFilter('status', this.value)">
            <option value="">كل الحالات</option>
            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>متوفر</option>
            <option value="out_of_stock" <?php echo $status_filter == 'out_of_stock' ? 'selected' : ''; ?>>نفذت الكمية</option>
            <option value="paused" <?php echo $status_filter == 'paused' ? 'selected' : ''; ?>>متوقف</option>
            <option value="hidden" <?php echo $status_filter == 'hidden' ? 'selected' : ''; ?>>مخفي</option>
        </select>
        
        <select onchange="applyFilter('category', this.value)">
            <option value="">كل الفئات</option>
            <?php while ($cat = $categories->fetch_assoc()): ?>
                <option value="<?php echo $cat['category']; ?>" <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['category']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    
    <div class="toolbar-actions">
        <a href="add_product.php" class="btn btn-success">
            <i class="fas fa-plus"></i> إضافة منتج
        </a>
    </div>
</div>

<!-- جدول المنتجات -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 50px;"><input type="checkbox" id="select-all"></th>
                        <th>المنتج</th>
                        <th>SKU</th>
                        <th>السعر</th>
                        <th>الكمية</th>
                        <th>الحالة</th>
                        <th>الظهور</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products->num_rows > 0): ?>
                        <?php while ($row = $products->fetch_assoc()): 
                            $status_map = [
                                'active' => ['badge-success', 'متوفر', 'fa-check-circle'],
                                'out_of_stock' => ['badge-danger', 'نفذ', 'fa-times-circle'],
                                'paused' => ['badge-warning', 'متوقف', 'fa-pause-circle'],
                                'hidden' => ['badge-info', 'مخفي', 'fa-eye-slash']
                            ];
                            $st = $status_map[$row['status'] ?? 'active'] ?? ['badge-info', 'غير محدد', 'fa-question'];
                            $final_price = $row['final_price'] ?? $row['price'];
                            $has_discount = ($row['discount_type'] ?? 'none') != 'none' && ($row['discount_value'] ?? 0) > 0;
                        ?>
                        <tr>
                            <td><input type="checkbox" class="product-checkbox" value="<?php echo $row['id']; ?>"></td>
                            <td>
                                <div class="product-cell">
                                    <img src="<?php echo htmlspecialchars($row['image']); ?>" class="product-thumb">
                                    <div class="product-info">
                                        <a href="edit_product.php?id=<?php echo $row['id']; ?>" class="product-name">
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </a>
                                        <span class="product-category"><?php echo htmlspecialchars($row['category']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><code><?php echo htmlspecialchars($row['sku'] ?? '-'); ?></code></td>
                            <td>
                                <span class="price-main"><?php echo formatPrice($final_price); ?></span>
                                <?php if ($has_discount): ?>
                                    <span class="price-old"><?php echo formatPrice($row['price']); ?></span>
                                    <span class="discount-tag">-<?php echo $row['discount_value']; ?>%</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="quantity-badge <?php echo ($row['quantity'] ?? 0) <= 5 ? 'low' : ''; ?>">
                                    <?php echo $row['quantity'] ?? 0; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="status-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="quick_status" value="1">
                                    <select name="new_status" class="status-select <?php echo $st[0]; ?>" onchange="this.form.submit()">
                                        <option value="active" <?php echo ($row['status'] ?? '') == 'active' ? 'selected' : ''; ?>>متوفر</option>
                                        <option value="out_of_stock" <?php echo ($row['status'] ?? '') == 'out_of_stock' ? 'selected' : ''; ?>>نفذ</option>
                                        <option value="paused" <?php echo ($row['status'] ?? '') == 'paused' ? 'selected' : ''; ?>>متوقف</option>
                                        <option value="hidden" <?php echo ($row['status'] ?? '') == 'hidden' ? 'selected' : ''; ?>>مخفي</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="toggle_visibility" value="1">
                                    <button type="submit" class="visibility-btn <?php echo ($row['is_visible'] ?? 1) ? 'visible' : 'hidden'; ?>">
                                        <i class="fas fa-<?php echo ($row['is_visible'] ?? 1) ? 'eye' : 'eye-slash'; ?>"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="../product.php?id=<?php echo $row['id']; ?>" target="_blank" class="action-btn view" title="معاينة">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <a href="edit_product.php?id=<?php echo $row['id']; ?>" class="action-btn edit" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="admin_products.php?delete=<?php echo $row['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                       class="action-btn delete" data-confirm="هل تريد حذف هذا المنتج؟" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <h3>لا توجد منتجات</h3>
                                <p>لم يتم العثور على منتجات تطابق معايير البحث</p>
                                <a href="add_product.php" class="btn btn-primary">إضافة منتج جديد</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Products Cards -->
        <div class="products-cards-mobile">
            <?php 
            // Reset the result pointer
            $products->data_seek(0);
            if ($products->num_rows > 0): ?>
                <?php while ($row = $products->fetch_assoc()): 
                    $status_map = [
                        'active' => ['badge-success', 'متوفر'],
                        'out_of_stock' => ['badge-danger', 'نفذ'],
                        'paused' => ['badge-warning', 'متوقف'],
                        'hidden' => ['badge-info', 'مخفي']
                    ];
                    $st = $status_map[$row['status'] ?? 'active'] ?? ['badge-info', 'غير محدد'];
                    $final_price = $row['final_price'] ?? $row['price'];
                    $has_discount = ($row['discount_type'] ?? 'none') != 'none' && ($row['discount_value'] ?? 0) > 0;
                ?>
                <div class="product-card-mobile">
                    <div class="product-card-header">
                        <img src="<?php echo htmlspecialchars($row['image']); ?>" class="product-card-img">
                        <div class="product-card-info">
                            <a href="edit_product.php?id=<?php echo $row['id']; ?>" class="product-card-name">
                                <?php echo htmlspecialchars($row['name']); ?>
                            </a>
                            <span class="product-card-category"><?php echo htmlspecialchars($row['category']); ?></span>
                        </div>
                        <span class="badge <?php echo $st[0]; ?>"><?php echo $st[1]; ?></span>
                    </div>
                    
                    <div class="product-card-body">
                        <div class="product-card-row">
                            <div class="product-card-price">
                                <span class="price-main"><?php echo formatPrice($final_price); ?></span>
                                <?php if ($has_discount): ?>
                                    <span class="price-old"><?php echo formatPrice($row['price']); ?></span>
                                    <span class="discount-tag">-<?php echo $row['discount_value']; ?>%</span>
                                <?php endif; ?>
                            </div>
                            <span class="quantity-badge <?php echo ($row['quantity'] ?? 0) <= 5 ? 'low' : ''; ?>">
                                <i class="fas fa-box"></i> <?php echo $row['quantity'] ?? 0; ?>
                            </span>
                        </div>
                        
                        <div class="product-card-row">
                            <form method="POST" class="status-form-mobile">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="quick_status" value="1">
                                <select name="new_status" class="status-select <?php echo $st[0]; ?>" onchange="this.form.submit()">
                                    <option value="active" <?php echo ($row['status'] ?? '') == 'active' ? 'selected' : ''; ?>>متوفر</option>
                                    <option value="out_of_stock" <?php echo ($row['status'] ?? '') == 'out_of_stock' ? 'selected' : ''; ?>>نفذ</option>
                                    <option value="paused" <?php echo ($row['status'] ?? '') == 'paused' ? 'selected' : ''; ?>>متوقف</option>
                                    <option value="hidden" <?php echo ($row['status'] ?? '') == 'hidden' ? 'selected' : ''; ?>>مخفي</option>
                                </select>
                            </form>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="toggle_visibility" value="1">
                                <button type="submit" class="visibility-btn <?php echo ($row['is_visible'] ?? 1) ? 'visible' : 'hidden'; ?>">
                                    <i class="fas fa-<?php echo ($row['is_visible'] ?? 1) ? 'eye' : 'eye-slash'; ?>"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="product-card-actions">
                        <a href="../product.php?id=<?php echo $row['id']; ?>" target="_blank" class="action-btn view">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <a href="edit_product.php?id=<?php echo $row['id']; ?>" class="action-btn edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="admin_products.php?delete=<?php echo $row['id']; ?>&token=<?php echo $csrf_token; ?>" 
                           class="action-btn delete" data-confirm="هل تريد حذف هذا المنتج؟">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state-mobile">
                    <i class="fas fa-box-open"></i>
                    <h3>لا توجد منتجات</h3>
                    <p>لم يتم العثور على منتجات تطابق معايير البحث</p>
                    <a href="add_product.php" class="btn btn-primary">إضافة منتج جديد</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.toolbar { display: flex; gap: 20px; align-items: center; margin-bottom: 25px; flex-wrap: wrap; background: var(--bg-card); padding: 20px; border-radius: var(--radius-lg); }
.toolbar-search { flex: 1; min-width: 250px; }
.search-form { display: flex; background: var(--bg-hover); border-radius: var(--radius-md); overflow: hidden; }
.search-form input { flex: 1; padding: 12px 15px; background: transparent; border: none; color: var(--text-primary); font-size: 14px; }
.search-form button { padding: 12px 18px; background: var(--primary); border: none; color: white; cursor: pointer; transition: all 0.3s ease; }
.search-form button:hover { background: var(--primary-dark); }
.toolbar-filters { display: flex; gap: 10px; }
.toolbar-filters select { padding: 10px 15px; background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: var(--radius-md); color: var(--text-primary); cursor: pointer; min-width: 130px; }
.toolbar-actions { margin-right: auto; }

.product-cell { display: flex; align-items: center; gap: 12px; }
.product-thumb { width: 50px; height: 50px; border-radius: var(--radius-sm); object-fit: cover; background: white; }
.product-info { display: flex; flex-direction: column; }
.product-name { color: var(--text-primary); font-weight: 600; text-decoration: none; transition: color 0.3s ease; }
.product-name:hover { color: var(--primary); }
.product-category { font-size: 12px; color: var(--text-muted); }

.price-main { color: var(--secondary); font-weight: 700; display: block; }
.price-old { color: var(--text-muted); text-decoration: line-through; font-size: 12px; }
.discount-tag { background: var(--danger); color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: 700; margin-right: 5px; }

.quantity-badge { background: var(--bg-hover); padding: 5px 12px; border-radius: 20px; font-weight: 600; }
.quantity-badge.low { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

.status-form { margin: 0; }
.status-select { padding: 6px 10px; border-radius: var(--radius-sm); border: none; font-size: 12px; font-weight: 600; cursor: pointer; }
.status-select.badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-select.badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.status-select.badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-select.badge-info { background: rgba(99, 102, 241, 0.15); color: #6366f1; }

.visibility-btn { width: 36px; height: 36px; border-radius: 50%; border: none; cursor: pointer; transition: all 0.3s ease; }
.visibility-btn.visible { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.visibility-btn.hidden { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
.visibility-btn:hover { transform: scale(1.1); }

.action-btn.view { background: rgba(99, 102, 241, 0.15); color: #6366f1; }
.action-btn.view:hover { background: #6366f1; color: white; }

.empty-state { text-align: center; padding: 60px 20px !important; }
.empty-state i { font-size: 60px; color: #333; margin-bottom: 20px; }
.empty-state h3 { color: var(--text-primary); margin-bottom: 10px; }
.empty-state p { color: var(--text-muted); margin-bottom: 20px; }

code { background: var(--bg-hover); padding: 3px 8px; border-radius: 4px; font-size: 12px; color: var(--primary); }

@media (max-width: 768px) {
    .toolbar { flex-direction: column; align-items: stretch; }
    .toolbar-search { min-width: 100%; }
    .toolbar-filters { flex-wrap: wrap; }
    .toolbar-actions { margin-right: 0; width: 100%; }
    .toolbar-actions .btn { width: 100%; justify-content: center; }
    
    /* إخفاء بعض الأعمدة */
    .admin-table td:nth-child(1),
    .admin-table th:nth-child(1),
    .admin-table td:nth-child(3),
    .admin-table th:nth-child(3),
    .admin-table td:nth-child(7),
    .admin-table th:nth-child(7) {
        display: none;
    }
    
    .product-cell {
        gap: 8px;
    }
    
    .product-thumb {
        width: 40px;
        height: 40px;
    }
    
    .product-name {
        font-size: 13px;
        max-width: 100px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
    }
    
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .admin-table {
        min-width: 550px;
    }
    
    .admin-table th,
    .admin-table td {
        padding: 10px 8px;
        font-size: 13px;
    }
    
    .action-btns {
        gap: 5px;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }
    
    .price-main {
        font-size: 13px;
    }
    
    .price-old {
        font-size: 10px;
    }
    
    .discount-tag {
        font-size: 9px;
        padding: 1px 4px;
    }
}

@media (max-width: 576px) {
    /* إخفاء المزيد من الأعمدة */
    .admin-table td:nth-child(5),
    .admin-table th:nth-child(5) {
        display: none;
    }
    
    .toolbar-filters select {
        min-width: 100%;
    }
    
    .status-select {
        padding: 4px 6px;
        font-size: 10px;
    }
    
    .visibility-btn {
        width: 30px;
        height: 30px;
    }
    
    .action-btn {
        width: 30px;
        height: 30px;
        font-size: 12px;
    }
    
    .product-thumb {
        width: 35px;
        height: 35px;
    }
    
    .product-name {
        max-width: 80px;
        font-size: 12px;
    }
    
    .product-category {
        font-size: 10px;
    }
    
    .quantity-badge {
        padding: 3px 8px;
        font-size: 11px;
    }
    
    .empty-state {
        padding: 40px 15px !important;
    }
    
    .empty-state i {
        font-size: 40px;
    }
    
    .empty-state h3 {
        font-size: 16px;
    }
    
    .empty-state p {
        font-size: 13px;
    }
}

/* Mobile Products Cards */
.products-cards-mobile {
    display: none;
    padding: 15px;
}

.product-card-mobile {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    margin-bottom: 12px;
    overflow: hidden;
}

.product-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg-hover);
    border-bottom: 1px solid var(--border-color);
}

.product-card-img {
    width: 50px;
    height: 50px;
    border-radius: var(--radius-sm);
    object-fit: cover;
    background: white;
}

.product-card-info {
    flex: 1;
    min-width: 0;
}

.product-card-name {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-card-category {
    font-size: 12px;
    color: var(--text-muted);
}

.product-card-body {
    padding: 12px;
}

.product-card-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.product-card-row:last-child {
    margin-bottom: 0;
}

.product-card-price {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.status-form-mobile {
    margin: 0;
}

.product-card-actions {
    display: flex;
    gap: 8px;
    padding: 12px;
    border-top: 1px solid var(--border-color);
    justify-content: center;
}

.product-card-actions .action-btn {
    flex: 1;
    max-width: 80px;
    justify-content: center;
}

.empty-state-mobile {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-state-mobile i {
    font-size: 50px;
    margin-bottom: 15px;
    display: block;
    color: #333;
}

.empty-state-mobile h3 {
    color: var(--text-primary);
    margin-bottom: 10px;
}

.empty-state-mobile p {
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .table-container {
        display: none;
    }
    
    .products-cards-mobile {
        display: block;
    }
    
    .toolbar { 
        flex-direction: column; 
        align-items: stretch;
        padding: 15px;
    }
    
    .toolbar-search { 
        min-width: 100%; 
    }
    
    .toolbar-filters { 
        flex-wrap: wrap; 
    }
    
    .toolbar-filters select {
        flex: 1;
        min-width: calc(50% - 5px);
    }
    
    .toolbar-actions { 
        margin-right: 0; 
        width: 100%; 
    }
    
    .toolbar-actions .btn { 
        width: 100%; 
        justify-content: center; 
    }
}

@media (max-width: 480px) {
    .toolbar {
        padding: 12px;
    }
    
    .toolbar-filters select {
        min-width: 100%;
    }
    
    .product-card-header {
        padding: 10px;
    }
    
    .product-card-img {
        width: 45px;
        height: 45px;
    }
    
    .product-card-name {
        font-size: 13px;
    }
    
    .product-card-body {
        padding: 10px;
    }
    
    .price-main {
        font-size: 14px;
    }
    
    .status-select {
        padding: 6px 8px;
        font-size: 11px;
    }
    
    .visibility-btn {
        width: 32px;
        height: 32px;
    }
    
    .product-card-actions .action-btn {
        width: 36px;
        height: 36px;
    }
}
</style>

<script>
function applyFilter(type, value) {
    const url = new URL(window.location.href);
    if (value) {
        url.searchParams.set(type, value);
    } else {
        url.searchParams.delete(type);
    }
    window.location.href = url.toString();
}

// تحديد الكل
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = this.checked);
});

// تأكيد الحذف
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm)) {
            e.preventDefault();
        }
    });
});
</script>

<?php include 'includes/admin_footer.php'; ?>
