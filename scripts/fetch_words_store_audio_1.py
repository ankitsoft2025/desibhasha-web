import mysql.connector
from mysql.connector import Error
from mysql_file_update import DB_CONFIG
from google_tts_hindi import google_tts_save, LANGUAGE_CONFIG
import os
GOOGLE_API_KEY="AIzaSyD1BhlgTGTS56SWaiM0avAhvudxxFq77R4"

language_id_to_name = {
    1: 'Hindi',
    2: 'Punjabi',
    3: 'Telugu',
    4: 'Marathi',
    5: 'Gujarati',
    6: 'Tamil',
    7: 'Malayalam',
    8: 'Kannada',
    9: 'Bangla',
    10: 'Odiya'
}

def create_mysql_connection():
    """Create and return a MySQL database connection"""
    base_config = dict(DB_CONFIG)
    try:
        connection = mysql.connector.connect(**base_config)
        if connection.is_connected():
            print("✓ Successfully connected to MySQL database")
            return connection
    except Error as e:
        # Some older mysql-connector builds cannot handle MySQL 8 default auth plugin.
        if "caching_sha2_password" in str(e).lower():
            try:
                fallback_config = dict(base_config)
                fallback_config["auth_plugin"] = "mysql_native_password"
                connection = mysql.connector.connect(**fallback_config)
                if connection.is_connected():
                    print("✓ Connected to MySQL using mysql_native_password fallback")
                    return connection
            except Error as fallback_error:
                print(f"✗ Error connecting to MySQL: {fallback_error}")
                print(
                    "Hint: upgrade connector in your venv: "
                    "pip install --upgrade mysql-connector-python"
                )
                return None
        print(f"✗ Error connecting to MySQL: {e}")
        return None
    
def get_letters_from_database(connection, language_id=1):
    cursor = connection.cursor()
    query = f"SELECT letter_id, letter_in_bhasha FROM mqyvhbte_desibhasha.letters WHERE language_id={language_id} ORDER BY letter_id ASC"
    cursor.execute(query)
    return cursor.fetchall()

def get_general_words_from_database(connection):
    cursor = connection.cursor()
    query = "SELECT g.general_word_id as id, g.word_in_bhasha as word, c.language_id as language_id, 'gw' as prefix FROM mqyvhbte_desibhasha.general_words g join categories c on (g.category_id=c.category_id) where audio_location IS NULL ORDER BY g.general_word_id ASC"
    cursor.execute(query)
    return cursor.fetchall()

def get_category_words_from_database(connection):
    cursor = connection.cursor()
    query = "SELECT category_id as id, name_in_bhasha as word, language_id as language_id, 'cat' as prefix FROM categories ORDER BY category_id"
    cursor.execute(query)
    return cursor.fetchall()

def get_counting_words_from_database(connection):
    cursor = connection.cursor()
    query = "SELECT counting_id as id, number_in_bhasha as word, language_id as language_id, 'count' as prefix FROM counting ORDER BY counting_id"
    cursor.execute(query)
    return cursor.fetchall()
def get_letter_words_from_database(connection):
    cursor = connection.cursor()
    query = "SELECT letter_id as id, letter_in_bhasha as word, language_id as language_id, 'letter' as prefix FROM letters ORDER BY letter_id"
    cursor.execute(query)
    return cursor.fetchall()
def get_letter_category_words_from_database(connection):
    cursor = connection.cursor()
    query = "SELECT lc.letter_category_id as id, lc.name_in_bhasha as word, lc.language_id as language_id, 'lc' as prefix FROM letter_category lc ORDER BY lc.letter_category_id"
    cursor.execute(query)
    return cursor.fetchall()
def traverse_audio_files_and_update_database(connection, audio_directory="audio_output"):
    cursor = connection.cursor()
    for filename in os.listdir(audio_directory):
        if filename.endswith(".ogg"):
            try:
                language_id_str, general_word_id_str = filename[:-4].split("_")
                general_word_id = int(general_word_id_str)
                file_name = "gw_"+filename
                update_query = "UPDATE mqyvhbte_desibhasha.general_words SET audio_location=%s WHERE general_word_id=%s"
                cursor.execute(update_query, ("assets/audio/general_words/"+file_name, general_word_id))
                connection.commit()
                # save audio_file with new name in audio_output directory
                os.rename(os.path.join(audio_directory, filename), os.path.join(audio_directory, file_name))

                print(f"✓ Updated database for '{filename}'")
            except Exception as e:
                print(f"✗ Error processing '{filename}': {e}")

def main():
    """Main function to orchestrate the process"""
    # Create connection
    connection = create_mysql_connection()
    if not connection:
        return
    # traverse_audio_files_and_update_database(connection)
    general_words = get_general_words_from_database(connection)
    print(f"Fetched {len(general_words)} general words from the database.")
    total_words = len(general_words)
    for index, (id, word, language_id, prefix) in enumerate(general_words, start=1):
        language_name = language_id_to_name.get(language_id, "Unknown")
        print(f"Processing word {index}/{total_words}: {word} (Language: {language_name})")
        filename = f"{prefix}_{language_id}_{id}.ogg"
        google_tts_save(
            text=word,
            language=language_name.lower(),
            dialect="",
            gender="female",
            filename=filename,
            api_key=GOOGLE_API_KEY,
            audio_encoding="OGG_OPUS",
            sample_rate_hz=16000,
            folder_name="audio_output_"+prefix
        )
        print(f"✓ Saved audio for '{word}' as '{filename}'")
        
if __name__ == "__main__":
    main()