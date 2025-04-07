<?php

namespace Mix8872\RabbitmqRpc;

use Illuminate\Support\Facades\Log;

use function App\Classes\config;

class Logger
{
    public static function log(string $message, string $type = 'error', ?string $debugData = null)
    {
        Log::{$type}($message);
        if ($debugData && config('app.debug')) {
            Log::error($debugData);
        }
    }
}
