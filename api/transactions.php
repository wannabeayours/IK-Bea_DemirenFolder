<?php

include "headers.php";

class Transactions
{
    function bookingList()
    {
        include "connection.php";

        $sql = "
    SELECT 
        b.reference_no AS 'Ref No',
        COALESCE(CONCAT(c.customers_fname, ' ', c.customers_lname),
                 CONCAT(w.customers_walk_in_fname, ' ', w.customers_walk_in_lname)) AS 'Name',
        b.booking_checkin_dateandtime AS 'Check-in',
        b.booking_checkout_dateandtime AS 'Check-out',
        GROUP_CONCAT(DISTINCT rt.roomtype_name SEPARATOR ', ') AS 'Room Type',
        'Pending' AS 'Status'
    FROM 
        tbl_booking b
    LEFT JOIN tbl_customers c ON b.customers_id = c.customers_id
    LEFT JOIN tbl_customers_walk_in w ON b.customers_walk_in_id = w.customers_walk_in_id
    LEFT JOIN tbl_booking_room br ON b.booking_id = br.booking_id
    LEFT JOIN tbl_roomtype rt ON br.roomtype_id = rt.roomtype_id
    WHERE 
        b.booking_id NOT IN (
            SELECT booking_id
            FROM tbl_booking_history
            WHERE status_id IN (1, 2, 3)
        )
    GROUP BY b.reference_no;
    ";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    }

    function finalizeBookingApproval($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $reference_no = $json['reference_no'] ?? '';
        $selected_room_ids = $json['assigned_rooms'] ?? [];

        if (!$reference_no || empty($selected_room_ids)) {
            echo 'invalid';
            return;
        }

        $stmt = $conn->prepare("SELECT booking_id FROM tbl_booking WHERE reference_no = :ref");
        $stmt->bindParam(':ref', $reference_no);
        $stmt->execute();
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            echo 'not_found';
            return;
        }

        $booking_id = $booking['booking_id'];
        $employee_id = isset($json['employee_id']) ? intval($json['employee_id']) : null;

        // Safeguards: prefer valid employee, but do not block if missing/invalid
        if (empty($employee_id) || $employee_id <= 0) {
            // Try fallback to first active employee
            try {
                $fallback = $conn->prepare("SELECT employee_id FROM tbl_employee WHERE employee_status NOT IN ('Inactive','Disabled',0,'0') ORDER BY employee_id ASC LIMIT 1");
                $fallback->execute();
                $fb = $fallback->fetch(PDO::FETCH_ASSOC);
                if ($fb && isset($fb['employee_id'])) {
                    $employee_id = intval($fb['employee_id']);
                } else {
                    // Proceed with null; history insert may still succeed if FK not enforced
                    $employee_id = 0;
                }
            } catch (Exception $e) {
                $employee_id = 0;
            }
        } else {
            $empStmt = $conn->prepare("SELECT employee_id, employee_status FROM tbl_employee WHERE employee_id = :employee_id");
            $empStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $empStmt->execute();
            $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
            if (!$empRow || $empRow["employee_status"] == 0 || $empRow["employee_status"] === 'Inactive' || $empRow["employee_status"] === 'Disabled') {
                // Fallback to any active employee
                try {
                    $fallback = $conn->prepare("SELECT employee_id FROM tbl_employee WHERE employee_status NOT IN ('Inactive','Disabled',0,'0') ORDER BY employee_id ASC LIMIT 1");
                    $fallback->execute();
                    $fb = $fallback->fetch(PDO::FETCH_ASSOC);
                    if ($fb && isset($fb['employee_id'])) {
                        $employee_id = intval($fb['employee_id']);
                    }
                } catch (Exception $e) {
                    // Proceed non-blocking
                }
            }
        }

        // Assign rooms to booking_room (do not change occupancy here)
        foreach ($selected_room_ids as $room_id) {
            $stmt = $conn->prepare("
            UPDATE tbl_booking_room 
            SET roomnumber_id = :room_id 
            WHERE booking_id = :booking_id AND roomnumber_id IS NULL 
            LIMIT 1
        ");
            $stmt->bindParam(':room_id', $room_id);
            $stmt->bindParam(':booking_id', $booking_id);
            $stmt->execute();
        }

        // Insert Reserved/Confirmed status dynamically based on name; occupancy updates happen on check-in via changeBookingStatus
        $stRows = $conn->query("SELECT booking_status_id, booking_status_name FROM tbl_booking_status")->fetchAll(PDO::FETCH_ASSOC);
        $findId = function ($rows, $cands) {
            foreach ($rows as $r) {
                $name = strtolower(trim($r['booking_status_name']));
                foreach ($cands as $c) {
                    if ($name === strtolower($c)) {
                        return intval($r['booking_status_id']);
                    }
                }
            }
            return null;
        };
        $reservedId = $findId($stRows, ['Reserved', 'Confirmed', 'Approved']);
        if ($reservedId === null) {
            $reservedId = 2;
        }
        $stmt = $conn->prepare("INSERT INTO tbl_booking_history (booking_id, employee_id, status_id, updated_at) VALUES (:id, :emp, :sid, NOW())");
        $stmt->bindParam(':id', $booking_id);
        $stmt->bindParam(':emp', $employee_id);
        $stmt->bindParam(':sid', $reservedId, PDO::PARAM_INT);
        $result = $stmt->execute();

        echo $result ? 'success' : 'fail';
    }

    function getVacantRoomsByBooking($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $reference_no = $json['reference_no'] ?? null;

        if (!$reference_no) {
            echo json_encode(['error' => 'Missing reference_no']);
            return;
        }

        // Step 1: Get booking ID
        $stmt = $conn->prepare("SELECT booking_id FROM tbl_booking WHERE reference_no = :ref");
        $stmt->bindParam(':ref', $reference_no);
        $stmt->execute();
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            echo json_encode(['error' => 'Booking not found']);
            return;
        }

        $booking_id = $booking['booking_id'];

        // Step 2: Get roomtype(s) and count(s)
        $stmt = $conn->prepare("
        SELECT br.roomtype_id, rt.roomtype_name, COUNT(*) AS room_count
        FROM tbl_booking_room br
        JOIN tbl_roomtype rt ON rt.roomtype_id = br.roomtype_id
        WHERE br.booking_id = :booking_id
        GROUP BY br.roomtype_id
    ");
        $stmt->bindParam(':booking_id', $booking_id);
        $stmt->execute();
        $roomGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Step 3: Get all available rooms
        $data = [];
        foreach ($roomGroups as $group) {
            $stmt = $conn->prepare("
            SELECT r.roomnumber_id, r.roomfloor
            FROM tbl_rooms r
            WHERE r.roomtype_id = :roomtype_id AND r.room_status_id = 3
        ");
            $stmt->bindParam(':roomtype_id', $group['roomtype_id']);
            $stmt->execute();
            $vacant_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data[] = [
                'roomtype_id' => $group['roomtype_id'],
                'roomtype_name' => $group['roomtype_name'],
                'room_count' => $group['room_count'],
                'vacant_rooms' => $vacant_rooms
            ];
        }

        echo json_encode($data);
    }

    function getRooms()
    {
        include "connection.php";
        $sql = "SELECT a.roomnumber_id, b.roomtype_name, c.status_name
                FROM tbl_rooms AS a
                INNER JOIN tbl_roomtype AS b ON b.roomtype_id = a.roomtype_id
                INNER JOIN tbl_status_types AS c ON c.status_id = a.room_status_id
                WHERE a.room_status_id = 3
                ORDER BY a.roomnumber_id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    }

    function chargesMasterList()
    {
        include "connection.php";

        $sql = "
        SELECT 
            c.charges_category_name AS 'Category',
            m.charges_master_id AS 'Charge ID',
            m.charges_master_name AS 'Charge Name',
            m.charges_master_price AS 'Price'
        FROM tbl_charges_master m
        JOIN tbl_charges_category c ON m.charges_category_id = c.charges_category_id
        ORDER BY c.charges_category_name, m.charges_master_name;
    ";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    }

    function bookingChargesList()
    {
        include "connection.php";

        $sql = "
        SELECT 
            bc.booking_charges_id AS 'Charge ID',
            bc.booking_room_id AS 'Room Booking ID',
            cc.charges_category_name AS 'Category',
            cm.charges_master_name AS 'Charge Name',
            bc.booking_charges_price AS 'Price',
            bc.booking_charges_quantity AS 'Quantity',
            (bc.booking_charges_price * bc.booking_charges_quantity) AS 'Total Amount'
        FROM tbl_booking_charges bc
        JOIN tbl_charges_master cm ON bc.charges_master_id = cm.charges_master_id
        JOIN tbl_charges_category cc ON cm.charges_category_id = cc.charges_category_id
        ORDER BY bc.booking_charges_id;
    ";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    }

    function addChargesAmenities()
    {
        include "connection.php";

        // Check if JSON exists in POST
        if (!isset($_POST['json'])) {
            echo json_encode(['status' => 'error', 'message' => 'No data sent']);
            return;
        }

        // Decode the incoming JSON
        $json = json_decode($_POST['json'], true);

        if (!isset($json['charges_category_id'], $json['charges_master_name'], $json['charges_master_price'])) {
            echo json_encode(['status' => 'error', 'message' => 'Incomplete data']);
            return;
        }

        $categoryId = $json['charges_category_id'];
        $amenityName = $json['charges_master_name'];
        $price = $json['charges_master_price'];

        try {
            $restricted = isset($json['charge_name_isRestricted']) ? intval($json['charge_name_isRestricted']) : 0;
            $description = $json['charges_master_description'] ?? null;
            $stmt = $conn->prepare("INSERT INTO tbl_charges_master (charges_category_id, charges_master_name, charges_master_price, charges_master_description, charge_name_isRestricted) VALUES (:categoryId, :name, :price, :description, :restricted)");
            $stmt->bindParam(':categoryId', $categoryId);
            $stmt->bindParam(':name', $amenityName);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':restricted', $restricted);
            $success = $stmt->execute();

            echo json_encode($success ? 'success' : 'fail');
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    function getChargesCategory()
    {
        include "connection.php";

        try {
            $sql = "SELECT charges_category_id, charges_category_name FROM tbl_charges_category ORDER BY charges_category_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($result);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    function saveAmenitiesCharges()
    {
        include "connection.php";

        if (!isset($_POST['json'])) {
            echo json_encode(['status' => 'error', 'message' => 'No data sent']);
            return;
        }

        $json = json_decode($_POST['json'], true);

        if (!isset($json['items']) || !is_array($json['items'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data format']);
            return;
        }

        try {
            $conn->beginTransaction();

            foreach ($json['items'] as $item) {
                if (!isset($item['charges_category_id'], $item['charges_master_name'], $item['charges_master_price'])) {
                    throw new Exception('Missing required fields');
                }

                $description = $item['charges_master_description'] ?? null;
                $restricted = isset($item['charge_name_isRestricted']) ? intval($item['charge_name_isRestricted']) : 0;
                $stmt = $conn->prepare("INSERT INTO tbl_charges_master (charges_category_id, charges_master_name, charges_master_price, charges_master_description, charge_name_isRestricted) VALUES (:categoryId, :name, :price, :description, :restricted)");
                $stmt->bindParam(':categoryId', $item['charges_category_id']);
                $stmt->bindParam(':name', $item['charges_master_name']);
                $stmt->bindParam(':price', $item['charges_master_price']);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':restricted', $restricted);
                $stmt->execute();
            }

            $conn->commit();
            echo 'success';
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    function updateAmenityCharges()
    {
        include "connection.php";

        if (!isset($_POST['json'])) {
            echo json_encode(['status' => 'error', 'message' => 'No data sent']);
            return;
        }

        $json = json_decode($_POST['json'], true);

        if (!isset($json['charges_master_id'], $json['charges_master_name'], $json['charges_master_price'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            return;
        }

        try {
            $description = $json['charges_master_description'] ?? null;
            $restricted = isset($json['charge_name_isRestricted']) ? intval($json['charge_name_isRestricted']) : null;
            $stmt = $conn->prepare("UPDATE tbl_charges_master SET charges_master_name = :name, charges_master_price = :price, charges_master_description = :description, charge_name_isRestricted = COALESCE(:restricted, charge_name_isRestricted) WHERE charges_master_id = :id");
            $stmt->bindParam(':name', $json['charges_master_name']);
            $stmt->bindParam(':price', $json['charges_master_price']);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':restricted', $restricted);
            $stmt->bindParam(':id', $json['charges_master_id']);

            $result = $stmt->execute();

            if ($result && $stmt->rowCount() > 0) {
                echo 'success';
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No records updated']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    function createInvoice($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        // Accept both billing_ids and booking_id; resolve billing_ids from booking_id if needed
        $billing_ids = isset($json["billing_ids"]) && is_array($json["billing_ids"]) ? $json["billing_ids"] : [];
        $booking_id_input = isset($json["booking_id"]) ? intval($json["booking_id"]) : null;
        $employee_id = isset($json["employee_id"]) ? intval($json["employee_id"]) : null;
        $payment_method_id = $json["payment_method_id"] ?? 2; // Default to Cash
        $invoice_status_id = 1; // Always set to Complete for checkout scenarios
        $discount_id = $json["discount_id"] ?? null;
        $vat_rate = $json["vat_rate"] ?? 0.0; // Default 0% VAT
        $downpayment = $json["downpayment"] ?? 0;
        // NEW: Delivery options
        $delivery_mode = isset($json["delivery_mode"]) ? $json["delivery_mode"] : 'both'; // 'email'|'pdf'|'both'
        $email_to = isset($json["email_to"]) ? $json["email_to"] : null;

        // Validate employee early (needed for potential billing creation)
        if (empty($employee_id) || $employee_id <= 0) {
            echo json_encode(["success" => false, "message" => "Missing or invalid employee_id"]);
            return;
        }
        $empStmt = $conn->prepare("SELECT employee_status FROM tbl_employee WHERE employee_id = :employee_id");
        $empStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
        $empStmt->execute();
        $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
        if (!$empRow) {
            echo json_encode(["success" => false, "message" => "Employee not found"]);
            return;
        }
        $status = $empRow["employee_status"];
        if ($status === 'Inactive' || $status === 'Disabled' || $status === '0' || $status === 0) {
            echo json_encode(["success" => false, "message" => "Employee is not active"]);
            return;
        }

        // Resolve billing_ids using booking_id if none provided
        if (empty($billing_ids) && !empty($booking_id_input)) {
            try {
                $fetchBills = $conn->prepare("SELECT billing_id FROM tbl_billing WHERE booking_id = :booking_id ORDER BY billing_id ASC");
                $fetchBills->bindParam(':booking_id', $booking_id_input, PDO::PARAM_INT);
                $fetchBills->execute();
                $rows = $fetchBills->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $billing_ids[] = intval($r['billing_id']);
                }
            } catch (Exception $_) {
            }

            // If still none, create a comprehensive billing record now
            if (empty($billing_ids)) {
                try {
                    $bdStmt = $conn->prepare("SELECT booking_payment AS booking_downpayment FROM tbl_booking WHERE booking_id = :booking_id");
                    $bdStmt->bindParam(':booking_id', $booking_id_input, PDO::PARAM_INT);
                    $bdStmt->execute();
                    $bdRow = $bdStmt->fetch(PDO::FETCH_ASSOC);
                    $booking_downpayment = $bdRow ? intval($bdRow['booking_downpayment']) : 0;

                    $breakdown = $this->calculateComprehensiveBillingInternal($conn, $booking_id_input, $discount_id, $vat_rate, $booking_downpayment);
                    if (!empty($breakdown) && isset($breakdown['success']) && $breakdown['success']) {
                        $invoice_number = 'BILL' . date('YmdHis') . rand(100, 999);
                        $ins = $conn->prepare("
                            INSERT INTO tbl_billing (
                                booking_id, employee_id, payment_method_id, discounts_id,
                                billing_dateandtime, billing_invoice_number, billing_downpayment, 
                                billing_vat, billing_total_amount, billing_balance
                            ) VALUES (
                                :booking_id, :employee_id, :payment_method_id, :discount_id,
                                NOW(), :invoice_number, :downpayment, 
                                :vat_amount, :total_amount, :balance
                            )
                        ");
                        $vat_amount = 0;
                        $ins->bindParam(':booking_id', $booking_id_input, PDO::PARAM_INT);
                        $ins->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
                        $ins->bindParam(':payment_method_id', $payment_method_id, PDO::PARAM_INT);
                        $ins->bindParam(':discount_id', $discount_id);
                        $ins->bindParam(':invoice_number', $invoice_number);
                        $ins->bindParam(':downpayment', $booking_downpayment);
                        $ins->bindParam(':vat_amount', $vat_amount);
                        $ins->bindParam(':total_amount', $breakdown['final_total']);
                        $ins->bindParam(':balance', $breakdown['balance']);
                        if ($ins->execute()) {
                            $billing_ids[] = intval($conn->lastInsertId());
                        }
                    }
                } catch (Exception $_) {
                }
            }
        }

        // Final safeguard
        if (empty($billing_ids)) {
            echo json_encode(["success" => false, "message" => "Missing required field: billing_ids or booking_id"]);
            return;
        }

        $invoice_date = date("Y-m-d");
        $invoice_time = date("H:i:s");
        $results = [];

        try {
            $conn->beginTransaction();

            foreach ($billing_ids as $billing_id) {
                // 1. Get the booking_id and booking downpayment linked to this billing_id
                $stmt = $conn->prepare("
                SELECT b.booking_id, b.booking_payment AS booking_downpayment 
                FROM tbl_billing bi 
                JOIN tbl_booking b ON bi.booking_id = b.booking_id 
                WHERE bi.billing_id = :billing_id
            ");
                $stmt->bindParam(':billing_id', $billing_id);
                $stmt->execute();
                $billingRow = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$billingRow) {
                    $results[] = ["billing_id" => $billing_id, "status" => "error", "message" => "Billing not found"];
                    continue;
                }

                $booking_id = $billingRow["booking_id"];
                // Use booking's downpayment if not manually specified, otherwise use the provided downpayment
                $actual_downpayment = $downpayment > 0 ? $downpayment : ($billingRow["booking_downpayment"] ?? 0);

                // 2. Calculate comprehensive billing breakdown
                $billingBreakdown = $this->calculateComprehensiveBillingInternal($conn, $booking_id, $discount_id, $vat_rate, $actual_downpayment);

                if (!$billingBreakdown["success"]) {
                    $results[] = ["billing_id" => $billing_id, "status" => "error", "message" => $billingBreakdown["message"]];
                    continue;
                }

                // 3. Update billing with comprehensive totals
                $updateBilling = $conn->prepare("
            UPDATE tbl_billing 
                    SET billing_total_amount = :total, 
                        billing_balance = :balance,
                        billing_downpayment = :downpayment,
                        billing_vat = :vat,
                        discounts_id = :discount_id
            WHERE billing_id = :billing_id
        ");
                $updateBilling->bindParam(':total', $billingBreakdown["final_total"]);
                $updateBilling->bindParam(':balance', $billingBreakdown["balance"]);
                $updateBilling->bindParam(':downpayment', $actual_downpayment);
                $updateBilling->bindParam(':vat', $billingBreakdown["vat_amount"]);
                $updateBilling->bindParam(':discount_id', $discount_id);
                $updateBilling->bindParam(':billing_id', $billing_id);
                $updateBilling->execute();

                // 4. Create invoice
                $insert = $conn->prepare("
            INSERT INTO tbl_invoice (
                billing_id, employee_id, payment_method_id,
                invoice_date, invoice_time, invoice_total_amount, invoice_status_id
            ) VALUES (
                :billing_id, :employee_id, :payment_method_id,
                :invoice_date, :invoice_time, :invoice_total_amount, :invoice_status_id
            )
        ");

                $insert->bindParam(':billing_id', $billing_id);
                $insert->bindParam(':employee_id', $employee_id);
                $insert->bindParam(':payment_method_id', $payment_method_id);
                $insert->bindParam(':invoice_date', $invoice_date);
                $insert->bindParam(':invoice_time', $invoice_time);
                $insert->bindParam(':invoice_total_amount', $billingBreakdown["final_total"]);
                $insert->bindParam(':invoice_status_id', $invoice_status_id);
                $insert->execute();

                $invoice_id = $conn->lastInsertId();

                // 5. Log billing activity
                $this->logBillingActivity($conn, $billing_id, $invoice_id, $employee_id, "INVOICE_CREATED", $billingBreakdown);

                // Build detailed charge lines for email/PDF
                $roomQuery = $conn->prepare("\n                    SELECT \n                        rt.roomtype_name as charge_name,\n                        r.roomnumber_id as room_number,\n                        rt.roomtype_price as unit_price,\n                        GREATEST(DATEDIFF(b.booking_checkout_dateandtime, b.booking_checkin_dateandtime), 1) as quantity,\n                        (rt.roomtype_price * GREATEST(DATEDIFF(b.booking_checkout_dateandtime, b.booking_checkin_dateandtime), 1)) as total_amount\n                    FROM tbl_booking_room br\n                    JOIN tbl_booking b ON br.booking_id = b.booking_id\n                    JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id\n                    JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id\n                    WHERE br.booking_id = :booking_id\n                ");
                $roomQuery->bindParam(':booking_id', $booking_id);
                $roomQuery->execute();
                $roomCharges = $roomQuery->fetchAll(PDO::FETCH_ASSOC);

                $chargesQuery = $conn->prepare("\n                    SELECT \n                        cm.charges_master_name as charge_name,\n                        r.roomnumber_id as room_number,\n                        bc.booking_charges_price as unit_price,\n                        bc.booking_charges_quantity as quantity,\n                        (bc.booking_charges_price * bc.booking_charges_quantity) as total_amount\n                    FROM tbl_booking_charges bc\n                    JOIN tbl_booking_room br ON bc.booking_room_id = br.booking_room_id\n                    JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id\n                    JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id\n                    JOIN tbl_charges_master cm ON bc.charges_master_id = cm.charges_master_id\n                    WHERE br.booking_id = :booking_id\n                    AND bc.booking_charge_status = 2\n                ");
                $chargesQuery->bindParam(':booking_id', $booking_id);
                $chargesQuery->execute();
                $additionalCharges = $chargesQuery->fetchAll(PDO::FETCH_ASSOC);

                // Render invoice HTML
                $customerStmt = $conn->prepare("SELECT b.reference_no, b.booking_checkout_dateandtime, CONCAT(c.customers_fname,' ',c.customers_lname) AS customer_fullname, c.customers_email FROM tbl_booking b LEFT JOIN tbl_customers c ON b.customers_id = c.customers_id WHERE b.booking_id = :booking_id");
                $customerStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
                $customerStmt->execute();
                $customerRow = $customerStmt->fetch(PDO::FETCH_ASSOC);
                // Prefer override from request; fall back to stored customer email
                $recipientEmail = null;
                if (!empty($email_to)) {
                    $recipientEmail = trim($email_to);
                }
                if (!$recipientEmail) {
                    $recipientEmail = $customerRow['customers_email'] ?? null;
                }

                // Fetch payment method name for display
                $pmStmt = $conn->prepare("SELECT payment_method_name FROM tbl_payment_method WHERE payment_method_id = :pmid");
                $pmStmt->bindParam(':pmid', $payment_method_id, PDO::PARAM_INT);
                $pmStmt->execute();
                $pmRow = $pmStmt->fetch(PDO::FETCH_ASSOC);
                $paymentMethodName = $pmRow['payment_method_name'] ?? 'Cash';

                $issueDate = date('m/d/Y');
                $dueDateRaw = $customerRow['booking_checkout_dateandtime'] ?? null;
                $dueDateFmt = $dueDateRaw ? date('m/d/Y', strtotime($dueDateRaw)) : $issueDate;
                $rows = '';
                foreach ($roomCharges as $row) {
                    $rows .= '<tr><td>Room — ' . htmlspecialchars($row['charge_name']) . ' (Room ' . htmlspecialchars($row['room_number']) . ')</td><td>&#8369; ' . number_format($row['unit_price'], 2) . '</td><td>' . intval($row['quantity']) . '</td><td>&#8369; ' . number_format($row['total_amount'], 2) . '</td></tr>';
                }
                foreach ($additionalCharges as $row) {
                    $rows .= '<tr><td>' . htmlspecialchars($row['charge_name']) . '</td><td>&#8369; ' . number_format($row['unit_price'], 2) . '</td><td>' . intval($row['quantity']) . '</td><td>&#8369; ' . number_format($row['total_amount'], 2) . '</td></tr>';
                }
                $subtotalN = ($billingBreakdown['room_total'] ?? 0) + ($billingBreakdown['charge_total'] ?? 0);
                $subtotal = number_format($subtotalN, 2);
                $roomTotalFmt = number_format($billingBreakdown['room_total'] ?? 0, 2);
                $vatFmt = number_format($billingBreakdown['vat_amount'] ?? 0, 2);
                $finalTotalFmt = number_format($billingBreakdown['final_total'] ?? $subtotalN, 2);
                $depositFmt = number_format($billingBreakdown['downpayment'] ?? 0, 2);
                $balanceFmt = number_format(($billingBreakdown['final_total'] ?? $subtotalN) - ($billingBreakdown['downpayment'] ?? 0), 2);

                $discountAmount = $billingBreakdown['discount_amount'] ?? 0;
                $discountFmt = number_format($discountAmount, 2);
                $discountLeft = $discountAmount > 0 ? '<div class=\"srow\"><span class=\"label\">Discount</span></div>' : '';
                $discountRight = $discountAmount > 0 ? '<div class=\"srow amount\">(&#8369; ' . $discountFmt . ')</div>' : '';

                $refNo = $customerRow['reference_no'] ?? '';

                $invoiceHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
                        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color:#2b2b2b; background:#ffffff; line-height:1.5; }
                        .wrapper { max-width:860px; margin:0 auto; padding:40px; }
                        .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
                        .logo { font-size:12px; color:#6b7280; letter-spacing:1px; text-transform:uppercase; }
                        .invno { font-size:12px; color:#6b7280; text-transform:uppercase; }
                        .doc-title { font-size:48px; font-weight:800; letter-spacing:2px; margin:8px 0 20px; }
                        .meta-line { font-size:13px; margin-bottom:16px; }
                        .meta-line .label { font-weight:700; margin-right:8px; }
                        .party { display:grid; grid-template-columns:1fr 1fr; gap:28px; margin-bottom:24px; }
                        .party .h6 { font-size:12px; font-weight:700; color:#111827; text-transform:uppercase; margin-bottom:6px; }
                        .party .small { font-size:13px; color:#374151; line-height:1.6; }
                        .table { width:100%; border-collapse:collapse; margin-top:10px; }
                        .table thead th { background:#e5e7eb; color:#111827; padding:12px; font-size:12px; text-transform:uppercase; letter-spacing:.5px; text-align:left; }
                        .table thead th:nth-child(2), .table thead th:nth-child(3), .table thead th:nth-child(4) { text-align:right; }
                        .table tbody td { border-bottom:1px solid #e5e7eb; padding:12px; font-size:13px; }
                        .table tbody td:nth-child(2), .table tbody td:nth-child(3), .table tbody td:nth-child(4){ text-align:right; }
                        .table tfoot td { padding:12px; font-size:13px; }
                        .table tfoot tr.total-row td { border-top:2px solid #d1d5db; font-weight:bold; }
                        .footer-notes { margin-top:24px; font-size:13px; }
                        .footer-notes .label { font-weight:700; margin-right:8px; }
                        </style></head><body>
                        <div class="wrapper">
                        <div class="header">
                            <div class="logo">YOUR LOGO</div>
                            <div class="invno">NO. ' . str_pad($invoice_id, 6, '0', STR_PAD_LEFT) . '</div>
                        </div>

                        <div class="doc-title">INVOICE</div>

                        <div class="meta-line"><span class="label">Date:</span> ' . $issueDate . '</div>

                        <div class="party">
                            <div>
                            <div class="h6">Billed to:</div>
                            <div class="small">' . htmlspecialchars($customerRow['customer_fullname'] ?? 'Guest') . '</div>
                            <div class="small">' . htmlspecialchars($customerRow['customers_email'] ?? '-') . '</div>
                            </div>
                            <div>
                            <div class="h6">From:</div>
                            <div class="small">Demiren Hotel & Restaurant</div>
                            <div class="small">123 Anywhere St., Any City</div>
                            <div class="small">hello@demiren.local</div>
                            </div>
                        </div>

                        <table class="table">
                            <thead>
                            <tr><th>Item</th><th>Quantity</th><th>Price</th><th>Amount</th></tr>
                            </thead>
                            <tbody>' . $rows . '</tbody>
                            <tfoot>
                            <tr><td colspan="3" style="text-align:right">Subtotal</td><td style="text-align:right">&#8369; ' . $subtotal . '</td></tr>
                            <tr><td colspan="3" style="text-align:right">VAT (12%)</td><td style="text-align:right">&#8369; ' . $vatFmt . '</td></tr>
                            ' . ($discountAmount > 0 ? '<tr><td colspan="3" style="text-align:right">Discount</td><td style="text-align:right">(&#8369; ' . $discountFmt . ')</td></tr>' : '') . '
                            <tr class="total-row"><td colspan="3" style="text-align:right">Total</td><td style="text-align:right">&#8369; ' . $finalTotalFmt . '</td></tr>
                            </tfoot>
                        </table>

                        <div class="footer-notes">
                            <div><span class="label">Payment method:</span> ' . htmlspecialchars($paymentMethodName) . '</div>
                            <div><span class="label">Note:</span> Thank you for choosing us!</div>
                            <div>We value your opinion — please share your feedback: <a href="http://localhost:3000/feedback" target="_blank" rel="noopener noreferrer">http://localhost:3000/feedback</a></div>
                        </div>
                        </div>
                        </body></html>';




                // Prepare email status; combined email will be sent after PDF generation
                $email_status = null;

                // Generate PDF if requested or needed for email attachment
                $pdf_url = null;
                $pdfPath = null;
                if (in_array($delivery_mode, ['pdf', 'both', 'email'])) {
                    try {
                        // Try autoload from local vendor, project root vendor, then packaged dompdf
                        $localAutoload = __DIR__ . '/vendor/autoload.php';
                        $rootAutoload = dirname(__DIR__) . '/vendor/autoload.php';
                        $dompdfAutoload = __DIR__ . '/dompdf/autoload.inc.php';
                        if (file_exists($localAutoload)) {
                            require_once $localAutoload;
                        }
                        if (!class_exists('\\Dompdf\\Dompdf') && file_exists($rootAutoload)) {
                            require_once $rootAutoload;
                        }
                        if (!class_exists('\\Dompdf\\Dompdf') && file_exists($dompdfAutoload)) {
                            require_once $dompdfAutoload;
                        }
                        if (!class_exists('\\Dompdf\\Dompdf')) {
                            throw new \Exception('Dompdf autoloader not found');
                        }

                        // Initialize Dompdf options (compatible across versions)
                        $options = new \Dompdf\Options();
                        $options->set('isRemoteEnabled', true);
                        $options->set('isHtml5ParserEnabled', true);
                        // Use generic setter for chroot to support older Dompdf versions
                        $options->set('chroot', __DIR__);
                        // Ensure a Unicode-capable default font
                        $options->set('defaultFont', 'DejaVu Sans');

                        $dompdf = new \Dompdf\Dompdf($options);
                        $dompdf->loadHtml($invoiceHtml);
                        $dompdf->setPaper('A4', 'portrait');
                        $dompdf->render();
                        $pdfDir = __DIR__ . DIRECTORY_SEPARATOR . 'invoices';
                        if (!is_dir($pdfDir)) {
                            @mkdir($pdfDir, 0777, true);
                        }
                        $pdfName = 'invoice_' . $booking_id . '_' . $invoice_id . '_' . date('YmdHis') . '.pdf';
                        $pdfPath = $pdfDir . DIRECTORY_SEPARATOR . $pdfName;
                        $pdfContent = $dompdf->output();
                        if ($pdfContent === false || strlen($pdfContent) === 0) {
                            @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'email_debug.log', date('c') . " dompdf output empty for booking {$booking_id}, invoice {$invoice_id}\n", FILE_APPEND);
                            $pdf_url = null;
                        } else {
                            $bytes = @file_put_contents($pdfPath, $pdfContent);
                            if ($bytes !== false) {
                                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                $scriptDir = rtrim(str_replace('\\\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
                                $baseUrl = $scheme . '://' . $host . $scriptDir . '/';
                                $pdf_url = $baseUrl . 'invoices/' . $pdfName;
                            } else {
                                @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'email_debug.log', date('c') . " file_put_contents failed for {$pdfPath}\n", FILE_APPEND);
                                $pdf_url = null;
                            }
                        }
                    } catch (\Throwable $e) {
                        @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'email_debug.log', date('c') . " dompdf exception: " . $e->getMessage() . "\n", FILE_APPEND);
                        $pdf_url = null;
                    }
                }

                // Send a Thank You email with the invoice PDF attached, if enabled
                $thank_you_status = null;
                if (in_array($delivery_mode, ['email', 'both']) && $recipientEmail) {
                    try {
                        // Load PHPMailer via Composer autoload
                        $localAutoload = __DIR__ . '/vendor/autoload.php';
                        if (file_exists($localAutoload)) {
                            require_once $localAutoload;
                        }
                        // Use fully-qualified class names to avoid namespace issues
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        // Match SMTP settings to SendEmail helper
                        $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
                        $mail->Debugoutput = function ($str, $level) {
                            @file_put_contents(__DIR__ . '/email_debug.log', '[' . date('c') . "] SMTP Debug (level {$level}): " . $str . "\n", FILE_APPEND);
                        };
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'ikversoza@gmail.com';
                        $mail->Password   = 'izpfukocrjngaogg';
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        $mail->Timeout    = 10;
                        @ini_set('default_socket_timeout', 10);
                        $mail->CharSet    = 'UTF-8';

                        $mail->setFrom('ikversoza@gmail.com', 'Demiren Hotel');
                        $mail->addAddress($recipientEmail, $customerRow['customer_fullname'] ?? 'Guest');
                        $mail->isHTML(true);
                        $mail->Subject = 'Demiren Hotel — Invoice #' . $invoice_id;
                        $guestName = htmlspecialchars($customerRow['customer_fullname'] ?? 'Guest');
                        $hotelName = 'Demiren Hotel & Restaurant';
                        $cityName = 'Iligan City';
                        $senderName = 'Demiren Hotel Team';
                        $senderPosition = 'Front Desk';
                        $emailBody = '<div style="font-family:Arial,Helvetica,sans-serif;color:#111827;line-height:1.6">'
                            . '<p>Dear ' . $guestName . ',</p>'
                            . '<p>Warm greetings from ' . $hotelName . '!</p>'
                            . '<p>We would like to sincerely thank you for choosing to stay with us during your recent visit to ' . $cityName . '. It was truly our pleasure to have you as our guest.</p>'
                            . '<p>We hope you had a comfortable and enjoyable experience — from our rooms to our amenities and service. Your satisfaction is very important to us, and we would love to hear your feedback to help us serve you even better next time.</p>'
                            . '<p>If your travels bring you back to ' . $cityName . ', we’d be delighted to welcome you again. Please don’t hesitate to contact us directly for any future reservations or special requests.</p>'
                            . '<p>Thank you once again for staying with ' . $hotelName . '. We wish you safe travels and look forward to seeing you soon!</p>'
                            . '<p>Warm regards,<br>' . $senderName . '<br>' . $senderPosition . '<br>' . $hotelName . '</p>'
                            . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0" />'
                            . '<p><strong>Customer Information</strong><br>'
                            . 'Name: ' . $guestName . '<br>'
                            . 'Email: ' . htmlspecialchars($customerRow['customers_email'] ?? '-') . '<br>'
                            . 'Reference No: ' . htmlspecialchars($refNo) . '</p>'
                            . '<p>Your invoice is attached as a PDF.</p>'
                            . '<p style="margin-top:8px">We value your feedback. Please share it here: '
                            . '<a href="http://localhost:3000/feedback" target="_blank" rel="noopener noreferrer">http://localhost:3000/feedback</a>'
                            . '</p>'
                            . '</div>';
                        $mail->Body    = $emailBody;
                        $mail->AltBody = 'Dear ' . ($customerRow['customer_fullname'] ?? 'Guest') . "\n\n"
                            . 'Warm greetings from ' . $hotelName . "!\n\n"
                            . 'We would like to sincerely thank you for choosing to stay with us during your recent visit to ' . $cityName . '. It was truly our pleasure to have you as our guest.' . "\n\n"
                            . 'We hope you had a comfortable and enjoyable experience — from our rooms to our amenities and service. Your satisfaction is very important to us, and we would love to hear your feedback to help us serve you even better next time.' . "\n\n"
                            . 'If your travels bring you back to ' . $cityName . ', we’d be delighted to welcome you again. Please don’t hesitate to contact us directly for any future reservations or special requests.' . "\n\n"
                            . 'Thank you once again for staying with ' . $hotelName . '. We wish you safe travels and look forward to seeing you soon!' . "\n\n"
                            . 'Warm regards,' . "\n" . $senderName . "\n" . $senderPosition . "\n" . $hotelName . "\n\n"
                            . 'Customer Information' . "\n"
                            . 'Name: ' . ($customerRow['customer_fullname'] ?? 'Guest') . "\n"
                            . 'Email: ' . ($customerRow['customers_email'] ?? '-') . "\n"
                            . 'Reference No: ' . $refNo . "\n\n"
                            . 'Your invoice is attached as a PDF.' . "\n\n"
                            . 'We value your feedback. Share it here: http://localhost:3000/feedback';
                        if ($pdfPath && file_exists($pdfPath)) {
                            $mail->addAttachment($pdfPath, 'invoice.pdf');
                        }
                        $mail->send();
                        $email_status = 'sent';
                        $thank_you_status = $email_status; // backward compatibility
                    } catch (\Throwable $e) {
                        @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'email_debug.log', date('c') . " combined email exception: " . $e->getMessage() . "\n", FILE_APPEND);
                        $email_status = 'failed';
                        $thank_you_status = $email_status; // backward compatibility
                    }
                }

                // 6. Update room status to Vacant (3) for check-out
                $roomUpdateStmt = $conn->prepare("
                    UPDATE tbl_rooms 
                    SET room_status_id = 3 
                    WHERE roomnumber_id IN (
                        SELECT br.roomnumber_id 
                        FROM tbl_booking_room br 
                        WHERE br.booking_id = :booking_id
                    )
                ");
                $roomUpdateStmt->bindParam(':booking_id', $booking_id);
                $roomUpdateStmt->execute();

                // 7. Update booking status to Checked-Out (6) in booking history
                $statusUpdateStmt = $conn->prepare("
                    INSERT INTO tbl_booking_history (booking_id, employee_id, status_id, updated_at) 
                    VALUES (:booking_id, :employee_id, 6, NOW())
                ");
                $statusUpdateStmt->bindParam(':booking_id', $booking_id);
                $statusUpdateStmt->bindParam(':employee_id', $employee_id);
                $statusUpdateStmt->execute();

                $results[] = [
                    "billing_id" => $billing_id,
                    "invoice_id" => $invoice_id,
                    "status" => "success",
                    "breakdown" => $billingBreakdown,
                    "email_status" => $email_status,
                    "pdf_url" => $pdf_url,
                    "thank_you_status" => $thank_you_status
                ];
            }

            $conn->commit();
            echo json_encode([
                "success" => true,
                "message" => "Invoices created successfully with comprehensive billing validation.",
                "results" => $results
            ]);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode([
                "success" => false,
                "message" => "Error creating invoices: " . $e->getMessage()
            ]);
        }
    }

    function calculateComprehensiveBillingInternal($conn, $booking_id, $discount_id = null, $vat_rate = 0.0, $downpayment = 0)
    {
        try {
            // 1. Calculate room charges (fixed the room price query)
            $roomQuery = $conn->prepare("
                SELECT 
                    SUM(rt.roomtype_price) AS room_total,
                    COUNT(br.booking_room_id) AS room_count
                FROM tbl_booking_room br
                JOIN tbl_roomtype rt ON br.roomtype_id = rt.roomtype_id
                WHERE br.booking_id = :booking_id
            ");
            $roomQuery->bindParam(':booking_id', $booking_id);
            $roomQuery->execute();
            $roomData = $roomQuery->fetch(PDO::FETCH_ASSOC);
            $room_total = $roomData['room_total'] ?: 0;
            // Recompute room_total to account for number of nights per room
            try {
                $roomQueryNights = $conn->prepare("
                    SELECT 
                        SUM(rt.roomtype_price * GREATEST(DATEDIFF(b.booking_checkout_dateandtime, b.booking_checkin_dateandtime), 1)) AS room_total
                    FROM tbl_booking_room br
                    JOIN tbl_booking b ON br.booking_id = b.booking_id
                    JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id
                    JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id
                    WHERE br.booking_id = :booking_id
                ");
                $roomQueryNights->bindParam(':booking_id', $booking_id);
                $roomQueryNights->execute();
                $room_total = $roomQueryNights->fetchColumn() ?: $room_total;
            } catch (Exception $_) {
            }

            // 2. Calculate additional charges (amenities, services, etc.)
            $chargesQuery = $conn->prepare("
                SELECT 
                    SUM(bc.booking_charges_price * bc.booking_charges_quantity) AS charge_total,
                    COUNT(bc.booking_charges_id) AS charge_count
                FROM tbl_booking_charges bc
                JOIN tbl_booking_room br ON bc.booking_room_id = br.booking_room_id
                WHERE br.booking_id = :booking_id
            ");
            $chargesQuery->bindParam(':booking_id', $booking_id);
            $chargesQuery->execute();
            $chargeData = $chargesQuery->fetch(PDO::FETCH_ASSOC);
            $charge_total = $chargeData['charge_total'] ?: 0;

            // 3. Calculate subtotal
            $subtotal = $room_total + $charge_total;

            // 4. Apply discount if provided
            $discount_amount = 0;
            if ($discount_id) {
                $discountQuery = $conn->prepare("
                    SELECT discounts_percentage, discounts_amount 
                    FROM tbl_discounts 
                    WHERE discounts_id = :discount_id
                ");
                $discountQuery->bindParam(':discount_id', $discount_id);
                $discountQuery->execute();
                $discount = $discountQuery->fetch(PDO::FETCH_ASSOC);

                if ($discount) {
                    if ($discount['discounts_percentage']) {
                        $discount_amount = $subtotal * ($discount['discounts_percentage'] / 100);
                    } else {
                        $discount_amount = $discount['discounts_amount'];
                    }
                }
            }

            // 5. Calculate amount after discount
            $amount_after_discount = $subtotal - $discount_amount;

            // 6. Calculate VAT (if provided) and final total
            $vat_amount = 0;
            if ($vat_rate && $vat_rate > 0) {
                $vat_amount = $amount_after_discount * ($vat_rate / 100.0);
            }
            $final_total = $amount_after_discount + $vat_amount;

            // 7. Calculate balance after downpayment
            $balance = $final_total - $downpayment;

            return [
                "success" => true,
                "room_total" => $room_total,
                "charge_total" => $charge_total,
                "subtotal" => $subtotal,
                "discount_amount" => $discount_amount,
                "amount_after_discount" => $amount_after_discount,
                "vat_amount" => $vat_amount,
                "final_total" => $final_total,
                "downpayment" => $downpayment,
                "balance" => $balance,
                "room_count" => $roomData['room_count'],
                "charge_count" => $chargeData['charge_count']
            ];
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Error calculating billing: " . $e->getMessage()
            ];
        }
    }

    function logBillingActivity($conn, $billing_id, $invoice_id, $employee_id, $activity_type, $data = null)
    {
        try {
            // Check if the table exists before trying to insert
            $checkTable = $conn->prepare("SHOW TABLES LIKE 'tbl_billing_activity_log'");
            $checkTable->execute();

            if ($checkTable->rowCount() > 0) {
                $stmt = $conn->prepare("
                    INSERT INTO tbl_billing_activity_log (
                        billing_id, invoice_id, employee_id, activity_type, 
                        activity_data, created_at
                    ) VALUES (
                        :billing_id, :invoice_id, :employee_id, :activity_type,
                        :activity_data, NOW()
                    )
                ");
                $stmt->bindParam(':billing_id', $billing_id);
                $stmt->bindParam(':invoice_id', $invoice_id);
                $stmt->bindParam(':employee_id', $employee_id);
                $stmt->bindParam(':activity_type', $activity_type);
                $stmt->bindParam(':activity_data', json_encode($data));
                $stmt->execute();
            }
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to log billing activity: " . $e->getMessage());
        }
    }

    function validateBillingCompleteness($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $booking_id = $json["booking_id"];

        try {
            // Check if there are any charges that need billing processing
            // This should check for charges that exist but aren't properly included in billing calculations
            $pendingChargesQuery = $conn->prepare("
                SELECT COUNT(*) as pending_count
                FROM tbl_booking_charges bc
                JOIN tbl_booking_room br ON bc.booking_room_id = br.booking_room_id
                WHERE br.booking_id = :booking_id 
                AND bc.booking_charge_status = 1
            ");
            $pendingChargesQuery->bindParam(':booking_id', $booking_id);
            $pendingChargesQuery->execute();
            $pendingCount = $pendingChargesQuery->fetchColumn();

            // Check if room charges are properly calculated
            $roomValidationQuery = $conn->prepare("
                SELECT COUNT(*) as room_count
                FROM tbl_booking_room br
                WHERE br.booking_id = :booking_id AND br.roomnumber_id IS NOT NULL
            ");
            $roomValidationQuery->bindParam(':booking_id', $booking_id);
            $roomValidationQuery->execute();
            $roomCount = $roomValidationQuery->fetchColumn();

            $result = [
                "success" => true,
                "pending_charges" => $pendingCount,
                "assigned_rooms" => $roomCount,
                "is_complete" => $pendingCount == 0, // Only check for truly pending charges (status = 1)
                "message" => $pendingCount > 0 ?
                    "There are {$pendingCount} charges with pending status that need to be approved before billing." : ($roomCount > 0 ? "Billing validation complete. All charges are ready for invoice creation." : "Billing validation complete. Note: No rooms assigned yet.")
            ];

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => "Error validating billing: " . $e->getMessage()
            ]);
        }
    }

    function calculateComprehensiveBilling($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $booking_id = $json["booking_id"];
        $discount_id = $json["discount_id"] ?? null;
        $vat_rate = $json["vat_rate"] ?? 0.0;
        $downpayment = $json["downpayment"] ?? 0;

        // If no downpayment provided, get it from the booking
        if ($downpayment == 0) {
            $stmt = $conn->prepare("SELECT booking_payment AS booking_downpayment FROM tbl_booking WHERE booking_id = :booking_id");
            $stmt->bindParam(':booking_id', $booking_id);
            $stmt->execute();
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            $downpayment = $booking ? ($booking["booking_downpayment"] ?? 0) : 0;
        }

        $result = $this->calculateComprehensiveBillingInternal($conn, $booking_id, $discount_id, $vat_rate, $downpayment);
        echo json_encode($result);
    }

    function createBillingRecord($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $booking_id = $json["booking_id"];
        $employee_id = isset($json["employee_id"]) ? intval($json["employee_id"]) : null;
        $payment_method_id = $json["payment_method_id"] ?? 2; // Default to Cash
        $discount_id = $json["discount_id"] ?? null;
        $vat_rate = $json["vat_rate"] ?? 0.12; // Default 12% VAT

        // Safeguards: require valid employee_id and booking_id; validate employee exists and is active
        if (empty($booking_id)) {
            echo json_encode(["success" => false, "message" => "Missing required field: booking_id"]);
            return;
        }
        if (empty($employee_id) || $employee_id <= 0) {
            echo json_encode(["success" => false, "message" => "Missing or invalid employee_id"]);
            return;
        }
        $empStmt = $conn->prepare("SELECT employee_status FROM tbl_employee WHERE employee_id = :employee_id");
        $empStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
        $empStmt->execute();
        $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
        if (!$empRow) {
            echo json_encode(["success" => false, "message" => "Employee not found"]);
            return;
        }
        $status = $empRow["employee_status"];
        if ($status === 'Inactive' || $status === 'Disabled' || $status === '0' || $status === 0) {
            echo json_encode(["success" => false, "message" => "Employee is not active"]);
            return;
        }

        try {
            // Check if billing already exists
            $checkStmt = $conn->prepare("SELECT billing_id FROM tbl_billing WHERE booking_id = :booking_id");
            $checkStmt->bindParam(':booking_id', $booking_id);
            $checkStmt->execute();

            if ($checkStmt->fetch()) {
                echo json_encode(["success" => true, "message" => "Billing record already exists"]);
                return;
            }

            // 1. Get booking information
            $bookingQuery = $conn->prepare("
                SELECT booking_payment AS booking_downpayment, booking_totalAmount, reference_no 
                FROM tbl_booking 
                WHERE booking_id = :booking_id
            ");
            $bookingQuery->bindParam(':booking_id', $booking_id);
            $bookingQuery->execute();
            $booking = $bookingQuery->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                echo json_encode(["success" => false, "message" => "Booking not found"]);
                return;
            }

            // 2. Calculate room charges
            $roomQuery = $conn->prepare("
                SELECT SUM(rt.roomtype_price * GREATEST(DATEDIFF(b.booking_checkout_dateandtime, b.booking_checkin_dateandtime), 1)) AS room_total
                FROM tbl_booking_room br
                JOIN tbl_booking b ON br.booking_id = b.booking_id
                JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id
                JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id
                WHERE br.booking_id = :booking_id
            ");
            $roomQuery->bindParam(':booking_id', $booking_id);
            $roomQuery->execute();
            $room_total = $roomQuery->fetchColumn() ?: 0;

            // 3. Calculate additional charges (approved charges only)
            $chargesQuery = $conn->prepare("
                SELECT SUM(bc.booking_charges_price * bc.booking_charges_quantity) AS charge_total
                FROM tbl_booking_charges bc
                JOIN tbl_booking_room br ON bc.booking_room_id = br.booking_room_id
                WHERE br.booking_id = :booking_id
                AND bc.booking_charge_status = 2
            ");
            $chargesQuery->bindParam(':booking_id', $booking_id);
            $chargesQuery->execute();
            $charge_total = $chargesQuery->fetchColumn() ?: 0;

            // 4. Calculate subtotal
            $subtotal = $room_total + $charge_total;

            // 5. Apply discount if provided
            $discount_amount = 0;
            if ($discount_id) {
                $discountQuery = $conn->prepare("
                    SELECT discounts_percentage, discounts_amount 
                    FROM tbl_discounts 
                    WHERE discounts_id = :discount_id
                ");
                $discountQuery->bindParam(':discount_id', $discount_id);
                $discountQuery->execute();
                $discount = $discountQuery->fetch(PDO::FETCH_ASSOC);

                if ($discount) {
                    if ($discount['discounts_percentage']) {
                        $discount_amount = $subtotal * ($discount['discounts_percentage'] / 100);
                    } else {
                        $discount_amount = $discount['discounts_amount'];
                    }
                }
            }

            // 6. Calculate amount after discount
            $amount_after_discount = $subtotal - $discount_amount;

            // 7. VAT removed; final total equals amount after discount
            $final_total = $amount_after_discount;
            $vat_amount = 0;

            // 9. Get booking downpayment
            $downpayment = $booking['booking_downpayment'] ?? 0;

            // 10. Calculate balance
            $balance = $final_total - $downpayment;

            // 11. Generate invoice number
            $invoice_number = 'BILL' . date('YmdHis') . rand(100, 999);

            // 12. Create comprehensive billing record
            $stmt = $conn->prepare("
                INSERT INTO tbl_billing (
                    booking_id, employee_id, payment_method_id, discounts_id,
                    billing_dateandtime, billing_invoice_number, billing_downpayment, 
                    billing_vat, billing_total_amount, billing_balance
                ) VALUES (
                    :booking_id, :employee_id, :payment_method_id, :discount_id,
                    NOW(), :invoice_number, :downpayment, 
                    :vat_amount, :total_amount, :balance
                )
            ");

            $stmt->bindParam(':booking_id', $booking_id);
            $stmt->bindParam(':employee_id', $employee_id);
            $stmt->bindParam(':payment_method_id', $payment_method_id);
            $stmt->bindParam(':discount_id', $discount_id);
            $stmt->bindParam(':invoice_number', $invoice_number);
            $stmt->bindParam(':downpayment', $downpayment);
            $stmt->bindParam(':vat_amount', $vat_amount);
            $stmt->bindParam(':total_amount', $final_total);
            $stmt->bindParam(':balance', $balance);

            if ($stmt->execute()) {
                $billing_id = $conn->lastInsertId();

                // Log the billing creation activity
                $activityQuery = $conn->prepare("
                    INSERT INTO tbl_activitylogs (
                        user_type, user_id, user_name, action_type, action_category, 
                        action_description, target_table, target_id, new_values, 
                        status, created_at
                    ) VALUES (
                        'admin', :employee_id, 'System', 'create', 'billing',
                        'Comprehensive billing record created with full calculations',
                        'tbl_billing', :billing_id, :new_values, 'success', NOW()
                    )
                ");

                $new_values = json_encode([
                    'billing_id' => $billing_id,
                    'booking_id' => $booking_id,
                    'total_amount' => $final_total,
                    'downpayment' => $downpayment,
                    'balance' => $balance
                ]);

                $activityQuery->bindParam(':employee_id', $employee_id);
                $activityQuery->bindParam(':billing_id', $billing_id);
                $activityQuery->bindParam(':new_values', $new_values);
                $activityQuery->execute();

                echo json_encode([
                    "success" => true,
                    "message" => "Comprehensive billing record created successfully",
                    "billing_id" => $billing_id,
                    "invoice_number" => $invoice_number,
                    "total_amount" => $final_total,
                    "downpayment" => $downpayment,
                    "balance" => $balance
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Failed to create billing record"]);
            }
        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
    }

    function getBookingBillingId($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $booking_id = $json["booking_id"];

        try {
            $stmt = $conn->prepare("SELECT billing_id FROM tbl_billing WHERE booking_id = :booking_id ORDER BY billing_id DESC LIMIT 1");
            $stmt->bindParam(':booking_id', $booking_id);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                echo json_encode([
                    "success" => true,
                    "billing_id" => $result['billing_id']
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "No billing record found for this booking"
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => "Error: " . $e->getMessage()
            ]);
        }
    }

    function getBookingCharges($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $booking_id = $json["booking_id"];

        try {
            // Get room charges
            $roomQuery = $conn->prepare("
                SELECT 
                    'Room Charges' as charge_type,
                    rt.roomtype_name as charge_name,
                    'Room' as category,
                    r.roomnumber_id as room_number,
                    rt.roomtype_name as room_type,
                    rt.roomtype_price as unit_price,
                    GREATEST(DATEDIFF(b.booking_checkout_dateandtime, b.booking_checkin_dateandtime), 1) as quantity,
                    (rt.roomtype_price * GREATEST(DATEDIFF(b.booking_checkout_dateandtime, b.booking_checkin_dateandtime), 1)) as total_amount,
                    rt.roomtype_description as charges_master_description
                FROM tbl_booking_room br
                JOIN tbl_booking b ON br.booking_id = b.booking_id
                JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id
                JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id
                WHERE br.booking_id = :booking_id
            ");
            $roomQuery->bindParam(':booking_id', $booking_id);
            $roomQuery->execute();
            $roomCharges = $roomQuery->fetchAll(PDO::FETCH_ASSOC);

            // Get additional charges
            $chargesQuery = $conn->prepare("
                SELECT 
                    'Additional Charges' as charge_type,
                    cm.charges_master_name as charge_name,
                    cc.charges_category_name as category,
                    r.roomnumber_id as room_number,
                    rt.roomtype_name as room_type,
                    bc.booking_charges_price as unit_price,
                    bc.booking_charges_quantity as quantity,
                    (bc.booking_charges_price * bc.booking_charges_quantity) as total_amount,
                    cm.charges_master_description as charges_master_description
                FROM tbl_booking_charges bc
                JOIN tbl_booking_room br ON bc.booking_room_id = br.booking_room_id
                JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id
                JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id
                JOIN tbl_charges_master cm ON bc.charges_master_id = cm.charges_master_id
                JOIN tbl_charges_category cc ON cm.charges_category_id = cc.charges_category_id
                WHERE br.booking_id = :booking_id
                AND bc.booking_charge_status = 2
            ");
            $chargesQuery->bindParam(':booking_id', $booking_id);
            $chargesQuery->execute();
            $additionalCharges = $chargesQuery->fetchAll(PDO::FETCH_ASSOC);

            // Combine all charges
            $allCharges = array_merge($roomCharges, $additionalCharges);

            echo json_encode([
                "success" => true,
                "booking_id" => $booking_id,
                "charges" => $allCharges,
                "room_charges_count" => count($roomCharges),
                "additional_charges_count" => count($additionalCharges),
                "total_charges_count" => count($allCharges)
            ]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => "Error fetching charges: " . $e->getMessage()
            ]);
        }
    }

    function addBookingCharge($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $booking_id = $json["booking_id"];
        $charge_name = $json["charge_name"];
        $charge_price = $json["charge_price"];
        $quantity = $json["quantity"] ?? 1;
        $category_id = $json["category_id"] ?? 4; // Default to "Additional Services"
        $employee_id = isset($json["employee_id"]) ? intval($json["employee_id"]) : null;

        if (empty($employee_id) || $employee_id <= 0) {
            echo json_encode(["success" => false, "message" => "Missing or invalid employee_id"]);
            return;
        }
        $empStmt = $conn->prepare("SELECT employee_status FROM tbl_employee WHERE employee_id = :employee_id");
        $empStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
        $empStmt->execute();
        $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
        if (!$empRow || $empRow["employee_status"] == 0 || $empRow["employee_status"] === 'Inactive' || $empRow["employee_status"] === 'Disabled') {
            echo json_encode(["success" => false, "message" => "Employee is not active"]);
            return;
        }

        try {
            // Get the first booking room for this booking
            $roomQuery = $conn->prepare("SELECT booking_room_id FROM tbl_booking_room WHERE booking_id = :booking_id LIMIT 1");
            $roomQuery->bindParam(':booking_id', $booking_id);
            $roomQuery->execute();
            $roomData = $roomQuery->fetch(PDO::FETCH_ASSOC);

            if (!$roomData) {
                echo json_encode(["success" => false, "message" => "No room assigned to this booking"]);
                return;
            }

            $booking_room_id = $roomData['booking_room_id'];

            // Check if charge already exists in master table
            $checkCharge = $conn->prepare("SELECT charges_master_id FROM tbl_charges_master WHERE charges_master_name = :charge_name");
            $checkCharge->bindParam(':charge_name', $charge_name);
            $checkCharge->execute();

            $charges_master_id = null;
            if ($checkCharge->rowCount() > 0) {
                $charges_master_id = $checkCharge->fetchColumn();
            } else {
                // Create new charge in master table
                $createCharge = $conn->prepare("\n                    INSERT INTO tbl_charges_master (charges_category_id, charges_master_name, charges_master_price, charges_master_description, charge_name_isRestricted)\n                    VALUES (:category_id, :charge_name, :charge_price, 'Added by frontdesk', 0)\n                ");
                $createCharge->bindParam(':category_id', $category_id);
                $createCharge->bindParam(':charge_name', $charge_name);
                $createCharge->bindParam(':charge_price', $charge_price);
                $createCharge->execute();
                $charges_master_id = $conn->lastInsertId();
            }

            // Add charge to booking
            $total = intval($charge_price) * intval($quantity);
            $addCharge = $conn->prepare("\n                INSERT INTO tbl_booking_charges (booking_room_id, charges_master_id, booking_charges_price, booking_charges_quantity, booking_charges_total, \n                booking_charge_status, booking_charge_datetime, booking_return_datetime)\n                VALUES (:booking_room_id, :charges_master_id, :charge_price, :quantity, :total, 2, NOW(), NOW())\n            ");
            $addCharge->bindParam(':booking_room_id', $booking_room_id);
            $addCharge->bindParam(':charges_master_id', $charges_master_id);
            $addCharge->bindParam(':charge_price', $charge_price);
            $addCharge->bindParam(':quantity', $quantity);
            $addCharge->bindParam(':total', $total);
            $addCharge->execute();

            echo json_encode([
                "success" => true,
                "message" => "Charge added successfully",
                "charge_id" => $conn->lastInsertId()
            ]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => "Error adding charge: " . $e->getMessage()
            ]);
        }
    }

    function getBookingsWithBillingStatus()
    {
        include "connection.php";

        $query = "
        SELECT DISTINCT
            b.booking_id,
            b.reference_no,
            b.booking_checkin_dateandtime,
            b.booking_checkout_dateandtime,
            CONCAT(c.customers_fname, ' ', c.customers_lname) AS customer_name,
            bi.billing_id,
            i.invoice_id,
            i.invoice_status_id,
            COALESCE(bs.booking_status_name, 'Pending') AS booking_status
        FROM tbl_booking b
        LEFT JOIN tbl_customers c ON b.customers_id = c.customers_id
        LEFT JOIN tbl_billing bi ON b.booking_id = bi.booking_id
        LEFT JOIN tbl_invoice i ON bi.billing_id = i.billing_id
        LEFT JOIN (
            SELECT bh1.booking_id, bs.booking_status_name
            FROM tbl_booking_history bh1
            INNER JOIN (
                SELECT booking_id, MAX(booking_history_id) AS latest_history_id
                FROM tbl_booking_history
                GROUP BY booking_id
            ) latest ON latest.booking_id = bh1.booking_id AND latest.latest_history_id = bh1.booking_history_id
            INNER JOIN tbl_booking_status bs ON bh1.status_id = bs.booking_status_id
        ) bs ON bs.booking_id = b.booking_id
        WHERE (
            bs.booking_status_name = 'Checked-In' 
            OR i.invoice_status_id = 1
        )
        ORDER BY b.booking_created_at DESC
    ";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($results);
    }

    // NEW API: Enhanced booking list with real-time balance calculation for transactions
    function getBookingsWithBillingStatusEnhanced()
    {
        include "connection.php";

        $query = "
        SELECT DISTINCT
            b.booking_id,
            b.reference_no,
            b.booking_checkin_dateandtime,
            b.booking_checkout_dateandtime,
            b.booking_created_at,
            -- Customer info (supports registered and walk-in)
            COALESCE(CONCAT(c.customers_fname, ' ', c.customers_lname),
                     CONCAT(w.customers_walk_in_fname, ' ', w.customers_walk_in_lname)) AS customer_name,
            COALESCE(c.customers_email, w.customers_walk_in_email) AS customer_email,
            COALESCE(c.customers_phone, w.customers_walk_in_phone) AS customer_phone,
            -- Enhanced balance calculation
            CASE 
                WHEN latest_billing.billing_id IS NOT NULL THEN
                    -- Use billing data if available
                    CASE 
                        WHEN latest_invoice.invoice_status_id = 1 THEN 0  -- Invoice complete = fully paid
                        ELSE COALESCE(latest_billing.billing_balance, 0)
                    END
                ELSE 
                    -- No billing record, calculate from booking data
                    COALESCE(b.booking_totalAmount, 0) - COALESCE(b.booking_payment, 0)
            END AS balance,
            -- Enhanced amounts
            CASE 
                WHEN latest_billing.billing_id IS NOT NULL THEN
                    COALESCE(latest_billing.billing_total_amount, b.booking_totalAmount)
                ELSE b.booking_totalAmount
            END AS total_amount,
            CASE 
                WHEN latest_billing.billing_id IS NOT NULL THEN
                    COALESCE(latest_billing.billing_downpayment, b.booking_payment)
                ELSE b.booking_payment
            END AS downpayment,
            
            -- Status and billing info
            COALESCE(bs.booking_status_name, 'Pending') AS booking_status,
            latest_billing.billing_id,
            latest_invoice.invoice_id,
            latest_invoice.invoice_status_id,
            CASE 
                WHEN latest_invoice.invoice_status_id = 1 THEN 'Complete'
                WHEN latest_invoice.invoice_status_id = 2 THEN 'Incomplete'
                WHEN latest_billing.billing_id IS NOT NULL THEN 'Billed'
                ELSE 'Not Billed'
            END AS billing_status
        FROM tbl_booking b
        LEFT JOIN tbl_customers c ON b.customers_id = c.customers_id
        LEFT JOIN tbl_customers_walk_in w ON b.customers_walk_in_id = w.customers_walk_in_id
        LEFT JOIN (
            SELECT bh1.booking_id, bs.booking_status_name
            FROM tbl_booking_history bh1
            INNER JOIN (
                SELECT booking_id, MAX(booking_history_id) AS latest_history_id
                FROM tbl_booking_history
                GROUP BY booking_id
            ) latest ON latest.booking_id = bh1.booking_id AND latest.latest_history_id = bh1.booking_history_id
            INNER JOIN tbl_booking_status bs ON bh1.status_id = bs.booking_status_id
        ) bs ON bs.booking_id = b.booking_id
        LEFT JOIN (
            -- Get the latest billing record for each booking
            SELECT 
                bi.booking_id,
                bi.billing_id,
                bi.billing_total_amount,
                bi.billing_downpayment,
                bi.billing_balance,
                bi.billing_vat,
                bi.billing_dateandtime
            FROM tbl_billing bi
            INNER JOIN (
                SELECT booking_id, MAX(billing_id) as max_billing_id
                FROM tbl_billing
                GROUP BY booking_id
            ) latest ON latest.booking_id = bi.booking_id AND latest.max_billing_id = bi.billing_id
        ) latest_billing ON latest_billing.booking_id = b.booking_id
        LEFT JOIN (
            -- Get the latest invoice for each billing record
            SELECT 
                i.billing_id,
                i.invoice_id,
                i.invoice_status_id,
                i.invoice_total_amount,
                i.invoice_date,
                i.invoice_time
            FROM tbl_invoice i
            INNER JOIN (
                SELECT billing_id, MAX(invoice_id) as max_invoice_id
                FROM tbl_invoice
                GROUP BY billing_id
            ) latest_inv ON latest_inv.billing_id = i.billing_id AND latest_inv.max_invoice_id = i.invoice_id
        ) latest_invoice ON latest_invoice.billing_id = latest_billing.billing_id
        WHERE (
            bs.booking_status_name = 'Checked-In' 
            OR latest_invoice.invoice_status_id = 1
        )
        ORDER BY b.booking_created_at DESC
    ";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($results);
    }

    function getBookingInvoice($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $booking_id = $json["booking_id"];

        // Get invoice details
        $query = "
        SELECT 
            a.booking_id,
            a.reference_no,
            CONCAT(b.customers_fname, ' ', b.customers_lname) AS customer_name,
            c.billing_id,
            c.billing_total_amount,
            c.billing_balance,
            c.billing_downpayment,
            d.invoice_id,
            d.invoice_date,
            d.invoice_time,
            d.invoice_total_amount,
            e.payment_method_name,
            f.employee_fname
        FROM tbl_booking a
        LEFT JOIN tbl_customers b ON a.customers_id = b.customers_id
        LEFT JOIN tbl_billing c ON a.booking_id = c.booking_id
        LEFT JOIN tbl_invoice d ON c.billing_id = d.billing_id
        LEFT JOIN tbl_payment_method e ON d.payment_method_id = e.payment_method_id
        LEFT JOIN tbl_employee f ON d.employee_id = f.employee_id
        WHERE a.booking_id = :booking_id
        LIMIT 1
    ";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(":booking_id", $booking_id, PDO::PARAM_INT);
        $stmt->execute();
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invoice) {
            echo json_encode($invoice);
        } else {
            echo json_encode(["error" => "Invoice not found."]);
        }
    }

    function getDetailedBookingCharges($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $booking_id = $json["booking_id"];

        try {
            // Get room charges
            $roomQuery = $conn->prepare("
                SELECT 
                    'Room Charges' as charge_type,
                    rt.roomtype_name as charge_name,
                    'Room' as category,
                    r.roomnumber_id as room_number,
                    rt.roomtype_name as room_type,
                    rt.roomtype_price as unit_price,
                    GREATEST(DATEDIFF(b.booking_checkout_dateandtime, b.booking_checkin_dateandtime), 1) as quantity,
                    (rt.roomtype_price * GREATEST(DATEDIFF(b.booking_checkout_dateandtime, b.booking_checkin_dateandtime), 1)) as total_amount,
                    rt.roomtype_description as charges_master_description
                FROM tbl_booking_room br
                JOIN tbl_booking b ON br.booking_id = b.booking_id
                JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id
                JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id
                WHERE br.booking_id = :booking_id
            ");
            $roomQuery->bindParam(':booking_id', $booking_id);
            $roomQuery->execute();
            $roomCharges = $roomQuery->fetchAll(PDO::FETCH_ASSOC);

            // Get additional charges
            $chargesQuery = $conn->prepare("
                SELECT 
                    'Additional Charges' as charge_type,
                    cm.charges_master_name as charge_name,
                    cc.charges_category_name as category,
                    r.roomnumber_id as room_number,
                    rt.roomtype_name as room_type,
                    bc.booking_charges_price as unit_price,
                    bc.booking_charges_quantity as quantity,
                    (bc.booking_charges_price * bc.booking_charges_quantity) as total_amount,
                    cm.charges_master_description as charges_master_description
                FROM tbl_booking_charges bc
                JOIN tbl_booking_room br ON bc.booking_room_id = br.booking_room_id
                JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id
                JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id
                JOIN tbl_charges_master cm ON bc.charges_master_id = cm.charges_master_id
                JOIN tbl_charges_category cc ON cm.charges_category_id = cc.charges_category_id
                WHERE br.booking_id = :booking_id
                AND bc.booking_charge_status = 2
            ");
            $chargesQuery->bindParam(':booking_id', $booking_id);
            $chargesQuery->execute();
            $additionalCharges = $chargesQuery->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            $room_total = array_sum(array_column($roomCharges, 'total_amount'));
            $charges_total = array_sum(array_column($additionalCharges, 'total_amount'));
            $subtotal = $room_total + $charges_total;
            $grand_total = $subtotal;

            // Get booking downpayment
            $bookingQuery = $conn->prepare("SELECT booking_payment AS booking_downpayment FROM tbl_booking WHERE booking_id = :booking_id");
            $bookingQuery->bindParam(':booking_id', $booking_id);
            $bookingQuery->execute();
            $booking = $bookingQuery->fetch(PDO::FETCH_ASSOC);
            $downpayment = $booking ? ($booking["booking_downpayment"] ?? 0) : 0;
            $balance = $grand_total - $downpayment;

            $result = [
                "success" => true,
                "room_charges" => $roomCharges,
                "additional_charges" => $additionalCharges,
                "summary" => [
                    "room_total" => $room_total,
                    "charges_total" => $charges_total,
                    "subtotal" => $subtotal,
                    "grand_total" => $grand_total,
                    "downpayment" => $downpayment,
                    "balance" => $balance
                ]
            ];

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => "Error getting detailed charges: " . $e->getMessage()
            ]);
        }
    }

    // New endpoints: getLatestBillingByBooking and recalculateBilling
    function getLatestBillingByBooking($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $booking_id = isset($json["booking_id"]) ? $json["booking_id"] : null;
        if (!$booking_id) {
            echo json_encode(["success" => false, "message" => "Missing booking_id"]);
            return;
        }
        try {
            $stmt = $conn->prepare("SELECT billing_id, booking_id, billing_total_amount, billing_balance, billing_downpayment, billing_vat, billing_dateandtime, discounts_id FROM tbl_billing WHERE booking_id = :booking_id ORDER BY billing_id DESC LIMIT 1");
            $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $stmt->execute();
            $billing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($billing) {
                echo json_encode(array_merge(["success" => true], $billing));
            } else {
                echo json_encode(["success" => false, "message" => "No billing record found"]);
            }
        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => "Error fetching billing: " . $e->getMessage()]);
        }
    }

    function recalculateBilling($json)
    {
        include "connection.php";
        $json = json_decode($json, true);
        $booking_id = isset($json["booking_id"]) ? $json["booking_id"] : null;
        $discount_id = isset($json["discount_id"]) ? $json["discount_id"] : null;
        $vat_rate = isset($json["vat_rate"]) ? $json["vat_rate"] : 0;
        $downpayment_override = isset($json["downpayment"]) ? $json["downpayment"] : null;
        if (!$booking_id) {
            echo json_encode(["success" => false, "message" => "Missing booking_id"]);
            return;
        }
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("SELECT billing_id, billing_downpayment, discounts_id FROM tbl_billing WHERE booking_id = :booking_id ORDER BY billing_id DESC LIMIT 1");
            $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $stmt->execute();
            $billingRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$billingRow) {
                $conn->rollBack();
                echo json_encode(["success" => false, "message" => "No billing record found for booking"]);
                return;
            }
            $billing_id = $billingRow["billing_id"];
            $actual_discount_id = $discount_id ? $discount_id : (isset($billingRow["discounts_id"]) ? $billingRow["discounts_id"] : null);
            $actual_downpayment = ($downpayment_override !== null) ? $downpayment_override : (isset($billingRow["billing_downpayment"]) ? $billingRow["billing_downpayment"] : 0);
            if (!$actual_downpayment || $actual_downpayment == 0) {
                try {
                    $dpQuery = $conn->prepare("SELECT booking_payment AS booking_downpayment FROM tbl_booking WHERE booking_id = :booking_id");
                    $dpQuery->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
                    $dpQuery->execute();
                    $bookingRow = $dpQuery->fetch(PDO::FETCH_ASSOC);
                    if ($bookingRow) {
                        $actual_downpayment = isset($bookingRow["booking_downpayment"]) ? $bookingRow["booking_downpayment"] : 0;
                    }
                } catch (Exception $_) {
                }
            }
            $billingBreakdown = $this->calculateComprehensiveBillingInternal($conn, $booking_id, $actual_discount_id, $vat_rate, $actual_downpayment);
            if (!isset($billingBreakdown["success"]) || !$billingBreakdown["success"]) {
                $conn->rollBack();
                echo json_encode(["success" => false, "message" => isset($billingBreakdown["message"]) ? $billingBreakdown["message"] : "Failed to calculate billing"]);
                return;
            }
            $update = $conn->prepare("UPDATE tbl_billing SET billing_total_amount = :total, billing_balance = :balance, billing_downpayment = :downpayment, billing_vat = :vat, discounts_id = :discount_id WHERE billing_id = :billing_id");
            $update->bindParam(':total', $billingBreakdown["final_total"]);
            $update->bindParam(':balance', $billingBreakdown["balance"]);
            $update->bindParam(':downpayment', $actual_downpayment);
            $update->bindParam(':vat', $billingBreakdown["vat_amount"]);
            $update->bindParam(':discount_id', $actual_discount_id);
            $update->bindParam(':billing_id', $billing_id);
            $update->execute();
            $conn->commit();
            echo json_encode(["success" => true, "message" => "Billing recalculated successfully.", "billing_id" => $billing_id, "breakdown" => $billingBreakdown]);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(["success" => false, "message" => "Error recalculating billing: " . $e->getMessage()]);
        }
    }
}

$json = isset($_POST['json']) ? $_POST['json'] : 0;
$operation = isset($_POST['operation']) ? $_POST['operation'] : 0;
$transactions = new Transactions();

switch ($operation) {
    case 'bookingList':
        $transactions->bookingList();
        break;
    case 'finalizeBookingApproval':
        $transactions->finalizeBookingApproval($json);
        break;
    case 'getVacantRoomsByBooking':
        $transactions->getVacantRoomsByBooking($json);
        break;
    case "chargesMasterList":
        $transactions->chargesMasterList();
        break;
    case "bookingChargesList":
        $transactions->bookingChargesList();
        break;
    case "addChargesAmenities":
        $transactions->addChargesAmenities();
        break;
    case "getChargesCategory":
        $transactions->getChargesCategory();
        break;
    case "chargesCategoryList":
        $transactions->getChargesCategory();
        break;
    case "saveAmenitiesCharges":
        $transactions->saveAmenitiesCharges();
        break;
    case "updateAmenityCharges":
        $transactions->updateAmenityCharges();
        break;
    case "createInvoice":
        $transactions->createInvoice($json);
        break;
    case "getBookingsWithBillingStatus":
        $transactions->getBookingsWithBillingStatus();
        break;
    case "getBookingsWithBillingStatusEnhanced":
        $transactions->getBookingsWithBillingStatusEnhanced();
        break;
    case "getBookingInvoice":
        $transactions->getBookingInvoice($json);
        break;
    case "validateBillingCompleteness":
        $transactions->validateBillingCompleteness($json);
        break;
    case "calculateComprehensiveBilling":
        $transactions->calculateComprehensiveBilling($json);
        break;
    case "createBillingRecord":
        $transactions->createBillingRecord($json);
        break;
    case "getBookingCharges":
        $transactions->getBookingCharges($json);
        break;
    case "addBookingCharge":
        $transactions->addBookingCharge($json);
        break;
    case "getDetailedBookingCharges":
        $transactions->getDetailedBookingCharges($json);
        break;
    case "getBookingBillingId":
        $transactions->getBookingBillingId($json);
        break;
    case "getLatestBillingByBooking":
        $transactions->getLatestBillingByBooking($json);
        break;
    case "recalculateBilling":
        $transactions->recalculateBilling($json);
        break;
    default:
        echo "Invalid Operation";
        break;
}
