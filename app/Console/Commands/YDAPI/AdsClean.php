<?php

namespace App\Console\Commands\YDAPI;

use App\YDAPI\Account;
use App\YDAPI\YDAPI;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdsClean extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ads:clean {account_id}';

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
            $account = Account::find($accId);
            Auth::loginUsingId($account->user_id );

            YDAPI::AdsCleaning( $account );
        } catch(Exception $e) {

            dump($e->getMessage() );
            dump($e->getFile() , $e->getLine());
           // dump($e->getTrace() );
            Log::channel('daily')->error( $e->getMessage() );
        }


    }
}
