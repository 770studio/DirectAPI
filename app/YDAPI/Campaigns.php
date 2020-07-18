<?php

namespace App\YDAPI;


use Exception;
use Illuminate\Database\Eloquent\Model;

class Campaigns extends Model
{
    //
    public $cmps;

    public function Account()
    {
        return $this->belongsTo('App\YDAPI\Account' );
    }





    function __construct()
    {


    }
/*
    static function fromJson( $json ) {
        $cmps = collect( json_decode( $json ) );
        if(!$cmps) throw new Exception(  'Campaigns fromJson: json не парсится, no CampaignIds ?' )    ;

        return new Campaigns( $cmps );

    }*/


    function getAvgBidByCampainId( $id ) {
        $cmp = $this->cmps[$id];
        if(!$cmp) throw new Exception(  'Campaigns getAvgBidByCampainId: нет кампании с CampaignId :' . $id )    ;
        if(!$cmp->avg_bid) throw new Exception(  'Campaigns getAvgBidByCampainId: нет средней ставквки для CampaignId :' . $id )    ;

        return $cmp->avg_bid;

    }
}
