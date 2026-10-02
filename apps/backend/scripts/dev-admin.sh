#!/usr/bin/env sh

set -eu

php artisan serve --host=127.0.0.1 --port=8002 &
server_pid=$!

php artisan queue:work --tries=3 --timeout=120 &
queue_pid=$!

cleanup() {
    kill "$server_pid" "$queue_pid" 2>/dev/null || true
}

trap cleanup INT TERM EXIT

while :; do
    if ! kill -0 "$server_pid" 2>/dev/null; then
        wait "$server_pid"
        exit $?
    fi

    if ! kill -0 "$queue_pid" 2>/dev/null; then
        wait "$queue_pid"
        exit $?
    fi

    sleep 1
done
