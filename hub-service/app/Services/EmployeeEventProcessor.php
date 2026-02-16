<?php

namespace App\Services;

use App\Events\ChecklistUpdated;
use App\Events\EmployeeDataUpdated;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

class EmployeeEventProcessor
{
    public function __construct(
        private readonly CacheRepository $cache
    ) {}

    /**
     * Process an employee event (as received from RabbitMQ): invalidate cache for the country and broadcast updates.
     */
    public function process(array $payload): void
    {
        $eventType = $payload['event_type'] ?? null;
        $country = $payload['country'] ?? null;

        if (!$eventType || !$country) {
            return;
        }

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
        $this->cache->forget("checklist:country:{$country}");

        if (config('cache.default') === 'redis') {
            $store = $this->cache->getStore();
            if (method_exists($store, 'getRedis')) {
                try {
                    $redis = $store->getRedis();
                    $prefix = config('cache.stores.redis.prefix', '');
                    $pattern = $prefix . 'employees:' . $country . ':*';
                    $keys = $redis->keys($pattern);
                    foreach ($keys as $key) {
                        $redis->del($key);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Redis employee cache invalidation failed', ['country' => $country, 'exception' => $e->getMessage()]);
                }
            }
        }
    }
}
