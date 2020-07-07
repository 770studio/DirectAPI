<?php

namespace App\Console\Commands\YDAPI;

use App\YDAPI\YDAPI;
use Illuminate\Console\Command;

class DoJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ydapi:run';

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
    public function handle()
    {

        $api_token  = 'AgAAAAAy8yPfAAZmWSYD9z8530PRnkpZz9wbjyA';

        YDAPI::run();


    }
}
