<?php

namespace App\Console\Commands;

use App\Services\EmployeeEventProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

class ConsumeEmployeeEventsCommand extends Command
{
    protected $signature = 'rabbitmq:consume-employee-events';

    protected $description = 'Consume employee events from RabbitMQ and invalidate cache';

    public function __construct(
        private readonly EmployeeEventProcessor $eventProcessor
    ) {
        parent::__construct();
    }

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

        $processor = $this->eventProcessor;
        $callback = function ($msg) use ($processor) {
            $body = json_decode($msg->body, true);
            $eventType = $body['event_type'] ?? null;
            $country = $body['country'] ?? null;
            if (!$eventType || !$country) {
                $this->warn('Invalid event payload: missing event_type or country');
                $msg->ack();
                return;
            }
            $this->info("Processing {$eventType} for country {$country}");
            try {
                $processor->process($body);
                $this->line("Invalidated cache for country: {$country}");
                $msg->ack();
            } catch (\Throwable $e) {
                Log::error('Event processing failed', ['payload' => $body, 'exception' => $e->getMessage()]);
                $this->error('Processing failed: ' . $e->getMessage());
                $msg->nack(true);
            }
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
}
