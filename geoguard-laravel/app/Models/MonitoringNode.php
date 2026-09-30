<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringNode extends Model
{
    protected $fillable = [
        'node_code',
        'name',
        'latitude',
        'longitude',
        'elevation',
        'status',
    ];

    public function telemetryLogs()
    {
        return $this->hasMany(TelemetryLog::class, 'node_id');
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class, 'node_id');
    }

    public function latestTelemetry()
    {
        return $this->hasOne(TelemetryLog::class, 'node_id')->latestOfMany();
    }

    public function latestIncident()
    {
        return $this->hasOne(Incident::class, 'node_id')->latestOfMany('triggered_at');
    }

    public function slopeRiskPredictions()
    {
        return $this->hasMany(SlopeRiskPrediction::class, 'node_id');
    }
}
