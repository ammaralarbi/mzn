<?php
session_start();
require 'db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Basic security check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    // ========================================================================
    // Case 1: Fetch notifications for the logged-in user
    // ========================================================================
    case 'get_notifications':
        $stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt_unread->execute([$user_id]);
        $unread_count = $stmt_unread->fetchColumn();

        $stmt_notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt_notifs->execute([$user_id]);
        $notifications = $stmt_notifs->fetchAll();
        
        echo json_encode(['unread_count' => $unread_count, 'notifications' => $notifications]);
        break;

    // ========================================================================
    // Case 2: Mark all user notifications as read
    // ========================================================================
    case 'mark_notifications_read':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(['success' => true]);
        }
        break;

    // ========================================================================
    // Case 3: Live search for laptops by serial number
    // ========================================================================
    case 'search':
        $query = $_GET['query'] ?? '';
        if (strlen($query) >= 2) {
            $stmt = $pdo->prepare("SELECT laptop_id, serial_number, status FROM broken_laptops WHERE serial_number LIKE ? LIMIT 5");
            $stmt->execute(["%$query%"]);
            $results = $stmt->fetchAll();
            echo json_encode($results);
        } else {
            echo json_encode([]);
        }
        break;

    // ========================================================================
    // Case 4: Get HTML for the "Recent Activity" widget
    // ========================================================================
    case 'get_activity':
        $query = $pdo->query("
            (SELECT o.laptop_id, b.serial_number, u.username, 'تم تسجيل عملية جديدة' as action, o.operation_date as activity_date FROM operations o JOIN users u ON o.user_id = u.user_id JOIN broken_laptops b ON o.laptop_id = b.laptop_id ORDER BY o.operation_date DESC LIMIT 5)
            UNION
            (SELECT l.laptop_id, b.serial_number, u.username, 'تم إغلاق تذكرة' as action, l.lock_date as activity_date FROM locks l JOIN users u ON l.user_id = u.user_id JOIN broken_laptops b ON l.laptop_id = b.laptop_id ORDER BY l.lock_date DESC LIMIT 5)
            ORDER BY activity_date DESC LIMIT 10
        ");
        $activities = $query->fetchAll();
        $html = '';
        if (empty($activities)) {
            $html = '<p class="text-center text-gray-500 py-8">لا يوجد نشاط حديث.</p>';
        } else {
            foreach ($activities as $activity) {
                $icon = $activity['action'] == 'تم إغلاق تذكرة' ? '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' : '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />';
                $html .= '<div class="flex items-start gap-3">
                            <div class="w-10 h-10 flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">' . $icon . '</svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold">' . htmlspecialchars($activity['username']) . ' <span class="font-normal text-gray-500 dark:text-gray-400">' . htmlspecialchars($activity['action']) . '</span></p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">لجهاز <a href="laptop_chat.php?laptop_id=' . $activity['laptop_id'] . '" class="text-blue-500 hover:underline">' . htmlspecialchars($activity['serial_number']) . '</a></p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">' . date("d M, Y H:i", strtotime($activity['activity_date'])) . '</p>
                            </div>
                          </div>';
            }
        }
        echo json_encode(['html' => $html]);
        break;

    // ========================================================================
    // Case 5: Get HTML for "Laptops Requiring Attention" widget
    // ========================================================================
    case 'get_attention_laptops':
        $stmt = $pdo->prepare("
            SELECT b.laptop_id, b.serial_number, b.problem_details, DATEDIFF(NOW(), op.first_date) as days_pending
            FROM broken_laptops b
            JOIN (SELECT laptop_id, MIN(operation_date) as first_date FROM operations GROUP BY laptop_id) op ON b.laptop_id = op.laptop_id
            WHERE b.status = 'review_pending' AND DATEDIFF(NOW(), op.first_date) > 3
            ORDER BY days_pending DESC
        ");
        $stmt->execute();
        $laptops = $stmt->fetchAll();
        $html = '';
        if (empty($laptops)) {
            $html = '<p class="text-center text-gray-500 py-8">لا توجد أجهزة متأخرة حالياً.</p>';
        } else {
            foreach ($laptops as $laptop) {
                $html .= '<div class="bg-red-50 dark:bg-red-500/10 p-4 rounded-lg flex justify-between items-center">
                            <div>
                                <p class="font-bold">' . htmlspecialchars($laptop['serial_number']) . '</p>
                                <p class="text-sm text-gray-600 dark:text-gray-300 truncate w-64">' . htmlspecialchars($laptop['problem_details']) . '</p>
                                <p class="text-xs text-red-500 font-semibold">متأخر ' . $laptop['days_pending'] . ' أيام</p>
                            </div>
                            <a href="laptop_chat.php?laptop_id=' . $laptop['laptop_id'] . '" class="px-3 py-1 bg-red-500 text-white text-sm rounded-md hover:bg-red-600">متابعة</a>
                          </div>';
            }
        }
        echo json_encode(['html' => $html]);
        break;

    // ========================================================================
    // Case 6: Get HTML for "Technician Leaderboard" widget
    // ========================================================================
    case 'get_leaderboard':
        $stmt = $pdo->query("
            SELECT u.username, COUNT(l.lock_id) as closed_count
            FROM locks l
            JOIN users u ON l.user_id = u.user_id
            GROUP BY u.user_id, u.username
            ORDER BY closed_count DESC
            LIMIT 5
        ");
        $techs = $stmt->fetchAll();
        $html = '';
        if (empty($techs)) {
            $html = '<p class="text-center text-gray-500 py-8">لا توجد بيانات لعرضها.</p>';
        } else {
            foreach ($techs as $index => $tech) {
                $html .= '<li class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-lg text-gray-400">' . ($index + 1) . '</span>
                                <img src="https://placehold.co/40x40/E2E8F0/4A5568?text=' . strtoupper(substr($tech['username'], 0, 1)) . '" class="w-10 h-10 rounded-full" alt="">
                                <p class="font-semibold">' . htmlspecialchars($tech['username']) . '</p>
                            </div>
                            <div class="text-left">
                                <p class="font-bold text-lg">' . $tech['closed_count'] . '</p>
                                <p class="text-xs text-gray-500">جهاز مغلق</p>
                            </div>
                          </li>';
            }
        }
        echo json_encode(['html' => $html]);
        break;
    // ========================================================================
    // Case: Get chat messages for a specific laptop
    // ========================================================================
    case 'get_chat_messages':
        $laptop_id = (int)($_GET['laptop_id'] ?? 0);
        if ($laptop_id > 0) {
            $stmt = $pdo->prepare("
                SELECT d.message, d.image_path, d.created_at, u.username, u.user_id
                FROM laptop_discussions d
                JOIN users u ON d.user_id = u.user_id
                WHERE d.laptop_id = ?
                ORDER BY d.created_at ASC
            ");
            $stmt->execute([$laptop_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($messages);
        } else {
            echo json_encode([]);
        }
        break;
         // ========================================================================
    // Case: Fetch comprehensive data for the reports dashboard
    // ========================================================================
    case 'get_report_data':
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        $status = $_GET['status'] ?? '';
        $assigned_user_id = $_GET['assigned_user_id'] ?? '';

        $base_query = "FROM broken_laptops b 
                       LEFT JOIN users u ON b.assigned_user_id = u.user_id 
                       LEFT JOIN operations o ON b.laptop_id = o.laptop_id AND o.operation_id = (SELECT MIN(op.operation_id) FROM operations op WHERE op.laptop_id = b.laptop_id)";
        
        $where_clauses = ["DATE(o.operation_date) BETWEEN ? AND ?"];
        $params = [$start_date, $end_date];

        if (!empty($status)) {
            $where_clauses[] = "b.status = ?";
            $params[] = $status;
        }
        if (!empty($assigned_user_id)) {
            $where_clauses[] = "b.assigned_user_id = ?";
            $params[] = $assigned_user_id;
        }

        $where_sql = "WHERE " . implode(" AND ", $where_clauses);

        // KPIs
        $total_tickets_stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.laptop_id) $base_query $where_sql");
        $total_tickets_stmt->execute($params);
        $total_tickets = $total_tickets_stmt->fetchColumn();

        $closed_tickets_stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.laptop_id) $base_query $where_sql AND b.status IN ('locked', 'مغلق')");
        $closed_tickets_stmt->execute($params);
        $closed_tickets = $closed_tickets_stmt->fetchColumn();
        
        $avg_time_stmt = $pdo->prepare("SELECT AVG(DATEDIFF(l.lock_date, o.operation_date)) FROM locks l JOIN operations o ON l.laptop_id = o.laptop_id WHERE l.lock_id IN (SELECT ll.lock_id FROM locks ll JOIN broken_laptops bb ON ll.laptop_id = bb.laptop_id JOIN operations oo ON bb.laptop_id = oo.laptop_id $where_sql)");
        $avg_time_stmt->execute($params);
        $avg_repair_time = round((float)$avg_time_stmt->fetchColumn(), 1);

        // Charts Data
        $tech_performance_stmt = $pdo->prepare("SELECT u.username, COUNT(b.laptop_id) as count $base_query $where_sql AND b.status IN ('locked', 'مغلق') GROUP BY u.username ORDER BY count DESC");
        $tech_performance_stmt->execute($params);
        $tech_performance = $tech_performance_stmt->fetchAll(PDO::FETCH_ASSOC);

        $problem_types_stmt = $pdo->prepare("SELECT b.problem_type, COUNT(b.laptop_id) as count $base_query $where_sql AND b.problem_type IS NOT NULL AND b.problem_type != '' GROUP BY b.problem_type ORDER BY count DESC LIMIT 5");
        $problem_types_stmt->execute($params);
        $problem_types = $problem_types_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Table Data
        $table_data_stmt = $pdo->prepare("SELECT b.laptop_id, b.serial_number, b.specs, b.employee_name, b.status, u.username as assigned_to, o.operation_date as entry_date $base_query $where_sql ORDER BY b.laptop_id DESC");
        $table_data_stmt->execute($params);
        $table_data = $table_data_stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'kpis' => [
                'total_tickets' => $total_tickets,
                'closed_tickets' => $closed_tickets,
                'completion_rate' => ($total_tickets > 0) ? round(($closed_tickets / $total_tickets) * 100) : 0,
                'avg_repair_time' => $avg_repair_time,
            ],
            'charts' => [
                'tech_performance' => $tech_performance,
                'problem_types' => $problem_types,
            ],
            'table_data' => $table_data
        ]);
        break;
    // ========================================================================
    // Case: Add a new user
    // ========================================================================
    case 'add_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $permissions = $_POST['permissions'] ?? '';

            if (empty($username) || empty($password) || empty($permissions)) {
                echo json_encode(['success' => false, 'message' => 'جميع الحقول مطلوبة.']);
                exit;
            }

            // Check if username already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'اسم المستخدم موجود مسبقاً.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO users (username, password, permissions) VALUES (?, ?, ?)");
            if ($stmt->execute([$username, $password, $permissions])) {
                $new_user_id = $pdo->lastInsertId();
                // Fetch the newly created user's full data to return to the frontend
                $stmt = $pdo->prepare("SELECT user_id, username, permissions FROM users WHERE user_id = ?");
                $stmt->execute([$new_user_id]);
                $new_user = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'user' => $new_user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل إضافة المستخدم.']);
            }
        }
        break;

    // ========================================================================
    // Case: Update an existing user
    // ========================================================================
    case 'update_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
            $user_id = $_POST['user_id'] ?? 0;
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? ''; // Password is optional
            $permissions = $_POST['permissions'] ?? '';

            if (empty($username) || empty($permissions) || empty($user_id)) {
                echo json_encode(['success' => false, 'message' => 'البيانات الأساسية للمستخدم مطلوبة.']);
                exit;
            }

            // Check for duplicate username (excluding the current user)
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'اسم المستخدم موجود مسبقاً.']);
                exit;
            }

            if (!empty($password)) {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, permissions = ? WHERE user_id = ?");
                $success = $stmt->execute([$username, $password, $permissions, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, permissions = ? WHERE user_id = ?");
                $success = $stmt->execute([$username, $permissions, $user_id]);
            }

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'تم تحديث المستخدم بنجاح.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل تحديث المستخدم.']);
            }
        }
        break;

    // ========================================================================
    // Case: Delete a user
    // ========================================================================
    case 'delete_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
            $user_id = $_POST['user_id'] ?? 0;

            if (empty($user_id)) {
                echo json_encode(['success' => false, 'message' => 'معرف المستخدم غير صحيح.']);
                exit;
            }

            // Prevent deleting the currently logged-in user
            if ($user_id == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'لا يمكنك حذف حسابك الخاص.']);
                exit;
            }

            // Check for dependencies (assigned tickets, etc.)
            $stmt = $pdo->prepare("SELECT laptop_id FROM broken_laptops WHERE assigned_user_id = ? OR entered_by_user_id = ?");
            $stmt->execute([$user_id, $user_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'لا يمكن حذف المستخدم لارتباطه بتذاكر. يرجى إعادة تعيين التذاكر أولاً.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            if ($stmt->execute([$user_id])) {
                echo json_encode(['success' => true, 'message' => 'تم حذف المستخدم بنجاح.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل حذف المستخدم.']);
            }
        }
        break;
            case 'get_specs':

        $item_number = $_GET['item_number'] ?? '';

        if (!empty($item_number)) {

            $stmt = $pdo->prepare("SELECT specs FROM item_specifications WHERE item_number = ?");

            $stmt->execute([$item_number]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode($result); // Will return {"specs": "..."} or null

        } else {

            echo json_encode(null);

        }

        break;



// ... (قبل default case) ...

    // ========================================================================
    // Case: Add a new user
    // ========================================================================
    case 'add_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $permissions = $_POST['permissions'] ?? '';
            $branch_name = trim($_POST['branch_name']) ?: null; // Get branch name

            if (empty($username) || empty($password) || empty($permissions)) {
                echo json_encode(['success' => false, 'message' => 'جميع الحقول مطلوبة.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'اسم المستخدم موجود مسبقاً.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO users (username, password, permissions, branch_name) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $password, $permissions, $branch_name])) {
                $new_user_id = $pdo->lastInsertId();
                $stmt = $pdo->prepare("SELECT user_id, username, permissions, branch_name FROM users WHERE user_id = ?");
                $stmt->execute([$new_user_id]);
                $new_user = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'user' => $new_user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل إضافة المستخدم.']);
            }
        }
        break;

    // ========================================================================
    // Case: Update an existing user
    // ========================================================================
    case 'update_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
            $user_id = $_POST['user_id'] ?? 0;
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $permissions = $_POST['permissions'] ?? '';
            $branch_name = trim($_POST['branch_name']) ?: null; // Get branch name

            if (empty($username) || empty($permissions) || empty($user_id)) {
                echo json_encode(['success' => false, 'message' => 'البيانات الأساسية للمستخدم مطلوبة.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'اسم المستخدم موجود مسبقاً.']);
                exit;
            }

            if (!empty($password)) {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, permissions = ?, branch_name = ? WHERE user_id = ?");
                $success = $stmt->execute([$username, $password, $permissions, $branch_name, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, permissions = ?, branch_name = ? WHERE user_id = ?");
                $success = $stmt->execute([$username, $permissions, $branch_name, $user_id]);
            }

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'تم تحديث المستخدم بنجاح.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'فشل تحديث المستخدم.']);
            }
        }
        break;
        // ... (الكود السابق في الملف) ...

    // ========================================================================
    // Case: Assign a ticket to a technician
    // ========================================================================
    case 'assign_ticket':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
            $laptop_id = $_POST['laptop_id'] ?? 0;
            $assign_to_user_id = $_POST['assign_to_user_id'] ?? 0;

            if (empty($laptop_id) || empty($assign_to_user_id)) {
                echo json_encode(['success' => false, 'message' => 'بيانات غير مكتملة.']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                // 1. Update the laptop record
                $stmt = $pdo->prepare("UPDATE broken_laptops SET assigned_user_id = ?, status = 'assigned' WHERE laptop_id = ?");
                $stmt->execute([$assign_to_user_id, $laptop_id]);

                // 2. Get info for notification
                $info_stmt = $pdo->prepare("SELECT serial_number FROM broken_laptops WHERE laptop_id = ?");
                $info_stmt->execute([$laptop_id]);
                $laptop_info = $info_stmt->fetch();
                $ticket_ref = $laptop_info['serial_number'] ?: ('جهاز رقم ' . $laptop_id);

                $assigner_username = $_SESSION['username'];

                // 3. Create an operation log
                $log_stmt = $pdo->prepare("INSERT INTO operations (laptop_id, user_id, repair_result, details) VALUES (?, ?, ?, ?)");
                $log_details = "تم تعيين الجهاز للفني بواسطة " . $assigner_username;
                $log_stmt->execute([$laptop_id, $_SESSION['user_id'], 'تم تعيين المهمة', $log_details]);

                // 4. Send notification to the technician
                $notification_message = "مهمة جديدة: تم تعيين الجهاز " . htmlspecialchars($ticket_ref) . " لك.";
                $notification_link = "laptop_chat.php?laptop_id=" . $laptop_id;
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
                $notif_stmt->execute([$assign_to_user_id, $notification_message, $notification_link]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'تم تعيين الجهاز بنجاح.']);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Assign task error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم.']);
            }
        }
        break;
      

        // ========================================================================
        // Case: Add a new predefined operation
        // ========================================================================
        case 'add_predefined_op':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
                $op_name = trim($_POST['op_name'] ?? '');
                if (empty($op_name)) {
                    echo json_encode(['success' => false, 'message' => 'اسم العملية مطلوب.']); exit;
                }
                try {
                    $stmt = $pdo->prepare("INSERT INTO predefined_operations (op_name) VALUES (?)");
                    $stmt->execute([$op_name]);
                    $new_id = $pdo->lastInsertId();
                    echo json_encode(['success' => true, 'op' => ['op_id' => $new_id, 'op_name' => $op_name]]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'اسم العملية موجود مسبقاً.']);
                }
            }
            break;
    
        // ========================================================================
        // Case: Delete a predefined operation
        // ========================================================================
        case 'delete_predefined_op':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
                $op_id = (int)($_POST['op_id'] ?? 0);
                if ($op_id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM predefined_operations WHERE op_id = ?");
                    if ($stmt->execute([$op_id])) {
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'فشل حذف العملية.']);
                    }
                }
            }
            break;
    
    // ========================================================================
    // Case: Add a new operation, potentially with costs
    // ========================================================================
    case 'add_operation':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            $laptop_id = $_POST['laptop_id'] ?? 0;
            $user_id = $_SESSION['user_id'];
            $repair_result = $_POST['repair_result'] ?? '';
            $details = $_POST['details'] ?? '';
            $work_order_ref = $_POST['work_order_ref'] ?? null;
            $cost_items_json = $_POST['cost_items'] ?? '[]';
            
            if (empty($laptop_id) || empty($repair_result)) {
                echo json_encode(['success' => false, 'message' => 'بيانات العملية غير مكتملة.']); exit;
            }

            $pdo->beginTransaction();
            try {
                // Insert into operations table first
                $stmt = $pdo->prepare("INSERT INTO operations (laptop_id, user_id, repair_result, details) VALUES (?, ?, ?, ?)");
                $stmt->execute([$laptop_id, $user_id, $repair_result, $details]);
                $operation_id = $pdo->lastInsertId();

                // If it's a work order, process costs
                if ($repair_result === 'امر شغل' && $work_order_ref) {
                    $cost_items = json_decode($cost_items_json, true);
                    $total_cost = 0;
                    if (is_array($cost_items)) {
                        foreach ($cost_items as $item) {
                            $total_cost += (float)($item['cost'] ?? 0);
                        }
                    }

                    $cost_stmt = $pdo->prepare(
                        "INSERT INTO repair_costs (laptop_id, operation_id, work_order_ref, user_id, cost_items, total_cost) VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $cost_stmt->execute([$laptop_id, $operation_id, $work_order_ref, $user_id, $cost_items_json, $total_cost]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'تم تسجيل العملية بنجاح.']);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Add Operation/Cost Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم.']);
            }
        }
        break;

        // ... (الكود السابق في الملف) ...

    // = a======================================================================
    // Case: Fetch, filter, and paginate laptops for the main view
    // ========================================================================
    case 'get_laptops':
        $page = (int)($_GET['page'] ?? 1);
        $limit = 20; // Number of items per page
        $offset = ($page - 1) * $limit;

        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'assigned_user_id' => $_GET['assigned_user_id'] ?? ''
        ];

        $base_query = "FROM broken_laptops b 
                       LEFT JOIN users u_assigned ON b.assigned_user_id = u_assigned.user_id
                       LEFT JOIN categories c ON b.category_id = c.category_id
                       LEFT JOIN (SELECT laptop_id, MIN(operation_date) as entry_date FROM operations GROUP BY laptop_id) o ON b.laptop_id = o.laptop_id";
        
        $where_clauses = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $search_term = '%' . $filters['search'] . '%';
            $where_clauses[] = "(b.laptop_id LIKE ? OR b.serial_number LIKE ? OR b.specs LIKE ? OR b.employee_name LIKE ?)";
            array_push($params, $search_term, $search_term, $search_term, $search_term);
        }
        if (!empty($filters['status'])) {
            $where_clauses[] = "b.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['assigned_user_id'])) {
            $where_clauses[] = "b.assigned_user_id = ?";
            $params[] = $filters['assigned_user_id'];
        }

        $where_sql = "WHERE " . implode(" AND ", $where_clauses);

        // Get total count for pagination
        $count_stmt = $pdo->prepare("SELECT COUNT(DISTINCT b.laptop_id) $base_query $where_sql");
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();

        // Get paginated data
        $data_stmt = $pdo->prepare("
            SELECT 
                b.laptop_id, b.serial_number, b.specs, b.employee_name, b.status, b.problem_details,
                u_assigned.username AS assigned_to,
                c.category_name,
                o.entry_date,
                (SELECT COUNT(*) FROM operations op WHERE op.laptop_id = b.laptop_id) as operations_count,
                (SELECT COUNT(*) FROM complaints co WHERE co.laptop_id = b.laptop_id) as complaints_count
            $base_query $where_sql 
            ORDER BY b.laptop_id DESC 
            LIMIT ? OFFSET ?
        ");
        
        $data_params = array_merge($params, [$limit, $offset]);
        $data_stmt->execute($data_params);
        $laptops = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'laptops' => $laptops,
            'total_records' => $total_records,
            'has_more' => ($offset + count($laptops)) < $total_records
        ]);
        break;

// ... (الكود السابق في الملف) ...

    // ========================================================================
    // Case: Assign a ticket to a technician
    // ========================================================================
    case 'assign_ticket':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
            $laptop_id = $_POST['laptop_id'] ?? 0;
            $assign_to_user_id = $_POST['assign_to_user_id'] ?? 0;

            if (empty($laptop_id) || empty($assign_to_user_id)) {
                echo json_encode(['success' => false, 'message' => 'بيانات غير مكتملة.']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                // 1. Update the laptop record
                $stmt = $pdo->prepare("UPDATE broken_laptops SET assigned_user_id = ?, status = 'assigned' WHERE laptop_id = ?");
                $stmt->execute([$assign_to_user_id, $laptop_id]);

                // 2. Get info for notification
                $info_stmt = $pdo->prepare("SELECT serial_number FROM broken_laptops WHERE laptop_id = ?");
                $info_stmt->execute([$laptop_id]);
                $laptop_info = $info_stmt->fetch();
                $ticket_ref = $laptop_info['serial_number'] ?: ('جهاز رقم ' . $laptop_id);

                $assigner_username = $_SESSION['username'];

                // 3. Create an operation log
                $log_stmt = $pdo->prepare("INSERT INTO operations (laptop_id, user_id, repair_result, details) VALUES (?, ?, ?, ?)");
                $log_details = "تم تعيين الجهاز للفني بواسطة " . $assigner_username;
                $log_stmt->execute([$laptop_id, $_SESSION['user_id'], 'تم تعيين المهمة', $log_details]);

                // 4. Send notification to the technician
                $notification_message = "مهمة جديدة: تم تعيين الجهاز " . htmlspecialchars($ticket_ref) . " لك.";
                $notification_link = "laptop_chat.php?laptop_id=" . $laptop_id;
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
                $notif_stmt->execute([$assign_to_user_id, $notification_message, $notification_link]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'تم تعيين الجهاز بنجاح.']);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Assign task error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم.']);
            }
        }
        break;

     

    // ========================================================================
    // Case: Save a user's Firebase Cloud Messaging (FCM) token
    // ========================================================================
    case 'save_fcm_token':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Support both application/json and form-encoded POST
            $token = '';
            if (!empty($_POST['token'])) {
                $token = $_POST['token'];
            } else {
                $raw = file_get_contents('php://input');
                if ($raw) {
                    $json = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE && !empty($json['token'])) {
                        $token = $json['token'];
                    }
                }
            }

            if (!empty($token)) {
                // Check if token already exists for this user to avoid duplicates
                $stmt = $pdo->prepare("SELECT token_id FROM fcm_tokens WHERE user_id = ? AND token = ?");
                $stmt->execute([$_SESSION['user_id'], $token]);
                if (!$stmt->fetch()) {
                    // Insert the new token
                    $insert_stmt = $pdo->prepare("INSERT INTO fcm_tokens (user_id, token) VALUES (?, ?)");
                    $insert_stmt->execute([$_SESSION['user_id'], $token]);
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Token is empty.']);
            }
        }
        break;
// ... (الكود السابق في الملف) ...

    // ========================================================================
    // Case: Create a new inventory transfer from the transfers page
    // ========================================================================
    case 'create_transfer':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
            $laptop_id = $_POST['laptop_id'] ?? '';
            $receive_user_id = (int)($_POST['receive_user_id'] ?? 0);
            $transfer_ref = trim($_POST['transfer_ref']) ?: null;
            $user_id = $_SESSION['user_id'];

            if (empty($laptop_id) || empty($receive_user_id)) {
                echo json_encode(['success' => false, 'message' => 'رقم التذكرة والمستلم حقول مطلوبة.']); exit;
            }

            // Check if laptop exists and is not locked
            $laptop_check_stmt = $pdo->prepare("SELECT status FROM broken_laptops WHERE laptop_id = ?");
            $laptop_check_stmt->execute([$laptop_id]);
            $laptop_status = $laptop_check_stmt->fetchColumn();

            if (!$laptop_status) {
                echo json_encode(['success' => false, 'message' => 'رقم التذكرة غير موجود.']); exit;
            }
            if (in_array($laptop_status, ['locked', 'مغلق', 'جاهز للبيع', 'لم يتم الإصلاح'])) {
                echo json_encode(['success' => false, 'message' => 'لا يمكن تحويل جهاز مغلق.']); exit;
            }

            $pdo->beginTransaction();
            try {
                // 1. Create the transfer record
                $transfer_stmt = $pdo->prepare(
                    "INSERT INTO inventory_transfers (laptop_id, transfer_ref, transfer_user_id, receive_user_id) VALUES (?, ?, ?, ?)"
                );
                $transfer_stmt->execute([$laptop_id, $transfer_ref, $user_id, $receive_user_id]);
                $transfer_id = $pdo->lastInsertId();

                // 2. Create an operation log
                $log_stmt = $pdo->prepare("INSERT INTO operations (laptop_id, user_id, repair_result, details) VALUES (?, ?, ?, ?)");
                $receiver_info = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
                $receiver_info->execute([$receive_user_id]);
                $receiver_name = $receiver_info->fetchColumn();
                $log_details = "تم تحويل الجهاز إلى: " . $receiver_name;
                $log_stmt->execute([$laptop_id, $user_id, 'تحويل مخزني', $log_details]);

                // 3. Send notification
                // require_once 'notifications_helper.php';
                $notification_message = "لديك جهاز بانتظار تأكيد الاستلام برقم تذكرة: " . $laptop_id;
                $notification_link = "transfers.php";
                // send_fcm_notification($pdo, $receive_user_id, 'تحويل مخزني جديد', $notification_message, $notification_link);

                $pdo->commit();
                
                // 4. Fetch the newly created transfer to return to frontend
                $new_transfer_stmt = $pdo->prepare("
                    SELECT t.transfer_id, t.laptop_id, t.transfer_ref, t.transfer_date, 
                    receiver.username as receiver_name, t.is_received, t.receive_date, bl.specs
                    FROM inventory_transfers t
                    JOIN users receiver ON t.receive_user_id = receiver.user_id
                    LEFT JOIN broken_laptops bl ON t.laptop_id = bl.laptop_id
                    WHERE t.transfer_id = ?
                ");
                $new_transfer_stmt->execute([$transfer_id]);
                $new_transfer = $new_transfer_stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'message' => 'تم إنشاء التحويل بنجاح.', 'transfer' => $new_transfer]);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Create Transfer Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم.']);
            }
        }
        break;
        // ... (الكود السابق في الملف) ...
// ... (الكود السابق في الملف) ...
// ... (الكود السابق في الملف) ...
// ... (الكود السابق في الملف) ...

    // ========================================================================
    // Case: Confirm receipt of a transfer (FIXED & IMPROVED)
    // ========================================================================
    case 'confirm_receipt':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            $transfer_id = (int)($_POST['transfer_id'] ?? 0);
            $receiver_id = $_SESSION['user_id'];

            if ($transfer_id > 0) {
                $pdo->beginTransaction();
                try {
                    // First, get transfer info to ensure the current user is the intended receiver
                    $info_stmt = $pdo->prepare("SELECT laptop_id, transfer_user_id FROM inventory_transfers WHERE transfer_id = ? AND receive_user_id = ? AND is_received = 0");
                    $info_stmt->execute([$transfer_id, $receiver_id]);
                    $transfer_info = $info_stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$transfer_info) {
                        throw new Exception('فشل تأكيد الاستلام. قد تكون لا تملك الصلاحية أو أن الجهاز تم استلامه مسبقاً.');
                    }

                    // 1. Update the transfer record
                    $update_stmt = $pdo->prepare("
                        UPDATE inventory_transfers 
                        SET is_received = 1, receive_date = CURRENT_TIMESTAMP 
                        WHERE transfer_id = ?
                    ");
                    $update_stmt->execute([$transfer_id]);

                    // 2. Create an operation log for the receipt
                    $log_stmt = $pdo->prepare("INSERT INTO operations (laptop_id, user_id, repair_result, details) VALUES (?, ?, ?, ?)");
                    $log_stmt->execute([$transfer_info['laptop_id'], $receiver_id, 'استلام مخزني', 'تم تأكيد استلام الجهاز المحول.']);
                    
                    // 3. [IMPROVED] Safely attempt to send a notification back to the original sender
                    if (file_exists('notifications_helper.php')) {
                        require_once 'notifications_helper.php';
                        try {
                            $sender_id = $transfer_info['transfer_user_id'];
                            $notification_message = "تم تأكيد استلام الجهاز برقم تذكرة " . $transfer_info['laptop_id'] . " الذي قمت بتحويله.";
                            $notification_link = "operations.php?laptop_id=" . $transfer_info['laptop_id'];
                            send_fcm_notification($pdo, $sender_id, 'تم استلام التحويل', $notification_message, $notification_link);
                        } catch (Exception $e) {
                            // Log the notification error but don't stop the main process
                            error_log("FCM Notification failed but receipt was confirmed: " . $e->getMessage());
                        }
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'تم تأكيد الاستلام بنجاح.']);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'معرف التحويل غير صالح.']);
            }
        }
        break;

// ... (الكود المتبقي في الملف) ...
// ... (الكود السابق في الملف) ...

    // ========================================================================
    // Case: Create a new inventory transfer from the transfers page (FIXED & IMPROVED)
    // ========================================================================
    case 'create_transfer':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['permissions'], ['admin', 'manager'])) {
            $laptop_id = $_POST['laptop_id'] ?? '';
            $receive_user_id = (int)($_POST['receive_user_id'] ?? 0);
            $transfer_ref = trim($_POST['transfer_ref']) ?: null;
            $user_id = $_SESSION['user_id'];

            if (empty($laptop_id) || empty($receive_user_id)) {
                echo json_encode(['success' => false, 'message' => 'رقم التذكرة والمستلم حقول مطلوبة.']); exit;
            }

            // Check if laptop exists and is not locked
            $laptop_check_stmt = $pdo->prepare("SELECT status FROM broken_laptops WHERE laptop_id = ?");
            $laptop_check_stmt->execute([$laptop_id]);
            $laptop_status = $laptop_check_stmt->fetchColumn();

            if (!$laptop_status) {
                echo json_encode(['success' => false, 'message' => 'رقم التذكرة غير موجود.']); exit;
            }
            if (in_array($laptop_status, ['locked', 'مغلق', 'جاهز للبيع', 'لم يتم الإصلاح'])) {
                echo json_encode(['success' => false, 'message' => 'لا يمكن تحويل جهاز مغلق.']); exit;
            }

            $pdo->beginTransaction();
            try {
                // 1. Create the transfer record
                $transfer_stmt = $pdo->prepare(
                    "INSERT INTO inventory_transfers (laptop_id, transfer_ref, transfer_user_id, receive_user_id) VALUES (?, ?, ?, ?)"
                );
                $transfer_stmt->execute([$laptop_id, $transfer_ref, $user_id, $receive_user_id]);
                $transfer_id = $pdo->lastInsertId();

                // 2. Create an operation log
                $log_stmt = $pdo->prepare("INSERT INTO operations (laptop_id, user_id, repair_result, details) VALUES (?, ?, ?, ?)");
                $receiver_info = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
                $receiver_info->execute([$receive_user_id]);
                $receiver_name = $receiver_info->fetchColumn();
                $log_details = "تم تحويل الجهاز إلى: " . $receiver_name;
                $log_stmt->execute([$laptop_id, $user_id, 'تحويل مخزني', $log_details]);

                // 3. [IMPROVED] Safely attempt to send a notification
                if (file_exists('notifications_helper.php')) {
                    require_once 'notifications_helper.php';
                    try {
                        $notification_message = "لديك جهاز بانتظار تأكيد الاستلام برقم تذكرة: " . $laptop_id;
                        $notification_link = "transfers.php";
                        send_fcm_notification($pdo, $receive_user_id, 'تحويل مخزني جديد', $notification_message, $notification_link);
                    } catch (Exception $e) {
                        error_log("FCM Notification failed during transfer creation: " . $e->getMessage());
                    }
                }

                $pdo->commit();
                
                // 4. Fetch the newly created transfer to return to frontend
                $new_transfer_stmt = $pdo->prepare("
                    SELECT t.transfer_id, t.laptop_id, t.transfer_ref, t.transfer_date, 
                    receiver.username as receiver_name, t.is_received, t.receive_date, bl.specs
                    FROM inventory_transfers t
                    JOIN users receiver ON t.receive_user_id = receiver.user_id
                    LEFT JOIN broken_laptops bl ON t.laptop_id = bl.laptop_id
                    WHERE t.transfer_id = ?
                ");
                $new_transfer_stmt->execute([$transfer_id]);
                $new_transfer = $new_transfer_stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'message' => 'تم إنشاء التحويل بنجاح.', 'transfer' => $new_transfer]);

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Create Transfer Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم.']);
            }
        }
        break;

    // ========================================================================
    // Case: Confirm receipt of a transfer (FIXED & IMPROVED)
    // ========================================================================
    case 'confirm_receipt':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
            $transfer_id = (int)($_POST['transfer_id'] ?? 0);
            $receiver_id = $_SESSION['user_id'];

            if ($transfer_id > 0) {
                $pdo->beginTransaction();
                try {
                    // First, get transfer info to ensure the current user is the intended receiver
                    $info_stmt = $pdo->prepare("SELECT laptop_id, transfer_user_id FROM inventory_transfers WHERE transfer_id = ? AND receive_user_id = ? AND is_received = 0");
                    $info_stmt->execute([$transfer_id, $receiver_id]);
                    $transfer_info = $info_stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$transfer_info) {
                        throw new Exception('فشل تأكيد الاستلام. قد تكون لا تملك الصلاحية أو أن الجهاز تم استلامه مسبقاً.');
                    }

                    // 1. Update the transfer record
                    $update_stmt = $pdo->prepare("
                        UPDATE inventory_transfers 
                        SET is_received = 1, receive_date = CURRENT_TIMESTAMP 
                        WHERE transfer_id = ?
                    ");
                    $update_stmt->execute([$transfer_id]);

                    // 2. Create an operation log for the receipt
                    $log_stmt = $pdo->prepare("INSERT INTO operations (laptop_id, user_id, repair_result, details) VALUES (?, ?, ?, ?)");
                    $log_stmt->execute([$transfer_info['laptop_id'], $receiver_id, 'استلام مخزني', 'تم تأكيد استلام الجهاز المحول.']);
                    
                    // 3. [IMPROVED] Safely attempt to send a notification back to the original sender
                    if (file_exists('notifications_helper.php')) {
                        require_once 'notifications_helper.php';
                        try {
                            $sender_id = $transfer_info['transfer_user_id'];
                            $notification_message = "تم تأكيد استلام الجهاز برقم تذكرة " . $transfer_info['laptop_id'] . " الذي قمت بتحويله.";
                            $notification_link = "operations.php?laptop_id=" . $transfer_info['laptop_id'];
                            send_fcm_notification($pdo, $sender_id, 'تم استلام التحويل', $notification_message, $notification_link);
                        } catch (Exception $e) {
                            // Log the notification error but don't stop the main process
                            error_log("FCM Notification failed but receipt was confirmed: " . $e->getMessage());
                        }
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'تم تأكيد الاستلام بنجاح.']);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'معرف التحويل غير صالح.']);
            }
        }
        break;

// ... (الكود المتبقي في الملف) ...


// ... (قبل default case) ...

    // ========================================================================
    // Case: Add or update item specification (called from add_broken_laptop when a new item number is entered)
    // ========================================================================
    case 'add_spec':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Support form-encoded POST or JSON body
            $item_number = trim($_POST['item_number'] ?? '');
            $specs = trim($_POST['specs'] ?? '');
            if ($item_number === '' || $specs === '') {
                // Try JSON body as fallback
                $raw = file_get_contents('php://input');
                if ($raw) {
                    $json = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $item_number = trim($json['item_number'] ?? $item_number);
                        $specs = trim($json['specs'] ?? $specs);
                    }
                }
            }

            if ($item_number === '' || $specs === '') {
                echo json_encode(['success' => false, 'message' => 'رقم الصنف والمواصفات مطلوبان.']);
                break;
            }

            try {
                // If table has a UNIQUE constraint on item_number this will act like upsert.
                // Otherwise perform a SELECT then INSERT or UPDATE to avoid duplicate rows.
                $check = $pdo->prepare("SELECT COUNT(*) FROM item_specifications WHERE item_number = ?");
                $check->execute([$item_number]);
                $exists = (int)$check->fetchColumn() > 0;

                if ($exists) {
                    $stmt = $pdo->prepare("UPDATE item_specifications SET specs = ? WHERE item_number = ?");
                    $stmt->execute([$specs, $item_number]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO item_specifications (item_number, specs) VALUES (?, ?)");
                    $stmt->execute([$item_number, $specs]);
                }

                echo json_encode(['success' => true, 'item_number' => $item_number, 'specs' => $specs]);
            } catch (Exception $e) {
                error_log('add_spec error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'فشل حفظ البيانات في قاعدة البيانات.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'الطلب يجب أن يكون بواسطة POST.']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>
