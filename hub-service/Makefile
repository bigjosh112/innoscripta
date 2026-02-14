# Run from hub-service/ after: docker compose up -d
.PHONY: rabbitmq-pull rabbitmq-pull-once rabbitmq-pull-all rabbitmq-pull-once-shared help

# Pull from hub-service's own RabbitMQ (container "rabbitmq")
rabbitmq-pull-once:
	docker compose exec hub-service php artisan rabbitmq:pull employee_events --once --exchange=hr.events --routing-key=employee.#

rabbitmq-pull:
	docker compose exec hub-service php artisan rabbitmq:pull employee_events --exchange=hr.events --routing-key=employee.# $(if $(limit),--limit=$(limit),)

rabbitmq-pull-all:
	docker compose exec hub-service php artisan rabbitmq:pull employee_events --exchange=hr.events --routing-key=employee.#

# Pull from host RabbitMQ (e.g. hr-service at localhost:5672) – use when the message is in the other stack
rabbitmq-pull-once-shared:
	docker compose run --rm -e RABBITMQ_HOST=host.docker.internal -e RABBITMQ_PORT=5672 hub-service php artisan rabbitmq:pull employee_events --once --exchange=hr.events --routing-key=employee.#

rabbitmq-pull-shared:
	docker compose run --rm -e RABBITMQ_HOST=host.docker.internal -e RABBITMQ_PORT=5672 hub-service php artisan rabbitmq:pull employee_events --exchange=hr.events --routing-key=employee.# $(if $(limit),--limit=$(limit),)

help:
	@echo "From hub-service/ (after: docker compose up -d)"
	@echo "  make rabbitmq-pull-once         - Pull one message (hub's own RabbitMQ)"
	@echo "  make rabbitmq-pull-once-shared  - Pull one message from host RabbitMQ (hr-service / localhost:5672)"
	@echo "  make rabbitmq-pull limit=N"
	@echo "  make rabbitmq-pull-all"
