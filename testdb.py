# test_db.py
from sqlalchemy import text
from database import engine, get_session

def test_connection():
    try:
        session = get_session()

        print("Connessione al database riuscita")

        result = session.execute(text("SHOW TABLES"))
        print("Tabelle trovate:")
        for row in result:
            print(" -", row[0])

        session.close()

    except Exception as e:
        print("Errore di connessione:")
        print(e)

if __name__ == "__main__":
    test_connection() 