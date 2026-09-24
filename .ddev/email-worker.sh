#!/usr/bin/env bash
# Started by DDEV (web_extra_daemons in config.yaml): waits for RabbitMQ, then
# runs the Messenger worker that handles OrderPlaced and sends the order email.
set -euo pipefail

until (exec 3<>"/dev/tcp/${RABBITMQ_HOST:-rabbitmq}/${RABBITMQ_PORT:-5672}") 2>/dev/null; do
    echo "email-worker: waiting for RabbitMQ..."
    sleep 2
done

exec php bin/console messenger:consume order_emails --time-limit=3600 --memory-limit=128M -vv
