#!/usr/bin/env bash

set -e
[[ -t 1 ]] && piped=0 || piped=1

while
    RANDOM_PORT=$(shuf -n 1 -i 49152-65535)
    netstat -atun | grep -q "$RANDOM_PORT"
do
    continue
done

echo $RANDOM_PORT
