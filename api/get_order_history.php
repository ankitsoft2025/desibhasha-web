<?php

require_once 'utils.php';

header('Content-Type: application/json');

$conn = get_db_connection();

// Accept JSON input (token can be in header or body)
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token($input);

$user_id = validate_token($conn, $token);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Token Expired']);
    exit;
}

// Fetch orders for user
$stmt = $conn->prepare('SELECT order_id, order_amount, bhasha_money, discount_amount, paid_amount, payment_type, auth_code, date_created FROM orders WHERE bhasha_user_id = ? ORDER BY date_created DESC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($order = $result->fetch_assoc()) {
    $order_id = $order['order_id'];

    // Fetch order items (plan purchase history) for this order
    $stmt_items = $conn->prepare('SELECT pph.plan_purchase_history_id, pph.bhasha_user_id, pph.plan_type_id, pph.plan_price, pph.plan_start_date, pph.plan_expiry_date, pph.date_created, pt.plan_language, pt.plan_name FROM plan_purchase_history pph JOIN plan_types pt ON pph.plan_type_id = pt.plan_type_id WHERE pph.order_id = ?');
    $stmt_items->bind_param('i', $order_id);
    $stmt_items->execute();
    $res_items = $stmt_items->get_result();
    $order_items = [];
    while ($item = $res_items->fetch_assoc()) {
        $order_items[] = $item;
     
    }
    $stmt_items->close();

    // Map order fields to requested response keys
    $orders[] = [
        'orderId' => $order['order_id'],
        'paidAmount' => $order['paid_amount'] !== null ? $order['paid_amount'] : 0,
        'totalAmount' => $order['order_amount'] !== null ? $order['order_amount'] : 0,
        'discount' => $order['discount_amount'] !== null ? $order['discount_amount'] : 0,
        'bhashaMoneyUsed' => $order['bhasha_money'] !== null ? $order['bhasha_money'] : 0,
        'orderDate' => date('Y-m-d', strtotime($order['date_created'])),
        'date_created' => $order['date_created'],
        // 'plan_language' => count($order_items) ? ($order_items[0]['plan_language'] ?? '') : '',
        'order_items' => $order_items
    ];
}

echo json_encode(['data' => $orders]);

$stmt->close();
$conn->close();

?>