import sys
import os
import cv2
import tkinter as tk
from tkinter import filedialog, messagebox
from PIL import Image, ImageTk

# Tambahkan folder raspberry_pi ke path agar bisa import edge_gateway
sys.path.append(os.path.join(os.path.dirname(__file__), "raspberry_pi"))
import edge_gateway

class CrackDetectionApp:
    def __init__(self, root):
        self.root = root
        self.root.title("GeoGuard AI - Crack Detection Tester")
        self.root.geometry("800x650")
        self.root.configure(bg="#0f172a") # Warna background dark mode
        
        # Header
        self.lbl_header = tk.Label(root, text="GeoGuard AI Tester", font=("Arial", 20, "bold"), fg="#38bdf8", bg="#0f172a")
        self.lbl_header.pack(pady=15)
        
        # Info Status
        self.lbl_info = tk.Label(root, text="Memuat model ONNX, mohon tunggu...", font=("Arial", 12), fg="white", bg="#0f172a")
        self.lbl_info.pack(pady=5)
        self.root.update()
        
        # Load Model
        edge_gateway.MODEL_PATH = "d:/lomba/model/best.onnx"
        if not edge_gateway.load_model():
            messagebox.showerror("Error", "Gagal memuat model ONNX! Pastikan file best.onnx ada di d:/lomba/model/best.onnx")
            sys.exit(1)
            
        self.lbl_info.config(text="Model SIAP! Silakan upload gambar tanah/tembok.")
            
        # Button Upload
        self.btn_upload = tk.Button(root, text="📂 Pilih & Analisis Gambar", font=("Arial", 12, "bold"), 
                                    bg="#0284c7", fg="white", padx=10, pady=5, borderwidth=0, cursor="hand2",
                                    command=self.upload_image)
        self.btn_upload.pack(pady=10)
        
        # Canvas untuk gambar
        self.canvas = tk.Label(root, bg="#1e293b", text="Belum ada gambar", fg="#64748b", font=("Arial", 14), width=60, height=20)
        self.canvas.pack(pady=10, padx=20, expand=True)
        
    def upload_image(self):
        file_path = filedialog.askopenfilename(
            title="Pilih Gambar untuk Dites",
            filetypes=[("Image Files", "*.jpg;*.jpeg;*.png")]
        )
        if not file_path:
            return
            
        self.lbl_info.config(text="⏳ Memproses gambar... mohon tunggu.", fg="#eab308")
        self.btn_upload.config(state="disabled")
        self.root.update()
        
        try:
            # Jalankan AI dari edge_gateway
            annotated_path, max_conf, crack_count = edge_gateway.run_crack_detection(file_path)
            
            # Update Status Teks
            if crack_count > 0:
                self.lbl_info.config(text=f"🚨 TERDETEKSI {crack_count} RETAKAN! (Confidence: {max_conf*100:.1f}%)", fg="#ef4444")
            else:
                self.lbl_info.config(text="✅ TIDAK ADA RETAKAN. Permukaan aman.", fg="#22c55e")
                
            # Tampilkan Gambar Hasil
            img = cv2.imread(annotated_path)
            if img is not None:
                img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
                
                # Resize agar muat cantik di UI layar
                h, w = img.shape[:2]
                max_size = 500
                if max(h, w) > max_size:
                    scale = max_size / max(h, w)
                    img = cv2.resize(img, (int(w * scale), int(h * scale)))
                    
                img_pil = Image.fromarray(img)
                img_tk = ImageTk.PhotoImage(img_pil)
                
                self.canvas.config(image=img_tk, text="", width=0, height=0)
                self.canvas.image = img_tk  # Wajib disimpan ke variable agar tidak kena garbage collection
                
        except Exception as e:
            messagebox.showerror("Error", f"Terjadi kesalahan saat AI memproses:\n{str(e)}")
            self.lbl_info.config(text="❌ Error memproses gambar.", fg="#ef4444")
            
        finally:
            self.btn_upload.config(state="normal")

if __name__ == "__main__":
    root = tk.Tk()
    app = CrackDetectionApp(root)
    root.mainloop()
