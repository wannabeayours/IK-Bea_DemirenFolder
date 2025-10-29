<?php
include "headers.php";
class Admin_Functions
{
    function setVisitorApproval()
    {
        include "connection.php";
        try {
            $id = $_POST['visitorlogs_id'] ?? null;
            $statusId = $_POST['visitorapproval_id'] ?? null;
            if (!$id || !$statusId) {
                if (ob_get_length()) { ob_clean(); }
                return json_encode(['response' => false, 'success' => false, 'message' => 'Missing parameters']);
            }

            $sql = "UPDATE tbl_visitorlogs SET visitorapproval_id = :statusId WHERE visitorlogs_id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':statusId', $statusId);
            $stmt->bindParam(':id', $id);
            $ok = $stmt->execute();

            if (ob_get_length()) { ob_clean(); }
            return json_encode(['response' => $ok === true, 'success' => $ok === true]);
        } catch (PDOException $e) {
            if (ob_get_length()) { ob_clean(); }
            return json_encode(['response' => false, 'success' => false, 'message' => 'Database error']);
        }
    }

    function extendBookingWithPayment($data)
    {
        include "connection.php";

        try {
            $conn->beginTransaction();

            $booking_id = intval($data["booking_id"] ?? 0);
            $employee_id = intval($data["employee_id"] ?? 0);
            $new_checkout_date = $data["new_checkout_date"] ?? null;
            $additional_nights = intval($data["additional_nights"] ?? 0);
            $additional_amount = floatval($data["additional_amount"] ?? 0);
            $payment_amount = floatval($data["payment_amount"] ?? 0);
            $payment_method_id = intval($data["payment_method_id"] ?? 2);
            $room_breakdown = $data["room_breakdown"] ?? [];

            if ($booking_id <= 0 || !$new_checkout_date || $additional_nights <= 0 || $additional_amount <= 0) {
                throw new Exception("Invalid parameters for booking extension");
            }

            // Fetch original booking
            $checkBooking = $conn->prepare("SELECT * FROM tbl_booking WHERE booking_id = :booking_id");
            $checkBooking->execute([':booking_id' => $booking_id]);
            $originalBooking = $checkBooking->fetch(PDO::FETCH_ASSOC);
            if (!$originalBooking) {
                throw new Exception("Booking with ID $booking_id not found");
            }

            // Update original booking checkout date only (no new reference created)
            $updateBooking = $conn->prepare("UPDATE tbl_booking SET booking_checkout_dateandtime = :new_checkout WHERE booking_id = :booking_id");
            $updateBooking->execute([':new_checkout' => $new_checkout_date, ':booking_id' => $booking_id]);

            // Get a room from this booking (single-room context)
            $getBookingRoom = $conn->prepare("SELECT booking_room_id, roomnumber_id FROM tbl_booking_room WHERE booking_id = :booking_id ORDER BY booking_room_id ASC LIMIT 1");
            $getBookingRoom->execute([':booking_id' => $booking_id]);
            $originalBookingRoom = $getBookingRoom->fetch(PDO::FETCH_ASSOC);
            if (!$originalBookingRoom) {
                throw new Exception("No booking room found for this booking");
            }

            // Occupancy remains unchanged here; check-in/out will update via changeBookingStatus

            // Ensure charges master exists for Room Extended Stay (schema without status column)
            $checkChargesMaster = $conn->prepare("SELECT charges_master_id FROM tbl_charges_master WHERE charges_master_name = 'Room Extended Stay' LIMIT 1");
            $checkChargesMaster->execute();
            $chargesMaster = $checkChargesMaster->fetch(PDO::FETCH_ASSOC);
            if (!$chargesMaster) {
                $createChargesMaster = $conn->prepare("INSERT INTO tbl_charges_master (charges_category_id, charges_master_name, charges_master_price, charge_name_isRestricted) VALUES (4, 'Room Extended Stay', 0, 1)");
                $createChargesMaster->execute();
                $charges_master_id = $conn->lastInsertId();
            } else {
                $charges_master_id = $chargesMaster['charges_master_id'];
            }

            // Insert charge per room (include required timestamps)
            $room_total = $price_per_night * $additional_nights;
            $insertCharge = $conn->prepare("INSERT INTO tbl_booking_charges (charges_master_id, booking_room_id, booking_charges_price, booking_charges_quantity, booking_charges_total, booking_charge_status, booking_charge_datetime, booking_return_datetime) VALUES (:charges_master_id, :booking_room_id, :room_price, :additional_nights, :room_total, 2, NOW(), NOW())");
            $insertCharge->execute([
                ':charges_master_id' => $charges_master_id,
                ':booking_room_id' => $bookingRoom['booking_room_id'],
                ':room_price' => $price_per_night,
                ':additional_nights' => $additional_nights,
                ':room_total' => $room_total
            ]);

            // Determine per-night price for charge entry
            $perNightPrice = 0.0;
            if (is_array($room_breakdown) && count($room_breakdown) > 0 && isset($room_breakdown[0]['pricePerNight'])) {
                $perNightPrice = floatval($room_breakdown[0]['pricePerNight']);
            }
            if ($perNightPrice <= 0 && $additional_nights > 0) {
                $perNightPrice = $additional_amount / $additional_nights;
            }

            // Insert booking charges tied to the original booking room (include required timestamps)
            $insertBookingCharges = $conn->prepare("INSERT INTO tbl_booking_charges (charges_master_id, booking_room_id, booking_charges_price, booking_charges_quantity, booking_charges_total, booking_charge_status, booking_charge_datetime, booking_return_datetime) VALUES (:charges_master_id, :booking_room_id, :room_price, :additional_nights, :additional_amount, 2, NOW(), NOW())");
            $insertBookingCharges->execute([
                ':charges_master_id' => $charges_master_id,
                ':booking_room_id' => $originalBookingRoom['booking_room_id'],
                ':room_price' => $perNightPrice,
                ':additional_nights' => $additional_nights,
                ':additional_amount' => $additional_amount
            ]);
            $booking_charges_id = $conn->lastInsertId();

            // Create billing record referenced to the original booking (always create; VAT = 0 for extensions)
            $billing_id = null;
            $insertBilling = $conn->prepare("INSERT INTO tbl_billing (booking_id, employee_id, payment_method_id, billing_dateandtime, billing_invoice_number, billing_downpayment, billing_vat, billing_total_amount, billing_balance) VALUES (:booking_id, :employee_id, :payment_method_id, NOW(), :invoice_number, :payment_amount, 0, :total_amount, :balance)");
             // Generate grouped extension invoice number under original reference e.g., REF1234567890-EXT001
             $baseRef = $originalBooking['reference_no'] ?? null;
             if (!$baseRef) { $baseRef = 'REF' . date('YmdHis'); }
             $countStmt = $conn->prepare("SELECT COUNT(*) AS ext_count FROM tbl_billing WHERE booking_id = :booking_id AND billing_invoice_number LIKE :like_pattern");
             $likePattern = $baseRef . '-EXT%';
             $countStmt->bindParam(':booking_id', $booking_id);
             $countStmt->bindParam(':like_pattern', $likePattern);
             $countStmt->execute();
             $extCountRow = $countStmt->fetch(PDO::FETCH_ASSOC);
             $sequence = intval($extCountRow['ext_count'] ?? 0) + 1;
             $invoice_number = $baseRef . '-EXT' . str_pad($sequence, 3, '0', STR_PAD_LEFT);

             $balance = $additional_amount - $payment_amount;
             if ($balance < 0) { $balance = 0; }
             $insertBilling->execute([
                 ':booking_id' => $booking_id,
                 ':employee_id' => $employee_id,
                 ':payment_method_id' => $payment_method_id,
                 ':invoice_number' => $invoice_number,
                 ':payment_amount' => $payment_amount,
                 ':total_amount' => $additional_amount,
                 ':balance' => $balance
             ]);
             $billing_id = $conn->lastInsertId();

            $conn->commit();

            // Clean output buffer to avoid warnings mixing with JSON
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([
                "success" => true,
                "message" => "Single-room booking extended: checkout updated and billing recorded",
                "original_booking_id" => $booking_id,
                "original_reference_no" => $originalBooking['reference_no'] ?? null,
                "updated_checkout_date" => $new_checkout_date,
                "total_additional_amount" => $additional_amount,
                "payment_amount" => $payment_amount,
                "remaining_balance" => $additional_amount - $payment_amount,
                "billing_id" => $billing_id,
                "charges_id" => $booking_charges_id
            ]);
        } catch (Exception $e) {
            $conn->rollBack();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([
                "success" => false,
                "error" => $e->getMessage()
            ]);
        }
    }

    // Handle multi-room booking extension with aggregated billing under grouped reference
    function extendMultiRoomBookingWithPayment($data)
    {
        include "connection.php";
        try {
            $conn->beginTransaction();

            $booking_id = intval($data["booking_id"] ?? 0);
            $employee_id = intval($data["employee_id"] ?? 0);
            $new_checkout_date = $data["new_checkout_date"] ?? null;
            $additional_nights = intval($data["additional_nights"] ?? 0);
            $additional_amount = floatval($data["additional_amount"] ?? 0); // frontend summed
            $payment_amount = floatval($data["payment_amount"] ?? 0);
            $payment_method_id = intval($data["payment_method_id"] ?? 2);
            $selected_rooms = $data["selected_rooms"] ?? [];

            if ($booking_id <= 0 || !$new_checkout_date || $additional_nights <= 0 || $additional_amount <= 0 || !is_array($selected_rooms) || count($selected_rooms) === 0) {
                throw new Exception("Invalid parameters for multi-room booking extension");
            }

            // Fetch original booking
            $checkBooking = $conn->prepare("SELECT * FROM tbl_booking WHERE booking_id = :booking_id");
            $checkBooking->execute([':booking_id' => $booking_id]);
            $originalBooking = $checkBooking->fetch(PDO::FETCH_ASSOC);
            if (!$originalBooking) {
                throw new Exception("Booking with ID $booking_id not found");
            }

            // Update booking checkout date
            $updateBooking = $conn->prepare("UPDATE tbl_booking SET booking_checkout_dateandtime = :new_checkout WHERE booking_id = :booking_id");
            $updateBooking->execute([':new_checkout' => $new_checkout_date, ':booking_id' => $booking_id]);

            // Loop through selected rooms and add charges per room
            $recomputed_total = 0.0;
            foreach ($selected_rooms as $room) {
                $room_id = intval($room['room_id'] ?? 0);
                $price_per_night = floatval($room['price_per_night'] ?? 0);
                if ($room_id <= 0 || $price_per_night <= 0) { continue; }

                // Find booking_room row for this room number
                $getBookingRoom = $conn->prepare("SELECT booking_room_id FROM tbl_booking_room WHERE booking_id = :booking_id AND roomnumber_id = :room_id LIMIT 1");
                $getBookingRoom->execute([':booking_id' => $booking_id, ':room_id' => $room_id]);
                $bookingRoom = $getBookingRoom->fetch(PDO::FETCH_ASSOC);
                if (!$bookingRoom) { continue; }

                // Ensure charges master exists for Room Extended Stay (schema without status column)
                $checkChargesMaster = $conn->prepare("SELECT charges_master_id FROM tbl_charges_master WHERE charges_master_name = 'Room Extended Stay' LIMIT 1");
                $checkChargesMaster->execute();
                $chargesMaster = $checkChargesMaster->fetch(PDO::FETCH_ASSOC);
                if (!$chargesMaster) {
                    $createChargesMaster = $conn->prepare("INSERT INTO tbl_charges_master (charges_category_id, charges_master_name, charges_master_price, charge_name_isRestricted) VALUES (4, 'Room Extended Stay', 0, 1)");
                    $createChargesMaster->execute();
                    $charges_master_id = $conn->lastInsertId();
                } else {
                    $charges_master_id = $chargesMaster['charges_master_id'];
                }

                // Insert charge per room (include required timestamps)
                $room_total = $price_per_night * $additional_nights;
                $insertCharge = $conn->prepare("INSERT INTO tbl_booking_charges (charges_master_id, booking_room_id, booking_charges_price, booking_charges_quantity, booking_charges_total, booking_charge_status, booking_charge_datetime, booking_return_datetime) VALUES (:charges_master_id, :booking_room_id, :room_price, :additional_nights, :room_total, 2, NOW(), NOW())");
                $insertCharge->execute([
                    ':charges_master_id' => $charges_master_id,
                    ':booking_room_id' => $bookingRoom['booking_room_id'],
                    ':room_price' => $price_per_night,
                    ':additional_nights' => $additional_nights,
                    ':room_total' => $room_total
                ]);

                $recomputed_total += $room_total;
            }

            // Use recomputed total if it differs and is > 0
            if ($recomputed_total > 0) {
                $additional_amount = $recomputed_total;
            }

            // Create aggregated billing entry (VAT=0), grouped under original reference
            $insertBilling = $conn->prepare("INSERT INTO tbl_billing (booking_id, employee_id, payment_method_id, billing_dateandtime, billing_invoice_number, billing_downpayment, billing_vat, billing_total_amount, billing_balance) VALUES (:booking_id, :employee_id, :payment_method_id, NOW(), :invoice_number, :payment_amount, 0, :total_amount, :balance)");
            $baseRef = $originalBooking['reference_no'] ?? 'REF' . date('YmdHis');
            $countStmt = $conn->prepare("SELECT COUNT(*) AS ext_count FROM tbl_billing WHERE booking_id = :booking_id AND billing_invoice_number LIKE :like_pattern");
            $likePattern = $baseRef . '-EXT%';
            $countStmt->bindParam(':booking_id', $booking_id);
            $countStmt->bindParam(':like_pattern', $likePattern);
            $countStmt->execute();
            $extCountRow = $countStmt->fetch(PDO::FETCH_ASSOC);
            $sequence = intval($extCountRow['ext_count'] ?? 0) + 1;
            $invoice_number = $baseRef . '-EXT' . str_pad($sequence, 3, '0', STR_PAD_LEFT);

            $balance = $additional_amount - $payment_amount;
            if ($balance < 0) { $balance = 0; }
            $insertBilling->execute([
                ':booking_id' => $booking_id,
                ':employee_id' => $employee_id,
                ':payment_method_id' => $payment_method_id,
                ':invoice_number' => $invoice_number,
                ':payment_amount' => $payment_amount,
                ':total_amount' => $additional_amount,
                ':balance' => $balance
            ]);
            $billing_id = $conn->lastInsertId();

            $conn->commit();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([
                'success' => true,
                'message' => 'Multi-room booking extended and billed',
                'original_reference_no' => $baseRef,
                'extension_reference_no' => $invoice_number,
                'total_additional_amount' => $additional_amount,
                'payment_amount' => $payment_amount,
                'remaining_balance' => $balance,
                'billing_id' => $billing_id
            ]);
        } catch (Exception $e) {
            $conn->rollBack();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    function getAllStatus()
    {
        include "connection.php";
        $stmt = $conn->prepare("SELECT booking_status_id, booking_status_name FROM tbl_booking_status ORDER BY booking_status_id ASC");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Visitor approvals for Visitors Log page
    function get_visitor_approval_statuses()
    {
        include "connection.php";
        try {
            $stmt = $conn->prepare("SELECT visitorapproval_id, visitorapproval_status FROM tbl_visitorapproval ORDER BY visitorapproval_id ASC");
            $stmt->execute();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // Visitor Logs: list all logs for admin UI
    function getVisitorLogs() {
        include "connection.php";
        try {
            $sql = "SELECT visitorlogs_id, visitorapproval_id, booking_room_id, employee_id, visitorlogs_visitorname, visitorlogs_purpose, visitorlogs_checkin_time, visitorlogs_checkout_time FROM tbl_visitorlogs ORDER BY visitorlogs_id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // Employee management: list all employees with user level
    function viewEmployees() {
        include "connection.php";
        try {
            $sql = "SELECT 
                        e.employee_id,
                        e.employee_fname,
                        e.employee_lname,
                        e.employee_username,
                        e.employee_email,
                        e.employee_phone,
                        e.employee_address,
                        e.employee_birthdate,
                        e.employee_gender,
                        e.employee_user_level_id,
                        e.employee_status,
                        e.employee_created_at,
                        e.employee_updated_at,
                        ul.userlevel_name
                    FROM tbl_employee e
                    LEFT JOIN tbl_user_level ul ON ul.userlevel_id = e.employee_user_level_id
                    ORDER BY e.employee_id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['status' => 'success', 'data' => $rows]);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch employees']);
        }
    }

    // Employee management: get user levels
    function getUserLevels() {
        include "connection.php";
        try {
            $stmt = $conn->prepare("SELECT userlevel_id, userlevel_name FROM tbl_user_level ORDER BY userlevel_id ASC");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['status' => 'success', 'data' => $rows]);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch user levels']);
        }
    }

    // Employee management: add employee
    function addEmployee($json) {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $required = ['employee_fname','employee_lname','employee_username','employee_email','employee_phone','employee_address','employee_birthdate','employee_gender','employee_password'];
            foreach ($required as $key) {
                if (!isset($data[$key]) || trim($data[$key]) === '') {
                    if (ob_get_length()) { ob_clean(); }
                    echo json_encode(['status' => 'error', 'message' => 'Missing required field: '.$key]);
                    return;
                }
            }

            // Check uniqueness of username/email
            $chk = $conn->prepare("SELECT employee_id FROM tbl_employee WHERE employee_username = :u OR employee_email = :e LIMIT 1");
            $chk->bindParam(':u', $data['employee_username']);
            $chk->bindParam(':e', $data['employee_email']);
            $chk->execute();
            if ($chk->fetch(PDO::FETCH_ASSOC)) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['status' => 'error', 'message' => 'Username or email already exists']);
                return;
            }

            $hashed = password_hash($data['employee_password'], PASSWORD_DEFAULT);
            $user_level = isset($data['employee_user_level_id']) ? intval($data['employee_user_level_id']) : 2; // default Front-Desk
            $status = isset($data['employee_status']) ? intval($data['employee_status']) : 1; // default Active

            $sql = "INSERT INTO tbl_employee (
                        employee_fname, employee_lname, employee_username, employee_email, employee_phone,
                        employee_password, employee_address, employee_birthdate, employee_gender,
                        employee_user_level_id, employee_status, employee_created_at, employee_updated_at
                    ) VALUES (
                        :fname, :lname, :username, :email, :phone,
                        :password, :address, :birthdate, :gender,
                        :user_level, :status, NOW(), NOW()
                    )";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':fname', $data['employee_fname']);
            $stmt->bindParam(':lname', $data['employee_lname']);
            $stmt->bindParam(':username', $data['employee_username']);
            $stmt->bindParam(':email', $data['employee_email']);
            $stmt->bindParam(':phone', $data['employee_phone']);
            $stmt->bindParam(':password', $hashed);
            $stmt->bindParam(':address', $data['employee_address']);
            $stmt->bindParam(':birthdate', $data['employee_birthdate']);
            $stmt->bindParam(':gender', $data['employee_gender']);
            $stmt->bindParam(':user_level', $user_level, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);
            $ok = $stmt->execute();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['status' => $ok ? 'success' : 'error', 'message' => $ok ? 'Employee added successfully' : 'Failed to add employee']);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    }

    // Employee management: update employee
    function updateEmployee($json) {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $employee_id = intval($data['employee_id'] ?? 0);
            if ($employee_id <= 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['status' => 'error', 'message' => 'Invalid employee ID']);
                return;
            }

            $fields = [];
            $params = [':employee_id' => $employee_id];
            $map = [
                'employee_fname','employee_lname','employee_username','employee_email','employee_phone',
                'employee_address','employee_birthdate','employee_gender','employee_user_level_id'
            ];
            foreach ($map as $key) {
                if (isset($data[$key])) { $fields[] = "$key = :$key"; $params[":$key"] = $data[$key]; }
            }

            if (isset($data['employee_password']) && trim($data['employee_password']) !== '') {
                $fields[] = "employee_password = :password";
                $params[':password'] = password_hash($data['employee_password'], PASSWORD_DEFAULT);
            }

            if (empty($fields)) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['status' => 'error', 'message' => 'No fields to update']);
                return;
            }

            $fields[] = "employee_updated_at = NOW()";
            $sql = "UPDATE tbl_employee SET " . implode(', ', $fields) . " WHERE employee_id = :employee_id";
            $stmt = $conn->prepare($sql);
            $ok = $stmt->execute($params);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['status' => $ok ? 'success' : 'error', 'message' => $ok ? 'Employee updated successfully' : 'Failed to update employee']);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    }

    // Employee management: toggle active/inactive
    function changeEmployeeStatus($json) {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $employee_id = intval($data['employee_id'] ?? 0);
            $employee_status = isset($data['employee_status']) ? intval($data['employee_status']) : null;
            if ($employee_id <= 0 || $employee_status === null) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['status' => 'error', 'message' => 'Missing employee_id or employee_status']);
                return;
            }
            $stmt = $conn->prepare("UPDATE tbl_employee SET employee_status = :status, employee_updated_at = NOW() WHERE employee_id = :id");
            $stmt->bindParam(':status', $employee_status, PDO::PARAM_INT);
            $stmt->bindParam(':id', $employee_id, PDO::PARAM_INT);
            $ok = $stmt->execute();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['status' => $ok ? 'success' : 'error', 'message' => $ok ? 'Status updated successfully' : 'Failed to update status']);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    }

    // Visitor Logs: add a new visitor log entry (robust defaults)
    function addVisitorLog() {
        include "connection.php";
        try {
            $name = isset($_POST['visitorlogs_visitorname']) ? trim($_POST['visitorlogs_visitorname']) : '';
            $purpose = isset($_POST['visitorlogs_purpose']) ? trim($_POST['visitorlogs_purpose']) : '';
            $checkin = isset($_POST['visitorlogs_checkin_time']) ? trim($_POST['visitorlogs_checkin_time']) : '';
            $statusId = isset($_POST['visitorapproval_id']) ? intval($_POST['visitorapproval_id']) : null;
            $booking_room_id = isset($_POST['booking_room_id']) ? intval($_POST['booking_room_id']) : 0;
            $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
            $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : null;

            // Attempt to resolve booking_room_id from booking_id if missing
            if ($booking_room_id <= 0 && $booking_id > 0) {
                $stmtBr = $conn->prepare("SELECT booking_room_id FROM tbl_booking_room WHERE booking_id = :booking_id LIMIT 1");
                $stmtBr->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
                $stmtBr->execute();
                $rowBr = $stmtBr->fetch(PDO::FETCH_ASSOC);
                if ($rowBr && isset($rowBr['booking_room_id'])) {
                    $booking_room_id = intval($rowBr['booking_room_id']);
                }
            }

            // Resolve default status to Approved when not provided
            if ($statusId === null || $statusId <= 0) {
                $stmtStatus = $conn->prepare("SELECT visitorapproval_id FROM tbl_visitorapproval WHERE LOWER(visitorapproval_status) LIKE '%approved%' ORDER BY visitorapproval_id ASC LIMIT 1");
                $stmtStatus->execute();
                $rowStatus = $stmtStatus->fetch(PDO::FETCH_ASSOC);
                if ($rowStatus && isset($rowStatus['visitorapproval_id'])) {
                    $statusId = intval($rowStatus['visitorapproval_id']);
                } else {
                    $statusId = null; // allow NULL if no Approved status exists
                }
            }

            if ($name === '' || $purpose === '' || $booking_room_id <= 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'response' => false, 'message' => 'Missing required fields']);
                return;
            }

            if ($checkin === '') {
                $checkin = date('Y-m-d H:i:s');
            }

            $sql = "INSERT INTO tbl_visitorlogs (visitorapproval_id, booking_room_id, employee_id, visitorlogs_visitorname, visitorlogs_purpose, visitorlogs_checkin_time) VALUES (:statusId, :booking_room_id, :employee_id, :name, :purpose, :checkin)";
            $stmt = $conn->prepare($sql);
            if ($statusId !== null && $statusId > 0) {
                $stmt->bindParam(':statusId', $statusId, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':statusId', null, PDO::PARAM_NULL);
            }
            $stmt->bindParam(':booking_room_id', $booking_room_id, PDO::PARAM_INT);
            if ($employee_id !== null && $employee_id > 0) {
                $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':employee_id', null, PDO::PARAM_NULL);
            }
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':purpose', $purpose);
            $stmt->bindParam(':checkin', $checkin);
            $ok = $stmt->execute();
            $id = $conn->lastInsertId();

            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => $ok === true, 'response' => $ok === true, 'visitorlogs_id' => $id]);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'response' => false, 'message' => 'Database error']);
        }
    }

    // Visitor Logs: update an existing visitor log entry
    function updateVisitorLog() {
        include "connection.php";
        try {
            $id = isset($_POST['visitorlogs_id']) ? intval($_POST['visitorlogs_id']) : 0;
            if ($id <= 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Invalid visitor log ID']);
                return;
            }

            $name = isset($_POST['visitorlogs_visitorname']) ? trim($_POST['visitorlogs_visitorname']) : null;
            $purpose = isset($_POST['visitorlogs_purpose']) ? trim($_POST['visitorlogs_purpose']) : null;
            $statusId = isset($_POST['visitorapproval_id']) ? intval($_POST['visitorapproval_id']) : null;
            $booking_room_id = isset($_POST['booking_room_id']) ? intval($_POST['booking_room_id']) : null;
            $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
            $checkout = isset($_POST['visitorlogs_checkout_time']) ? trim($_POST['visitorlogs_checkout_time']) : null;

            $fields = [];
            $params = [ ':id' => $id ];
            if ($name !== null) { $fields[] = 'visitorlogs_visitorname = :name'; $params[':name'] = $name; }
            if ($purpose !== null) { $fields[] = 'visitorlogs_purpose = :purpose'; $params[':purpose'] = $purpose; }
            if ($statusId !== null) { $fields[] = 'visitorapproval_id = :statusId'; $params[':statusId'] = $statusId; }
            if ($booking_room_id !== null && $booking_room_id > 0) { $fields[] = 'booking_room_id = :booking_room_id'; $params[':booking_room_id'] = $booking_room_id; }
            if ($employee_id !== null) { $fields[] = 'employee_id = :employee_id'; $params[':employee_id'] = $employee_id; }
            if ($checkout !== null && $checkout !== '') { $fields[] = 'visitorlogs_checkout_time = :checkout'; $params[':checkout'] = $checkout; }

            if (empty($fields)) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                return;
            }

            $sql = "UPDATE tbl_visitorlogs SET " . implode(', ', $fields) . " WHERE visitorlogs_id = :id";
            $stmt = $conn->prepare($sql);
            $ok = $stmt->execute($params);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => $ok === true, 'response' => $ok === true]);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'response' => false, 'message' => 'Database error']);
        }
    }

    function viewAllRooms()
    {
        include "connection.php";
        $stmt = $conn->prepare("SELECT r.roomnumber_id, r.roomfloor, rt.roomtype_id, rt.roomtype_name, rt.roomtype_price, rt.roomtype_description, rt.roomtype_capacity, rt.roomtype_beds, st.status_name, r.room_status_id FROM tbl_rooms r INNER JOIN tbl_roomtype rt ON rt.roomtype_id = r.roomtype_id INNER JOIN tbl_status_types st ON st.status_id = r.room_status_id ORDER BY r.roomnumber_id ASC");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Returns room type master data with image filenames for admin UI
    function view_room_types()
    {
        include "connection.php";
        try {
            $sql = "SELECT 
                        rt.roomtype_id,
                        rt.roomtype_name,
                        rt.roomtype_price,
                        rt.roomtype_description,
                        GROUP_CONCAT(DISTINCT img.imagesroommaster_filename ORDER BY img.imagesroommaster_id ASC) AS images
                    FROM tbl_roomtype rt
                    LEFT JOIN tbl_imagesroommaster img ON img.roomtype_id = rt.roomtype_id
                    GROUP BY rt.roomtype_id, rt.roomtype_name, rt.roomtype_price, rt.roomtype_description
                    ORDER BY rt.roomtype_id ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // Wrapper alias expected by frontend
    function viewRoomTypes()
    {
        $this->view_room_types();
    }

    // Update room type details (price and description)
    function updateRoomType($json)
    {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            
            $roomtype_id = intval($data['roomtype_id'] ?? 0);
            $roomtype_name = trim($data['roomtype_name'] ?? '');
            $roomtype_description = trim($data['roomtype_description'] ?? '');
            $roomtype_price = floatval($data['roomtype_price'] ?? 0);

            if ($roomtype_id <= 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Invalid room type ID']);
                return;
            }

            if (empty($roomtype_name)) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Room type name is required']);
                return;
            }

            if ($roomtype_price <= 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Room type price must be greater than 0']);
                return;
            }

            // Check if room type exists
            $checkStmt = $conn->prepare("SELECT roomtype_id FROM tbl_roomtype WHERE roomtype_id = :roomtype_id");
            $checkStmt->bindParam(':roomtype_id', $roomtype_id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Room type not found']);
                return;
            }

            // Update room type
            $sql = "UPDATE tbl_roomtype SET 
                        roomtype_name = :roomtype_name,
                        roomtype_description = :roomtype_description,
                        roomtype_price = :roomtype_price
                    WHERE roomtype_id = :roomtype_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':roomtype_name', $roomtype_name, PDO::PARAM_STR);
            $stmt->bindParam(':roomtype_description', $roomtype_description, PDO::PARAM_STR);
            $stmt->bindParam(':roomtype_price', $roomtype_price, PDO::PARAM_STR);
            $stmt->bindParam(':roomtype_id', $roomtype_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => true, 'message' => 'Room type updated successfully']);
            } else {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Failed to update room type']);
            }
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Add room type (create)
    function addRoomType($json)
    {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);

            $roomtype_name = trim($data['roomtype_name'] ?? '');
            $roomtype_description = trim($data['roomtype_description'] ?? '');
            $roomtype_price = floatval($data['roomtype_price'] ?? 0);
            $roomtype_capacity = intval($data['roomtype_capacity'] ?? 2);
            $roomtype_beds = intval($data['roomtype_beds'] ?? 1);
            $roomtype_sizes = trim($data['roomtype_sizes'] ?? 'Standard');
            $max_capacity = intval($data['max_capacity'] ?? 4);

            if (empty($roomtype_name)) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Room type name is required']);
                return;
            }
            if ($roomtype_price <= 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Room type price must be greater than 0']);
                return;
            }

            // Duplicate name check (case-insensitive)
            $dupStmt = $conn->prepare("SELECT roomtype_id FROM tbl_roomtype WHERE LOWER(roomtype_name) = LOWER(:name) LIMIT 1");
            $dupStmt->bindParam(':name', $roomtype_name, PDO::PARAM_STR);
            $dupStmt->execute();
            if ($dupStmt->rowCount() > 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Room type already exists']);
                return;
            }

            $sql = "INSERT INTO tbl_roomtype (roomtype_name, roomtype_description, roomtype_price, roomtype_capacity, roomtype_beds, roomtype_sizes, max_capacity) 
                    VALUES (:name, :desc, :price, :capacity, :beds, :sizes, :max_capacity)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':name', $roomtype_name, PDO::PARAM_STR);
            $stmt->bindParam(':desc', $roomtype_description, PDO::PARAM_STR);
            $stmt->bindParam(':price', $roomtype_price, PDO::PARAM_STR);
            $stmt->bindParam(':capacity', $roomtype_capacity, PDO::PARAM_INT);
            $stmt->bindParam(':beds', $roomtype_beds, PDO::PARAM_INT);
            $stmt->bindParam(':sizes', $roomtype_sizes, PDO::PARAM_STR);
            $stmt->bindParam(':max_capacity', $max_capacity, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $new_id = $conn->lastInsertId();
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => true, 'roomtype_id' => intval($new_id)]);
            } else {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Failed to add room type']);
            }
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // New: viewCharges for ChargeMaster page
    function viewCharges()
    {
        include "connection.php";
        try {
            $sql = "SELECT 
                        cm.charges_master_id,
                        cm.charges_master_name,
                        cm.charges_master_price,
                        cm.charges_master_description,
                        cm.charge_name_isRestricted,
                        COALESCE(cm.charges_master_status_id, 1) AS charges_master_status_id,
                        cc.charges_category_id,
                        cc.charges_category_name
                    FROM tbl_charges_master cm
                    LEFT JOIN tbl_charges_category cc ON cc.charges_category_id = cm.charges_category_id
                    ORDER BY cc.charges_category_name ASC, cm.charges_master_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // New: viewChargesCategory for category dropdowns
    function viewChargesCategory()
    {
        include "connection.php";
        try {
            $sql = "SELECT charges_category_id, charges_category_name FROM tbl_charges_category ORDER BY charges_category_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // New: Amenity Management Methods
    function viewAmenities()
    {
        include "connection.php";
        try {
            $sql = "SELECT room_amenities_master_id, room_amenities_master_name 
                    FROM tbl_room_amenities_master 
                    ORDER BY room_amenities_master_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // New: Nationalities for dropdowns in Walk-In flow
    function viewNationalities()
    {
        include "connection.php";
        try {
            $sql = "SELECT nationality_id, nationality_name FROM tbl_nationality ORDER BY nationality_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // New: Payment Methods for PaymentMethod.jsx
    function getAllPayMethods()
    {
        include "connection.php";
        try {
            $sql = "SELECT payment_method_id, payment_method_name FROM tbl_payment_method ORDER BY payment_method_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    function addAmenities()
    {
        include "connection.php";
        try {
            $json = json_decode($_POST['json'], true);
            $amenity_name = $json['amenity_name'];
            
            $sql = "INSERT INTO tbl_room_amenities_master (room_amenities_master_name) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$amenity_name]);
            
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($result ? 1 : 0);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(0);
        }
    }

    function updateAmenities()
    {
        include "connection.php";
        try {
            $json = json_decode($_POST['json'], true);
            $amenity_id = $json['amenity_id'];
            $amenity_name = $json['amenity_name'];
            
            $sql = "UPDATE tbl_room_amenities_master SET room_amenities_master_name = ? WHERE room_amenities_master_id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$amenity_name, $amenity_id]);
            
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($result ? 1 : 0);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(0);
        }
    }

    function disableAmenities()
    {
        include "connection.php";
        try {
            $json = json_decode($_POST['json'], true);
            $amenity_id = $json['amenity_id'];
            
            // Instead of deleting, we could set a status field, but for now we'll delete
            $sql = "DELETE FROM tbl_room_amenities_master WHERE room_amenities_master_id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$amenity_id]);
            
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($result ? 1 : 0);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(0);
        }
    }

    // New: Additional Charge Management Methods
    function addCharges()
    {
        include "connection.php";
        try {
            $json = json_decode($_POST['json'], true);
            $charge_category = $json['charge_category'];
            $charge_name = $json['charge_name'];
            $charge_price = $json['charge_price'];
            $charge_description = $json['charge_description'] ?? '';
            $charge_is_restricted = $json['charge_is_restricted'] ?? 0;
            
            $sql = "INSERT INTO tbl_charges_master (charges_category_id, charges_master_name, charges_master_price, charges_master_description, charge_name_isRestricted, charges_master_status_id) 
                    VALUES (?, ?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$charge_category, $charge_name, $charge_price, $charge_description, $charge_is_restricted]);
            
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($result ? 1 : 0);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(0);
        }
    }

    function updateCharges()
    {
        include "connection.php";
        try {
            $json = json_decode($_POST['json'], true);
            $charges_master_id = $json['charges_master_id'];
            $charge_name = $json['charge_name'];
            $charge_category = $json['charge_category'];
            $charge_price = $json['charge_price'];
            $charge_description = $json['charge_description'] ?? '';
            $charge_is_restricted = $json['charge_is_restricted'] ?? 0;
            
            $sql = "UPDATE tbl_charges_master SET 
                    charges_category_id = ?, 
                    charges_master_name = ?, 
                    charges_master_price = ?, 
                    charges_master_description = ?, 
                    charge_name_isRestricted = ?
                    WHERE charges_master_id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$charge_category, $charge_name, $charge_price, $charge_description, $charge_is_restricted, $charges_master_id]);
            
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($result ? 1 : 0);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(0);
        }
    }

    function disableCharges()
    {
        include "connection.php";
        try {
            $json = json_decode($_POST['json'], true);
            $charges_master_id = $json['charges_master_id'];
            
            // Set status to inactive instead of deleting
            $sql = "UPDATE tbl_charges_master SET charges_master_status_id = 0 WHERE charges_master_id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$charges_master_id]);
            
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($result ? 1 : 0);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(0);
        }
    }

    // New: Discount Management Methods
    function viewDiscounts()
    {
        include "connection.php";
        try {
            $sql = "SELECT discounts_id, discounts_name, discounts_percentage, discounts_amount, discounts_description, discount_start_in, discount_ends_in FROM tbl_discounts ORDER BY discounts_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    function addDiscounts()
    {
        include "connection.php";
        try {
            $json = json_decode($_POST['json'], true);
            $discountName = $json['discountName'] ?? null;
            $discountPercentage = (isset($json['discountPercentage']) && $json['discountPercentage'] !== '') ? floatval($json['discountPercentage']) : null;
            $discountAmount = (isset($json['discountAmount']) && $json['discountAmount'] !== '') ? intval($json['discountAmount']) : null;
            $discountDescription = $json['discountDescription'] ?? null;
            $discountStartIn = $json['discountStartIn'] ?? null;
            $discountEndsIn = $json['discountEndsIn'] ?? null;

            if (!$discountName || ($discountPercentage === null && $discountAmount === null)) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(0);
                return;
            }

            $sql = "INSERT INTO tbl_discounts (discounts_name, discounts_percentage, discounts_amount, discounts_description, discount_start_in, discount_ends_in) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$discountName, $discountPercentage, $discountAmount, $discountDescription, $discountStartIn, $discountEndsIn]);
            
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($result ? 1 : 0);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(0);
        }
    }

    function updateDiscounts()
    {
        include "connection.php";
        try {
            $json = json_decode($_POST['json'], true);
            $discount_id = $json['discount_id'];
            $discountName = $json['discountName'] ?? null;
            $discountPercentage = (isset($json['discountPercentage']) && $json['discountPercentage'] !== '') ? floatval($json['discountPercentage']) : null;
            $discountAmount = (isset($json['discountAmount']) && $json['discountAmount'] !== '') ? intval($json['discountAmount']) : null;
            $discountDescription = $json['discountDescription'] ?? null;
            $discountStartIn = $json['discountStartIn'] ?? null;
            $discountEndsIn = $json['discountEndsIn'] ?? null;

            $sql = "UPDATE tbl_discounts SET discounts_name = ?, discounts_percentage = ?, discounts_amount = ?, discounts_description = ?, discount_start_in = ?, discount_ends_in = ? WHERE discounts_id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$discountName, $discountPercentage, $discountAmount, $discountDescription, $discountStartIn, $discountEndsIn, $discount_id]);
            
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($result ? 1 : 0);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(0);
        }
    }

    function disableDiscounts()
    {
        include "connection.php";
        try {
            $json = json_decode($_POST['json'], true);
            $discount_id = $json['discount_id'];
            
            $sql = "DELETE FROM tbl_discounts WHERE discounts_id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$discount_id]);
            
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($result ? 1 : 0);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(0);
        }
    }

    function viewBookingsEnhanced()
    {
        include "connection.php";
        $q = "
        SELECT DISTINCT
            b.booking_id,
            b.reference_no,
            b.booking_checkin_dateandtime,
            b.booking_checkout_dateandtime,
            b.booking_created_at,
            COALESCE(CONCAT(c.customers_fname, ' ', c.customers_lname),
                     CONCAT(w.customers_walk_in_fname, ' ', w.customers_walk_in_lname)) AS customer_name,
            COALESCE(c.customers_email, w.customers_walk_in_email) AS customer_email,
            COALESCE(c.customers_phone, w.customers_walk_in_phone) AS customer_phone,
            brinfo.room_numbers,
            brinfo.roomtype_name,
            brinfo.roomtype_price,
            CASE 
                WHEN latest_billing.billing_id IS NOT NULL THEN
                    CASE 
                        WHEN latest_invoice.invoice_status_id = 1 THEN 0
                        ELSE COALESCE(latest_billing.billing_balance, 0)
                    END
                ELSE COALESCE(b.booking_totalAmount, 0) - COALESCE(b.booking_payment, 0)
            END AS balance,
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
            SELECT b1.billing_id, b1.booking_id, b1.billing_total_amount, b1.billing_balance, b1.billing_downpayment
            FROM tbl_billing b1
            INNER JOIN (
                SELECT booking_id, MAX(billing_id) AS latest_billing_id
                FROM tbl_billing
                GROUP BY booking_id
            ) latest ON latest.booking_id = b1.booking_id AND latest.latest_billing_id = b1.billing_id
        ) latest_billing ON latest_billing.booking_id = b.booking_id
        LEFT JOIN (
            SELECT i1.invoice_id, i1.billing_id, i1.invoice_status_id
            FROM tbl_invoice i1
            INNER JOIN (
                SELECT billing_id, MAX(invoice_id) AS latest_invoice_id
                FROM tbl_invoice
                GROUP BY billing_id
            ) latest ON latest.billing_id = i1.billing_id AND latest.latest_invoice_id = i1.invoice_id
        ) latest_invoice ON latest_invoice.billing_id = latest_billing.billing_id
        LEFT JOIN (
            SELECT br.booking_id,
                   GROUP_CONCAT(br.roomnumber_id ORDER BY br.booking_room_id SEPARATOR ',') AS room_numbers,
                   MIN(rt.roomtype_name) AS roomtype_name,
                   MIN(rt.roomtype_price) AS roomtype_price
            FROM tbl_booking_room br
            LEFT JOIN tbl_rooms r ON r.roomnumber_id = br.roomnumber_id
            LEFT JOIN tbl_roomtype rt ON rt.roomtype_id = r.roomtype_id
            GROUP BY br.booking_id
        ) brinfo ON brinfo.booking_id = b.booking_id
        ORDER BY b.booking_id DESC
        ";
        $stmt = $conn->prepare($q);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    function getExtendedRooms($json)
    {
        include "connection.php";
        $d = is_string($json) ? json_decode($json, true) : $json;
        $booking_id = intval($d["booking_id"] ?? 0);
        if ($booking_id <= 0) { echo json_encode(["success"=>false,"data"=>[]]); return; }
        $stmt = $conn->prepare("SELECT br.booking_room_id, r.roomnumber_id, rt.roomtype_id, rt.roomtype_name, rt.roomtype_price AS roomtype_price FROM tbl_booking_room br JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id WHERE br.booking_id = :booking_id");
        $stmt->bindParam(":booking_id", $booking_id, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(["success"=>true,"data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // NEW: Unified dataset of booking rooms for Amenity and Visitor pages
    function get_booking_rooms()
    {
        include "connection.php";
        $sql = "
        SELECT
            br.booking_room_id,
            b.booking_id,
            b.reference_no,
            b.booking_checkin_dateandtime,
            b.booking_checkout_dateandtime,
            COALESCE(CONCAT(c.customers_fname, ' ', c.customers_lname),
                     CONCAT(w.customers_walk_in_fname, ' ', w.customers_walk_in_lname)) AS customer_name,
            COALESCE(c.customers_email, w.customers_walk_in_email) AS customers_email,
            COALESCE(c.customers_phone, w.customers_walk_in_phone) AS customers_phone,
            r.roomnumber_id,
            r.roomfloor,
            rt.roomtype_name,
            rt.roomtype_price,
            rt.roomtype_capacity AS max_capacity,
            br.bookingRoom_adult,
            br.bookingRoom_children,
            bs.booking_status_name
        FROM tbl_booking_room br
        INNER JOIN tbl_booking b ON br.booking_id = b.booking_id
        LEFT JOIN tbl_customers c ON b.customers_id = c.customers_id
        LEFT JOIN tbl_customers_walk_in w ON b.customers_walk_in_id = w.customers_walk_in_id
        INNER JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id
        INNER JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id
        INNER JOIN (
            SELECT bh.booking_id, bh.status_id
            FROM tbl_booking_history bh
            INNER JOIN (
                SELECT booking_id, MAX(booking_history_id) AS latest_history_id
                FROM tbl_booking_history
                GROUP BY booking_id
            ) latest ON latest.booking_id = bh.booking_id AND latest.latest_history_id = bh.booking_history_id
        ) latest_hist ON latest_hist.booking_id = b.booking_id
        INNER JOIN tbl_booking_status bs ON bs.booking_status_id = latest_hist.status_id
        WHERE bs.booking_status_name = 'Checked-In'
        ORDER BY b.booking_id DESC, br.booking_room_id ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // NEW: Active booking rooms (Reserved, Checked-In, Pending) for availability checks
    function get_booking_rooms_active()
    {
        include "connection.php";
        $sql = "
        SELECT
            br.booking_room_id,
            b.booking_id,
            b.reference_no,
            b.booking_checkin_dateandtime,
            b.booking_checkout_dateandtime,
            COALESCE(CONCAT(c.customers_fname, ' ', c.customers_lname),
                     CONCAT(w.customers_walk_in_fname, ' ', w.customers_walk_in_lname)) AS customer_name,
            COALESCE(c.customers_email, w.customers_walk_in_email) AS customers_email,
            COALESCE(c.customers_phone, w.customers_walk_in_phone) AS customers_phone,
            r.roomnumber_id,
            r.roomfloor,
            rt.roomtype_name,
            rt.roomtype_price,
            rt.roomtype_capacity AS max_capacity,
            br.bookingRoom_adult,
            br.bookingRoom_children,
            bs.booking_status_name
        FROM tbl_booking_room br
        INNER JOIN tbl_booking b ON br.booking_id = b.booking_id
        LEFT JOIN tbl_customers c ON b.customers_id = c.customers_id
        LEFT JOIN tbl_customers_walk_in w ON b.customers_walk_in_id = w.customers_walk_in_id
        INNER JOIN tbl_rooms r ON br.roomnumber_id = r.roomnumber_id
        INNER JOIN tbl_roomtype rt ON r.roomtype_id = rt.roomtype_id
        INNER JOIN (
            SELECT bh.booking_id, bh.status_id
            FROM tbl_booking_history bh
            INNER JOIN (
                SELECT booking_id, MAX(booking_history_id) AS latest_history_id
                FROM tbl_booking_history
                GROUP BY booking_id
            ) latest ON latest.booking_id = bh.booking_id AND latest.latest_history_id = bh.booking_history_id
        ) latest_hist ON latest_hist.booking_id = b.booking_id
        INNER JOIN tbl_booking_status bs ON bs.booking_status_id = latest_hist.status_id
        WHERE b.booking_isArchive = 0
          AND bs.booking_status_name IN ('Pending','Reserved','Checked-In')
        ORDER BY b.booking_id DESC, br.booking_room_id ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // NEW: Date-aware available rooms count per room type for admin UI
    function getAvailableRoomsCount($json = null)
    {
        include "connection.php";
        try {
            if (!$json && isset($_POST['json'])) { $json = $_POST['json']; }
            $payload = null;
            if ($json) {
                try { $payload = json_decode($json, true); } catch (Exception $e) { $payload = null; }
            }
            $check_in = $payload['check_in'] ?? null;
            $check_out = $payload['check_out'] ?? null;

            if ($check_in && $check_out) {
                $sql = "
                    SELECT 
                        rt.roomtype_id,
                        COUNT(r.roomnumber_id) AS available_count
                    FROM tbl_roomtype rt
                    LEFT JOIN tbl_rooms r
                        ON r.roomtype_id = rt.roomtype_id
                        AND r.room_status_id = 3
                    WHERE (
                        r.roomnumber_id IS NULL
                        OR r.roomnumber_id NOT IN (
                            SELECT br.roomnumber_id
                            FROM tbl_booking_room br
                            INNER JOIN tbl_booking b ON b.booking_id = br.booking_id
                            WHERE b.booking_isArchive = 0
                              AND br.roomnumber_id IS NOT NULL
                              AND (b.booking_checkin_dateandtime < :check_out AND b.booking_checkout_dateandtime > :check_in)
                        )
                    )
                    GROUP BY rt.roomtype_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':check_in', $check_in);
                $stmt->bindParam(':check_out', $check_out);
            } else {
                $sql = "
                    SELECT 
                        rt.roomtype_id,
                        COUNT(r.roomnumber_id) AS available_count
                    FROM tbl_roomtype rt
                    LEFT JOIN tbl_rooms r
                        ON r.roomtype_id = rt.roomtype_id
                        AND r.room_status_id = 3
                    GROUP BY rt.roomtype_id";
                $stmt = $conn->prepare($sql);
            }

            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $by_roomtype = [];
            foreach ($rows as $row) {
                $by_roomtype[$row['roomtype_id']] = intval($row['available_count'] ?? 0);
            }

            $legacy = [
                1 => 'standard_twin_available',
                2 => 'single_available',
                3 => 'double_available',
                4 => 'triple_available',
                5 => 'quadruple_available',
                6 => 'family_a_available',
                7 => 'family_b_available',
                8 => 'family_c_available',
            ];
            $response = ['by_roomtype' => $by_roomtype];
            foreach ($legacy as $id => $key) {
                $response[$key] = isset($by_roomtype[$id]) ? $by_roomtype[$id] : 0;
            }

            if (ob_get_length()) { ob_clean(); }
            echo json_encode($response);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['by_roomtype' => new stdClass()]);
        }
    }

    // New: change booking status and sync room occupancy
    function changeBookingStatus($json)
    {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $booking_id = intval($data['booking_id'] ?? 0);
            $employee_id = intval($data['employee_id'] ?? 0);
            $status_id = intval($data['booking_status_id'] ?? 0);
            $status_name = isset($data['booking_status_name']) ? trim($data['booking_status_name']) : null;
            $provided_room_ids = (isset($data['room_ids']) && is_array($data['room_ids'])) ? $data['room_ids'] : null;

            // Allow either status_id or status_name
            if ($booking_id <= 0 || $employee_id <= 0 || ($status_id <= 0 && empty($status_name))) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                return;
            }

            // If only status_name provided, map to id; if id provided, fetch canonical name
            if ($status_id <= 0 && !empty($status_name)) {
                $sidStmt = $conn->prepare("SELECT booking_status_id FROM tbl_booking_status WHERE LOWER(booking_status_name) = LOWER(:name) LIMIT 1");
                $sidStmt->bindParam(':name', $status_name, PDO::PARAM_STR);
                $sidStmt->execute();
                $sidRow = $sidStmt->fetch(PDO::FETCH_ASSOC);
                $status_id = $sidRow ? intval($sidRow['booking_status_id']) : 0;
            } else if ($status_id > 0 && empty($status_name)) {
                $snameStmt = $conn->prepare("SELECT booking_status_name FROM tbl_booking_status WHERE booking_status_id = :sid LIMIT 1");
                $snameStmt->bindParam(':sid', $status_id, PDO::PARAM_INT);
                $snameStmt->execute();
                $snameRow = $snameStmt->fetch(PDO::FETCH_ASSOC);
                $status_name = $snameRow ? $snameRow['booking_status_name'] : null;
            }

            // Normalize for downstream logic
            $nm = strtolower(trim($status_name ?? ''));

            // Fallback synonym mapping if status_id unresolved
            if ($status_id <= 0 && !empty($nm)) {
                $rows = $conn->query("SELECT booking_status_id, booking_status_name FROM tbl_booking_status")->fetchAll(PDO::FETCH_ASSOC);
                $synCheckIn = ['checked-in','checked in','check-in','check in'];
                $synCheckOut = ['checked-out','checked out','check-out','check out'];
                $synCancelled = ['cancelled','canceled'];
                foreach ($rows as $r) {
                    $rn = strtolower(trim($r['booking_status_name']));
                    if (in_array($rn, $synCheckIn) && in_array($nm, $synCheckIn)) { $status_id = intval($r['booking_status_id']); break; }
                    if (in_array($rn, $synCheckOut) && in_array($nm, $synCheckOut)) { $status_id = intval($r['booking_status_id']); break; }
                    if (in_array($rn, $synCancelled) && in_array($nm, $synCancelled)) { $status_id = intval($r['booking_status_id']); break; }
                }
            }

            // Validate employee (non-blocking). If invalid, fallback to first active employee or proceed.
            $empStmt = $conn->prepare("SELECT employee_id, employee_status FROM tbl_employee WHERE employee_id = :employee_id");
            $empStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $empStmt->execute();
            $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
            if (!$empRow || $empRow['employee_status'] == 0 || $empRow['employee_status'] === 'Inactive' || $empRow['employee_status'] === 'Disabled') {
                try {
                    $fallback = $conn->prepare("SELECT employee_id FROM tbl_employee WHERE employee_status NOT IN ('Inactive','Disabled',0,'0') ORDER BY employee_id ASC LIMIT 1");
                    $fallback->execute();
                    $fb = $fallback->fetch(PDO::FETCH_ASSOC);
                    if ($fb && isset($fb['employee_id'])) {
                        $employee_id = intval($fb['employee_id']);
                    }
                } catch (Exception $e) {
                    // Continue without blocking
                }
            }

            // Validate booking
            $bookStmt = $conn->prepare("SELECT booking_id FROM tbl_booking WHERE booking_id = :booking_id");
            $bookStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $bookStmt->execute();
            $bookRow = $bookStmt->fetch(PDO::FETCH_ASSOC);
            if (!$bookRow) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
                return;
            }

            // Determine room ids
            $room_ids = [];
            if (is_array($provided_room_ids) && count($provided_room_ids) > 0) {
                $room_ids = array_map('intval', $provided_room_ids);
            } else {
                $roomStmt = $conn->prepare("SELECT roomnumber_id FROM tbl_booking_room WHERE booking_id = :booking_id AND roomnumber_id IS NOT NULL");
                $roomStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
                $roomStmt->execute();
                $room_ids = array_map(function($r){ return intval($r['roomnumber_id']); }, $roomStmt->fetchAll(PDO::FETCH_ASSOC));
            }

            $conn->beginTransaction();

            // Insert status history
            $hist = $conn->prepare("INSERT INTO tbl_booking_history (booking_id, employee_id, status_id, updated_at) VALUES (:booking_id, :employee_id, :status_id, NOW())");
            $hist->bindParam(':booking_id', $booking_id);
            $hist->bindParam(':employee_id', $employee_id);
            $hist->bindParam(':status_id', $status_id);
            $hist->execute();

            // Fallback: on Checked-In, auto-assign vacant rooms to booking if none assigned
            if (in_array($nm, ['checked-in','checked in']) && empty($room_ids)) {
                $missingStmt = $conn->prepare("SELECT booking_room_id, roomtype_id FROM tbl_booking_room WHERE booking_id = :booking_id AND roomnumber_id IS NULL");
                $missingStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
                $missingStmt->execute();
                $missingRows = $missingStmt->fetchAll(PDO::FETCH_ASSOC);

                $allocated = [];
                foreach ($missingRows as $mr) {
                    $rt = intval($mr['roomtype_id']);
                    // Find first vacant room of this type
                    $vacantStmt = $conn->prepare("SELECT roomnumber_id FROM tbl_rooms WHERE roomtype_id = :rt AND room_status_id = 3 ORDER BY roomnumber_id ASC LIMIT 1");
                    $vacantStmt->bindParam(':rt', $rt, PDO::PARAM_INT);
                    $vacantStmt->execute();
                    $vac = $vacantStmt->fetch(PDO::FETCH_ASSOC);
                    if ($vac && isset($vac['roomnumber_id'])) {
                        $rn = intval($vac['roomnumber_id']);
                        // Assign to booking_room
                        $assignStmt = $conn->prepare("UPDATE tbl_booking_room SET roomnumber_id = :rn WHERE booking_room_id = :br_id");
                        $assignStmt->execute([':rn' => $rn, ':br_id' => intval($mr['booking_room_id'])]);
                        $allocated[] = $rn;
                    }
                }
                if (!empty($allocated)) {
                    $room_ids = $allocated; // use newly assigned room numbers
                }
            }

            // Sync room occupancy based on status - using canonical status name
            if (!empty($room_ids)) {
                $isCheckIn = in_array($nm, ['checked-in','checked in']);
                $isVacate = in_array($nm, ['checked-out','checked out','cancelled']);

                $roomUpdateStatus = null;
                if ($isCheckIn) {
                    $roomUpdateStatus = 1; // Occupied
                } else if ($isVacate) {
                    $roomUpdateStatus = 3; // Vacant
                }
                if ($roomUpdateStatus !== null) {
                    $update = $conn->prepare("UPDATE tbl_rooms SET room_status_id = :rs WHERE roomnumber_id = :rn");
                    foreach ($room_ids as $rn) {
                        $result = $update->execute([':rs' => $roomUpdateStatus, ':rn' => $rn]);
                        if (!$result) {
                            error_log("Failed to update room status for room $rn to status $roomUpdateStatus");
                        }
                    }
                }
            }

            // Final safeguard: sync by booking join in case no room_ids were passed/found
            // This ensures room status is ALWAYS updated regardless of triggers
            // Recompute dynamic status IDs locally to avoid scope issues
            $stRows2 = $conn->query("SELECT booking_status_id, booking_status_name FROM tbl_booking_status")->fetchAll(PDO::FETCH_ASSOC);
            $findId2 = function($rows, $cands) {
                foreach ($rows as $r) {
                    $name = strtolower(trim($r['booking_status_name']));
                    foreach ($cands as $c) {
                        if ($name === strtolower($c)) { return intval($r['booking_status_id']); }
                    }
                }
                return null;
            };
            $isCheckIn2 = in_array($nm, ['checked-in','checked in']);
            $isVacate2 = in_array($nm, ['checked-out','checked out','cancelled']);

            if ($isCheckIn2 || $isVacate2) {
                $roomUpdateStatus = ($isCheckIn2) ? 1 : 3;
                $joinUpdate = $conn->prepare(
                    "UPDATE tbl_rooms r 
                     JOIN tbl_booking_room br ON br.roomnumber_id = r.roomnumber_id 
                     SET r.room_status_id = :rs 
                     WHERE br.booking_id = :booking_id 
                       AND br.roomnumber_id IS NOT NULL"
                );
                $result = $joinUpdate->execute([':rs' => $roomUpdateStatus, ':booking_id' => $booking_id]);
                $affectedRows = $joinUpdate->rowCount();
                
                if (!$result) {
                    error_log("Failed to update room status via join for booking $booking_id");
                } else {
                    error_log("Updated $affectedRows room(s) to status $roomUpdateStatus for booking $booking_id");
                }
            }

            $conn->commit();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollBack();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    function getCustomerInvoice($json)
    {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $reference_no = $data['reference_no'] ?? '';
            $booking_id = $data['booking_id'] ?? 0;

            if (empty($reference_no) && empty($booking_id)) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Missing reference number or booking ID']);
                return;
            }

            // Build query based on available parameter
            if (!empty($reference_no)) {
                $query = "
                SELECT 
                    i.invoice_id,
                    i.invoice_date,
                    i.invoice_time,
                    i.invoice_total_amount,
                    i.invoice_status_id,
                    b.billing_id,
                    b.billing_total_amount,
                    b.billing_balance,
                    b.billing_downpayment,
                    b.billing_vat,
                    bk.booking_id,
                    bk.reference_no,
                    pm.payment_method_name,
                    CONCAT(e.employee_fname, ' ', e.employee_lname) AS employee_name
                FROM tbl_invoice i
                LEFT JOIN tbl_billing b ON i.billing_id = b.billing_id
                LEFT JOIN tbl_booking bk ON b.booking_id = bk.booking_id
                LEFT JOIN tbl_payment_method pm ON i.payment_method_id = pm.payment_method_id
                LEFT JOIN tbl_employee e ON i.employee_id = e.employee_id
                WHERE bk.reference_no = :reference_no
                ORDER BY i.invoice_id DESC
                LIMIT 1
                ";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':reference_no', $reference_no);
            } else {
                $query = "
                SELECT 
                    i.invoice_id,
                    i.invoice_date,
                    i.invoice_time,
                    i.invoice_total_amount,
                    i.invoice_status_id,
                    b.billing_id,
                    b.billing_total_amount,
                    b.billing_balance,
                    b.billing_downpayment,
                    b.billing_vat,
                    bk.booking_id,
                    bk.reference_no,
                    pm.payment_method_name,
                    CONCAT(e.employee_fname, ' ', e.employee_lname) AS employee_name
                FROM tbl_invoice i
                LEFT JOIN tbl_billing b ON i.billing_id = b.billing_id
                LEFT JOIN tbl_booking bk ON b.booking_id = bk.booking_id
                LEFT JOIN tbl_payment_method pm ON i.payment_method_id = pm.payment_method_id
                LEFT JOIN tbl_employee e ON i.employee_id = e.employee_id
                WHERE bk.booking_id = :booking_id
                ORDER BY i.invoice_id DESC
                LIMIT 1
                ";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            }

            $stmt->execute();
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (ob_get_length()) { ob_clean(); }
            if ($invoice) {
                echo json_encode(['success' => true, 'invoice_data' => $invoice]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No invoice found']);
            }
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => 'Error fetching invoice data']);
        }
    }

    function getCustomerBilling($json)
    {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $reference_no = $data['reference_no'] ?? '';
            $booking_id = $data['booking_id'] ?? 0;

            if (empty($reference_no) && empty($booking_id)) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Missing reference number or booking ID']);
                return;
            }

            // Build query based on available parameter
            if (!empty($reference_no)) {
                $query = "
                SELECT 
                    b.billing_id,
                    b.billing_dateandtime,
                    b.billing_invoice_number,
                    b.billing_total_amount,
                    b.billing_balance,
                    b.billing_downpayment,
                    b.billing_vat,
                    bk.booking_id,
                    bk.reference_no,
                    pm.payment_method_name,
                    CONCAT(e.employee_fname, ' ', e.employee_lname) AS employee_name
                FROM tbl_billing b
                LEFT JOIN tbl_booking bk ON b.booking_id = bk.booking_id
                LEFT JOIN tbl_payment_method pm ON b.payment_method_id = pm.payment_method_id
                LEFT JOIN tbl_employee e ON b.employee_id = e.employee_id
                WHERE bk.reference_no = :reference_no
                ORDER BY b.billing_id DESC
                ";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':reference_no', $reference_no);
            } else {
                $query = "
                SELECT 
                    b.billing_id,
                    b.billing_dateandtime,
                    b.billing_invoice_number,
                    b.billing_total_amount,
                    b.billing_balance,
                    b.billing_downpayment,
                    b.billing_vat,
                    bk.booking_id,
                    bk.reference_no,
                    pm.payment_method_name,
                    CONCAT(e.employee_fname, ' ', e.employee_lname) AS employee_name
                FROM tbl_billing b
                LEFT JOIN tbl_booking bk ON b.booking_id = bk.booking_id
                LEFT JOIN tbl_payment_method pm ON b.payment_method_id = pm.payment_method_id
                LEFT JOIN tbl_employee e ON b.employee_id = e.employee_id
                WHERE bk.booking_id = :booking_id
                ORDER BY b.billing_id DESC
                ";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            }

            $stmt->execute();
            $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true, 'billing_data' => $billings]);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => 'Error fetching billing data']);
        }
    }

    function login($json)
    {
        include "connection.php";
        $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if ($username === '' || $password === '') {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => 'Missing username or password']);
            return;
        }

        try {
            $sql = "SELECT e.*, ul.userlevel_name
                    FROM tbl_employee e
                    LEFT JOIN tbl_user_level ul ON ul.userlevel_id = e.employee_user_level_id
                    WHERE e.employee_username = :u OR e.employee_email = :u
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':u', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
                return;
            }

            $stored = $user['employee_password'] ?? '';
            $verified = false;
            if (is_string($stored) && password_get_info($stored)['algo'] !== null) {
                $verified = password_verify($password, $stored);
            } else {
                $verified = ($password === $stored);
            }

            if (!$verified) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
                return;
            }

            $status = $user['employee_status'] ?? null;
            if ($status === 0 || $status === 'Inactive' || $status === 'Disabled') {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Account is inactive']);
                return;
            }

            unset($user['employee_password']);
            $roleName = strtolower(trim($user['userlevel_name'] ?? ''));
            $user_type = ($roleName === 'admin') ? 'admin' : 'front-desk';

            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true, 'user' => $user, 'user_type' => $user_type]);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => 'Login error']);
        }
    }

    function getAdminProfile($json)
    {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $employee_id = intval($data['employee_id'] ?? 0);

            if ($employee_id <= 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
                return;
            }

            $sql = "SELECT e.*, ul.userlevel_name
                    FROM tbl_employee e
                    LEFT JOIN tbl_user_level ul ON ul.userlevel_id = e.employee_user_level_id
                    WHERE e.employee_id = :employee_id AND e.employee_status = 1
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
                return;
            }

            // Remove password from response
            unset($user['employee_password']);

            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true, 'data' => $user]);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => 'Error fetching profile']);
        }
    }

    function updateAdminProfile($json)
    {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $employee_id = intval($data['employee_id'] ?? 0);

            if ($employee_id <= 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
                return;
            }

            // Check if employee exists and is active
            $checkSql = "SELECT employee_id, employee_password FROM tbl_employee WHERE employee_id = :employee_id AND employee_status = 1";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $checkStmt->execute();
            $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingUser) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
                return;
            }

            // Handle password change if provided
            $updateFields = [];
            $params = [':employee_id' => $employee_id];

            if (!empty($data['current_password']) && !empty($data['new_password'])) {
                // Verify current password
                $stored = $existingUser['employee_password'];
                $verified = false;
                if (is_string($stored) && password_get_info($stored)['algo'] !== null) {
                    $verified = password_verify($data['current_password'], $stored);
                } else {
                    $verified = ($data['current_password'] === $stored);
                }

                if (!$verified) {
                    if (ob_get_length()) { ob_clean(); }
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                    return;
                }

                // Hash new password
                $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
                $updateFields[] = "employee_password = :password";
                $params[':password'] = $hashedPassword;
            }

            // Update other fields, including 2FA authentication status
            $fieldsToUpdate = [
                'employee_fname', 'employee_lname', 'employee_username',
                'employee_email', 'employee_phone', 'employee_address',
                'employee_birthdate', 'employee_gender',
                'employee_online_authentication_status'
            ];

            foreach ($fieldsToUpdate as $field) {
                if (isset($data[$field])) {
                    if ($field === 'employee_online_authentication_status') {
                        $val = intval($data[$field]);
                        $val = ($val === 1) ? 1 : 0; // clamp to 0/1
                        $updateFields[] = "$field = :$field";
                        $params[":$field"] = $val;
                    } else {
                        $updateFields[] = "$field = :$field";
                        $params[":$field"] = $data[$field];
                    }
                }
            }

            if (empty($updateFields)) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                return;
            }

            // Add updated timestamp
            $updateFields[] = "employee_updated_at = NOW()";

            $sql = "UPDATE tbl_employee SET " . implode(', ', $updateFields) . " WHERE employee_id = :employee_id";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute($params)) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            } else {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
            }
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => 'Error updating profile']);
        }
    }

    function getAllTransactionHistories($json)
    {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $limit = intval($data['limit'] ?? 50);
            $offset = intval($data['offset'] ?? 0);
            $type = strtolower(trim($data['transaction_type'] ?? 'all'));
            $status_filter = strtolower(trim($data['status_filter'] ?? 'all'));
            $date_from = $data['date_from'] ?? null;
            $date_to = $data['date_to'] ?? null;
            $viewer_user_type = strtolower(trim($data['viewer_user_type'] ?? 'admin'));
            $viewer_employee_id = intval($data['viewer_employee_id'] ?? 0);

            // Build conditions for Billing
            $conditionsBilling = [];
            $paramsBilling = [];
            if ($viewer_user_type !== 'admin' && $viewer_employee_id > 0) {
                $conditionsBilling[] = "b.employee_id = :viewer_emp_b";
                $paramsBilling[':viewer_emp_b'] = $viewer_employee_id;
            }
            if (!empty($date_from)) {
                $conditionsBilling[] = "b.billing_dateandtime >= :from_b";
                $paramsBilling[':from_b'] = $date_from . " 00:00:00";
            }
            if (!empty($date_to)) {
                $conditionsBilling[] = "b.billing_dateandtime <= :to_b";
                $paramsBilling[':to_b'] = $date_to . " 23:59:59";
            }
            if ($status_filter === 'pending') {
                $conditionsBilling[] = "COALESCE(b.billing_balance, 0) > 0";
            } else if ($status_filter === 'approved' || $status_filter === 'active') {
                $conditionsBilling[] = "COALESCE(b.billing_balance, 0) = 0";
            }
            $whereBilling = count($conditionsBilling) ? ("WHERE " . implode(" AND ", $conditionsBilling)) : "";

            $sqlBilling = "SELECT 
                b.billing_id,
                b.billing_dateandtime,
                b.billing_total_amount,
                b.billing_balance,
                b.billing_downpayment,
                b.billing_invoice_number,
                pm.payment_method_name,
                e.employee_id,
                CONCAT(e.employee_fname, ' ', e.employee_lname) AS employee_name,
                bk.booking_id,
                bk.reference_no,
                COALESCE(CONCAT(c.customers_fname, ' ', c.customers_lname),
                         CONCAT(w.customers_walk_in_fname, ' ', w.customers_walk_in_lname)) AS customer_name,
                COALESCE(c.customers_email, w.customers_walk_in_email) AS customer_email
            FROM tbl_billing b
            LEFT JOIN tbl_booking bk ON bk.booking_id = b.booking_id
            LEFT JOIN tbl_customers c ON bk.customers_id = c.customers_id
            LEFT JOIN tbl_customers_walk_in w ON bk.customers_walk_in_id = w.customers_walk_in_id
            LEFT JOIN tbl_payment_method pm ON pm.payment_method_id = b.payment_method_id
            LEFT JOIN tbl_employee e ON e.employee_id = b.employee_id
            $whereBilling
            ORDER BY b.billing_dateandtime DESC";

            $stmtB = $conn->prepare($sqlBilling);
            foreach ($paramsBilling as $k => $v) { $stmtB->bindValue($k, $v); }
            $stmtB->execute();
            $rowsB = $stmtB->fetchAll(PDO::FETCH_ASSOC);

            $billingList = array_map(function($r) {
                $balanced = floatval($r['billing_balance'] ?? 0);
                $status = ($balanced > 0) ? 'Pending' : 'Approved';
                $status_color = ($balanced > 0) ? 'warning' : 'success';
                return [
                    'transaction_type' => 'billing',
                    'transaction_id' => intval($r['billing_id']),
                    'target_table' => 'tbl_billing',
                    'target_id' => intval($r['billing_id']),
                    'reference_no' => ($r['reference_no'] ?? $r['billing_invoice_number']),
                    'customer_name' => $r['customer_name'] ?? null,
                    'customer_email' => $r['customer_email'] ?? null,
                    'employee_id' => isset($r['employee_id']) ? intval($r['employee_id']) : null,
                    'employee_name' => $r['employee_name'] ?? null,
                    'payment_method_name' => $r['payment_method_name'] ?? null,
                    'amount' => $r['billing_total_amount'] ?? 0,
                    'status' => $status,
                    'status_color' => $status_color,
                    'transaction_date' => $r['billing_dateandtime'] ?? null,
                    'source_type' => 'billing'
                ];
            }, $rowsB);

            // Build conditions for Invoice
            $conditionsInv = [];
            $paramsInv = [];
            if ($viewer_user_type !== 'admin' && $viewer_employee_id > 0) {
                $conditionsInv[] = "i.employee_id = :viewer_emp_i";
                $paramsInv[':viewer_emp_i'] = $viewer_employee_id;
            }
            if (!empty($date_from)) {
                $conditionsInv[] = "CONCAT(i.invoice_date, ' ', i.invoice_time) >= :from_i";
                $paramsInv[':from_i'] = $date_from . " 00:00:00";
            }
            if (!empty($date_to)) {
                $conditionsInv[] = "CONCAT(i.invoice_date, ' ', i.invoice_time) <= :to_i";
                $paramsInv[':to_i'] = $date_to . " 23:59:59";
            }
            if ($status_filter === 'pending') {
                $conditionsInv[] = "i.invoice_status_id != 1";
            } else if ($status_filter === 'approved' || $status_filter === 'active') {
                $conditionsInv[] = "i.invoice_status_id = 1";
            }
            $whereInv = count($conditionsInv) ? ("WHERE " . implode(" AND ", $conditionsInv)) : "";

            $sqlInv = "SELECT 
                i.invoice_id,
                i.invoice_total_amount,
                i.invoice_status_id,
                i.invoice_date,
                i.invoice_time,
                pm.payment_method_name,
                e.employee_id,
                CONCAT(e.employee_fname, ' ', e.employee_lname) AS employee_name,
                b.billing_id,
                b.billing_invoice_number,
                bk.booking_id,
                bk.reference_no,
                COALESCE(CONCAT(c.customers_fname, ' ', c.customers_lname),
                         CONCAT(w.customers_walk_in_fname, ' ', w.customers_walk_in_lname)) AS customer_name,
                COALESCE(c.customers_email, w.customers_walk_in_email) AS customer_email
            FROM tbl_invoice i
            LEFT JOIN tbl_billing b ON b.billing_id = i.billing_id
            LEFT JOIN tbl_booking bk ON bk.booking_id = b.booking_id
            LEFT JOIN tbl_customers c ON bk.customers_id = c.customers_id
            LEFT JOIN tbl_customers_walk_in w ON bk.customers_walk_in_id = w.customers_walk_in_id
            LEFT JOIN tbl_payment_method pm ON pm.payment_method_id = i.payment_method_id
            LEFT JOIN tbl_employee e ON e.employee_id = i.employee_id
            $whereInv
            ORDER BY i.invoice_id DESC";

            $stmtI = $conn->prepare($sqlInv);
            foreach ($paramsInv as $k => $v) { $stmtI->bindValue($k, $v); }
            $stmtI->execute();
            $rowsI = $stmtI->fetchAll(PDO::FETCH_ASSOC);

            $invoiceList = array_map(function($r) {
                $approved = intval($r['invoice_status_id'] ?? 0) === 1;
                $status = $approved ? 'Approved' : 'Pending';
                $status_color = $approved ? 'success' : 'warning';
                $datetime = trim(($r['invoice_date'] ?? '') . ' ' . ($r['invoice_time'] ?? ''));
                return [
                    'transaction_type' => 'invoice',
                    'transaction_id' => intval($r['invoice_id']),
                    'target_table' => 'tbl_invoice',
                    'target_id' => intval($r['invoice_id']),
                    'reference_no' => ($r['reference_no'] ?? $r['billing_invoice_number']),
                    'customer_name' => $r['customer_name'] ?? null,
                    'customer_email' => $r['customer_email'] ?? null,
                    'employee_id' => isset($r['employee_id']) ? intval($r['employee_id']) : null,
                    'employee_name' => $r['employee_name'] ?? null,
                    'payment_method_name' => $r['payment_method_name'] ?? null,
                    'amount' => $r['invoice_total_amount'] ?? 0,
                    'status' => $status,
                    'status_color' => $status_color,
                    'transaction_date' => $datetime,
                    'source_type' => 'invoice'
                ];
            }, $rowsI);

            $combined = array_merge($billingList, $invoiceList);

            // Server-side filter by transaction_type when specified
            if ($type === 'billing') {
                $combined = array_values(array_filter($combined, function($t){ return ($t['transaction_type'] ?? '') === 'billing'; }));
            } else if ($type === 'invoice') {
                $combined = array_values(array_filter($combined, function($t){ return ($t['transaction_type'] ?? '') === 'invoice'; }));
            }

            // Sort by date desc (strings compare OK if YYYY-MM-DD HH:MM:SS)
            usort($combined, function($a, $b) {
                return strcmp(($b['transaction_date'] ?? ''), ($a['transaction_date'] ?? ''));
            });

            $total = count($combined);
            if ($limit > 0) {
                $combined = array_slice($combined, $offset, $limit);
            }

            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true, 'transactions' => $combined, 'total_count' => $total]);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => 'Error fetching transactions']);
        }
    }
    // Amenity endpoints
    // 1) List available charges (amenities)
    function get_available_charges() {
        include "connection.php";
        try {
            $sql = "SELECT cm.charges_master_id, cm.charges_category_id, cm.charges_master_name, cm.charges_master_price, cm.charges_master_description, cc.charges_category_name
                    FROM tbl_charges_master cm
                    LEFT JOIN tbl_charges_category cc ON cc.charges_category_id = cm.charges_category_id
                    ORDER BY cc.charges_category_name ASC, cm.charges_master_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // 2) Add amenity request(s) for a booking room
    // Expects $json: { booking_room_id, amenities: [{charges_master_id, booking_charges_price, booking_charges_quantity}], booking_charge_status }
    function add_amenity_request($json) {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : $json;
            if (!is_array($data)) { $data = []; }
            $booking_room_id = intval($data['booking_room_id'] ?? 0);
            $amenities = $data['amenities'] ?? [];
            $status = intval($data['booking_charge_status'] ?? 1); // 1=pending,2=approved,3=rejected

            if ($booking_room_id <= 0 || !is_array($amenities) || count($amenities) === 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Invalid payload']);
                return;
            }

            $conn->beginTransaction();
            $insert = $conn->prepare("INSERT INTO tbl_booking_charges (charges_master_id, booking_room_id, booking_charges_price, booking_charges_quantity, booking_charges_total, booking_charge_status, booking_charge_datetime, booking_return_datetime)
                                       VALUES (:cmid, :brid, :price, :qty, :total, :status, NOW(), NOW())");

            foreach ($amenities as $a) {
                $cmid = intval($a['charges_master_id'] ?? 0);
                $price = floatval($a['booking_charges_price'] ?? 0);
                $qty = intval($a['booking_charges_quantity'] ?? 1);
                $total = $price * $qty;
                if ($cmid <= 0 || $qty <= 0) { continue; }
                $insert->execute([
                    ':cmid' => $cmid,
                    ':brid' => $booking_room_id,
                    ':price' => $price,
                    ':qty' => $qty,
                    ':total' => $total,
                    ':status' => $status
                ]);
            }

            $conn->commit();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($conn && $conn->inTransaction()) { $conn->rollBack(); }
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false]);
        }
    }

    // 3) Fetch amenity requests with rich details for UI
    function get_amenity_requests() {
        include "connection.php";
        try {
            $sql = "SELECT 
                        bc.booking_charges_id AS request_id,
                        b.booking_id,
                        b.reference_no,
                        COALESCE(CONCAT(c.customers_fname,' ',c.customers_lname), CONCAT(w.customers_walk_in_fname,' ',w.customers_walk_in_lname), 'Unknown') AS customer_name,
                        COALESCE(c.customers_email, w.customers_walk_in_email, '') AS customer_email,
                        COALESCE(c.customers_phone, w.customers_walk_in_phone, '') AS customer_phone,
                        rt.roomtype_name,
                        r.roomnumber_id AS roomnumber_name,
                        cm.charges_master_name,
                        cc.charges_category_name,
                        bc.booking_charges_quantity AS request_quantity,
                        bc.booking_charges_price AS request_price,
                        bc.booking_charges_total AS request_total,
                        CASE bc.booking_charge_status WHEN 1 THEN 'pending' WHEN 2 THEN 'approved' WHEN 3 THEN 'rejected' ELSE 'pending' END AS request_status,
                        bc.booking_charge_datetime AS requested_at,
                        CASE WHEN bc.booking_charge_status IN (2,3) THEN bc.booking_return_datetime ELSE NULL END AS processed_at,
                        bn.booking_c_notes AS admin_notes,
                        NULL AS customer_notes
                    FROM tbl_booking_charges bc
                    LEFT JOIN tbl_booking_charges_notes bn ON bn.booking_c_notes_id = bc.booking_charges_notes_id
                    LEFT JOIN tbl_booking_room br ON br.booking_room_id = bc.booking_room_id
                    LEFT JOIN tbl_booking b ON b.booking_id = br.booking_id
                    LEFT JOIN tbl_rooms r ON r.roomnumber_id = br.roomnumber_id
                    LEFT JOIN tbl_roomtype rt ON rt.roomtype_id = r.roomtype_id
                    LEFT JOIN tbl_customers c ON b.customers_id = c.customers_id
                    LEFT JOIN tbl_customers_walk_in w ON b.customers_walk_in_id = w.customers_walk_in_id
                    LEFT JOIN tbl_charges_master cm ON cm.charges_master_id = bc.charges_master_id
                    LEFT JOIN tbl_charges_category cc ON cc.charges_category_id = cm.charges_category_id
                    ORDER BY bc.booking_charge_datetime DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // 4) Stats for cards
    function get_amenity_request_stats() {
        include "connection.php";
        try {
            $total = intval($conn->query("SELECT COUNT(*) FROM tbl_booking_charges")->fetchColumn());
            $pending = intval($conn->query("SELECT COUNT(*) FROM tbl_booking_charges WHERE booking_charge_status=1")->fetchColumn());
            $approved = intval($conn->query("SELECT COUNT(*) FROM tbl_booking_charges WHERE booking_charge_status=2")->fetchColumn());
            $rejected = intval($conn->query("SELECT COUNT(*) FROM tbl_booking_charges WHERE booking_charge_status=3")->fetchColumn());

            $pending_amt = floatval($conn->query("SELECT COALESCE(SUM(booking_charges_total),0) FROM tbl_booking_charges WHERE booking_charge_status=1")->fetchColumn());
            $approved_amt = floatval($conn->query("SELECT COALESCE(SUM(booking_charges_total),0) FROM tbl_booking_charges WHERE booking_charge_status=2")->fetchColumn());
            $curr_month_approved = floatval($conn->query("SELECT COALESCE(SUM(booking_charges_total),0) FROM tbl_booking_charges WHERE booking_charge_status=2 AND YEAR(booking_charge_datetime)=YEAR(CURDATE()) AND MONTH(booking_charge_datetime)=MONTH(CURDATE())")->fetchColumn());

            $resp = [
                'total_requests' => $total,
                'pending_requests' => $pending,
                'approved_requests' => $approved,
                'rejected_requests' => $rejected,
                'pending_amount' => $pending_amt,
                'approved_amount' => $approved_amt,
                'current_month_approved' => $curr_month_approved
            ];
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($resp);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['total_requests'=>0,'pending_requests'=>0,'approved_requests'=>0,'rejected_requests'=>0,'pending_amount'=>0,'approved_amount'=>0,'current_month_approved'=>0]);
        }
    }

    // 5) Approve amenity request
    function approve_amenity_request($json) {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : $json;
            $request_id = intval($data['request_id'] ?? 0);
            $admin_notes = trim($data['admin_notes'] ?? '');
            if ($request_id <= 0) { if (ob_get_length()) { ob_clean(); } echo 0; return; }

            $conn->beginTransaction();

            $note_id = null;
            if ($admin_notes !== '') {
                $stmtN = $conn->prepare("INSERT INTO tbl_booking_charges_notes (booking_c_notes) VALUES (:notes)");
                $stmtN->execute([':notes' => $admin_notes]);
                $note_id = $conn->lastInsertId();
            }

            $stmt = $conn->prepare("UPDATE tbl_booking_charges SET booking_charge_status=2, booking_return_datetime=NOW()" . ($note_id ? ", booking_charges_notes_id=:nid" : "") . " WHERE booking_charges_id=:id");
            $params = [':id' => $request_id];
            if ($note_id) { $params[':nid'] = $note_id; }
            $stmt->execute($params);

            $conn->commit();
            if (ob_get_length()) { ob_clean(); }
            echo 1;
        } catch (Exception $e) {
            if ($conn && $conn->inTransaction()) { $conn->rollBack(); }
            if (ob_get_length()) { ob_clean(); }
            echo 0;
        }
    }

    // 6) Reject amenity request
    function reject_amenity_request($json) {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : $json;
            $request_id = intval($data['request_id'] ?? 0);
            $admin_notes = trim($data['admin_notes'] ?? '');
            if ($request_id <= 0) { if (ob_get_length()) { ob_clean(); } echo 0; return; }

            $conn->beginTransaction();

            $note_id = null;
            if ($admin_notes !== '') {
                $stmtN = $conn->prepare("INSERT INTO tbl_booking_charges_notes (booking_c_notes) VALUES (:notes)");
                $stmtN->execute([':notes' => $admin_notes]);
                $note_id = $conn->lastInsertId();
            }

            $stmt = $conn->prepare("UPDATE tbl_booking_charges SET booking_charge_status=3, booking_return_datetime=NOW()" . ($note_id ? ", booking_charges_notes_id=:nid" : "") . " WHERE booking_charges_id=:id");
            $params = [':id' => $request_id];
            if ($note_id) { $params[':nid'] = $note_id; }
            $stmt->execute($params);

            $conn->commit();
            if (ob_get_length()) { ob_clean(); }
            echo 1;
        } catch (Exception $e) {
            if ($conn && $conn->inTransaction()) { $conn->rollBack(); }
            if (ob_get_length()) { ob_clean(); }
            echo 0;
        }
    }

    // 7) Pending amenity count for header bell
    function get_pending_amenity_count() {
        include "connection.php";
        try {
            $cnt = intval($conn->query("SELECT COUNT(*) FROM tbl_booking_charges WHERE booking_charge_status=1")->fetchColumn());
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true, 'pending_count' => $cnt]);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'pending_count' => 0]);
        }
    }

    // 8) Increment delivered amenity day(s) for multiple amenity types
    // Accepts: json { amenity_ids?: [int], amenity_names?: [string], booking_id?: int, booking_room_id?: int }
    function increment_amenity_day($json) {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $amenity_ids = isset($data['amenity_ids']) && is_array($data['amenity_ids']) ? array_filter(array_map('intval', $data['amenity_ids'])) : [];
            $amenity_names = isset($data['amenity_names']) && is_array($data['amenity_names']) ? array_filter(array_map('strval', $data['amenity_names'])) : [];
            $booking_id = intval($data['booking_id'] ?? 0);
            $booking_room_id = intval($data['booking_room_id'] ?? 0);

            // Resolve amenity IDs by names if needed
            if (count($amenity_ids) === 0 && count($amenity_names) > 0) {
                $placeholders = implode(',', array_fill(0, count($amenity_names), '?'));
                $stmt = $conn->prepare("SELECT charges_master_id FROM tbl_charges_master WHERE charges_master_name IN ($placeholders)");
                foreach ($amenity_names as $i => $n) { $stmt->bindValue($i+1, $n, PDO::PARAM_STR); }
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) { $amenity_ids[] = intval($r['charges_master_id']); }
            }

            // Sensible defaults if still empty: Bed (2), Extra Guest (12), plus Television if present
            if (count($amenity_ids) === 0) {
                $amenity_ids = [2, 12];
                try {
                    $tvStmt = $conn->prepare("SELECT charges_master_id FROM tbl_charges_master WHERE charges_master_name LIKE 'Television%' LIMIT 1");
                    $tvStmt->execute();
                    $tvId = $tvStmt->fetchColumn();
                    if ($tvId) { $amenity_ids[] = intval($tvId); }
                } catch (Exception $_) { /* ignore */ }
            }

            // Build WHERE scope for delivered charges
            $where = "bc.booking_charge_status = 2";
            $params = [];
            if (count($amenity_ids) > 0) {
                $in = implode(',', array_fill(0, count($amenity_ids), '?'));
                $where .= " AND bc.charges_master_id IN ($in)";
                foreach ($amenity_ids as $id) { $params[] = $id; }
            }
            if ($booking_room_id > 0) {
                $where .= " AND bc.booking_room_id = ?";
                $params[] = $booking_room_id;
            } elseif ($booking_id > 0) {
                $where .= " AND br.booking_id = ?";
                $params[] = $booking_id;
            }

            // Atomic increment using master price
            $sql = "UPDATE tbl_booking_charges bc
                    JOIN tbl_charges_master cm ON cm.charges_master_id = bc.charges_master_id
                    JOIN tbl_booking_room br ON br.booking_room_id = bc.booking_room_id
                    SET bc.booking_charges_quantity = bc.booking_charges_quantity + 1,
                        bc.booking_charges_total = bc.booking_charges_total + cm.charges_master_price,
                        bc.booking_return_datetime = NOW()
                    WHERE $where";
            $stmt = $conn->prepare($sql);
            foreach ($params as $i => $p) { $stmt->bindValue($i+1, $p, PDO::PARAM_INT); }
            $stmt->execute();
            $affected = $stmt->rowCount();

            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true, 'affected' => $affected]);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // List online booking requests grouped with requested rooms
    function reqBookingList() {
        include "connection.php";
        try {
            $sql = "
                SELECT 
                    b.booking_id,
                    b.reference_no,
                    b.booking_checkin_dateandtime,
                    b.booking_checkout_dateandtime,
                    b.booking_created_at,
                    COALESCE(CONCAT(c.customers_fname,' ',c.customers_lname), CONCAT(w.customers_walk_in_fname,' ',w.customers_walk_in_lname)) AS customer_name,
                    br.booking_room_id,
                    br.roomtype_id,
                    rt.roomtype_name,
                    br.roomnumber_id
                FROM tbl_booking b
                LEFT JOIN tbl_customers c ON b.customers_id = c.customers_id
                LEFT JOIN tbl_customers_walk_in w ON b.customers_walk_in_id = w.customers_walk_in_id
                LEFT JOIN tbl_booking_room br ON br.booking_id = b.booking_id
                LEFT JOIN tbl_roomtype rt ON rt.roomtype_id = br.roomtype_id
                WHERE b.booking_id NOT IN (
                    SELECT booking_id FROM tbl_booking_history WHERE status_id IN (1,2,3)
                )
                ORDER BY b.booking_created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($rows as $row) {
                $bid = $row['booking_id'];
                if (!isset($grouped[$bid])) {
                    $grouped[$bid] = [
                        'booking_id' => $row['booking_id'],
                        'reference_no' => $row['reference_no'],
                        'customer_name' => $row['customer_name'],
                        'booking_checkin_dateandtime' => $row['booking_checkin_dateandtime'],
                        'booking_checkout_dateandtime' => $row['booking_checkout_dateandtime'],
                        'booking_created_at' => $row['booking_created_at'],
                        'rooms' => []
                    ];
                }
                if (!empty($row['roomtype_name'])) {
                    $grouped[$bid]['rooms'][] = [
                        'booking_room_id' => $row['booking_room_id'],
                        'roomtype_id' => $row['roomtype_id'],
                        'roomtype_name' => $row['roomtype_name'],
                        'roomnumber_id' => $row['roomnumber_id']
                    ];
                }
            }
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(array_values($grouped));
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // Fetch booking rooms by booking_id for guest counts and fallback room assignment
    function get_booking_rooms_by_booking($json) {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $booking_id = intval($data['booking_id'] ?? 0);
            if ($booking_id <= 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode([]);
                return;
            }
            $sql = "
                SELECT 
                    br.booking_room_id,
                    br.bookingRoom_adult,
                    br.bookingRoom_children,
                    br.roomnumber_id,
                    br.roomtype_id,
                    rt.roomtype_name
                FROM tbl_booking_room br
                LEFT JOIN tbl_roomtype rt ON rt.roomtype_id = br.roomtype_id
                WHERE br.booking_id = :booking_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (ob_get_length()) { ob_clean(); }
            echo json_encode($rows);
        } catch (Exception $e) {
            if (ob_get_length()) { ob_clean(); }
            echo json_encode([]);
        }
    }

    // Approve a customer booking: assign rooms, update totals, log status
    function approveCustomerBooking($json) {
        include "connection.php";
        try {
            $data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
            $booking_id = intval($data['booking_id'] ?? 0);
            $employee_id = intval($data['user_id'] ?? 0);
            $room_ids = isset($data['room_ids']) && is_array($data['room_ids']) ? array_map('intval', $data['room_ids']) : [];
            $total = floatval($data['booking_totalAmount'] ?? 0);
            $down = floatval($data['booking_downpayment'] ?? 0);

            if ($booking_id <= 0 || $employee_id <= 0) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Missing booking_id or user_id']);
                return;
            }

            // Validate employee is active
            $empStmt = $conn->prepare("SELECT employee_status FROM tbl_employee WHERE employee_id = :employee_id");
            $empStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $empStmt->execute();
            $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
            if (!$empRow || $empRow['employee_status'] == 0 || $empRow['employee_status'] === 'Inactive' || $empRow['employee_status'] === 'Disabled') {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Invalid employee']);
                return;
            }

            // Validate booking
            $bookStmt = $conn->prepare("SELECT booking_id FROM tbl_booking WHERE booking_id = :booking_id");
            $bookStmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
            $bookStmt->execute();
            $bookRow = $bookStmt->fetch(PDO::FETCH_ASSOC);
            if (!$bookRow) {
                if (ob_get_length()) { ob_clean(); }
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
                return;
            }

            $conn->beginTransaction();

            // Assign provided rooms to unassigned booking_room rows
            foreach ($room_ids as $rn) {
                $upd = $conn->prepare("UPDATE tbl_booking_room SET roomnumber_id = :rn WHERE booking_id = :bid AND roomnumber_id IS NULL LIMIT 1");
                $upd->execute([':rn' => $rn, ':bid' => $booking_id]);
                // Do NOT change room_status_id on approval; occupancy should change only on actual check-in
            }

            // Update booking totals (store provided amounts)
            $updBook = $conn->prepare("UPDATE tbl_booking SET booking_totalAmount = :total, booking_payment = :down WHERE booking_id = :bid");
            $updBook->execute([':total' => $total, ':down' => $down, ':bid' => $booking_id]);

            // Insert status history as Reserved/Confirmed using dynamic status ID
            $stRows = $conn->query("SELECT booking_status_id, booking_status_name FROM tbl_booking_status")->fetchAll(PDO::FETCH_ASSOC);
            $findId = function($rows, $cands) {
                foreach ($rows as $r) {
                    $name = strtolower(trim($r['booking_status_name']));
                    foreach ($cands as $c) {
                        if ($name === strtolower($c)) { return intval($r['booking_status_id']); }
                    }
                }
                return null;
            };
            $approvedId = $findId($stRows, ['Reserved','Confirmed','Approved']);
            if ($approvedId === null) { $approvedId = 2; }
            $hist = $conn->prepare("INSERT INTO tbl_booking_history (booking_id, employee_id, status_id, updated_at) VALUES (:bid, :eid, :sid, NOW())");
            $hist->execute([':bid' => $booking_id, ':eid' => $employee_id, ':sid' => $approvedId]);

            $conn->commit();
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => true, 'email_status' => 'skipped']);
        } catch (Exception $e) {
            if ($conn && $conn->inTransaction()) { $conn->rollBack(); }
            if (ob_get_length()) { ob_clean(); }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}

$json = isset($_POST['json']) ? $_POST['json'] : 0;
$method = isset($_POST['method']) ? $_POST['method'] : 0;
$admin = new Admin_Functions();

switch ($method) {
    case 'getAllStatus':
        $admin->getAllStatus();
        break;
    case 'viewAllRooms':
        $admin->viewAllRooms();
        break;
    case 'view_room_types':
        $admin->view_room_types();
        break;
    case 'viewRoomTypes':
        $admin->viewRoomTypes();
        break;
    case 'addRoomType':
        $admin->addRoomType($json);
        break;
    case 'addRoomTypes': // alias for legacy frontend naming
        $admin->addRoomType($json);
        break;
    case 'updateRoomType':
        $admin->updateRoomType($json);
        break;
    case 'updateRoomTypes': // alias for legacy frontend naming
        $admin->updateRoomType($json);
        break;
    case 'viewCharges':
        $admin->viewCharges();
        break;
    case 'viewChargesCategory':
        $admin->viewChargesCategory();
        break;
    case 'viewAmenities':
        $admin->viewAmenities();
        break;
    case 'viewNationalities':
        $admin->viewNationalities();
        break;
    case 'getAllPayMethods':
        $admin->getAllPayMethods();
        break;
    case 'addAmenities':
        $admin->addAmenities();
        break;
    case 'updateAmenities':
        $admin->updateAmenities();
        break;
    case 'disableAmenities':
        $admin->disableAmenities();
        break;
    case 'addCharges':
        $admin->addCharges();
        break;
    case 'updateCharges':
        $admin->updateCharges();
        break;
    case 'disableCharges':
        $admin->disableCharges();
        break;
    // Discount management routes
    case 'viewDiscounts':
        $admin->viewDiscounts();
        break;
    case 'addDiscounts':
        $admin->addDiscounts();
        break;
    case 'updateDiscounts':
        $admin->updateDiscounts();
        break;
    case 'disableDiscounts':
        $admin->disableDiscounts();
        break;
    case 'viewBookingsEnhanced':
        $admin->viewBookingsEnhanced();
        break;
    case 'get_booking_rooms':
        $admin->get_booking_rooms();
        break;
    case 'get_booking_rooms_active':
        $admin->get_booking_rooms_active();
        break;
    case 'reqBookingList':
        $admin->reqBookingList();
        break;
    case 'get_booking_rooms_by_booking':
        $admin->get_booking_rooms_by_booking($json);
        break;
    // NEW: date-aware counts endpoint
    case 'getAvailableRoomsCount':
        $admin->getAvailableRoomsCount($json);
        break;
    case 'approveCustomerBooking':
        $admin->approveCustomerBooking($json);
        break;
    case 'getExtendedRooms':
        $admin->getExtendedRooms($json);
        break;
    case 'setVisitorApproval':
        $admin->setVisitorApproval();
        break;
    case 'get_visitor_approval_statuses':
        $admin->get_visitor_approval_statuses();
        break;
    case 'getVisitorLogs':
        $admin->getVisitorLogs();
        break;
    case 'addVisitorLog':
        $admin->addVisitorLog();
        break;
    case 'updateVisitorLog':
        $admin->updateVisitorLog();
        break;
    case 'extendBookingWithPayment':
        $data = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($data)) { $data = []; }
        $admin->extendBookingWithPayment($data);
        break;
    case 'extendMultiRoomBookingWithPayment':
        $data = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($data)) { $data = []; }
        $admin->extendMultiRoomBookingWithPayment($data);
        break;
    case 'changeBookingStatus':
        $admin->changeBookingStatus($json);
        break;
    case 'getAdminProfile':
        $admin->getAdminProfile($json);
        break;
    case 'updateAdminProfile':
        $admin->updateAdminProfile($json);
        break;
    case 'login':
        $admin->login($json);
        break;

    // Send OTP email for admin profile actions
    case 'sendAdminOTP':
        $data = is_string($json) ? json_decode($json, true) : $json;
        $email = $data['email'] ?? null;
        $otp_code = $data['otp_code'] ?? null;
        if (!$email || !$otp_code) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            break;
        }
        try {
            include "send_email.php";
            $mailer = new SendEmail();
            $subject = 'Demirens Admin OTP';
            $body = '<div style="font-family: Arial, sans-serif; line-height: 1.5;">'
                  . '<h2 style="margin:0 0 8px;">Your Admin OTP Code</h2>'
                  . '<p>Use this One-Time Passcode to verify your action:</p>'
                  . '<p style="font-size:22px; font-weight:bold; letter-spacing:2px;">' . htmlspecialchars($otp_code) . '</p>'
                  . '<p>This code expires in 5 minutes.</p>'
                  . '<hr style="margin:16px 0;" />'
                  . '<p style="color:#666; font-size:12px;">Demirens Booking System</p>'
                  . '</div>';
            $ok = $mailer->sendEmail($email, $subject, $body);
            echo json_encode(['success' => $ok === true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Email send error']);
        }
        break;

    case 'getCustomerInvoice':
        $admin->getCustomerInvoice($json);
        break;
    case 'getCustomerBilling':
        $admin->getCustomerBilling($json);
        break;
    case 'getAllTransactionHistories':
        $admin->getAllTransactionHistories($json);
        break;

    // Employee management routes
    case 'viewEmployees':
        $admin->viewEmployees();
        break;

    // List online customers (accounts)
    case 'getOnlineCustomers':
        include "connection.php";
        try {
            $sql = "SELECT co.customers_online_id, co.customers_online_username, co.customers_online_email, co.customers_online_phone, co.customers_online_created_at, co.customers_online_authentication_status, co.customers_online_profile_image, c.customers_fname, c.customers_lname, c.customers_email, c.customers_phone
                    FROM tbl_customers_online co
                    LEFT JOIN tbl_customers c ON c.customers_online_id = co.customers_online_id
                    ORDER BY co.customers_online_created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->rowCount() > 0 ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            echo json_encode($rows);
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    case 'getUserLevels':
        $admin->getUserLevels();
        break;
    case 'addEmployee':
        $admin->addEmployee($json);
        break;
    case 'updateEmployee':
        $admin->updateEmployee($json);
        break;
    case 'changeEmployeeStatus':
        $admin->changeEmployeeStatus($json);
        break;

    // Amenity request routes
    case 'get_available_charges':
        $admin->get_available_charges();
        break;
    case 'add_amenity_request':
        $admin->add_amenity_request($json);
        break;
    case 'get_amenity_requests':
        $admin->get_amenity_requests();
        break;
    case 'get_amenity_request_stats':
        $admin->get_amenity_request_stats();
        break;
    case 'approve_amenity_request':
        $admin->approve_amenity_request($json);
        break;
    case 'reject_amenity_request':
        $admin->reject_amenity_request($json);
        break;
    case 'get_pending_amenity_count':
        $admin->get_pending_amenity_count();
        break;
    case 'increment_amenity_day':
        $admin->increment_amenity_day($json);
        break;

    default:
        echo json_encode([]);
        break;
}