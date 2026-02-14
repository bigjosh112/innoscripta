<?php

namespace App\Console\Commands;

use App\Events\ChecklistUpdated;
use App\Events\EmployeeDataUpdated;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

class ConsumeEmployeeEventsCommand extends Command
{
    protected $signature = 'rabbitmq:consume-employee-events';

    protected $description = 'Consume employee events from RabbitMQ and invalidate cache';

    public function handle(): int
    {
        $host = config('services.rabbitmq.host');
        $port = config('services.rabbitmq.port');
        $user = config('services.rabbitmq.user');
        $password = config('services.rabbitmq.password');
        $vhost = config('services.rabbitmq.vhost');
        $exchange = config('services.rabbitmq.exchange');
        $queue = config('services.rabbitmq.queue');

        $this->info("Connecting to RabbitMQ at {$host}:{$port}...");

        $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $channel = $connection->channel();

        $channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);
        $channel->queue_declare($queue, false, true, false, false);
        $channel->queue_bind($queue, $exchange, 'employee.#');

        $this->info("Listening on queue {$queue} (exchange: {$exchange}, routing: employee.#)...");

        $callback = function ($msg) {
            $body = json_decode($msg->body, true);
            $this->processEvent($body);
            $msg->ack();
        };

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();

        return 0;
    }

    private function processEvent(array $payload): void
    {
        $eventType = $payload['event_type'] ?? null;
        $country = $payload['country'] ?? null;

        if (!$eventType || !$country) {
            $this->warn('Invalid event payload: missing event_type or country');
            return;
        }

        $this->info("Processing {$eventType} for country {$country}");

        $this->invalidateCache($country);
        $this->broadcastUpdates($country, $eventType, $payload);
    }

    private function broadcastUpdates(string $country, string $eventType, array $payload): void
    {
        ChecklistUpdated::dispatch($country, $eventType);
        EmployeeDataUpdated::dispatch($country, $eventType, $payload['data'] ?? null);
    }

    private function invalidateCache(string $country): void
    {
        $cache = app('cache');
        $cache->forget("checklist:country:{$country}");

        if (config('cache.default') === 'redis') {
            try {
                $redis = $cache->getStore()->getRedis();
                $prefix = config('cache.stores.redis.prefix', '');
                $pattern = $prefix . 'employees:' . $country . ':*';
                $keys = $redis->keys($pattern);
                foreach ($keys as $key) {
                    $redis->del($key);
                }
            } catch (\Throwable) {
                // Cache will expire
            }
        }

        $this->line("Invalidated cache for country: {$country}");
    }
}
