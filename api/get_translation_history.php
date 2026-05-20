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

    if($language_id != '' ) //pull data from Translation tables for this language_id.
    {
       $query="SELECT tu.translation_user_id, date_format(tu.date_created,'%b %d,%Y, %H:%i') as date_created, tg.translation_global_id, 
                tg.language_id, tg.english_para, tg.bhasha_para, 
                CASE
                    WHEN tg.audio_location_bhasha is not null THEN concat('https://www.desibhasha.com/', tg.audio_location_bhasha)
                    ELSE tg.audio_location_bhasha
                END AS audio_location_bhasha,
                tg.ramanization_para 
            FROM translation_user tu, translation_global tg 
            where tu.bhasha_user_id = ? 
            and tg.language_id = ?
            and tu.translation_global_id = tg.translation_global_id 
            order by tu.date_created DESC;";

      $stmt = $conn->prepare($query);
      $stmt->bind_param('ii', $user_id, $language_id);
      $stmt->execute();
      $result = $stmt->get_result();  
      $plans = [];
      while ($row = $result->fetch_assoc()) {
          $plans[] = $row;
      }       
      
      echo json_encode(['data' => $plans]);   
      
      $stmt->close();     
      $conn->close();
    }
    else //pull data from Translation tables for all language_id.
    {
       $query="SELECT tu.translation_user_id, date_format(tu.date_created,'%b %d,%Y, %H:%i') as date_created, tg.translation_global_id, 
                tg.language_id, tg.english_para, tg.bhasha_para, 
                CASE
                    WHEN tg.audio_location_bhasha is not null THEN concat('https://www.desibhasha.com/', tg.audio_location_bhasha)
                    ELSE tg.audio_location_bhasha
                END AS audio_location_bhasha,
             tg.ramanization_para 
            FROM translation_user tu, translation_global tg 
            where tu.bhasha_user_id = ? 
            and tu.translation_global_id = tg.translation_global_id 
            order by tu.date_created DESC;";

      $stmt = $conn->prepare($query);
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $result = $stmt->get_result();  
      $plans = [];
      while ($row = $result->fetch_assoc()) {
          $plans[] = $row;
      }       
      
      echo json_encode(['data' => $plans]);   
      
      $stmt->close();     
      $conn->close();
    }


?>