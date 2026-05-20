<?php
// Update profile: validate token, update name

require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();
// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token($input);

$user_id = validate_token($conn, $token);
if (!$user_id) {    
    http_response_code(401);
    echo json_encode(['error' => 'Token Expired']);
    exit;
}   
$plan_type_id = $input['plan_type_id'] ?? '';

$plan_price = $input['plan_price'] ?? 0;
$bhasha_money = $input['bhasha_money'] ?? 0;
$paid_amount = $input['paid_amount'] ?? 0;
$payment_type = $input['payment_type'] ?? 0;
$auth_code = $input['auth_code'] ?? 0;
$plan_start_date = $input['plan_start_date'] ?? date('Y-m-d', strtotime('+1 day'));
$plan_expiry_date = $input['plan_expiry_date'] ?? NULL;
$date_created = $input['date_created'] ?? date('Y-m-d H:i:s');

// validate input fields plan_type_id, validate plan_type_id from table and plan_start_date can not be before today
if (empty($plan_type_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid plan selected']);
    exit;
}

// Check if plan_type_id exists in the database
$query = "SELECT * FROM `mqyvhbte_desibhasha`.`plan_types` WHERE is_active=1 and plan_type_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $plan_type_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid plan_type_id']);
    exit;
}
if (empty($plan_start_date) || strtotime($plan_start_date) < strtotime('today')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid plan_start_date']);
    exit;
}

$query="INSERT INTO `mqyvhbte_desibhasha`.`plan_purchase_history`
(`bhasha_user_id`, `plan_type_id`, `plan_price`, `bhasha_money`, `paid_amount`, `payment_type`, `auth_code`, `plan_start_date`, `plan_expiry_date`, `date_created`)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Database prepare failed', 'db_error' => $conn->error]);
    $conn->close();
    exit;
}
// bind types: user_id int, plan_type_id int, plan_price double, bhasha_money double, paid_amount double, payment_type string, auth_code string, plan_start_date string, plan_expiry_date string, date_created string
$stmt->bind_param("iidddsssss", $user_id, $plan_type_id, $plan_price, $bhasha_money, $paid_amount, $payment_type, $auth_code, $plan_start_date, $plan_expiry_date, $date_created);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Database execute failed', 'db_error' => $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}
// get id of inserted row
$inserted_id = $stmt->insert_id;
$query="SELECT * FROM `mqyvhbte_desibhasha`.`plan_purchase_history` WHERE plan_purchase_history_id = ?";
$stmt = $conn->prepare($query);

$stmt->bind_param("i", $inserted_id);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Database execute failed', 'db_error' => $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}
//return plan_history object wrt id
$plans = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $plans[] = $row;
}
echo json_encode(['data' => $plans]);
$stmt->close();
$conn->close();
?>