const wsUrl = `ws://${window.location.host}/ws/telemetry`;
const maxDataPoints = 40; // Sliding window of 40 points

// UI Elements
const els = {
    hudContainer: document.getElementById('hud-container'),
    connStatus: document.getElementById('conn-status'),
    statusBadge: document.getElementById('status-badge'),
    emergencyPill: document.getElementById('emergency-pill'),
    accX: document.getElementById('acc-x'),
    accY: document.getElementById('acc-y'),
    accZ: document.getElementById('acc-z'),
    pitch: document.getElementById('pitch'),
    roll: document.getElementById('roll'),
    freq: document.getElementById('freq'),
    confidence: document.getElementById('confidence'),
    camFeed: document.getElementById('cam-feed'),
    camTime: document.getElementById('cam-time'),
    camStatus: document.getElementById('cam-status')
};

// --- CHART.JS SETUP ---
const ctx = document.getElementById('chart').getContext('2d');
Chart.defaults.color = '#475569';
Chart.defaults.font.family = 'monospace';

const telemetryChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: Array(maxDataPoints).fill(''),
        datasets: [
            { borderColor: '#06b6d4', backgroundColor: 'transparent', data: Array(maxDataPoints).fill(0), borderWidth: 2, tension: 0.1, pointRadius: 0 }, // X: Cyan
            { borderColor: '#84cc16', backgroundColor: 'transparent', data: Array(maxDataPoints).fill(0), borderWidth: 2, tension: 0.1, pointRadius: 0 }, // Y: Lime
            { borderColor: '#ef4444', backgroundColor: 'transparent', data: Array(maxDataPoints).fill(0), borderWidth: 2, tension: 0.1, pointRadius: 0 }  // Z: Red
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 0 }, 
        plugins: {
            legend: { display: false },
            tooltip: { enabled: false }
        },
        layout: { padding: 0 },
        scales: {
            x: { display: false },
            y: {
                display: true,
                grid: { color: '#1e293b', drawBorder: false },
                ticks: { color: '#64748b', font: { size: 10 } },
                suggestedMin: -15,
                suggestedMax: 15
            }
        }
    }
});

// --- LEAFLET MAP SETUP ---
// Using Bingham Canyon Mine coordinates as representative
const mineCoords = [40.5226, -112.1481];
const map = L.map('map', {
    zoomControl: false,
    attributionControl: false
}).setView(mineCoords, 14);

// Esri World Imagery
L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 18
}).addTo(map);

// Add dark overlay to satellite map to fit the HUD theme
const darkOverlay = L.rectangle([[-90, -180], [90, 180]], {
    color: 'none',
    fillColor: '#000',
    fillOpacity: 0.4
}).addTo(map);

// Custom Marker
const normalIcon = L.divIcon({ className: 'normal-marker', iconSize: [16, 16] });
const criticalIcon = L.divIcon({ className: 'pulse-marker', iconSize: [20, 20] });
const marker = L.marker(mineCoords, { icon: normalIcon }).addTo(map);

// --- WEBSOCKET & STATE MGMT ---
let ws;

function connectWebSocket() {
    ws = new WebSocket(wsUrl);

    ws.onopen = () => {
        els.connStatus.textContent = 'LINK_OK';
        els.connStatus.className = 'font-mono text-xs text-emerald-500';
    };

    ws.onclose = () => {
        els.connStatus.textContent = 'NO_LINK';
        els.connStatus.className = 'font-mono text-xs text-red-500 animate-pulse';
        setHUDState('DISCONNECTED');
        setTimeout(connectWebSocket, 3000);
    };

    ws.onmessage = (event) => {
        const data = JSON.parse(event.data);
        updateHUD(data);
    };
}

// Update UI
function updateHUD(data) {
    // 1. Text Metrics
    els.accX.textContent = data.acc_x.toFixed(2);
    els.accY.textContent = data.acc_y.toFixed(2);
    els.accZ.textContent = data.acc_z.toFixed(2);
    els.freq.textContent = data.vibration_frequency.toFixed(1);
    els.confidence.textContent = (data.confidence * 100).toFixed(0);

    // Pitch/Roll with conditional red styling if > 10 degrees
    els.pitch.textContent = data.pitch.toFixed(1);
    els.roll.textContent = data.roll.toFixed(1);
    els.pitch.className = Math.abs(data.pitch) > 10 ? 'text-red-500 font-bold' : 'text-slate-300';
    els.roll.className = Math.abs(data.roll) > 10 ? 'text-red-500 font-bold' : 'text-slate-300';

    // 2. Chart Update
    telemetryChart.data.datasets[0].data.push(data.acc_x);
    telemetryChart.data.datasets[0].data.shift();
    telemetryChart.data.datasets[1].data.push(data.acc_y);
    telemetryChart.data.datasets[1].data.shift();
    telemetryChart.data.datasets[2].data.push(data.acc_z);
    telemetryChart.data.datasets[2].data.shift();
    telemetryChart.update();

    // 3. State Engine
    setHUDState(data.ai_status);
}

let lastState = '';
function setHUDState(status) {
    if (status === lastState) return;
    lastState = status;

    let badgeClass = 'w-full text-center py-4 rounded font-bold tracking-widest text-2xl border transition-colors duration-300 ';
    
    // Reset global styles
    els.hudContainer.classList.remove('critical-border');
    els.emergencyPill.classList.add('hidden');
    marker.setIcon(normalIcon);
    els.camStatus.className = 'bg-slate-800/80 text-slate-400 px-2 py-1 border border-slate-700 text-xs font-mono backdrop-blur-sm h-fit';
    els.camStatus.textContent = 'STATUS: IDLE';

    if (status === 'SAFE' || status === 'NORMAL') {
        els.statusBadge.className = badgeClass + 'bg-emerald-500/20 text-emerald-400 border-emerald-500/50';
        els.statusBadge.textContent = 'SAFE';
        els.camStatus.className = 'bg-emerald-500/20 text-emerald-400 px-2 py-1 border border-emerald-500/50 text-xs font-mono backdrop-blur-sm h-fit';
        els.camStatus.textContent = 'STATUS: VERIFIED';
    } 
    else if (status === 'OPERATIONAL NOISE' || status === 'VIBRATION NOISE') {
        els.statusBadge.className = badgeClass + 'bg-amber-500/20 text-amber-400 border-amber-500/50';
        els.statusBadge.textContent = 'VIBRATION NOISE';
    } 
    else if (status === 'CRITICAL HAZARD') {
        els.statusBadge.className = badgeClass + 'bg-red-600 text-white border-red-500 animate-pulse shadow-[0_0_20px_rgba(239,68,68,0.7)]';
        els.statusBadge.textContent = 'CRITICAL HAZARD';
        
        // Trigger Critical UX
        els.hudContainer.classList.add('critical-border');
        els.emergencyPill.classList.remove('hidden');
        marker.setIcon(criticalIcon);
        
        els.camStatus.className = 'bg-red-500/20 text-red-500 px-2 py-1 border border-red-500 text-xs font-mono backdrop-blur-sm h-fit animate-pulse';
        els.camStatus.textContent = 'STATUS: UNVERIFIED';

        // Fetch the snapshot
        setTimeout(fetchLatestSnapshot, 1000); // 1 sec delay to allow backend to write the file
    } 
    else {
        els.statusBadge.className = badgeClass + 'bg-slate-800 text-slate-500 border-slate-700';
        els.statusBadge.textContent = status;
    }
}

async function fetchLatestSnapshot() {
    try {
        // Cache bust URL to force image reload
        const timestamp = new Date().getTime();
        const url = `/api/incidents/latest/image?t=${timestamp}`;
        
        const res = await fetch(url);
        if (res.ok) {
            els.camFeed.src = url;
            const now = new Date();
            els.camTime.textContent = `${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}:${now.getSeconds().toString().padStart(2,'0')}`;
        }
    } catch(e) {
        console.error("Failed to load latest snapshot", e);
    }
}

// Check for initial snapshot on load
fetchLatestSnapshot();

// Init WS
connectWebSocket();
