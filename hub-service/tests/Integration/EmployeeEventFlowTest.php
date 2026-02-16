<?php

namespace Tests\Integration;

use App\Events\ChecklistUpdated;
use App\Events\EmployeeDataUpdated;
use App\Services\EmployeeEventProcessor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Integration test: event flow from RabbitMQ payload → cache invalidation → broadcast.
 * Uses the same EmployeeEventProcessor that rabbitmq:pull and rabbitmq:consume-employee-events use.
 */
class EmployeeEventFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_processing_event_invalidates_checklist_cache(): void
    {
        $country = 'USA';
        $cacheKey = "checklist:country:{$country}";

        Cache::put($cacheKey, ['overall' => ['total' => 1], 'employees' => []], 60);
        $this->assertTrue(Cache::has($cacheKey), 'Cache should be populated before processing');

        $payload = [
            'event_type' => 'EmployeeUpdated',
            'country' => $country,
            'data' => ['id' => 1, 'name' => 'Jane'],
        ];

        app(EmployeeEventProcessor::class)->process($payload);

        $this->assertFalse(Cache::has($cacheKey), 'Checklist cache for country should be invalidated after event');
    }

    public function test_processing_event_dispatches_checklist_and_employee_broadcasts(): void
    {
        Event::fake([ChecklistUpdated::class, EmployeeDataUpdated::class]);

        $payload = [
            'event_type' => 'EmployeeCreated',
            'country' => 'Germany',
            'data' => ['id' => 2, 'name' => 'Hans', 'goal' => 'Increase sales'],
        ];

        app(EmployeeEventProcessor::class)->process($payload);

        Event::assertDispatched(ChecklistUpdated::class, function (ChecklistUpdated $event) {
            return $event->country === 'Germany' && $event->eventType === 'EmployeeCreated';
        });
        Event::assertDispatched(EmployeeDataUpdated::class, function (EmployeeDataUpdated $event) {
            return $event->country === 'Germany'
                && $event->eventType === 'EmployeeCreated'
                && $event->data === ['id' => 2, 'name' => 'Hans', 'goal' => 'Increase sales'];
        });
    }

    public function test_full_flow_cache_then_process_then_broadcast(): void
    {
        $country = 'USA';
        $cacheKey = "checklist:country:{$country}";
        Cache::put($cacheKey, ['overall' => ['total' => 2], 'employees' => []], 60);

        Event::fake([ChecklistUpdated::class, EmployeeDataUpdated::class]);

        $payload = [
            'event_type' => 'EmployeeDeleted',
            'country' => $country,
            'data' => ['id' => 1],
        ];

        app(EmployeeEventProcessor::class)->process($payload);

        $this->assertFalse(Cache::has($cacheKey));
        Event::assertDispatched(ChecklistUpdated::class);
        Event::assertDispatched(EmployeeDataUpdated::class);
    }

    public function test_invalid_payload_does_not_invalidate_cache_or_dispatch(): void
    {
        $country = 'Germany';
        $cacheKey = "checklist:country:{$country}";
        Cache::put($cacheKey, ['cached' => true], 60);

        Event::fake([ChecklistUpdated::class, EmployeeDataUpdated::class]);

        app(EmployeeEventProcessor::class)->process(['event_type' => 'EmployeeCreated']);
        app(EmployeeEventProcessor::class)->process(['country' => 'USA']);

        $this->assertTrue(Cache::has($cacheKey), 'Cache should not be invalidated when payload is invalid');
        Event::assertNotDispatched(ChecklistUpdated::class);
        Event::assertNotDispatched(EmployeeDataUpdated::class);
    }
}
