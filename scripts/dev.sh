#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# Run a composer command for this repo inside the official composer image (no host PHP needed).
# Usage: scripts/dev.sh install | scripts/dev.sh test | scripts/dev.sh stan | scripts/dev.sh run cs:check ...
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
IMAGE="docker.io/library/composer:2"
exec podman run --rm --userns=keep-id -v "$ROOT:/app:Z" -w /app -e COMPOSER_CACHE_DIR=/app/.composer-cache "$IMAGE" composer "$@"
