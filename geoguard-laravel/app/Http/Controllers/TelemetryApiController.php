<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelemetryLog;
use App\Models\MonitoringNode;
use App\Models\Incident;
use App\Models\SlopeRiskPrediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TelemetryApiController extends Controller
{
    public function ingest(Request $request)
    {
        // Optional API Token check (if GEOGUARD_API_TOKEN configured in .env)
        $expectedToken = env('GEOGUARD_API_TOKEN');
        if (!empty($expectedToken)) {
            $bearer = $request->bearerToken() ?? $request->header('X-API-KEY');
            if ($bearer !== $expectedToken) {
                return response()->json(['error' => 'Unauthorized: Invalid API token'], 401);
            }
        }

        $validated = $request->validate([
            'node_code' => 'required|string',
            'timestamp' => 'required|date',
            'acc_x' => 'required|numeric',
            'acc_y' => 'required|numeric',
            'acc_z' => 'required|numeric',
            'pitch' => 'required|numeric',
            'roll' => 'required|numeric',
            'vibration_freq' => 'required|numeric',
            'ppv_value' => 'nullable|numeric',
            'ai_classification' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'elevation' => 'nullable|numeric',
        ]);

        $node = MonitoringNode::where('node_code', $validated['node_code'])->first();
        if (!$node) {
            // For testing purposes, create the node if it doesn't exist
            $node = MonitoringNode::create([
                'node_code' => $validated['node_code'],
                'name' => 'Auto-generated Node ' . $validated['node_code'],
                'latitude' => $validated['latitude'] ?? -1.2370,
                'longitude' => $validated['longitude'] ?? 116.8520,
                'elevation' => 85,
                'status' => 'STABLE'
            ]);
        }

        // Update GPS location and Status based on realtime ESP32 telemetry
        $updateData = [];
        if (isset($validated['latitude']) && isset($validated['longitude']) && $validated['latitude'] != 0.0) {
            $updateData['latitude'] = $validated['latitude'];
            $updateData['longitude'] = $validated['longitude'];
        }
        
        // Terima nilai elevasi (altitude) dari GPS jika dikirimkan oleh ESP32
        if (isset($validated['elevation'])) {
            $updateData['elevation'] = $validated['elevation'];
        }
        
        $espStatus = strtoupper($validated['ai_classification']);
        if ($espStatus === 'NORMAL') {
            $updateData['status'] = 'STABLE';
        } elseif (in_array($espStatus, ['WARNING', 'CRITICAL'])) {
            $updateData['status'] = $espStatus;
        }

        if (!empty($updateData)) {
            $node->update($updateData);
        }

        // Smart timestamp: gunakan timestamp ESP32 jika valid dan berada di rentang wajar (±24 jam), jika tidak pakai waktu server
        $recordedAt = now();
        if (!empty($validated['timestamp'])) {
            try {
                $parsedTime = \Carbon\Carbon::parse($validated['timestamp']);
                if (abs($parsedTime->diffInHours(now())) <= 24) {
                    $recordedAt = $parsedTime;
                }
            } catch (\Exception $e) {
                $recordedAt = now();
            }
        }

        $telemetry = TelemetryLog::create([
            'node_id' => $node->id,
            'recorded_at' => $recordedAt,
            'acc_x' => $validated['acc_x'],
            'acc_y' => $validated['acc_y'],
            'acc_z' => $validated['acc_z'],
            'pitch' => $validated['pitch'],
            'roll' => $validated['roll'],
            'vibration_freq' => $validated['vibration_freq'],
            'ppv_value' => $validated['ppv_value'] ?? 0,
            'ai_classification' => $validated['ai_classification'],
        ]);

        return response()->json(['status' => 'success']);
    }

    public function triggerIncident(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|string',
            'angle_x' => 'required|numeric',
            'angle_y' => 'required|numeric',
            'status' => 'required|in:CRITICAL,WARNING',
            'photo' => 'nullable|file|mimes:jpeg,png,jpg',
            'ai_confidence' => 'nullable|numeric|min:0|max:1',
        ]);

        $node = MonitoringNode::where('node_code', $validated['device_id'])->first();
        if (!$node) {
            return response()->json(['error' => 'Node not found'], 404);
        }

        DB::transaction(function () use ($request, $validated, $node) {
            $path = null;
            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('snapshots', 'public');
            }

            $maxTilt = max(abs($validated['angle_x']), abs($validated['angle_y']));
            $severity = $validated['status'];  // Sudah langsung CRITICAL atau WARNING

            $aiConfidence = $request->input('ai_confidence', null);
            $triggerType = $request->input('trigger_type', 'TILT_ALERT');

            $incident = Incident::create([
                'node_id' => $node->id,
                'triggered_at' => now(),
                'trigger_type' => $triggerType,
                'severity' => $severity,
                'max_tilt_angle' => $maxTilt,
                'ai_confidence' => $aiConfidence,
                'snapshot_path' => $path,
                'status' => 'UNRESOLVED',
            ]);

            // Update status HANYA jika ini dari sensor fisik (bukan dari kamera)
            if ($triggerType !== 'CRACK_DETECT') {
                $node->update(['status' => $severity]);
            }

            // Send Telegram Notification
            $this->sendTelegramAlert($incident, $node);
        });

        return response()->json(['status' => 'incident logged']);
    }

    private function sendTelegramAlert($incident, $node)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');
        if (!$token || !$chatId)
            return;

        $emoji     = $incident->severity === 'CRITICAL' ? '🚨' : '⚠️';
        $safeCode  = htmlspecialchars($node->node_code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeName  = htmlspecialchars($node->name ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $isCrack   = $incident->trigger_type === 'CRACK_DETECT';

        // ── Header ──────────────────────────────────────────────
        $title = $isCrack ? 'AI CRACK DETECTION ALERT' : 'TILT SENSOR ALERT';
        $message  = "{$emoji} <b>GEOGUARD {$title}: {$incident->severity}</b>\n\n";
        $message .= "📍 <b>Node:</b> {$safeCode}" . ($safeName ? " ({$safeName})" : "") . "\n";
        $message .= "⏱ <b>Waktu:</b> " . now()->timezone('Asia/Makassar')->format('Y-m-d H:i:s') . " WITA\n";

        // ── Koordinat ────────────────────────────────────────────
        if ($node->latitude && $node->longitude) {
            $message .= "🗺️ <b>Koordinat:</b> {$node->latitude}, {$node->longitude}\n";
            if ($node->elevation) {
                $message .= "⛰️ <b>Elevasi:</b> {$node->elevation} m\n";
            }
            $message .= "🌐 <b>Google Maps:</b> https://www.google.com/maps?q={$node->latitude},{$node->longitude}\n";
        }

        // ── Detail berdasarkan jenis trigger ─────────────────────
        $message .= "\n";
        if ($isCrack) {
            $confPct  = round($incident->ai_confidence * 100, 1);
            $message .= "🔍 <b>HASIL DETEKSI AI (YOLO Crack Detection):</b>\n";
            $message .= "• <b>Jenis Deteksi:</b> Retakan Permukaan (Surface Crack)\n";
            $message .= "• <b>AI Confidence:</b> {$confPct}%\n";
            $message .= "• <b>Max Tilt Sensor:</b> {$incident->max_tilt_angle}°\n";
        } else {
            $message .= "📐 <b>HASIL DETEKSI SENSOR KEMIRINGAN:</b>\n";
            $message .= "• <b>Max Tilt:</b> {$incident->max_tilt_angle}°\n";
            $message .= "• <b>Triggered by:</b> Inclinometer (MPU-6050)\n";
        }

        // ── Instruksi tindakan ───────────────────────────────────
        $message .= "\n";
        if ($incident->severity === 'CRITICAL') {
            $message .= "‼️ <b>ACTION REQUIRED:</b> SEGERA EVAKUASI RADIUS 200m! Hentikan seluruh operasi tambang.";
        } else {
            $message .= "🚧 <b>ACTION REQUIRED:</b> Tingkatkan monitoring. Batasi operasi alat berat di area ini.";
        }


        try {
            if ($incident->snapshot_path) {
                $url = "https://api.telegram.org/bot{$token}/sendPhoto";
                $photoUrl = asset('storage/' . $incident->snapshot_path);

                $response = Http::post($url, [
                    'chat_id' => $chatId,
                    'photo' => $photoUrl,
                    'caption' => $message,
                    'parse_mode' => 'HTML'
                ]);
                $response->throw();
            } else {
                $url = "https://api.telegram.org/bot{$token}/sendMessage";
                $response = Http::post($url, [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML'
                ]);
                $response->throw();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Telegram Error: ' . $e->getMessage());
        }
    }

    public function latest(Request $request)
    {
        $query = TelemetryLog::select('id', 'node_id', 'acc_x', 'acc_y', 'acc_z', 'pitch', 'roll', 'vibration_freq', 'ai_classification')
            ->with('node');

        // Filter by node_code if provided
        if ($request->has('node_code')) {
            $node = MonitoringNode::where('node_code', $request->node_code)->first();
            if ($node) {
                $query->where('node_id', $node->id);
            }
        }

        $log = $query->orderBy('id', 'desc')->first();

        if (!$log) {
            return response()->json(['error' => 'No data yet'], 404);
        }

        $incidentQuery = Incident::select('ai_confidence', 'snapshot_path');
        if ($log->node_id) {
            $incidentQuery->where('node_id', $log->node_id);
        }
        $incident = $incidentQuery->orderBy('id', 'desc')->first();

        return response()->json([
            'acc_x' => $log->acc_x,
            'acc_y' => $log->acc_y,
            'acc_z' => $log->acc_z,
            'pitch' => $log->pitch,
            'roll' => $log->roll,
            'vibration_freq' => $log->vibration_freq,
            'ai_confidence' => $incident ? $incident->ai_confidence : 0.99,
            'status' => $log->ai_classification,
            'node_code' => $log->node ? $log->node->node_code : 'INC_HW_01',
            'latitude' => $log->node ? (float) $log->node->latitude : -1.215,
            'longitude' => $log->node ? (float) $log->node->longitude : 116.851,
            'snapshot_url' => $incident && $incident->snapshot_path ? asset('storage/' . $incident->snapshot_path) : null
        ]);
    }

    public function latestPrediction()
    {
        $prediction = SlopeRiskPrediction::with('node')
            ->orderBy('id', 'desc')
            ->first();

        if (!$prediction) {
            return response()->json(['status' => 'no data'], 404);
        }

        return response()->json([
            'node_code' => $prediction->node->node_code ?? null,
            'angular_velocity' => (float) $prediction->angular_velocity,
            'inv_velocity' => (float) $prediction->inv_velocity,
            'risk_level' => $prediction->risk_level,
            'estimated_collapse_time' => $prediction->estimated_collapse_time,
            'insar_rate' => (float) $prediction->insar_displacement_rate,
            'calculated_at' => $prediction->created_at->toIso8601String(),
            'latitude' => $prediction->node ? (float) $prediction->node->latitude : -1.215,
            'longitude' => $prediction->node ? (float) $prediction->node->longitude : 116.851,
        ]);
    }

    public function muteBuzzer($node_code)
    {
        try {
            $server = env('MQTT_HOST', '100.71.97.101'); // IP Raspberry Pi MQTT Broker
            $port = (int) env('MQTT_PORT', 1883);
            $clientId = 'laravel-backend-' . uniqid();

            $mqtt = new \PhpMqtt\Client\MqttClient($server, $port, $clientId);
            $mqtt->connect();

            $payload = json_encode([
                'command' => 'buzzer_off',
                'node' => $node_code,
                'targetNode' => $node_code
            ]);

            $mqtt->publish('mine/pit1/command', $payload, 0);
            $mqtt->disconnect();

            return response()->json(['status' => 'success', 'message' => 'Command sent']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('MQTT Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'MQTT failed: ' . $e->getMessage()], 500);
        }
    }

    public function setInterval(Request $request, $node_code)
    {
        $validated = $request->validate([
            'interval' => 'required|integer|min:1000'
        ]);

        try {
            $server = env('MQTT_HOST', '100.71.97.101'); // IP Raspberry Pi MQTT Broker
            $port = (int) env('MQTT_PORT', 1883);
            $clientId = 'laravel-backend-' . uniqid();

            $mqtt = new \PhpMqtt\Client\MqttClient($server, $port, $clientId);
            $mqtt->connect();

            $payload = json_encode([
                'command' => 'set_interval',
                'node' => $node_code,
                'targetNode' => $node_code,
                'interval' => $validated['interval']
            ]);

            $mqtt->publish('mine/pit1/command', $payload, 0);
            $mqtt->disconnect();

            return response()->json(['status' => 'success', 'message' => 'Interval updated']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('MQTT Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'MQTT failed: ' . $e->getMessage()], 500);
        }
    }
    public function setLocation(Request $request, $node_code)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'elevation' => 'nullable|numeric'
        ]);

        $node = MonitoringNode::where('node_code', $node_code)->first();
        
        if (!$node) {
            return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);
        }

        $node->update([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'elevation' => $validated['elevation'] ?? $node->elevation
        ]);

        return response()->json([
            'status' => 'success', 
            'message' => 'Manual location updated successfully',
            'data' => [
                'latitude' => $node->latitude,
                'longitude' => $node->longitude,
                'elevation' => $node->elevation
            ]
        ]);
    }

    public function getInsarMode()
    {
        $path = public_path('data/insar_mode.json');
        $mode = 'real';
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            if (isset($data['mode'])) {
                $mode = $data['mode'];
            }
        }
        return response()->json(['mode' => $mode]);
    }

    public function setInsarMode(Request $request)
    {
        $request->validate(['mode' => 'required|in:real,simulated']);
        
        $path = public_path('data/insar_mode.json');
        
        // Pastikan folder data ada
        if (!file_exists(public_path('data'))) {
            mkdir(public_path('data'), 0755, true);
        }

        file_put_contents($path, json_encode(['mode' => $request->mode]));

        return response()->json([
            'status' => 'success',
            'message' => 'InSAR mode updated successfully to ' . $request->mode
        ]);
    }
}
