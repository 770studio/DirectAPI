<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // runInBackground почему то не работает, скрипт падает в самом начале
        $schedule->command('ads:clean 1')->weekdays()->twiceDaily(4, 6); // ->runInBackground(); // avtopark116.ru
        $schedule->command('bids:update 1')->weekdays()->everyFiveMinutes()->withoutOverlapping() ; // avtopark116.ru
        //TODO быстрая постановка в очередь, нужен redis


    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
