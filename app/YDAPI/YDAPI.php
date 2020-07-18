<?php

namespace App\YDAPI;




use  Exception;
use Illuminate\Support\Facades\Log;

class YDAPI
{

    public $averageBid = 30;
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


    static function AdsCleaning( $accId) {


        APIRequest::$check_units = false; // за отчеты баллы не списывают

       $account = Account::find($accId);
        if(!$account) throw new Exception('no such an account');

        Log::channel('chrono')->info('Запуск AdCleaning, account:' . $accId );

        $n = new self ( $account);

        $ads  = APIRequest::FindBadAds();
        Log::channel('chrono')->info('Получили adIds'   );
        APIRequest::SuspendAd( $ads );
        Log::channel('chrono')->info('Закончили AdCleaning'   );





    }
    static function UpdateKeywordBids( $accId ) {

        $account = Account::find($accId);
        if(!$account) throw new Exception('no such an account');

        Log::channel('chrono')->info('Запуск UpdateKeywordBids, account:' . $accId );

        $cIds = explode(',', $account->CampaignIds);
        if(!$cIds) throw new Exception('no CampaignIds');





        //$json = file_get_contents('r.json');
        //$jsonDecoded = json_decode($json);
        //dd( $jsonDecoded->result->KeywordBids);

        $n = new self ( $account ) ;

        $bids =   APIRequest::getKeywordBids($cIds )  ;
        Log::channel('chrono')->info('получили ставки');

        $n->handleKeywordBids( $bids);
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

    function handleKeywordBids( & $bids) {

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
                elseif( $Bid < $this->averageBid  )  {
                    // ставка не выше чем нужно, но меньше средней, т.е ситуацмя , когда
                    // мы снижали ставку (когда она была слишком высокая), а теперь она не слишком высока уже
                    // т.е можно приподнять до средней, если средняя не больше максимальной, если больше, то до максимальной (перебить ставку)
                    $myBid = $this->averageBid > $maxBid ? $maxBid + $this->min_delta  : $this->averageBid ;

                    dump('-----------------------------------');
                    dump($AdGroupId, $KeywordId, $ServingStatus, $StrategyPriority, $Bid);
                    dump("СТАВКА НЕ ВЫШЕ МАКС но и НЕ ВЫШЕ СРЕДНЕЙ, СДЕЛАТЬ СТАВКУ СРЕДНЕй?:", $Bid, "против:" , $this->averageBid);
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

            $r =   APIRequest::updateKeywordBid( $this->keywords_update );
            dump( $r ); // обновленные ставки

        } catch (Exception $e) {
            dump($e->getMessage() );
             Log::channel('chrono')->info('апдейт ставок завершен неудачей.');

        }

        Log::channel('chrono')->info('апдейт ставок прошел');



    }


}
