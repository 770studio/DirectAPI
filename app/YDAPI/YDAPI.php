<?php

namespace App\YDAPI;




use  Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class YDAPI
{


    public $TrafficVolume = 10; //
    public $min_delta = 2000000; // 2 руб
    private $keywords_update = [];
    public $account;
    private $ad_state;

    //


    public function __construct( & $account)
    {

        $this->account = & $account;
        APIRequest::setAccount( $account );

    }

    static function run( $accId ) {



    }


    static function AdsCleaning( & $account ) {
        APIRequest::$check_units = false; // за отчеты баллы не списывают

        if(!$account) throw new Exception('no such an account');




        Log::channel('chrono')->info('Запуск AdCleaning, account:' . $account->id );

        $n = new self ( $account);

        $reports = $n->processReports() ;

        Log::channel('chrono')->info('Закончили AdCleaning'   );


        /*
                $ads  = APIRequest::FindBadAds();
                Log::channel('chrono')->info('Получили adIds'   );
                APIRequest::SuspendAd( $ads );
                Log::channel('chrono')->info('Закончили AdCleaning'   );
        */




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

        if($account->TrafficVolume) $n->TrafficVolume = $account->TrafficVolume;

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
             dd($ad);

            $cutDown = $this->adIsUnderCut($ad->Id)   ;

           // if($cutDown)
            dump($cutDown, $ad->AdGroupId, $ad->KeywordId );

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
                    //KeywordBid::create($newKb);

                    KeywordBid::updateOrCreate(
                        ['KeywordId' =>  $KeywordId, 'ServingStatus' => 'RARELY_SERVED'],
                        $newKb
                    );

                    continue;
                }

                //TODO  TODOTODOTODOTODOTODOTODOTODOTODOTODOTODOTODOTODOTODOTODOTODO
                //  стратегии

     /*
                1. Заплатить минимум за минимум трафика. Исследование рынка. Проверка бюджета.
                   Претендуем на самый дешевый пакет трафика, это будут видимо нижние позиции в выдаче, которыу могут
                   обеспечить какой-то трафик, при этом ставка всегда минимальна.

                    Выбранная макс ставка на рк.
                    Соотвественно ставка не может быть выше выбранной, т.е нужно идти
                    вверх по объему трафика и выбрать тот объем (занять позицию на поиске) , который позвояет макс. ставка

                2. Заплатить максимум (не не более определенного значения) и взять трафика столько, сколько позволит
                    макс. уровень ставки , определяемый для каждой рк.

                 Выбранный объем трафика. При этом ставка сама по себе не ограничена, ограничения будут на уровне
                рк (дневные ограничения), при этом неплохо бы проверить , что органичение сработало, чтобы приостановить
                дальнейшее бессмысленное выполнение


                 3.


                */


                $BidItems = collect($Search->AuctionBids->AuctionBidItems );

               // $bids->where('Bid', '<', 950000000)->max('TrafficVolume')


                $BidItem = $BidItems->where('TrafficVolume', $this->TrafficVolume)->first();

                $tvolume = $this->TrafficVolume;
                while(!$BidItem )  {
                    // если нет цены за данный объем берем меньший
                    $tvolume = $tvolume- 5 ;
                    if($tvolume < 5) break;
                    $BidItem = $BidItems->where('TrafficVolume', $tvolume)->first();

                }
                if(!$BidItem)  throw new Exception('нет цены за трафик');
                $Price = $BidItem->Price;
                $maxBid = $BidItem->Bid ;
               // dd($maxBid, $BidItems->where('TrafficVolume', 5)->first());

                if($cutDown) {
                   // ставка должна быть срезана т.к объявление имеет плохую эффективность (альтернатива остановки )
                    $myBid = $avg_bid/2;
                    $this->getDown($myBid, $KeywordId);
                    KeywordBid::create(array_merge($newKb, [
                        'AuctionBid' => $maxBid,
                        'AuctionPrice' => $Price,
                        'action' => 'cut_down2',
                        'newBid' => $myBid
                    ]));

                    dump($AdGroupId, $KeywordId, $ServingStatus, $StrategyPriority, $Bid, $this->TrafficVolume);
                    dump("ставка должна быть срезана т.к объявление имеет плохую эффективность (альтернатива остановки ):", $Bid, "против:" , $maxBid, "для кол-во трафа:" , $this->TrafficVolume);


                }

                elseif($Bid >  $maxBid + $this->min_delta  ) {
                    // наша ставка больше , чем максимальная
                    $myBid = $maxBid + $this->min_delta;
                    dump('----------------' . $Price . '-------------------');
                    dump($AdGroupId, $KeywordId, $ServingStatus, $StrategyPriority, $Bid, $this->TrafficVolume);
                    dump("СТАВКА НЕОПРАВДАНО ВЫСОКАЯ:", $Bid, "против:" , $maxBid, "для кол-во трафа:" , $this->TrafficVolume);
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
//dd($BidItem, $this->TrafficVolume, $BidItems);
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

    private function processReports() {


        // TODO severity на уровне кампаний , а не аккаунта
        // чтобы взять отчеты , посмотрим какие отчеты применимы для аккаунта
        $reasons =  [];

        //TODO логика или cleaning_severity или severity_tag , реализовать и или
        if($this->account->cleaning_severity) {
            $reasons = SuspensionReason::where('severity', '<=', $this->account->cleaning_severity )->get();
        } elseif($this->account->severity_tag) {
            $reasons = SuspensionReason::whereIn('severity_tag',  explode(',', $this->account->severity_tag ) )->get();
        }


        $suspend = $suspendAdIds = [];

        // по каждому комплексу условий свой отчет
        foreach($reasons as $reason) {


            try {
                $report = APIRequest::getAD_PERFORMANCE_REPORT( $reason );
                Log::channel('chrono')->info('Получили отчет:' . $reason->report_name   );


            } catch (Exception $e) {
                //  возможно отчет формируется ...
                Log::channel('chrono')->info($e->getMessage()  );
                continue; // к следующему отчету
            }


            // в  зависимости от типа мер приостановка или уменьшение ставки

            switch($reason->type) {
                case 'suspend':

                    Log::channel('chrono')->info('Отчет содержит объявления на остановку: suspend'   );

                    foreach($report as $adGroupId => $ad_id) {
                        $suspend[] = [  'adgroup_id' => $adGroupId , 'suspended' => 1, 'reason_id' => $reason->id , 'account_id' => $this->account->id ];
                        $suspendAdIds[] = $ad_id;
                    }


                    break;
                case 'cut_down2':
                     Log::channel('chrono')->info('Отчет содержит объявления на понижение ставки: cut_down2'   );
                    // для того , чтоб зафиксировать cut_down2 , просто добавим или обновим запись в бд
                     foreach($report as $adGroupId => $ad_id) {
                            AdsStatus::updateOrCreate(
                                [  'adgroup_id' => $adGroupId,  'account_id' => $this->account->id ],
                                [ 'adgroup_id' => $adGroupId, 'suspended' => 0, 'reason_id' => $reason->id, 'account_id' => $this->account->id ]
                            );


                    }

                    break;

            }






        } // foreach

        Log::channel('chrono')->info('Закончили Cut Down'   );


        if($suspend) {
            // есть  на удаление
            try {
                Log::channel('chrono')->info(count($suspend) . 'объявлений на остановку, - остонавливаем'   );
                APIRequest::SuspendAd(   $suspendAdIds  ) ; // collect($suspend)->pluck('ad_id')->all()

                foreach ( $suspend as $row ) {
                    AdsStatus::updateOrCreate(
                        ['adgroup_id' =>   $row['adgroup_id'], 'account_id' => $this->account->id ],
                        $row
                    );
                }








                /*
                $existed = AdsStatus::select('ad_id')->whereIn('ad_id', $suspend );
                $existedAdIds = $existed->get()->pluck('ad_id')->toArray();
                if($existedAdIds) {
                    $existed->update(['suspended'=> 1 ]);
                    $insertIds = array_diff($suspend, $existedAdIds );
                } else $insertIds = $suspend;



                try{
                    AdsStatus::insert($data);

                }  catch (Exception $e) {

                    if($e->getCode() == '2300 ') {
                        // Duplicate entry
                    }
                    dump(

                        $e->getMessage());
                }
 */


                Log::channel('chrono')->info('Закончили Suspending'   );

            }
            catch (Exception $e) {
                //
                Log::channel('chrono')->info($e->getMessage()  );

            }

        }


    }






    function  adIsUnderCut($ad_id) {

        if(!$this->ad_state) {
            // $this->ad_state = AdsStatus::where('account_id' , $this->account->id)->get();
            $this->ad_state =   AdsStatus::where('account_id' , $this->account->id)->whereHas('SuspendReason', function ( Builder $query) {
                $query->where('type',  'cut_down2');
            })->get();


        }

          return (bool)$this->ad_state->where('ad_id', $ad_id)->count();
    }











}
