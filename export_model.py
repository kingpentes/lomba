from ultralytics import YOLO
model = YOLO("D:/lomba/model/best.pt")
model.export(format="onnx", imgsz=640, simplify=True)
