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

$teaching_level_id = $input['teaching_level_id'] ?? '';
$bottom_menu_id = $input['bottom_menu_id'] ?? '';
$language_id = $input['language_id'] ?? '';

    if(!$teaching_level_id || !$bottom_menu_id || !$language_id){
        http_response_code(400);
        echo json_encode(['error' => 'TeachingLevelId/BottomMenuId/LanguageID all are mandatory.']);
        exit;
    }

    if($bottom_menu_id == 100) //Foundation for teaching_level1
    {
        $query="select lc.bottom_menu_id, lc.letter_category_id as category_id,  lc.display_order, lc.english_meaning, lc.name_in_bhasha, 
            CASE
                WHEN lc.audio_location is not null THEN concat('https://www.desibhasha.com/', lc.audio_location)
                ELSE lc.audio_location
            END AS audio_location,
            lc.common_romanization, 0 as is_test
            from letter_categories lc
            where lc.teaching_level_id = ? 
            and lc.bottom_menu_id = ?
            and lc.language_id = ? 
            order by letter_category_id;";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('iii', $teaching_level_id, $bottom_menu_id, $language_id);
        $stmt->execute();
        $result = $stmt->get_result();  
        $topsub_menus = [];
        while ($row = $result->fetch_assoc()) {
            $topsub_menus[] = $row;
        }       
        echo json_encode(['data' => $topsub_menus]);   

        $stmt->close(); 
        $conn->close();
    }
    elseif($bottom_menu_id == 200) //Grammer - Pull Matras category from letter_categories
    {
        $query="select lc.bottom_menu_id, lc.letter_category_id as category_id,  lc.display_order, lc.english_meaning, lc.name_in_bhasha, 
                CASE
                    WHEN lc.audio_location is not null THEN concat('https://www.desibhasha.com/', lc.audio_location)
                    ELSE lc.audio_location
                END AS audio_location,
                lc.common_romanization, 0 as is_test, 0 as incomplete_test, 0 as completed_test, 0 as performance
                    from letter_categories lc
                    where lc.teaching_level_id = ? 
                    and lc.bottom_menu_id = ?
                    and lc.language_id = ? 
                UNION
                select c.bottom_menu_id, c.category_id, c.display_order, c.english_meaning, c.name_in_bhasha, 
                CASE
                    WHEN c.audio_location is not null THEN concat('https://www.desibhasha.com/', c.audio_location)
                    ELSE c.audio_location
                END AS audio_location,
                c.common_romanization, c.is_test,
                    (select count(*) as total from test_results tr where tr.category_id = c.category_id
                    and tr.bhasha_user_id = ? and tr.date_completed is null) as incomplete_test,
                    (select count(*) as total from test_results tr where tr.category_id = c.category_id
                    and tr.bhasha_user_id = ? and tr.date_completed is not null) as completed_test,
                    round((select sum(total_correct_answers)*100/sum(total_correct_answers+total_wrong_answers) as performance 
                            from test_results tr where tr.category_id = c.category_id
                            and tr.bhasha_user_id = ? and tr.date_completed is not null)) as performance
                    from categories c 
                    where c.teaching_level_id = ? 
                    and c.bottom_menu_id = ? 
                    and c.language_id = ?
                order by display_order;";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('iiiiiiiii', $teaching_level_id, $bottom_menu_id, $language_id, $user_id, $user_id, $user_id, $teaching_level_id, $bottom_menu_id, $language_id);
        $stmt->execute();
        $result = $stmt->get_result();  
        $topsub_menus = [];
        while ($row = $result->fetch_assoc()) {
            $topsub_menus[] = $row;
        }       
        echo json_encode(['data' => $topsub_menus]);   

        $stmt->close(); 
        $conn->close();
    }
    else
    {
        $query="select c.bottom_menu_id, c.category_id, c.display_order, c.english_meaning, c.name_in_bhasha, 
                CASE
                    WHEN c.audio_location is not null THEN concat('https://www.desibhasha.com/', c.audio_location)
                    ELSE c.audio_location
                END AS audio_location,
                c.common_romanization, c.is_test,
                (select count(*) as total from test_results tr where tr.category_id = c.category_id
                 and tr.bhasha_user_id = ? and tr.date_completed is null) as incomplete_test,
                (select count(*) as total from test_results tr where tr.category_id = c.category_id
                 and tr.bhasha_user_id = ? and tr.date_completed is not null) as completed_test,
                round((select sum(total_correct_answers)*100/sum(total_correct_answers+total_wrong_answers) as performance 
                 from test_results tr where tr.category_id = c.category_id
                 and tr.bhasha_user_id = ? and tr.date_completed is not null)) as performance
                from categories c 
                where c.teaching_level_id = ? 
                and c.bottom_menu_id = ? 
                and c.language_id = ?
                order by display_order;";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('iiiiii', $user_id, $user_id, $user_id, $teaching_level_id, $bottom_menu_id, $language_id);
        $stmt->execute();
        $result = $stmt->get_result();  
        $topsub_menus = [];
        while ($row = $result->fetch_assoc()) {
            $topsub_menus[] = $row;
        }       
        echo json_encode(['data' => $topsub_menus]);   

        $stmt->close(); 
        $conn->close();
    }

?>