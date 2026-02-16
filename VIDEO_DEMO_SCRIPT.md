# Video Demo – Step-by-Step Script

Use this order when recording. Have these ready: terminal, browser (two tabs: one for APIs/echo-test, one for RabbitMQ UI), and optional second terminal for logs.

---

## 1. Quick overview of architecture

**What to say/show (30–60 seconds):**

- **Two Laravel services:** HR Service (source of truth, employee CRUD, publishes to RabbitMQ) and Hub Service (consumes events, caches, exposes APIs and WebSocket).
- **Data flow:** Client writes → HR → PostgreSQL + RabbitMQ. Hub consumes from RabbitMQ → invalidates cache → broadcasts. Reads go to Hub → cache or HR.
- **Optional:** Show the architecture diagram from `README.md` (Section 2) on screen.

---

## 2. Start system with docker-compose up

**Commands (from repo root):**

```bash
cd /path/to/innoscripta
docker compose up -d
# or: make up
```

**What to show:**

- Run the command; wait until all containers are running (`docker compose ps`).
- **URLs:** HR http://localhost:8001, Hub http://localhost:9001, RabbitMQ UI http://localhost:15673.

**Optional:** If you need an employee for later steps and the DB is empty:

```bash
curl -s -X POST http://localhost:8001/api/employees \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane","last_name":"Doe","salary":60000,"country":"USA","ssn":"123-45-6789","address":"456 Oak Ave"}' \
  | jq .
```

Note the returned `id` (e.g. `1`) for step 4.

---

## 3. Make API request to /api/checklists

**What to show:**

- Call Hub’s checklist API (e.g. for USA):

```bash
curl -s "http://localhost:9001/api/checklists?country=USA" | jq .
```

**Point out:**

- Response has `overall` (total, complete, percentage) and `employees` with per-employee checklist (fields, completion_percentage, complete).
- This is cached by Hub; data ultimately comes from HR.

---

## 4. Update employee data via HR Service API

**What to show:**

- Update an existing employee via HR (use a real `id` from your DB, e.g. `1`):

```bash
curl -s -X PATCH http://localhost:8001/api/employees/1 \
  -H "Content-Type: application/json" \
  -d '{"salary":65000}' \
  | jq .
```

**Or** create a new employee (if you didn’t in step 2):

```bash
curl -s -X POST http://localhost:8001/api/employees \
  -H "Content-Type: application/json" \
  -d '{"name":"Hans","last_name":"Mueller","salary":55000,"country":"Germany","goal":"Increase sales","tax_id":"DE123456789"}' \
  | jq .
```

**Point out:**

- HR returns the updated/created employee. HR also publishes an event to RabbitMQ (EmployeeUpdated or EmployeeCreated); we’ll see that next.

---

## 5. Show event in RabbitMQ Management UI

**What to do:**

1. Open **http://localhost:15673** in the browser.
2. Log in: **guest** / **guest** (or your configured user).
3. Go to **Queues**.
4. Find the queue used by Hub (e.g. **employee_events**). If it’s not there yet, run step 6 once so Hub creates/binds the queue, then refresh.
5. Show **Ready** (or **Total**) message count – the event(s) from step 4.
6. **Optional:** Open the queue → “Get messages” to peek at one (don’t requeue if you want Hub to process it later).

**Point out:**

- Each employee create/update/delete produces a message; Hub will consume these and invalidate cache + broadcast.

---

## 6. Show logs confirming event processing

**Option A – Process one message and show Hub output:**

1. In a terminal, run (from repo root):

```bash
make rabbitmq-pull-once
```

2. Show the terminal output: “Processing EmployeeUpdated for country USA” (or similar), “Invalidated cache for country: USA”, “Processed 1 message(s).”

**Option B – Follow Hub logs while processing:**

1. In one terminal: `docker compose logs -f hub-service` (or `make hub-logs`).
2. In another: `make rabbitmq-pull-once`.
3. In the first terminal, show the same kind of log lines (if any are emitted to stdout).

**Point out:**

- Hub consumed the message, ran EmployeeEventProcessor, invalidated cache for that country, and broadcast. Next we’ll see updated checklist and the WebSocket event.

---

## 7. Show updated checklist data

**What to do:**

- Call the checklist API again (same as step 3):

```bash
curl -s "http://localhost:9001/api/checklists?country=USA" | jq .
```

**Point out:**

- Cache was invalidated in step 6, so this response is fresh from HR (e.g. updated salary or new employee). Numbers and list should reflect the change from step 4.

---

## 8. Show WebSocket update in browser console

**What to do:**

1. **Before step 6:** Open **http://localhost:9001/echo-test.html** in a browser tab and open DevTools → **Console**.
2. Confirm the page shows “Live” (connected to Pusher) and lists channels (e.g. checklist.USA, employees.USA).
3. Run **step 6** (`make rabbitmq-pull-once`) so Hub processes a message and broadcasts.
4. In the console, show the logged event (e.g. “WebSocket event [USA]: ChecklistUpdated” or “EmployeeDataUpdated” with payload).
5. Optionally show the on-page panels (USA/Germany) updating with the new event.

**Point out:**

- The event we processed triggered a broadcast; the browser received it over Pusher, so the frontend can refetch or update the UI in real time.

**Tip:** If you already processed all messages in step 6, trigger a new event (e.g. PATCH another employee in step 4), then run `make rabbitmq-pull-once` again with the echo-test page open.

---

## 9. Quick look at test results

**What to do:**

1. From repo root, run Hub tests:

```bash
cd hub-service
./vendor/bin/phpunit
# or: php artisan test
```

2. Optionally run by suite:

```bash
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Feature
./vendor/bin/phpunit --testsuite=Integration
```

**Point out:**

- Unit tests (e.g. ChecklistValidator, EmployeeEventProcessor), Feature tests (API endpoints), Integration tests (event flow → cache → broadcast). All green.

**Optional – HR tests:**

```bash
cd hr-service
./vendor/bin/phpunit
```

---

## Checklist summary

| # | Item | Done |
|---|------|------|
| 1 | Architecture overview | ☐ |
| 2 | `docker compose up -d` | ☐ |
| 3 | `GET /api/checklists?country=USA` | ☐ |
| 4 | Update/create employee via HR API | ☐ |
| 5 | RabbitMQ Management UI – queue / messages | ☐ |
| 6 | Process event + show logs | ☐ |
| 7 | Second checklist request – updated data | ☐ |
| 8 | echo-test.html + console – WebSocket event | ☐ |
| 9 | Run tests (Hub, optionally HR) | ☐ |

---

## Suggested recording order

1. **Intro:** Architecture (step 1).  
2. **Start:** `docker compose up -d` (step 2).  
3. **Read path:** Checklist API (step 3).  
4. **Write path:** HR update (step 4).  
5. **Queue:** RabbitMQ UI (step 5).  
6. **Consumption:** `make rabbitmq-pull-once` + logs (step 6).  
7. **Fresh read:** Checklist again (step 7).  
8. **Real-time:** echo-test + console (step 8).  
9. **Outro:** Tests (step 9).

Keep the echo-test page open and connected **before** you run step 6 so the WebSocket event appears in the console during the demo.
