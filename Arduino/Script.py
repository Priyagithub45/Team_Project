# import serial
# import time
# import oracledb

# PORT = "COM5"
# BAUD_RATE = 9600

# DB_USER = "CLECKONMART"
# DB_PASS = "clecphp99"
# DB_DSN = "localhost:1521/FREEPDB1"

# TARGET_UID = "d32ca7e2"

# try:
#     # Connect to Oracle
#     conn = oracledb.connect(
#         user=DB_USER,
#         password=DB_PASS,
#         dsn=DB_DSN
#     )
#     cursor = conn.cursor()
#     print("Connected to Oracle database.")

#     # Connect to Arduino
#     arduino = serial.Serial(PORT, BAUD_RATE, timeout=1)
#     time.sleep(2)
#     print(f"Connected to Arduino on {PORT}")
#     print("Scan RFID card...\n")

#     while True:
#         if arduino.in_waiting > 0:
#             line = arduino.readline().decode("utf-8", errors="ignore").strip()

#             if line:
#                 print(line)

#                 if line.startswith("UID String:"):
#                     scanned_uid = line.replace("UID String:", "").strip().lower()

#                     if scanned_uid == TARGET_UID:
#                         print("\nMatching card scanned.")
#                         print("Printing all TRADER data...\n")

#                         cursor.execute("SELECT * FROM TRADER")

#                         columns = [col[0] for col in cursor.description]
#                         rows = cursor.fetchall()

#                         if not rows:
#                             print("No trader data found.")
#                         else:
#                             for row in rows:
#                                 print("-------------------")
#                                 for col_name, value in zip(columns, row):
#                                     print(f"{col_name}: {value}")
#                             print("-------------------\n")

#                     else:
#                         print("Card UID does not match.")

# except oracledb.DatabaseError as e:
#     print("Oracle database error:")
#     print(e)

# except serial.SerialException as e:
#     print("Arduino serial error:")
#     print(e)

# except KeyboardInterrupt:
#     print("\nStopped by user.")

# finally:
#     try:
#         cursor.close()
#         conn.close()
#         print("Oracle connection closed.")
#     except:
#         pass

#     try:
#         arduino.close()
#         print("Serial port closed.")
#     except:
#         pass

import serial
import time
import oracledb

# -----------------------------
# Arduino Configuration
# -----------------------------
PORT = "COM5"
BAUD_RATE = 9600

# -----------------------------
# Oracle Database Configuration
# -----------------------------
DB_USER = "CLECKONMART"
DB_PASS = "clecphp99"
DB_DSN = "localhost:1521/FREEPDB1"

# -----------------------------
# RFID Card UID
# -----------------------------
TARGET_UID = "d32ca7e2"

try:
    # -----------------------------
    # Connect to Oracle Database
    # -----------------------------
    conn = oracledb.connect(
        user=DB_USER,
        password=DB_PASS,
        dsn=DB_DSN
    )

    cursor = conn.cursor()

    print("Connected to Oracle Database.")

    # -----------------------------
    # Connect to Arduino
    # -----------------------------
    arduino = serial.Serial(PORT, BAUD_RATE, timeout=1)

    time.sleep(2)

    print(f"Connected to Arduino on {PORT}")
    print("Scan RFID Card...\n")

    # -----------------------------
    # Main Loop
    # -----------------------------
    while True:

        if arduino.in_waiting > 0:

            line = arduino.readline().decode(
                "utf-8",
                errors="ignore"
            ).strip()

            if line:

                print(line)

                # -----------------------------
                # Check RFID UID
                # -----------------------------
                if line.startswith("UID String:"):

                    scanned_uid = line.replace(
                        "UID String:",
                        ""
                    ).strip().lower()

                    print(f"Scanned UID: {scanned_uid}")

                    # -----------------------------
                    # If Correct RFID Card
                    # -----------------------------
                    if scanned_uid == TARGET_UID:

                        print("\nMatching RFID card detected.")

                        # -----------------------------
                        # Update Product Stock
                        # Any stock below 9 becomes 20
                        # -----------------------------
                        cursor.execute("""
                            UPDATE PRODUCT
                            SET STOCK_QUANTITY = 20
                            WHERE STOCK_QUANTITY < 9
                        """)

                        conn.commit()

                        print("Stock updated successfully.")
                        print("Low stock on dashboard should now become 0.\n")

                    else:
                        print("Card UID does not match.\n")

# -----------------------------
# Oracle Database Error
# -----------------------------
except oracledb.DatabaseError as e:

    print("Oracle Database Error:")
    print(e)

# -----------------------------
# Arduino Serial Error
# -----------------------------
except serial.SerialException as e:

    print("Arduino Serial Error:")
    print(e)

# -----------------------------
# Stop Program
# -----------------------------
except KeyboardInterrupt:

    print("\nProgram stopped by user.")

# -----------------------------
# Close Connections
# -----------------------------
finally:

    try:
        cursor.close()
        conn.close()
        print("Oracle connection closed.")
    except:
        pass

    try:
        arduino.close()
        print("Serial port closed.")
    except:
        pass 