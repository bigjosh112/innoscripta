<?php

namespace App\Services;

use App\Events\ChecklistUpdated;
use App\Events\EmployeeDataUpdated;
use App\Services\Checklist\ChecklistValidator;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

class EmployeeEventProcessor
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ChecklistValidator $checklistValidator
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
        $missingFields = $this->getMissingFieldsForPayload($country, $payload);
        ChecklistUpdated::dispatch($country, $eventType, $missingFields);
        EmployeeDataUpdated::dispatch($country, $eventType, $payload['data'] ?? null);
    }

    /**
     * Validate the employee in the payload (if present) and return list of incomplete field messages for the broadcast.
     *
     * @return array<int, string>
     */
    private function getMissingFieldsForPayload(string $country, array $payload): array
    {
        $data = $payload['data'] ?? null;
        $employee = $data['employee'] ?? $data;
        if (! is_array($employee) || empty($employee)) {
            return [];
        }

        $result = $this->checklistValidator->validateEmployee($employee, $country);
        $missing = [];
        foreach ($result['fields'] ?? [] as $field) {
            if (empty($field['complete'])) {
                $missing[] = $field['message'];
            }
        }

        return $missing;
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
