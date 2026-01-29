from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker, declarative_base

Base = declarative_base()

# Modifica username e password con quelli del tuo MySQL
engine = create_engine("mysql+pymysql://chris:bailinxuan1005@localhost/KeyManager", echo=True)
 
Session = sessionmaker(bind=engine)
session = Session() 
