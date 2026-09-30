"""
Script tes koneksi Google Earth Engine + Sentinel-1
Jalankan: python test_gee.py
"""
import json
import os
import sys

# Force UTF-8 output on Windows
if sys.platform == 'win32':
    sys.stdout.reconfigure(encoding='utf-8')

# Path ke credential
CRED_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'config', 'gee_credentials.json')

print("=" * 60)
print("  GeoGuard - Test Koneksi Google Earth Engine")
print("=" * 60)

# 1. Cek credential
print(f"\n[1/4] Mengecek credential di: {CRED_PATH}")
if not os.path.exists(CRED_PATH):
    print("   GAGAL: File credential tidak ditemukan!")
    sys.exit(1)

with open(CRED_PATH, 'r') as f:
    cred = json.load(f)
print(f"   OK: Credential ditemukan!")
print(f"   Email: {cred.get('client_email', 'N/A')}")
print(f"   Project: {cred.get('project_id', 'N/A')}")

# 2. Install earthengine-api jika belum ada
print(f"\n[2/4] Mengecek package earthengine-api...")
try:
    import ee
    print(f"   OK: earthengine-api sudah terinstal (versi: {ee.__version__})")
except ImportError:
    print("   INSTALLING: earthengine-api belum terinstal. Menginstal...")
    os.system(f"{sys.executable} -m pip install earthengine-api")
    import ee
    print(f"   OK: earthengine-api berhasil diinstal (versi: {ee.__version__})")

# 3. Inisialisasi GEE
print(f"\n[3/4] Menginisialisasi Google Earth Engine...")
try:
    credentials = ee.ServiceAccountCredentials(
        email=cred['client_email'],
        key_file=CRED_PATH
    )
    ee.Initialize(credentials)
    print("   OK: Earth Engine berhasil diinisialisasi!")
except Exception as e:
    print(f"   GAGAL: {e}")
    print("\n   Kemungkinan penyebab:")
    print("   - Service Account belum didaftarkan ke Earth Engine")
    print("   - Earth Engine API belum di-enable di Google Cloud Console")
    print("   - Koneksi internet bermasalah")
    sys.exit(1)

# 4. Query Sentinel-1
print(f"\n[4/4] Mengambil data Sentinel-1 GRD...")
try:
    lat, lon = -8.611, 115.201
    point = ee.Geometry.Point([lon, lat])
    aoi = point.buffer(500)

    from datetime import datetime, timedelta
    end_date = datetime.utcnow()
    start_date = end_date - timedelta(days=30)

    collection = (
        ee.ImageCollection('COPERNICUS/S1_GRD')
        .filterBounds(aoi)
        .filterDate(start_date.strftime('%Y-%m-%d'), end_date.strftime('%Y-%m-%d'))
        .filter(ee.Filter.eq('instrumentMode', 'IW'))
        .filter(ee.Filter.listContains('transmitterReceiverPolarisation', 'VH'))
        .select('VH')
    )

    count = collection.size().getInfo()
    print(f"   OK: Ditemukan {count} citra Sentinel-1 dalam 30 hari terakhir.")

    if count > 0:
        first = collection.first()
        info = first.getInfo()
        img_id = info.get('id', 'N/A')
        print(f"   Contoh citra: {img_id}")

        mean_img = collection.mean()
        stats = mean_img.reduceRegion(
            reducer=ee.Reducer.mean(),
            geometry=point,
            scale=10
        ).getInfo()
        vh_value = stats.get('VH', 'N/A')
        if isinstance(vh_value, (int, float)):
            print(f"   Rata-rata VH backscatter: {vh_value:.2f} dB")
        else:
            print(f"   VH: {vh_value}")
    else:
        print("   WARNING: Tidak ada citra di area ini dalam 30 hari terakhir.")

except Exception as e:
    print(f"   GAGAL query: {e}")
    sys.exit(1)

print("\n" + "=" * 60)
print("  SEMUA TES BERHASIL! GEE siap digunakan di Airflow.")
print("=" * 60)
