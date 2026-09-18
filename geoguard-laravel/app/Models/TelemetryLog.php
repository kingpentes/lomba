<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryLog extends Model
{
    protected $fillable = [
        'node_id',
        'recorded_at',
        'acc_x',
        'acc_y',
        'acc_z',
        'pitch',
        'roll',
        'vibration_freq',
        'ppv_value',
        'ai_classification',
    ];
    
    protected $casts = [
        'recorded_at' => 'datetime',
        'acc_x' => 'float',
        'acc_y' => 'float',
        'acc_z' => 'float',
        'pitch' => 'float',
        'roll' => 'float',
        'vibration_freq' => 'float',
        'ppv_value' => 'float',
    ];

    public function node()
    {
        return $this->belongsTo(MonitoringNode::class, 'node_id');
    }
}
