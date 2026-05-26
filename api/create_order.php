<?php
require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();

// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token($input);
$user_id = validate_token($conn, $token);
$order_amount = $input['order_amount'] ?? 0;
$bhasha_money_used = $input['bhasha_money_used'] ?? 0;
$bhasha_money_available = $input['bhasha_money_available'] ?? 0;
$discount = $input['discount'] ?? 0;
$paid_amount = $input['paid_amount'] ?? 0;
if (!$user_id) {    
    http_response_code(401);
    echo json_encode(['error' => 'Token Expired']);
    exit;
}
$stmt = $conn->prepare('SELECT plan_purchase_cart_id, plan_type_id FROM plan_purchase_cart where order_id is null and bhasha_user_id = ?');
$stmt->bind_param('s', $user_id);
$stmt->execute();
$result = $stmt->get_result();  
if ($result->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No plans in cart to create order']);
    exit;
}
$plan_type_ids = [];
$plan_purchase_cart_ids = [];
while ($row = $result->fetch_assoc()) {
    $plan_type_ids[] = $row['plan_type_id'];
    $plan_purchase_cart_ids[] = $row['plan_purchase_cart_id'];
}

// create order
//$plan_type_id = $plan_type_ids[0];
$stmt_insert = $conn->prepare('INSERT INTO orders (`bhasha_user_id`, `order_amount`,`bhasha_money`,`discount_amount`,`paid_amount`,`status`,`date_created`) VALUES (?, ?, ?, ?, ?, NULL, NOW())');
$stmt_insert->bind_param('idddd', $user_id, $order_amount, $bhasha_money_used, $discount, $paid_amount);
$stmt_insert->execute();
print_r($stmt_insert->error);
$order_id = $stmt_insert->insert_id;
if ($stmt_insert->error)
{
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create order']);
    exit;
}
print_r($stmt_insert->error);
// update plan_purchase_cart with order_id
$plan_purchase_cart_ids_str = implode(',', $plan_purchase_cart_ids);
$stmt_update = $conn->prepare("UPDATE plan_purchase_cart SET order_id = ? WHERE plan_purchase_cart_id IN ($plan_purchase_cart_ids_str)");
$stmt_update->bind_param('i', $order_id);
$stmt_update->execute();

// plans in user's plan_purchase_history
foreach ($plan_type_ids as $pt_id) {

    $plan_query = "SELECT plan_price, valid_for_days FROM plan_types WHERE plan_type_id = ?";
    $stmt_plan = $conn->prepare($plan_query);
    $stmt_plan->bind_param('i', $pt_id);
    $stmt_plan->execute();
    $plan_result = $stmt_plan->get_result();
    $plan_row = $plan_result->fetch_assoc();
    $plan_price = $plan_row['plan_price'];
    $valid_for_days = $plan_row['valid_for_days'];
    $plan_start_date = date('Y-m-d');
    $plan_expiry_date = date('Y-m-d', strtotime($plan_start_date . " +$valid_for_days days"));

    $query = "INSERT INTO `plan_purchase_history` (`bhasha_user_id`, `plan_type_id`, `plan_price`, `plan_start_date`, `plan_expiry_date`, `order_id`, `date_created`) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt_history = $conn->prepare($query);
    $stmt_history->bind_param('iiissi', $user_id, $pt_id, $plan_price, $plan_start_date, $plan_expiry_date, $order_id);
    $stmt_history->execute();
    $stmt_history->close();

}
add_bhasha_money_transaction_after_order($conn, $user_id, $bhasha_money_used, $order_id);
$stmt->close(); 
$stmt_insert->close();
$conn->close();
echo json_encode(['message' => 'Order created', 'order_id' => $order_id]);
?>