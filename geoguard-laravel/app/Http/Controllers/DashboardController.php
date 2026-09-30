<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MonitoringNode;
use App\Models\Incident;

class DashboardController extends Controller
{
    public function index()
    {
        $nodes = MonitoringNode::all();
        $latestIncident = Incident::with('node')->orderBy('id', 'desc')->first();
        
        return view('dashboard', compact('nodes', 'latestIncident'));
    }

    public function dispatcher()
    {
        $nodes = MonitoringNode::with(['latestTelemetry', 'latestIncident'])->get();
        return view('dispatcher', compact('nodes'));
    }

    public function prediction()
    {
        return view('prediction');
    }

    public function earlyWarning()
    {
        return view('earlywarning');
    }
}
