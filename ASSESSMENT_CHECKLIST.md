# Senior Backend Engineer Challenge – Requirements Checklist

This checklist maps each requirement from the assessment PDF to the codebase. **Done** = implemented; **Missing** = not found.

---

## 1. HR Service (Microservice)

| Requirement | Status | Where |
|-------------|--------|--------|
| REST endpoints for employee CRUD | Done | `hr-service/routes/api.php` – `Route::apiResource('employees', ...)` |
| Publish events to RabbitMQ when data changes | Done | `hr-service/app/Http/Observers/EmployeeObserver.php` |
| Database schema for employees | Done | `hr-service/database/migrations/` (employees table) |
| Country-specific fields: USA (ssn, address), Germany (goal, tax_id) | Done | Migrations + `hr-service/app/Models/Employee.php`, validation in Store/Update requests |
| Events: EmployeeCreated, EmployeeUpdated, EmployeeDeleted | Done | `hr-service/app/Enums/EmployeeEventTypeEnum.php`, used in EmployeeObserver |

---

## 2. HubService – Feature 1: Checklist System

| Requirement | Status | Where |
|-------------|--------|--------|
| GET /api/checklists?country= | Done | `hub-service/routes/api.php`, `ChecklistController::index` |
| Overall completion statistics | Done | `ChecklistController::computeChecklist` – `overall.total`, `complete`, `percentage` |
| Per-employee checklist status | Done | `overall` + `employees[].checklist`, `completion_percentage`, `complete` |
| Fields complete vs incomplete + actionable messages | Done | `ChecklistValidator` – each field has `message` (e.g. "SSN is required or invalid") |
| USA rules: ssn, salary > 0, address | Done | `hub-service/app/Services/Checklist/ChecklistValidator.php` (USA block) |
| Germany rules: salary > 0, goal, tax_id (DE + 9 digits) | Done | `ChecklistValidator.php` (Germany block), `preg_match('/^DE\d{9}$/')` |
| Cache expensive checklist calculations | Done | `ChecklistController` – `Cache::remember("checklist:country:{$country}", 60, ...)` |
| Invalidate cache when events arrive | Done | `PullFromRabbitMQCommand`, `ConsumeEmployeeEventsCommand` – `invalidateCache($country)` |
| Cache key structure / when to invalidate / which entries to clear | Done | Checklist: `checklist:country:{country}`. Employees: `employees:{country}:*`. Cleared on any create/update/delete for that country. |
| WebSocket channel naming for checklist updates | Done | Channel `checklist.{country}` (e.g. `checklist.USA`) |
| Broadcast updates after processing events and updating cache | Done | Both commands call `broadcastUpdates()` after `invalidateCache()`; dispatch `ChecklistUpdated`, `EmployeeDataUpdated`, `EmployeeUpdated` |
| Country-level and employee-level channels | Done | Country: `checklist.{country}`, `employees.{country}`. Employee: `employee.{country}.{id}` – `App\Events\EmployeeUpdated` |

---

## 3. HubService – Feature 2: Server-Driven UI APIs

| Requirement | Status | Where |
|-------------|--------|--------|
| GET /api/steps?country= | Done | `hub-service/routes/api.php`, `StepsController` |
| USA: Dashboard, Employees | Done | `hub-service/app/Services/Steps/StepsConfig.php` – USA match |
| Germany: Dashboard, Employees, Documentation | Done | `StepsConfig.php` – Germany match |
| Metadata (labels, icons, ordering, paths) | Done | `getSteps()` returns `id`, `label`, `path`, `order`, `icon` |
| GET /api/employees?country=&page=&per_page= | Done | `EmployeeController::index`, `IndexEmployeesRequest` |
| USA columns: Name, Last Name, Salary, SSN (masked) | Done | `EmployeeController::getColumnsForCountry` + `transformEmployee` (maskSsn) |
| Germany columns: Name, Last Name, Salary, Goal | Done | Same, Germany branch |
| Column definitions for frontend | Done | Response includes `columns` array with `key`, `label`, `type` |
| Pagination | Done | `page`, `per_page` (1–100), passed to HR client |
| Cache employees and invalidate on employee events | Done | `Cache::remember` in index/show; invalidation in both RabbitMQ commands |
| GET /api/schema/{step_id}?country= | Done | `SchemaController::show`, `SchemaConfig::getSchema` |
| USA dashboard widgets: Employee count, Average salary, Completion rate | Done | `SchemaConfig` – dashboard USA returns those three widgets |
| Germany dashboard: Employee count, Goal tracking | Done | `SchemaConfig` – dashboard Germany |
| Widgets: data source and real-time update channels | Done | Each widget has `data_source` and `channel` (e.g. `checklist.USA`) |
| Frontend-agnostic widget structure | Done | Generic `id`, `type`, `title`, `data_source`, `channel` |

---

## 4. Technical: RabbitMQ & Events

| Requirement | Status | Where |
|-------------|--------|--------|
| RabbitMQ in Docker Compose | Done | `docker-compose.yml` – service `rabbitmq` |
| RabbitMQ accessible from HR and Hub | Done | Both services have `RABBITMQ_HOST=rabbitmq` (and depend on it) |
| HR: event payload design, routing, publishing failures | Done | EmployeeObserver builds payload; routing (e.g. employee.#); Log::error on failure |
| Hub: consumer processes events from RabbitMQ | Done | `ConsumeEmployeeEventsCommand`, `PullFromRabbitMQCommand` |
| Consume from appropriate queue | Done | Queue from config, bind to `employee.#` |
| Route event types to handlers | Done | Single handler uses `event_type` and `country`; invalidates + broadcasts |
| Error handling and retry logic | Partial | Pull: try/catch, nack(true) on failure. Consume: no try/catch in callback, no retry – consider adding. |
| Event handlers: extract data, update/invalidate cache, broadcast, log | Done | processEvent → invalidateCache → broadcastUpdates; `info($payload)` in Pull |

---

## 5. Real-Time WebSocket

| Requirement | Status | Where |
|-------------|--------|--------|
| Pusher or Soketi chosen | Done | Pusher (config in `hub-service/config/broadcasting.php`, .env) |
| Channel naming strategy (country, employee-specific) | Done | `checklist.{country}`, `employees.{country}`, `employee.{country}.{id}` |
| Broadcast after processing RabbitMQ events | Done | In both commands after invalidateCache |
| Include relevant data in broadcasts | Done | ChecklistUpdated, EmployeeDataUpdated, EmployeeUpdated – payload with country, event_type, data/message |
| Simple HTML page: connect, subscribe, display events, prove flow | Done | `hub-service/public/echo-test.html` – Pusher, USA/Germany panels, console + on-page display |

Note: WebSocket server – assessment lists “Soketi, Pusher, or Laravel WebSockets” as a 6th service. FAQ says Pusher’s free tier is fine; Pusher is external so no extra container. **Done** with Pusher.

---

## 6. Caching

| Requirement | Status | Where |
|-------------|--------|--------|
| Choose caching tech and justify in docs | Done | Redis used; `hub-service/docs/CACHING_AND_REALTIME.md` (or README) |
| Caching in Docker Compose | Done | `redis` service, Hub env `REDIS_HOST=redis`, `CACHE_STORE=redis` |
| Cache checklist data | Done | `ChecklistController` – `checklist:country:{country}` |
| Cache employee lists | Done | `EmployeeController` – `employees:{country}:{page}:{per_page}` and `employees:{country}:{id}` |
| Cache-aside, TTL, cache miss handling | Done | Cache::remember (cache-aside); TTL 60/300s; miss triggers fetch from HR |

---

## 7. Docker

| Requirement | Status | Where |
|-------------|--------|--------|
| docker-compose: Hub, HR, PostgreSQL, RabbitMQ, cache, WebSocket (or Pusher) | Done | Root `docker-compose.yml`: hub-service, hr-service, postgres, rabbitmq, redis; Pusher external |
| Services communicate; env-based config | Done | Same network; all config via env |
| One-command start: docker-compose up -d | Done | `make up` or `docker compose up -d` at repo root |

---

## 8. Code Quality & Deliverables

| Requirement | Status | Where |
|-------------|--------|--------|
| Separation of concerns, DI, SRP, naming | Done | Controllers, Services, Actions, Events used throughout |
| Form Requests for validation | Done | IndexEmployeesRequest, ShowEmployeeRequest, IndexChecklistRequest; HR Store/Update requests |
| Resource classes for API responses | Done | ChecklistResource, EmployeeChecklistResource; HR EmployeeResource |
| .env / environment config | Done | Both apps use .env; docker-compose sets env |
| README: Overview, tech stack, design decisions | Done | Root `README.md` – Overview, tech stack, design decisions |
| README: Architecture, data flow | Done | Root `README.md` – Architecture & Data Flow section with diagram |

---

## 9. Testing (Minimum)

| Requirement | Status | Where |
|-------------|--------|--------|
| Unit: checklist validation, event handlers | Done | `hub-service/tests/Unit/ChecklistValidatorTest.php` – USA/Germany complete and incomplete cases |
| Integration: event flow RabbitMQ → cache → broadcast | Missing | No integration test in hub-service (optional) |
| Feature: API endpoints return correct responses | Done | hr-service `EmployeeApiTest`; hub-service `tests/Feature/ChecklistApiTest.php` – GET /api/checklists validation and structure |

---

## Summary

- **Implemented:** HR CRUD + events, Hub checklist + steps + employees + schema, caching + invalidation, WebSocket channels + broadcasts, Docker one-command, Form Requests, Resources, Pusher + test page.
- **Gaps:**  
  1. ~~Root README.md~~ **Done** – `README.md` at repo root with Overview and Architecture.  
  2. ~~Hub unit/feature tests~~ **Done** – Unit: `ChecklistValidatorTest`; Feature: `ChecklistApiTest` for /api/checklists. Integration test for event flow still optional.  
  3. **Optional:** Retry/error handling in ConsumeEmployeeEventsCommand callback (e.g. try/catch + nack with requeue).

I have not changed or duplicated your existing code; this file only verifies requirements. Add the root README and Hub tests to fully meet the assessment.
