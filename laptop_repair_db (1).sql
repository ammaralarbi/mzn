-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 15 أغسطس 2025 الساعة 02:36
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `laptop_repair_db`
--

-- --------------------------------------------------------

--
-- بنية الجدول `broken_laptops`
--

CREATE TABLE `broken_laptops` (
  `laptop_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `device_category_number` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) NOT NULL,
  `specs` text DEFAULT NULL,
  `with_charger` tinyint(1) DEFAULT 0,
  `problems_count` int(11) DEFAULT 0,
  `branch_name` varchar(100) DEFAULT NULL,
  `problem_details` text DEFAULT NULL,
  `entered_by_user_id` int(11) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `problem_type` varchar(100) DEFAULT NULL,
  `transfer_ref` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `repeat_problem_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `broken_laptops`
--

INSERT INTO `broken_laptops` (`laptop_id`, `category_id`, `device_category_number`, `serial_number`, `specs`, `with_charger`, `problems_count`, `branch_name`, `problem_details`, `entered_by_user_id`, `assigned_user_id`, `problem_type`, `transfer_ref`, `status`, `repeat_problem_count`) VALUES
(4, 1, '1', '354363', 'هعغهغ', 1, 4, 'المتجر', 'من زمان', 6, 4, 'كسر خارجي', '9897', 'review_pending', 5);

-- --------------------------------------------------------

--
-- بنية الجدول `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'لابتوبات');

-- --------------------------------------------------------

--
-- بنية الجدول `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `complaint_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `problem_title` varchar(200) DEFAULT NULL,
  `problem_details` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `complaints`
--

INSERT INTO `complaints` (`complaint_id`, `laptop_id`, `complaint_date`, `problem_title`, `problem_details`, `image_path`, `user_id`) VALUES
(1, 4, '2025-08-15 00:04:55', 'مشكلة أولية', 'من زمان', NULL, 6);

-- --------------------------------------------------------

--
-- بنية الجدول `laptop_discussions`
--

CREATE TABLE `laptop_discussions` (
  `discussion_id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `laptop_discussions`
--

INSERT INTO `laptop_discussions` (`discussion_id`, `laptop_id`, `user_id`, `message`, `image_path`, `created_at`) VALUES
(1, 4, 6, 'الزبون يشكي من خلل في كرت الشاشه', NULL, '2025-08-15 00:33:22');

-- --------------------------------------------------------

--
-- بنية الجدول `locks`
--

CREATE TABLE `locks` (
  `lock_id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `lock_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `lock_type` varchar(100) DEFAULT NULL,
  `solution_percentage` decimal(5,2) DEFAULT NULL,
  `more_description` text DEFAULT NULL,
  `final_status` varchar(100) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `transfer_ref` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `operations`
--

CREATE TABLE `operations` (
  `operation_id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `operation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `repair_result` varchar(255) DEFAULT NULL,
  `remaining_problems_count` int(11) DEFAULT 0,
  `details` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `operations`
--

INSERT INTO `operations` (`operation_id`, `laptop_id`, `operation_date`, `user_id`, `repair_result`, `remaining_problems_count`, `details`, `image_path`) VALUES
(1, 4, '2025-08-15 00:04:55', 6, 'تم إدخال الجهاز والمشكلة', 4, 'تم إدخال الجهاز بواسطة المستخدم', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `permissions` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `permissions`) VALUES
(4, 'manager', '4444', 'manager'),
(5, 'technician', '4444', 'technician'),
(6, 'admin', '4444', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `broken_laptops`
--
ALTER TABLE `broken_laptops`
  ADD PRIMARY KEY (`laptop_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `entered_by_user_id` (`entered_by_user_id`),
  ADD KEY `assigned_user_id` (`assigned_user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`complaint_id`),
  ADD KEY `laptop_id` (`laptop_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `laptop_discussions`
--
ALTER TABLE `laptop_discussions`
  ADD PRIMARY KEY (`discussion_id`),
  ADD KEY `laptop_id` (`laptop_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `locks`
--
ALTER TABLE `locks`
  ADD PRIMARY KEY (`lock_id`),
  ADD KEY `laptop_id` (`laptop_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`operation_id`),
  ADD KEY `laptop_id` (`laptop_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `broken_laptops`
--
ALTER TABLE `broken_laptops`
  MODIFY `laptop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `laptop_discussions`
--
ALTER TABLE `laptop_discussions`
  MODIFY `discussion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `locks`
--
ALTER TABLE `locks`
  MODIFY `lock_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `operation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `broken_laptops`
--
ALTER TABLE `broken_laptops`
  ADD CONSTRAINT `broken_laptops_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `broken_laptops_ibfk_2` FOREIGN KEY (`entered_by_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `broken_laptops_ibfk_3` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `broken_laptops` (`laptop_id`),
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `laptop_discussions`
--
ALTER TABLE `laptop_discussions`
  ADD CONSTRAINT `laptop_discussions_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `broken_laptops` (`laptop_id`),
  ADD CONSTRAINT `laptop_discussions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `locks`
--
ALTER TABLE `locks`
  ADD CONSTRAINT `locks_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `broken_laptops` (`laptop_id`),
  ADD CONSTRAINT `locks_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `operations`
--
ALTER TABLE `operations`
  ADD CONSTRAINT `operations_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `broken_laptops` (`laptop_id`),
  ADD CONSTRAINT `operations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION

تمام، سأعطيك **شرح تفصيلي لكل واجهة في نظام إدارة اللابتوبات المعطلة**، مع توضيح الهدف، المستخدمين المسموح لهم، وماذا سيحدث داخل كل واجهة. سأرتبها حسب **تدفق العمل من إدخال الجهاز حتى الإغلاق النهائي**:

---

## 1️⃣ **تسجيل الدخول (login.php)**

**الهدف:** السماح للمستخدمين بالدخول حسب صلاحياتهم.
**المستخدمون:** كل من المدراء، مهندسي الصيانة، المسؤولين.
**الوظائف:**

* إدخال اسم المستخدم وكلمة المرور.
* التحقق من الصلاحية.
* إعادة توجيه المستخدم للصفحة المناسبة حسب الصلاحية (Dashboard).

**ملاحظات:**

* يجب أن يكون هناك نظام جلسة `session` لتتبع المستخدم.
* لا يسمح بالدخول بدون تسجيل.

---

## 2️⃣ **لوحة التحكم / Dashboard (index.php)**

**الهدف:** عرض نظرة عامة عن حالة النظام والأجهزة.
**المستخدمون:** كل من لديه صلاحية الدخول.
**المحتوى:**

* إحصائيات سريعة: عدد الأجهزة المعطلة، تحت الإصلاح، المغلقة.
* آخر الأجهزة المضافة.
* تنبيهات للأجهزة ذات المشاكل الحرجة أو المتأخرة.
* روابط سريعة: إضافة جهاز، قائمة الأجهزة، إدارة المستخدمين (حسب الصلاحية).

**ملاحظات:**

* تصميم مختصر وواضح، مناسب للجوال.
* يمكن إضافة ألوان لتوضيح الحالة (أحمر = مشكلة حرجة، أصفر = تحت الصيانة، أخضر = مغلق).

---

## 3️⃣ **إدارة المستخدمين (users.php)**

**الهدف:** إدارة حسابات وصلاحيات المستخدمين.
**المستخدمون:** المدير أو مسؤول النظام فقط.
**الوظائف:**

* إضافة مستخدم جديد (اسم، كلمة مرور، صلاحية: مهندس، مسؤول صيانة، مدير).
* تعديل بيانات المستخدم أو تغيير الصلاحيات.
* حذف مستخدمين إذا لزم.

**ملاحظات:**

* صلاحيات دقيقة حسب الدور.
* لا يمكن حذف المستخدمين الذين لديهم أجهزة تحت إدارتهم إلا بعد نقل الأجهزة.

---

## 4️⃣ **قائمة الأجهزة المعطلة (broken\_laptops.php)**

**الهدف:** عرض كل الأجهزة المعطلة ومتابعتها.
**المستخدمون:** كل المستخدمين حسب الصلاحية.
**المحتوى:**

* جدول يحتوي على: رقم الجهاز، السيريال، الصنف، الحالة الحالية، مدخل البيانات، إجراءات.
* الإجراءات: الدخول لدردشة الجهاز، تسجيل عملية صيانة، عرض الشكاوى، إغلاق الجهاز.
* البحث أو الفرز حسب الفرع، الحالة، الصنف.

**ملاحظات:**

* الصفحة الرئيسية لعملية متابعة الأجهزة.
* تصميم متوافق مع الجوال، مع إمكانية التمرير والبحث السريع.

---

## 5️⃣ **إضافة جهاز جديد (add\_broken\_laptop.php)**

**الهدف:** إدخال جهاز معطل جديد للنظام.
**المستخدمون:** مسؤول الصيانة أو أي مستخدم مخول بإضافة الأجهزة.
**الوظائف:**

* اختيار الصنف (category).
* إدخال السيريال والمواصفات.
* تحديد إذا كان يشمل الشاحن.
* وصف تفصيلي للمشكلة.
* تحديد الفرع ونوع المشكلة.

**ملاحظات:**

* يجب أن تكون جميع الحقول الأساسية (السيريال، التفاصيل) مطلوبة.
* يتم ربط الجهاز تلقائيًا بالمدخل الحالي (user\_id).

---

## 6️⃣ **دردشة/مناقشة الجهاز (laptop\_chat.php)**

**الهدف:** مناقشة المشكلة بين المهندسين والمسؤولين.
**المستخدمون:** كل من لديه حق الوصول للجهاز (مهندسين، مسؤول صيانة، المدير).
**المحتوى:**

* أول رسالة = تفاصيل المشكلة الأساسية من جدول `broken_laptops`.
* باقي الرسائل = المحادثات بين المهندسين والمستخدمين.
* إمكانية رفع صور لكل رسالة.
* عرض اسم المرسل وتاريخ الرسالة.

**ملاحظات:**

* واجهة مثل WhatsApp على الجوال.
* دعم الصور يجعل التوثيق أسهل.

---

## 7️⃣ **إدارة العمليات (operations.php)**

**الهدف:** تسجيل كل خطوات الإصلاح التي يقوم بها المهندس.
**المستخدمون:** المهندس المسؤول عن الجهاز.
**الوظائف:**

* تسجيل نتيجة الإصلاح لكل عملية.
* عدد المشاكل التي تم حلها أو المتبقية.
* رفع صور للعملية إذا لزم.
* تحديث الجهاز تلقائيًا في حالة انتهاء جزء من الإصلاح.

**ملاحظات:**

* كل عملية مرتبطة بـ `user_id` و `laptop_id`.
* يمكن ربط العمليات بالمناقشة لتسهيل التوثيق.

---

## 8️⃣ **جدول الأقفال / إغلاق الجهاز (locks.php)**

**الهدف:** تسجيل الإغلاق النهائي للجهاز بعد الانتهاء من جميع العمليات.
**المستخدمون:** مسؤول الصيانة أو المدير.
**الوظائف:**

* تحديد نوع القفل (نهائي، مؤقت، تحويل).
* تسجيل نسبة حل المشكلة.
* شرح إضافي.
* تحديد الحالة النهائية: مغلق، تحتاج إعادة صيانة، تحويل.

**ملاحظات:**

* يحدد الحالة التي ستظهر في لوحة التحكم.
* بعد الإغلاق النهائي، لا يمكن تعديل البيانات إلا من المدير.

---

## 9️⃣ **عرض الشكاوى (complaints.php)**

**الهدف:** إدارة الشكاوى المرتبطة بكل جهاز.
**المستخدمون:** كل المستخدمين حسب الصلاحية.
**الوظائف:**

* عرض كل الشكاوى لكل جهاز.
* إضافة شكوى جديدة (نص + صورة).
* عرض تاريخ الشكوى واسم المرسل.

---

## 10️⃣ **إعدادات النظام (settings.php)**

**الهدف:** إدارة إعدادات عامة للنظام.
**المستخدمون:** المدير فقط.
**الوظائف:**

* إضافة/تعديل أصناف الأجهزة (categories).
* إضافة أنواع المشاكل.
* إعدادات الفروع.
* إعدادات أخرى مثل تنبيهات البريد أو صلاحيات إضافية.

---

### 🔹 ملاحظات عامة:

* **التدفق الأساسي للجهاز:**

1. إضافة الجهاز → 2. مسؤول الصيانة يراجع → 3. المهندس يقوم بالإصلاح → 4. مسؤول الصيانة يغلق الجهاز → 5. يظهر في لوحة التحكم مع الحالة النهائية.

* **التكامل بين النوافذ:**

  * `broken_laptops.php` = نقطة الوصول لكل جهاز.
  * `laptop_chat.php` = متصل بكل جهاز للتواصل.
  * `operations.php` = لكل عملية إصلاح.
  * `locks.php` = الإغلاق النهائي.

* **الجوال:**

  * كل الواجهات مصممة باستخدام **Tailwind** لتكون متوافقة مع الجوال.
  * يمكن إضافة خصائص مثل الرسائل الفورية لاحقًا باستخدام AJAX.

---

إذا أحببت، أقدر الآن أن أرسم لك **خريطة النظام مع كل واجهة وارتباطها بالمراحل الأربع للجهاز** بشكل رسومي تفاعلي لتبسيط الفهم.

هل تريد أن أفعل ذلك؟



 */;
