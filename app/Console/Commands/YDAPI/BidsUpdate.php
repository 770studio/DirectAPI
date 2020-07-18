<?php

namespace App\Console\Commands\YDAPI;

use App\YDAPI\YDAPI;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BidsUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bids:update {account_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle( )
    {
        try{

            $accId = (int)$this->argument('account_id');
            YDAPI::UpdateKeywordBids( $accId );
        } catch(Exception $e) {

            dump($e->getMessage() );
            dump($e->getFile() , $e->getLine());
            dump($e->getTrace() );
            Log::error( $e->getMessage() );
        }


    }
}
