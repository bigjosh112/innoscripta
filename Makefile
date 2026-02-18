# Run from repo root. Ports: HR 8001, Hub 9001, Postgres 5434, RabbitMQ 5673 / UI 15673, Redis 6380
.PHONY: up down build logs hr-logs hub-logs rabbitmq-pull rabbitmq-pull-once rabbitmq-pull-all hr-migrate hr-migrate-fresh hr-migrate-refresh help

# Start all services (hr, hub, postgres, rabbitmq, redis)
up:
	docker compose up -d

# Stop all services
down:
	docker compose down

# Build (or rebuild) all images
build:
	docker compose build

# Rebuild and start
up-build: build up

# Logs
logs:
	docker compose logs -f

hr-logs:
	docker compose logs -f hr-service

hub-logs:
	docker compose logs -f hub-service

# HR: run migrations
hr-migrate:
	docker compose exec hr-service php artisan migrate --force

# HR: refresh DB — drop all tables and re-run migrations (optional: make hr-migrate-fresh seed=1)
hr-migrate-fresh:
	docker compose exec hr-service php artisan migrate:fresh --force $(if $(seed),--seed,)

# HR: refresh DB — rollback all migrations then re-run
hr-migrate-refresh:
	docker compose exec hr-service php artisan migrate:refresh --force

# RabbitMQ: pull one message from employee_events (shared queue)
rabbitmq-pull-once:
	docker compose exec hub-service php artisan rabbitmq:pull employee_events --once --exchange=hr.events --routing-key=employee.#

# RabbitMQ: pull with optional limit (e.g. make rabbitmq-pull limit=5)
rabbitmq-pull:
	docker compose exec hub-service php artisan rabbitmq:pull employee_events --exchange=hr.events --routing-key=employee.# $(if $(limit),--limit=$(limit),)

# RabbitMQ: pull all messages in queue
rabbitmq-pull-all:
	docker compose exec hub-service php artisan rabbitmq:pull employee_events --exchange=hr.events --routing-key=employee.#

help:
	@echo "Stack (shared Redis + RabbitMQ)"
	@echo "  make up          - Start all (HR :8001, Hub :9001)"
	@echo "  make down       - Stop all"
	@echo "  make build      - Build images"
	@echo "  make up-build   - Build and start"
	@echo "  make logs       - Follow all logs"
	@echo "  make hr-logs    - Follow hr-service logs"
	@echo "  make hub-logs   - Follow hub-service logs"
	@echo "  make hr-migrate         - Run HR migrations"
	@echo "  make hr-migrate-fresh   - Drop all tables and re-migrate (optional: seed=1)"
	@echo "  make hr-migrate-refresh - Rollback all then re-migrate"
	@echo ""
	@echo "RabbitMQ (employee_events queue)"
	@echo "  make rabbitmq-pull-once  - Pull one message"
	@echo "  make rabbitmq-pull       - Pull (optional: limit=N)"
	@echo "  make rabbitmq-pull-all   - Pull all"
