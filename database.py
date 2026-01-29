# database.py
from sqlalchemy import create_engine
from sqlalchemy.orm import declarative_base, sessionmaker

# === CONFIGURAZIONE DATABASE ===
DB_USER = "chris"
DB_PASSWORD = "bailinxuan1005"
DB_HOST = "localhost"
DB_NAME = "KeyManager"

DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}/{DB_NAME}"

# === ENGINE ===
engine = create_engine(
    DATABASE_URL,
    echo=True,            # mostra le query SQL (utile in sviluppo)
    pool_pre_ping=True    # evita errori di connessione perse
)

# === BASE ORM ===
Base = declarative_base()

# === SESSION ===
SessionLocal = sessionmaker(
    bind=engine,
    autoflush=False,
    autocommit=False
)

def get_session():
    """Ritorna una nuova sessione DB"""
    return SessionLocal()