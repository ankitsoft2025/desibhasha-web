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

$bottom_menu_id = $input['bottom_menu_id'] ?? '';
$category_id = $input['category_id'] ?? '';
$teaching_level_id = $input['teaching_level_id'] ?? '';
$language_id = $input['language_id'] ?? '';
$letter_id = $input['letter_id'] ?? '';

    if($category_id > 10000000) //pull data from misspelled_words table.
    {
        $query="SELECT misspelled_word_id, category_id, teaching_level_id, word_in_bhasha, 
                CASE
                    WHEN audio_location is not null THEN concat('https://www.desibhasha.com/', audio_location)
                    ELSE audio_location
                END AS audio_location,
 		    common_romanization, word_in_bhasha_wrong, romanization_wrong_word, 
 				CASE
                    WHEN word_in_bhasha_wrong_audio_location is not null THEN concat('https://www.desibhasha.com/', word_in_bhasha_wrong_audio_location)
                    ELSE word_in_bhasha_wrong_audio_location
                END AS word_in_bhasha_wrong_audio_location,
            english_meaning,detail_explanation, correct_sentence, english_sentence
                FROM misspelled_words
                where category_id = ? 
                order by misspelled_word_id;";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();  
        $submenu_sanatan_data = [];
        while ($row = $result->fetch_assoc()) 
        {
            $submenu_sanatan_data[] = $row;
        }       
        echo json_encode(['data' => $submenu_sanatan_data,'category'=>'misspelled']);

        $stmt->close(); 
        $conn->close();
    }
    elseif($category_id > 1000000) //pull data from sanatan_data table.
    {
        $query="SELECT sanatan_data_id, category_id, language_id, title_english, description, 
                CASE
                    WHEN image_location is not null THEN concat('https://www.desibhasha.com/', image_location)
                    ELSE image_location
                END AS image_location,
                CASE
                    WHEN pdf_doc_location is not null THEN concat('https://www.desibhasha.com/', pdf_doc_location)
                    ELSE pdf_doc_location
                END AS practice_document
                FROM sanatan_data
                where category_id = ? 
                order by sanatan_data_id;";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();  
        $submenu_sanatan_data = [];
        while ($row = $result->fetch_assoc()) 
        {
            $submenu_sanatan_data[] = $row;
        }       
        echo json_encode(['data' => $submenu_sanatan_data,'category'=>'sanatan']);

        $stmt->close(); 
        $conn->close();
    }
    elseif($category_id > 100000) //pull data from letters table.
    {
      if(!$letter_id) //if no letter-id coming, mean pull data from letter table for this category_id
      {
            $query="SELECT letter_id, letter_in_bhasha, common_romanization, approximate_pronunciation, english_equivelant_sound, 
                    image_location, practice_document, is_trial,
                    CASE
                        WHEN audio_location is not null THEN concat('https://www.desibhasha.com/', audio_location)
                        ELSE audio_location
                    END AS audio_location,
                    tracing_steps
                from letters 
                where category_id = ? 
                order by display_order;";
        
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $category_id);
            $stmt->execute();
            $result = $stmt->get_result();  
            $letters_submenu_data = [];
            while ($row = $result->fetch_assoc()) 
            {
                $letters_submenu_data[] = $row;
            }       
            echo json_encode(['data' => $letters_submenu_data,'category'=>'letters']);

            $stmt->close(); 
            $conn->close();
      }
      else // since letter_id is not null, means pull data from letter_words table
      {
            $query="SELECT lw.letter_word_id, lw.letter_id, lw.letter_in_bhasha, concat(concat(lw.letter_in_bhasha, 
                IFNULL(lg.se_alternate,'    ')), lw.word_in_bhasha) as word_in_bhasha, lw.common_romanization, lw.english_meaning, 
                CASE
                    WHEN lw.audio_location is not null THEN concat('https://www.desibhasha.com/', lw.audio_location)
                    ELSE lw.audio_location
                END AS audio_location,
                CASE
                    WHEN lw.image_location is not null THEN concat('https://www.desibhasha.com/', lw.image_location)
                    ELSE lw.image_location
                END AS image_location, 
                CASE
                    WHEN lw.practice_sheet is not null THEN concat('https://www.desibhasha.com/', lw.practice_sheet)
                    ELSE lw.practice_sheet
                END AS practice_sheet, lw.is_trial 
                from letter_words lw, letters l, languages lg
                where lw.letter_id = l.letter_id
                and l.language_id = lg.language_id
                and lw.letter_id = ?; ";
        
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $letter_id);
            $stmt->execute();
            $result = $stmt->get_result();  
            $letters_words_data = [];
            while ($row = $result->fetch_assoc()) 
            {
                $letters_words_data[] = $row;
            }       
            echo json_encode(['data' => $letters_words_data,'category'=>'words']);

            $stmt->close(); 
            $conn->close();
      }
    }
    elseif($category_id <= 200) //pull data from Counting table.
    {
        $query="SELECT counting_id, number_in_bhasha, number_in_english, name_in_bhasha as word_in_bhasha, common_romanization, english_meaning, practice_document,
                CASE
                    WHEN audio_location is not null THEN concat('https://www.desibhasha.com/', audio_location)
                    ELSE audio_location
                END AS audio_location, is_trial
                FROM counting
                where category_id = ? 
                order by counting_id;";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();  
        $submenu_counting_data = [];
        while ($row = $result->fetch_assoc()) 
        {
            $submenu_counting_data[] = $row;
        }       
        echo json_encode(['data' => $submenu_counting_data,'category'=>'counting']);

        $stmt->close(); 
        $conn->close();
    }
    elseif($category_id > 200 && $category_id <= 400) //pull data from poems table.
    {
        $query="SELECT poem_id, poem_title_bhasha, poem_title_romanization, poem_title_english, poem_bhasha, poem_bhasha_audio_with_text, common_romanization, 
                    english_meaning, 
                CASE
                    WHEN poem_title_audio_location is not null THEN concat('https://www.desibhasha.com/', poem_title_audio_location)
                    ELSE poem_title_audio_location
                END AS poem_title_audio_location, 
                CASE
                    WHEN poem_bhasha_audio_location is not null THEN concat('https://www.desibhasha.com/', poem_bhasha_audio_location)
                    ELSE poem_bhasha_audio_location
                END AS poem_bhasha_audio_location, 
                CASE
                    WHEN poem_bhasha_audio_musical is not null THEN concat('https://www.desibhasha.com/', poem_bhasha_audio_musical)
                    ELSE poem_bhasha_audio_musical
                END AS poem_bhasha_audio_musical, 
                CASE
                    WHEN image_location is not null THEN concat('https://www.desibhasha.com/', image_location)
                    ELSE image_location
                END AS image_location, 
                    is_trial, teaching_level_id
                FROM poems
                where category_id = ? 
                order by poem_id;";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();  
        $submenu_poem_data = [];
        while ($row = $result->fetch_assoc()) 
        {
            $submenu_poem_data[] = $row;
        }       
        echo json_encode(['data' => $submenu_poem_data,'category'=>'poems']);

        $stmt->close(); 
        $conn->close();
    }
    elseif($category_id > 400 && $category_id <= 600) //pull data from stories table.
    {
        $query="SELECT story_id, story_title_bhasha, story_title_romanization, story_title_english, story_bhasha, story_romanization, 
                    english_meaning, moral_text, moral_text_romanization, moral_text_english, 
                CASE
                    WHEN story_title_audio_location is not null THEN concat('https://www.desibhasha.com/', story_title_audio_location)
                    ELSE story_title_audio_location
                END AS story_title_audio_location, 
                CASE
                    WHEN story_bhasha_audio_location is not null THEN concat('https://www.desibhasha.com/', story_bhasha_audio_location)
                    ELSE story_bhasha_audio_location
                END AS story_bhasha_audio_location, 
                CASE
                    WHEN story_moral_audio_location is not null THEN concat('https://www.desibhasha.com/', story_moral_audio_location)
                    ELSE story_moral_audio_location
                END AS story_moral_audio_location, 
                CASE
                    WHEN image_location is not null THEN concat('https://www.desibhasha.com/', image_location)
                    ELSE image_location
                END AS image_location, 
                is_trial, teaching_level_id
                FROM stories
                where category_id = ? 
                order by story_id;";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();  
        $submenu_story_data = [];
        while ($row = $result->fetch_assoc()) 
        {
            $submenu_story_data[] = $row;
        }       
        echo json_encode(['data' => $submenu_story_data,'category'=>'stories']);

        $stmt->close(); 
        $conn->close();
    }
    elseif($category_id >= 1000) //pull data from general_words table.
    {
        $query="select gw.general_word_id, gw.title, gw.word_in_bhasha, gw.common_romanization, gw.english_meaning, 
            CASE
                WHEN gw.audio_location is not null THEN concat('https://www.desibhasha.com/', gw.audio_location)
                ELSE gw.audio_location
            END AS audio_location,
            CASE
                WHEN gw.image_location is not null THEN concat('https://www.desibhasha.com/', gw.image_location)
                ELSE gw.image_location
            END AS image_location,
            gw.is_trial 
            from general_words gw 
            where category_id = ? 
            order by gw.general_word_id;";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();  
        $submenu_data = [];
        while ($row = $result->fetch_assoc()) 
        {
            $submenu_data[] = $row;
        }       
        echo json_encode(['data' => $submenu_data,'category'=>'general_words_data']);   

        $stmt->close(); 
        $conn->close();
    }
    else
    {
         http_response_code(400);
        echo json_encode(['error' => 'Invalid Category Selected']);
        exit;
    }


?>