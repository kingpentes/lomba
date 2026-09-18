<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'node_id',
        'triggered_at',
        'trigger_type',
        'severity',
        'max_tilt_angle',
        'ai_confidence',
        'snapshot_path',
        'status',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'max_tilt_angle' => 'float',
        'ai_confidence' => 'float',
    ];

    public function node()
    {
        return $this->belongsTo(MonitoringNode::class, 'node_id');
    }

    public function actions()
    {
        return $this->hasMany(IncidentAction::class, 'incident_id');
    }
}
