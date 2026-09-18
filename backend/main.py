import os
import io
import csv
from fastapi import FastAPI, WebSocket, WebSocketDisconnect, HTTPException
from fastapi.responses import FileResponse, StreamingResponse
from fastapi.staticfiles import StaticFiles
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List
from .database import init_db, log_incident, get_all_incidents
from .camera import capture_snapshot

app = FastAPI(title="GeoGuard EWS API")

# Setup CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize DB
init_db()

# Pydantic models
class TelemetryData(BaseModel):
    timestamp: str
    acc_x: float
    acc_y: float
    acc_z: float
    pitch: float
    roll: float
    vibration_frequency: float
    ai_status: str
    confidence: float

# WebSocket Connection Manager
class ConnectionManager:
    def __init__(self):
        self.active_connections: List[WebSocket] = []

    async def connect(self, websocket: WebSocket):
        await websocket.accept()
        self.active_connections.append(websocket)

    def disconnect(self, websocket: WebSocket):
        self.active_connections.remove(websocket)

    async def broadcast(self, message: str):
        for connection in self.active_connections:
            await connection.send_text(message)

manager = ConnectionManager()

@app.websocket("/ws/telemetry")
async def websocket_endpoint(websocket: WebSocket):
    await manager.connect(websocket)
    try:
        while True:
            # Keep connection alive
            await websocket.receive_text()
    except WebSocketDisconnect:
        manager.disconnect(websocket)

@app.post("/api/telemetry")
async def ingest_telemetry(data: TelemetryData):
    # Broadcast to all connected clients
    await manager.broadcast(data.model_dump_json())
    
    # Check if critical hazard triggered
    if data.ai_status == "CRITICAL HAZARD":
        # Capture snapshot
        snapshot_path = capture_snapshot()
        # Log to DB
        tilt_delta = max(abs(data.pitch), abs(data.roll)) # simplified for demo
        log_incident(data.timestamp, data.ai_status, data.confidence, tilt_delta, snapshot_path)
        
    return {"status": "success"}

@app.get("/api/incidents")
def read_incidents():
    return get_all_incidents()

@app.get("/api/incidents/latest/image")
def read_latest_incident_image():
    incidents = get_all_incidents()
    if not incidents:
        raise HTTPException(status_code=404, detail="No incidents found")
    
    # get_all_incidents sorts by ID DESC, so index 0 is the latest
    latest_incident = incidents[0]
    filepath = latest_incident.get("snapshot_path")
    
    if not filepath or not os.path.exists(filepath):
        raise HTTPException(status_code=404, detail="Image not found on disk")
        
    return FileResponse(filepath)

@app.get("/api/incidents/{incident_id}/image")
def read_incident_image(incident_id: int):
    # Fetch incident from DB to get path
    incidents = get_all_incidents()
    incident = next((i for i in incidents if i["id"] == incident_id), None)
    if not incident or not incident.get("snapshot_path"):
        raise HTTPException(status_code=404, detail="Image not found")
        
    filepath = incident["snapshot_path"]
    if not os.path.exists(filepath):
        raise HTTPException(status_code=404, detail="File not found on disk")
        
    return FileResponse(filepath)

@app.get("/api/export")
def export_incidents():
    incidents = get_all_incidents()
    
    output = io.StringIO()
    writer = csv.DictWriter(output, fieldnames=["id", "timestamp", "trigger_class", "confidence", "tilt_delta", "snapshot_path"])
    writer.writeheader()
    writer.writerows(incidents)
    
    output.seek(0)
    return StreamingResponse(
        iter([output.getvalue()]), 
        media_type="text/csv", 
        headers={"Content-Disposition": "attachment; filename=incident_report.csv"}
    )

# Mount frontend files at root
frontend_dir = os.path.join(os.path.dirname(os.path.dirname(__file__)), "frontend")
if os.path.exists(frontend_dir):
    app.mount("/", StaticFiles(directory=frontend_dir, html=True), name="frontend")
