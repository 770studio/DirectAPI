<?php

namespace App\Logging;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\RotatingFileHandler;

class CustomFilenames
{
    /**
     * Customize the given logger instance.
     *
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
             if ($handler instanceof RotatingFileHandler) {
               //  dump(444444, Auth::user()->accounts );

               //  $user_path = Storage::path( Auth::user()->id );
                // Storage::makeDirectory( $user_path  );
                 $id = Auth::user() ? Auth::user()->id : 0;
                  $handler->setFilenameFormat(   $id ."-userlog-{date}", 'Y-m-d');

                // dd(7777, 45555);

              //  setFilenameFormat(string $filenameFormat, string $dateFormat)
            }
        }
    }
}
