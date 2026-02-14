# Senior Backend Engineer Challenge – Step-by-Step Breakdown

This document breaks down the assessment, maps it to your current codebase, and gives a clear implementation plan.

---

## Part 1: What the challenge asks for (high level)

You build **two Laravel services** that work together:

| Service | Role |
|--------|------|
| **HR Service** | Microservice: employee CRUD, publishes events to RabbitMQ when data changes |
| **HubService** | Central layer: consumes RabbitMQ events, caches, checklist validation, server-driven UI APIs, WebSocket broadcasts |

**Infrastructure (Docker):** HubService, HR Service, PostgreSQL, RabbitMQ, cache (e.g. Redis), WebSocket server (e.g. Soketi). All start with `docker-compose up -d`.

---

## Part 2: What you have now

### HR Service (`hr-service/` – this repo)

| Area | Status | Notes |
|------|--------|--------|
| **Employee model & DB** | Done | `Employee` with USA/Germany fields, migration with `country` index |
| **Country enum** | Done | `CountryEnum`: USA, Germany |
| **Event type enum** | Done | `EmployeeEventTypeEnum`: Created, Updated, Deleted |
| **CRUD API** | Partial | `store`, `update`, `destroy` exist; **missing `index` and `show`** for HubService to fetch employees |
| **Form requests** | Partial | `StoreEmployeeRequest` has rules; `UpdateEmployeeRequest` has empty rules; `authorize()` is `false` (should be `true` for this challenge) |
| **Event publishing** | Partial | Interface `EmployeeEventPublisher` + `RabbitMQEmployeeEventPublisher` and `PublishEmployeeEvent` exist but **do not actually send to RabbitMQ** (no client/connection); `RabbitMQEmployeeEventPublisher` uses `Str::uuid()` without `use Illuminate\Support\Str`; Create uses interface, Update/Delete use `PublishEmployeeEvent` (inconsistent) |
| **Actions** | Done | Create/Update/Delete actions call publisher; Update correctly computes `changed_fields` |
| **Docker** | Missing | No `Dockerfile` or `docker-compose` |

### HubService

- **Not present.** It must be created (new Laravel app or sibling project).

### Docker & infrastructure

- No `docker-compose.yml`, no Dockerfiles. You need one compose that runs both apps + PostgreSQL, RabbitMQ, Redis, WebSocket server.

---

## Part 3: Step-by-step implementation plan

Use this order so each step builds on the previous one.

---

### Phase A: Fix and finish HR Service

**Step A1 – Fix existing code**

- In `RabbitMQEmployeeEventPublisher`: add `use Illuminate\Support\Str` and fix `Str::uuid()` (use `(string) Str::uuid()` if you need string).
- Unify publishing: have all three actions (Create, Update, Delete) depend on `EmployeeEventPublisher` (interface), and bind `RabbitMQEmployeeEventPublisher` to that interface in `AppServiceProvider` (fix namespace: `App\Services\RabbitMQ\EmployeeEventPublisher` and `RabbitMQEmployeeEventPublisher`).
- Remove or replace `PublishEmployeeEvent` so only the RabbitMQ implementation is used.
- In Form Requests: set `authorize()` to `true` (or remove if not needed). Copy validation rules from `StoreEmployeeRequest` into `UpdateEmployeeRequest` (same fields, often `sometimes` instead of `required`).

**Step A2 – Add missing HR API endpoints**

- **GET /api/employees** – list employees (optional query: `country`, pagination). HubService will call this to build checklist and employee list.
- **GET /api/employees/{id}** – single employee (for HubService if needed).

**Step A3 – Implement real RabbitMQ publishing**

- Add a RabbitMQ client (e.g. `vladimir-yuldashev/laravel-queue-rabbitmq` or `php-amqplib`).
- Configure connection (host, port, vhost, credentials) via `.env`.
- In `RabbitMQEmployeeEventPublisher::publish()`: connect, declare exchange/queue if required, publish JSON payload, handle failures (log, optionally retry).
- Decide routing: e.g. one exchange `hr.events`, routing key `employee.{event_type}.{country}` so HubService can consume by country/type.

**Step A4 – Country-specific validation (optional but good)**

- In Store/Update requests or in a custom validator: USA require `ssn`, `address`, `salary`; Germany require `salary`, `goal`, `tax_id` (format `DE` + 9 digits). Align with the checklist rules in the PDF so HR only stores valid data per country.

---

### Phase B: Docker and one-command run

**Step B1 – One compose for everything**

Create a `docker-compose.yml` at repo root (or in a parent folder that contains both services) with:

1. **PostgreSQL** – one DB for HR Service (and later one for HubService if you use separate DBs).
2. **RabbitMQ** – with management plugin (port 15672 for UI).
3. **Redis** – for HubService cache.
4. **HR Service** – Laravel app (PHP-FPM + nginx or `php artisan serve` in Docker).
5. **HubService** – Laravel app (same idea).
6. **WebSocket server** – e.g. Soketi (Pusher-compatible) or Laravel WebSockets.

All services on the same Docker network, use env vars for hostnames (e.g. `rabbitmq`, `redis`, `postgres`).

**Step B2 – Dockerfiles**

- **HR Service:** Dockerfile that installs PHP, extensions (e.g. amqp, redis, pdo_pgsql), Composer, runs `composer install`, sets entrypoint (e.g. `php artisan serve` or nginx+php-fpm).
- **HubService:** Same stack; add Redis and AMQP extensions; ensure it can reach RabbitMQ and Redis.

**Step B3 – One-command start**

- `docker-compose up -d` starts all containers.
- Document in README: how to set `.env` for each app (DB_HOST=postgres, RABBITMQ_HOST=rabbitmq, REDIS_HOST=redis, etc.) and run migrations for both apps.

---

### Phase C: HubService – core behavior

**Step C1 – New Laravel app**

- Create HubService (e.g. `hub-service/` next to `hr-service/` or inside `innoscripta/`).
- Add same Docker network and env (RabbitMQ, Redis, PostgreSQL if you give it its own DB).

**Step C2 – Consume RabbitMQ events**

- Use the same RabbitMQ package as HR Service.
- Create a queue bound to the exchange HR uses (e.g. `hub.employee.events`).
- Consumer process: listen on that queue, decode JSON, dispatch to handlers by `event_type` (EmployeeCreated, EmployeeUpdated, EmployeeDeleted).
- Each handler: update in-memory/cache state, invalidate relevant cache keys, then broadcast (next step).

**Step C3 – Caching and checklist data**

- Use Redis (or your chosen cache) in HubService.
- **Cache keys:** e.g. `checklist:country:{country}`, `employees:country:{country}:page:{n}`, `employee:{id}`.
- **Checklist:** For each country, define rules (USA: ssn, salary > 0, address; Germany: salary > 0, goal, tax_id DE + 9 digits). Either fetch employees from HR Service HTTP API or from cache; compute completion per employee and overall stats; cache result under `checklist:country:{country}`.
- **Invalidation:** On EmployeeCreated/Updated/Deleted, invalidate `checklist:country:{country}`, `employees:country:{country}:*`, and `employee:{id}`.

**Step C4 – Checklist API**

- **GET /api/checklists?country=USA|Germany** – return overall completion stats, per-employee checklist (complete/incomplete fields, messages). Use cache; on miss, compute and store.
- Return structure: e.g. `{ "overall": { "total", "complete", "percentage" }, "employees": [ { "id", "name", "checklist": [ { "field", "complete", "message" } ], "completion_percentage" } ] }`.

---

### Phase D: HubService – server-driven UI and employees API

**Step D1 – Steps API**

- **GET /api/steps?country=USA|Germany** – return navigation steps.
  - USA: Dashboard, Employees (with labels, icons, order, paths).
  - Germany: Dashboard, Employees, Documentation.
- Structure: e.g. `[{ "id": "dashboard", "label": "Dashboard", "path": "/dashboard", "order": 1 }, ...]`.

**Step D2 – Employees list API**

- **GET /api/employees?country=...&page=&per_page=** – call HR Service (or cache), then return country-specific columns:
  - USA: name, last_name, salary, ssn (masked, e.g. ***-**-6789).
  - Germany: name, last_name, salary, goal.
- Include column definitions (key, label, type) so frontend can render generically. Paginate. Cache and invalidate on events.

**Step D3 – Schema API**

- **GET /api/schema/{step_id}?country=** – return widget config for that step.
  - Dashboard USA: widgets e.g. employee count, average salary, completion rate (with data source and WebSocket channel for updates).
  - Dashboard Germany: employee count, goal tracking widgets.
  - Design a small JSON schema (widget type, title, data_source, channel) that is frontend-agnostic.

---

### Phase E: Real-time (WebSockets)

**Step E1 – WebSocket server in Docker**

- Add Soketi (or Laravel WebSockets) to `docker-compose`. Configure HubService to use it (Pusher driver: host, port, key, secret).

**Step E2 – Channel strategy**

- Examples: `checklist.{country}`, `employees.{country}`, `employee.{id}`. Decide public vs private; for the challenge, public may be enough; if private, implement auth in HubService.

**Step E3 – Broadcast after event processing**

- In each RabbitMQ event handler (after updating cache and invalidating): broadcast to the right channel(s), e.g. `ChecklistUpdated`, `EmployeeListUpdated`, with payload the frontend needs (e.g. new checklist for country, or updated employee).

**Step E4 – Test page**

- Simple HTML + JS: connect to Soketi/Pusher, subscribe to e.g. `checklist.USA`, log or display incoming events. Proves flow: HR update → RabbitMQ → HubService → cache invalidation → broadcast → browser.

---

### Phase F: Quality and deliverables

**Step F1 – Code quality**

- Clean separation: event handlers, checklist rules, API controllers, cache keys in one place.
- Use Form Requests, Resources, and dependency injection; interfaces for HTTP client to HR Service and for cache if useful.

**Step F2 – Tests**

- **Unit:** Checklist validation rules (per country), cache key building, payload building.
- **Integration:** Publish event from HR → RabbitMQ → HubService consumer updates cache and broadcasts (mock or real RabbitMQ in test).
- **Feature:** GET /api/checklists, /api/steps, /api/employees, /api/schema/{step_id} with correct `country` and optional pagination.

**Step F3 – README**

- Overview, architecture diagram (ASCII or image), how to run `docker-compose up -d`, env vars, how to run tests. Document cache key strategy and when you invalidate.

**Step F4 – Optional video**

- Show: `docker-compose up`, call /api/checklists, update employee via HR API, see event in RabbitMQ UI, see log in HubService, see checklist update and WebSocket event in browser.

---

## Part 4: Checklist – what to do in order

- [ ] **A1** Fix RabbitMQ publisher (Str, interface, bindings, remove duplicate PublishEmployeeEvent usage).
- [ ] **A2** Add GET /api/employees (list) and GET /api/employees/{id} (show).
- [ ] **A3** Implement actual RabbitMQ publish (library + connection + publish in RabbitMQEmployeeEventPublisher).
- [ ] **A4** (Optional) Country-specific validation in HR requests.
- [ ] **B1–B3** Docker Compose + Dockerfiles + one-command up for HR, Hub, PostgreSQL, RabbitMQ, Redis, WebSocket.
- [ ] **C1** Create HubService Laravel app.
- [ ] **C2** RabbitMQ consumer and event handlers (update cache, invalidate, prepare broadcast).
- [ ] **C3** Cache strategy (keys, TTL, invalidation) and checklist computation.
- [ ] **C4** GET /api/checklists.
- [ ] **D1** GET /api/steps.
- [ ] **D2** GET /api/employees (HubService) with country columns and caching.
- [ ] **D3** GET /api/schema/{step_id}.
- [ ] **E1–E4** WebSocket server, channels, broadcast in handlers, HTML test page.
- [ ] **F1–F4** Clean architecture, unit/integration/feature tests, README, optional video.

---

## Part 5: Quick reference – event payload and APIs

**Event payload (HR → RabbitMQ):**

```json
{
  "event_type": "EmployeeUpdated",
  "event_id": "uuid",
  "timestamp": "2024-02-09T10:30:00Z",
  "country": "USA",
  "data": {
    "employee_id": 1,
    "changed_fields": ["salary"],
    "employee": { "id": 1, "name": "John", ... }
  }
}
```

**HubService APIs:**

| Endpoint | Purpose |
|----------|--------|
| GET /api/checklists?country=USA \| Germany | Checklist stats + per-employee status |
| GET /api/steps?country=... | Navigation steps (Dashboard, Employees, Documentation for DE) |
| GET /api/employees?country=...&page=&per_page= | Employee list with country-specific columns |
| GET /api/schema/{step_id}?country=... | Widget config for a step (dashboard widgets, etc.) |

**Country rules:**

- **USA:** ssn, salary > 0, address required.
- **Germany:** salary > 0, goal, tax_id (DE + 9 digits) required.

You can use this file as your master checklist and implement phase by phase. If you tell me which phase you want to do first (e.g. “fix HR Service and add Docker” or “create HubService and RabbitMQ consumer”), I can give concrete code and file changes next.
