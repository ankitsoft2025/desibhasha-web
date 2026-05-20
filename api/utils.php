<?php



 error_reporting(E_ALL);

 ini_set('display_errors', 1);

function mark_otp_done($conn, $email, $otp) {

    		// $conn->query("DELETE FROM otps WHERE email = '" . $conn->real_escape_string($email) . "'");

    // Mark OTP as used

		$stmt_update = $conn->prepare('UPDATE otps SET is_used = 1 WHERE email = ? AND otp = ?');

		$stmt_update->bind_param('ss', $email, $otp);

		$stmt_update->execute();



    return $stmt_update->affected_rows > 0;

}

function get_db_connection_local() {

    return new mysqli('localhost', 'desibhasha', 'desibhasha@123', 'sonam');

}

function get_db_connection() {

    return new mysqli('50.6.35.221', 'mqyvhbte_dataload', 'DataLoad!23', 'mqyvhbte_desibhasha');

}

// Utility functions: token, OTP, version check

function generate_otp($email = '') {

    if (strpos($email, 'testuser_') === 0) {

        return '9999';

    }

    return rand(100000, 999999);

}



function send_otp_mail($email, $otp) {

    if (strpos($email, 'testuser_') === 0) {

        // Mock mail for testuser

        return true;

    }
   // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@desibhasha.com" . "\r\n";


    // return mail($email, 'Your OTP', "Your OTP is: $otp", $headers);

    return mail($email, 'Your OTP', "Your OTP is: $otp", $headers);

}



function send_mail($to, $subject, $message, $frommail)

{

    $headers  = 'MIME-Version: 1.0' . "\r\n"; 

    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n"; 

    $headers .= 'From: <'.$frommail.'>';

    mail($to, $subject, $message, $headers); 

        

}

/**

 * Extract bearer token from Authorization header (with fallbacks) and language_id from input.

 *

 * @param array $input decoded JSON input

 * @return string token

 */

function get_bearer_token(?array $input = null) : ?string {

    $token = null;

    $authHeader = '';



    if (function_exists('getallheaders')) {

        foreach (getallheaders() as $name => $value) {

            if (strtolower($name) === 'authorization') {

                $authHeader = $value;

                break;

            }

        }

    }



    if (!$authHeader) {

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    }



    if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {

        $token = trim($m[1]);

    } elseif ($authHeader) {

        $token = trim($authHeader);

    }



    if (empty($token) && is_array($input)) {

        $token = $input['token'] ?? '';

    }

return $token;

}



function validate_token($conn, $token) {

    $stmt = $conn->prepare('SELECT user_id FROM tokens WHERE token = ? and last_used>= Now() - INTERVAL 30 DAY');

    $stmt->bind_param('s', $token);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        //update last_used

        $stmt_update = $conn->prepare('UPDATE tokens SET last_used = NOW() WHERE token = ?');

        $stmt_update->bind_param('s', $token);

        $stmt_update->execute();

        return $row['user_id'];

    }

    else {

        // print_r("Token validation failed for token: $token");

    return false;

}

}



function add_refer_bhasha_money($conn, $user_id, $refer_code) {

    $query = "INSERT INTO bhasha_money_transactions (bhasha_user_id, bhasha_money, bhasha_money_type, referred_to, date_created) VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);

    $amount = 5;
    $type = 'RFU';
    $date_created = date('Y-m-d H:i:s');
    $stmt->bind_param('iisis', $refer_code, $amount, $type, $user_id, $date_created);

    $stmt->execute();

}

function add_bhasha_money_transaction_after_order($conn, $user_id, $bhasha_money_used, $order_id) {
        // error_log("you are here with $bhasha_money_used . \n");


    $date_created = date('Y-m-d H:i:s');
    $refer_code = null;
    // check referrd user exists
     $stmt_check = $conn->prepare("SELECT bhasha_user_id FROM bhasha_money_transactions WHERE referred_to = ? and bhasha_money_type='RF'");
     $stmt_check->bind_param('i', $user_id);
     $stmt_check->execute();
     $result_check = $stmt_check->get_result();
    if ($row_check = $result_check->fetch_assoc()) {
        $refer_code = $row_check['bhasha_user_id'];
    }
    if($refer_code!=null){
    $stmt_check = $conn->prepare("SELECT bhasha_user_id FROM bhasha_money_transactions WHERE referred_to = ? and bhasha_money_type='BP' and bhasha_user_id=?");
     $stmt_check->bind_param('ii', $user_id, $refer_code);
     $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows == 0) {
    
    // add bhasha money to referred user
    $query = "INSERT INTO bhasha_money_transactions (bhasha_user_id, bhasha_money, bhasha_money_type, buyplan_order_id, referred_to, date_created) VALUES (?, ?, ?, ?, ?, ?)";
    $amount = 5;
    $type = 'BP';
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iisiis', $refer_code, $amount, $type, $order_id, $user_id, $date_created);
    $stmt->execute();
    }
}
    //  if ($bhasha_money_used <= 0) {

    //     return;

    // }

    // add bhasha money transaction for referred user
    $query = "INSERT INTO bhasha_money_transactions (bhasha_user_id, bhasha_money, bhasha_money_type, redeem_order_id, date_created) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $type = 'RD';
    $stmt->bind_param('iisis', $user_id, $bhasha_money_used, $type, $order_id, $date_created);
    $stmt->execute();

    // update redeem order_id
    // $conn->begin_transaction();

    $no_of_transactions = intdiv($bhasha_money_used, 5);
    
    $query = "
    UPDATE bhasha_money_transactions
    SET redeem_order_id = ?, date_redeemed = ?
    WHERE redeem_order_id IS NULL and bhasha_user_id=?
    ORDER BY date_created
    LIMIT ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isii', $order_id, $date_created,$user_id, $no_of_transactions);
    $stmt->execute();
    // if ($stmt->affected_rows !== $no_of_transactions) {
    //     $conn->rollback(); // not enough balance
    //     throw new Exception("Insufficient bhasha money");
    // }
    
    // $conn->commit();
    
}



function expire_token($token) {

    $conn = new mysqli('localhost', 'desibhasha', 'desibhasha@123', 'sonam');

    $stmt = $conn->prepare('DELETE FROM tokens WHERE token = ?');

    $stmt->bind_param('s', $token);

    $stmt->execute();

}



function check_app_version($conn, $platform, $version) {


    $stmt = $conn->prepare('SELECT * FROM app_versions WHERE platform = ? AND version = ? AND (active_to IS NULL OR active_to >= CURDATE())');
    $stmt->bind_param('ss', $platform, $version);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0;

}

/**
 * Mark download request as completed if email exists in download_requests_new table
 * Returns true if record was updated, false otherwise
 */
function mark_download_request_completed($conn, $email) {
    $query = "UPDATE download_requests SET status = 'completed', date_completed = ? WHERE referred_email = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $completion_date = date('Y-m-d H:i:s');
        $stmt->bind_param('ss', $completion_date, $email);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $affected_rows > 0;
    }
    return false;
}

/**
 * Retrieve refercode from download_requests_new table for an email
 * Returns refercode if found and status is not 'completed', otherwise returns null
 * Also marks the record as completed when returning the refercode
 */
function get_refercode_from_download_request($conn, $email) {
    $query = "SELECT refer_code, status FROM download_requests WHERE referred_email = ? AND status != 'completed' LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $refercode = $row['refer_code'];
        $stmt->close();
        
        // Mark as completed
        mark_download_request_completed($conn, $email);
        
        return !empty($refercode) ? $refercode : null;
    }
    
    $stmt->close();
    return null;
}



