// تحسينات التجاوب مع الهواتف
document.addEventListener('DOMContentLoaded', function() {
    // إضافة زر القائمة للهواتف
    const sidebar = document.querySelector('.sidebar');
    if (sidebar && !document.querySelector('.mobile-toggle')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'mobile-toggle';
        toggleBtn.innerHTML = '☰';
        document.body.appendChild(toggleBtn);
        
        // تفعيل زر القائمة
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            this.classList.toggle('active');
        });
        
        // إغلاق القائمة عند النقر خارجها
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
                toggleBtn.classList.remove('active');
            }
        });
    }
    
    // تحسين التمرير للجداول
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        if (!table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentElement.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });
    
    // تحسين النماذج
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const submitBtn = form.querySelector('input[type="submit"], button[type="submit"]');
        if (submitBtn) {
            submitBtn.classList.add('btn', 'btn-submit');
        }
    });
    
    // تحسين الصور
    const images = document.querySelectorAll('img:not(.profile-image)');
    images.forEach(img => {
        img.setAttribute('loading', 'lazy');
        if (!img.hasAttribute('alt')) {
            img.setAttribute('alt', 'صورة');
        }
    });
    
    // إضافة خاصية اللمس للقوائم المنسدلة
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('touchstart', function(e) {
            e.preventDefault();
            this.classList.toggle('active');
        });
    });
});

// تحسين أداء التمرير
let scrollTimeout;
function optimizeScroll(callback) {
    return function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(callback, 150);
    }
}

// تطبيق التحسينات عند التمرير
window.addEventListener('scroll', optimizeScroll(function() {
    const header = document.querySelector('.main-header');
    if (header) {
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    }
}));