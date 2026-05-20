<?php

require_once 'utils.php';

header('Content-Type: application/json');

/*
        // this query for categores table
$query = "SELECT c.category_id as id, c.name_in_bhasha as word, c.language_id as language_id, 'cat' as prefix 
            FROM categories c where audio_location is null ORDER BY c.category_id;";

        // this query for counting table
$query = "SELECT c.counting_id as id, c.name_in_bhasha as word, c.language_id as language_id, 'count' as prefix 
            FROM counting c where c.audio_location is null;";

        // this query for General_words table
$query = "SELECT gw.general_word_id as id, gw.word_in_bhasha as word, c.language_id as language_id, 'gw' as prefix 
            FROM general_words gw, categories c 
            where gw.category_id = c.category_id
            and gw.audio_location is null
            ORDER BY gw.general_word_id;;";

        // this query for letter_categores table
$query = "SELECT lc.letter_category_id as id, lc.name_in_bhasha as word, lc.language_id as language_id, 'lc' as prefix 
            FROM letter_categories lc where lc.audio_location is null ORDER BY lc.letter_category_id";

        // this query for letters table
$query = "SELECT l.letter_id as id, l.letter_in_bhasha as word, l.language_id as language_id, 'l' as prefix 
            FROM letters l where audio_location is null ORDER BY l.letter_id;";

        // this query for letter_words table
$query = "SELECT lw.letter_word_id as id, concat(concat(lw.letter_in_bhasha, IFNULL(lg.se_alternate,'    ')), lw.word_in_bhasha) as word, l.language_id as language_id, 'lw' as prefix 
                from letter_words lw, letters l, languages lg
                where lw.letter_id = l.letter_id
                and l.language_id = lg.language_id
                and lw.audio_location is null;";

*/


/*
        // this query for poems-title table
$query = "SELECT p.poem_id as id, p.poem_title_bhasha as word, p.language_id as language_id, 'poem_title' as prefix 
            FROM poems p 
            where poem_title_audio_location is null
            ORDER BY p.poem_id;";

        // this query for poems-bhasha table
$query = "SELECT p.poem_id as id, p.poem_bhasha as word, p.language_id as language_id, 'poem_bhasha' as prefix 
            FROM poems p 
            where poem_bhasha_audio_location is null
            ORDER BY p.poem_id;";
*/


/*
        // this query for Story-title table
$query = "SELECT s.story_id as id, s.story_title_bhasha as word, s.language_id as language_id, 'story_title' as prefix 
            FROM stories s where story_title_audio_location is null ORDER BY s.story_id;";

        // this query for Stories table
$query = "SELECT s.story_id as id, s.story_bhasha as word, s.language_id as language_id, 'story_bhasha' as prefix 
            FROM stories s where story_bhasha_audio_location is null ORDER BY s.story_id;";

        // this query for Story-moral table
$query = "SELECT s.story_id as id, s.moral_text as word, s.language_id as language_id, 'story_moral' as prefix 
            FROM stories s where story_moral_audio_location is null and moral_text is not null ORDER BY s.story_id;";
*/

$query = "SELECT l.letter_id as id, l.letter_in_bhasha as word, l.language_id as language_id, 'letter' as prefix 
            FROM letters l where audio_location is null ORDER BY l.letter_id;";
 
$conn = get_db_connection();
$result = $conn->query($query); 
if (!$result) {
    respond_and_exit(500, ['error' => 'Failed to execute query', 'details' => $conn->error]);
}
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'file_name' => $row['prefix'].'_'. $row['language_id'].'_'.$row['id'].'.ogg',
        'word' => $row['word'],
        'language_id' => $row['language_id']
    ];
}
echo json_encode($data);

?>
