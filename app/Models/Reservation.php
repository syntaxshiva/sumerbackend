<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $table = 'student_trips';

    //planned trip
    public function plannedTrip()
    {
        return $this->belongsTo(PlannedTrip::class, 'planned_trip_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function firstStop()
    {
        return $this->belongsTo(Stop::class, 'start_stop_id');
    }

    //last stop
    public function lastStop()
    {
        return $this->belongsTo(Stop::class, 'end_stop_id');
    }

}
