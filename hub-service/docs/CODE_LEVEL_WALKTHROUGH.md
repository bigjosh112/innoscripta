# Hub Service – Code-Level Walkthrough

This document walks through the **actual code** line by line so you understand exactly what runs when. It assumes you already know the architecture (see `HUB_SERVICE_DEEP_DIVE.md`).

---

## 1. Request entry: routes and dependency injection

**File: `routes/api.php`**

```php
Route::get('/checklists', [ChecklistController::class, 'index']);
Route::get('/employees', [HubEmployeeController::class, 'index']);
// ...
```

- Laravel prefixes these with `/api` (from `bootstrap/app.php` → `api: __DIR__.'/../routes/api.php'`), so the real path is `GET /api/checklists?country=USA`.
- When a request hits that route, Laravel resolves `ChecklistController` from the container. The controller’s constructor has type-hinted dependencies → Laravel injects `HrServiceClient` and `ChecklistValidator`.

**Where does `HrServiceClient` come from?**  
**File: `app/Providers/AppServiceProvider.php`**

```php
$this->app->singleton(HrServiceClient::class, function ($app) {
    return new HrServiceClient(config('services.hr_service.url'));
});
```

- Every time the container needs `HrServiceClient`, it runs this closure once and reuses the same instance (singleton).
- `config('services.hr_service.url')` reads `config/services.php` → `env('HR_SERVICE_URL', 'http://hr-service:8000')`.

**`ChecklistValidator`** has no binding, so Laravel will `new ChecklistValidator()` when needed (no constructor args).

---

## 2. ChecklistController – step by step

**File: `app/Http/Controllers/ChecklistController.php`**

### `index(Request $request)`

```php
$country = $request->query('country');
if (empty($country)) {
    return response()->json(['error' => 'country query parameter is required'], 422);
}
```

- Reads `?country=...` from the URL. If missing or empty, return 422 immediately (no cache, no HR call).

```php
$cacheKey = "checklist:country:{$country}";
$result = Cache::remember($cacheKey, 60, fn () => $this->computeChecklist($country));
return response()->json($result);
```

- **Cache::remember($key, $ttlSeconds, $callback):**
  - If the key exists in cache (Redis/file/etc.), the callback is **not** run; the cached value is returned.
  - If the key does not exist, the callback runs; its return value is stored under that key for 60 seconds and then returned.
- So the first request (or after invalidation) runs `computeChecklist($country)`; subsequent requests within 60s get the cached array.

### `computeChecklist(string $country)`

```php
$employees = $this->hrClient->getAllEmployees($country);
```

- Calls the HR service until all pages are fetched (see `HrServiceClient::getAllEmployees` below). Returns one flat array of employee arrays.

```php
$employeeChecklists = [];
$completeCount = 0;

foreach ($employees as $emp) {
    $validation = $this->validator->validateEmployee($emp, $country);
    $employeeChecklists[] = [
        'id'                   => $emp['id'] ?? null,
        'name'                 => ($emp['name'] ?? '') . ' ' . ($emp['last_name'] ?? ''),
        'checklist'            => $validation['fields'],
        'completion_percentage' => $validation['completion_percentage'],
        'complete'             => $validation['complete'],
    ];
    if ($validation['complete']) {
        $completeCount++;
    }
}
```

- For each employee we get a **validation result** from `ChecklistValidator::validateEmployee` (see below). We store:
  - `id`, full `name`, the list of **fields** (each with `field`, `label`, `complete`, `message`), **completion_percentage**, and **complete**.
- We count how many employees are fully complete (`$completeCount`).

```php
$total = count($employeeChecklists);
$overall = [
    'total'      => $total,
    'complete'   => $completeCount,
    'percentage' => $total > 0 ? (int) round(($completeCount / $total) * 100) : 0,
];

return [
    'overall'   => $overall,
    'employees' => $employeeChecklists,
];
```

- Builds the **overall** block (total employees, how many complete, percentage). Return shape is exactly what the API contract expects.

---

## 3. ChecklistValidator – how one employee is validated

**File: `app/Services/Checklist/ChecklistValidator.php`**

### `validateEmployee(array $employee, string $country)`

```php
$country = $this->normalizeCountry($country);
$fields = [];
```

- **normalizeCountry:** `USA` → `USA`; `DE` / `DEU` / `GERMANY` → `Germany`; anything else returned as-is. So we always compare against `'USA'` or `'Germany'` in the branches below.

**USA branch:**

```php
if ($country === 'USA') {
    $fields = [
        $this->checkField($employee, 'ssn', 'SSN', fn ($v) => !empty(trim((string) $v))),
        $this->checkField($employee, 'salary', 'Salary', fn ($v) => isset($v) && (float) $v > 0),
        $this->checkField($employee, 'address', 'Address', fn ($v) => !empty(trim((string) $v))),
    ];
}
```

- Three rules: **ssn** non-empty (after trim), **salary** present and > 0, **address** non-empty. Each is evaluated via `checkField`.

**Germany branch:**

```php
if ($country === 'Germany') {
    $fields = [
        $this->checkField($employee, 'salary', 'Salary', fn ($v) => isset($v) && (float) $v > 0),
        $this->checkField($employee, 'goal', 'Goal', fn ($v) => !empty(trim((string) $v))),
        $this->checkField($employee, 'tax_id', 'Tax ID', fn ($v) => preg_match('/^DE\d{9}$/', (string) $v) === 1),
    ];
}
```

- **tax_id** must match exactly `DE` plus 9 digits (e.g. `DE123456789`). No spaces or other characters.

### `checkField(array $employee, string $key, string $label, callable $valid)`

```php
$value = $employee[$key] ?? null;
$complete = $valid($value);

return [
    'field'    => $key,
    'label'    => $label,
    'complete' => $complete,
    'message'  => $complete ? "{$label} is complete" : "{$label} is required or invalid",
];
```

- Reads the value from the employee array, runs the closure. Returns one “field” object for the API: **field** name, **label**, **complete** (bool), **message** (for UI).

### Completion

```php
$complete = count(array_filter($fields, fn ($f) => $f['complete'])) === count($fields);
return [
    'complete' => $complete,
    'fields'   => $fields,
    'completion_percentage' => count($fields) > 0
        ? (int) round((count(array_filter($fields, fn ($f) => $f['complete'])) / count($fields)) * 100)
        : 0,
];
```

- **complete:** true only if every entry in `$fields` has `'complete' => true`.  
- **completion_percentage:** (number of complete fields / total fields) * 100, rounded. So for USA (3 fields), 2 complete → 66%.

---

## 4. HrServiceClient – HTTP calls to HR

**File: `app/Services/HrServiceClient.php`**

Constructor receives the base URL (e.g. `http://hr-service:8000`).

### `getEmployees(?string $country, int $page, int $perPage)`

```php
$response = Http::timeout(10)->get("{$this->baseUrl}/api/employees", [
    'country'   => $country,
    'page'      => $page,
    'per_page'  => $perPage,
]);

$response->throw();
return $response->json();
```

- Single GET to HR’s `/api/employees`. `throw()` turns 4xx/5xx into an exception. Return is the JSON decoded as array (typically `['data' => [...], 'meta' => [...]]`).

### `getEmployee(int $id, ?string $country)`

- GET `{$baseUrl}/api/employees/{$id}` with optional `?country=`. Returns `$response->json('data')` if present, else full JSON (so the controller always gets one employee array).

### `getAllEmployees(?string $country)`

```php
$employees = [];
$page = 1;

do {
    $result = $this->getEmployees($country, $page, 100);
    $data = $result['data'] ?? [];
    $employees = array_merge($employees, $data);
    $meta = $result['meta'] ?? [];
    $lastPage = $meta['last_page'] ?? 1;
    $page++;
} while ($page <= $lastPage);

return $employees;
```

- Paginates with page size 100 until `meta.last_page` is reached. Merges all `data` arrays into one and returns that. Used only by the checklist so we have the full list to validate.

---

## 5. EmployeeController – list and show

**File: `app/Http/Controllers/EmployeeController.php`**

### `index(IndexEmployeesRequest $request)`

- **IndexEmployeesRequest** (see below) validates query params and merges them into the request. So you use `$request->validated('country')` and `$request->integer('page', 1)` etc.

```php
$country = $request->validated('country');
$page = $request->integer('page', 1);
$perPage = $request->integer('per_page', 15);
$perPage = max(1, min(100, $perPage));

$cacheKey = "employees:{$country}:{$page}:{$perPage}";
$result = Cache::remember($cacheKey, 300, function () use ($country, $page, $perPage) {
    return $this->fetchAndTransform($country, $page, $perPage);
});
return response()->json($result);
```

- Cache key includes country, page, and per_page so each paginated “page” is cached separately. TTL 300 seconds. On miss, `fetchAndTransform` calls HR, then adds columns and transforms each row (including SSN masking for USA).

### `fetchAndTransform(string $country, int $page, int $perPage)`

```php
$response = $this->hrClient->getEmployees($country, $page, $perPage);
$data = $response['data'] ?? [];
$meta = $response['meta'] ?? [];

$columns = $this->getColumnsForCountry($country);
$transformed = array_map(fn (array $emp) => $this->transformEmployee($emp, $country), $data);

return [
    'data'       => $transformed,
    'meta'       => $meta,
    'columns'    => $columns,
];
```

- One HR call for this page. **getColumnsForCountry** returns the column definitions (USA: name, last_name, salary, ssn_masked; Germany: name, last_name, salary, goal). **transformEmployee** builds each row and for USA calls **maskSsn**.

### `getColumnsForCountry(string $country)`

```php
$normalized = strtoupper($country) === 'USA' ? 'USA' : 'Germany';
return match ($normalized) {
    'USA' => [ ['key' => 'name', ...], ['key' => 'last_name', ...], ['key' => 'salary', ...], ['key' => 'ssn_masked', 'label' => 'SSN', ...] ],
    'Germany' => [ ..., ['key' => 'goal', ...] ],
    default => [ name, last_name, salary only ],
};
```

- So any non-USA country is treated as Germany for columns (and for transform). Default is a safe subset.

### `transformEmployee(array $emp, string $country)`

- Builds a base array: `id`, `name`, `last_name`, `salary`, `country`.  
- If USA: adds `ssn_masked` from `maskSsn($emp['ssn'] ?? '')`.  
- If Germany: adds `goal`.  
- So the API never returns raw `ssn`; only `ssn_masked` for USA.

### `maskSsn(string $ssn)`

```php
$digits = preg_replace('/\D/', '', $ssn);
if (strlen($digits) < 4) {
    return '***-**-****';
}
return '***-**-' . substr($digits, -4);
```

- Strips non-digits. If we have fewer than 4 digits, return a fixed mask. Otherwise show only last 4 digits (e.g. `123-45-6789` → `***-**-6789`).

### `show(ShowEmployeeRequest $request, int $id)`

- Cache key `employees:{country}:{id}`, TTL 60. On miss: `getEmployee($id, $country)`, then same columns + `transformEmployee`, return `{ data, columns }`.

---

## 6. Form request: IndexEmployeesRequest

**File: `app/Http/Requests/IndexEmployeesRequest.php`**

```php
public function rules(): array
{
    return [
        'country'  => ['required', 'string', 'min:1'],
        'page'     => ['sometimes', 'integer', 'min:1'],
        'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
    ];
}

protected function prepareForValidation(): void
{
    $this->merge([
        'country'  => $this->query('country'),
        'page'     => $this->query('page'),
        'per_page' => $this->query('per_page'),
    ]);
}
```

- **prepareForValidation** copies query params into the request body so Laravel’s validator sees them (validation runs on the merged request).
- **country** is required; **page** and **per_page** optional. If validation fails, Laravel returns 422 with error messages (handled by your exception handler in `bootstrap/app.php`).

---

## 7. StepsController and SchemaController (short)

**StepsController:** Gets `country` from query, returns `StepsConfig::getSteps($country)`. No cache.  
**StepsConfig::getSteps:** `normalizeCountry` then `match`: USA returns 2 steps (dashboard, employees), Germany 3 (dashboard, employees, documentation). Each step: `id`, `label`, `path`, `order`, `icon`.

**SchemaController:** Gets `step_id` and `country`, returns `SchemaConfig::getSchema($step_id, $country)`.  
**SchemaConfig::getSchema:** For `dashboard`, USA vs Germany get different widgets (USA: employee count, average salary, completion rate; Germany: employee count, goal tracking). Each widget has `id`, `type`, `title`, `data_source` (API URL), `channel` (e.g. `checklist.USA`). For step `employees`, one table widget with `data_source` and `channel` for that country. So the frontend knows which API to call and which Pusher channel to subscribe to.

---

## 8. RabbitMQ consumer – ConsumeEmployeeEventsCommand

**File: `app/Console/Commands/ConsumeEmployeeEventsCommand.php`**

### `handle()`

```php
$host = config('services.rabbitmq.host');
// ... port, user, password, vhost, exchange, queue
$connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
$channel = $connection->channel();
```

- Reads all RabbitMQ settings from `config/services.php` (env-driven). Creates a single connection and one channel.

```php
$channel->exchange_declare($exchange, AMQPExchangeType::TOPIC, false, true, false);
$channel->queue_declare($queue, false, true, false, false);
$channel->queue_bind($queue, $exchange, 'employee.#');
```

- **exchange_declare:** Topic exchange named e.g. `hr.events` (third param `true` = durable).  
- **queue_declare:** Queue name from config (e.g. `hub.employee.events`), durable.  
- **queue_bind:** This queue receives all messages whose routing key matches `employee.#` (e.g. `employee.created`, `employee.updated`).

```php
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
```

- **Callback:** Decode JSON body → `processEvent` → then **ack** so RabbitMQ removes the message. If we didn’t ack, the message would be redelivered.  
- **basic_qos(1):** Process one message at a time (prefetch 1).  
- **basic_consume:** Start consuming; the last `false` means “no ack” is not used (we ack manually).  
- **while / wait:** Blocks and waits for messages until the process is killed. So this command is long-lived.

### `processEvent(array $payload)`

```php
$eventType = $payload['event_type'] ?? null;
$country = $payload['country'] ?? null;

if (!$eventType || !$country) {
    $this->warn('Invalid event payload: missing event_type or country');
    return;
}
$this->info("Processing {$eventType} for country {$country}");

$this->invalidateCache($country);
$this->broadcastUpdates($country, $eventType, $payload);
```

- We only need **event_type** and **country** to decide what to invalidate and what to broadcast. Invalidating by country clears all checklist and employee cache for that country. Then we broadcast so UIs can refetch.

### `invalidateCache(string $country)`

```php
$cache = app('cache');
$cache->forget("checklist:country:{$country}");
```

- Removes the checklist cache entry for this country. Next GET /api/checklists for that country will call `computeChecklist` again.

```php
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
```

- Laravel’s Redis cache store prefixes keys. We get the underlying Redis client and the prefix (if any). Pattern `employees:USA:*` matches list keys like `employees:USA:1:15` and single keys like `employees:USA:42`. We **keys()** then **del()** each. So all employee-related cache for that country is gone; next request will hit HR and repopulate. If Redis isn’t used or something throws, we only forget the checklist key; employee keys will expire by TTL.

### `broadcastUpdates(string $country, string $eventType, array $payload)`

```php
ChecklistUpdated::dispatch($country, $eventType);
EmployeeDataUpdated::dispatch($country, $eventType, $payload['data'] ?? null);
```

- **dispatch** runs the event immediately (sync). Both events implement **ShouldBroadcastNow**, so Laravel serializes them and sends to Pusher right away (no queue).  
- **ChecklistUpdated:** tells subscribers on `checklist.{country}` that checklist data was invalidated.  
- **EmployeeDataUpdated:** sends the same plus optional `data` (e.g. employee payload from the event) on `employees.{country}` so the frontend can show “Employee X created” or refetch the list.

---

## 9. Broadcast events – what gets sent to Pusher

**File: `app/Events/ChecklistUpdated.php`**

- **broadcastOn():** `[ new Channel('checklist.' . $this->country) ]` → channel name e.g. `checklist.USA`.  
- **broadcastAs():** `'ChecklistUpdated'` → event name the frontend listens for.  
- **broadcastWith():** `country`, `event_type`, `message`. So the client knows which country and can refetch `/api/checklists?country=USA`.

**File: `app/Events/EmployeeDataUpdated.php`**

- **broadcastOn():** `Channel('employees.' . $this->country)` → e.g. `employees.Germany`.  
- **broadcastAs():** `'EmployeeDataUpdated'`.  
- **broadcastWith():** `country`, `event_type`, `data` (the optional payload from the RabbitMQ message), `message`. Frontend can refetch `/api/employees?country=...` or use `data` to update UI.

Both use **ShouldBroadcastNow** so broadcasting happens in the same process (no queue). The Pusher driver uses config from `config/broadcasting.php` (key, secret, cluster, etc. from env).

---

## 10. Call flow summary

**GET /api/checklists?country=USA**

1. Route → `ChecklistController::index`.
2. Read `country`; if empty → 422.
3. `Cache::remember('checklist:country:USA', 60, fn => computeChecklist('USA'))`.
4. On miss: `hrClient->getAllEmployees('USA')` → paginate HR until all employees.
5. For each employee: `validator->validateEmployee($emp, 'USA')` → USA rules (ssn, salary, address) → list of fields + complete + percentage.
6. Build `overall` and `employees`, cache and return.

**GET /api/employees?country=USA&page=1&per_page=15**

1. Route → `EmployeeController::index`.
2. `IndexEmployeesRequest` validates query; 422 if country missing.
3. `Cache::remember('employees:USA:1:15', 300, fn => fetchAndTransform(...))`.
4. On miss: `hrClient->getEmployees('USA', 1, 15)` → get columns for USA → transform each row (mask SSN) → return `{ data, meta, columns }`, cache and return.

**RabbitMQ message `{ "event_type": "EmployeeCreated", "country": "USA", "data": { ... } }`**

1. Callback receives message, decodes JSON, calls `processEvent($body)`.
2. `invalidateCache('USA')`: forget `checklist:country:USA`, delete Redis keys matching `*employees:USA:*`.
3. `broadcastUpdates('USA', 'EmployeeCreated', $body)`: dispatch `ChecklistUpdated` and `EmployeeDataUpdated` → Pusher sends to `checklist.USA` and `employees.USA`.
4. `$msg->ack()`.
5. Loop waits for next message.

---

## 11. Config values that matter (code-level)

- **HR base URL:** `config('services.hr_service.url')` → `AppServiceProvider` → singleton `HrServiceClient`.
- **RabbitMQ:** All from `config('services.rabbitmq')` in the consumer (host, port, user, password, vhost, exchange, queue).
- **Cache driver:** `config('cache.default')` — must be `redis` for the employee-keys invalidation block to run. Prefix for Redis: `config('cache.stores.redis.prefix', '')` or the global `config('cache.prefix')` depending on Laravel version; the code uses the store prefix so keys match what Laravel wrote.
- **Broadcasting:** Default connection from `config('broadcasting.default')`; Pusher connection from `config('broadcasting.connections.pusher')` (key, secret, app_id, options).

Once you can trace these paths in the code, you understand the hub at code level. Use this doc next to the repo and step through with the debugger or logs if you want to see it run live.
