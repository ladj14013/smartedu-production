/**
 * Smart Education - Landing Page JavaScript
 * السكريبتات الخاصة بالصفحة الرئيسية
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ========== Mobile Menu Toggle ==========
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    const navbar = document.querySelector('.navbar');
    
    if (mobileMenuToggle && navLinks) {
        // إضافة مستمع الحدث للزر
        mobileMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileMenuToggle.classList.toggle('active');
            navLinks.classList.toggle('active');
        });

        // إغلاق القائمة عند النقر خارجها
        document.addEventListener('click', function(e) {
            if (!navbar.contains(e.target)) {
                mobileMenuToggle.classList.remove('active');
                navLinks.classList.remove('active');
            }
        });

        // إغلاق القائمة عند النقر على أي رابط
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                mobileMenuToggle.classList.remove('active');
                navLinks.classList.remove('active');
            });
        });
    }
    
    // ========== Smooth Scrolling for Navigation Links ==========
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            
            if (target) {
                const headerOffset = 80;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
                
                // Close mobile menu if open
                if (navLinks.classList.contains('active')) {
                    navLinks.classList.remove('active');
                    mobileMenuToggle.classList.remove('active');
                }
            }
        });
    });
    
    // ========== Header Scroll Effect ==========
    const header = document.querySelector('.main-header');
    let lastScroll = 0;
    
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 100) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
        
        lastScroll = currentScroll;
    });
    
    // ========== Floating Cards Animation on Scroll ==========
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);
    
    // Observe all sections
    document.querySelectorAll('section').forEach(section => {
        observer.observe(section);
    });
    
    // Observe feature cards for staggered animation
    document.querySelectorAll('.feature-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        observer.observe(card);
    });
    
    // ========== Counter Animation for Stats ==========
    const stats = document.querySelectorAll('.stat-number');
    let hasAnimated = false;
    
    const animateCounters = () => {
        if (hasAnimated) return;
        
        stats.forEach(stat => {
            const target = parseInt(stat.textContent);
            const duration = 2000;
            const increment = target / (duration / 16);
            let current = 0;
            
            const updateCounter = () => {
                current += increment;
                if (current < target) {
                    stat.textContent = Math.floor(current) + '+';
                    requestAnimationFrame(updateCounter);
                } else {
                    stat.textContent = target + '+';
                }
            };
            
            updateCounter();
        });
        
        hasAnimated = true;
    };
    
    // Start counter animation when stats are visible
    const statsObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounters();
            }
        });
    }, { threshold: 0.5 });
    
    const heroStats = document.querySelector('.hero-stats');
    if (heroStats) {
        statsObserver.observe(heroStats);
    }
    
    // ========== Tab Functionality for Roles Section ==========
    const roleTabs = document.querySelectorAll('.role-tab');
    const roleContents = document.querySelectorAll('.role-content');
    
    roleTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetRole = this.dataset.role;
            
            // Remove active class from all tabs and contents
            roleTabs.forEach(t => t.classList.remove('active'));
            roleContents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            const targetContent = document.querySelector(`[data-role-content="${targetRole}"]`);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
    
    // ========== Accordion for FAQ (if exists) ==========
    const accordionHeaders = document.querySelectorAll('.accordion-header');
    
    accordionHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const accordionItem = this.parentElement;
            const isActive = accordionItem.classList.contains('active');
            
            // Close all accordion items
            document.querySelectorAll('.accordion-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Open clicked item if it wasn't active
            if (!isActive) {
                accordionItem.classList.add('active');
            }
        });
    });
    
    // ========== Contact Form Modal ==========
    const contactButton = document.querySelector('[data-contact-modal]');
    const contactModal = document.getElementById('contactModal');
    const closeModal = document.querySelector('.close-modal');
    
    if (contactButton && contactModal) {
        contactButton.addEventListener('click', function(e) {
            e.preventDefault();
            contactModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
        
        if (closeModal) {
            closeModal.addEventListener('click', function() {
                contactModal.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
        
        // Close modal when clicking outside
        contactModal.addEventListener('click', function(e) {
            if (e.target === contactModal) {
                contactModal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && contactModal.classList.contains('active')) {
                contactModal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
    
    // ========== Form Validation ==========
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('input[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('error');
                } else {
                    input.classList.remove('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('الرجاء ملء جميع الحقول المطلوبة');
            }
        });
    });
    
    // ========== Parallax Effect for Hero Background ==========
    window.addEventListener('scroll', function() {
        const scrolled = window.pageYOffset;
        const heroBackground = document.querySelector('.hero-background');
        
        if (heroBackground) {
            heroBackground.style.transform = `translateY(${scrolled * 0.5}px)`;
        }
    });
    
    // ========== Loading Animation ==========
    window.addEventListener('load', function() {
        document.body.classList.add('loaded');
    });
    
});

// ========== Utility Functions ==========

/**
 * Format number with commas
 */
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Check if element is in viewport
 */
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}
