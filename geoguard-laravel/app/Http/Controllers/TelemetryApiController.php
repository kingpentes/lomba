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
        ]);

        $node = MonitoringNode::where('node_code', $validated['node_code'])->first();
        if (!$node) {
            // For testing purposes, create the node if it doesn't exist
            $node = MonitoringNode::create([
                'node_code' => $validated['node_code'],
                'name' => 'Auto-generated Node ' . $validated['node_code'],
                'latitude' => 40.5226,
                'longitude' => $validated['longitude'] ?? -112.1481,
                'elevation' => 1500,
                'status' => 'STABLE'
            ]);
        }

        // Update GPS location and Status based on realtime ESP32 telemetry
        $updateData = [];
        if (isset($validated['latitude']) && isset($validated['longitude']) && $validated['latitude'] != 0.0) {
            $updateData['latitude'] = $validated['latitude'];
            $updateData['longitude'] = $validated['longitude'];
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

        TelemetryLog::create([
            'node_id' => $node->id,
            'recorded_at' => now(), // Abaikan jam dari ESP32, gunakan waktu asli server/laptop saat data tiba
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

            // Gunakan confidence dari AI jika tersedia, fallback ke 0.99
            $aiConfidence = $validated['ai_confidence'] ?? 0.99;

            // Trigger type: CRACK_DETECT jika AI mendeteksi retakan, TILT_ALERT jika hanya sensor
            $triggerType = ($aiConfidence > 0 && $aiConfidence < 0.99) ? 'CRACK_DETECT' : 'TILT_ALERT';

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

            $node->update(['status' => $severity]);

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

        $emoji = $incident->severity === 'CRITICAL' ? '🚨' : '⚠️';
        $message = "{$emoji} <b>GEOGUARD ALERT: {$incident->severity}</b>\n\n";
        $message .= "📍 <b>Node:</b> {$node->node_code}" . ($node->name ? " ({$node->name})" : "") . "\n";
        $message .= "⏱ <b>Waktu:</b> " . now()->format('Y-m-d H:i:s') . "\n";
        if ($node->latitude && $node->longitude) {
            $message .= "🗺️ <b>Koordinat:</b> {$node->latitude}, {$node->longitude}\n";
            if ($node->elevation) {
                $message .= "⛰️ <b>Elevasi:</b> {$node->elevation} m\n";
            }
            $message .= "🌐 <b>Google Maps:</b> https://www.google.com/maps?q={$node->latitude},{$node->longitude}\n";
        }
        $message .= "📐 <b>Max Tilt:</b> {$incident->max_tilt_angle}°\n";
        $message .= "🤖 <b>AI Trigger:</b> {$incident->trigger_type}\n\n";

        if ($incident->severity === 'CRITICAL') {
            $message .= "‼️ <b>ACTION REQUIRED:</b> IMMEDIATELY EVACUATE THE AREA WITHIN 200m RADIUS.";
        } else {
            $message .= "🚧 <b>ACTION REQUIRED:</b> Increase monitoring and restrict heavy equipment.";
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
        ]);
    }

    public function muteBuzzer($node_code)
    {
        try {
            $server = '100.71.97.101'; // IP Raspberry Pi MQTT Broker
            $port = 1883;
            $clientId = 'laravel-backend-' . uniqid();

            $mqtt = new \PhpMqtt\Client\MqttClient($server, $port, $clientId);
            $mqtt->connect();

            $payload = json_encode([
                'command' => 'buzzer_off',
                'node' => $node_code
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
            $server = '10.214.68.91'; // IP Raspberry Pi MQTT Broker
            $port = 1883;
            $clientId = 'laravel-backend-' . uniqid();

            $mqtt = new \PhpMqtt\Client\MqttClient($server, $port, $clientId);
            $mqtt->connect();

            $payload = json_encode([
                'command' => 'set_interval',
                'node' => $node_code,
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
}
