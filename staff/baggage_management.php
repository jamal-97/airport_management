<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Handle baggage status update
if (isset($_POST['update_status']) && isset($_POST['baggage_id']) && isset($_POST['new_status'])) {
    $baggage_id = $_POST['baggage_id'];
    $new_status = $_POST['new_status'];
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate status
    $valid_statuses = ['checked_in', 'security_screening', 'loading', 'in_transit', 'unloading', 'arrived', 'delivered', 'delayed', 'lost'];
    
    if (!in_array($new_status, $valid_statuses)) {
        $error = "Invalid status.";
    } else {
        // Check if baggage exists
        $query = "SELECT b.id, b.tracking_number, b.status, f.flight_number 
                 FROM baggage b
                 JOIN bookings bk ON b.booking_id = bk.id
                 JOIN flights f ON bk.flight_id = f.id
                 WHERE b.id = ?";
        $result = $db->executeQuery($query, "i", [$baggage_id]);
        
        if ($result && count($result) > 0) {
            $tracking_number = $result[0]['tracking_number'];
            $old_status = $result[0]['status'];
            $flight_number = $result[0]['flight_number'];
            
            // Update status
            $query = "UPDATE baggage SET status = ?, last_updated = NOW() WHERE id = ?";
            $result = $db->executeQuery($query, "si", [$new_status, $baggage_id]);
            
            if ($result) {
                // Log the status change
                $staff_id = $_SESSION['user_id'];
                $query = "INSERT INTO baggage_status_logs (baggage_id, staff_id, previous_status, new_status, location, remarks, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $db->executeQuery($query, "iissss", [$baggage_id, $staff_id, $old_status, $new_status, $location, $remarks]);
                
                $success = "Baggage status has been updated successfully.";
            } else {
                $error = "Failed to update baggage status. Please try again.";
            }
        } else {
            $error = "Baggage not found.";
        }
    }
}

// Handle quick scan baggage update
if (isset($_POST['quick_scan']) && isset($_POST['tracking_number']) && isset($_POST['scan_status'])) {
    $tracking_number = trim($_POST['tracking_number']);
    $scan_status = $_POST['scan_status'];
    $scan_location = isset($_POST['scan_location']) ? trim($_POST['scan_location']) : '';
    
    // Validate status
    $valid_statuses = ['checked_in', 'security_screening', 'loading', 'in_transit', 'unloading', 'arrived', 'delivered', 'delayed', 'lost'];