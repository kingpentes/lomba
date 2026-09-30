@extends('layouts.app')

@section('title', 'Early Warning System')

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-black text-white tracking-wider">EARLY WARNING SYSTEM</h1>
            <p class="text-slate-400 mt-1">Predictive Slope Failure Alert & Recommended Actions</p>
        </div>
        <div class="flex gap-4">
            <div class="bg-slate-900 border border-slate-700 px-4 py-2 rounded-lg text-center">
                <div class="text-xs text-slate-500 font-bold">SYSTEM STATUS</div>
                <div class="text-sm font-bold text-green-400" id="sysStatus">MONITORING</div>
            </div>
            <div class="bg-slate-900 border border-slate-700 px-4 py-2 rounded-lg text-center">
                <div class="text-xs text-slate-500 font-bold">LAST UPDATED</div>
                <div class="text-sm font-bold text-cyan-400" id="lastUpdate">SYNCING...</div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- MAIN ALERT BANNER -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div id="mainAlertBanner" class="mb-6 rounded-xl border-2 p-6 transition-all duration-500 bg-green-950/30 border-green-600/40">
        <div class="flex items-start gap-4">
            <div id="alertIcon" class="shrink-0 mt-1">
                <svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                    <span id="alertBadge" class="px-3 py-1 rounded-full text-xs font-black tracking-widest bg-green-500/20 text-green-400 border border-green-500/30">SAFE</span>
                    <span id="alertTime" class="text-xs text-slate-500 font-mono"></span>
                </div>
                <h2 id="alertTitle" class="text-2xl font-black text-white mb-2">All Slopes Within Normal Parameters</h2>
                <p id="alertMessage" class="text-slate-300 text-sm leading-relaxed">
                    No anomalous deformation detected across all monitoring nodes. Sensor readings are within acceptable thresholds. Continue routine monitoring operations.
                </p>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- NODE RISK CARDS -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Dynamic Node Cards will be injected here -->
        <div id="nodeCardsContainer">
            <!-- Placeholder while loading -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 animate-pulse">
                <div class="h-4 bg-slate-800 rounded w-1/3 mb-4"></div>
                <div class="h-8 bg-slate-800 rounded w-1/2 mb-2"></div>
                <div class="h-4 bg-slate-800 rounded w-full"></div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- PREDICTION TIMELINE & RECOMMENDED ACTIONS -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-3 gap-6 mb-6">

        <!-- Prediction Summary Panel -->
        <div class="col-span-2 bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-lg">
            <h3 class="text-lg font-bold text-slate-300 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                PREDICTION TIMELINE
            </h3>
            <div id="predictionTimeline" class="space-y-3">
                <!-- Timeline items injected by JS -->
            </div>
        </div>

        <!-- Recommended Actions Panel -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-lg">
            <h3 class="text-lg font-bold text-slate-300 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                RECOMMENDED ACTIONS
            </h3>
            <div id="actionsList" class="space-y-3">
                <!-- Actions injected by JS -->
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- RISK MATRIX & MULTI-SENSOR FUSION TABLE -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-lg">
        <h3 class="text-lg font-bold text-slate-300 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h16M12 4v16"></path></svg>
            MULTI-SENSOR RISK FUSION TABLE
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700 text-slate-400">
                        <th class="text-left py-3 px-4 font-bold">NODE</th>
                        <th class="text-center py-3 px-4 font-bold">TILT (deg)</th>
                        <th class="text-center py-3 px-4 font-bold">VIBRATION (Hz)</th>
                        <th class="text-center py-3 px-4 font-bold">InSAR (mm/yr)</th>
                        <th class="text-center py-3 px-4 font-bold">INV. VELOCITY</th>
                        <th class="text-center py-3 px-4 font-bold">FUSION RISK</th>
                        <th class="text-center py-3 px-4 font-bold">PREDICTION</th>
                    </tr>
                </thead>
                <tbody id="riskTableBody">
                    <tr class="border-b border-slate-800/50">
                        <td colspan="7" class="text-center py-6 text-slate-600">Loading sensor data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4 text-xs text-slate-600 border-t border-slate-800 pt-3">
            <strong>Fusion Algorithm:</strong> Risk level is computed by weighting IoT sensor data (tilt, vibration, PPV) at 40%, satellite InSAR displacement at 30%, and Fukuzono inverse velocity extrapolation at 30%. Final risk = MAX(IoT_risk, Satellite_risk, Fukuzono_risk) with weighted confidence scoring.
        </div>
    </div>
</div>

<script>
    // ═══════════════════════════════════════════════════════════════════
    // Configuration
    // ═══════════════════════════════════════════════════════════════════
    const RISK_CONFIG = {
        SAFE:     { color: 'green',  label: 'SAFE',     bgClass: 'bg-green-950/30',  borderClass: 'border-green-600/40',  badgeClass: 'bg-green-500/20 text-green-400 border-green-500/30', textClass: 'text-green-400' },
        WARNING:  { color: 'amber',  label: 'WARNING',  bgClass: 'bg-amber-950/30',  borderClass: 'border-amber-600/40',  badgeClass: 'bg-amber-500/20 text-amber-400 border-amber-500/30', textClass: 'text-amber-400' },
        CRITICAL: { color: 'red',    label: 'CRITICAL', bgClass: 'bg-red-950/30',    borderClass: 'border-red-600/40',    badgeClass: 'bg-red-500/20 text-red-400 border-red-500/30',       textClass: 'text-red-400'   },
        STABLE:   { color: 'green',  label: 'STABLE',   bgClass: 'bg-green-950/30',  borderClass: 'border-green-600/40',  badgeClass: 'bg-green-500/20 text-green-400 border-green-500/30', textClass: 'text-green-400' },
    };

    const ALERT_ICONS = {
        SAFE: '<svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        WARNING: '<svg class="w-10 h-10 text-amber-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
        CRITICAL: '<svg class="w-10 h-10 text-red-500 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        STABLE: '<svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
    };

    const ALERT_MESSAGES = {
        SAFE: {
            title: 'All Slopes Within Normal Parameters',
            message: 'No anomalous deformation detected across all monitoring nodes. Sensor readings are within acceptable thresholds. Continue routine monitoring operations.',
        },
        STABLE: {
            title: 'All Slopes Within Normal Parameters',
            message: 'No anomalous deformation detected across all monitoring nodes. Sensor readings are within acceptable thresholds. Continue routine monitoring operations.',
        },
        WARNING: {
            title: 'Elevated Deformation Detected — Monitor Closely',
            message: 'One or more nodes are exhibiting abnormal tilt or displacement rates. The Fukuzono model indicates an increasing trend in deformation velocity. Recommend increasing monitoring frequency and preparing contingency measures.',
        },
        CRITICAL: {
            title: 'IMMINENT SLOPE FAILURE RISK — ACTION REQUIRED',
            message: 'Critical deformation rates detected. The Fukuzono inverse velocity model predicts potential slope failure. IMMEDIATELY evacuate personnel within the affected zone and suspend all operations in the vicinity.',
        },
    };

    const RECOMMENDED_ACTIONS = {
        SAFE: [
            { icon: '🟢', text: 'Continue routine monitoring every 1 minute', priority: 'LOW' },
            { icon: '📋', text: 'Perform weekly visual inspection of slope face', priority: 'LOW' },
            { icon: '🛰️', text: 'InSAR satellite sync: nominal (@weekly)', priority: 'LOW' },
        ],
        STABLE: [
            { icon: '🟢', text: 'Continue routine monitoring every 1 minute', priority: 'LOW' },
            { icon: '📋', text: 'Perform weekly visual inspection of slope face', priority: 'LOW' },
            { icon: '🛰️', text: 'InSAR satellite sync: nominal (@weekly)', priority: 'LOW' },
        ],
        WARNING: [
            { icon: '🟡', text: 'Increase sensor polling to every 10 seconds', priority: 'MEDIUM' },
            { icon: '🚧', text: 'Restrict heavy equipment within 100m of affected node', priority: 'MEDIUM' },
            { icon: '👷', text: 'Deploy field geotechnical engineer for visual inspection', priority: 'HIGH' },
            { icon: '📡', text: 'Trigger manual InSAR DAG re-run for fresh data', priority: 'MEDIUM' },
            { icon: '📢', text: 'Notify site supervisor and safety officer', priority: 'HIGH' },
        ],
        CRITICAL: [
            { icon: '🔴', text: 'EVACUATE all personnel within 200m radius IMMEDIATELY', priority: 'CRITICAL' },
            { icon: '🚫', text: 'HALT all blasting and heavy machinery operations', priority: 'CRITICAL' },
            { icon: '🚨', text: 'Activate emergency siren via Dispatcher Panel', priority: 'CRITICAL' },
            { icon: '📞', text: 'Notify mine manager and local BNPB office', priority: 'CRITICAL' },
            { icon: '📸', text: 'Deploy drone/camera for crack documentation', priority: 'HIGH' },
            { icon: '🛰️', text: 'Request emergency InSAR analysis (manual DAG trigger)', priority: 'HIGH' },
        ],
    };

    // ═══════════════════════════════════════════════════════════════════
    // Update Main Alert Banner
    // ═══════════════════════════════════════════════════════════════════
    function updateAlertBanner(riskLevel, nodeCode, prediction) {
        const config = RISK_CONFIG[riskLevel] || RISK_CONFIG.SAFE;
        const msgs = ALERT_MESSAGES[riskLevel] || ALERT_MESSAGES.SAFE;
        const banner = document.getElementById('mainAlertBanner');

        banner.className = `mb-6 rounded-xl border-2 p-6 transition-all duration-500 ${config.bgClass} ${config.borderClass}`;
        if (riskLevel === 'CRITICAL') banner.classList.add('animate-pulse');
        else banner.classList.remove('animate-pulse');

        document.getElementById('alertIcon').innerHTML = ALERT_ICONS[riskLevel] || ALERT_ICONS.SAFE;
        document.getElementById('alertBadge').className = `px-3 py-1 rounded-full text-xs font-black tracking-widest ${config.badgeClass} border`;
        document.getElementById('alertBadge').innerText = config.label;
        document.getElementById('alertTime').innerText = new Date().toLocaleString('id-ID');

        let title = msgs.title;
        let message = msgs.message;

        if (riskLevel === 'CRITICAL' && nodeCode) {
            title = `IMMINENT SLOPE FAILURE at ${nodeCode} — EVACUATE NOW`;
            if (prediction && prediction.estimated_collapse_time) {
                const ttf = new Date(prediction.estimated_collapse_time);
                message = `Critical deformation velocity detected at Node ${nodeCode}. Fukuzono model predicts potential slope collapse at approximately ${ttf.toLocaleString('id-ID')}. IMMEDIATELY evacuate all personnel and equipment within 200m radius of this node. Suspend blasting and heavy machinery operations in the affected zone.`;
            }
        } else if (riskLevel === 'WARNING' && nodeCode) {
            title = `Elevated Risk at ${nodeCode} — Increase Monitoring`;
            message = `Node ${nodeCode} is showing abnormal deformation patterns. Tilt angle or displacement rate exceeding warning thresholds. Recommend deploying a field engineer for visual inspection and increasing monitoring frequency.`;
        }

        document.getElementById('alertTitle').innerText = title;
        document.getElementById('alertMessage').innerText = message;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Update Recommended Actions
    // ═══════════════════════════════════════════════════════════════════
    function updateActions(riskLevel) {
        const actions = RECOMMENDED_ACTIONS[riskLevel] || RECOMMENDED_ACTIONS.SAFE;
        const container = document.getElementById('actionsList');

        const priorityColors = {
            LOW: 'text-slate-500 bg-slate-800',
            MEDIUM: 'text-amber-400 bg-amber-900/30',
            HIGH: 'text-orange-400 bg-orange-900/30',
            CRITICAL: 'text-red-400 bg-red-900/30 animate-pulse',
        };

        container.innerHTML = actions.map(a => `
            <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-800/50 border border-slate-700/50">
                <span class="text-lg shrink-0">${a.icon}</span>
                <div class="flex-1">
                    <p class="text-sm text-slate-300">${a.text}</p>
                    <span class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-bold ${priorityColors[a.priority]}">${a.priority}</span>
                </div>
            </div>
        `).join('');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Update Prediction Timeline
    // ═══════════════════════════════════════════════════════════════════
    function updateTimeline(prediction, telemetry) {
        const container = document.getElementById('predictionTimeline');
        const now = new Date();
        const items = [];

        // Item 1: Latest Sensor Reading
        if (telemetry) {
            const tiltMax = Math.max(Math.abs(telemetry.pitch || 0), Math.abs(telemetry.roll || 0));
            const tiltConfig = tiltMax > 15 ? RISK_CONFIG.CRITICAL : (tiltMax > 8 ? RISK_CONFIG.WARNING : RISK_CONFIG.SAFE);
            items.push({
                time: now.toLocaleTimeString('id-ID'),
                title: `IoT Sensor: ${telemetry.node_code || 'INC_HW_01'}`,
                detail: `Tilt: ${tiltMax.toFixed(1)}° | Vibration: ${(telemetry.vibration_freq || 0).toFixed(1)} Hz | Status: ${telemetry.status || 'STABLE'}`,
                config: tiltConfig,
                type: 'SENSOR',
            });
        }

        // Item 2: Fukuzono Prediction
        if (prediction) {
            const predConfig = RISK_CONFIG[prediction.risk_level] || RISK_CONFIG.SAFE;
            let predDetail = `Inv. Velocity: ${prediction.inv_velocity?.toFixed(2) || 'N/A'} | Risk: ${prediction.risk_level}`;
            if (prediction.estimated_collapse_time) {
                const ttf = new Date(prediction.estimated_collapse_time);
                predDetail += ` | Predicted Failure: ${ttf.toLocaleString('id-ID')}`;
            }
            items.push({
                time: prediction.calculated_at ? new Date(prediction.calculated_at).toLocaleTimeString('id-ID') : '--',
                title: 'Fukuzono Model Prediction',
                detail: predDetail,
                config: predConfig,
                type: 'AI',
            });
        }

        // Item 3: InSAR Data
        if (prediction && prediction.insar_rate !== undefined) {
            const absRate = Math.abs(prediction.insar_rate);
            const insarConfig = absRate > 50 ? RISK_CONFIG.CRITICAL : (absRate > 20 ? RISK_CONFIG.WARNING : RISK_CONFIG.SAFE);
            items.push({
                time: 'Weekly',
                title: 'Sentinel-1 InSAR Displacement',
                detail: `Rate: ${prediction.insar_rate?.toFixed(1) || 0} mm/yr | Source: Google Earth Engine`,
                config: insarConfig,
                type: 'SATELLITE',
            });
        }

        const typeIcons = {
            SENSOR: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>',
            AI: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>',
            SATELLITE: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        };

        container.innerHTML = items.map((item, idx) => `
            <div class="flex gap-4 items-start p-4 rounded-lg border ${item.config.borderClass} ${item.config.bgClass}">
                <div class="shrink-0 flex flex-col items-center gap-1">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center ${item.config.badgeClass} border">
                        ${typeIcons[item.type] || ''}
                    </div>
                    ${idx < items.length - 1 ? '<div class="w-px h-6 bg-slate-700"></div>' : ''}
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-sm font-bold text-white">${item.title}</span>
                        <span class="text-xs ${item.config.textClass} font-mono">${item.time}</span>
                    </div>
                    <p class="text-xs text-slate-400">${item.detail}</p>
                </div>
            </div>
        `).join('');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Update Risk Table
    // ═══════════════════════════════════════════════════════════════════
    function updateRiskTable(telemetry, prediction) {
        const tbody = document.getElementById('riskTableBody');
        const nodeCode = telemetry?.node_code || prediction?.node_code || 'INC_HW_01';
        const tilt = Math.max(Math.abs(telemetry?.pitch || 0), Math.abs(telemetry?.roll || 0));
        const vibration = telemetry?.vibration_freq || 0;
        const insarRate = prediction?.insar_rate || 0;
        const invVel = prediction?.inv_velocity || 999;
        const riskLevel = prediction?.risk_level || 'STABLE';
        const config = RISK_CONFIG[riskLevel] || RISK_CONFIG.SAFE;

        let predictionText = 'No imminent risk';
        if (prediction?.estimated_collapse_time) {
            const ttf = new Date(prediction.estimated_collapse_time);
            predictionText = `Collapse ~${ttf.toLocaleDateString('id-ID')}`;
        }

        // Tilt risk color
        const tiltColor = tilt > 15 ? 'text-red-400' : (tilt > 8 ? 'text-amber-400' : 'text-green-400');
        const vibColor = vibration > 50 ? 'text-red-400' : (vibration > 20 ? 'text-amber-400' : 'text-green-400');
        const insarColor = Math.abs(insarRate) > 50 ? 'text-red-400' : (Math.abs(insarRate) > 20 ? 'text-amber-400' : 'text-green-400');
        const invVelColor = invVel < 5 ? 'text-red-400' : (invVel < 50 ? 'text-amber-400' : 'text-green-400');

        tbody.innerHTML = `
            <tr class="border-b border-slate-800/50 hover:bg-slate-800/30 transition-colors">
                <td class="py-3 px-4 font-bold text-cyan-400 font-mono">${nodeCode}</td>
                <td class="py-3 px-4 text-center font-mono ${tiltColor}">${tilt.toFixed(1)}°</td>
                <td class="py-3 px-4 text-center font-mono ${vibColor}">${vibration.toFixed(1)}</td>
                <td class="py-3 px-4 text-center font-mono ${insarColor}">${insarRate.toFixed(1)}</td>
                <td class="py-3 px-4 text-center font-mono ${invVelColor}">${invVel.toFixed(2)}</td>
                <td class="py-3 px-4 text-center">
                    <span class="px-3 py-1 rounded-full text-xs font-black ${config.badgeClass} border">${riskLevel}</span>
                </td>
                <td class="py-3 px-4 text-center text-xs font-mono text-slate-300">${predictionText}</td>
            </tr>
        `;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Main Fetch & Update Loop
    // ═══════════════════════════════════════════════════════════════════
    let latestPrediction = null;
    let latestTelemetry = null;

    function fetchAllData() {
        // Fetch prediction data
        fetch('/api/v1/predictions/latest')
            .then(res => res.json())
            .then(data => {
                if (!data.error && data.status !== 'no data') {
                    latestPrediction = data;
                }
                updateUI();
            })
            .catch(() => updateUI());

        // Fetch telemetry data
        fetch('/api/v1/telemetry/latest')
            .then(res => res.json())
            .then(data => {
                if (!data.error) {
                    latestTelemetry = data;
                }
                updateUI();
            })
            .catch(() => updateUI());
    }

    function updateUI() {
        const pred = latestPrediction;
        const tele = latestTelemetry;

        // Determine highest risk
        let worstRisk = 'SAFE';
        if (pred) {
            worstRisk = pred.risk_level || 'STABLE';
        }
        // Override from telemetry if classification is worse
        if (tele && tele.status) {
            const teleRisk = tele.status.toUpperCase();
            const riskOrder = { NORMAL: 0, STABLE: 0, WARNING: 1, CRITICAL: 2 };
            if ((riskOrder[teleRisk] || 0) > (riskOrder[worstRisk] || 0)) {
                worstRisk = teleRisk;
            }
        }

        const nodeCode = tele?.node_code || pred?.node_code || 'INC_HW_01';

        // Update all UI components
        updateAlertBanner(worstRisk, nodeCode, pred);
        updateActions(worstRisk);
        updateTimeline(pred, tele);
        updateRiskTable(tele, pred);

        // System status
        document.getElementById('sysStatus').innerText = worstRisk === 'CRITICAL' ? 'ALERT' : (worstRisk === 'WARNING' ? 'WATCH' : 'MONITORING');
        document.getElementById('sysStatus').className = `text-sm font-bold ${(RISK_CONFIG[worstRisk] || RISK_CONFIG.SAFE).textClass}`;

        // Last updated
        document.getElementById('lastUpdate').innerText = new Date().toLocaleTimeString('id-ID');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Initialize
    // ═══════════════════════════════════════════════════════════════════
    fetchAllData();
    setInterval(fetchAllData, 5000);
</script>
@endsection
