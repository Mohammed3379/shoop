/* =============================================
   Admin Panel JavaScript - التفاعلات والحركات
   ============================================= */

document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initRippleEffect();
    initTooltips();
    initConfirmDialogs();
    initAnimations();
    initCounters();
    initResponsive();
});

/* ========== الشريط الجانبي ========== */
function initSidebar() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
            
            // تغيير أيقونة الزر
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            }
        });
        
        // إغلاق عند الضغط على الـ overlay
        if (overlay) {
            overlay.addEventListener('click', function() {
                closeSidebar();
            });
        }
        
        // إغلاق عند الضغط على رابط في الموبايل
        if (window.innerWidth <= 1024) {
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function() {
                    closeSidebar();
                });
            });
        }
    }
    
    function closeSidebar() {
        sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');
        
        const icon = menuToggle?.querySelector('i');
        if (icon) {
            icon.classList.add('fa-bars');
            icon.classList.remove('fa-times');
        }
    }
    
    // تحديد العنصر النشط
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href === currentPage) {
            item.classList.add('active');
        }
    });
}

/* ========== التجاوب ========== */
function initResponsive() {
    // إغلاق القائمة عند تغيير حجم الشاشة
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 1024) {
                const sidebar = document.querySelector('.admin-sidebar');
                const overlay = document.querySelector('.sidebar-overlay');
                if (sidebar) sidebar.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            }
        }, 250);
    });
    
    // تحسين الجداول للموبايل
    initResponsiveTables();
    
    // تحسين اللمس للموبايل
    initTouchSupport();
}

/* ========== جداول متجاوبة ========== */
function initResponsiveTables() {
    if (window.innerWidth <= 768) {
        document.querySelectorAll('.admin-table').forEach(table => {
            const headers = table.querySelectorAll('thead th');
            const headerTexts = Array.from(headers).map(th => th.textContent.trim());
            
            table.querySelectorAll('tbody tr').forEach(row => {
                row.querySelectorAll('td').forEach((cell, index) => {
                    if (headerTexts[index]) {
                        cell.setAttribute('data-label', headerTexts[index]);
                    }
                });
            });
        });
    }
}

/* ========== دعم اللمس ========== */
function initTouchSupport() {
    // تحسين السحب للإغلاق
    let touchStartX = 0;
    let touchEndX = 0;
    const sidebar = document.querySelector('.admin-sidebar');
    
    if (sidebar && 'ontouchstart' in window) {
        document.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        document.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });
        
        function handleSwipe() {
            const swipeThreshold = 100;
            const diff = touchEndX - touchStartX;
            
            // سحب لليسار لفتح القائمة (RTL)
            if (diff < -swipeThreshold && touchStartX > window.innerWidth - 50) {
                sidebar.classList.add('active');
                document.querySelector('.sidebar-overlay')?.classList.add('active');
            }
            
            // سحب لليمين لإغلاق القائمة (RTL)
            if (diff > swipeThreshold && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                document.querySelector('.sidebar-overlay')?.classList.remove('active');
            }
        }
    }
}

/* ========== تأثير Ripple ========== */
function initRippleEffect() {
    document.querySelectorAll('.btn, .nav-item, .stat-card, .action-btn').forEach(element => {
        element.classList.add('ripple');
        
        element.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const ripple = document.createElement('span');
            ripple.className = 'ripple-effect';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
}

/* ========== التلميحات ========== */
function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const text = this.getAttribute('data-tooltip');
            
            const tooltip = document.createElement('div');
            tooltip.className = 'admin-tooltip';
            tooltip.textContent = text;
            tooltip.style.cssText = `
                position: fixed;
                background: #1a1a2e;
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 12px;
                z-index: 10000;
                pointer-events: none;
                opacity: 0;
                transform: translateY(5px);
                transition: all 0.2s ease;
                border: 1px solid #2a2a45;
                box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
            tooltip.style.left = (rect.left + rect.width/2 - tooltip.offsetWidth/2) + 'px';
            
            setTimeout(() => {
                tooltip.style.opacity = '1';
                tooltip.style.transform = 'translateY(0)';
            }, 10);
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.style.opacity = '0';
                setTimeout(() => this._tooltip?.remove(), 200);
            }
        });
    });
}

/* ========== نوافذ التأكيد ========== */
function initConfirmDialogs() {
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            
            const message = this.getAttribute('data-confirm') || 'هل أنت متأكد؟';
            const href = this.getAttribute('href');
            
            showConfirmDialog(message, () => {
                window.location.href = href;
            });
        });
    });
}

function showConfirmDialog(message, onConfirm) {
    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    const dialog = document.createElement('div');
    dialog.style.cssText = `
        background: #1a1a2e;
        border: 1px solid #2a2a45;
        border-radius: 16px;
        padding: 30px;
        max-width: 400px;
        text-align: center;
        transform: scale(0.9);
        transition: transform 0.3s ease;
    `;
    
    dialog.innerHTML = `
        <div style="width:60px;height:60px;background:rgba(239,68,68,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <i class="fas fa-exclamation-triangle" style="font-size:28px;color:#ef4444;"></i>
        </div>
        <h3 style="color:white;margin-bottom:10px;font-size:18px;">تأكيد العملية</h3>
        <p style="color:#a0a0b0;margin-bottom:25px;font-size:14px;">${message}</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button class="confirm-yes" style="padding:12px 30px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;transition:all 0.3s;">
                نعم، تأكيد
            </button>
            <button class="confirm-no" style="padding:12px 30px;background:#2a2a45;color:#a0a0b0;border:1px solid #3a3a55;border-radius:8px;cursor:pointer;font-weight:600;transition:all 0.3s;">
                إلغاء
            </button>
        </div>
    `;
    
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
    
    // تفعيل الأنيميشن
    setTimeout(() => {
        overlay.style.opacity = '1';
        dialog.style.transform = 'scale(1)';
    }, 10);
    
    // الأحداث
    dialog.querySelector('.confirm-yes').addEventListener('click', () => {
        closeDialog();
        onConfirm();
    });
    
    dialog.querySelector('.confirm-no').addEventListener('click', closeDialog);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeDialog();
    });
    
    function closeDialog() {
        overlay.style.opacity = '0';
        dialog.style.transform = 'scale(0.9)';
        setTimeout(() => overlay.remove(), 300);
    }
}

/* ========== الأنيميشن عند الظهور ========== */
function initAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, { threshold: 0.1 });
    
    document.querySelectorAll('.stat-card, .card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'all 0.5s ease';
        observer.observe(el);
    });
    
    // إضافة الكلاس للأنيميشن
    document.head.insertAdjacentHTML('beforeend', `
        <style>
            .animate-in {
                opacity: 1 !important;
                transform: translateY(0) !important;
            }
        </style>
    `);
}

/* ========== عداد الأرقام ========== */
function initCounters() {
    document.querySelectorAll('.stat-value[data-count]').forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'));
        const duration = 1500;
        const step = target / (duration / 16);
        let current = 0;
        
        const updateCounter = () => {
            current += step;
            if (current < target) {
                counter.textContent = Math.floor(current).toLocaleString('ar-EG');
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target.toLocaleString('ar-EG');
            }
        };
        
        // بدء العد عند الظهور
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                updateCounter();
                observer.disconnect();
            }
        });
        observer.observe(counter);
    });
}

/* ========== Toast Notifications ========== */
function showToast(type, title, message) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icon = type === 'success' ? 'fa-check' : 'fa-times';
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas ${icon}"></i>
        </div>
        <div class="toast-content">
            <h4>${title}</h4>
            <p>${message}</p>
        </div>
    `;
    
    container.appendChild(toast);
    
    // إزالة بعد 4 ثواني
    setTimeout(() => {
        toast.classList.add('toast-out');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

/* ========== Loading State ========== */
function setLoading(element, loading) {
    if (loading) {
        element.classList.add('loading');
        element.disabled = true;
        element._originalText = element.innerHTML;
        element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحميل...';
    } else {
        element.classList.remove('loading');
        element.disabled = false;
        if (element._originalText) {
            element.innerHTML = element._originalText;
        }
    }
}

/* ========== تصدير الدوال للاستخدام الخارجي ========== */
window.AdminPanel = {
    showToast,
    showConfirmDialog,
    setLoading
};
