<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Incident;
use App\Models\MonitoringNode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $incidents = Incident::with('node')->orderBy('id', 'desc')->get();
        return view('incidents', compact('incidents'));
    }
    
    public function export()
    {
        $incidents = Incident::with('node')->orderBy('id', 'desc')->get();
        
        $response = new StreamedResponse(function() use ($incidents) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Node Code', 'Triggered At', 'Severity', 'Trigger Type', 'Max Tilt Angle', 'AI Confidence', 'Status']);
            
            foreach ($incidents as $incident) {
                fputcsv($handle, [
                    $incident->id,
                    $incident->node->node_code ?? 'N/A',
                    $incident->triggered_at,
                    $incident->severity,
                    $incident->trigger_type,
                    $incident->max_tilt_angle,
                    $incident->ai_confidence,
                    $incident->status,
                ]);
            }
            fclose($handle);
        });
        
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="incident_report.csv"');
        return $response;
    }
}
