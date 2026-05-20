<?php
require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();

// Get the user_id from the token
$input = json_decode(file_get_contents('php://input'), true);
$token = get_bearer_token();
$user_id = validate_token($conn, $token);

//$money_type = trim($input['money_type'] ?? ''); // A- Available, R-Redeemed
$money_type = $input['money_type'] ?? '';


if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
// pagination parameters (query string): page, per_page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 100;
$per_page = $per_page > 0 ? min(100, $per_page) : 100;
$offset = ($page - 1) * $per_page;

// total count
    if ($money_type != 'R')
    {
        $bhasha_money_type = "";
        $count_stmt = $conn->prepare("SELECT COUNT(*) AS total_records, sum(bhasha_money) as total_money
                            FROM bhasha_money_transactions 
                            WHERE bhasha_user_id = ? 
                            and bhasha_money_type in ('RF','BP')
                            and redeem_order_id is null");

        $count_stmt->bind_param('i', $user_id);
        $count_stmt->execute();
        $count_res = $count_stmt->get_result();
        $total = 0;
        if ($r = $count_res->fetch_assoc()) 
        {$total = intval($r['total_records']);
            $total_money = intval($r['total_money']);
        }
            //DATE_FORMAT(bmt.date_created, "%b %d, %Y") as signedup,
            // get Available Bhasha Money Transactions
        $stmt = $conn->prepare("SELECT bmt.bhasha_money_transaction_id, 
                            CASE bmt.bhasha_money_type
                                WHEN 'RF' THEN 'Referred'
                                WHEN 'BP' THEN 'Bought Plan'
                            END as bhasha_money_type, 
                            bmt.bhasha_money,  date_format(bmt.date_created,'%b %d,%Y, %H:%i') as date_created,
                                bu.first_name, bu.last_name, bu.email_id
                        FROM bhasha_money_transactions as bmt, bhasha_users as bu
                        WHERE bmt.bhasha_user_id = ?
                        and bmt.referred_to = bu.bhasha_user_id
                        and bmt.bhasha_money_type in ('RF','BP')
                        and redeem_order_id is null
                        order by bmt.date_created DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('iii', $user_id, $per_page, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $referred_users = [];
        while ($row = $result->fetch_assoc()) {
            $referred_users[] = $row;
        }
    }
    else
    {
        $count_stmt = $conn->prepare("SELECT COUNT(*) AS total_records, sum(bhasha_money) as total_money
                            FROM bhasha_money_transactions 
                            WHERE bhasha_user_id = ? 
                            and bhasha_money_type in ('RF','BP') 
                            and redeem_order_id is not null");


        $count_stmt->bind_param('i', $user_id);
        $count_stmt->execute();
        $count_res = $count_stmt->get_result();
        $total = 0;
        if ($r = $count_res->fetch_assoc())
        { $total = intval($r['total_records']);
          $total_money = intval($r['total_money']);
        }

            // get Available Bhasha Money Transactions
        $stmt = $conn->prepare("SELECT bmt.bhasha_money_transaction_id, bmt.redeem_order_id, ord.order_amount, ord.bhasha_money as redeemed_bhasha_money, 
                            ord.discount_amount, ord.paid_amount,
                            CASE bmt.bhasha_money_type
                                WHEN 'RF' THEN 'Referred'
                                WHEN 'BP' THEN 'Bought Plan'
                            END as bhasha_money_type, 
                            bmt.bhasha_money, date_format(bmt.date_redeemed,'%b %d,%Y') as date_redeemed
                        FROM bhasha_money_transactions bmt, orders ord
                        WHERE bmt.bhasha_user_id = ?
                        and bmt.redeem_order_id = ord.order_id
                        and bmt.bhasha_money_type in ('RF','BP') 
                        and redeem_order_id is not null
                        order by bmt.date_created DESC LIMIT ? OFFSET ?");
                        
        $stmt->bind_param('iii', $user_id, $per_page, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $referred_users = [];
        while ($row = $result->fetch_assoc()) {
            $referred_users[] = $row;
        }
    }
    

$total_pages = $per_page ? ceil($total / $per_page) : 0;
echo json_encode([
    'referred_users' => $referred_users,
    'pagination' => [
        'page' => $page,
        'per_page' => $per_page,
        'total' => $total,
        'total_money'=>$total_money,
        'total_pages' => $total_pages
    ]
]);
?>