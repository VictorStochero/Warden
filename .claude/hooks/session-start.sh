#!/bin/bash
# Warden — Claude Code on the web session bootstrap.
# Installs Composer dependencies so the test/lint/static-analysis toolchain
# (phpunit, pint, phpstan) is ready before the session begins.
set -euo pipefail

# Only run in the remote (web) environment; locals use Herd/PowerShell.
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-.}"

# Idempotent: re-running just no-ops when vendor/ is already present and current.
composer install --no-interaction --no-progress --prefer-dist
