<?php

namespace App\YDAPI;




use  Exception;
use Illuminate\Support\Facades\Log;

class YDAPI
{


    public $TrafficVolume = 5;
    public $min_delta = 2000000; // 2 руб
    private $keywords_update = [];

    //


    public function __construct( & $account)
    {

        APIRequest::setAccount( $account );

    }

    static function run( $accId ) {



    }


    static function AdsCleaning( & $account ) {
        APIRequest::$check_units = false; // за отчеты баллы не списывают

        if(!$account) throw new Exception('no such an account');




        Log::channel('chrono')->info('Запуск AdCleaning, account:' . $account->id );

        $n = new self ( $account);

        $ads  = APIRequest::FindBadAds();
        Log::channel('chrono')->info('Получили adIds'   );
        APIRequest::SuspendAd( $ads );
        Log::channel('chrono')->info('Закончили AdCleaning'   );





    }
    static function UpdateKeywordBids( & $account ) {


        if(!$account) throw new Exception('no such an account');

        Log::channel('chrono')->info('Запуск UpdateKeywordBids, account:' . $account->id );

        // $cIds = json_decode( $account->CampaignIds ) ; //explode(',', $account->CampaignIds);

        // dd(5555, $account->Campaigns->count() ); // keyBy('compaign_id')->all()
         $cIds = $account->Campaigns->pluck('campaign_id');
         if(!$cIds) throw new Exception('no CampaignIds');



        //$json = file_get_contents('r.json');
        //$jsonDecoded = json_decode($json);
        //dd( $jsonDecoded->result->KeywordBids);

        $n = new self ( $account ) ;

        $bids =   APIRequest::getKeywordBids($cIds )  ;
        Log::channel('chrono')->info('получили ставки');



        $n->handleKeywordBids( $bids, $account);
        Log::channel('chrono')->info('обработали ставки');



    }

    function getUp($myBid,  $KeywordId) {
        $this->setKeywordBid($myBid, $KeywordId);
    }
    function getDown($myBid,  $KeywordId) {
        $this->setKeywordBid($myBid, $KeywordId);
    }
    function setKeywordBid($myBid, $KeywordId) {
        $this->keywords_update[] = ["KeywordId" => $KeywordId, "SearchBid"=> $myBid ]  ;

    }

    /*
кампаний — не более 10;
групп — не более 1000;
ключевых фраз и автотаргетингов — не более 10 000.
         * TODO как проверить ?

*/

    function handleKeywordBids( & $bids, & $account) {

        foreach($bids->result->KeywordBids as $ad) {
              // dd($ad);


            try {
                $CampaignId = $ad->CampaignId;
                $AdGroupId = $ad->AdGroupId;
                $KeywordId = $ad->KeywordId;
                $ServingStatus = $ad->ServingStatus;
                $StrategyPriority = $ad->StrategyPriority;
                $Search = $ad->Search;
                $Bid = $Search->Bid;

                $avg_bid =  1000000 * (int)$account->Campaigns->where('campaign_id',  $CampaignId )->first()->avg_bid ;
                if(!$avg_bid)  throw new Exception('Нулевая средняя ставка');

                $newKb = [
                    'CampaignId' =>  $CampaignId,
                    'AdGroupId' =>  $AdGroupId,
                    'KeywordId' =>  $KeywordId,
                    'ServingStatus' => $ServingStatus,
                    'StrategyPriority' => $StrategyPriority,
                    'TrafficVolume' => $this->TrafficVolume,
                    'Bid' => $Bid,
                ];




                if($ServingStatus == 'RARELY_SERVED') {
                    KeywordBid::create($newKb);
                    continue;
                }



                $BidItems = collect($Search->AuctionBids->AuctionBidItems );
                $BidItem = $BidItems->where('TrafficVolume', $this->TrafficVolume)->first();
                $Price = $BidItem->Price;
                $maxBid = $BidItem->Bid ;
               // dd($maxBid, $BidItems->where('TrafficVolume', 5)->first());
                if($Bid >  $maxBid + $this->min_delta  ) {
                    // наша ставка больше , чем максимальная
                    $myBid = $maxBid + $this->min_delta;
                    dump('----------------' . $Price . '-------------------');
                    dump($AdGroupId, $KeywordId, $ServingStatus, $StrategyPriority, $Bid);
                    dump("СТАВКА НЕОПРАВДАНО ВЫСОКАЯ:", $Bid, "против:" , $maxBid);
                    $this->getDown($myBid, $KeywordId);
                    KeywordBid::create(array_merge($newKb, [
                        'AuctionBid' => $maxBid,
                        'AuctionPrice' => $Price,
                        'action' => 'down',
                        'newBid' => $myBid
                    ]));
                }
                elseif( $Bid + $this->min_delta < $avg_bid )  {
                    // ставка не выше чем нужно, но меньше средней, т.е ситуацмя , когда
                    // мы снижали ставку (когда она была слишком высокая), а теперь она не слишком высока уже
                    // т.е можно приподнять до средней, если средняя не больше максимальной, если больше, то до максимальной (перебить ставку)
                    $myBid = $avg_bid > $maxBid ? $maxBid + $this->min_delta  : $avg_bid;

                    dump('-----------------------------------');
                    dump($AdGroupId, $KeywordId, $ServingStatus, $StrategyPriority, $Bid);
                    dump("СТАВКА НЕ ВЫШЕ МАКС но при это НИЖЕ СРЕДНЕЙ, СДЕЛАТЬ СТАВКУ СРЕДНЕй?:", $Bid, "против:" , $avg_bid);
                    $this->getUp($myBid,  $KeywordId);

                    KeywordBid::create(array_merge($newKb, [
                        'AuctionBid' => $maxBid,
                        'AuctionPrice' => $Price,
                        'action' => 'up',
                        'newBid' => $myBid
                    ]));
                }


            } catch (Exception $e) {
                 dump($e->getMessage() );
                // dump($Search );
                Log::channel('chrono')->info( $e->getMessage() );

                continue;
            }






        }

        Log::channel('chrono')->info('обработали массив ставок');

        try {

            Log::channel('chrono')->info('апдейт ставок , всего в массве: ' . count($this->keywords_update) );

            if(count($this->keywords_update))  {
                $r =   APIRequest::updateKeywordBid( $this->keywords_update );
                dump( $r ); // обновленные ставки
            }
            else  Log::channel('chrono')->info('масив пустой , апдейт не требуется '  );



        } catch (Exception $e) {
            dump($e->getMessage() );
             Log::channel('chrono')->info('апдейт ставок завершен неудачей.');

        }

        Log::channel('chrono')->info('апдейт ставок завершен');



    }


}
