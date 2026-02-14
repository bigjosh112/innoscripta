<?php

namespace App\Console\Commands;

use App\Events\ChecklistUpdated;
use App\Events\EmployeeDataUpdated;
use App\Services\StreamConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use PhpAmqpLib\Exchange\AMQPExchangeType;

class PullFromRabbitMQCommand extends Command
{
    protected $signature = 'rabbitmq:pull
                            {queue? : Queue name (default: from config)}
                            {--exchange= : Exchange to bind queue to (default: from config)}
                            {--routing-key= : Routing key for bind (default: employee.#)}
                            {--limit= : Max number of messages to pull (default: no limit)}
                            {--once : Pull and process a single message then exit}';

    protected $description = 'Pull messages from RabbitMQ queue and process them (cache invalidation)';

    public function handle(StreamConnection $streamConnection): int
    {
        $queue = $this->argument('queue') ?? config('services.rabbitmq.queue');
        $exchange = $this->option('exchange') ?? config('services.rabbitmq.exchange');
        $routingKey = $this->option('routing-key') ?? 'employee.#';

        $limit = $this->option('once') ? 1 : ($this->option('limit') ? (int) $this->option('limit') : null);

        $this->info('Connecting to RabbitMQ...');

        $connection = $streamConnection->getConnection();
        $channel = $streamConnection->getChannel();

        $channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);
        $channel->queue_declare($queue, false, true, false, false);
        $channel->queue_bind($queue, $exchange, $routingKey);

        $this->info("Pulling from queue {$queue}..." . ($limit !== null ? " (limit: {$limit})" : ''));

        $processed = 0;

        while (true) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }

            $msg = $channel->basic_get($queue);

            if ($msg === null) {
                if ($processed === 0) {
                    $this->line('No messages in queue.');
                }
                break;
            }

            try {
                $body = json_decode($msg->body, true);
                $this->processEvent($body);
                $msg->ack();
                $processed++;
            } catch (\Throwable $e) {
                $this->error('Failed to process message: ' . $e->getMessage());
                $msg->nack(true);
                throw $e;
            }
        }

        $channel->close();
        $connection->close();

        $this->info("Done. Processed {$processed} message(s).");

        return 0;
    }

    private function processEvent(array $payload): void
    {
        $eventType = $payload['event_type'] ?? null;
        $country = $payload['country'] ?? null;

        info($payload);

        if (!$eventType || !$country) {
            $this->warn('Invalid event payload: missing event_type or country');
            return;
        }

        $this->info("Processing {$eventType} for country {$country}");

        $this->invalidateCache($country);
        $this->broadcastUpdates($country, $eventType, $payload);
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
                //
            }
        }
    }

    private function broadcastUpdates(string $country, string $eventType, array $payload): void
    {
        ChecklistUpdated::dispatch($country, $eventType);
        EmployeeDataUpdated::dispatch($country, $eventType, $payload['data'] ?? null);
    }
}   
