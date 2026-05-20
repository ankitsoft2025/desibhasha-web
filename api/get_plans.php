<?php
// Get Plans

require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();

// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);
$language_id = $input['language_id'] ?? '';
$token = get_bearer_token($input);

// return is_active plans when language_id is '' or not provided then all languages plans
$user_id = validate_token($conn, $token);
if (!$user_id) {    
    http_response_code(401);
    echo json_encode(['error' => 'Token Expired']);
    exit;
}

if ($language_id) {
    $query="SELECT pt.plan_type_id,
    pt.language_id,
    pt.plan_language,
    pt.plan_name,
    pt.valid_for_days,
    pt.plan_price,
    pt.discount_amount,
    pt.is_active,
    pph.plan_expiry_date,
    pph.order_id,
    ppc.plan_purchase_cart_id as plan_purchase_cart_id,
    (select sum(bhasha_money) from bhasha_money_transactions where bhasha_user_id =  $user_id and bhasha_money_type in ('RF', 'BP')) bhasha_money_total,
    (select sum(bhasha_money) from bhasha_money_transactions where bhasha_user_id =  $user_id and bhasha_money_type in ('RD')) bhasha_money_redeemed
FROM plan_types pt
LEFT JOIN plan_purchase_history pph
ON (pt.plan_type_id = pph.plan_type_id 
    and pph.plan_expiry_date > curdate() and pph.bhasha_user_id = $user_id)
LEFT JOIN plan_purchase_cart ppc
ON (pt.plan_type_id = ppc.plan_type_id 
    and ppc.bhasha_user_id = $user_id)
WHERE pt.language_id = $language_id AND pt.is_active=1 and pt.plan_type_id < 100000;";
} else { 
    $query="SELECT pt.plan_type_id,
    pt.language_id,
    pt.plan_language,
    pt.plan_name,
    pt.valid_for_days,
    pt.plan_price,
    pt.discount_amount,
    pt.is_active,
    pph.plan_expiry_date,
    pph.order_id,
    ppc.plan_purchase_cart_id as plan_purchase_cart_id,
    (select sum(bhasha_money) from bhasha_money_transactions where bhasha_user_id =  $user_id and bhasha_money_type in ('RF', 'BP')) bhasha_money_total,
    (select sum(bhasha_money) from bhasha_money_transactions where bhasha_user_id =  $user_id and bhasha_money_type in ('RD')) bhasha_money_redeemed
FROM plan_types pt
LEFT JOIN plan_purchase_history pph
ON (pt.plan_type_id = pph.plan_type_id 
    and pph.plan_expiry_date > curdate() and pph.bhasha_user_id = $user_id)
LEFT JOIN plan_purchase_cart ppc
ON (pt.plan_type_id = ppc.plan_type_id 
    and ppc.bhasha_user_id = $user_id)
WHERE pt.is_active=1 and pt.plan_type_id < 100000;";
}
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();  
$plans = [];
while ($row = $result->fetch_assoc()) {
    $plans[] = $row;
}       
echo json_encode(['data' => $plans]);   

$stmt->close(); 
$conn->close();

?>