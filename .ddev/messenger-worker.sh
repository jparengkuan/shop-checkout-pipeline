#!/usr/bin/env bash
# Started by DDEV (web_extra_daemons in config.yaml): waits for RabbitMQ, then
# runs a Messenger worker for the transport given as the first argument,
# e.g. "order_invoices" (invoice-worker) or "order_emails" (email-worker).
set -euo pipefail

transport="${1:?usage: messenger-worker.sh <transport>}"

until (exec 3<>"/dev/tcp/${RABBITMQ_HOST:-rabbitmq}/${RABBITMQ_PORT:-5672}") 2>/dev/null; do
    echo "${transport} worker: waiting for RabbitMQ..."
    sleep 2
done

exec php bin/console messenger:consume "${transport}" --time-limit=3600 --memory-limit=128M -vv
