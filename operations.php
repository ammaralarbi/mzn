<?php
session_start();
require 'db.php';

// =================================================================================
// SECURITY & PERMISSIONS
// =================================================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$laptop_id = $_GET['laptop_id'] ?? 0;
if (empty($laptop_id)) die("جهاز غير صالح");

// NOTE: The form submission is now handled entirely by api_handler.php

// =================================================================================
// FETCH DATA FOR PAGE DISPLAY
// =================================================================================
$stmt = $pdo->prepare("SELECT b.*, u.username as assigned_to FROM broken_laptops b LEFT JOIN users u ON b.assigned_user_id = u.user_id WHERE laptop_id = ?");
$stmt->execute([$laptop_id]);
$laptop = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$laptop) die("الجهاز غير موجود");

// Fetch UNIFIED timeline data
$timeline_query = $pdo->prepare("
    (SELECT 'operation' as type, o.operation_date as date, u.username, o.repair_result as title, o.details, o.image_path, o.operation_id FROM operations o JOIN users u ON o.user_id = u.user_id WHERE o.laptop_id = ?)
    UNION ALL
    (SELECT 'complaint' as type, c.complaint_date as date, u.username, c.problem_title as title, c.problem_details as details, c.image_path, NULL as operation_id FROM complaints c JOIN users u ON c.user_id = u.user_id WHERE c.laptop_id = ?)
    UNION ALL
    (SELECT 'lock' as type, l.lock_date as date, u.username, CONCAT('إغلاق تذكرة: ', l.lock_type) as title, l.more_description as details, NULL as image_path, NULL as operation_id FROM locks l JOIN users u ON l.user_id = u.user_id WHERE l.laptop_id = ?)
    UNION ALL
    (SELECT 'discussion' as type, d.created_at as date, u.username, 'رسالة في الدردشة' as title, d.message as details, d.image_path, NULL as operation_id FROM laptop_discussions d JOIN users u ON d.user_id = u.user_id WHERE d.laptop_id = ?)
    ORDER BY date DESC
");
$timeline_query->execute([$laptop_id, $laptop_id, $laptop_id, $laptop_id]);
$timeline_events = $timeline_query->fetchAll(PDO::FETCH_ASSOC);

// Fetch cost data to enrich the timeline and calculate total
$costs_query = $pdo->prepare("SELECT operation_id, work_order_ref, total_cost, cost_items FROM repair_costs WHERE laptop_id = ?");
$costs_query->execute([$laptop_id]);
$costs_data = $costs_query->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Calculate total repair cost
$total_repair_cost = 0;
foreach($costs_data as $op_costs) {
    foreach($op_costs as $cost_entry) {
        $total_repair_cost += (float)$cost_entry['total_cost'];
    }
}

// Fetch predefined operations for the dropdown
$ops_query = $pdo->query("SELECT op_name FROM predefined_operations ORDER BY op_name ASC");
$predefined_ops = $ops_query->fetchAll(PDO::FETCH_COLUMN);

$problems = [];
if (!empty($laptop['problem_details'])) {
    $decoded = json_decode($laptop['problem_details'], true);
    if (json_last_error() === JSON_ERROR_NONE) $problems = $decoded;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجل عمليات الجهاز: <?= htmlspecialchars($laptop['serial_number'] ?: $laptop['laptop_id']) ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>
    <link href="https://unpkg.com/filepond/dist/filepond.css" rel="stylesheet">
    <link href="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Cairo', sans-serif; }
        [x-cloak] { display: none !important; }
        .ql-editor { min-height: 120px; font-size: 1rem; }
        .choices__input { background-color: transparent !important; }
        .timeline-item:not(:last-child)::before {
            content: ''; position: absolute; top: 1.25rem; right: 0.7rem; width: 2px;
            height: calc(100% + 2rem); background-color: #E5E7EB;
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="container mx-auto p-4 sm:p-6 lg:p-8" x-data="operationsPage()">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">السجل الزمني للجهاز</h1>
            <p class="text-gray-500">رقم التذكرة: <span class="font-semibold font-mono"><?= htmlspecialchars($laptop['laptop_id']) ?></span></p>
        </div>
        <a href="broken_laptops.php" class="text-sm text-blue-600 hover:underline">العودة لقائمة الأجهزة</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <aside class="lg:col-span-1 bg-white p-6 rounded-2xl shadow-lg h-fit lg:sticky top-8">
            <h2 class="text-xl font-bold mb-4 border-b pb-3">ملخص الجهاز</h2>
            <div class="space-y-4">
                <div><p class="text-sm text-gray-500">المواصفات</p><p class="font-semibold text-gray-800"><?= htmlspecialchars($laptop['specs'] ?: 'غير محدد') ?></p></div>
                <div><p class="text-sm text-gray-500">الفني المسؤول</p><p class="font-semibold text-gray-800"><?= htmlspecialchars($laptop['assigned_to'] ?: 'لم يعين') ?></p></div>
                <div><p class="text-sm text-gray-500">الموظف</p><p class="font-semibold text-gray-800"><?= htmlspecialchars($laptop['employee_name'] ?: 'غير محدد') ?></p></div>
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <p class="text-sm text-blue-800">إجمالي تكاليف الإصلاح</p>
                    <p class="font-bold text-2xl text-blue-600">$<?= number_format($total_repair_cost, 2) ?></p>
                </div>
            </div>
        </aside>

        <main class="lg:col-span-2">
            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-lg mb-8">
                <h2 class="text-xl font-bold mb-6">إضافة عملية / ملاحظة جديدة</h2>
                <form @submit.prevent="submitOperation">
                    <div class="space-y-4">
                        <div>
                            <label for="repair_result" class="block text-sm font-medium text-gray-700 mb-1">عنوان العملية</label>
                            <select id="repair_result" x-ref="choicesSelect" @change="checkWorkOrder">
                                <option value="">-- اختر عملية أو اكتب --</option>
                                <?php foreach($predefined_ops as $op): ?>
                                    <option value="<?= htmlspecialchars($op) ?>"><?= htmlspecialchars($op) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Conditional Work Order Fields -->
                        <div x-show="isWorkOrder" x-transition class="space-y-4 border-t pt-4 mt-4">
                            <div>
                                <label for="work_order_ref" class="block text-sm font-medium text-gray-700 mb-1">رقم أمر الشغل (من النظام المحاسبي)</label>
                                <input type="text" id="work_order_ref" x-model="workOrder.ref" placeholder="e.g., WO-2025-123" class="w-full p-3 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">قائمة التكاليف</label>
                                <div class="space-y-3">
                                    <template x-for="(cost, index) in workOrder.costs" :key="index">
                                        <!-- UPDATED: This div is now responsive for mobile -->
                                        <div class="flex flex-col sm:flex-row items-center gap-2">
                                            <input type="text" x-model="cost.description" placeholder="وصف القطعة أو العمل" class="w-full sm:flex-1 p-2 border border-gray-300 rounded-lg">
                                            <input type="number" step="0.01" x-model="cost.cost" placeholder="التكلفة بالدولار" class="w-full sm:w-32 p-2 border border-gray-300 rounded-lg">
                                            <button type="button" @click="removeCost(index)" x-show="workOrder.costs.length > 1" class="text-red-500 hover:text-red-700 p-2">&times;</button>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="addCost" class="mt-3 text-sm font-semibold text-blue-600 hover:text-blue-800">+ إضافة تكلفة أخرى</button>
                                <div class="mt-3 text-right font-bold text-lg">الإجمالي: $<span x-text="totalCost.toFixed(2)"></span></div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تفاصيل إضافية (اختياري)</label>
                            <div x-ref="quillEditor"></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">إرفاق صورة (اختياري)</label>
                            <input type="file" class="filepond" name="image">
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="w-full px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 disabled:bg-gray-400" :disabled="isSubmitting">تسجيل العملية</button>
                    </div>
                </form>
            </div>
            
            <!-- Timeline -->
            <div>
                <div class="mb-4">
                    <input type="text" x-model="timelineSearch" placeholder="بحث في السجل..." class="w-full p-3 border border-gray-300 rounded-lg">
                </div>
                <div class="space-y-8">
                    <template x-for="event in filteredTimeline" :key="event.date + event.title">
                        <div class="flex gap-4 relative timeline-item">
                            <div class="bg-white flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center z-10" :class="getEventStyle(event.type).bg">
                                <svg class="w-6 h-6" :class="getEventStyle(event.type).iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-html="getEventStyle(event.type).icon"></svg>
                            </div>
                            <div class="flex-1">
                                <div class="bg-white p-4 rounded-lg shadow">
                                    <div class="flex justify-between items-center mb-2">
                                        <p class="font-bold text-gray-800" x-text="event.title"></p>
                                        <span class="text-xs text-gray-500" x-text="new Date(event.date).toLocaleString('ar-EG', {dateStyle: 'medium', timeStyle: 'short'})"></span>
                                    </div>
                                    <p class="text-sm text-gray-500 mb-2">بواسطة: <span class="font-semibold" x-text="event.username"></span></p>
                                    <div class="prose prose-sm max-w-none text-gray-700" x-html="event.details"></div>
                                    
                                    <template x-if="event.image_path">
                                        <!-- UPDATED: Image is now responsive for mobile -->
                                        <img :src="event.image_path" class="mt-3 w-full max-w-xs rounded-lg cursor-pointer" @click="openImage(event.image_path)">
                                    </template>

                                    <template x-if="event.cost_details">
                                        <div class="mt-3 border-t pt-3">
                                            <p class="font-semibold text-sm">تفاصيل أمر الشغل: <span class="font-mono text-blue-600" x-text="event.cost_details.work_order_ref"></span></p>
                                            <ul class="list-disc pr-5 mt-2 text-sm">
                                                <template x-for="item in event.cost_details.cost_items" :key="item.description">
                                                    <li><span x-text="item.description"></span>: <span class="font-semibold" x-text="`$${parseFloat(item.cost).toFixed(2)}`"></span></li>
                                                </template>
                                            </ul>
                                            <p class="text-right font-bold mt-2">الإجمالي: <span x-text="`$${parseFloat(event.cost_details.total_cost).toFixed(2)}`"></span></p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                     <div x-show="filteredTimeline.length === 0" class="text-center py-10 text-gray-500">
                        <p>لا توجد نتائج مطابقة للبحث.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Image Viewer Modal -->
    <div x-show="isImageViewerOpen" @keydown.escape.window="isImageViewerOpen = false" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4" x-cloak>
        <div @click.away="isImageViewerOpen = false" class="relative">
            <img :src="imageViewerSrc" class="max-w-full max-h-[90vh] rounded-lg">
            <button @click="isImageViewerOpen = false" class="absolute -top-2 -right-2 text-white bg-gray-800 rounded-full p-1">&times;</button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.js"></script>
<script src="https://unpkg.com/filepond/dist/filepond.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('operationsPage', () => ({
        isSubmitting: false,
        quill: null,
        pond: null,
        choices: null,
        isWorkOrder: false,
        timelineSearch: '',
        workOrder: {
            ref: '',
            costs: [{ description: '', cost: 0.00 }]
        },
        timelineEvents: [],
        isImageViewerOpen: false,
        imageViewerSrc: '',

        get totalCost() {
            return this.workOrder.costs.reduce((total, item) => total + (parseFloat(item.cost) || 0), 0);
        },
        
        get filteredTimeline() {
            if (!this.timelineSearch.trim()) {
                return this.timelineEvents;
            }
            const search = this.timelineSearch.toLowerCase();
            return this.timelineEvents.filter(event => {
                return (event.title && event.title.toLowerCase().includes(search)) ||
                       (event.details && event.details.toLowerCase().includes(search)) ||
                       (event.username && event.username.toLowerCase().includes(search));
            });
        },

        init() {
            this.timelineEvents = <?= json_encode($timeline_events) ?>.map(event => {
                const cost_details = <?= json_encode($costs_data) ?>[event.operation_id];
                return { ...event, cost_details: cost_details ? cost_details[0] : null };
            });

            this.quill = new Quill(this.$refs.quillEditor, { theme: 'snow', placeholder: 'اكتب تفاصيل إضافية...' });
            this.choices = new Choices(this.$refs.choicesSelect, {
                removeItemButton: true,
                addItemText: (value) => `اضغط Enter لإضافة "${value}"`,
            });
            FilePond.registerPlugin(FilePondPluginImagePreview);
            this.pond = FilePond.create(document.querySelector('.filepond'), {
                labelIdle: `اسحب وأفلت الصورة هنا أو <span class="filepond--label-action">تصفح</span>`,
                credits: false, storeAsFile: true,
            });
        },

        checkWorkOrder() {
            this.isWorkOrder = this.choices.getValue(true) === 'امر شغل';
        },

        addCost() { this.workOrder.costs.push({ description: '', cost: 0.00 }); },
        removeCost(index) { this.workOrder.costs.splice(index, 1); },
        
        openImage(src) { this.imageViewerSrc = src; this.isImageViewerOpen = true; },

        getEventStyle(type) {
            const styles = {
                operation:  { icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />', bg: 'bg-blue-100', iconColor: 'text-blue-600' },
                complaint:  { icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />', bg: 'bg-red-100', iconColor: 'text-red-600' },
                lock:       { icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />', bg: 'bg-green-100', iconColor: 'text-green-600' },
                discussion: { icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />', bg: 'bg-gray-100', iconColor: 'text-gray-600' }
            };
            return styles[type] || styles['discussion'];
        },

        submitOperation() {
            this.isSubmitting = true;
            const formData = new FormData();
            formData.append('laptop_id', <?= json_encode($laptop_id) ?>);
            formData.append('repair_result', this.choices.getValue(true));
            formData.append('details', this.quill.root.innerHTML);
            if (this.pond.getFiles().length > 0) {
                formData.append('image', this.pond.getFile().file);
            }

            if (this.isWorkOrder) {
                formData.append('work_order_ref', this.workOrder.ref);
                formData.append('cost_items', JSON.stringify(this.workOrder.costs));
            }

            fetch('api_handler.php?action=add_operation', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'تم!', text: data.message, timer: 1500, showConfirmButton: false });
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    Swal.fire({ icon: 'error', title: 'خطأ', text: data.message });
                }
            })
            .catch(err => Swal.fire({ icon: 'error', title: 'خطأ فني', text: 'فشل الاتصال بالخادم.' }))
            .finally(() => this.isSubmitting = false);
        }
    }));
});
</script>

</body>
</html>
