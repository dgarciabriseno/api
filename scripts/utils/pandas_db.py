"""
Requires SQLAlchemy and Pymysql plugins.
"""

from utils.database import get_dbinfo
from sqlalchemy import create_engine

def get_db_connection():
    db, username, password = get_dbinfo()
    engine = create_engine("mysql+pymysql://{}:{}@localhost/{}?charset=utf8mb4"
                            .format(username, password, db))
    conn = engine.connect()
    return conn
