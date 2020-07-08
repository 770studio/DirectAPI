<?php

namespace App\YDAPI;



use  Exception;

class YDAPI
{

    public $averageBid = 30;
    public $TrafficVolume = 5;
    public $min_delta = 2000000; // 2 руб

    //
   static function run() {
       $json = file_get_contents('r.json');
       $jsonDecoded = json_decode($json);
        //dd( $jsonDecoded->result->KeywordBids);

       $n = new self;
       $n->process( $jsonDecoded);


    }


    static function runAdKiller() {

        //dd( $jsonDecoded->result->KeywordBids);

        $n = new self;
        $n->process( $jsonDecoded);


    }




    function process( & $jsonDecoded) {

        foreach($jsonDecoded->result->KeywordBids as $ad) {
            // dd($ad);

            try {
                $AdGroupId = $ad->AdGroupId;
                $KeywordId = $ad->KeywordId;
                $ServingStatus = $ad->ServingStatus;

                if($ServingStatus == 'RARELY_SERVED') continue;
                $StrategyPriority = $ad->StrategyPriority;
                $Bid = $ad->Search->Bid;
                $Search = $ad->Search;



                $BidItems = collect($Search->AuctionBids->AuctionBidItems );
                $BidItem = $BidItems->where('TrafficVolume', $this->TrafficVolume)->first();
                $Price = $BidItem->Price;
                $Price = $BidItem->Price;
                $maxBid = $BidItem->Bid ;
               // dd($maxBid, $BidItems->where('TrafficVolume', 5)->first());
                if($Bid >  $maxBid + $this->min_delta  ) {
                    // наша ставка больше , чем максимальная
                    $myBid = $maxBid + $this->min_delta;
                    dump('----------------' . $Price . '-------------------');
                    dump($AdGroupId, $KeywordId, $ServingStatus, $StrategyPriority, $Bid);
                    dump("СТАВКА НЕОПРАВДАНО ВЫСОКАЯ:", $Bid, "против:" , $maxBid);
                }
                elseif( $Bid < $this->averageBid  )  {
                    // ставка не выше чем нужно, но меньше средней, т.е ситуацмя , когда
                    // мы снижали ставку (когда она была слишком высокая), а теперь она не слишком высока уже
                    // т.е можно приподнять до средней, если средняя не больше максимальной, если больше, то до максимальной (перебить ставку)
                    $myBid = $this->averageBid > $maxBid ? $maxBid + $this->min_delta  : $this->averageBid ;

                    dump('-----------------------------------');
                    dump($AdGroupId, $KeywordId, $ServingStatus, $StrategyPriority, $Bid);
                    dump("СТАВКА НЕ ВЫШЕ МАКС но и НЕ ВЫШЕ СРЕДНЕЙ, СДЕЛАТЬ СТАВКУ СРЕДНЕй?:", $Bid, "против:" , $this->averageBid);

                }


            } catch (Exception $e) {
                dump($e->getMessage() );
                dump($Search );
                continue;
            }


        }

    }

    function getBids() {

        $body = array (
            'method' => 'get',
            'params' =>
                array (
                    'SelectionCriteria' =>
                        array (
                            'CampaignIds' =>
                                array (
                                    0 => 51359047,
                                ),
                            'AdGroupIds' =>
                                array (
                                ),
                            'KeywordIds' =>
                                array (
                                ),
                            'ServingStatuses' =>
                                array (
                                ),
                        ),
                    'FieldNames' =>
                        array (
                            0 => 'AdGroupId',
                            1 => 'KeywordId',
                            2 => 'ServingStatus',
                            3 => 'StrategyPriority',
                        ),
                    'SearchFieldNames' =>
                        array (
                            0 => 'Bid',
                            1 => 'AuctionBids',
                        ),
                    'NetworkFieldNames' =>
                        array (
                        ),
                    'Page' =>
                        array (
                            'Limit' => 1000,
                            'Offset' => 0,
                        ),
                ),
        );

    }

    function getDenial() {
        return array (
            'params' =>
                array (
                    'SelectionCriteria' =>
                        array (
                            'Filter' =>
                                array (
                                    0 =>
                                        array (
                                            'Field' => 'BounceRate',
                                            'Operator' => 'GREATER_THAN',
                                            'Values' =>
                                                array (
                                                    0 => '80',
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'Field' => 'Conversions',
                                            'Operator' => 'LESS_THAN',
                                            'Values' =>
                                                array (
                                                    0 => '1',
                                                ),
                                        ),
                                    2 =>
                                        array (
                                            'Field' => 'Impressions',
                                            'Operator' => 'GREATER_THAN',
                                            'Values' =>
                                                array (
                                                    0 => '20',
                                                ),
                                        ),
                                ),
                        ),
                    'FieldNames' =>
                        array (
                            0 => 'AdGroupId',
                            1 => 'Impressions',
                            2 => 'CampaignId',
                            3 => 'Clicks',
                            4 => 'Cost',
                            5 => 'BounceRate',
                            6 => 'Conversions',
                        ),
                    'ReportName' => 'BounceRate80+Conversions0+20Impressions+LAST_30_DAYS',
                    'ReportType' => 'ADGROUP_PERFORMANCE_REPORT',
                    'Format' => 'TSV',
                    'IncludeVAT' => 'YES',
                    'IncludeDiscount' => 'YES',
                    'DateRangeType' => 'LAST_30_DAYS',
                ),
        );

    }
}
