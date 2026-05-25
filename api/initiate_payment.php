<?php
require_once 'utils.php';
header('Content-Type: application/json');

$conn = get_db_connection();
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token($input);
$order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;

$api_data = [
    "url" => "https://apitest.authorize.net/xml/v1/request.api",
    "username" => "75aUGd3N",
    "password" => "3ZpP696s7Tx84T4R"
];

if (!$order_id || empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id and token are required']);
    exit;
}

$user_id = validate_token($conn, $token);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Token Expired']);
    exit;
}

$stmt = $conn->prepare('SELECT order_id, bhasha_user_id, paid_amount, status FROM orders WHERE order_id = ?');
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

$status = $order['status'];
if (!is_null($status) && strtoupper($status) !== 'FAILED') {
    http_response_code(400);
    echo json_encode(['error' => 'Order status must be null or FAILED to initiate payment']);
    exit;
}

// Insert transaction record
$stmt_insert = $conn->prepare('INSERT INTO order_transactions (order_id) VALUES (?)');
if (!$stmt_insert) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare transaction insert']);
    exit;
}
$stmt_insert->bind_param('i', $order_id);
$stmt_insert->execute();

if ($stmt_insert->error) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create order transaction']);
    $stmt_insert->close();
    exit;
}

$order_transaction_id = $stmt_insert->insert_id;
$stmt_insert->close();
$conn->close();

function encode_api_data($api_data, $password, $salt = "WoltersDesign#45&65") {
    $data = json_encode($api_data);
    // Derive a 32-byte key using PBKDF2
    $key = hash_pbkdf2("sha256", $password, $salt, 10000, 32, true);

    // Generate a random 16-byte IV
    $iv = openssl_random_pseudo_bytes(16);

    // Encrypt the data
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    // Combine IV and Encrypted data, then encode to Base64 for transit
    $payload = base64_encode($iv . $encrypted);

    return $payload;
}

// encode API data as JSON string and salt encoded (use API password and transaction id as salt)
$api_data_encoded = encode_api_data($api_data,(string)$order_transaction_id);

// Return transaction ID and paid amount
echo json_encode([
    'order_transaction_id' => $order_transaction_id,
    'paid_amount' => $order['paid_amount'] !== null ? floatval($order['paid_amount']) : 0,
    'encoded_api_data' => $api_data_encoded
]);

