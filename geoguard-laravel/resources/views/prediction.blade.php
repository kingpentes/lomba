@extends('layouts.app')

@section('content')
<div class="p-6">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-black text-white tracking-wider">GEO-ANALYSIS & PREDICTION</h1>
            <p class="text-slate-400 mt-1">Fukuzono Inverse Velocity Extrapolation Model</p>
        </div>
        <div class="flex gap-4">
            <div class="bg-slate-900 border border-slate-700 px-4 py-2 rounded-lg text-center">
                <div class="text-xs text-slate-500 font-bold">ALGORITHM STATUS</div>
                <div class="text-sm font-bold text-green-400">ONLINE (AIRFLOW DAG)</div>
            </div>
            <div class="bg-slate-900 border border-slate-700 px-4 py-2 rounded-lg text-center">
                <div class="text-xs text-slate-500 font-bold">LAST COMPUTATION</div>
                <div class="text-sm font-bold text-cyan-400" id="lastCompTime">SYNCING...</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6 mb-6">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl flex flex-col justify-center items-center shadow-lg">
            <div class="text-sm text-slate-500 font-bold mb-2 tracking-widest">SLOPE RISK LEVEL</div>
            <div id="predStatus" class="text-4xl font-black text-green-500">SAFE</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl flex flex-col justify-center items-center shadow-lg">
            <div class="text-sm text-slate-500 font-bold mb-2 tracking-widest">INVERSE VELOCITY (1/v)</div>
            <div id="invVelValue" class="text-4xl font-mono font-bold text-cyan-400">999.0</div>
            <div class="text-xs text-slate-500 mt-1">min/deg</div>
        </div>
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl flex flex-col justify-center items-center shadow-lg">
            <div class="text-sm text-slate-500 font-bold mb-2 tracking-widest">ESTIMATED TIME TO FAILURE (TTF)</div>
            <div id="ttfValue" class="text-3xl font-mono font-bold text-purple-400">NO IMMINENT RISK</div>
            <div class="text-xs text-slate-500 mt-1">Predicted Collapse Time</div>
        </div>
    </div>

    <!-- InSAR Satellite Section -->
    <div class="grid grid-cols-3 gap-6 mb-6">
        <!-- InSAR Displacement Card -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-lg">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <h3 class="text-lg font-bold text-slate-300">SATELLITE InSAR</h3>
            </div>
            <div class="text-center py-4">
                <div class="text-xs text-slate-500 font-bold tracking-widest mb-2">DISPLACEMENT RATE</div>
                <div id="insarRate" class="text-5xl font-black text-emerald-400 font-mono">0.0</div>
                <div class="text-sm text-slate-500 mt-1">mm/year</div>
            </div>
            <div class="mt-4 space-y-2 text-xs">
                <div class="flex justify-between text-slate-400">
                    <span>Source</span>
                    <span class="text-cyan-400 font-mono" id="insarSource">Sentinel-1 GRD (C-Band SAR)</span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>Pipeline</span>
                    <span class="text-emerald-400 font-mono">Google Earth Engine</span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>Schedule</span>
                    <span class="text-amber-400 font-mono">@weekly (Airflow DAG)</span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>Last Sync</span>
                    <span class="text-slate-300 font-mono" id="insarLastSync">--</span>
                </div>
            </div>
            <!-- Risk Gauge -->
            <div class="mt-4 pt-4 border-t border-slate-800">
                <div class="text-xs text-slate-500 font-bold mb-2">SUBSIDENCE SEVERITY</div>
                <div class="w-full bg-slate-800 rounded-full h-3 overflow-hidden">
                    <div id="insarGauge" class="h-3 rounded-full transition-all duration-1000" style="width: 5%; background: linear-gradient(90deg, #22c55e, #f59e0b, #ef4444);"></div>
                </div>
                <div class="flex justify-between text-xs text-slate-600 mt-1">
                    <span>Stable</span>
                    <span>Warning</span>
                    <span>Critical</span>
                </div>
            </div>
        </div>

        <!-- InSAR Mini Map -->
        <div class="col-span-2 bg-slate-900 border border-slate-800 rounded-xl shadow-lg overflow-hidden">
            <div class="flex items-center justify-between px-6 pt-4 pb-2">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    <h3 class="text-lg font-bold text-slate-300">PS-InSAR DISPLACEMENT MAP</h3>
                </div>
                <div class="flex gap-3 text-xs">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span> Stable (> -20)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-amber-500 inline-block"></span> Warning (-20 to -50)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span> Critical (< -50)</span>
                </div>
            </div>
            <div id="insarMap" class="w-full" style="height: 280px;"></div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-lg">
        <h2 class="text-lg font-bold text-slate-300 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path></svg>
            Fukuzono Extrapolation Curve
        </h2>
        <div class="relative w-full h-[400px]">
            <canvas id="fukuzonoChart"></canvas>
        </div>
        <div class="mt-4 text-sm text-slate-500 border-t border-slate-800 pt-4">
            <p><strong>Methodology:</strong> This chart plots the Inverse Velocity (1/v) of slope deformation against time. According to the Fukuzono method, as a slope approaches failure, its deformation velocity increases, causing 1/v to approach zero. The dashed red line represents the linear regression (Machine Learning) forecast calculated by the Airflow DAG. The intersection with the X-axis (1/v = 0) is the Estimated Time to Failure (TTF).</p>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // ═══════════════════════════════════════════════════════════════
    // InSAR Mini Map Initialization
    // ═══════════════════════════════════════════════════════════════
    const insarMap = L.map('insarMap', { zoomControl: true }).setView([-1.215, 116.851], 13);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri'
    }).addTo(insarMap);

    // Load InSAR GeoJSON dan tampilkan titik-titik PS-InSAR
    let insarPointCount = 0;
    fetch('/data/insar_data.geojson')
        .then(res => res.json())
        .then(data => {
            L.geoJSON(data, {
                pointToLayer: function (feature, latlng) {
                    let vel = feature.properties.velocity_mm_yr;
                    let color = '#22c55e'; // Green (Stable)
                    let radius = 6;
                    
                    if (vel <= -50) {
                        color = '#ef4444'; // Red (Critical)
                        radius = 12;
                    } else if (vel <= -20) {
                        color = '#f59e0b'; // Amber (Warning)
                        radius = 9;
                    }

                    return L.circleMarker(latlng, {
                        radius: radius,
                        fillColor: color,
                        color: color,
                        weight: 1,
                        opacity: 0.8,
                        fillOpacity: 0.6
                    }).bindPopup(
                        `<div style="font-family: monospace; font-size: 12px;">
                            <b>PS-InSAR Point</b><br>
                            <b>Velocity:</b> <span style="color: ${color}">${vel} mm/yr</span><br>
                            <b>Coherence:</b> ${feature.properties.coherence}<br>
                            <b>Source:</b> ${feature.properties.source || 'Sentinel-1'}
                        </div>`
                    );
                }
            }).addTo(insarMap);

            insarPointCount = data.features ? data.features.length : 0;

            // Hitung rata-rata velocity dari GeoJSON
            if (data.features && data.features.length > 0) {
                const avgVel = data.features.reduce((sum, f) => sum + f.properties.velocity_mm_yr, 0) / data.features.length;
                updateInsarDisplay(avgVel);
            }

            // Update metadata
            if (data.metadata && data.metadata.generated_at) {
                document.getElementById('insarLastSync').innerText = new Date(data.metadata.generated_at).toLocaleDateString();
            }
        })
        .catch(err => console.log('InSAR GeoJSON not loaded:', err));

    function updateInsarDisplay(rate) {
        const rateEl = document.getElementById('insarRate');
        const gaugeEl = document.getElementById('insarGauge');
        const absRate = Math.abs(rate);
        
        rateEl.innerText = rate.toFixed(1);
        
        // Warna berdasarkan severity
        if (absRate > 50) {
            rateEl.className = 'text-5xl font-black text-red-400 font-mono animate-pulse';
        } else if (absRate > 20) {
            rateEl.className = 'text-5xl font-black text-amber-400 font-mono';
        } else {
            rateEl.className = 'text-5xl font-black text-emerald-400 font-mono';
        }
        
        // Update gauge bar (0-120 mm/yr mapped to 0-100%)
        const gaugeWidth = Math.min((absRate / 120) * 100, 100);
        gaugeEl.style.width = gaugeWidth + '%';
    }

    // ═══════════════════════════════════════════════════════════════
    // Fukuzono Chart Initialization
    // ═══════════════════════════════════════════════════════════════
    const ctx = document.getElementById('fukuzonoChart').getContext('2d');
    
    const fukuzonoChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Historical 1/v',
                    data: [],
                    borderColor: '#22d3ee',
                    backgroundColor: 'rgba(34, 211, 238, 0.1)',
                    borderWidth: 2,
                    pointRadius: 3,
                    fill: true,
                    tension: 0.1
                },
                {
                    label: 'Extrapolation (Forecast to TTF)',
                    data: [],
                    borderColor: '#ef4444',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointRadius: 5,
                    pointBackgroundColor: '#ef4444',
                    fill: false,
                    tension: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    type: 'linear',
                    grid: { color: '#1e293b' },
                    ticks: { 
                        color: '#94a3b8',
                        callback: function(value) {
                            return new Date(value).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#1e293b' },
                    ticks: { color: '#94a3b8' },
                    title: {
                        display: true,
                        text: 'Inverse Velocity (1/v)',
                        color: '#94a3b8'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: { color: '#cbd5e1' }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // Fetch Prediction Data (Fukuzono + InSAR Rate dari API)
    // ═══════════════════════════════════════════════════════════════
    function fetchPredictionData() {
        fetch('/api/v1/predictions/latest')
            .then(res => res.json())
            .then(data => {
                if (!data.error && data.status !== 'no data' && data.inv_velocity !== undefined) {
                    const pred = data;
                    
                    document.getElementById('invVelValue').innerText = pred.inv_velocity.toFixed(2);
                    
                    let computedTime = 'JUST NOW';
                    if (pred.calculated_at) {
                        computedTime = new Date(pred.calculated_at).toLocaleTimeString();
                    }
                    document.getElementById('lastCompTime').innerText = computedTime;
                    
                    const statusEl = document.getElementById('predStatus');
                    statusEl.innerText = pred.risk_level;
                    if(pred.risk_level === 'CRITICAL') {
                        statusEl.className = 'text-4xl font-black text-red-500 animate-pulse';
                    } else if (pred.risk_level === 'WARNING') {
                        statusEl.className = 'text-4xl font-black text-amber-500';
                    } else {
                        statusEl.className = 'text-4xl font-black text-green-500';
                    }

                    // Update InSAR rate dari database (jika ada)
                    if (pred.insar_rate !== undefined && pred.insar_rate !== 0) {
                        updateInsarDisplay(pred.insar_rate);
                        document.getElementById('insarLastSync').innerText = computedTime;
                    }

                    // Chart data
                    const now = new Date();
                    const histData = [];
                    const forecastData = [];

                    let currentInv = pred.inv_velocity;
                    for(let i=5; i>0; i--) {
                        let t = new Date(now.getTime() - i*60000);
                        histData.push({x: t.getTime(), y: currentInv + (i*0.5)});
                    }
                    
                    histData.push({x: now.getTime(), y: currentInv});

                    if (pred.estimated_collapse_time) {
                        const ttfDate = new Date(pred.estimated_collapse_time);
                        document.getElementById('ttfValue').innerText = ttfDate.toLocaleString();
                        
                        forecastData.push({x: now.getTime(), y: currentInv});
                        forecastData.push({x: ttfDate.getTime(), y: 0.0});
                    } else {
                        document.getElementById('ttfValue').innerText = 'NO IMMINENT RISK';
                    }

                    fukuzonoChart.data.datasets[0].data = histData;
                    fukuzonoChart.data.datasets[1].data = forecastData;
                    fukuzonoChart.update();
                }
            })
            .catch(err => console.error("Error fetching prediction:", err));
    }

    // Polling setiap 5 detik
    fetchPredictionData();
    setInterval(fetchPredictionData, 5000);
</script>
@endsection

