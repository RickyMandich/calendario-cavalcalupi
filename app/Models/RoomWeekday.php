<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomWeekday extends Model
{
    protected $table = 'room_weekday';
    public $timestamps = false;
    protected $fillable = ['room_id', 'weekday'];
}
?>
