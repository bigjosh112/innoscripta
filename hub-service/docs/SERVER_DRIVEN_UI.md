# Feature 2: Server-Driven UI APIs

## What we're trying to achieve (plain English)

**Problem:** If the frontend hardcodes "we have Dashboard, Employees, Documentation", then every time the product team wants different navigation per country (e.g. USA gets 2 tabs, Germany gets 3), you change frontend code and redeploy.

**Goal:** The **backend** decides what the user sees by country. The frontend is generic: it asks "what nav do I show?" and "what widgets go on this screen?" and renders whatever the backend returns. So USA gets 2 nav items (Dashboard, Employees) and Germany gets 3 (Dashboard, Employees, Documentation). No frontend deploy needed to add or remove a step—only backend config changes. That's "server-driven UI".

**How the three APIs fit in:** (1) **Steps** – backend returns the nav list; frontend renders it. (2) **Employees** – backend returns data plus which columns to show; frontend doesn't hardcode columns. (3) **Schema** – backend returns which widgets to show on each step and where to get data; frontend doesn't hardcode "dashboard has 3 cards".

**In one sentence:** One frontend shows different navigation and content per country, controlled by the backend, so layout changes don't require a frontend deploy.

---

## What does the dashboard actually show? (not just links)

The schema returns **widget config**: `id`, `type`, `title`, `data_source` (URL), `channel` (WebSocket). That is a **recipe**, not the visible content.

**What the user sees:** The dashboard shows **values** — e.g. “**47**” for Employee Count, “**72,500**” for Average Salary, “**89%**” for Completion Rate. Those numbers come from **calling the `data_source` URL** and rendering the response.

**Flow:**

1. Frontend gets schema for the dashboard step → list of widgets (e.g. Employee Count, Average Salary, Completion Rate), each with `data_source: /api/checklists?country=USA` and `channel: checklist.USA`.
2. For each widget, the frontend **GETs the `data_source`** (same URL can serve multiple widgets). Example: `GET /api/checklists?country=USA` returns JSON with an `overall` object.
3. The frontend **maps widget `id` to a field** in that response and renders it in the widget’s `type` (e.g. `stat` = one number + title):
   - **employee_count** → display `overall.total`
   - **average_salary** → display `overall.average_salary` (e.g. formatted as currency)
   - **completion_rate** → display `overall.percentage` (e.g. with a “%”)
4. The frontend **subscribes to `channel`** so when the backend broadcasts (e.g. checklist recomputed), it can refetch or update the widget.

So: **schema = “what to show and where to get it”**; **data API response = the actual numbers/lists** the dashboard displays. The dashboard does not show the URLs — it shows the data returned from those URLs.

**Checklist API response shape (for dashboard stats):**  
`GET /api/checklists?country=USA` returns (among other fields) `overall`: `{ "total": 47, "complete": 42, "percentage": 89, "average_salary": 72500 }`. The frontend uses `total`, `average_salary`, and `percentage` for the three USA stat widgets.

---

## Why steps and schema? (technical)

**Concept (from spec):** The backend controls what the frontend displays by country, so you can change UI layouts without deploying frontend code.

- **Steps (API 1)** = **Navigation.** They answer: “Which screens/pages should this user see?” The frontend does *not* hardcode “Dashboard, Employees”; it calls `GET /api/steps?country=...` and builds the nav from the response. USA gets 2 steps (Dashboard, Employees); Germany gets 3 (Dashboard, Employees, **Documentation**). Adding or reordering steps is a backend change only.
- **Schema (API 3)** = **Widget config per step.** For the *selected* step (e.g. `dashboard` or `employees`), the frontend calls `GET /api/schema/{step_id}?country=...` to get the list of widgets for that screen: title, type, `data_source` (API to fetch data), and `channel` (WebSocket for real-time). So: steps define *which* screens exist; schema defines *what* appears on each screen.

Together, steps + schema make the UI server-driven: navigation and content are controlled by the backend per country.

---

## API 1: Steps Configuration

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Endpoint** GET /api/steps | ✅ | `routes/api.php` → `StepsController::index` |
| **Query:** country (required) | ✅ | `IndexStepsRequest` validates `country`; 422 if missing |
| **USA returns:** Dashboard, Employees | ✅ | `StepsConfig::getSteps('USA')` – 2 steps |
| **Germany returns:** Dashboard, Employees, Documentation | ✅ | `StepsConfig::getSteps('Germany')` – 3 steps |
| **Metadata:** labels, icons, ordering, paths | ✅ | Each step: `id`, `label`, `path`, `order`, `icon` |

**Response shape:** `{ "data": [ { "id", "label", "path", "order", "icon" }, ... ] }`

**Code:** `app/Services/Steps/StepsConfig.php`, `app/Http/Controllers/StepsController.php`

---

## API 2: Employee List with Real-Time Updates

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Endpoint** GET /api/employees | ✅ | `EmployeeController::index` |
| **Query:** country (required), page, per_page | ✅ | `IndexEmployeesRequest`: country required; page/per_page optional, 1–100 |
| **USA columns:** Name, Last Name, Salary, SSN (masked) | ✅ | `getColumnsForCountry` + `transformEmployee`: `name`, `last_name`, `salary`, `ssn_masked` (masked as `***-**-XXXX`) |
| **Germany columns:** Name, Last Name, Salary, Goal | ✅ | Same; columns and transform include `goal`, no SSN |
| **Column definitions for frontend** | ✅ | Response includes `columns`: `[{ "key", "label", "type" }, ...]` |
| **Pagination** | ✅ | Forwards `page`, `per_page` to HR; response includes `data` + `meta` (from HR: current_page, last_page, total, etc.) |
| **Cache and invalidate on employee events** | ✅ | Cache key `employees:{country}:{page}:{perPage}` and `employees:{country}:{id}`; `EmployeeEventProcessor::invalidateCache()` clears `employees:{country}:*` when events arrive |

**Response shape:** `{ "data": [...], "meta": { ... }, "columns": [ { "key", "label", "type" }, ... ] }`

**Code:** `app/Http/Controllers/EmployeeController.php`, `app/Services/EmployeeEventProcessor.php` (invalidation)

---

## API 3: Schema Configuration

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| **Endpoint** GET /api/schema/{step_id} | ✅ | `SchemaController::show` |
| **Query:** country (required) | ✅ | `ShowSchemaRequest` validates `country` |
| **Dashboard – USA widgets:** Employee count, Average salary, Completion rate | ✅ | `SchemaConfig::getSchema('dashboard', 'USA')`: `employee_count`, `average_salary`, `completion_rate` (type `stat`) |
| **Dashboard – Germany widgets:** Employee count, Goal tracking | ✅ | Same for Germany: `employee_count`, `goal_tracking` (type `list`) |
| **Widgets specify data_source and real-time channel** | ✅ | Each widget: `data_source` (e.g. `/api/checklists?country=USA`), `channel` (e.g. `checklist.USA`). Frontend **fetches** `data_source` and **displays** the response (e.g. `overall.total`, `overall.average_salary`, `overall.percentage`) — see “What does the dashboard actually show?” above. |
| **Frontend-agnostic widget structure** | ✅ | Generic: `id`, `type`, `title`, `data_source`, `channel` (no framework-specific fields) |

**Response shape:** `{ "data": { "step_id": "...", "widgets": [ { "id", "type", "title", "data_source", "channel" }, ... ] } }`

**Code:** `app/Services/Schema/SchemaConfig.php`, `app/Http/Controllers/SchemaController.php`

---

## Summary

- **API 1 (Steps):** Country-driven steps with metadata; USA vs Germany step set as specified.
- **API 2 (Employees):** Country-specific columns (USA: SSN masked; Germany: Goal), column definitions, pagination, caching with invalidation on employee events.
- **API 3 (Schema):** Per-step, per-country widget config with `data_source` and `channel` for real-time; dashboard widgets match spec for USA and Germany.

All Feature 2 requirements are implemented in the Hub service.
