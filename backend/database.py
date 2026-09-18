import sqlite3
import os

DB_PATH = "geoguard.db"

def init_db():
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS incidents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL,
            trigger_class TEXT NOT NULL,
            confidence REAL NOT NULL,
            tilt_delta REAL NOT NULL,
            snapshot_path TEXT
        )
    """)
    conn.commit()
    conn.close()

def log_incident(timestamp, trigger_class, confidence, tilt_delta, snapshot_path):
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("""
        INSERT INTO incidents (timestamp, trigger_class, confidence, tilt_delta, snapshot_path)
        VALUES (?, ?, ?, ?, ?)
    """, (timestamp, trigger_class, confidence, tilt_delta, snapshot_path))
    conn.commit()
    conn.close()

def get_all_incidents():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM incidents ORDER BY id DESC")
    rows = cursor.fetchall()
    conn.close()
    return [dict(ix) for ix in rows]
