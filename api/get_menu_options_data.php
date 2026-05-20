<?php
// Get Menu Options Data in HTML

require_once 'utils.php';
header('Content-Type: application/json');
$conn = get_db_connection();

// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);

// AU - About Us, FAQ - FAQs, PP - Privacy Policy, TC - Terms & Conditions
$menu_option_id = $input['menu_option_id'] ?? '';
$token = get_bearer_token($input);

// return is_active plans when language_id is '' or not provided then all languages plans
//$user_id = validate_token($conn, $token);
//if (!$user_id) {    
//    http_response_code(401);
//    echo json_encode(['error' => 'Token Expired']);
//    exit;
//}

   $query="SELECT menu_options_data_id, html_code
            FROM menu_options_data 
            where menu_options_data_id = ?;";

  $stmt = $conn->prepare($query);
  $stmt->bind_param('s', $menu_option_id);
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