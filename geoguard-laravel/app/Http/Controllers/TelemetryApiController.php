<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelemetryLog;
use App\Models\MonitoringNode;
use App\Models\Incident;
use App\Models\SlopeRiskPrediction;
use Illuminate\Support\Facades\DB;

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
        ]);

        $node = MonitoringNode::where('node_code', $validated['node_code'])->first();
        if (!$node) {
            // For testing purposes, create the node if it doesn't exist
            $node = MonitoringNode::create([
                'node_code' => $validated['node_code'],
                'name' => 'Auto-generated Node ' . $validated['node_code'],
                'latitude' => 40.5226,
                'longitude' => -112.1481,
                'elevation' => 1500,
                'status' => 'STABLE'
            ]);
        }

        TelemetryLog::create([
            'node_id' => $node->id,
            'recorded_at' => $validated['timestamp'],
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
            'status' => 'required|in:BAHAYA,WASPADA',
            'photo' => 'nullable|file|mimes:jpeg,png,jpg',
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
            $severity = $validated['status'] === 'BAHAYA' ? 'CRITICAL' : 'WARNING';

            Incident::create([
                'node_id' => $node->id,
                'triggered_at' => now(),
                'trigger_type' => 'TILT_ALERT',
                'severity' => $severity,
                'max_tilt_angle' => $maxTilt,
                'ai_confidence' => 0.99, // default if not provided
                'snapshot_path' => $path,
                'status' => 'UNRESOLVED',
            ]);

            $node->update(['status' => $severity]);
        });

        return response()->json(['status' => 'incident logged']);
    }

    public function latest()
    {
        $log = TelemetryLog::select('id', 'node_id', 'acc_x', 'acc_y', 'acc_z', 'pitch', 'roll', 'vibration_freq', 'ai_classification')
            ->orderBy('id', 'desc')
            ->first();
            
        if (!$log) {
            return response()->json(['error' => 'No data yet'], 404);
        }
        
        $incident = Incident::select('ai_confidence', 'snapshot_path')->orderBy('id', 'desc')->first();
        
        return response()->json([
            'acc_x' => $log->acc_x,
            'acc_y' => $log->acc_y,
            'acc_z' => $log->acc_z,
            'pitch' => $log->pitch,
            'roll' => $log->roll,
            'vibration_freq' => $log->vibration_freq,
            'ai_confidence' => $incident ? $incident->ai_confidence : 0.99,
            'status' => $log->ai_classification,
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
}
