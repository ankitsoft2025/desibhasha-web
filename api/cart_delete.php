<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();
$token = get_bearer_token();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$plan_purchase_cart_id = trim($input['plan_purchase_cart_id'] ?? '');

// Generate user_id from token
$user_id = validate_token($conn, $token);
if (!$user_id) {    
    http_response_code(401);
    echo json_encode(['error' => 'Token Expired']);
    exit;
}


// insert into plan_purchase_cart

 $stmt = $conn->prepare('delete from plan_purchase_cart where plan_purchase_cart_id = ?');
$stmt->bind_param('i', $plan_purchase_cart_id);
if ($stmt->execute()) {
    $id = $conn->insert_id;
	echo json_encode(['message' => 'Language deleted from the Cart!']);
} 
else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete Language from cart.']);
}

