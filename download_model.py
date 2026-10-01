import os
from huggingface_hub import hf_hub_download
from ultralytics import YOLO

print("=" * 60)
print("  GEOGUARD — Downloading Crack Detection Model (Local)")
print("=" * 60)

# Download model OpenSistemas
model_path = hf_hub_download(
    repo_id="OpenSistemas/YOLOv8-crack-seg",
    filename="best.pt",
    revision="main",
    local_dir="d:/lomba/raspberry_pi/models"
)
print(f"✅ Model berhasil didownload: {model_path}")

print("\n" + "=" * 60)
print("  EXPORT MODEL KE FORMAT ONNX")
print("=" * 60)

export_model = YOLO(model_path)
onnx_path = export_model.export(
    format="onnx",
    imgsz=640,
    simplify=True,
    opset=11,
    half=False,
)

print(f"\n✅ Model ONNX berhasil di-export!")
print(f"   File: {onnx_path}")
print(f"   Size: {os.path.getsize(onnx_path) / 1024 / 1024:.1f} MB")
