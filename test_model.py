import sys
import os
import cv2

# Tambahkan folder raspberry_pi ke path agar bisa import edge_gateway
sys.path.append(os.path.join(os.path.dirname(__file__), "raspberry_pi"))

import edge_gateway

def main():
    if len(sys.argv) < 2:
        print("Penggunaan: python test_model.py <path_ke_gambar>")
        print("Contoh: python test_model.py retakan1.jpg")
        sys.exit(1)

    image_path = sys.argv[1]
    if not os.path.exists(image_path):
        print(f"File tidak ditemukan: {image_path}")
        sys.exit(1)

    # Load model ONNX (sesuaikan path dengan lokasi best.onnx yang baru)
    edge_gateway.MODEL_PATH = "d:/lomba/model/best.onnx"
    print("Loading model...")
    if not edge_gateway.load_model():
        sys.exit(1)

    print(f"Menganalisis gambar: {image_path}")
    annotated_path, max_conf, crack_count = edge_gateway.run_crack_detection(image_path)

    print("\n--- HASIL DETEKSI ---")
    print(f"Jumlah Retakan : {crack_count}")
    print(f"Confidence Max : {max_conf * 100:.1f}%")
    print(f"Gambar Hasil   : {annotated_path}")
    print("---------------------\n")

    # Tampilkan gambar (tekan sembarang tombol untuk menutup)
    img = cv2.imread(annotated_path)
    if img is not None:
        cv2.imshow("Hasil Deteksi AI", img)
        print("Tekan tombol apapun pada jendela gambar untuk menutup...")
        cv2.waitKey(0)
        cv2.destroyAllWindows()

if __name__ == "__main__":
    main()
