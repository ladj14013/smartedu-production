<?php
// التحقق من حالة تسجيل الدخول
session_start();
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Smart Education - منصة تعليمية ذكية توفر بيئة تعليمية متكاملة للطلاب والمعلمين وأولياء الأمور">
    <meta name="keywords" content="تعليم, منصة تعليمية, ذكاء اصطناعي, دروس, تمارين">
    <title>Smart Education - منصة تعليمية ذكية</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/landing.css">
    <link rel="stylesheet" href="assets/css/mobile-nav.css">
</head>
<body>
    <!-- Header / Navigation -->
    <header class="main-header">
        <nav class="navbar">
            <div class="container">
                <div class="nav-wrapper">
                    <!-- Logo -->
                    <a href="" class="logo">
                        <span class="logo-icon">🎓</span>
                        <span class="logo-text">Smart Education</span>
                    </a>
                    
                    <!-- Navigation Links -->
                    <ul class="nav-links">
                        <li><a href="#features">الميزات</a></li>
                        <li><a href="#roles">الأدوار</a></li>
                        <li style="display: none;"><a href="#pricing">الأسعار</a></li>
                        <li><a href="#contact">تواصل معنا</a></li>
                        <!-- Auth Buttons for Mobile -->
                        <li class="mobile-auth-buttons">
                            <?php if ($is_logged_in): ?>
                                <a href="dashboard/index.php" class="btn btn-secondary">لوحة التحكم</a>
                                <a href="public/logout.php" class="btn btn-outline">تسجيل الخروج</a>
                            <?php else: ?>
                                <a href="public/login.php" class="btn btn-outline">تسجيل الدخول</a>
                                <a href="public/signup.php" class="btn btn-primary">إنشاء حساب</a>
                            <?php endif; ?>
                        </li>
                    </ul>
                    
                    <!-- Auth Buttons for Desktop -->
                    <div class="auth-buttons desktop-auth-buttons">
                        <?php if ($is_logged_in): ?>
                            <a href="dashboard/index.php" class="btn btn-secondary">لوحة التحكم</a>
                            <a href="public/logout.php" class="btn btn-outline">تسجيل الخروج</a>
                        <?php else: ?>
                            <a href="public/login.php" class="btn btn-outline">تسجيل الدخول</a>
                            <a href="public/signup.php" class="btn btn-primary">إنشاء حساب</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Mobile Menu Toggle -->
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-background">
            <div class="hero-overlay"></div>
        </div>
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1 class="hero-title">
                        مستقبل التعليم 
                        <span class="gradient-text">الذكي</span>
                    </h1>
                    <p class="hero-description">
                        منصة تعليمية متكاملة تجمع الطلاب والمعلمين وأولياء الأمور في بيئة واحدة
                        <br>
                        مع تقييم ذكي بالذكاء الاصطناعي ومتابعة دقيقة للتقدم الدراسي
                    </p>
                    
                    <?php if ($is_logged_in): ?>
                        <!-- Logged In User Actions -->
                        <div class="hero-signup-form">
                            <div style="text-align: center;">
                                <a href="dashboard/index.php" class="btn btn-hero" style="display: inline-flex; align-items: center; gap: 10px; padding: 15px 40px; font-size: 1.1rem;">
                                    انتقل إلى لوحة التحكم
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                            </div>
                            <p class="hero-note" style="margin-top: 15px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                مرحباً بعودتك! يمكنك الآن الوصول إلى لوحة التحكم الخاصة بك
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- Quick Signup Form -->
                        <div class="hero-signup-form">
                            <form action="public/signup.php" method="GET" class="quick-signup">
                                <div class="form-group-inline">
                                    <input 
                                        type="email" 
                                        name="email" 
                                        placeholder="أدخل بريدك الإلكتروني" 
                                        class="form-input-hero"
                                        required
                                    >
                                    <button type="submit" class="btn btn-hero">
                                        ابدأ الآن مجانًا
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                            <polyline points="12 5 19 12 12 19"></polyline>
                                        </svg>
                                    </button>
                                </div>
                            </form>
                            <p class="hero-note">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                لا حاجة لبطاقة ائتمان • البدء مجاني
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Stats -->
                    <div class="hero-stats">
                        <div class="stat-item">
                            <span class="stat-number">1000+</span>
                            <span class="stat-label">طالب نشط</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">150+</span>
                            <span class="stat-label">معلم متميز</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">500+</span>
                            <span class="stat-label">درس تفاعلي</span>
                        </div>
                    </div>
                </div>
                
                <!-- Hero Image -->
                <div class="hero-image">
                    <div class="hero-image-wrapper">
                        <!-- Placeholder for image -->
                        <div class="hero-img-placeholder">
                            <svg viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="250" cy="250" r="200" fill="#4285F4" opacity="0.1"/>
                                <circle cx="250" cy="250" r="150" fill="#4285F4" opacity="0.2"/>
                                <circle cx="250" cy="250" r="100" fill="#4285F4" opacity="0.3"/>
                                <text x="250" y="250" text-anchor="middle" dominant-baseline="middle" font-size="60" fill="#4285F4">🎓</text>
                            </svg>
                        </div>
                        
                        <!-- Floating Cards -->
                        <div class="floating-card card-1">
                            <div class="floating-icon">📚</div>
                            <div class="floating-text">
                                <strong>دروس تفاعلية</strong>
                                <span>محتوى غني ومتنوع</span>
                            </div>
                        </div>
                        
                        <div class="floating-card card-2">
                            <div class="floating-icon">🤖</div>
                            <div class="floating-text">
                                <strong>ذكاء اصطناعي</strong>
                                <span>تقييم فوري ودقيق</span>
                            </div>
                        </div>
                        
                        <div class="floating-card card-3">
                            <div class="floating-icon">📊</div>
                            <div class="floating-text">
                                <strong>تقارير شاملة</strong>
                                <span>متابعة التقدم</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scroll Indicator -->
        <div class="scroll-indicator">
            <div class="scroll-text">استكشف المزيد</div>
            <div class="scroll-arrow">↓</div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">مميزات المنصة</h2>
                <p class="section-subtitle">
                    اكتشف كيف تساعدك منصة SmartEdu Hub على تحقيق أهدافك التعليمية
                </p>
            </div>
            
            <div class="features-grid">
                <!-- Feature 1: تعليم تفاعلي -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="32" cy="32" r="30" fill="#4285F4" opacity="0.1"/>
                            <path d="M32 16L40 24L32 32L24 24L32 16Z" fill="#4285F4"/>
                            <path d="M24 28L32 36L40 28" stroke="#4285F4" stroke-width="2"/>
                            <path d="M24 36L32 44L40 36" stroke="#4285F4" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">تعليم تفاعلي</h3>
                    <p class="feature-description">
                        دروس تفاعلية بالفيديو والصور مع تمارين عملية تضمن الفهم العميق للمواد الدراسية
                    </p>
                    <ul class="feature-list">
                        <li>فيديوهات تعليمية عالية الجودة</li>
                        <li>تمارين تفاعلية متنوعة</li>
                        <li>محتوى منظم ومتسلسل</li>
                    </ul>
                </div>
                
                <!-- Feature 2: متابعة الأداء -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="32" cy="32" r="30" fill="#22c55e" opacity="0.1"/>
                            <rect x="16" y="40" width="8" height="16" rx="2" fill="#22c55e"/>
                            <rect x="28" y="32" width="8" height="24" rx="2" fill="#22c55e"/>
                            <rect x="40" y="24" width="8" height="32" rx="2" fill="#22c55e"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">متابعة الأداء</h3>
                    <p class="feature-description">
                        نظام متقدم لتتبع التقدم الدراسي مع تقارير مفصلة وإحصائيات دقيقة
                    </p>
                    <ul class="feature-list">
                        <li>تقارير مفصلة للأداء</li>
                        <li>إحصائيات تفاعلية</li>
                        <li>تنبيهات ذكية للتحسين</li>
                    </ul>
                </div>
                
                <!-- Feature 3: مكتبة شاملة -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="32" cy="32" r="30" fill="#FFA726" opacity="0.1"/>
                            <rect x="20" y="16" width="24" height="32" rx="2" stroke="#FFA726" stroke-width="2" fill="none"/>
                            <line x1="26" y1="24" x2="38" y2="24" stroke="#FFA726" stroke-width="2"/>
                            <line x1="26" y1="30" x2="38" y2="30" stroke="#FFA726" stroke-width="2"/>
                            <line x1="26" y1="36" x2="34" y2="36" stroke="#FFA726" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">مكتبة شاملة</h3>
                    <p class="feature-description">
                        مكتبة ضخمة من الدروس والتمارين تغطي جميع المراحل والمستويات الدراسية
                    </p>
                    <ul class="feature-list">
                        <li>آلاف الدروس المتنوعة</li>
                        <li>تمارين لجميع المستويات</li>
                        <li>تحديثات دورية للمحتوى</li>
                    </ul>
                </div>
                
                <!-- Feature 4: تقييم ذكي -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="32" cy="32" r="30" fill="#8b5cf6" opacity="0.1"/>
                            <circle cx="32" cy="28" r="8" stroke="#8b5cf6" stroke-width="2"/>
                            <path d="M32 36C24 36 18 40 18 44V48H46V44C46 40 40 36 32 36Z" fill="#8b5cf6"/>
                            <path d="M42 20L44 22L48 18" stroke="#8b5cf6" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">تقييم ذكي بالـ AI</h3>
                    <p class="feature-description">
                        تقييم فوري ودقيق للإجابات باستخدام تقنيات الذكاء الاصطناعي المتقدمة
                    </p>
                    <ul class="feature-list">
                        <li>تصحيح فوري للتمارين</li>
                        <li>تحليل نقاط القوة والضعف</li>
                        <li>اقتراحات مخصصة للتحسين</li>
                    </ul>
                </div>
                
                <!-- Feature 5: تواصل فعال -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="32" cy="32" r="30" fill="#ec4899" opacity="0.1"/>
                            <rect x="16" y="20" width="32" height="24" rx="4" stroke="#ec4899" stroke-width="2" fill="none"/>
                            <path d="M16 24L32 34L48 24" stroke="#ec4899" stroke-width="2"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">تواصل فعال</h3>
                    <p class="feature-description">
                        نظام رسائل متطور يربط الطلاب بالمعلمين وأولياء الأمور لمتابعة مستمرة
                    </p>
                    <ul class="feature-list">
                        <li>رسائل مباشرة مع المعلمين</li>
                        <li>تنبيهات فورية لأولياء الأمور</li>
                        <li>نظام إشعارات ذكي</li>
                    </ul>
                </div>
                
                <!-- Feature 6: إحصائيات متقدمة -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="32" cy="32" r="30" fill="#0ea5e9" opacity="0.1"/>
                            <circle cx="32" cy="32" r="18" stroke="#0ea5e9" stroke-width="2" fill="none"/>
                            <path d="M32 14V32H50" stroke="#0ea5e9" stroke-width="2"/>
                            <circle cx="32" cy="32" r="3" fill="#0ea5e9"/>
                        </svg>
                    </div>
                    <h3 class="feature-title">إحصائيات متقدمة</h3>
                    <p class="feature-description">
                        لوحات تحكم تفاعلية مع رسوم بيانية توضح التقدم والإنجازات بشكل مرئي
                    </p>
                    <ul class="feature-list">
                        <li>رسوم بيانية تفاعلية</li>
                        <li>تقارير شاملة ودورية</li>
                        <li>مقارنة الأداء بمرور الوقت</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Roles Section -->
    <section id="roles" class="roles-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">منصة شاملة لجميع الأدوار</h2>
                <p class="section-subtitle">
                    تجربة مخصصة لكل مستخدم حسب دوره في العملية التعليمية
                </p>
            </div>
            
            <!-- Tabs -->
            <div class="roles-tabs">
                <button class="role-tab active" data-role="student">
                    <span class="tab-icon">🎓</span>
                    <span class="tab-label">الطالب</span>
                </button>
                <button class="role-tab" data-role="teacher">
                    <span class="tab-icon">👨‍🏫</span>
                    <span class="tab-label">المعلم</span>
                </button>
                <button class="role-tab" data-role="parent">
                    <span class="tab-icon">👨‍👩‍👧</span>
                    <span class="tab-label">ولي الأمر</span>
                </button>
                <button class="role-tab" data-role="supervisor">
                    <span class="tab-icon">👔</span>
                    <span class="tab-label">المشرف</span>
                </button>
                <button class="role-tab" data-role="director" style="display: none;">
                    <span class="tab-icon">💼</span>
                    <span class="tab-label">المدير</span>
                </button>
            </div>
            
            <!-- Tab Contents -->
            <div class="roles-content">
                <!-- Student Role -->
                <div class="role-content active" data-role-content="student">
                    <div class="role-layout">
                        <div class="role-info">
                            <h3 class="role-title">لوحة تحكم الطالب</h3>
                            <p class="role-description">
                                تجربة تعليمية متكاملة مع دروس تفاعلية وتمارين شاملة ومتابعة دقيقة للتقدم
                            </p>
                            <ul class="role-features">
                                <li>
                                    <span class="feature-icon">📚</span>
                                    <div class="feature-text">
                                        <strong>مكتبة شاملة</strong>
                                        <p>الوصول لجميع الدروس والمواد الدراسية</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">✍️</span>
                                    <div class="feature-text">
                                        <strong>تمارين تفاعلية</strong>
                                        <p>اختبر معلوماتك مع تقييم فوري بالذكاء الاصطناعي</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">📊</span>
                                    <div class="feature-text">
                                        <strong>متابعة التقدم</strong>
                                        <p>تقارير مفصلة وإحصائيات دقيقة لأدائك</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">🏆</span>
                                    <div class="feature-text">
                                        <strong>نظام النقاط</strong>
                                        <p>اكسب نقاطاً وشارات مع كل إنجاز</p>
                                    </div>
                                </li>
                            </ul>
                            <a href="public/signup.php?role=student" class="btn-primary">ابدأ التعلم الآن</a>
                        </div>
                        <div class="role-preview">
                            <div class="dashboard-mockup">
                                <div class="mockup-header">
                                    <span>🎓</span> لوحة الطالب
                                </div>
                                <div class="mockup-stats">
                                    <div class="mini-stat">
                                        <div class="mini-number">12</div>
                                        <div class="mini-label">دروس مكتملة</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-number">85%</div>
                                        <div class="mini-label">معدل النجاح</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-number">420</div>
                                        <div class="mini-label">نقاط</div>
                                    </div>
                                </div>
                                <div class="mockup-progress">
                                    <div class="progress-item">
                                        <span>الرياضيات</span>
                                        <div class="progress-bar"><div class="progress-fill" style="width: 75%"></div></div>
                                    </div>
                                    <div class="progress-item">
                                        <span>العلوم</span>
                                        <div class="progress-bar"><div class="progress-fill" style="width: 60%"></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Teacher Role -->
                <div class="role-content" data-role-content="teacher">
                    <div class="role-layout">
                        <div class="role-info">
                            <h3 class="role-title">لوحة تحكم المعلم</h3>
                            <p class="role-description">
                                أدوات قوية لإدارة الدروس ومتابعة الطلاب وتقييم الأداء بشكل فعال
                            </p>
                            <ul class="role-features">
                                <li>
                                    <span class="feature-icon">📝</span>
                                    <div class="feature-text">
                                        <strong>إنشاء الدروس</strong>
                                        <p>أضف دروساً تفاعلية بالفيديو والصور</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">👥</span>
                                    <div class="feature-text">
                                        <strong>إدارة الطلاب</strong>
                                        <p>تابع أداء طلابك وقدم الدعم اللازم</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">📊</span>
                                    <div class="feature-text">
                                        <strong>تقارير شاملة</strong>
                                        <p>احصائيات مفصلة عن أداء كل طالب</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">💬</span>
                                    <div class="feature-text">
                                        <strong>تواصل مباشر</strong>
                                        <p>راسل الطلاب وأولياء الأمور بسهولة</p>
                                    </div>
                                </li>
                            </ul>
                            <a href="public/signup.php?role=teacher" class="btn-primary">انضم كمعلم</a>
                        </div>
                        <div class="role-preview">
                            <div class="dashboard-mockup teacher">
                                <div class="mockup-header">
                                    <span>👨‍🏫</span> لوحة المعلم
                                </div>
                                <div class="mockup-stats">
                                    <div class="mini-stat">
                                        <div class="mini-number">45</div>
                                        <div class="mini-label">طالب</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-number">18</div>
                                        <div class="mini-label">درس</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-number">92%</div>
                                        <div class="mini-label">معدل الرضا</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Parent Role -->
                <div class="role-content" data-role-content="parent">
                    <div class="role-layout">
                        <div class="role-info">
                            <h3 class="role-title">لوحة تحكم ولي الأمر</h3>
                            <p class="role-description">
                                تابع تقدم أبنائك الدراسي واطلع على أدائهم وتواصل مع المعلمين
                            </p>
                            <ul class="role-features">
                                <li>
                                    <span class="feature-icon">👨‍👩‍👧‍👦</span>
                                    <div class="feature-text">
                                        <strong>متابعة الأبناء</strong>
                                        <p>راقب تقدم جميع أبنائك من مكان واحد</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">📈</span>
                                    <div class="feature-text">
                                        <strong>تقارير دورية</strong>
                                        <p>احصل على تقارير مفصلة عن الأداء</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">🔔</span>
                                    <div class="feature-text">
                                        <strong>إشعارات فورية</strong>
                                        <p>تنبيهات عن الواجبات والاختبارات</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">💬</span>
                                    <div class="feature-text">
                                        <strong>تواصل سهل</strong>
                                        <p>راسل المعلمين والإدارة مباشرة</p>
                                    </div>
                                </li>
                            </ul>
                            <a href="public/signup.php?role=parent" class="btn-primary">سجل كولي أمر</a>
                        </div>
                        <div class="role-preview">
                            <div class="dashboard-mockup parent">
                                <div class="mockup-header">
                                    <span>👨‍👩‍👧</span> لوحة ولي الأمر
                                </div>
                                <div class="mockup-stats">
                                    <div class="mini-stat">
                                        <div class="mini-number">3</div>
                                        <div class="mini-label">أبناء</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-number">88%</div>
                                        <div class="mini-label">متوسط الأداء</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Supervisor Role -->
                <div class="role-content" data-role-content="supervisor">
                    <div class="role-layout">
                        <div class="role-info">
                            <h3 class="role-title">لوحة تحكم المشرف</h3>
                            <p class="role-description">
                                أدوات متقدمة لمراقبة المحتوى التعليمي ومتابعة أداء المعلمين
                            </p>
                            <ul class="role-features">
                                <li>
                                    <span class="feature-icon">🔍</span>
                                    <div class="feature-text">
                                        <strong>مراجعة المحتوى</strong>
                                        <p>راجع وافحص الدروس قبل النشر</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">👨‍🏫</span>
                                    <div class="feature-text">
                                        <strong>متابعة المعلمين</strong>
                                        <p>راقب أداء المعلمين وجودة المحتوى</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">📊</span>
                                    <div class="feature-text">
                                        <strong>تقارير متقدمة</strong>
                                        <p>احصائيات شاملة عن جميع الأنشطة</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">✅</span>
                                    <div class="feature-text">
                                        <strong>الموافقة والرفض</strong>
                                        <p>اعتماد أو رفض الدروس والتمارين</p>
                                    </div>
                                </li>
                            </ul>
                            <a href="public/signup.php?role=supervisor" class="btn-primary">انضم كمشرف</a>
                        </div>
                        <div class="role-preview">
                            <div class="dashboard-mockup supervisor">
                                <div class="mockup-header">
                                    <span>👔</span> لوحة المشرف
                                </div>
                                <div class="mockup-stats">
                                    <div class="mini-stat">
                                        <div class="mini-number">15</div>
                                        <div class="mini-label">معلم</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-number">8</div>
                                        <div class="mini-label">قيد المراجعة</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Director Role -->
                <div class="role-content" data-role-content="director" style="display: none;">
                    <div class="role-layout">
                        <div class="role-info">
                            <h3 class="role-title">لوحة تحكم المدير</h3>
                            <p class="role-description">
                                تحكم كامل في النظام مع إحصائيات شاملة وإدارة متقدمة
                            </p>
                            <ul class="role-features">
                                <li>
                                    <span class="feature-icon">👥</span>
                                    <div class="feature-text">
                                        <strong>إدارة المستخدمين</strong>
                                        <p>أضف وعدل وحذف جميع المستخدمين</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">🎯</span>
                                    <div class="feature-text">
                                        <strong>إدارة المحتوى</strong>
                                        <p>تحكم كامل في المراحل والمواد والدروس</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">📊</span>
                                    <div class="feature-text">
                                        <strong>لوحة تحليلات</strong>
                                        <p>احصائيات متقدمة عن كامل المنصة</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="feature-icon">⚙️</span>
                                    <div class="feature-text">
                                        <strong>الإعدادات العامة</strong>
                                        <p>تحكم في جميع إعدادات النظام</p>
                                    </div>
                                </li>
                            </ul>
                            <a href="public/signup.php?role=director" class="btn-primary">سجل كمدير</a>
                        </div>
                        <div class="role-preview">
                            <div class="dashboard-mockup director">
                                <div class="mockup-header">
                                    <span>💼</span> لوحة المدير
                                </div>
                                <div class="mockup-stats">
                                    <div class="mini-stat">
                                        <div class="mini-number">500+</div>
                                        <div class="mini-label">مستخدم</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-number">120</div>
                                        <div class="mini-label">درس</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing-section" style="display: none;">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">خطط مرنة تناسب احتياجاتك</h2>
                <p class="section-subtitle">
                    اختر الخطة المناسبة لك سواء كنت فرداً أو مؤسسة تعليمية
                </p>
            </div>
            
            <div class="pricing-grid">
                <!-- Free Plan -->
                <div class="pricing-card">
                    <div class="pricing-header">
                        <div class="plan-icon">🎓</div>
                        <h3 class="plan-name">الخطة المجانية</h3>
                        <p class="plan-description">مثالية للطلاب الأفراد</p>
                    </div>
                    <div class="pricing-body">
                        <div class="price">
                            <span class="currency">مجاناً</span>
                            <span class="period">للأبد</span>
                        </div>
                        <ul class="features-list">
                            <li class="included">
                                <span class="check-icon">✓</span>
                                الوصول للدروس العامة
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                تمارين أساسية
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                تقارير شهرية
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                دعم المجتمع
                            </li>
                            <li class="excluded">
                                <span class="cross-icon">✗</span>
                                الدروس الخاصة
                            </li>
                            <li class="excluded">
                                <span class="cross-icon">✗</span>
                                التقييم بالذكاء الاصطناعي
                            </li>
                            <li class="excluded">
                                <span class="cross-icon">✗</span>
                                دعم فني مخصص
                            </li>
                        </ul>
                    </div>
                    <div class="pricing-footer">
                        <a href="public/signup.php" class="btn-outline">ابدأ مجاناً</a>
                    </div>
                </div>
                
                <!-- School Plan -->
                <div class="pricing-card featured">
                    <div class="popular-badge">الأكثر شعبية</div>
                    <div class="pricing-header">
                        <div class="plan-icon">🏫</div>
                        <h3 class="plan-name">خطة المدرسة</h3>
                        <p class="plan-description">للمدارس والمعاهد الصغيرة</p>
                    </div>
                    <div class="pricing-body">
                        <div class="price">
                            <span class="amount">500</span>
                            <span class="currency">دولار</span>
                            <span class="period">/ سنوياً</span>
                        </div>
                        <ul class="features-list">
                            <li class="included">
                                <span class="check-icon">✓</span>
                                جميع ميزات الخطة المجانية
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                الدروس الخاصة المتقدمة
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                تقييم بالذكاء الاصطناعي
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                حتى 200 طالب
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                تقارير أسبوعية مفصلة
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                دعم فني ذو أولوية
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                لوحات تحكم متقدمة
                            </li>
                        </ul>
                    </div>
                    <div class="pricing-footer">
                        <a href="public/signup.php?plan=school" class="btn-primary">اشترك الآن</a>
                    </div>
                </div>
                
                <!-- Enterprise Plan -->
                <div class="pricing-card">
                    <div class="pricing-header">
                        <div class="plan-icon">🏢</div>
                        <h3 class="plan-name">خطة المؤسسات</h3>
                        <p class="plan-description">للجامعات والمؤسسات الكبرى</p>
                    </div>
                    <div class="pricing-body">
                        <div class="price">
                            <span class="currency">حسب الطلب</span>
                            <span class="period">خطة مخصصة</span>
                        </div>
                        <ul class="features-list">
                            <li class="included">
                                <span class="check-icon">✓</span>
                                جميع ميزات خطة المدرسة
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                عدد غير محدود من الطلاب
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                تخصيص كامل للمنصة
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                API متقدم للتكامل
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                دعم فني على مدار الساعة
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                تدريب مخصص للموظفين
                            </li>
                            <li class="included">
                                <span class="check-icon">✓</span>
                                مدير حساب مخصص
                            </li>
                        </ul>
                    </div>
                    <div class="pricing-footer">
                        <a href="#contact" class="btn-outline" data-contact-modal>اتصل بنا</a>
                    </div>
                </div>
            </div>
            
            <div class="pricing-note">
                <p>
                    💡 <strong>ملاحظة:</strong> جميع الخطط تشمل تحديثات مجانية ونسخ احتياطي يومي وحماية SSL
                </p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <div class="cta-text">
                    <h2 class="cta-title">جاهز لبدء رحلتك التعليمية؟</h2>
                    <p class="cta-description">
                        انضم إلى آلاف الطلاب والمعلمين الذين يستخدمون SmartEdu Hub لتحقيق أهدافهم التعليمية
                    </p>
                    <div class="cta-features">
                        <div class="cta-feature">
                            <span class="cta-icon">✓</span>
                            <span>تسجيل مجاني بدون بطاقة ائتمان</span>
                        </div>
                        <div class="cta-feature">
                            <span class="cta-icon">✓</span>
                            <span>ابدأ الاستخدام فوراً</span>
                        </div>
                        <div class="cta-feature">
                            <span class="cta-icon">✓</span>
                            <span>دعم فني متاح</span>
                        </div>
                    </div>
                </div>
                <div class="cta-actions">
                    <a href="public/signup.php" class="btn-cta-primary">
                        <span>ابدأ الآن مجاناً</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                    <a href="#contact" class="btn-cta-secondary" data-contact-modal>
                        تواصل معنا
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Background decorations -->
        <div class="cta-decoration cta-circle-1"></div>
        <div class="cta-decoration cta-circle-2"></div>
        <div class="cta-decoration cta-circle-3"></div>
    </section>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <!-- About Section -->
                <div class="footer-section">
                    <h3 class="footer-title">SmartEdu Hub</h3>
                    <p class="footer-description">
                        منصة تعليمية متكاملة توفر تجربة تعليمية متميزة للطلاب والمعلمين مع تقنيات الذكاء الاصطناعي
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link" aria-label="Facebook">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                        <a href="#" class="social-link" aria-label="Twitter">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>
                            </svg>
                        </a>
                        <a href="#" class="social-link" aria-label="Instagram">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" fill="none" stroke="white" stroke-width="2"/>
                                <circle cx="17.5" cy="6.5" r="1.5" fill="white"/>
                            </svg>
                        </a>
                        <a href="#" class="social-link" aria-label="LinkedIn">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                                <rect x="2" y="9" width="4" height="12"/>
                                <circle cx="4" cy="4" r="2"/>
                            </svg>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="footer-section">
                    <h4 class="footer-section-title">روابط سريعة</h4>
                    <ul class="footer-links">
                        <li><a href="#features">المميزات</a></li>
                        <li><a href="#roles">الأدوار</a></li>
                        <li><a href="public/login.php">تسجيل الدخول</a></li>
                        <li><a href="public/signup.php">إنشاء حساب</a></li>
                    </ul>
                </div>
                
                <!-- Support -->
                <div class="footer-section">
                    <h4 class="footer-section-title">الدعم</h4>
                    <ul class="footer-links">
                        <li><a href="#" data-contact-modal>اتصل بنا</a></li>
                        <li><a href="#">الأسئلة الشائعة</a></li>
                        <li><a href="#">دليل الاستخدام</a></li>
                        <li><a href="#">سياسة الخصوصية</a></li>
                        <li><a href="#">الشروط والأحكام</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div class="footer-section">
                    <h4 class="footer-section-title">تواصل معنا</h4>
                    <ul class="contact-info">
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <span>الجزائر - تيميمون - أجدير الغربي</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                            <span dir="ltr">+213 655363136</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <span>info@smartedu.sa</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 SmartEdu Hub. جميع الحقوق محفوظة.</p>
                <div style="margin-top: 15px; padding: 12px; background: linear-gradient(135deg, rgba(66, 133, 244, 0.1) 0%, rgba(102, 126, 234, 0.1) 100%); border-radius: 10px; font-size: 0.95rem; color: #4285F4;">
                    💻 تم بناء وتطوير الموقع بأجدير الغربي شروين* ❤️
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Contact Modal -->
    <div id="contactModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="modal-title">تواصل معنا</h2>
            <p class="modal-description">نسعد بتواصلك معنا. املأ النموذج وسنرد عليك في أقرب وقت</p>
            
            <form class="contact-form" id="contactForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="contactName">الاسم الكامل</label>
                        <input type="text" id="contactName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="contactEmail">البريد الإلكتروني</label>
                        <input type="email" id="contactEmail" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contactPhone">رقم الجوال</label>
                        <input type="tel" id="contactPhone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="contactSubject">الموضوع</label>
                        <select id="contactSubject" name="subject" required>
                            <option value="">اختر الموضوع</option>
                            <option value="general">استفسار عام</option>
                            <option value="support">دعم فني</option>
                            <option value="pricing">الأسعار والباقات</option>
                            <option value="partnership">شراكة</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="contactMessage">الرسالة</label>
                    <textarea id="contactMessage" name="message" rows="5" required></textarea>
                </div>
                
                <button type="submit" class="btn-submit">إرسال الرسالة</button>
            </form>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="assets/js/landing.js"></script>
</body>
</html>
