# Hub Service – Deep Dive (Interview Prep)

This document walks through the hub-service so you can explain it clearly to a tech lead: **what it does**, **how data flows**, and **where the important code lives**.

---

## 1. What is the Hub Service?

**One sentence:** The hub is the **central orchestration layer** that does not own employee data; it **consumes events** from the HR service via RabbitMQ, **caches** data from the HR service, **validates** country-specific checklists, and **exposes server-driven UI APIs** (checklists, steps, employees, schema) and **real-time updates** via WebSockets (Pusher).

**Responsibilities:**

- **Read path:** Serve API requests for checklists, steps, employee lists, and widget schema. Data comes from HR service; hub caches it (Redis) and applies country-specific rules (columns, SSN masking, checklist rules).
- **Event path:** Consume employee create/update/delete events from RabbitMQ → invalidate cache for that country → broadcast so UIs can refetch or update.
- **Server-driven UI:** Steps and schema APIs tell the frontend which screens to show (e.g. USA: Dashboard + Employees; Germany: + Documentation) and which widgets to render and which API/channel each uses.

---

## 2. High-Level Data Flow

```
[HR Service]  -->  REST (employee CRUD)  -->  [PostgreSQL]
       |
       v  (on create/update/delete)
[RabbitMQ]  -->  employee.* events
       |
       v
[Hub: Consumer]  -->  process event  -->  invalidate cache (checklist + employees for country)
       |                                    |
       v                                    v
  broadcast (Pusher)              [Redis] keys removed
  ChecklistUpdated, EmployeeDataUpdated

[Client]  -->  GET /api/checklists?country=USA  -->  [Hub]
                                                    cache hit?  --> return
                                                    cache miss? --> GET HR /api/employees --> compute checklist --> cache --> return
[Client]  -->  GET /api/employees?country=USA&page=1  -->  [Hub]
                                                            cache hit?  --> return
                                                            cache miss? --> GET HR /api/employees --> transform (columns, mask SSN) --> cache --> return
```

- **Writes** happen only in HR service (and HR publishes to RabbitMQ).
- **Hub never writes to HR’s DB.** It only reads via HTTP and manages its own cache.
- **Cache invalidation** is by country: one employee change in USA invalidates all checklist and employee list cache entries for USA, not for Germany.

---

## 3. API Layer (Routes & Controllers)

**File:** `routes/api.php`  
Laravel prefixes these with `/api`, so the actual paths are `/api/checklists`, `/api/steps`, etc.

| Method + Path | Controller | Purpose |
|---------------|------------|--------|
| GET /api/checklists?country= | ChecklistController::index | Checklist stats (overall + per employee) for a country |
| GET /api/steps?country= | StepsController::index | Navigation steps (Dashboard, Employees, Documentation for DE) |
| GET /api/employees?country=&page=&per_page= | EmployeeController::index | Paginated employee list, country-specific columns |
| GET /api/employees/{id}?country= | EmployeeController::show | Single employee + column definition |
| GET /api/schema/{step_id}?country= | SchemaController::show | Widget config for a step (data_source URL + channel for real-time) |
| GET /api/broadcasting/config | Closure | Public Pusher config for frontend (key, cluster, etc.) |

**Design points to mention:**

- **Country is always required** (query param). Validation is in Form Requests for employees (`IndexEmployeesRequest`, `ShowEmployeeRequest`); checklists/steps/schema use manual checks and return 422 when missing.
- **Controllers are thin:** they validate input, call services (or cache), and return JSON. Business logic lives in services (ChecklistValidator, StepsConfig, SchemaConfig, HrServiceClient).

---

## 4. Controllers in Detail

### ChecklistController (`app/Http/Controllers/ChecklistController.php`)

- **Input:** `country` (query).
- **Flow:**  
  - Cache key: `checklist:country:{country}`, TTL 60 seconds.  
  - On cache miss: `HrServiceClient::getAllEmployees($country)` (paginates through HR API until all employees are fetched), then for each employee runs `ChecklistValidator::validateEmployee()`, builds `overall` (total, complete, percentage) and `employees[]` (id, name, checklist fields, completion_percentage, complete).  
  - Result is stored in cache and returned.
- **Response shape:** `{ overall: { total, complete, percentage }, employees: [ { id, name, checklist: [ { field, label, complete, message } ], completion_percentage, complete } ] }`.

### EmployeeController (`app/Http/Controllers/EmployeeController.php`)

- **index:**  
  - Cache key: `employees:{country}:{page}:{perPage}`, TTL 300 seconds.  
  - On miss: calls `HrServiceClient::getEmployees($country, $page, $perPage)`, then `getColumnsForCountry($country)` and `transformEmployee()` for each (USA: mask SSN; Germany: include goal).  
  - Returns `{ data, meta, columns }`.
- **show:**  
  - Cache key: `employees:{country}:{id}`, TTL 60 seconds.  
  - On miss: `HrServiceClient::getEmployee($id, $country)`, same column/transform logic.  
  - Returns `{ data, columns }`.
- **Country-specific columns:**  
  - USA: name, last_name, salary, ssn_masked.  
  - Germany: name, last_name, salary, goal.  
  SSN is masked as `***-**-XXXX` (last 4 digits).

### StepsController (`app/Http/Controllers/StepsController.php`)

- Uses `StepsConfig::getSteps($country)`. No cache (config-driven, cheap).
- USA: Dashboard, Employees. Germany: Dashboard, Employees, Documentation. Each step has id, label, path, order, icon.

### SchemaController (`app/Http/Controllers/SchemaController.php`)

- Uses `SchemaConfig::getSchema($step_id, $country)`. No cache.
- Returns widget definitions: id, type, title, `data_source` (e.g. `/api/checklists?country=USA`), `channel` (e.g. `checklist.USA`) for real-time. USA dashboard: employee count, average salary, completion rate. Germany dashboard: employee count, goal tracking. Employees step: table with data_source `/api/employees?country=...`.

---

## 5. Services (Where the Logic Lives)

### HrServiceClient (`app/Services/HrServiceClient.php`)

- **Injected as singleton** (see `AppServiceProvider`: `HrServiceClient::class` built from `config('services.hr_service.url')`).
- **Methods:**  
  - `getEmployees($country, $page, $perPage)` → `{ data, meta }` from HR.  
  - `getEmployee($id, $country)` → single employee.  
  - `getAllEmployees($country)` → loops pages (100 per page) and merges into one array (used by checklist).
- **Base URL:** From `config/services.php` → `HR_SERVICE_URL` (e.g. `http://hr-service:8000` in Docker, `http://localhost:8001` locally).

### ChecklistValidator (`app/Services/Checklist/ChecklistValidator.php`)

- **Pure PHP**, no Laravel (easy to unit test).
- **validateEmployee(array $employee, string $country):**  
  - **USA:** ssn (non-empty), salary > 0, address (non-empty).  
  - **Germany:** salary > 0, goal (non-empty), tax_id (regex `^DE\d{9}$`).  
  - Returns `{ complete, fields: [ { field, label, complete, message } ], completion_percentage }`.
- **normalizeCountry:** USA; DE/DEU/Germany → Germany.

### StepsConfig (`app/Services/Steps/StepsConfig.php`)

- Returns array of steps per country (id, label, path, order, icon). USA vs Germany differ only by Documentation step for Germany.

### SchemaConfig (`app/Services/Schema/SchemaConfig.php`)

- Returns widgets per `step_id` and country. Each widget has `data_source` (API URL) and `channel` (Pusher channel name) so the frontend knows what to fetch and what to subscribe to for live updates.

---

## 6. Caching Strategy

- **Driver:** Configurable; production uses Redis (`CACHE_STORE=redis`). Keys can be prefixed (`config('cache.prefix')`).
- **Keys:**  
  - Checklist: `checklist:country:{country}` (TTL 60).  
  - Employee list: `employees:{country}:{page}:{perPage}` (TTL 300).  
  - Employee single: `employees:{country}:{id}` (TTL 60).
- **Pattern:** Cache-aside. On read: if key exists, return; else fetch from HR, compute/transform, `Cache::remember()`, return.  
- **Invalidation:** Only when events are processed (see below). We do **not** update cache in place; we **forget** keys so the next request recomputes. That keeps logic simple and avoids subtle stale data.

**Redis-specific invalidation** (in consumer): For `employees:{country}:*` we use Redis `KEYS` (or similar) with the app prefix, then `DEL` each. If the cache driver is not Redis, we only forget `checklist:country:{country}`; employee list keys then expire by TTL.

---

## 7. RabbitMQ Consumer (Event Path)

**Command:** `php artisan rabbitmq:consume-employee-events`  
**File:** `app/Console/Commands/ConsumeEmployeeEventsCommand.php`

- **Connection:** Uses `config('services.rabbitmq')` (host, port, user, password, vhost, exchange, queue). Queue name from config is typically `hub.employee.events` (see `config/services.php`: `RABBITMQ_QUEUE`).
- **Setup:** Declares topic exchange `hr.events`, declares queue, binds with routing key `employee.#`. So all employee.* events from HR land in this queue.
- **Consumption:** `basic_qos(1)` (prefetch 1), then `basic_consume` with a callback that:  
  1. Decodes JSON body.  
  2. Calls `processEvent($body)`.  
  3. `$msg->ack()`.
- **processEvent:**  
  - Reads `event_type` and `country`; if missing, logs and returns.  
  - Calls `invalidateCache($country)` (forget checklist key + delete Redis keys matching `employees:{country}:*`).  
  - Calls `broadcastUpdates($country, $eventType, $payload['data'])` → dispatches `ChecklistUpdated` and `EmployeeDataUpdated`.
- **Long-lived:** The command runs in a loop (`while ($channel->is_consuming()) { $channel->wait(); }`), so it stays running and processes messages as they arrive.

**Note:** `routes/console.php` schedules `rabbitmq:pull employee_events ...`. If there is no `rabbitmq:pull` command registered in this codebase, that schedule entry would fail. The **implemented** way to process events is the long-lived consumer: `rabbitmq:consume-employee-events`.

---

## 8. Cache Invalidation (Exact Code Path)

In `ConsumeEmployeeEventsCommand`:

1. **Checklist:** `$cache->forget("checklist:country:{$country}");`
2. **Employee keys (Redis only):**  
   - Get Redis from cache store.  
   - Pattern: `{prefix}employees:{country}:*`.  
   - `keys()` then `del()` each.  
   So all list and single-employee caches for that country are removed; next API request will hit HR and repopulate cache.

---

## 9. Broadcasting (Real-Time)

- **Config:** `config/broadcasting.php` — default connection from `BROADCAST_CONNECTION` (e.g. `pusher`). Pusher needs `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_ID`, `PUSHER_APP_CLUSTER`.
- **When:** Right after invalidating cache in the consumer, we dispatch:
  - **ChecklistUpdated($country, $eventType)** → channel `checklist.{country}` (e.g. `checklist.USA`). Payload: country, event_type, message.
  - **EmployeeDataUpdated($country, $eventType, $data)** → channel `employees.{country}`. Payload: country, event_type, data (from event), message.
- **Events:** Both implement `ShouldBroadcastNow` (sync dispatch, no queue). `broadcastOn()` returns a `Channel`; `broadcastAs()` gives the event name the frontend listens to; `broadcastWith()` is the payload.
- **Frontend:** Can subscribe to `checklist.USA`, `employees.Germany`, etc., and on event refetch the corresponding API (e.g. `/api/checklists?country=USA`) or update UI. The schema API tells the frontend which channel each widget uses.

---

## 10. Configuration & Environment

- **Hub ↔ HR:** `HR_SERVICE_URL` (e.g. `http://hr-service:8000` in Docker).
- **RabbitMQ:** `RABBITMQ_HOST`, `RABBITMQ_PORT`, `RABBITMQ_USER`, `RABBITMQ_PASSWORD`, `RABBITMQ_VHOST`, `RABBITMQ_EXCHANGE` (e.g. `hr.events`), `RABBITMQ_QUEUE` (e.g. `hub.employee.events`).
- **Cache:** `CACHE_STORE=redis`; Redis connection from Laravel’s `config/database.php` / Redis config.
- **Broadcasting:** `BROADCAST_CONNECTION=pusher`, then `PUSHER_APP_*` vars.

---

## 11. Quick Reference: Where Is X?

| Topic | File(s) |
|-------|--------|
| API routes | `routes/api.php` |
| Checklist API + cache | `app/Http/Controllers/ChecklistController.php` |
| Employee API + cache + columns/SSN | `app/Http/Controllers/EmployeeController.php` |
| Steps API | `app/Http/Controllers/StepsController.php`, `app/Services/Steps/StepsConfig.php` |
| Schema API | `app/Http/Controllers/SchemaController.php`, `app/Services/Schema/SchemaConfig.php` |
| HR HTTP client | `app/Services/HrServiceClient.php` |
| Checklist rules (USA/Germany) | `app/Services/Checklist/ChecklistValidator.php` |
| RabbitMQ consumer | `app/Console/Commands/ConsumeEmployeeEventsCommand.php` |
| Cache invalidation | Same command: `invalidateCache()` |
| Broadcast events | `app/Events/ChecklistUpdated.php`, `app/Events/EmployeeDataUpdated.php` |
| Pusher config | `config/broadcasting.php` |
| HR URL, RabbitMQ, queue name | `config/services.php` |
| Form validation (employees) | `app/Http/Requests/IndexEmployeesRequest.php`, `ShowEmployeeRequest.php` |

---

## 12. Interview-Style Talking Points

- **“How does the hub get employee data?”**  
  It doesn’t store employees. It calls the HR service over HTTP (HrServiceClient). Responses are cached in Redis by country/page so we don’t hit HR on every request. Cache is invalidated when we process RabbitMQ events for that country.

- **“Why invalidate instead of updating cache?”**  
  Invalidation is simpler and avoids bugs from partial updates. We forget the key; the next request does a full refetch and recompute (e.g. checklist), then caches again. TTLs provide a safety net if an event is missed.

- **“How does the frontend know what to show for USA vs Germany?”**  
  Steps API returns different steps (Germany has Documentation). Schema API returns widgets with `data_source` (which API to call) and `channel` (which Pusher channel to subscribe to). Columns for employees differ (USA: SSN masked; Germany: goal).

- **“How is real-time implemented?”**  
  After processing an event we invalidate cache and dispatch ChecklistUpdated and EmployeeDataUpdated to Pusher channels per country. Frontend subscribes to those channels and refetches the relevant API (or updates UI) when it receives an event.

- **“Where are checklist rules defined?”**  
  In `ChecklistValidator`: USA requires ssn, salary > 0, address; Germany requires salary > 0, goal, tax_id (DE + 9 digits). Each field returns complete/incomplete and a short message for the UI.

- **“How do you scale or run the consumer?”**  
  The consumer is a long-running Artisan command. You can run one per queue for throughput; RabbitMQ distributes messages. For high availability you’d run multiple workers and ensure acks are only sent after successful processing (we ack after processEvent).

This should give you a complete mental model of the hub-service for your tech lead interview.
