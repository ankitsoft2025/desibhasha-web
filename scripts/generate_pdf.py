import os
import time
import mysql.connector
from mysql.connector import Error
from urllib.parse import urlparse
from urllib.request import Request, urlopen
from mysql_file_update import DB_CONFIG
from pdf_write import create_learning_pdf

def get_words_from_database(connection):
    cursor = connection.cursor()
    query = """SELECT l.language_id, lw.letter_in_bhasha, lw.word_in_bhasha, lw.english_meaning, lw.image_location, practice_sheet, concat('ps_', concat(lw.letter_word_id, concat('_', concat(lw.letter_id, '.pdf')))) as practice_sheet_file_name, 'assets/images/practicesheets' as practice_sheet_location		
FROM letter_words lw
inner join letters l using(letter_id)
where lw.image_location is not null
and lw.practice_sheet is null and l.language_id=1;"""
    cursor.execute(query)
    return cursor.fetchall()
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

def get_local_image_path(image_location, cache_folder):
    if image_location.startswith("http://") or image_location.startswith("https://"):
        remote_url = image_location
    else:
        remote_url = f"https://desibhasha.com/{image_location.lstrip('/')}"

    parsed = urlparse(remote_url)
    local_subpath = parsed.path.lstrip("/")
    local_path = os.path.join(cache_folder, *local_subpath.split("/"))
    os.makedirs(os.path.dirname(local_path), exist_ok=True)

    if not os.path.exists(local_path):
        try:
            print(f"Downloading image from {remote_url}")
            request = Request(
                remote_url,
                headers={
                    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
                    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
                },
            )
            with urlopen(request) as response, open(local_path, "wb") as out_file:
                out_file.write(response.read())
        except Exception as e:
            raise RuntimeError(f"Failed to download image {remote_url}: {e}")

    return local_path

def main():
    """Main function to orchestrate the process"""
    # Create connection
    connection = create_mysql_connection()
    if not connection:
        return
    try:
        # Prepare separate output folder for Hindi PDFs
        output_folder = os.path.join(os.path.dirname(__file__), "output", "hindi_pdfs")
        os.makedirs(output_folder, exist_ok=True)
        image_cache_folder = os.path.join(output_folder, "image_cache")
        os.makedirs(image_cache_folder, exist_ok=True)

        # Fetch words
        words = get_words_from_database(connection)
        print(f"Fetched {len(words)} words from the database.")
        # Create PDFs
        for word in words:
            (
                language_id,
                letter_in_bhasha,
                word_in_bhasha,
                english_meaning,
                image_location,
                practice_sheet,
                practice_sheet_file_name,
                practice_sheet_location,
            ) = word
            pdf_path = os.path.join(output_folder, practice_sheet_file_name)
            try:
                image_path = get_local_image_path(image_location, image_cache_folder)
            except Exception as download_error:
                print(f"⚠ Skipping word because image download failed: {download_error}")
                time.sleep(1)
                continue

            create_learning_pdf(
                letter_in_bhasha,
                word_in_bhasha,
                english_meaning,
                image_path,
                language_id,
                output_filename=pdf_path,
            )
            print(f"Created PDF: {pdf_path}")
            time.sleep(1)
    except Exception as e:

        print(f"✗ An error occurred: {e}")
    finally:
        connection.close()
        print("✓ MySQL connection closed.")

    
if __name__ == "__main__":
    main()