#!/bin/sh
set -e

JS_FILE=$(grep -nril "window.location.origin||\"http://localhost:8080\"" /etc/nginx/html/ || echo "0")
if [ "0" != "${JS_FILE}" ]; then
    sed -i 's/window.location.origin||"http:\/\/localhost:8080"/"http:\/\/api.'${TRADING_BOT_DOMAIN}':'${TRADING_BOT_API_PORT}'"/g' $JS_FILE
fi

exec "$@"
