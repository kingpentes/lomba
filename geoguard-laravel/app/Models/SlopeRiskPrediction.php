<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlopeRiskPrediction extends Model
{
    protected $fillable = [
        'node_id',
        'angular_velocity',
        'inv_velocity',
        'risk_level',
        'estimated_collapse_time',
        'insar_displacement_rate'
    ];

    protected $casts = [
        'estimated_collapse_time' => 'datetime',
    ];

    public function node()
    {
        return $this->belongsTo(MonitoringNode::class, 'node_id');
    }
}
