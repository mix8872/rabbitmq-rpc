<?php

namespace Mix8872\RabbitmqRpc;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use NeedleProject\LaravelRabbitMq\Processor\AbstractMessageProcessor;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class CliOutputProcessor
 *
 * @codeCoverageIgnore
 */
class RMQRpcProcessor extends AbstractMessageProcessor
{
    public function processMessage(AMQPMessage $message): bool
    {
        $arData = null;
        try {
            $body = Crypt::decryptString($message->getBody());
            if (!$arData = json_decode($body, true)) {
                Logger::log("'Can't decode json from body: $body'");

                return false;
            }

            $validator = RMQRpcValidator::make($arData);

            switch (true) {
                case $validator->fails():
                    Logger::log(message: 'Incorrect RPC request: ' . $validator->errors()->toJson(), debugData: print_r($arData, true));

                    return false;
                case isset($arData['action']):
                    return $this->runAction($arData);
                case isset($arData['error']):
                    Logger::info("Previous RMQ action '{$arData['reply_for']}' returns an error: {$arData['error']}");

                    return true;
            }

            return true;
        } catch (\Exception|\Error $e) {
            $msg = 'RMQ RPC - ' . $e->getMessage();
            Logger::log(message: $msg, debugData: $e->getTraceAsString() . PHP_EOL . print_r($arData, true));
            RMQRpcPublisher::make()
                ->error($msg)
                ->replyFor($arData['request_id'])
                ->publish($arData['reply_to']);
            return false;
        }
    }

    private function runAction($arData)
    {
        [$class, $method] = explode('.', $arData['action']);

        switch (true) {
            /* формат поля action: '<псевдоним_класса>.<метод>'
             * псевдоним_класса - ключ массива в конфиге laravel_rabbitmq.rpc
             */
            case !$processors = config('laravel_rabbitmq.rpc.processors'): // если не заданы обработчики в конфиге
                throw new \Exception('нет обработчиков RPC в конфиге laravel_rabbitmq.rpc.processors');
            case !isset($processors[$class]): // если запрошенный псевдоним класса обработчика не найден в конфиге
                throw new \Exception("псевдоним класса обработчика '$class' не найден");
            case !class_exists($processors[$class]): // если псевдоним найден, но сам класс не существует
                throw new \Exception("error: Class {$processors[$class]} is not found");
            case !method_exists($processors[$class], $method): // если в классе обработчика отсутствует запрошенный метод
                throw new \Exception("метод не найден в запрошенном классе: {$arData['action']} - {$processors[$class]}::$method");
        }

        // зеркалируем метод для проверки
        $ref = new \ReflectionMethod($class, $method);
        $attributes = $arData['attributes'] ?? [];

        return $ref->isStatic() ? $class::$method(...$attributes) : (new $class)->{$method}(...$attributes);
    }
}
