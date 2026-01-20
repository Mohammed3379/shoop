<?php
/**
 * إضافة منتج جديد - نظام متقدم
 * صلاحيات المدير الكاملة
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

$page_title = 'إضافة منتج جديد';
$page_icon = 'fa-plus-circle';
$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

// جلب الفئات
$categories = [];
$cat_result = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");
if ($cat_result) while ($cat = $cat_result->fetch_assoc()) $categories[] = $cat;

// جلب الحقول المخصصة
$custom_fields = [];
$cf_result = $conn->query("SELECT * FROM product_custom_fields WHERE is_active = 1 ORDER BY sort_order");
if ($cf_result) while ($cf = $cf_result->fetch_assoc()) $custom_fields[] = $cf;

// معالجة الإضافة
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verifyAdminCSRF($_POST['csrf_token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        // الحقول الإجبارية
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $currency = trim($_POST['currency'] ?? 'EGP');
        $quantity = intval($_POST['quantity']);
        $category = trim($_POST['category']);
        
        // الحقول الاختيارية
        $description = trim($_POST['description'] ?? '');
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $discount_type = $_POST['discount_type'] ?? 'none';
        $discount_value = floatval($_POST['discount_value'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        $sku = trim($_POST['sku'] ?? '');
        $weight = floatval($_POST['weight'] ?? 0);
        $dimensions = trim($_POST['dimensions'] ?? '');

        // الألوان والأحجام
        $colors = !empty($_POST['colors']) ? json_encode(array_filter(array_map('trim', explode(',', $_POST['colors']))), JSON_UNESCAPED_UNICODE) : null;
        $sizes = !empty($_POST['sizes']) ? json_encode(array_filter(array_map('trim', explode(',', $_POST['sizes']))), JSON_UNESCAPED_UNICODE) : null;
        
        // حساب السعر النهائي
        $final_price = $price;
        if ($discount_type == 'percentage' && $discount_value > 0) {
            $final_price = $price - ($price * $discount_value / 100);
        } elseif ($discount_type == 'fixed' && $discount_value > 0) {
            $final_price = $price - $discount_value;
        }

        // الصورة الرئيسية
        $main_image = trim($_POST['main_image'] ?? '');
        
        // حقول إضافية
        $extra_fields = [];
        if (!empty($_POST['extra_field_name']) && !empty($_POST['extra_field_value'])) {
            foreach ($_POST['extra_field_name'] as $i => $field_name) {
                if (!empty($field_name) && isset($_POST['extra_field_value'][$i])) {
                    $extra_fields[trim($field_name)] = trim($_POST['extra_field_value'][$i]);
                }
            }
        }
        $extra_fields_json = !empty($extra_fields) ? json_encode($extra_fields, JSON_UNESCAPED_UNICODE) : null;

        // التحقق من الحقول الإجبارية
        if (empty($name) || $price <= 0 || $quantity < 0) {
            $message = 'يرجى ملء جميع الحقول الإجبارية بشكل صحيح!';
            $message_type = 'danger';
        } else {
            // إدخال المنتج
            $stmt = $conn->prepare("INSERT INTO products (name, price, currency, quantity, category, description, manufacturer, discount_type, discount_value, final_price, colors, sizes, status, is_visible, image, extra_fields, sku, weight, dimensions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sdsissssddsssisssds", $name, $price, $currency, $quantity, $category, $description, $manufacturer, $discount_type, $discount_value, $final_price, $colors, $sizes, $status, $is_visible, $main_image, $extra_fields_json, $sku, $weight, $dimensions);

            if ($stmt->execute()) {
                $product_id = $conn->insert_id;
                
                // إضافة الصور المتعددة
                if (!empty($_POST['images'])) {
                    $images = array_filter(array_map('trim', explode("\n", $_POST['images'])));
                    $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                    $sort = 0;
                    foreach ($images as $img_url) {
                        if (!empty($img_url)) {
                            $is_primary = ($sort == 0 && empty($main_image)) ? 1 : 0;
                            $img_stmt->bind_param("isii", $product_id, $img_url, $is_primary, $sort);
                            $img_stmt->execute();
                            $sort++;
                        }
                    }
                    $img_stmt->close();
                }

                // إضافة الفيديوهات
                if (!empty($_POST['videos'])) {
                    $videos = array_filter(array_map('trim', explode("\n", $_POST['videos'])));
                    $vid_stmt = $conn->prepare("INSERT INTO product_videos (product_id, video_url, video_type, sort_order) VALUES (?, ?, ?, ?)");
                    $sort = 0;
                    foreach ($videos as $vid_url) {
                        if (!empty($vid_url)) {
                            $video_type = 'external';
                            if (strpos($vid_url, 'youtube') !== false || strpos($vid_url, 'youtu.be') !== false) {
                                $video_type = 'youtube';
                            } elseif (strpos($vid_url, 'vimeo') !== false) {
                                $video_type = 'vimeo';
                            }
                            $vid_stmt->bind_param("issi", $product_id, $vid_url, $video_type, $sort);
                            $vid_stmt->execute();
                            $sort++;
                        }
                    }
                    $vid_stmt->close();
                }
                
                // تسجيل في سجل التغييرات
                $admin_id = $_SESSION['admin_id'] ?? 1;
                $new_data = json_encode(['name' => $name, 'price' => $price, 'status' => $status], JSON_UNESCAPED_UNICODE);
                $hist_stmt = $conn->prepare("INSERT INTO product_history (product_id, admin_id, action_type, new_data) VALUES (?, ?, 'create', ?)");
                $hist_stmt->bind_param("iis", $product_id, $admin_id, $new_data);
                $hist_stmt->execute();
                $hist_stmt->close();
                
                $message = 'تمت إضافة المنتج بنجاح!';
                $message_type = 'success';
                logAdminAction('add_product', "تم إضافة المنتج: $name (ID: $product_id)");
                $_POST = [];
            } else {
                $message = 'حدث خطأ في إضافة المنتج: ' . $conn->error;
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}

include 'includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>" style="animation: slideIn 0.3s ease;">
    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
    <?php if ($message_type == 'success'): ?>
        <div style="margin-top: 10px;">
            <a href="admin.php" class="btn btn-sm btn-outline" style="margin-left: 10px;">العودة للوحة التحكم</a>
            <a href="add_product.php" class="btn btn-sm btn-primary">إضافة منتج آخر</a>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" id="product-form" enctype="multipart/form-data">
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
                        <input type="text" name="name" class="form-control" required placeholder="أدخل اسم المنتج" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">السعر <span class="required">*</span></label>
                            <input type="number" name="price" id="price-input" class="form-control" required step="0.01" min="0" placeholder="0.00" value="<?php echo $_POST['price'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">العملة</label>
                            <input type="text" class="form-control" value="<?php echo getCurrencyName(); ?> (<?php echo getCurrencyCode(); ?>)" readonly style="background:#333;">
                            <input type="hidden" name="currency" value="<?php echo getCurrencyCode(); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الكمية <span class="required">*</span></label>
                            <input type="number" name="quantity" class="form-control" required min="0" placeholder="0" value="<?php echo $_POST['quantity'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">التصنيف <span class="required">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="">اختر التصنيف</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['slug']; ?>" <?php echo ($_POST['category'] ?? '') == $cat['slug'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="electronics">إلكترونيات</option>
                            <option value="fashion">أزياء رجالية</option>
                            <option value="women">أزياء نسائية</option>
                            <option value="watches">ساعات</option>
                            <option value="perfume">عطور</option>
                            <option value="shoes">أحذية</option>
                            <option value="accessories">إكسسوارات</option>
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
                        <textarea name="description" class="form-control" rows="4" placeholder="اكتب وصفاً تفصيلياً للمنتج..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">الشركة المصنعة</label>
                            <input type="text" name="manufacturer" class="form-control" placeholder="مثال: Apple, Samsung..." value="<?php echo htmlspecialchars($_POST['manufacturer'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رمز المنتج (SKU)</label>
                            <input type="text" name="sku" class="form-control" placeholder="مثال: PRD-001" value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">الألوان المتوفرة</label>
                            <input type="text" name="colors" class="form-control" placeholder="أحمر, أزرق, أسود (مفصولة بفاصلة)" value="<?php echo htmlspecialchars($_POST['colors'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأحجام المتوفرة</label>
                            <input type="text" name="sizes" class="form-control" placeholder="S, M, L, XL (مفصولة بفاصلة)" value="<?php echo htmlspecialchars($_POST['sizes'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">الوزن (كجم)</label>
                            <input type="number" name="weight" class="form-control" step="0.01" min="0" placeholder="0.00" value="<?php echo $_POST['weight'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأبعاد (سم)</label>
                            <input type="text" name="dimensions" class="form-control" placeholder="الطول × العرض × الارتفاع" value="<?php echo htmlspecialchars($_POST['dimensions'] ?? ''); ?>">
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
                                <option value="none">بدون خصم</option>
                                <option value="percentage">نسبة مئوية %</option>
                                <option value="fixed">مبلغ ثابت</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">قيمة الخصم</label>
                            <input type="number" name="discount_value" id="discount-value" class="form-control" min="0" step="0.01" placeholder="0" value="0" onchange="calculateFinalPrice()" oninput="calculateFinalPrice()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">السعر النهائي</label>
                            <input type="text" id="final-price" class="form-control final-price-display" readonly>
                        </div>
                    </div>
                    <div class="discount-preview" id="discount-preview" style="display: none;">
                        <i class="fas fa-tag"></i>
                        <span id="discount-text"></span>
                    </div>
                </div>
            </div>
            
            <!-- الوسائط -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-images"></i> الوسائط (صور وفيديو)</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">الصورة الرئيسية (رابط)</label>
                        <div class="input-with-preview">
                            <input type="url" name="main_image" id="main-image" class="form-control" placeholder="https://example.com/image.jpg" oninput="previewMainImage(this.value)">
                            <div class="image-preview-small" id="main-image-preview"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">صور إضافية (رابط في كل سطر)</label>
                        <textarea name="images" id="additional-images" class="form-control" rows="3" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg" oninput="previewAdditionalImages()"></textarea>
                        <div class="images-preview-grid" id="images-preview"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">فيديوهات (رابط في كل سطر)</label>
                        <textarea name="videos" class="form-control" rows="2" placeholder="https://youtube.com/watch?v=..."></textarea>
                        <small class="form-hint"><i class="fas fa-info-circle"></i> يدعم YouTube, Vimeo أو رابط مباشر</small>
                    </div>
                </div>
            </div>

            <!-- حقول إضافية -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-plus-square"></i> حقول إضافية (مخصصة)</h3>
                    <button type="button" class="btn btn-sm btn-outline" onclick="addExtraField()">
                        <i class="fas fa-plus"></i> إضافة حقل
                    </button>
                </div>
                <div class="card-body">
                    <div id="extra-fields-container"></div>
                    <p class="form-hint"><i class="fas fa-lightbulb"></i> أضف معلومات إضافية مثل: الضمان، بلد المنشأ، المواصفات التقنية...</p>
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
                            <option value="active">✅ متوفر</option>
                            <option value="out_of_stock">❌ نفذت الكمية</option>
                            <option value="paused">⏸️ متوقف مؤقتاً</option>
                            <option value="hidden">👁️ مخفي</option>
                        </select>
                    </div>
                    
                    <div class="visibility-toggle">
                        <label class="toggle-label">
                            <input type="checkbox" name="is_visible" value="1" checked>
                            <span class="toggle-switch"></span>
                            <div class="toggle-text">
                                <span class="toggle-title">إظهار في المتجر</span>
                                <span class="toggle-desc">المنتج سيظهر للعملاء</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- معاينة المنتج -->
            <div class="card sticky-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-eye"></i> معاينة</h3>
                </div>
                <div class="card-body">
                    <div class="product-preview-card">
                        <div class="preview-image" id="preview-image">
                            <i class="fas fa-image"></i>
                        </div>
                        <div class="preview-content">
                            <div class="preview-category" id="preview-category">التصنيف</div>
                            <h4 class="preview-name" id="preview-name">اسم المنتج</h4>
                            <div class="preview-price">
                                <span class="preview-final-price" id="preview-final-price">0 <?php echo getCurrencySymbol(); ?></span>
                                <span class="preview-old-price" id="preview-old-price"></span>
                            </div>
                            <div class="preview-status" id="preview-status">
                                <span class="status-badge status-active">متوفر</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-plus"></i> إضافة المنتج
                        </button>
                        <a href="admin.php" class="btn btn-outline btn-block">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.product-form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
.form-main-column .card { margin-bottom: 25px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
.required { color: var(--danger); }
.form-hint { color: var(--text-muted); font-size: 12px; margin-top: 5px; display: flex; align-items: center; gap: 5px; }
.final-price-display { background: var(--bg-hover) !important; color: var(--secondary) !important; font-weight: 700 !important; font-size: 16px !important; }
.discount-preview { background: linear-gradient(135deg, var(--secondary), #10b981); color: white; padding: 12px 15px; border-radius: var(--radius-md); margin-top: 15px; display: flex; align-items: center; gap: 10px; font-weight: 600; }
.input-with-preview { display: flex; gap: 10px; align-items: center; }
.input-with-preview input { flex: 1; }
.image-preview-small { width: 50px; height: 50px; border-radius: var(--radius-sm); background: var(--bg-hover); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.image-preview-small img { width: 100%; height: 100%; object-fit: cover; }
.images-preview-grid { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
.images-preview-grid .preview-thumb { width: 60px; height: 60px; border-radius: var(--radius-sm); overflow: hidden; }
.images-preview-grid .preview-thumb img { width: 100%; height: 100%; object-fit: cover; }
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
.extra-field-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; animation: slideIn 0.3s ease; }
.extra-field-row input { flex: 1; }
.extra-field-row .btn-danger { padding: 10px 12px; }
@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
@media (max-width: 1024px) { .product-form-grid { grid-template-columns: 1fr; } .sticky-card { position: static; } }
@media (max-width: 768px) { 
    .form-row-2, .form-row-3 { grid-template-columns: 1fr; } 
    .card { margin-bottom: 15px; }
    .card-header { padding: 15px; flex-wrap: wrap; gap: 10px; }
    .card-body { padding: 15px; }
    .form-group { margin-bottom: 15px; }
    .form-control { padding: 10px 12px; font-size: 14px; }
    .input-with-preview { flex-direction: column; }
    .image-preview-small { width: 100%; height: 100px; }
    .preview-image { height: 120px; }
    .preview-content { padding: 12px; }
    .preview-final-price { font-size: 16px; }
    .btn-block { padding: 12px; font-size: 14px; }
    .discount-preview { padding: 10px 12px; font-size: 13px; }
    .toggle-label { padding: 12px; gap: 10px; }
    .toggle-switch { width: 44px; height: 24px; }
    .toggle-switch::after { width: 20px; height: 20px; }
    .toggle-label input:checked + .toggle-switch::after { left: 22px; }
}
@media (max-width: 480px) {
    .card-header h3 { font-size: 14px; }
    .form-label { font-size: 13px; }
    .form-hint { font-size: 11px; }
    .extra-field-row { flex-direction: column; }
    .extra-field-row input { width: 100%; }
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
    const price = parseFloat(document.getElementById('price-input').value) || 0;
    const discountType = document.getElementById('discount-type').value;
    const discountValue = parseFloat(document.getElementById('discount-value').value) || 0;
    const symbol = '<?php echo getCurrencySymbol(); ?>';
    
    let finalPrice = price;
    let discountText = '';
    
    if (discountType === 'percentage' && discountValue > 0) {
        finalPrice = price - (price * discountValue / 100);
        discountText = `خصم ${discountValue}% - توفير ${(price - finalPrice).toFixed(2)} ${symbol}`;
    } else if (discountType === 'fixed' && discountValue > 0) {
        finalPrice = price - discountValue;
        discountText = `خصم ${discountValue} ${symbol}`;
    }
    
    document.getElementById('final-price').value = finalPrice.toFixed(2) + ' ' + symbol;
    document.getElementById('preview-final-price').textContent = finalPrice.toFixed(2) + ' ' + symbol;
    
    const oldPriceEl = document.getElementById('preview-old-price');
    const discountPreview = document.getElementById('discount-preview');
    
    if (discountType !== 'none' && discountValue > 0 && price > 0) {
        oldPriceEl.textContent = price.toFixed(2) + ' ' + symbol;
        oldPriceEl.style.display = 'inline';
        discountPreview.style.display = 'flex';
        document.getElementById('discount-text').textContent = discountText;
    } else {
        oldPriceEl.style.display = 'none';
        discountPreview.style.display = 'none';
    }
}

document.getElementById('price-input').addEventListener('input', calculateFinalPrice);

// معاينة الصورة الرئيسية
function previewMainImage(url) {
    const container = document.getElementById('main-image-preview');
    const previewImage = document.getElementById('preview-image');
    
    if (url && isValidUrl(url)) {
        container.innerHTML = `<img src="${url}" onerror="this.parentElement.innerHTML=''">`;
        previewImage.innerHTML = `<img src="${url}" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-image\\'></i>'">`;
    } else {
        container.innerHTML = '';
        previewImage.innerHTML = '<i class="fas fa-image"></i>';
    }
}

// معاينة الصور الإضافية
function previewAdditionalImages() {
    const textarea = document.getElementById('additional-images');
    const container = document.getElementById('images-preview');
    const urls = textarea.value.split('\n').filter(url => url.trim() && isValidUrl(url.trim()));
    
    container.innerHTML = urls.slice(0, 5).map(url => 
        `<div class="preview-thumb"><img src="${url.trim()}" onerror="this.parentElement.remove()"></div>`
    ).join('');
}

function isValidUrl(string) {
    try { new URL(string); return true; } catch (_) { return false; }
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
            <input type="text" name="extra_field_name[]" class="form-control" placeholder="اسم الحقل (مثال: الضمان)">
            <input type="text" name="extra_field_value[]" class="form-control" placeholder="القيمة (مثال: سنتين)">
            <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', fieldHtml);
}

// تهيئة عند التحميل
document.addEventListener('DOMContentLoaded', function() {
    calculateFinalPrice();
    updateStatusPreview();
});
</script>

<?php include 'includes/admin_footer.php'; ?>
