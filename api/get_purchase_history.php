<?php

// Update profile: validate token, update name



require_once 'utils.php';

header('Content-Type: application/json');

$conn = get_db_connection();

// Accept JSON input

$input = json_decode(file_get_contents('php://input'), true);

$token = get_bearer_token($input);



// return is_active plans when language_id is '' or not provided then all languages plans

$user_id = validate_token($conn, $token);

if (!$user_id) {    

    http_response_code(401);

    echo json_encode(['error' => 'Token Expired']);

    exit;

}

$query="SELECT pph.plan_purchase_history_id,

    pph.bhasha_user_id,

    pph.plan_type_id,

    pph.plan_price,

    -- pph.bhasha_money,

    -- pph.discount_amount,

    -- pph.paid_amount,

    -- pph.payment_type,

    -- pph.auth_code,

    pph.plan_start_date,

    pph.plan_expiry_date,

    pph.date_created,

    pt.plan_language,

    pt.plan_name

FROM plan_purchase_history pph, plan_types pt

WHERE pph.plan_type_id = pt.plan_type_id

and bhasha_user_id = $user_id;";



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