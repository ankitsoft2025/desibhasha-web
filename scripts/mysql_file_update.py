import mysql.connector
from mysql.connector import Error
import os
from pathlib import Path

# MySQL Database Configuration
DB_CONFIG = {
    'host': '50.6.35.221',      # Change to your host
    'user': 'mqyvhbte_dataload',           # Change to your username
    'password': 'DataLoad!23',           # Change to your password
    'database': 'mqyvhbte_desibhasha'  # Change to your database name
}

# Folder path to retrieve files
INPUT_FOLDER = r'C:\Users\pc\Desktop\python\desibhasha-scripts\image-script\server\images\grains'
server_folder_path = r'assets/images/grains'

def create_mysql_connection():
    """Create and return a MySQL database connection"""
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        if connection.is_connected():
            print("✓ Successfully connected to MySQL database")
            return connection
    except Error as e:
        print(f"✗ Error connecting to MySQL: {e}")
        return None


def get_files_from_folder(folder_path):
    """Retrieve all files from the specified folder"""
    try:
        if not os.path.exists(folder_path):
            print(f"✗ Folder not found: {folder_path}")
            return []
        
        files = [f for f in os.listdir(folder_path) if os.path.isfile(os.path.join(folder_path, f))]
        print(f"✓ Found {len(files)} file(s) in folder")
        return files
    except Exception as e:
        print(f"✗ Error reading folder: {e}")
        return []


def update_general_words_table(connection, file_name, file_id=None):
    """Update file names in general_words table"""
    try:
        cursor = connection.cursor()
        
        
            # If you want to update based on file name pattern
        query = "Update mqyvhbte_desibhasha.general_words set image_location = %s where lower(REPLACE(english_meaning,' ', '_'))=%s and image_location is null"
        cursor.execute(query, (f"{server_folder_path}/{file_name}", f"{file_name.split('.')[0].lower()}"))
                

        connection.commit()
        if cursor.rowcount == 0:
            print(f"⚠ No rows updated for: {file_name}")
        else:
            print(f"✓ Updated {cursor.rowcount} row(s) for: {file_name}")
        cursor.close()
        
    except Error as e:
        print(f"✗ Error updating table: {e}")
        connection.rollback()


def main():
    """Main function to orchestrate the process"""
    # Create connection
    connection = create_mysql_connection()
    if not connection:
        return
    
    try:
        # Get files from folder
        files = get_files_from_folder(INPUT_FOLDER)
        
        if not files:
            print("No files found to process")
            return
        
        # Process each file
        print("\nProcessing files...")
        for index, file_name in enumerate(files, 1):
            print(f"\n[{index}/{len(files)}] Processing: {file_name}")
            update_general_words_table(connection, file_name, file_id=None)
        
        print("\n✓ All files processed successfully!")
        
    except Exception as e:
        print(f"✗ Error in main process: {e}")
    
    finally:
        # Close connection
        if connection.is_connected():
            connection.close()
            print("\n✓ Database connection closed")


if __name__ == "__main__":
    print("=" * 50)
    print("MySQL File Name Update Script")
    print("=" * 50)
    main()
