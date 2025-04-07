<?php

namespace Mix8872\RabbitmqRpc;

use Illuminate\Support\Facades\Validator;

class RMQRpcValidator
{
    public static function make(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'request_id' => 'string|required', // current request id
            'reply_to' => 'string|required', // queue name
            'action' => 'string|required_without:error|regex:/[a-z]+\.[a-z]+/ui',
            'attributes' => 'array',
            'error' => 'string|required_without:action', // error message on previous request
            'reply_for' => 'string|required_with:error', // previous request id
        ]);
    }
}
