# Run from repo root. Ports: HR 8001, Hub 9001, Postgres 5434, RabbitMQ 5673 / UI 15673, Redis 6380
.PHONY: up down build logs hr-logs hub-logs rabbitmq-pull rabbitmq-pull-once rabbitmq-pull-all hr-migrate help

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
	@echo "  make hr-migrate - Run HR migrations"
	@echo ""
	@echo "RabbitMQ (employee_events queue)"
	@echo "  make rabbitmq-pull-once  - Pull one message"
	@echo "  make rabbitmq-pull       - Pull (optional: limit=N)"
	@echo "  make rabbitmq-pull-all   - Pull all"
