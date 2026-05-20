<?php
// MySQL Database Configuration
$dbHost = 'localhost';
$dbUser = 'mqyvhbte_dataload';
$dbPass = 'DataLoad!23';
$dbName = 'mqyvhbte_desibhasha';

// Folder path to retrieve files
$INPUT_FOLDER = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'general';
$server_folder_path = 'assets/images/general';

function create_mysql_connection($host, $user, $pass, $db) {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_errno) {
        echo "✗ Error connecting to MySQL: " . $conn->connect_error . PHP_EOL;
        return null;
    }
    echo "✓ Successfully connected to MySQL database" . PHP_EOL;
    return $conn;
}

function get_files_from_folder($folder_path) {
    $files = [];
    if (!is_dir($folder_path)) {
        echo "✗ Folder not found: {$folder_path}" . PHP_EOL;
        return $files;
    }
    $entries = scandir($folder_path);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = $folder_path . DIRECTORY_SEPARATOR . $entry;
        if (is_file($full)) $files[] = $entry;
    }
    echo "✓ Found " . count($files) . " file(s) in folder" . PHP_EOL;
    return $files;
}

function update_general_words_table($conn, $file_name, $server_folder_path) {
    $query = "UPDATE mqyvhbte_desibhasha.letter_words 
              SET image_location = ? 
              WHERE LOWER(REPLACE(english_meaning,' ', '_')) = ? 
                AND image_location IS NULL";


/*    $query = "UPDATE mqyvhbte_desibhasha.general_words 
              SET image_location = ? 
              WHERE LOWER(REPLACE(english_meaning,' ', '_')) = ? 
                AND image_location IS NULL";
*/

    if (!($stmt = $conn->prepare($query))) {
        echo "✗ Prepare failed: " . $conn->error . PHP_EOL;
        return;
    }
    $name_without_ext = strtolower(pathinfo($file_name, PATHINFO_FILENAME));
    $imageLocation = $server_folder_path . '/' . $file_name;
    $stmt->bind_param('ss', $imageLocation, $name_without_ext);
    if (!$stmt->execute()) {
        echo "✗ Error updating table: " . $stmt->error . PHP_EOL;
        $stmt->close();
        return;
    }
    $affected = $stmt->affected_rows;
    if ($affected === 0) {
        echo "⚠ No rows updated for: {$file_name}" . PHP_EOL;
    } else {
        echo "✓ Updated {$affected} row(s) for: {$file_name}" . PHP_EOL;
    }
    $stmt->close();
}

function main() {
    global $dbHost, $dbUser, $dbPass, $dbName, $INPUT_FOLDER, $server_folder_path;
    $conn = create_mysql_connection($dbHost, $dbUser, $dbPass, $dbName);
    if (!$conn) return;

    $files = get_files_from_folder($INPUT_FOLDER);
    if (empty($files)) {
        echo "No files found to process" . PHP_EOL;
        $conn->close();
        return;
    }

    echo PHP_EOL . "Processing files..." . PHP_EOL;
    $total = count($files);
    foreach ($files as $i => $file_name) {
        $index = $i + 1;
        echo PHP_EOL . "[{$index}/{$total}] Processing: {$file_name}" . PHP_EOL;
        //update_general_words_table($conn, $file_name, $server_folder_path);
        exit();
    }

    echo PHP_EOL . "✓ All files processed successfully!" . PHP_EOL;

    $conn->close();
    echo PHP_EOL . "✓ Database connection closed" . PHP_EOL;
}
main();
?>