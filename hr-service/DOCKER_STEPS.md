# Run HR Service with Docker – Step by Step

## Prerequisites

- **Docker** and **Docker Compose** installed ([Docker Desktop](https://www.docker.com/products/docker-desktop/) includes both).
- Terminal (or Cursor terminal) open in the project folder: `hr-service`.

---

## Step 1: Open the project in the terminal

```bash
cd /Users/dayo/innoscripta/hr-service
```

(Or in Cursor: open the integrated terminal; it’s usually already in the project root.)

---

## Step 2: Start all services

Run:

```bash
docker compose up -d
```

- `-d` runs containers in the background.
- First time this will **build** the HR Service image (can take 1–2 minutes), then start **PostgreSQL**, **RabbitMQ**, and **HR Service**.

You should see something like:

```
[+] Building ...
[+] Running 3/3
 ✔ Container hr-postgres   Started
 ✔ Container hr-rabbitmq   Started
 ✔ Container hr-service    Started
```

---

## Step 3: Wait for the app to be ready

The HR Service container runs `composer install`, then migrations, then starts the server. Give it **30–60 seconds** the first time.

Check that the API responds:

```bash
curl -s http://localhost:8000/api/employees
```

You should get JSON, e.g.:

```json
{"data":[],"meta":{"current_page":1,...}}
```

If you get “Connection refused”, wait a bit and try again.

---

## Step 4: Test the API (optional)

**Create an employee (USA):**

```bash
curl -s -X POST http://localhost:8000/api/employees \
  -H "Content-Type: application/json" \
  -d '{"name":"John","last_name":"Doe","country":"USA","salary":75000,"ssn":"123-45-6789","address":"123 Main St, NY"}'
```

**List employees:**

```bash
curl -s http://localhost:8000/api/employees
```

---

## Step 5: Open RabbitMQ Management (optional)

- URL: **http://localhost:15672**
- Login: **guest** / **guest**

Go to **Exchanges** → you should see `hr.events` after at least one create/update/delete.

---

## Useful commands

| What you want to do | Command |
|---------------------|--------|
| See running containers | `docker compose ps` |
| See HR Service logs | `docker compose logs -f hr-service` |
| Run tests inside Docker | `docker compose exec hr-service php artisan test` |
| Stop everything | `docker compose down` |
| Stop and remove database data too | `docker compose down -v` |

---

## If something goes wrong

1. **Port already in use**  
   Something else is using 8000, 5432, or 15672. Stop that app or change the port in `docker-compose.yml` (e.g. `"8001:8000"` for the API).

2. **“Cannot connect to Docker daemon”**  
   Start Docker Desktop (or the Docker service) and try again.

3. **HR Service exits or won’t start**  
   Check logs:
   ```bash
   docker compose logs hr-service
   ```
   Often it’s waiting for PostgreSQL or RabbitMQ; wait 30s and run `docker compose up -d` again.

4. **Clean start (remove DB and volumes)**  
   ```bash
   docker compose down -v
   docker compose up -d
   ```
