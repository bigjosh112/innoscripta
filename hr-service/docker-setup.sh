#!/bin/sh
# Run this when hr-service won't stay up (e.g. vendor missing).
# Step 1: Start infra (postgres, rabbitmq)
# Step 2: Populate vendor in a one-off container
# Step 3: Start full stack

set -e
cd "$(dirname "$0")"

echo "Starting PostgreSQL and RabbitMQ..."
docker compose up -d postgres rabbitmq

echo "Waiting for them to be healthy..."
sleep 5

echo "Installing PHP dependencies (one-off container)..."
docker compose run --rm hr-service composer install --no-interaction

echo "Starting HR Service..."
docker compose up -d hr-service

echo "Done. API: http://localhost:8000"
echo "Check: docker compose ps"
