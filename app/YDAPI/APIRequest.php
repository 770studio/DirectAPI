<?php

namespace App\YDAPI;



use Carbon\Carbon;
use  Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class APIRequest
{

    public static $account ;
    public static $check_units = true ;



    static function setAccount( & $account) {
        self::$account = & $account;
    }
    static function getKeywordBidsBody($CampaignIds) {
        return array (
            'method' => 'get',
            'params' =>
                array (
                    'SelectionCriteria' =>
                        array (
                            'CampaignIds' => $CampaignIds,
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
                            4 => 'CampaignId',
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


    static function updateKeywordBid( & $keywords  ) {

        if(!$keywords) return;
        $data = [ "method"=> "set",
                     "params" => [
                        "KeywordBids" => $keywords
                     ]
            ];

      $response = self::request( "https://api.direct.yandex.com/json/v5/keywordbids",
               $data
        );

        self::Checkin($response );

        return $response->body();

    }
    static function getKeywordBids ( $CampaignIds  ) {
        $body = self::getKeywordBidsBody($CampaignIds);
        $response = self::request( "https://api.direct.yandex.com/json/v5/keywordbids",
               $body
        );


            if(!$response->successful()) throw new Exception( $response->body() )    ;

            $data = json_decode( $response->body() );
            if(!$data) throw new Exception(  'getKeywordBids: json не парсится' )    ;

            return $data;
/*        dd($response->successful(), $response->body(), $response->header('Units-Used-Login'),
            $response->header('Units')
        );*/

    }

    static function Checkin(& $response ) {

        if(!self::$check_units)  return;

        // units
        $units = $response->header('Units');
        //израсходовано при выполнении запроса / доступный остаток / суточный лимит
        if(!$units || !preg_match("/^(\d+)\/(\d+)\/(\d+)$/", $units, $matches ))
                throw new Exception('Невозможно получть баллы');

        // на запрос надо 15 баллов TODO уточнить и расширить
        if($matches[2] < 15 ) {
            if( self::takeAbreak( ))
                Log::channel('chrono')->info('баллы закончились, пауза на час'  );

        }



    }


    static function FindBadAds() {
        $body = self::getBadAdsArray();
        $response = self::request( "https://api.direct.yandex.com/json/v5/reports",
            $body
        );

        if(!$response->successful() || !$response->body()) throw new Exception(  'FindBadAds: отчет формируется!' )    ;;

        $data = [];
        $rows = explode( "\n", $response->body());
        foreach( $rows as  $row) {
            $data[] = explode( "\t", $row)[0];
        }
        array_shift($data);
        array_shift($data);
        array_pop($data);
        array_pop($data);


        return $data;
    }
    static function getAD_PERFORMANCE_REPORT(SuspensionReason $r) {
        $body = self::getAdsArray($r);

        $response = self::request( "https://api.direct.yandex.com/json/v5/reports",
            $body
        );

        if(!$response->successful() || !$response->body()) throw new Exception(  'getReport: отчет формируется!' )    ;;

        $data = [];
        $rows = explode( "\n", $response->body());
        foreach( $rows as  $row) {
            $data[] = explode( "\t", $row)[0];
        }
        array_shift($data);
        array_shift($data);
        array_pop($data);
        array_pop($data);


        return $data;
    }



    /*
     * Не более 10 000 объявлений в одном вызове метода.
     */
    static function SuspendAd( & $adIds ) {
        $data = [ "method"=> "suspend",
            "params" => [
                "SelectionCriteria" => [
                    "Ids" =>  $adIds
                ]
            ]
        ];
         $response = self::request( "https://api.direct.yandex.com/json/v5/ads",
            $data
        );

        if( !json_decode($response->body())) throw new Exception('SuspendAd похоже не отработал так как ответ не парсится');



    }
    static function __IsApiSuspended() {
        // если время timeout не истекло
       return  Carbon::now()->diffInMinutes(Carbon::parse(self::$account->timeout),  false) > 0;
    }
    static function takeAbreak( ) {
        if(self::__IsApiSuspended(self::$account) ) return; // уже в таймауте

        // установим таймаут на час ноль пять (с запасом)
        self::$account->timeout = Carbon::now()->addHour(1)->addMinute(5)->toDateTimeString();
        return self::$account->save();

    }


    static function getBadAdsArray( ) {
        // TODO переделать LAST_30_DAYS_ на последовательную обработку , например обработали предпоследнюю неделю, больше ее обрабатывать не надо.
        $reportName = 'BounceRate80+Conversions0+20Impressions+LAST_30_DAYS_' . Carbon::now()->toDateString();
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
                                    3 =>
                                        array (
                                            'Field' => 'Clicks',
                                            'Operator' => 'GREATER_THAN',
                                            'Values' =>
                                                array (
                                                    0 => '0',
                                                ),
                                        ),
                                ),
                        ),
                    'FieldNames' =>
                        array (
                            0 => 'AdId',
                            /*              1 => 'Impressions',
                                          2 => 'CampaignId',
                                          3 => 'Clicks',
                                          4 => 'Cost',
                                          5 => 'BounceRate',
                                          6 => 'Conversions',*/
                        ),
                    'ReportName' => $reportName,
                    'ReportType' => 'AD_PERFORMANCE_REPORT',
                    'Format' => 'TSV',
                    'IncludeVAT' => 'YES',
                    'IncludeDiscount' => 'YES',
                    'DateRangeType' => 'LAST_30_DAYS',
                ),
        );

    }
    static function getAdsArray(SuspensionReason $r) {
        $reportName =  $r->report_name . '_' . $r->DateRangeType . '_' . Carbon::now()->toDateString();

        $filter =  json_decode($r->report_conditions_json);
        if(!$filter) throw new Exception('report_conditions_json is not parsable');

        return array (
            'params' =>
                array (
                    'SelectionCriteria' =>
                        array (
                            'Filter' =>
                                $filter,
                        ),
                    'FieldNames' =>
                        array (
                            0 => 'AdId',
                            /*              1 => 'Impressions',
                                          2 => 'CampaignId',
                                          3 => 'Clicks',
                                          4 => 'Cost',
                                          5 => 'BounceRate',
                                          6 => 'Conversions',*/
                        ),
                    'ReportName' => $reportName,
                    'ReportType' => 'AD_PERFORMANCE_REPORT',
                    'Format' => 'TSV',
                    'IncludeVAT' => 'YES',
                    'IncludeDiscount' => 'YES',
                    'DateRangeType' => $r->DateRangeType,
                ),
        );

    }


    private static function request($url,  &$body ) {
        if( self::__IsApiSuspended( ) ) throw new Exception('account temporarily suspended');

        $response =  Http::withToken( self::$account->api_key  )
            ->withHeaders([
                // 'X-First' => 'foo',
            ])
            ->withBody(
                json_encode( $body ), "application/json"
            )
            /*            ->withOptions([
                           'debug' => false, 'verify' => false,
                       ])*/
            ->post($url)
            ->throw();  // TODO обработка ошибок "{"error":{"error_detail":"JSON can't be processed","error_string":"Invalid request","request_id":"1264458033037596430","error_code":"8000"}}"

        Log::channel('chrono')->info('запрос выполнен: ' . $url  );

        /*        dd(

                    $response->headers(),
                    $response->body()
                );*/

        self::Checkin($response );

            return $response;


    }












}
