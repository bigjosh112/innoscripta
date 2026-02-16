<?php

namespace App\Console\Commands;

use App\Services\EmployeeEventProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

class PullFromRabbitMQCommand extends Command
{
    protected $signature = 'rabbitmq:pull
                            {queue? : Queue name (default from config)}
                            {--once : Process one message then exit}
                            {--exchange= : Exchange name (default from config)}
                            {--routing-key= : Routing key (default: employee.#)}
                            {--limit= : Max messages to process}';

    protected $description = 'Pull message(s) from RabbitMQ, process, then exit (use --once for one message)';

    public function __construct(
        private readonly EmployeeEventProcessor $eventProcessor
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $queue = $this->argument('queue') ?? config('services.rabbitmq.queue');
        $exchange = $this->option('exchange') ?: config('services.rabbitmq.exchange');
        $routingKey = $this->option('routing-key') ?: 'employee.#';
        $once = $this->option('once');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $host = config('services.rabbitmq.host');
        $port = config('services.rabbitmq.port');
        $user = config('services.rabbitmq.user');
        $password = config('services.rabbitmq.password');
        $vhost = config('services.rabbitmq.vhost');

        $this->info("Connecting to RabbitMQ at {$host}:{$port}...");

        $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $channel = $connection->channel();

        $channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);
        $channel->queue_declare($queue, false, true, false, false);
        $channel->queue_bind($queue, $exchange, $routingKey);

        $this->info("Pulling from queue {$queue} (exchange: {$exchange}, routing: {$routingKey})...");

        $processed = 0;
        $max = $once ? 1 : ($limit ?? 0);

        while (true) {
            $msg = $channel->basic_get($queue);
            if ($msg === null) {
                $this->line('No more messages.');
                break;
            }

            $body = json_decode($msg->body, true);
            if (!is_array($body)) {
                $this->warn('Invalid JSON, nacking.');
                $msg->nack(true);
                continue;
            }

            $eventType = $body['event_type'] ?? null;
            $country = $body['country'] ?? null;
            if (!$eventType || !$country) {
                $this->warn('Invalid payload: missing event_type or country');
                $msg->ack();
                $processed++;
                if ($max && $processed >= $max) {
                    break;
                }
                continue;
            }

            $this->info("Processing {$eventType} for country {$country}");
            try {
                $this->eventProcessor->process($body);
                $this->line("Invalidated cache for country: {$country}");
            } catch (\Throwable $e) {
                Log::error('Event processing failed', ['payload' => $body, 'exception' => $e->getMessage()]);
                $this->error('Processing failed: ' . $e->getMessage());
                $msg->nack(true);
                continue;
            }
            $msg->ack();
            $processed++;

            if ($max && $processed >= $max) {
                break;
            }
        }

        $channel->close();
        $connection->close();

        $this->info("Processed {$processed} message(s).");
        return 0;
    }
}
