#!/usr/bin/env bash
# One-shot: wipe ALL issues on the repo, then recreate exactly the 42 from
# docs/issues/*.md (each file: TITLE/MILESTONE/LABELS header, a `---` line, body).
# Run once:  bash scripts/reset-issues.sh
set -uo pipefail

REPO="tessak22/handy-herdsman"
cd "$(dirname "$0")/.."

echo "==> Deleting all existing issues on $REPO ..."
for n in $(gh issue list -R "$REPO" -s all -L 500 --json number -q '.[].number'); do
  gh issue delete "$n" -R "$REPO" --yes >/dev/null 2>&1 \
    || { sleep 3; gh issue delete "$n" -R "$REPO" --yes >/dev/null 2>&1; }
  sleep 0.5
done
remaining=$(gh issue list -R "$REPO" -s all -L 500 --json number -q 'length')
echo "    remaining after delete: $remaining"

echo "==> Recreating 42 issues from docs/issues/ ..."
created=0
for f in $(ls docs/issues/*.md | sort -V); do
  title=$(grep -m1 '^TITLE:' "$f" | sed 's/^TITLE:[[:space:]]*//')
  milestone=$(grep -m1 '^MILESTONE:' "$f" | sed 's/^MILESTONE:[[:space:]]*//')
  labels=$(grep -m1 '^LABELS:' "$f" | sed 's/^LABELS:[[:space:]]*//')

  body_file=$(mktemp)
  awk 'body{print} /^---/{body=1}' "$f" > "$body_file"

  label_args=()
  IFS=',' read -ra L <<< "$labels"
  for l in "${L[@]}"; do l="$(echo "$l" | xargs)"; [ -n "$l" ] && label_args+=(--label "$l"); done

  url=$(gh issue create -R "$REPO" --title "$title" --body-file "$body_file" \
        --milestone "$milestone" "${label_args[@]}" 2>/dev/null) \
    && { echo "    + $title"; created=$((created+1)); } \
    || echo "    ! FAILED: $title"
  rm -f "$body_file"
  sleep 1.5   # pace against GitHub secondary rate limits
done

echo "==> DONE. created=$created  final_open=$(gh issue list -R "$REPO" -s open -L 500 --json number -q 'length')"
