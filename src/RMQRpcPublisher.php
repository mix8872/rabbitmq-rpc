<?php

namespace Mix8872\RabbitmqRpc;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use NeedleProject\LaravelRabbitMq\PublisherInterface;
use NeedleProject\LaravelRabbitMq\Entity\ExchangeEntity;

/**
 * Класс для отправки RPC запросов через rabbitMQ
 * формат запроса:
 *      'request_id' => 'string|required', // current request id - добавляется автоматически
 *      'reply_to' => 'string|required', // route name - добавляется автоматически
 *      'action' => 'string|required_without:error|regex:/[a-z]+\.[a-z]+/ui', // формат поля action: 'псевдоним_класса.метод'
 *                                                                              псевдоним_класса - ключ массива в конфиге laravel_rabbitmq.rpc
 *      'attributes' => 'array', // массив атрибутов [ключ => значение]
 *      'error' => 'string|required_without:action', // ответ ошибкой на предыдущий запрос
 *      'reply_for' => 'string|required_with:error', // ИД предыдущего запроса, обязателен, если отвечаем ошибкой
 *
 * @property null|self $instance
 * @property ?string $publisher
 * @property string $action
 * @property string $error
 * @property array $attributes
 * @property string $replyFor
 */
class RMQRpcPublisher
{
    private static ?self $_instance = null;

    public ?ExchangeEntity $publisher = null;

    private ?string $action = null;

    private ?string $error = null;

    private ?array $attributes = null;

    private ?string $replyFor = null;

    /**
     * @throws BindingResolutionException
     */
    private function __construct(?string $publisherName)
    {
        if (! $publishers = config('laravel_rabbitmq.publishers')) {
            Log::error('RMQ: no publishers defined');
        }
        $pKeys = array_keys($publishers);

        /* Если указан публикатор, то берем его, иначе первый из конфига
         * (публикаторы указываются тут laravel_rabbitmq.publishers)
         */
        $arPublisher = $publisherName && isset($publishers[$publisherName]) ? [$publisher] : [array_pop($pKeys)];

        $this->publisher = app()->makeWith(PublisherInterface::class, $arPublisher);
    }

    /**
     * @throws BindingResolutionException
     */
    public static function make(?string $publisherName = null): self
    {
        if (! self::$_instance) {
            self::$_instance = new self($publisherName);
        }

        return self::$_instance;
    }

    /**
     * Задает имя экшена в запросе
     * @return $this
     */
    public function action(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Задает текст ошибки в запросе
     * @return $this
     */
    public function error(string $error): static
    {
        $this->error = $error;

        return $this;
    }

    /**
     * Задает атрибуты для экшена в запросе
     * @return $this
     */
    public function attributes(array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Задает ID запроса ответом на который является текущий запрос
     * @return $this
     */
    public function replyFor(string $replyFor): static
    {
        $this->replyFor = $replyFor;

        return $this;
    }

    /**
     * Собирает тело запроса и отправляет запрос в rmq
     * @param string|array $routingKey
     * @return mixed
     */
    public function publish(string|array $routingKey): mixed
    {
        $arData = [];
        $arData['request_id'] = config('app.name').'_'.time();
        $arData['reply_to'] = config('app.name');
        $this->action ? $arData['action'] = $this->action : false;
        $this->attributes ? $arData['attributes'] = $this->attributes : false;
        $this->error ? $arData['error'] = $this->error : false;
        $this->replyFor ? $arData['reply_for'] = $this->replyFor : false;

        $validator = RMQRpcValidator::make();

        if ($validator->fails()) {
            throw \Exception('RMQ publish fails: '.$validator->errors()->toJson());
        }

        $data = Crypt::encryptString(json_encode($arData));

        if (is_string($routingKey)) {
            return $this->send($arData, $data, $routingKey);
        }

        foreach ($routingKey as $key) {
            self::send($arData, $data, $key);
        }
        return true;
    }

    /**
     * @param array $arData
     * @param string $data
     * @param $routingKey
     * @return mixed
     */
    private function send(array $arData, string $data, $routingKey): mixed
    {
        if (config('app.debug')) {
            Log::info("The RMQ message was sent to route $routingKey: \n".print_r($arData, 1));
        }
        return $this->publisher->publish($data, $routingKey, ['delivery_mode' => 2]);
    }
}
