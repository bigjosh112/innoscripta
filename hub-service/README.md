# Hub Service

Central orchestration layer for the multi-country HR platform. Consumes events from HR Service via RabbitMQ, caches data, validates checklists, and serves country-specific UI APIs.

## Features

- **Event consumer:** Listens to RabbitMQ (`hr.events` exchange, `employee.#` routing) for employee create/update/delete
- **Checklist API:** `GET /api/checklists?country=USA|Germany` – completion stats per country
- **Steps API:** `GET /api/steps?country=...` – navigation steps (Dashboard, Employees, Documentation for DE)
- **Employees API:** `GET /api/employees?country=...&page=&per_page=` – country-specific columns, masked SSN for USA
- **Schema API:** `GET /api/schema/{step_id}?country=...` – widget config for each step
- **Caching:** Redis for checklist and employee list (invalidated on RabbitMQ events)

## Quick start

### With Docker (combined stack at `innoscripta/`)

```bash
cd /Users/dayo/innoscripta
docker compose up -d
```

- **HR Service:** http://localhost:8000
- **Hub Service:** http://localhost:9000
- **RabbitMQ UI:** http://localhost:15672
- **Redis:** localhost:6379

### Run RabbitMQ consumer (cache invalidation)

In a separate terminal:

```bash
cd hub-service
php artisan rabbitmq:consume-employee-events
```

Or inside Docker:

```bash
docker compose exec hub-service php artisan rabbitmq:consume-employee-events
```

### Run locally (with HR Service + RabbitMQ + Redis in Docker)

1. Start infra: `docker compose up -d postgres rabbitmq redis hr-service`
2. Copy `.env.example` to `.env`, set:
   - `HR_SERVICE_URL=http://localhost:8000`
   - `RABBITMQ_HOST=127.0.0.1`
   - `REDIS_HOST=127.0.0.1`
   - `CACHE_STORE=redis`
3. `composer install && php artisan serve --port=9000`
4. Run consumer: `php artisan rabbitmq:consume-employee-events`

## API endpoints

| Endpoint | Description |
|----------|-------------|
| GET /api/checklists?country=USA \| Germany | Checklist stats + per-employee completion |
| GET /api/steps?country=... | Navigation steps |
| GET /api/employees?country=...&page=&per_page= | Employee list (country columns) |
| GET /api/schema/{step_id}?country=... | Widget config for step |

## Checklist rules

- **USA:** ssn, salary > 0, address required
- **Germany:** salary > 0, goal, tax_id (DE + 9 digits) required
