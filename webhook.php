<?php
require_once 'config.php';

// Webhook endpoint for Postal delivery status updates
// This file should be accessible at: yourdomain.com/webhook.php

// Log webhook requests for debugging
error_log("Webhook received: " . file_get_contents('php://input'));

try {
    // Get the webhook payload
    $input = file_get_contents('php://input');
    $event = json_decode($input, true);
    
    if (!$event || !isset($event['payload']['message_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid webhook payload']);
        exit;
    }
    
    $messageId = $event['payload']['message_id'];
    $status = $event['payload']['status'] ?? 'unknown';
    $bounceType = $event['payload']['bounce_type'] ?? null;
    $timestamp = $event['payload']['timestamp'] ?? date('Y-m-d H:i:s');
    
    // Map Postal status to internal status
    $statusMap = [
        'sent' => 'sent',
        'delivered' => 'delivered',
        'bounced' => 'bounced',
        'spam' => 'spam',
        'failed' => 'failed',
        'held' => 'pending'
    ];
    
    $internalStatus = $statusMap[$status] ?? 'pending';
    
    // Update email record in database
    $db = Database::getInstance()->getConnection();
    
    $updateData = [
        'status' => $internalStatus,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if ($bounceType) {
        $updateData['bounce_type'] = $bounceType;
    }
    
    if ($internalStatus === 'delivered') {
        $updateData['delivered_at'] = $timestamp;
    }
    
    // Build update query
    $setParts = [];
    $values = [];
    foreach ($updateData as $key => $value) {
        $setParts[] = "$key = ?";
        $values[] = $value;
    }
    $values[] = $messageId;
    
    $sql = "UPDATE emails SET " . implode(', ', $setParts) . " WHERE postal_message_id = ?";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($values);
    
    if ($result) {
        error_log("Email status updated for message ID: $messageId to status: $internalStatus");
        echo json_encode(['success' => true]);
    } else {
        error_log("Failed to update email status for message ID: $messageId");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update email status']);
    }
    
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>