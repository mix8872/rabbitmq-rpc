<?php

namespace Mix8872\RabbitmqRpc;

use Illuminate\Support\Facades\Log;

use function App\Classes\config;

class Logger
{
    /**
     * @param string $message
     * @param string|null $debugData
     * @return void
     */
    public static function info(string $message, ?string $debugData = null): void
    {
        self::log($message, 'info', $debugData);
    }

    /**
     * @param string $message
     * @param string $type
     * @param string|null $debugData
     * @return void
     */
    public static function log(string $message, string $type = 'error', ?string $debugData = null): void
    {
        Log::{$type}($message);
        if ($debugData && \config('app.debug')) {
            Log::error($debugData);
        }
    }
}
