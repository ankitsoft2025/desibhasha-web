<?php
require_once 'utils.php';
header('Content-Type: application/json');

$conn = get_db_connection();
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token($input);

$order_transaction_id = isset($input['order_transaction_id']) ? intval($input['order_transaction_id']) : 0;
$order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;
$status = isset($input['status']) ? trim(strtolower($input['status'])) : '';
$last_four = isset($input['last_four']) ? trim($input['last_four']) : ''; 
$auth_code = isset($input['auth_code']) ? trim($input['auth_code']) : '';
$gateway_response = $input['gateway_response'] ?? null;
$last_updated = isset($input['last_updated']) ? trim($input['last_updated']) : null;

if (!$order_transaction_id || !$order_id || empty($token) || !in_array($status, ['success', 'failed'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'order_transaction_id, order_id, status and token are required and status must be success or failed']);
    exit;
}

$user_id = validate_token($conn, $token);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Token Expired']);
    exit;
}

$stmt = $conn->prepare('SELECT order_id, bhasha_user_id FROM orders WHERE order_id = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare order lookup']);
    exit;
}
$stmt->bind_param('i', $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

if (intval($order['bhasha_user_id']) !== intval($user_id)) {
    http_response_code(403);
    echo json_encode(['error' => 'Order does not belong to authenticated user']);
    exit;
}

$stmt = $conn->prepare('SELECT order_transaction_id FROM order_transactions WHERE order_transaction_id = ? AND order_id = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare transaction lookup']);
    exit;
}
$stmt->bind_param('ii', $order_transaction_id, $order_id);
$stmt->execute();
$result = $stmt->get_result();
$transaction = $result->fetch_assoc();
$stmt->close();

if (!$transaction) {
    http_response_code(404);
    echo json_encode(['error' => 'Order transaction not found for this order']);
    exit;
}

if (is_array($gateway_response) || is_object($gateway_response)) {
    $gateway_response = json_encode($gateway_response);
} else {
    $gateway_response = trim((string)$gateway_response);
}

$last_updated = date('Y-m-d H:i:s');

$order_status = $status === 'success' ? 'SUCCESS' : 'FAILED';

$stmt = $conn->prepare('UPDATE order_transactions SET last_four = ?, status = ?, auth_code = ?, gateway_response = ?, last_updated = ? WHERE order_transaction_id = ? AND order_id = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare transaction update']);
    exit;
}
$stmt->bind_param('ssssiii', $last_four, $status, $auth_code, $gateway_response, $last_updated, $order_transaction_id, $order_id);
$stmt->execute();

if ($stmt->error) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update order transaction']);
    $stmt->close();
    exit;
}
$stmt->close();

$stmt = $conn->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare order status update']);
    exit;
}
$stmt->bind_param('si', $order_status, $order_id);
$stmt->execute();

if ($stmt->error) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update order status']);
    $stmt->close();
    exit;
}
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'order_id' => $order_id,
    'order_transaction_id' => $order_transaction_id
]);
