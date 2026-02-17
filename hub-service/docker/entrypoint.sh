#!/bin/sh
set -e
# Run RabbitMQ pull every 60s in background (inherits full Docker env so Pusher broadcast works)
(
  while true; do
    cd /app && php artisan rabbitmq:pull employee_events --once --exchange=hr.events --routing-key=employee.# 2>/dev/null || true
    sleep 60
  done
) &
# Run the main process (e.g. php artisan serve)
exec "$@"
