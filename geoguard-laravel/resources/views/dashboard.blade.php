@extends('layouts.app')

@section('title', 'EWS Dashboard')

@push('head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        /* Navbar is h-14 = 3.5rem, so subtract it from 100vh */
        .bento-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; grid-template-rows: auto 1fr; gap: 1rem; height: calc(100vh - 3.5rem); padding: 1rem; box-sizing: border-box; }
        .col-span-3-module { grid-column: span 3; }
        .module { background-color: #0f172a; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1rem; display: flex; flex-direction: column; overflow: hidden; }
        .pulse-border { animation: pulseRed 2s infinite; }
        @keyframes pulseRed { 0% { box-shadow: inset 0 0 10px rgba(239, 68, 68, 0.2), 0 0 0 2px rgba(239, 68, 68, 0.5); border-color: rgba(239, 68, 68, 0.8); } 50% { box-shadow: inset 0 0 30px rgba(239, 68, 68, 0.8), 0 0 0 4px rgba(239, 68, 68, 0.8); border-color: rgba(239, 68, 68, 1); } 100% { box-shadow: inset 0 0 10px rgba(239, 68, 68, 0.2), 0 0 0 2px rgba(239, 68, 68, 0.5); border-color: rgba(239, 68, 68, 0.8); } }
        
        .pulse-marker { width: 24px; height: 24px; background: rgba(239, 68, 68, 0.8); border-radius: 50%; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); animation: mapPulse 1.5s infinite; }
        .normal-marker { width: 16px; height: 16px; background: rgba(34, 197, 94, 0.8); border-radius: 50%; border: 2px solid white; }
        @keyframes mapPulse { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
    </style>
@endpush

@section('content')
    <div id="hud" class="bento-grid">
        <!-- Module A: Telemetry -->
        <div class="module">
            <h2 class="text-lg font-bold mb-2 text-slate-400 flex justify-between items-center">
                <span>CORE TELEMETRY</span>
                <span id="activeNodeLabel" class="text-xs font-mono px-2 py-1 bg-cyan-900/30 text-cyan-400 rounded border border-cyan-500/30">INC_HW_01</span>
            </h2>
            <div id="statusBadge" class="text-3xl font-black text-center p-4 rounded bg-green-900/30 text-green-400 border border-green-500/50 mb-4 transition-colors duration-500">
                SYSTEM SAFE
            </div>
            <button id="muteBuzzerBtn" onclick="muteBuzzer()" class="w-full mb-4 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold py-2 px-4 rounded border border-slate-600 transition-colors shadow-lg">
                🔕 Matikan Sirine (Acknowledge)
            </button>
            <div class="grid grid-cols-2 gap-3 flex-grow">
                <div class="bg-slate-900/80 p-3 rounded text-center border border-slate-800 flex flex-col justify-center">
                    <div class="text-xs text-slate-500 font-semibold mb-1">ACCELERATION (X/Y/Z)</div>
                    <div class="text-lg font-mono text-cyan-400" id="accelDisp">0.0 / 0.0 / 0.0</div>
                </div>
                <div class="bg-slate-900/80 p-3 rounded text-center border border-slate-800 flex flex-col justify-center">
                    <div class="text-xs text-slate-500 font-semibold mb-1">TILT (PITCH / ROLL)</div>
                    <div class="text-lg font-mono text-cyan-400" id="tiltDisp">0.0&deg; / 0.0&deg;</div>
                </div>
                <div class="col-span-2 bg-slate-900/80 p-3 rounded text-center border border-slate-800 flex flex-col justify-center">
                    <div class="text-xs text-slate-500 font-semibold mb-1">FREQUENCY (Hz)</div>
                    <div class="text-lg font-mono text-amber-400" id="freqDisp">0.0</div>
                </div>
            </div>
        </div>


        <!-- Module B: Visual -->
        <div class="module">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-lg font-bold text-slate-400">VISUAL VERIFICATION</h2>
                <div id="liveIndicator" class="text-xs font-mono text-red-500 animate-pulse hidden">&#x25CF; LIVE EVENT</div>
            </div>
            <div class="relative flex-grow bg-black rounded border border-slate-700 flex items-center justify-center overflow-hidden group">
                <img id="cameraFeed" src="https://via.placeholder.com/640x360/0f172a/38bdf8.png?text=STANDBY+-+NO+ACTIVE+HAZARD+SNAPSHOT" alt="Camera" class="object-cover w-full h-full opacity-70 group-hover:opacity-100 transition-opacity">
                <!-- Tactical overlay grid -->
                <div class="absolute inset-0 pointer-events-none border border-cyan-900/50 m-2" style="background: linear-gradient(rgba(6,182,212,0.1) 1px, transparent 1px) 0 0 / 40px 40px, linear-gradient(90deg, rgba(6,182,212,0.1) 1px, transparent 1px) 0 0 / 40px 40px;"></div>
                <div class="absolute bottom-2 right-2 text-xs font-mono bg-black/60 px-2 py-1 text-cyan-500 border border-cyan-900">CAM-01 / SECTOR 7</div>
            </div>
        </div>

        <!-- Module D: Map -->
        <div class="module">
            <h2 class="text-lg font-bold mb-2 text-slate-400 flex justify-between items-center">
                <span>TACTICAL MAP</span>
                <div class="flex items-center">
                    <label class="inline-flex items-center cursor-pointer mr-3" title="Toggle InSAR Simulation Mode">
                        <input type="checkbox" id="simToggle" onchange="toggleSimMode(this)" class="sr-only peer">
                        <div class="relative w-7 h-4 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-cyan-500"></div>
                        <span class="ms-2 text-[10px] font-bold text-slate-400">SIM</span>
                    </label>
                    <button id="setLocBtn" onclick="promptSetLocation()" class="text-[10px] bg-slate-800 hover:bg-cyan-900 text-cyan-400 px-2 py-1 rounded border border-cyan-800 transition-colors">SET LOC</button>
                </div>
            </h2>
            <div id="map" class="flex-grow rounded z-0 border border-slate-700 min-h-[250px]"></div>
        </div>

        <!-- Module C: Chart -->
        <div class="module col-span-3-module">
            <h2 class="text-lg font-bold mb-2 text-slate-400">REAL-TIME TREND</h2>
            <div class="flex-grow relative w-full h-full bg-slate-900/50 rounded p-2">
                <canvas id="telemetryChart"></canvas>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Map Initialization
        const map = L.map('map', { zoomControl: false }).setView([-1.215, 116.851], 14);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        }).addTo(map);
        
        let marker = L.marker([-1.215, 116.851], {
            icon: L.divIcon({ className: 'normal-marker', iconSize: [16, 16], iconAnchor: [8, 8] }),
            zIndexOffset: 1000
        }).addTo(map);

        // Load InSAR GeoJSON Data (Simulasi / Ekspor dari QGIS)
        fetch('/data/insar_data.geojson')
            .then(res => res.json())
            .then(data => {
                L.geoJSON(data, {
                    pointToLayer: function (feature, latlng) {
                        let vel = feature.properties.velocity_mm_yr;
                        let color = '#22c55e'; // Green (Stable)
                        let radius = 6;
                        
                        if (vel <= -50) {
                            color = '#ef4444'; // Red (Critical Subsidence)
                            radius = 12;
                        } else if (vel <= -20) {
                            color = '#f59e0b'; // Amber (Warning)
                            radius = 9;
                        }

                        let circle = L.circleMarker(latlng, {
                            radius: radius,
                            fillColor: color,
                            color: color,
                            weight: 1,
                            opacity: 0.8,
                            fillOpacity: 0.5
                        });
                        
                        circle.on('click', function(e) {
                            if (window.isSettingLocation) {
                                map.fireEvent('click', e);
                            } else {
                                circle.bindPopup(`<b>InSAR Data</b><br>Velocity: ${vel} mm/yr<br>Coherence: ${feature.properties.coherence}`).openPopup();
                            }
                        });
                        
                        return circle;
                    }
                }).addTo(map);
            })
            .catch(err => console.log('InSAR data not found yet.'));

        let currentNodeCode = localStorage.getItem('geoguard_active_node') || 'INC_HW_01';

        // Show which node is active
        function updateNodeIndicator() {
            const el = document.getElementById('activeNodeLabel');
            if (el) el.innerText = currentNodeCode;
        }
        updateNodeIndicator();

        function muteBuzzer() {
            const badgeText = document.getElementById('statusBadge').innerText;
            if (badgeText.includes('SAFE')) {
                alert('Sistem saat ini sedang dalam kondisi aman. Tidak ada sirine yang perlu dimatikan.');
                return;
            }

            const btn = document.getElementById('muteBuzzerBtn');
            btn.innerText = "⏳ Mengirim...";
            btn.disabled = true;

            fetch(`/api/v1/nodes/${currentNodeCode}/mute-buzzer`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if(!res.ok) {
                    return res.json().then(errData => { throw new Error(errData.message || 'Server Error ' + res.status); });
                }
                return res.json();
            })
            .then(data => {
                if(data.status === 'success') {
                    btn.innerText = "✅ Sirine Dimatikan";
                    btn.classList.remove('bg-slate-800', 'text-slate-300', 'border-slate-600', 'bg-red-900/50', 'text-red-400', 'border-red-500/50');
                    btn.classList.add('bg-green-900/50', 'text-green-400', 'border-green-500/50');
                } else {
                    throw new Error(data.message || 'Unknown Error');
                }
            })
            .catch(err => {
                console.error(err);
                btn.innerText = "❌ Gagal: " + err.message.substring(0, 30);
                btn.classList.remove('bg-slate-800', 'text-slate-300', 'border-slate-600');
                btn.classList.add('bg-red-900/50', 'text-red-400', 'border-red-500/50');
                
                // Kembalikan tombol seperti semula setelah 3 detik
                setTimeout(() => {
                    btn.innerText = "🔕 Matikan Sirine (Acknowledge)";
                    btn.classList.add('bg-slate-800', 'text-slate-300', 'border-slate-600');
                    btn.classList.remove('bg-red-900/50', 'text-red-400', 'border-red-500/50');
                    btn.disabled = false;
                }, 3000);
            });
        }

        // Chart Initialization
        const ctx = document.getElementById('telemetryChart').getContext('2d');
        const telemetryChart = new Chart(ctx, {
            type: 'line',
            data: { labels: [], datasets: [
                { label: 'Acc X', borderColor: '#ef4444', borderWidth: 2, tension: 0.3, pointRadius: 0, data: [] },
                { label: 'Acc Y', borderColor: '#3b82f6', borderWidth: 2, tension: 0.3, pointRadius: 0, data: [] },
                { label: 'Acc Z', borderColor: '#22c55e', borderWidth: 2, tension: 0.3, pointRadius: 0, data: [] }
            ]},
            options: {
                responsive: true, maintainAspectRatio: false, animation: { duration: 0 },
                plugins: { 
                    legend: { 
                        display: true, 
                        position: 'top', 
                        labels: { color: '#94a3b8', boxWidth: 12, usePointStyle: true, pointStyle: 'circle' } 
                    } 
                },
                scales: {
                    x: { display: false },
                    y: { grid: { color: '#334155' }, border: { dash: [4,4] }, ticks: { color: '#94a3b8', font: { family: 'monospace' } } }
                }
            }
        });

        // Polling Simulation Connection
        let isMapCentered = false;
        function fetchTelemetry() {
            Promise.all([
                fetch(`/api/v1/telemetry/latest?node_code=${currentNodeCode}`).then(res => res.json()),
                fetch('/api/v1/predictions/latest').then(res => res.json()).catch(() => ({}))
            ]).then(([data, predData]) => {
                if(data.error) return;
                
                // Evaluate combined risk level
                let worstStatus = data.status || 'NORMAL';
                if (predData && predData.risk_level) {
                    const teleRisk = worstStatus.toUpperCase();
                    const predRisk = predData.risk_level.toUpperCase();
                    const riskOrder = { NORMAL: 0, STABLE: 0, WARNING: 1, WASPADA: 1, 'OPERATIONAL NOISE': 1, CRITICAL: 2, 'CRITICAL HAZARD': 2 };
                    if ((riskOrder[predRisk] || 0) > (riskOrder[teleRisk] || 0)) {
                        worstStatus = predRisk;
                    }
                }
                
                // Update Text
                    document.getElementById('accelDisp').innerText = `${data.acc_x.toFixed(2)} / ${data.acc_y.toFixed(2)} / ${data.acc_z.toFixed(2)}`;
                    const pText = data.pitch.toFixed(1);
                    const rText = data.roll.toFixed(1);
                    
                    let tiltHtml = '';
                    tiltHtml += Math.abs(data.pitch) > 10 ? `<span class="text-red-500">${pText}&deg;</span>` : `${pText}&deg;`;
                    tiltHtml += ' / ';
                    tiltHtml += Math.abs(data.roll) > 10 ? `<span class="text-red-500">${rText}&deg;</span>` : `${rText}&deg;`;
                    document.getElementById('tiltDisp').innerHTML = tiltHtml;
                    
                    document.getElementById('freqDisp').innerText = data.vibration_freq.toFixed(1);
                    if(data.node_code) {
                        currentNodeCode = data.node_code;
                    }

                    const hud = document.getElementById('hud');
                    const badge = document.getElementById('statusBadge');
                    const muteBtn = document.getElementById('muteBuzzerBtn');
                    
                    if (worstStatus === 'CRITICAL HAZARD' || worstStatus === 'CRITICAL') {
                        hud.classList.add('pulse-border');
                        badge.className = 'text-3xl font-black text-center p-4 rounded bg-red-900/80 text-white mb-4 border-2 border-red-500 pulse-border transition-colors duration-500';
                        badge.innerText = 'CRITICAL HAZARD!';
                        marker.setIcon(L.divIcon({ className: 'pulse-marker', iconSize: [24,24], iconAnchor: [12,12] }));
                        document.getElementById('liveIndicator').classList.remove('hidden');

                    } else if (worstStatus === 'OPERATIONAL NOISE' || worstStatus === 'WARNING' || worstStatus === 'WASPADA') {
                        hud.classList.remove('pulse-border');
                        badge.className = 'text-3xl font-black text-center p-4 rounded bg-amber-900/30 text-amber-400 border border-amber-500/50 mb-4 transition-colors duration-500';
                        badge.innerText = 'SYSTEM WARNING';
                        marker.setIcon(L.divIcon({ className: 'normal-marker', iconSize: [16,16], iconAnchor: [8,8] }));
                        document.getElementById('liveIndicator').classList.add('hidden');
                    } else {
                        hud.classList.remove('pulse-border');
                        badge.className = 'text-3xl font-black text-center p-4 rounded bg-green-900/30 text-green-400 border border-green-500/50 mb-4 transition-colors duration-500';
                        badge.innerText = 'SYSTEM SAFE';
                        marker.setIcon(L.divIcon({ className: 'normal-marker', iconSize: [16,16], iconAnchor: [8,8] }));
                        document.getElementById('liveIndicator').classList.add('hidden');
                        
                        // Reset mute button
                        muteBtn.disabled = false;
                        muteBtn.innerText = "🔕 Matikan Sirine (Acknowledge)";
                        muteBtn.classList.add('bg-slate-800', 'text-slate-300', 'border-slate-600');
                        muteBtn.classList.remove('bg-green-900/50', 'text-green-400', 'border-green-500/50');
                    }
                    
                    // Update Map Position
                    if (data.latitude && data.longitude && data.latitude !== 0) {
                        const newLatLng = [data.latitude, data.longitude];
                        marker.setLatLng(newLatLng);
                        if (!isMapCentered) {
                            map.setView(newLatLng);
                            isMapCentered = true;
                        }
                    }
                    
                    // Fetch latest snapshot
                    if(data.snapshot_url) {
                        document.getElementById('cameraFeed').src = data.snapshot_url + '?t=' + new Date().getTime();
                    } else {
                        document.getElementById('cameraFeed').src = "https://via.placeholder.com/640x360/0f172a/38bdf8.png?text=STANDBY+-+NO+ACTIVE+HAZARD+SNAPSHOT";
                    }
                    
                    // Update Chart
                    const now = new Date().toLocaleTimeString();
                    telemetryChart.data.labels.push(now);
                    telemetryChart.data.datasets[0].data.push(data.acc_x);
                    telemetryChart.data.datasets[1].data.push(data.acc_y);
                    telemetryChart.data.datasets[2].data.push(data.acc_z);
                    if(telemetryChart.data.labels.length > 30) {
                        telemetryChart.data.labels.shift();
                        telemetryChart.data.datasets.forEach(ds => ds.data.shift());
                    }
                    telemetryChart.update();
                })
                .catch(err => {
                    console.error("Connection lost", err);
                    document.getElementById('statusBadge').className = 'text-3xl font-black text-center p-4 rounded bg-slate-800 text-slate-500 mb-4';
                    document.getElementById('statusBadge').innerText = 'OFFLINE';
                });
        }
        
        setInterval(fetchTelemetry, 1000);

        let isSettingLocation = false;

        function promptSetLocation() {
            const setLocBtn = document.getElementById('setLocBtn');
            isSettingLocation = !isSettingLocation;
            if (isSettingLocation) {
                setLocBtn.innerText = "CLICK ON MAP";
                setLocBtn.classList.replace('bg-slate-800', 'bg-amber-600');
                setLocBtn.classList.replace('text-cyan-400', 'text-white');
                document.getElementById('map').style.cursor = 'crosshair';
            } else {
                setLocBtn.innerText = "SET LOC";
                setLocBtn.classList.replace('bg-amber-600', 'bg-slate-800');
                setLocBtn.classList.replace('text-white', 'text-cyan-400');
                document.getElementById('map').style.cursor = '';
            }
        }

        map.on('click', function(e) {
            if (!isSettingLocation) return;
            
            if(confirm(`Pindahkan tiang ke kordinat ini?\nLat: ${e.latlng.lat.toFixed(5)}\nLng: ${e.latlng.lng.toFixed(5)}`)) {
                fetch(`/api/v1/nodes/${currentNodeCode}/set-location`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ latitude: e.latlng.lat, longitude: e.latlng.lng })
                })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        // Immediately update local marker
                        marker.setLatLng(e.latlng);
                        alert('Lokasi berhasil diupdate!');
                    } else {
                        alert('Gagal: ' + data.message);
                    }
                }).catch(err => alert('Koneksi Error'));
            }
            
            // Auto turn off edit mode
            promptSetLocation();
        });

        // Initialize Simulation Toggle
        fetch('/api/v1/insar/mode')
            .then(res => res.json())
            .then(data => {
                document.getElementById('simToggle').checked = (data.mode === 'simulated');
            }).catch(err => console.log('Could not fetch sim mode.'));

        function toggleSimMode(el) {
            const mode = el.checked ? 'simulated' : 'real';
            fetch('/api/v1/insar/mode', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ mode: mode })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('InSAR mode set to ' + mode.toUpperCase() + '.\\nChange will take effect on the next Airflow DAG run.');
                }
            })
            .catch(err => {
                alert('Gagal mengubah mode InSAR.');
                el.checked = !el.checked;
            });
        }


    </script>
@endpush
