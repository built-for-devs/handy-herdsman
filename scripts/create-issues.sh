#!/usr/bin/env bash
# One-time helper: create GitHub issues from docs/issues/*.md (M0 artifact).
set -euo pipefail
cd "$(dirname "$0")/.."

created=0
for f in $(ls docs/issues/*.md | sort -V); do
  title=$(grep -m1 '^TITLE:' "$f" | sed 's/^TITLE:[[:space:]]*//')
  milestone=$(grep -m1 '^MILESTONE:' "$f" | sed 's/^MILESTONE:[[:space:]]*//')
  labels=$(grep -m1 '^LABELS:' "$f" | sed 's/^LABELS:[[:space:]]*//')

  # Body = everything after the first '---' separator line.
  body_file=$(mktemp)
  awk 'body{print} /^---/{body=1}' "$f" > "$body_file"

  label_args=()
  IFS=',' read -ra L <<< "$labels"
  for l in "${L[@]}"; do label_args+=(--label "$l"); done

  url=$(gh issue create --title "$title" --body-file "$body_file" \
        --milestone "$milestone" "${label_args[@]}")
  echo "  + $title -> $url"
  rm -f "$body_file"
  created=$((created+1))
  sleep 2   # pace to avoid GitHub secondary rate limit
done
echo "created $created issues"
