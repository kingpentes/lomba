<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentAction extends Model
{
    protected $fillable = [
        'incident_id',
        'user_id',
        'action_notes',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
