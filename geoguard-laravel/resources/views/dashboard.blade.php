<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoGuard - EWS Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { slate: { 950: '#020617' } } } } }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body { background-color: #020617; color: white; overflow: hidden; margin: 0; padding: 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }
        .bento-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 1rem; height: 100vh; padding: 1rem; box-sizing: border-box; }
        .col-span-2-module { grid-column: span 2; }
        .module { background-color: #0f172a; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1rem; display: flex; flex-direction: column; overflow: hidden; }
        .pulse-border { animation: pulseRed 2s infinite; }
        @keyframes pulseRed { 0% { box-shadow: inset 0 0 10px rgba(239, 68, 68, 0.2), 0 0 0 2px rgba(239, 68, 68, 0.5); border-color: rgba(239, 68, 68, 0.8); } 50% { box-shadow: inset 0 0 30px rgba(239, 68, 68, 0.8), 0 0 0 4px rgba(239, 68, 68, 0.8); border-color: rgba(239, 68, 68, 1); } 100% { box-shadow: inset 0 0 10px rgba(239, 68, 68, 0.2), 0 0 0 2px rgba(239, 68, 68, 0.5); border-color: rgba(239, 68, 68, 0.8); } }
        
        .pulse-marker { width: 24px; height: 24px; background: rgba(239, 68, 68, 0.8); border-radius: 50%; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); animation: mapPulse 1.5s infinite; }
        .normal-marker { width: 16px; height: 16px; background: rgba(34, 197, 94, 0.8); border-radius: 50%; border: 2px solid white; }
        @keyframes mapPulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
    </style>
</head>
<body>
    <div id="hud" class="bento-grid">
        <!-- Module A: Telemetry -->
        <div class="module">
            <h2 class="text-lg font-bold mb-2 text-slate-400">CORE TELEMETRY</h2>
            <div id="statusBadge" class="text-3xl font-black text-center p-4 rounded bg-green-900/30 text-green-400 border border-green-500/50 mb-4 transition-colors duration-500">
                SYSTEM SAFE
            </div>
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

        <!-- Module E: Geotechnical Prediction -->
        <div class="module border-cyan-900/50">
            <h2 class="text-lg font-bold mb-2 text-slate-400">GEOTECHNICAL PREDICTION</h2>
            <div id="riskBadge" class="text-2xl font-black text-center p-3 rounded bg-slate-800 text-slate-500 mb-3 transition-colors duration-500">
                WAITING DATA...
            </div>
            <div class="grid grid-cols-2 gap-3 flex-grow">
                <div class="bg-slate-900/80 p-3 rounded text-center border border-slate-800 flex flex-col justify-center">
                    <div class="text-xs text-slate-500 font-semibold mb-1">INVERSE VELOCITY (1/v)</div>
                    <div class="text-xl font-mono text-cyan-400" id="invVelDisp">--</div>
                </div>
                <div class="bg-slate-900/80 p-3 rounded text-center border border-slate-800 flex flex-col justify-center">
                    <div class="text-xs text-slate-500 font-semibold mb-1">ANGULAR VEL (&deg;/min)</div>
                    <div class="text-xl font-mono text-amber-400" id="angVelDisp">--</div>
                </div>
                <div class="col-span-2 bg-slate-900/80 p-3 rounded text-center border border-slate-800 flex flex-col justify-center">
                    <div class="text-xs text-slate-500 font-semibold mb-1">ESTIMATED TIME TO FAILURE</div>
                    <div class="text-2xl font-mono text-purple-400" id="ttfDisp">--</div>
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

        <!-- Module C: Chart -->
        <div class="module col-span-2-module">
            <h2 class="text-lg font-bold mb-2 text-slate-400">REAL-TIME TREND</h2>
            <div class="flex-grow relative w-full h-full bg-slate-900/50 rounded p-2">
                <canvas id="telemetryChart"></canvas>
            </div>
        </div>

        <!-- Module D: Map -->
        <div class="module">
            <h2 class="text-lg font-bold mb-2 text-slate-400 flex justify-between items-center">
                <span>TACTICAL MAP</span>
                <a href="{{ route('incidents') }}" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1 rounded border border-slate-700 transition-colors">Incident Logs &rarr;</a>
            </h2>
            <div id="map" class="flex-grow rounded z-0 border border-slate-700"></div>
        </div>
    </div>

    <script>
        // Map Initialization
        const map = L.map('map', { zoomControl: false }).setView([-8.61, 115.2], 15);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        }).addTo(map);
        
        let marker = L.marker([-8.61, 115.2], {
            icon: L.divIcon({ className: 'normal-marker', iconSize: [16, 16], iconAnchor: [8, 8] })
        }).addTo(map);

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
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { grid: { color: '#334155' }, border: { dash: [4,4] }, ticks: { color: '#94a3b8', font: { family: 'monospace' } } }
                }
            }
        });

        // Polling Simulation Connection
        function fetchTelemetry() {
            fetch('/api/v1/telemetry/latest')
                .then(res => res.json())
                .then(data => {
                    if(data.error) return;
                    
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
                    // Update HUD State
                    const hud = document.getElementById('hud');
                    const badge = document.getElementById('statusBadge');
                    
                    if (data.status === 'CRITICAL HAZARD' || data.status === 'CRITICAL' || data.status === 'BAHAYA') {
                        hud.classList.add('pulse-border');
                        badge.className = 'text-3xl font-black text-center p-4 rounded bg-red-900/80 text-white mb-4 border-2 border-red-500 pulse-border transition-colors duration-500';
                        badge.innerText = 'CRITICAL HAZARD!';
                        marker.setIcon(L.divIcon({ className: 'pulse-marker', iconSize: [24,24], iconAnchor: [12,12] }));
                        document.getElementById('liveIndicator').classList.remove('hidden');

                    } else if (data.status === 'OPERATIONAL NOISE' || data.status === 'WARNING' || data.status === 'WASPADA') {
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

        function fetchPrediction() {
            fetch('/api/v1/predictions/latest')
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'no data' || data.error) return;
                    
                    document.getElementById('invVelDisp').innerText = data.inv_velocity !== null ? data.inv_velocity.toFixed(4) : '--';
                    document.getElementById('angVelDisp').innerText = data.angular_velocity !== null ? data.angular_velocity.toFixed(4) : '--';
                    
                    const badge = document.getElementById('riskBadge');
                    if (data.risk_level === 'CRITICAL' || data.risk_level === 'AWAS') {
                        badge.className = 'text-2xl font-black text-center p-3 rounded bg-red-900/80 text-white mb-3 border-2 border-red-500 pulse-border transition-colors duration-500';
                        badge.innerText = 'CRITICAL (AWAS)';
                    } else if (data.risk_level === 'WARNING' || data.risk_level === 'WASPADA') {
                        badge.className = 'text-2xl font-black text-center p-3 rounded bg-amber-900/30 text-amber-400 border border-amber-500/50 mb-3 transition-colors duration-500';
                        badge.innerText = 'WARNING (WASPADA)';
                    } else {
                        badge.className = 'text-2xl font-black text-center p-3 rounded bg-green-900/30 text-green-400 border border-green-500/50 mb-3 transition-colors duration-500';
                        badge.innerText = 'STABLE (NORMAL)';
                    }
                    
                    if (data.estimated_collapse_time) {
                        const ttfDate = new Date(data.estimated_collapse_time);
                        document.getElementById('ttfDisp').innerText = ttfDate.toLocaleTimeString();
                        if (data.risk_level === 'CRITICAL' || data.risk_level === 'AWAS') {
                           document.getElementById('ttfDisp').classList.add('animate-pulse', 'text-red-500');
                        } else {
                           document.getElementById('ttfDisp').classList.remove('animate-pulse', 'text-red-500');
                        }
                    } else {
                        document.getElementById('ttfDisp').innerText = '--';
                    }
                })
                .catch(err => console.error("Prediction fetch error", err));
        }
        
        setInterval(fetchPrediction, 2000);
    </script>
</body>
</html>
