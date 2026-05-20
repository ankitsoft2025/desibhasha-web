<?php
require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();

// Get the user_id from the token
$token = get_bearer_token();
$user_id = validate_token($conn, $token);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
// pagination parameters (query string): page, per_page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
$per_page = $per_page > 0 ? min(100, $per_page) : 20;
$offset = ($page - 1) * $per_page;

// total count
$count_stmt = $conn->prepare('SELECT COUNT(*) AS total FROM bhasha_money_transactions WHERE bhasha_user_id = ? and bhasha_money_type = ?');
$bhasha_money_type = 'RF';

$count_stmt->bind_param('is', $user_id, $bhasha_money_type);
$count_stmt->execute();
$count_res = $count_stmt->get_result();
$total = 0;
if ($r = $count_res->fetch_assoc()) $total = intval($r['total']);

// fetch page 
$stmt = $conn->prepare('SELECT bmt.bhasha_money_transaction_id,  DATE_FORMAT(bmt.date_created, "%b %d, %Y") as signedup, bu.first_name, bu.last_name, bu.phone, bu.email_id
                        FROM bhasha_money_transactions as bmt, bhasha_users as bu
                        WHERE bmt.bhasha_user_id = ?
                        and bmt.referred_to = bu.bhasha_user_id
                        and bmt.bhasha_money_type = ? 
                         order by bmt.date_created DESC LIMIT ? OFFSET ?');
$stmt->bind_param('isii', $user_id, $bhasha_money_type, $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

$referred_users = [];
while ($row = $result->fetch_assoc()) {
    $referred_users[] = $row;
}

$total_pages = $per_page ? ceil($total / $per_page) : 0;
echo json_encode([
    'referred_users' => $referred_users,
    'pagination' => [
        'page' => $page,
        'per_page' => $per_page,
        'total' => $total,
        'total_pages' => $total_pages
    ]
]);


?>

