# login_app.py
import bcrypt
from sqlalchemy import text 
from login.database import get_session

def login():
    email = input("Email: ").strip()
    password = input("Password: ").strip()

    session = get_session() 

    try:
        result = session.execute(
            text("SELECT * FROM users WHERE email = :email"),
            {"email": email}
        ).fetchone()

        if not result:
            print("Utente non trovato.")
            return

        if result.email_verified == 0:
            print("Email non verificata.")
            return

        stored_hash = result.password_hash_master.encode()

        if bcrypt.checkpw(password.encode(), stored_hash):
            print("Login riuscito ")
            print(f"Benvenuto {result.username}")
        else:
            print("Password errata.")

    except Exception as e:
        print("Errore:", e)

    finally:
        session.close()


if __name__ == "__main__":
    login()