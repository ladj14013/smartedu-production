# دليل التثبيت السريع - SmartEdu Hub

## 🎯 الخطوات السريعة للبدء

### 1️⃣ تثبيت XAMPP (إذا لم يكن مثبتاً)

1. قم بتحميل XAMPP من: https://www.apachefriends.org/
2. قم بتثبيته في المسار الافتراضي: `C:\xampp`
3. شغّل XAMPP Control Panel

### 2️⃣ نسخ المشروع

```bash
# انسخ مجلد HTML_PHP_Version إلى
C:\xampp\htdocs\smartedu
```

أو يمكنك إنشاء symbolic link:
```bash
mklink /D "C:\xampp\htdocs\smartedu" "C:\Users\pc\Desktop\SmartEdu-Hub\HTML_PHP_Version"
```

### 3️⃣ إنشاء قاعدة البيانات

**الطريقة 1: عبر phpMyAdmin (الأسهل)**

1. افتح المتصفح على: `http://localhost/phpmyadmin`
2. انقر على "جديد" (New) في الشريط الجانبي
3. اسم قاعدة البيانات: `smartedu_hub`
4. الترميز: `utf8mb4_unicode_ci`
5. انقر "إنشاء" (Create)
6. انقر على "استيراد" (Import)
7. اختر ملف: `C:\xampp\htdocs\smartedu\database_schema.sql`
8. انقر "تنفيذ" (Go)

**الطريقة 2: عبر سطر الأوامر**

```bash
# افتح Command Prompt
cd C:\xampp\mysql\bin
mysql -u root -p

# في shell الخاص بـ MySQL:
source C:/xampp/htdocs/smartedu/database_schema.sql
exit;
```

### 4️⃣ تشغيل الخادم

1. افتح XAMPP Control Panel
2. اضغط "Start" بجانب Apache
3. اضغط "Start" بجانب MySQL
4. افتح المتصفح على: `http://localhost/smartedu/public/`

### 5️⃣ تسجيل الدخول

**حساب المدير الافتراضي:**
- البريد الإلكتروني: `admin@smartedu.com`
- كلمة المرور: `admin123`

---

## ✅ التحقق من النجاح

إذا رأيت الصفحة الرئيسية لـ Smart Education، تهانينا! 🎉

### المشاكل الشائعة؟

**المشكلة**: لا يمكن الوصول للصفحة
**الحل**: 
- تأكد من تشغيل Apache في XAMPP
- تحقق من المسار: `http://localhost/smartedu/public/`

**المشكلة**: خطأ في قاعدة البيانات
**الحل**:
- تأكد من تشغيل MySQL في XAMPP
- تحقق من استيراد `database_schema.sql`

**المشكلة**: صفحات التنسيق لا تعمل
**الحل**:
- تحقق من مسارات CSS في الملفات
- تأكد من وجود ملفات CSS في `assets/css/`

---

## 📝 الخطوات التالية

1. ✅ سجل حساب جديد كطالب أو معلم
2. ⏳ استكشف لوحة التحكم (قيد التطوير)
3. ⏳ أنشئ درساً أو تمريناً (قيد التطوير)
4. ⏳ جرب التقييم بالذكاء الاصطناعي (قيد التطوير)

---

## 🔧 التطوير

للمساهمة في تطوير المشروع:

1. راجع ملف `README.md` للتفاصيل الكاملة
2. تحقق من قائمة المهام في TODO list
3. اتبع معايير البرمجة PHP

---

## 📞 المساعدة

إذا واجهت أي مشكلة:
1. راجع `README.md`
2. تحقق من `database_schema.sql`
3. تأكد من إعدادات `config/database.php`

حظاً موفقاً! 🚀
