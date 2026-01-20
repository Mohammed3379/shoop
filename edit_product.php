<?php
/**
 * تعديل المنتج - نظام متقدم
 * صلاحيات المدير الكاملة
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();
$csrf_token = getAdminCSRF();

if (!isset($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: admin.php");
    exit();
}

$page_title = 'تعديل: ' . mb_substr($product['name'], 0, 25);
$page_icon = 'fa-edit';
$message = '';
$message_type = '';

// جلب الفئات
$categories = [];
$cat_result = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");
if ($cat_result) while ($cat = $cat_result->fetch_assoc()) $categories[] = $cat;

// جلب الصور والفيديوهات باستخدام prepared statements
$images = [];
$img_stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
$img_stmt->bind_param("i", $id);
$img_stmt->execute();
$img_result = $img_stmt->get_result();
while ($img = $img_result->fetch_assoc()) $images[] = $img;
$img_stmt->close();

$videos = [];
$vid_stmt = $conn->prepare("SELECT * FROM product_videos WHERE product_id = ? ORDER BY sort_order");
$vid_stmt->bind_param("i", $id);
$vid_stmt->execute();
$vid_result = $vid_stmt->get_result();
while ($vid = $vid_result->fetch_assoc()) $videos[] = $vid;
$vid_stmt->close();

// تحليل JSON
$colors = !empty($product['colors']) ? implode(', ', json_decode($product['colors'], true) ?? []) : '';
$sizes = !empty($product['sizes']) ? implode(', ', json_decode($product['sizes'], true) ?? []) : '';
$extra_fields = !empty($product['extra_fields']) ? json_decode($product['extra_fields'], true) : [];

// معالجة التحديث
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verifyAdminCSRF($_POST['csrf_token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        // حفظ البيانات القديمة للسجل
        $old_data = json_encode([
            'name' => $product['name'],
            'price' => $product['price'],
            'status' => $product['status']
        ], JSON_UNESCAPED_UNICODE);
        
        // الحقول
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $currency = trim($_POST['currency'] ?? 'EGP');
        $quantity = intval($_POST['quantity']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description'] ?? '');
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $discount_type = $_POST['discount_type'] ?? 'none';
        $discount_value = floatval($_POST['discount_value'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        $main_image = trim($_POST['main_image'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $weight = floatval($_POST['weight'] ?? 0);
        $dimensions = trim($_POST['dimensions'] ?? '');

        // الألوان والأحجام
        $colors_json = !empty($_POST['colors']) ? json_encode(array_filter(array_map('trim', explode(',', $_POST['colors']))), JSON_UNESCAPED_UNICODE) : null;
        $sizes_json = !empty($_POST['sizes']) ? json_encode(array_filter(array_map('trim', explode(',', $_POST['sizes']))), JSON_UNESCAPED_UNICODE) : null;
        
        // حساب السعر النهائي
        $final_price = $price;
        if ($discount_type == 'percentage' && $discount_value > 0) {
            $final_price = $price - ($price * $discount_value / 100);
        } elseif ($discount_type == 'fixed' && $discount_value > 0) {
            $final_price = $price - $discount_value;
        }
        
        // حقول إضافية
        $new_extra_fields = [];
        if (!empty($_POST['extra_field_name']) && !empty($_POST['extra_field_value'])) {
            foreach ($_POST['extra_field_name'] as $i => $field_name) {
                if (!empty($field_name) && isset($_POST['extra_field_value'][$i])) {
                    $new_extra_fields[trim($field_name)] = trim($_POST['extra_field_value'][$i]);
                }
            }
        }
        $extra_fields_json = !empty($new_extra_fields) ? json_encode($new_extra_fields, JSON_UNESCAPED_UNICODE) : null;

        // تحديث المنتج
        $stmt = $conn->prepare("UPDATE products SET name=?, price=?, currency=?, quantity=?, category=?, description=?, manufacturer=?, discount_type=?, discount_value=?, final_price=?, colors=?, sizes=?, status=?, is_visible=?, image=?, extra_fields=?, sku=?, weight=?, dimensions=? WHERE id=?");
        $stmt->bind_param("sdsissssddsssisssdsi", $name, $price, $currency, $quantity, $category, $description, $manufacturer, $discount_type, $discount_value, $final_price, $colors_json, $sizes_json, $status, $is_visible, $main_image, $extra_fields_json, $sku, $weight, $dimensions, $id);

        if ($stmt->execute()) {
            // تحديث الصور
            if (isset($_POST['update_images'])) {
                $del_img_stmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
                $del_img_stmt->bind_param("i", $id);
                $del_img_stmt->execute();
                $del_img_stmt->close();
                if (!empty($_POST['images'])) {
                    $images_arr = array_filter(array_map('trim', explode("\n", $_POST['images'])));
                    $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)");
                    $sort = 0;
                    foreach ($images_arr as $img_url) {
                        if (!empty($img_url)) {
                            $img_stmt->bind_param("isi", $id, $img_url, $sort);
                            $img_stmt->execute();
                            $sort++;
                        }
                    }
                    $img_stmt->close();
                }
            }

            // تحديث الفيديوهات
            if (isset($_POST['update_videos'])) {
                $del_vid_stmt = $conn->prepare("DELETE FROM product_videos WHERE product_id = ?");
                $del_vid_stmt->bind_param("i", $id);
                $del_vid_stmt->execute();
                $del_vid_stmt->close();
                if (!empty($_POST['videos'])) {
                    $videos_arr = array_filter(array_map('trim', explode("\n", $_POST['videos'])));
                    $vid_stmt = $conn->prepare("INSERT INTO product_videos (product_id, video_url, video_type, sort_order) VALUES (?, ?, ?, ?)");
                    $sort = 0;
                    foreach ($videos_arr as $vid_url) {
                        if (!empty($vid_url)) {
                            $video_type = 'external';
                            if (strpos($vid_url, 'youtube') !== false || strpos($vid_url, 'youtu.be') !== false) {
                                $video_type = 'youtube';
                            } elseif (strpos($vid_url, 'vimeo') !== false) {
                                $video_type = 'vimeo';
                            }
                            $vid_stmt->bind_param("issi", $id, $vid_url, $video_type, $sort);
                            $vid_stmt->execute();
                            $sort++;
                        }
                    }
                    $vid_stmt->close();
                }
            }
            
            // تسجيل في سجل التغييرات
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $new_data = json_encode(['name' => $name, 'price' => $price, 'status' => $status], JSON_UNESCAPED_UNICODE);
            $hist_stmt = $conn->prepare("INSERT INTO product_history (product_id, admin_id, action_type, old_data, new_data) VALUES (?, ?, 'update', ?, ?)");
            $hist_stmt->bind_param("iiss", $id, $admin_id, $old_data, $new_data);
            $hist_stmt->execute();
            $hist_stmt->close();
            
            // إغلاق الـ statement الأول
            $stmt->close();
            
            // إعادة جلب البيانات المحدثة
            $refresh_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $refresh_stmt->bind_param("i", $id);
            $refresh_stmt->execute();
            $product = $refresh_stmt->get_result()->fetch_assoc();
            $refresh_stmt->close();
            
            $colors = !empty($product['colors']) ? implode(', ', json_decode($product['colors'], true) ?? []) : '';
            $sizes = !empty($product['sizes']) ? implode(', ', json_decode($product['sizes'], true) ?? []) : '';
            $extra_fields = !empty($product['extra_fields']) ? json_decode($product['extra_fields'], true) : [];
            
            // إعادة جلب الصور والفيديوهات
            $images = [];
            $img_result = $conn->query("SELECT * FROM product_images WHERE product_id = $id ORDER BY sort_order");
            if ($img_result) while ($img = $img_result->fetch_assoc()) $images[] = $img;
            
            $videos = [];
            $vid_result = $conn->query("SELECT * FROM product_videos WHERE product_id = $id ORDER BY sort_order");
            if ($vid_result) while ($vid = $vid_result->fetch_assoc()) $videos[] = $vid;
        } else {
            $message = 'حدث خطأ في تحديث المنتج: ' . $conn->error;
            $message_type = 'danger';
            $stmt->close();
        }
    }
}

// حذف المنتج
if (isset($_GET['delete']) && $_GET['delete'] == 1) {
    if (isset($_GET['confirm']) && $_GET['confirm'] == $csrf_token) {
        $admin_id = $_SESSION['admin_id'] ?? 1;
        $hist_stmt = $conn->prepare("INSERT INTO product_history (product_id, admin_id, action_type, old_data) VALUES (?, ?, 'delete', ?)");
        $old_data = json_encode(['name' => $product['name']], JSON_UNESCAPED_UNICODE);
        $hist_stmt->bind_param("iis", $id, $admin_id, $old_data);
        $hist_stmt->execute();
        $hist_stmt->close();
        
        $del_stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $del_stmt->bind_param("i", $id);
        $del_stmt->execute();
        $del_stmt->close();
        
        logAdminAction('delete_product', "تم حذف المنتج: {$product['name']} (ID: $id)");
        header("Location: admin.php?msg=deleted");
        exit();
    }
}

include 'includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- شريط الإجراءات -->
<div class="action-bar">
    <div class="action-bar-info">
        <span class="product-id">ID: <?php echo $id; ?></span>
        <?php if (!empty($product['sku'])): ?>
            <span class="product-sku">SKU: <?php echo htmlspecialchars($product['sku']); ?></span>
        <?php endif; ?>
        <span class="product-date">آخر تحديث: <?php echo date('Y/m/d H:i', strtotime($product['updated_at'] ?? 'now')); ?></span>
    </div>
    <div class="action-bar-buttons">
        <a href="product.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-outline btn-sm">
            <i class="fas fa-external-link-alt"></i> معاينة
        </a>
        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete()">
            <i class="fas fa-trash"></i> حذف
        </button>
    </div>
</div>

<form method="POST" id="product-form">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="product-form-grid">
        <!-- العمود الأيسر -->
        <div class="form-main-column">
            <!-- الحقول الإجبارية -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-asterisk" style="color: var(--danger);"></i> الحقول الإجبارية</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">اسم المنتج <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">السعر <span class="required">*</span></label>
                            <input type="number" name="price" id="price-input" class="form-control" required step="0.01" min="0" value="<?php echo $product['price']; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">العملة</label>
                            <input type="text" class="form-control" value="<?php echo getCurrencyName(); ?> (<?php echo getCurrencyCode(); ?>)" readonly style="background:#333;">
                            <input type="hidden" name="currency" value="<?php echo getCurrencyCode(); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الكمية</label>
                            <input type="number" name="quantity" class="form-control" min="0" value="<?php echo $product['quantity'] ?? 0; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">التصنيف</label>
                        <select name="category" class="form-control">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['slug']; ?>" <?php echo $product['category'] == $cat['slug'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="electronics" <?php echo $product['category'] == 'electronics' ? 'selected' : ''; ?>>إلكترونيات</option>
                            <option value="fashion" <?php echo $product['category'] == 'fashion' ? 'selected' : ''; ?>>أزياء رجالية</option>
                            <option value="women" <?php echo $product['category'] == 'women' ? 'selected' : ''; ?>>أزياء نسائية</option>
                            <option value="watches" <?php echo $product['category'] == 'watches' ? 'selected' : ''; ?>>ساعات</option>
                            <option value="perfume" <?php echo $product['category'] == 'perfume' ? 'selected' : ''; ?>>عطور</option>
                            <option value="shoes" <?php echo $product['category'] == 'shoes' ? 'selected' : ''; ?>>أحذية</option>
                            <option value="accessories" <?php echo $product['category'] == 'accessories' ? 'selected' : ''; ?>>إكسسوارات</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- الحقول الاختيارية -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-sliders-h"></i> الحقول الاختيارية</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">وصف المنتج</label>
                        <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">الشركة المصنعة</label>
                            <input type="text" name="manufacturer" class="form-control" value="<?php echo htmlspecialchars($product['manufacturer'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رمز المنتج (SKU)</label>
                            <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">الألوان المتوفرة</label>
                            <input type="text" name="colors" class="form-control" placeholder="مفصولة بفاصلة" value="<?php echo htmlspecialchars($colors); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأحجام المتوفرة</label>
                            <input type="text" name="sizes" class="form-control" placeholder="مفصولة بفاصلة" value="<?php echo htmlspecialchars($sizes); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">الوزن (كجم)</label>
                            <input type="number" name="weight" class="form-control" step="0.01" min="0" value="<?php echo $product['weight'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأبعاد (سم)</label>
                            <input type="text" name="dimensions" class="form-control" value="<?php echo htmlspecialchars($product['dimensions'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- الخصم والتسعير -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-percent"></i> الخصم والتسعير</h3>
                </div>
                <div class="card-body">
                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">نوع الخصم</label>
                            <select name="discount_type" id="discount-type" class="form-control" onchange="calculateFinalPrice()">
                                <option value="none" <?php echo ($product['discount_type'] ?? '') == 'none' ? 'selected' : ''; ?>>بدون خصم</option>
                                <option value="percentage" <?php echo ($product['discount_type'] ?? '') == 'percentage' ? 'selected' : ''; ?>>نسبة مئوية %</option>
                                <option value="fixed" <?php echo ($product['discount_type'] ?? '') == 'fixed' ? 'selected' : ''; ?>>مبلغ ثابت</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">قيمة الخصم</label>
                            <input type="number" name="discount_value" id="discount-value" class="form-control" min="0" step="0.01" value="<?php echo $product['discount_value'] ?? 0; ?>" onchange="calculateFinalPrice()" oninput="calculateFinalPrice()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">السعر النهائي</label>
                            <input type="text" id="final-price" class="form-control final-price-display" readonly>
                        </div>
                    </div>
                    
                    <!-- إحصائيات البيع -->
                    <div class="sales-stats">
                        <div class="stat-item">
                            <i class="fas fa-shopping-cart"></i>
                            <span>عدد المبيعات: <strong><?php echo $product['sales_count'] ?? 0; ?></strong></span>
                        </div>
                        <?php if (!empty($product['last_sale_date'])): ?>
                        <div class="stat-item">
                            <i class="fas fa-calendar"></i>
                            <span>آخر بيع: <strong><?php echo date('Y/m/d', strtotime($product['last_sale_date'])); ?></strong></span>
                        </div>
                        <?php endif; ?>
                        <div class="stat-item">
                            <i class="fas fa-star"></i>
                            <span>التقييم: <strong><?php echo $product['rating'] ?? 0; ?>/5</strong> (<?php echo $product['rating_count'] ?? 0; ?> تقييم)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الوسائط -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-images"></i> الوسائط</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">الصورة الرئيسية</label>
                        <div class="input-with-preview">
                            <input type="url" name="main_image" id="main-image" class="form-control" value="<?php echo htmlspecialchars($product['image'] ?? ''); ?>" oninput="previewMainImage(this.value)">
                            <div class="image-preview-small" id="main-image-preview">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            صور إضافية
                            <label style="margin-right: 15px; font-weight: normal;">
                                <input type="checkbox" name="update_images" value="1"> تحديث الصور
                            </label>
                        </label>
                        <textarea name="images" class="form-control" rows="3" placeholder="رابط في كل سطر"><?php echo implode("\n", array_column($images, 'image_url')); ?></textarea>
                        <?php if (!empty($images)): ?>
                        <div class="current-images">
                            <?php foreach ($images as $img): ?>
                                <div class="current-image-thumb">
                                    <img src="<?php echo htmlspecialchars($img['image_url']); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            فيديوهات
                            <label style="margin-right: 15px; font-weight: normal;">
                                <input type="checkbox" name="update_videos" value="1"> تحديث الفيديوهات
                            </label>
                        </label>
                        <textarea name="videos" class="form-control" rows="2" placeholder="رابط في كل سطر"><?php echo implode("\n", array_column($videos, 'video_url')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- حقول إضافية -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-plus-square"></i> حقول إضافية</h3>
                    <button type="button" class="btn btn-sm btn-outline" onclick="addExtraField()">
                        <i class="fas fa-plus"></i> إضافة
                    </button>
                </div>
                <div class="card-body">
                    <div id="extra-fields-container">
                        <?php foreach ($extra_fields as $key => $value): ?>
                        <div class="extra-field-row">
                            <input type="text" name="extra_field_name[]" class="form-control" value="<?php echo htmlspecialchars($key); ?>">
                            <input type="text" name="extra_field_value[]" class="form-control" value="<?php echo htmlspecialchars($value); ?>">
                            <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- العمود الأيمن -->
        <div class="form-side-column">
            <!-- حالة المنتج -->
            <div class="card sticky-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-toggle-on"></i> حالة المنتج</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">الحالة</label>
                        <select name="status" id="product-status" class="form-control" onchange="updateStatusPreview()">
                            <option value="active" <?php echo ($product['status'] ?? '') == 'active' ? 'selected' : ''; ?>>✅ متوفر</option>
                            <option value="out_of_stock" <?php echo ($product['status'] ?? '') == 'out_of_stock' ? 'selected' : ''; ?>>❌ نفذت الكمية</option>
                            <option value="paused" <?php echo ($product['status'] ?? '') == 'paused' ? 'selected' : ''; ?>>⏸️ متوقف مؤقتاً</option>
                            <option value="hidden" <?php echo ($product['status'] ?? '') == 'hidden' ? 'selected' : ''; ?>>👁️ مخفي</option>
                        </select>
                    </div>
                    
                    <div class="visibility-toggle">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_visible" value="1" <?php echo ($product['is_visible'] ?? 1) ? 'checked' : ''; ?>>
                            <span class="toggle-switch"></span>
                            <div class="toggle-text">
                                <span class="toggle-title">إظهار في المتجر</span>
                                <span class="toggle-desc">المنتج سيظهر للعملاء</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- معاينة -->
            <div class="card sticky-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-eye"></i> معاينة</h3>
                </div>
                <div class="card-body">
                    <div class="product-preview-card">
                        <div class="preview-image" id="preview-image">
                            <?php if (!empty($product['image'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>">
                            <?php else: ?>
                                <i class="fas fa-image"></i>
                            <?php endif; ?>
                        </div>
                        <div class="preview-content">
                            <div class="preview-category" id="preview-category"><?php echo htmlspecialchars($product['category']); ?></div>
                            <h4 class="preview-name" id="preview-name"><?php echo htmlspecialchars($product['name']); ?></h4>
                            <div class="preview-price">
                                <span class="preview-final-price" id="preview-final-price"><?php echo formatPrice($product['final_price'] ?? $product['price']); ?></span>
                                <?php if (($product['discount_type'] ?? 'none') != 'none' && ($product['discount_value'] ?? 0) > 0): ?>
                                    <span class="preview-old-price" id="preview-old-price"><?php echo formatPrice($product['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="preview-status" id="preview-status">
                                <?php
                                $statusMap = [
                                    'active' => ['status-active', 'متوفر'],
                                    'out_of_stock' => ['status-out', 'نفذت الكمية'],
                                    'paused' => ['status-paused', 'متوقف مؤقتاً'],
                                    'hidden' => ['status-hidden', 'مخفي']
                                ];
                                $st = $statusMap[$product['status'] ?? 'active'] ?? ['status-active', 'متوفر'];
                                ?>
                                <span class="status-badge <?php echo $st[0]; ?>"><?php echo $st[1]; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> حفظ التغييرات
                        </button>
                        <a href="admin.php" class="btn btn-outline btn-block">
                            <i class="fas fa-arrow-right"></i> العودة
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.action-bar { display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); padding: 15px 20px; border-radius: var(--radius-md); margin-bottom: 25px; }
.action-bar-info { display: flex; gap: 20px; color: var(--text-muted); font-size: 13px; }
.action-bar-info span { display: flex; align-items: center; gap: 5px; }
.action-bar-buttons { display: flex; gap: 10px; }
.product-form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
.form-main-column .card { margin-bottom: 25px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
.required { color: var(--danger); }
.final-price-display { background: var(--bg-hover) !important; color: var(--secondary) !important; font-weight: 700 !important; font-size: 16px !important; }
.sales-stats { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color); }
.stat-item { display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 13px; }
.stat-item i { color: var(--primary); }
.stat-item strong { color: var(--text-primary); }
.input-with-preview { display: flex; gap: 10px; align-items: center; }
.input-with-preview input { flex: 1; }
.image-preview-small { width: 50px; height: 50px; border-radius: var(--radius-sm); background: var(--bg-hover); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.image-preview-small img { width: 100%; height: 100%; object-fit: cover; }
.current-images { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
.current-image-thumb { width: 60px; height: 60px; border-radius: var(--radius-sm); overflow: hidden; border: 2px solid var(--border-color); }
.current-image-thumb img { width: 100%; height: 100%; object-fit: cover; }
.visibility-toggle { margin-top: 15px; }
.toggle-label { display: flex; align-items: center; gap: 15px; cursor: pointer; padding: 15px; background: var(--bg-hover); border-radius: var(--radius-md); transition: all 0.3s ease; }
.toggle-label:hover { background: var(--bg-card); }
.toggle-label input { display: none; }
.toggle-switch { width: 50px; height: 26px; background: #444; border-radius: 13px; position: relative; transition: all 0.3s ease; flex-shrink: 0; }
.toggle-switch::after { content: ''; position: absolute; width: 22px; height: 22px; background: white; border-radius: 50%; top: 2px; left: 2px; transition: all 0.3s ease; }
.toggle-label input:checked + .toggle-switch { background: var(--secondary); }
.toggle-label input:checked + .toggle-switch::after { left: 26px; }
.toggle-text { flex: 1; }
.toggle-title { display: block; color: var(--text-primary); font-weight: 600; }
.toggle-desc { display: block; color: var(--text-muted); font-size: 12px; margin-top: 2px; }
.sticky-card { position: sticky; top: 100px; }
.product-preview-card { background: var(--bg-hover); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 20px; }
.preview-image { height: 150px; background: #1a1a1a; display: flex; align-items: center; justify-content: center; }
.preview-image i { font-size: 40px; color: #333; }
.preview-image img { width: 100%; height: 100%; object-fit: contain; }
.preview-content { padding: 15px; }
.preview-category { font-size: 11px; color: var(--primary); margin-bottom: 5px; }
.preview-name { color: var(--text-primary); font-size: 14px; margin-bottom: 10px; }
.preview-price { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.preview-final-price { color: var(--secondary); font-size: 18px; font-weight: 700; }
.preview-old-price { color: var(--text-muted); text-decoration: line-through; font-size: 13px; }
.status-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.status-active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-out { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.status-paused { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-hidden { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
.form-actions { display: flex; flex-direction: column; gap: 10px; }
.btn-block { width: 100%; justify-content: center; }
.extra-field-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
.extra-field-row input { flex: 1; }
@media (max-width: 1024px) { .product-form-grid { grid-template-columns: 1fr; } .sticky-card { position: static; } .action-bar { flex-direction: column; gap: 15px; } }
@media (max-width: 768px) { 
    .form-row-2, .form-row-3 { grid-template-columns: 1fr; } 
    .sales-stats { flex-direction: column; gap: 10px; }
    .card { margin-bottom: 15px; }
    .card-header { padding: 15px; flex-wrap: wrap; gap: 10px; }
    .card-body { padding: 15px; }
    .form-group { margin-bottom: 15px; }
    .form-control { padding: 10px 12px; font-size: 14px; }
    .action-bar { padding: 12px 15px; }
    .action-bar-info { flex-wrap: wrap; gap: 10px; font-size: 12px; }
    .action-bar-buttons { width: 100%; justify-content: center; }
    .input-with-preview { flex-direction: column; }
    .image-preview-small { width: 100%; height: 100px; }
    .current-images { justify-content: center; }
    .preview-image { height: 120px; }
    .preview-content { padding: 12px; }
    .preview-final-price { font-size: 16px; }
    .btn-block { padding: 12px; font-size: 14px; }
    .toggle-label { padding: 12px; gap: 10px; }
    .toggle-switch { width: 44px; height: 24px; }
    .toggle-switch::after { width: 20px; height: 20px; }
    .toggle-label input:checked + .toggle-switch::after { left: 22px; }
    .extra-field-row { flex-direction: column; }
    .extra-field-row input { width: 100%; }
}
@media (max-width: 480px) {
    .card-header h3 { font-size: 14px; }
    .form-label { font-size: 13px; }
    .stat-item { font-size: 12px; }
}
</style>

<script>
// معاينة حية
document.querySelector('input[name="name"]').addEventListener('input', function() {
    document.getElementById('preview-name').textContent = this.value || 'اسم المنتج';
});

document.querySelector('select[name="category"]').addEventListener('change', function() {
    document.getElementById('preview-category').textContent = this.options[this.selectedIndex].text || 'التصنيف';
});

// حساب السعر النهائي
function calculateFinalPrice() {
    const priceInput = document.getElementById('price-input');
    const discountTypeEl = document.getElementById('discount-type');
    const discountValueEl = document.getElementById('discount-value');
    
    if (!priceInput || !discountTypeEl || !discountValueEl) return;
    
    const price = parseFloat(priceInput.value) || 0;
    const discountType = discountTypeEl.value;
    const discountValue = parseFloat(discountValueEl.value) || 0;
    const symbol = '<?php echo getCurrencySymbol(); ?>';
    
    let finalPrice = price;
    if (discountType === 'percentage' && discountValue > 0) {
        finalPrice = price - (price * discountValue / 100);
    } else if (discountType === 'fixed' && discountValue > 0) {
        finalPrice = price - discountValue;
    }
    
    const finalPriceEl = document.getElementById('final-price');
    const previewFinalPriceEl = document.getElementById('preview-final-price');
    const oldPriceEl = document.getElementById('preview-old-price');
    
    if (finalPriceEl) finalPriceEl.value = finalPrice.toFixed(2) + ' ' + symbol;
    if (previewFinalPriceEl) previewFinalPriceEl.textContent = finalPrice.toFixed(2) + ' ' + symbol;
    
    if (oldPriceEl) {
        if (discountType !== 'none' && discountValue > 0 && price > 0) {
            oldPriceEl.textContent = price.toFixed(2) + ' ' + symbol;
            oldPriceEl.style.display = 'inline';
        } else {
            oldPriceEl.style.display = 'none';
        }
    }
}

document.getElementById('price-input').addEventListener('input', calculateFinalPrice);

// معاينة الصورة
function previewMainImage(url) {
    const container = document.getElementById('main-image-preview');
    const previewImage = document.getElementById('preview-image');
    
    if (url) {
        container.innerHTML = `<img src="${url}" onerror="this.parentElement.innerHTML=''">`;
        previewImage.innerHTML = `<img src="${url}" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-image\\'></i>'">`;
    } else {
        container.innerHTML = '';
        previewImage.innerHTML = '<i class="fas fa-image"></i>';
    }
}

// تحديث معاينة الحالة
function updateStatusPreview() {
    const status = document.getElementById('product-status').value;
    const statusMap = {
        'active': ['status-active', 'متوفر'],
        'out_of_stock': ['status-out', 'نفذت الكمية'],
        'paused': ['status-paused', 'متوقف مؤقتاً'],
        'hidden': ['status-hidden', 'مخفي']
    };
    const [className, text] = statusMap[status] || ['status-active', status];
    document.getElementById('preview-status').innerHTML = `<span class="status-badge ${className}">${text}</span>`;
}

// إضافة حقل إضافي
function addExtraField() {
    const container = document.getElementById('extra-fields-container');
    const fieldHtml = `
        <div class="extra-field-row">
            <input type="text" name="extra_field_name[]" class="form-control" placeholder="اسم الحقل">
            <input type="text" name="extra_field_value[]" class="form-control" placeholder="القيمة">
            <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', fieldHtml);
}

// تأكيد الحذف
function confirmDelete() {
    if (confirm('هل أنت متأكد من حذف هذا المنتج؟ لا يمكن التراجع عن هذا الإجراء!')) {
        window.location.href = 'edit_product.php?id=<?php echo $id; ?>&delete=1&confirm=<?php echo $csrf_token; ?>';
    }
}

// تهيئة عند التحميل
document.addEventListener('DOMContentLoaded', function() {
    calculateFinalPrice();
});
</script>

<?php include 'includes/admin_footer.php'; ?>
