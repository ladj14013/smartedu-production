<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/css/responsive.css">
<header>
    <h1>مشروع سمارت إديو</h1>
    <nav>
        <ul class="nav-links">
            <li><a href="../dashboard/student/index.php">التلميذ</a></li>
            <li><a href="../dashboard/teacher/index.php">الأستاذ</a></li>
            <!-- روابط إضافية حسب الحاجة -->
        </ul>
        <button class="mobile-menu-btn" aria-label="القائمة">☰</button>
    </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            this.classList.toggle('active');
        });
    }
});
</script>
