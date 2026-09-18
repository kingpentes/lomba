import cv2
import os
from datetime import datetime
import numpy as np

SNAPSHOT_DIR = "snapshots"
if not os.path.exists(SNAPSHOT_DIR):
    os.makedirs(SNAPSHOT_DIR)

def capture_snapshot() -> str:
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    filename = f"alert_{timestamp}.jpg"
    filepath = os.path.join(SNAPSHOT_DIR, filename)
    
    # Try to capture from webcam
    cap = cv2.VideoCapture(0)
    success = False
    
    if cap.isOpened():
        ret, frame = cap.read()
        if ret:
            cv2.imwrite(filepath, frame)
            success = True
        cap.release()
        
    if not success:
        # Fallback: create a blank image with text
        print(f"Warning: Failed to capture from webcam. Generating fallback image at {filepath}")
        frame = np.zeros((480, 640, 3), dtype=np.uint8)
        frame[:] = (0, 0, 50) # Dark red background
        cv2.putText(frame, 'WEBCAM UNAVAILABLE', (100, 200), cv2.FONT_HERSHEY_SIMPLEX, 1, (255, 255, 255), 2)
        cv2.putText(frame, 'Simulated Alert Snapshot', (100, 250), cv2.FONT_HERSHEY_SIMPLEX, 1, (255, 255, 255), 2)
        cv2.putText(frame, f'Time: {timestamp}', (100, 300), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 1)
        cv2.imwrite(filepath, frame)
        
    return filepath
