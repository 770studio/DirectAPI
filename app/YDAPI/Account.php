<?php

namespace App\YDAPI;

use Illuminate\Database\Eloquent\Model;


class Account extends Model
{
    public $timestamps = false;
    //

    public function User()
    {
        return $this->belongsTo('App\User' );
    }

}
