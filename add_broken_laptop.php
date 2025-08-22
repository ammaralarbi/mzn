<?php
ob_start();
session_start();
require 'db.php';

// =================================================================================
// SECURITY & PERMISSIONS
// =================================================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_permissions = $_SESSION['permissions'];
if (!in_array($user_permissions, ['technician', 'admin', 'manager', 'storekeeper', 'sales', 'prep_engineer'])) {
    header("Location: index.php?error=access_denied");
    exit;
}

// =================================================================================
// LOGIC FOR DEFAULT CATEGORY
// =================================================================================
// Find or create the default "Laptops" category to hide the selection from the user.
$default_category_name = 'لابتوبات';
$stmt_cat = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = ?");
$stmt_cat->execute([$default_category_name]);
$default_category_id = $stmt_cat->fetchColumn();
if (!$default_category_id) {
    $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)")->execute([$default_category_name]);
    $default_category_id = $pdo->lastInsertId();
}

// =================================================================================
// PREDICT NEXT TICKET NUMBER FOR DISPLAY
// =================================================================================
$stmt_status = $pdo->query("SHOW TABLE STATUS LIKE 'broken_laptops'");
$table_status = $stmt_status->fetch(PDO::FETCH_ASSOC);
$next_laptop_id = $table_status['Auto_increment'];
$predicted_ticket_number = date('ym') . $next_laptop_id;

// =================================================================================
// INITIAL DATA LOADING FOR THE FORM
// =================================================================================
$users_query = $pdo->query("SELECT user_id, username, permissions FROM users WHERE permissions IN ('technician', 'admin', 'manager') ORDER BY username");
$assignable_users = $users_query->fetchAll();
$branches_query = $pdo->query("SELECT DISTINCT branch_name FROM broken_laptops WHERE branch_name IS NOT NULL AND branch_name != '' ORDER BY branch_name");
$existing_branches = $branches_query->fetchAll(PDO::FETCH_COLUMN);
$item_numbers_query = $pdo->query("SELECT item_number FROM item_specifications ORDER BY item_number");
$existing_item_numbers = $item_numbers_query->fetchAll(PDO::FETCH_COLUMN);
// Predefined problem types for the new select dropdown
$problem_types = ['هاردوير', 'سوفتوير', 'بطارية', 'تغير قطعه', 'حراره', 'لا اعلم'];

// =================================================================================
// Helper: normalize serial numbers (convert Arabic digits to ASCII digits and uppercase Latin letters)
if (!function_exists('normalize_serial')) {
    function normalize_serial($s) {
        if ($s === null) return null;
        // Replace Arabic-Indic digits (U+0660–U+0669) and Eastern Arabic-Indic (U+06F0–U+06F9) with ASCII
        $s = str_replace(
            array_merge(range("60", "69"), range("F0", "F9")),
            array_merge(range('0','9'), range('0','9')),
            $s
        );
        // Fallback safer replacement using bytes for common Arabic digits
        $arabic_digits = ['60','61','62','63','64','65','66','67','68','69'];
        $eastern_digits = ['F0','F1','F2','F3','F4','F5','F6','F7','F8','F9'];
        // Replace by character codes (numeric replacement)
        $s = preg_replace_callback('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', function($m){
            $code = mb_ord($m[0], 'UTF-8');
            if ($code >= 0x0660 && $code <= 0x0669) return chr($code - 0x0660 + 48);
            if ($code >= 0x06F0 && $code <= 0x06F9) return chr($code - 0x06F0 + 48);
            return $m[0];
        }, $s);
        // Uppercase ASCII letters (leave other scripts untouched)
        // mb_strtoupper may affect non-Latin scripts; use preg to uppercase A-Z only
        $s = preg_replace_callback('/[a-z]/i', function($m){ return strtoupper($m[0]); }, $s);
        return $s;
    }
}

// =================================================================================
// FORM SUBMISSION HANDLING
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'errors' => [], 'laptop_id' => null, 'ticket_display_number' => null];
    
    try {
        $pdo->beginTransaction();

        $employee_name = $_SESSION['username']; 
        $item_number = trim($_POST['item_number']) ?: null;
        if (empty($item_number)) $response['errors'][] = "رقم الصنف مطلوب.";

        // Serial number is optional. Store NULL when the field is left empty.
        $serial_number = null;
        if (isset($_POST['serial_number'])) {
            $tmp = trim($_POST['serial_number']);
            $serial_number = $tmp !== '' ? normalize_serial($tmp) : null;
        }

        $specs = trim($_POST['specs']) ?: null;
        if (empty($specs)) $response['errors'][] = "المواصفات حقل مطلوب.";

        $problems = isset($_POST['problems']) ? $_POST['problems'] : [];
        if (empty($problems) || !is_array($problems)) $response['errors'][] = "يجب إضافة مشكلة واحدة على الأقل.";
        $problem_details_json = json_encode($problems, JSON_UNESCAPED_UNICODE);

        // Branch handling: allow choosing existing branch or submitting a new branch via branch_new
        $branch_name = null;
        if (isset($_POST['branch_name'])) {
            if ($_POST['branch_name'] === '__other__') {
                $branch_name = trim($_POST['branch_new'] ?? '') ?: null;
            } else {
                $branch_name = trim($_POST['branch_name']) ?: null;
            }
        }

        if (!empty($response['errors'])) throw new Exception("Validation failed");

        $assigned_user_id = null;
        if (in_array($user_permissions, ['admin', 'manager'])) {
            $assigned_user_id = empty($_POST['assigned_user_id']) ? null : (int)$_POST['assigned_user_id'];
        }
        
        $sql = "INSERT INTO broken_laptops (
                    employee_name, item_number, category_id, device_category_number, serial_number, specs, 
                    with_charger, branch_name, problem_details, entered_by_user_id, 
                    assigned_user_id, problem_type, status, repeat_problem_count
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $employee_name, $item_number, (int)$default_category_id, // Use default category ID
            // device_category_number is intentionally not shown in the form — store NULL
            null, $serial_number, $specs,
            isset($_POST['with_charger']) ? 1 : 0, $branch_name,
            $problem_details_json, (int)$_SESSION['user_id'], $assigned_user_id,
            trim($_POST['problem_type']) ?: null, 'entered', (int)($_POST['repeat_problem_count'] ?? 0)
        ]);

        $laptop_id = $pdo->lastInsertId();
        $response['laptop_id'] = $laptop_id;

        // Ensure ticket_number column exists; create if missing
        try {
            $colCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'broken_laptops' AND COLUMN_NAME = 'ticket_number'");
            $colCheck->execute();
            if ((int)$colCheck->fetchColumn() === 0) {
                // add column
                $pdo->exec("ALTER TABLE broken_laptops ADD COLUMN ticket_number VARCHAR(32) NULL");
            }

            // Compute monthly sequence based on existing ticket_number values for current YYMM
            $ym = date('ym'); // e.g. 2508
            $like = $ym . '%';
            $seqStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(ticket_number,5) AS UNSIGNED)) as maxseq FROM broken_laptops WHERE ticket_number LIKE ?");
            $seqStmt->execute([$like]);
            $maxSeq = (int)$seqStmt->fetchColumn();
            $seq = $maxSeq + 1;
            if ($seq < 1) $seq = 1;
            if ($seq > 999) $seq = 999; // safety cap as requested

            $ticket_number = $ym . str_pad($seq, 3, '0', STR_PAD_LEFT); // e.g. 2508001

            $update = $pdo->prepare("UPDATE broken_laptops SET ticket_number = ? WHERE laptop_id = ?");
            $update->execute([$ticket_number, $laptop_id]);

            $response['ticket_display_number'] = $ticket_number;
        } catch (Exception $e) {
            // fallback to old behavior if anything goes wrong
            $response['ticket_display_number'] = date('ym') . $laptop_id;
        }

        $first_problem = ($problems[0]['title'] ?? 'N/A') . ' - ' . ($problems[0]['details'] ?? '');
        $cstm = $pdo->prepare("INSERT INTO complaints (laptop_id, problem_title, problem_details, user_id) VALUES (?, ?, ?, ?)");
        $cstm->execute([$laptop_id, 'مشكلة أولية عند الإدخال', $first_problem, (int)$_SESSION['user_id']]);

        $log = $pdo->prepare("INSERT INTO operations (laptop_id, user_id, repair_result, details) VALUES (?, ?, ?, ?)");
        $log->execute([$laptop_id, (int)$_SESSION['user_id'], 'تم إدخال الجهاز للنظام', 'تم إنشاء تذكرة صيانة برقم ' . $response['ticket_display_number']]);
        
        if ($assigned_user_id) {
            $notification_message = "تم تعيين جهاز جديد لك (" . htmlspecialchars($serial_number) . ") برقم تذكرة " . $response['ticket_display_number'];
            $notification_link = "laptop_chat.php?laptop_id=" . $laptop_id;
            $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
            $notif_stmt->execute([$assigned_user_id, $notification_message, $notification_link]);
        }

        $pdo->commit();
        $response['success'] = true;

    } catch (Exception $e) {
        $pdo->rollBack();
        if(empty($response['errors'])) {
            error_log('Add Laptop Error: ' . $e->getMessage());
            $response['errors'][] = 'حدث خطأ غير متوقع في الخادم.';
        }
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة تذكرة صيانة جديدة</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://unpkg.com/filepond/dist/filepond.css" rel="stylesheet">
    <link href="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.7/minified/html5-qrcode.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .choices__input { background-color: transparent !important; }
        [x-cloak] { display: none !important; }
        .step-indicator { transition: all 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-gray-50">

    <div x-data="ticketForm()" x-cloak class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-5xl">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">إنشاء تذكرة صيانة جديدة</h1>
                <p class="text-gray-500">سيتم توليد رقم تذكرة فريد بعد الحفظ.</p>
            </div>
            <a href="index.php" class="text-sm text-blue-600 hover:underline">العودة للرئيسية</a>
        </div>

        <div class="flex items-center justify-between mb-8 p-2 bg-gray-100 rounded-lg">
            <template x-for="(step, index) in steps" :key="index">
                <div class="flex-1 text-center">
                    <button @click="currentStep = index + 1" :disabled="index >= currentStep" 
                            class="step-indicator w-full py-2 px-4 rounded-md text-sm font-semibold"
                            :class="{
                                'bg-blue-500 text-white shadow': currentStep === index + 1,
                                'bg-white text-blue-500 hover:bg-blue-50': currentStep > index + 1,
                                'bg-transparent text-gray-400 cursor-not-allowed': currentStep < index + 1
                            }">
                        <span x-text="step"></span>
                    </button>
                </div>
            </template>
        </div>

        <form @submit.prevent="submitForm" id="add-ticket-form" method="POST" enctype="multipart/form-data" class="bg-white p-6 sm:p-8 rounded-2xl shadow-lg">
            
        <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-4 rounded-r-lg">
                    <p class="font-bold">رقم تذكرة الصيانة:<?= htmlspecialchars($predicted_ticket_number) ?></p>
                    <p class="font-bold text-2xl tracking-widest"></p>
                    <p class="text-xs mt-1">هذا رقم يتم كتابته فوق الجهاز.</p>
                </div>
            
            <!-- ======================= STEP 1: ITEM & DEVICE INFO ======================= -->
            <section x-show="currentStep === 1">
                <h2 class="text-xl font-bold mb-4 border-b pb-2">1. بيانات الجهاز الأساسية</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="item_number" class="block text-sm font-medium text-gray-700 mb-1">رقم الصنف</label>
                        <div class="flex gap-2 items-center">
                            <input type="text" id="item_number" name="item_number" x-model="device.item_number" @blur="fetchSpecs()" list="item_numbers" placeholder="أدخل رقم الصنف لجلب المواصفات" required class="flex-1 p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button" @click="openQRScanner()" title="مسح QR" class="p-2 bg-blue-500 text-white rounded-md hover:bg-blue-600" style="min-width:44px;">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h4M3 11h4M3 15h4M17 7h4M17 11h4M17 15h4M7 3v4M11 3v4M7 17v4M11 17v4M13 7h-2v2h2V7z"/></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="serial_number" class="block text-sm font-medium text-gray-700 mb-1">الرقم التسلسلي (اختياري)</label>
                        <input type="text" id="serial_number" name="serial_number" class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>
                    <div class="md:col-span-2">
                        <label for="specs" class="block text-sm font-medium text-gray-700 mb-1">المواصفات</label>
                        <textarea id="specs" name="specs" x-model="device.specs" rows="3" placeholder="سيتم ملء هذا الحقل تلقائياً بعد إدخال رقم الصنف..." class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed text-left" required readonly style="direction: ltr;"></textarea>
                    </div>
                </div>
            </section>

            <!-- ======================= STEP 2: PROBLEM DESCRIPTION ======================= -->
            <section x-show="currentStep === 2" style="display: none;">
                <h2 class="text-xl font-bold mb-4 border-b pb-2">2. وصف المشاكل</h2>
                <div id="problems-container" class="space-y-4">
                    <template x-for="(problem, index) in problems" :key="index">
                        <div class="p-4 border rounded-lg bg-gray-50 relative">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1" :for="'problem_title_' + index">عنوان المشكلة</label>
                                    <input type="text" :id="'problem_title_' + index" :name="'problems[' + index + '][title]'" x-model="problem.title" required placeholder="مثال: الشاشة لا تعمل" class="w-full p-2 border border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1" :for="'problem_details_' + index">تفاصيل إضافية</label>
                                    <input type="text" :id="'problem_details_' + index" :name="'problems[' + index + '][details]'" x-model="problem.details" placeholder="مثال: تظهر خطوط عمودية" class="w-full p-2 border border-gray-300 rounded-md">
                                </div>
                            </div>
                            <button type="button" @click="removeProblem(index)" x-show="problems.length > 1" class="absolute top-2 left-2 text-gray-400 hover:text-red-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addProblem" class="mt-4 text-sm font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    إضافة مشكلة أخرى
                </button>
            </section>

            <!-- ======================= STEP 3: ATTACHMENTS & ASSIGNMENT ======================= -->
            <section x-show="currentStep === 3" style="display: none;">
                 <h2 class="text-xl font-bold mb-4 border-b pb-2">3. المرفقات والتعيين</h2>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">إرفاق صور (اختياري)</label>
                        <input type="file" name="complaint_images[]" class="filepond" multiple data-max-file-size="3MB" data-max-files="5">
                    </div>
                    
                    <div>
                        <label for="branch_name" class="block text-sm font-medium text-gray-700 mb-1">الفرع</label>
                        <select id="branch_name" name="branch_name" x-model="branchName" @change="toggleNewBranchInput" class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="">-- اختر الفرع --</option>
                            <?php foreach ($existing_branches as $branch): ?>
                                <option value="<?= htmlspecialchars($branch) ?>"><?= htmlspecialchars($branch) ?></option>
                            <?php endforeach; ?>
                            <option value="__other__">فرع جديد...</option>
                        </select>
                    </div>
                    <div x-show="branchName === '__other__'" style="display: none;">
                        <label for="branch_new" class="block text-sm font-medium text-gray-700 mb-1">اسم الفرع الجديد</label>
                        <input type="text" id="branch_new" name="branch_new" placeholder="أدخل اسم الفرع الجديد" class="w-full p-3 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label for="problem_type" class="block text-sm font-medium text-gray-700 mb-1">نوع المشكلة</label>
                        <select name="problem_type" id="problem_type" class="w-full p-3 border border-gray-300 rounded-lg">
                            <option value="">-- اختر النوع --</option>
                            <?php foreach ($problem_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- <?php if (in_array($user_permissions, ['admin', 'manager'])): ?>
                    <div class="md:col-span-2">
                        <label for="assigned_user_id" class="block text-sm font-medium text-gray-700 mb-1">تعيين لفني (اختياري)</label>
                         <select name="assigned_user_id" id="assigned_user_id" x-ref="assigneeSelect">
                            <option value="">-- عدم التعيين --</option>
                            <?php foreach ($assignable_users as $user): ?>
                                <option value="<?= $user['user_id'] ?>"><?= htmlspecialchars($user['username'] . ' (' . $user['permissions'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?> -->
                 </div>
            </section>

            <div class="mt-8 pt-6 border-t flex justify-between items-center">
                <button type="button" @click="prevStep" x-show="currentStep > 1" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300" style="display: none;">السابق</button>
                <div x-show="currentStep === 1" class="w-full text-left"></div>
                <button type="button" @click="nextStep" x-show="currentStep < steps.length" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">التالي</button>
                <button type="submit" x-show="currentStep === steps.length" 
                        class="px-8 py-3 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700 flex items-center gap-2 disabled:bg-gray-400"
                        :disabled="isSubmitting" style="display: none;">
                    <span x-show="!isSubmitting">حفظ التذكرة</span>
                    <span x-show="isSubmitting" style="display: none;">جاري الحفظ...</span>
                    <div x-show="isSubmitting" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" style="display: none;"></div>
                </button>
            </div>
        </form>
    </div>

    <!-- QR Scanner Modal -->
    <div x-show="showScanner" style="display:none;" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-4 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-2">
                <h3 class="font-semibold">مسح QR لرقم الصنف</h3>
                <button type="button" @click="closeQRScanner()" class="text-gray-600 hover:text-gray-800">إغلاق</button>
            </div>
            <div id="qr-reader" style="width:100%;"></div>
            <p class="text-sm text-gray-500 mt-2">اسمح للمتصفح باستخدام الكاميرا ثم وجه QR نحو الكاميرا. سيتم إدخال رقم الصنف تلقائياً عند المسح.</p>
        </div>
    </div>

    <script src="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.js"></script>
    <script src="https://unpkg.com/filepond-plugin-file-validate-size/dist/filepond-plugin-file-validate-size.js"></script>
    <script src="https://unpkg.com/filepond/dist/filepond.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    
    <script>
        // Normalize Arabic-Indic digits to ASCII and uppercase Latin letters in JS
        function normalizeSerialJS(s) {
            if (!s) return s;
            // Replace Arabic-Indic (0660-0669) and Eastern Arabic-Indic (06F0-06F9)
            s = s.replace(/[\u0660-\u0669]/g, function(ch){ return String.fromCharCode(48 + ch.charCodeAt(0) - 0x0660); });
            s = s.replace(/[\u06F0-\u06F9]/g, function(ch){ return String.fromCharCode(48 + ch.charCodeAt(0) - 0x06F0); });
            // Uppercase English letters
            s = s.replace(/[a-z]/g, function(ch){ return ch.toUpperCase(); });
            return s;
        }

        document.addEventListener('DOMContentLoaded', function(){
            var el = document.getElementById('serial_number');
            if (el) {
                el.addEventListener('input', function(e){
                    var pos = el.selectionStart;
                    el.value = normalizeSerialJS(el.value);
                    try { el.setSelectionRange(pos, pos); } catch(e){}
                });
                // normalize once on page load
                el.value = normalizeSerialJS(el.value);
                // ensure normalization before form submit
                var form = el.form;
                if (form) form.addEventListener('submit', function(){ el.value = normalizeSerialJS(el.value); });
            }
        });

        document.addEventListener('alpine:init', () => {
            Alpine.data('ticketForm', () => ({
                // QR scanner state
                showScanner: false,
                qrScannerInstance: null,
                currentStep: 1,
                steps: ['بيانات الجهاز', 'وصف المشاكل', 'المرفقات والتعيين'],
                isSubmitting: false,
                device: {
                    item_number: '',
                    specs: ''
                },
                problems: [{ title: '', details: '' }],
                branchName: '',
                assigneeSelect: null,
                init() {
                    if (this.$refs.assigneeSelect) {
                        this.assigneeSelect = new Choices(this.$refs.assigneeSelect, { searchEnabled: true, itemSelectText: 'اختر', placeholder: true, placeholderValue: '-- بحث عن فني --' });
                    }
                    FilePond.registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateSize);
                    FilePond.create(document.querySelector('.filepond'), { labelIdle: `اسحب وأفلت الملفات هنا أو <span class="filepond--label-action">تصفح</span>`, credits: false });
                },
                nextStep() { if (this.currentStep < this.steps.length) this.currentStep++; },
                prevStep() { if (this.currentStep > 1) this.currentStep--; },
                fetchSpecs() {
                    if (!this.device.item_number) return;
                    const itemNum = this.device.item_number.trim();
                    fetch(`api_handler.php?action=get_specs&item_number=${encodeURIComponent(itemNum)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.specs) {
                                this.device.specs = data.specs;
                            } else {
                                this.device.specs = ''; // Clear if not found
                                // Ask user to add specs for this item number
                                Swal.fire({
                                    title: 'لم يتم العثور على رقم الصنف',
                                    html: `<p>رقم الصنف <strong>${itemNum}</strong> غير موجود. هل تريد إضافة مواصفات لهذا الرقم الآن؟</p>`,
                                    showCancelButton: true,
                                    confirmButtonText: 'إضافة مواصفات',
                                    cancelButtonText: 'إلغاء',
                                    input: 'textarea',
                                    inputPlaceholder: 'أدخل مواصفات الصنف هنا...',
                                    inputAttributes: { 'aria-label': 'مواصفات الصنف' },
                                    preConfirm: (specs) => {
                                        if (!specs || specs.trim() === '') {
                                            Swal.showValidationMessage('المواصفات لا يمكن أن تكون فارغة');
                                            return false;
                                        }
                                        return specs.trim();
                                    }
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        const specsText = result.value;
                                        // send to server to save the spec
                                        const fd = new FormData();
                                        fd.append('item_number', itemNum);
                                        fd.append('specs', specsText);
                                        fetch('api_handler.php?action=add_spec', { method: 'POST', body: fd })
                                            .then(r => r.json())
                                            .then(resp => {
                                                if (resp && resp.success) {
                                                    Swal.fire({ icon: 'success', title: 'تم الحفظ', text: 'تم إضافة مواصفات الصنف بنجاح.' });
                                                    this.device.specs = specsText;
                                                    // add new item number to datalist so it is available next time without reload
                                                    try {
                                                        const dl = document.getElementById('item_numbers');
                                                        if (dl) {
                                                            const exists = Array.from(dl.options).some(o => o.value === itemNum);
                                                            if (!exists) {
                                                                const opt = document.createElement('option');
                                                                opt.value = itemNum;
                                                                dl.appendChild(opt);
                                                            }
                                                        }
                                                    } catch (e) { console.warn('Failed to update datalist:', e); }
                                                    // ensure item_number normalized/trimmed
                                                    this.device.item_number = itemNum;
                                                    // move to next step automatically
                                                    if (typeof this.nextStep === 'function') this.nextStep();
                                                } else {
                                                    Swal.fire({ icon: 'error', title: 'فشل الحفظ', text: (resp.message || 'تعذر حفظ المواصفات.') });
                                                }
                                            })
                                            .catch(err => {
                                                console.error('Add spec error:', err);
                                                Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذر الاتصال بالخادم.' });
                                            });
                                    }
                                });
                            }
                        }).catch(err => {
                            console.error('get_specs error:', err);
                        });
                },
                addProblem() { this.problems.push({ title: '', details: '' }); },
                removeProblem(index) { this.problems.splice(index, 1); },
                toggleNewBranchInput() {
                    if (this.branchName === '__other__') {
                        document.querySelector('#branch_new').parentElement.style.display = 'block';
                    } else {
                        document.querySelector('#branch_new').parentElement.style.display = 'none';
                    }
                },
                openQRScanner() {
                    // Show modal then start camera
                    this.showScanner = true;
                    this.$nextTick(() => {
                        try {
                            if (this.qrScannerInstance) return; // already started
                            const config = { fps: 10, qrbox: 250 };
                            this.qrScannerInstance = new Html5Qrcode("qr-reader");
                            Html5Qrcode.getCameras().then(cameras => {
                                const cameraId = (cameras && cameras.length) ? cameras[0].id : null;
                                this.qrScannerInstance.start(
                                    cameraId,
                                    config,
                                    (decodedText, decodedResult) => {
                                        // when a QR is scanned, fill the field and stop
                                        this.device.item_number = decodedText.trim();
                                        // close scanner and fetch specs
                                        this.closeQRScanner();
                                        this.fetchSpecs();
                                    },
                                    (errorMessage) => {
                                        // ignore scan failures
                                    }
                                ).catch(err => {
                                    console.error('QR start error:', err);
                                    Swal.fire({ icon: 'error', title: 'خطأ بالكاميرا', text: 'تعذر تشغيل الكاميرا. تحقق من أذونات المتصفح.' });
                                });
                            }).catch(err => {
                                console.error('No cameras:', err);
                                Swal.fire({ icon: 'error', title: 'لا توجد كاميرا', text: 'لم يتم العثور على كاميرا متاحة.' });
                            });
                        } catch (e) { console.error(e); }
                    });
                },
                closeQRScanner() {
                    try {
                        if (this.qrScannerInstance) {
                            this.qrScannerInstance.stop().then(() => {
                                this.qrScannerInstance.clear();
                                this.qrScannerInstance = null;
                            }).catch(e => { console.warn('QR stop error', e); this.qrScannerInstance = null; });
                        }
                    } catch (e) { console.warn(e); }
                    this.showScanner = false;
                },
                submitForm() {
                    this.isSubmitting = true;
                    const formData = new FormData(document.getElementById('add-ticket-form'));
                    fetch('add_broken_laptop.php', { method: 'POST', body: formData })
                    .then(response => response.text())
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success', 
                                    title: 'تم الحفظ بنجاح!', 
                                    html: `تم إنشاء تذكرة الصيانة بنجاح. <br><br> <strong>رقم التذكرة: ${data.ticket_display_number}</strong>`,
                                    showCancelButton: true, 
                                    confirmButtonText: 'عرض التذكرة', 
                                    cancelButtonText: 'إضافة تذكرة أخرى',
                                    confirmButtonColor: '#3B82F6', 
                                    cancelButtonColor: '#10B981',
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.href = `laptop_chat.php?laptop_id=${data.laptop_id}`;
                                    } else {
                                        window.location.reload();
                                    }
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'خطأ في الإدخال', html: data.errors.join('<br>') });
                            }
                        } catch (e) {
                            console.error("Failed to parse JSON:", e);
                            console.error("Server response:", text);
                            Swal.fire({
                                icon: 'error', title: 'خطأ فني',
                                html: `حدث خطأ غير متوقع من الخادم. <br><br><b>استجابة الخادم:</b><pre class="text-left text-xs bg-gray-100 p-2 rounded">${text.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</pre>`
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({ icon: 'error', title: 'خطأ فني', text: 'فشل الاتصال بالخادم: ' + error.message });
                        console.error('Fetch Error:', error);
                    })
                    .finally(() => {
                        this.isSubmitting = false;
                    });
                }
            }));
        });
    </script>
</body>
</html>
