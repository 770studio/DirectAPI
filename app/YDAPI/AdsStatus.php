<?php

namespace App\YDAPI;

use Illuminate\Database\Eloquent\Model;

class AdsStatus extends Model
{
    protected $table = 'ads_status';
    protected $guarded = [];
    public function SuspendReason()
    {
        return $this->hasOne('App\YDAPI\SuspensionReason', 'id' , 'reason_id');
    }
}
