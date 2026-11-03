#!/usr/bin/env bash
# Creates .env from .env.example with generated random database passwords.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -f .env ]; then
  echo ".env already exists; not overwriting."
  exit 0
fi

umask 077
while IFS= read -r line; do
  case "$line" in
    *=__generated__) printf '%s=%s\n' "${line%%=*}" "$(openssl rand -hex 24)" ;;
    *) printf '%s\n' "$line" ;;
  esac
done < .env.example > .env
echo "Created .env with generated passwords."
