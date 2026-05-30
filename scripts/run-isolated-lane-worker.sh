#!/usr/bin/env bash
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LANE="${1:?lane name required}"
SLICE="${2:?micro-slice label required}"
SESSION="${3:-isolated-${LANE}-${SLICE}}"

refill_libsqlite_on_exit() {
  local status=$?
  trap - EXIT
  if [[ "${LANE:-}" == "libsqlite" && "${LIBSQLITE_AUTO_REFILL:-1}" != "0" && -x "$ROOT/scripts/refill-libsqlite-workers.sh" ]]; then
    LIBSQLITE_AUTO_REFILL=0 bash "$ROOT/scripts/refill-libsqlite-workers.sh" --once || true
  fi
  exit "$status"
}
trap refill_libsqlite_on_exit EXIT

AGENT_BIN="${AGENT_BIN:-codex}"
AGENT_FAST_MODEL="${AGENT_FAST_MODEL:-gpt-5.5}"
AGENT_FAST_REASONING="${AGENT_FAST_REASONING:-low}"
AGENT_FAST_SERVICE_TIER="${AGENT_FAST_SERVICE_TIER:-priority}"

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
SAFE_SESSION="$(printf '%s' "$SESSION" | tr -cs 'A-Za-z0-9._-' '-')"
WORKTREE_ROOT="${ISOLATED_WORKTREE_ROOT:-$ROOT/.tmux-team/worktrees}"
WORKTREE="$WORKTREE_ROOT/$SAFE_SESSION-$TIMESTAMP"
PROMPT_TEMPLATE="$ROOT/.tmux-team/prompts/isolated-worker-template.md"
PROMPT_DIR="$ROOT/.tmux-team/tmp/isolated-worker-prompts"
LOG_DIR="$ROOT/.tmux-team/logs/isolated-lane-workers"
HANDOFF_DIR="$ROOT/.tmux-team/tmp/handoff-candidates"
PROMPT_FILE="$PROMPT_DIR/$SAFE_SESSION-$TIMESTAMP.md"
LOG_FILE="$LOG_DIR/$SAFE_SESSION-$TIMESTAMP.log"
PATCH_FILE="$HANDOFF_DIR/$SAFE_SESSION-$TIMESTAMP.patch"
META_FILE="$HANDOFF_DIR/$SAFE_SESSION-$TIMESTAMP.md"
READY_FILE="$HANDOFF_DIR/$SAFE_SESSION-$TIMESTAMP.ready"
WORKTREE_SNAPSHOT_DIR="$ROOT/.tmux-team/tmp/pruned-worktree-diffs/auto-$SAFE_SESSION-$TIMESTAMP"

cd "$ROOT" || exit 1

if [[ ! -d "lanes/$LANE" ]]; then
  printf 'Lane does not exist: lanes/%s\n' "$LANE" >&2
  exit 2
fi

if [[ ! -f "$PROMPT_TEMPLATE" ]]; then
  printf 'Prompt template does not exist: %s\n' "$PROMPT_TEMPLATE" >&2
  exit 2
fi

if ! command -v "$AGENT_BIN" >/dev/null 2>&1; then
  printf 'Agent binary not found: %s\n' "$AGENT_BIN" >&2
  exit 2
fi

if [[ -n "${ISOLATED_BASE_SHA:-}" ]]; then
  BASE_SHA="$(git rev-parse --verify "$ISOLATED_BASE_SHA")" || exit 2
else
  BASE_SHA="$(git rev-parse HEAD)" || exit 2
fi
mkdir -p "$WORKTREE_ROOT" "$PROMPT_DIR" "$LOG_DIR" "$HANDOFF_DIR"

if [[ -e "$WORKTREE" ]]; then
  printf 'Refusing to reuse existing worktree path: %s\n' "$WORKTREE" >&2
  exit 2
fi

git worktree add --detach "$WORKTREE" "$BASE_SHA" || exit 2

export LANE SESSION SLICE BASE_SHA WORKTREE LOG_FILE HANDOFF_DIR
MAIN_REPO="$ROOT"
export MAIN_REPO
perl -0pe '
  s/\{\{LANE\}\}/$ENV{LANE}/g;
  s/\{\{SESSION\}\}/$ENV{SESSION}/g;
  s/\{\{SLICE\}\}/$ENV{SLICE}/g;
  s/\{\{BASE_SHA\}\}/$ENV{BASE_SHA}/g;
  s/\{\{MAIN_REPO\}\}/$ENV{MAIN_REPO}/g;
  s/\{\{WORKTREE\}\}/$ENV{WORKTREE}/g;
  s/\{\{LOG_FILE\}\}/$ENV{LOG_FILE}/g;
  s/\{\{HANDOFF_DIR\}\}/$ENV{HANDOFF_DIR}/g;
' "$PROMPT_TEMPLATE" > "$PROMPT_FILE" || exit 2

printf 'Isolated lane worker\n'
printf 'Lane: %s\n' "$LANE"
printf 'Slice: %s\n' "$SLICE"
printf 'Session: %s\n' "$SESSION"
printf 'Base: %s\n' "$BASE_SHA"
printf 'Worktree: %s\n' "$WORKTREE"
printf 'Prompt: %s\n' "$PROMPT_FILE"
printf 'Log: %s\n\n' "$LOG_FILE"

"$AGENT_BIN" \
  -m "$AGENT_FAST_MODEL" \
  -c "model_service_tier=\"$AGENT_FAST_SERVICE_TIER\"" \
  -c "model_reasoning_effort=\"$AGENT_FAST_REASONING\"" \
  -a never exec -C "$WORKTREE" -s danger-full-access - < "$PROMPT_FILE" > "$LOG_FILE" 2>&1
status=$?

if [[ "$status" -ne 0 ]]; then
  printf 'Agent exited with status %s. No ready marker written.\n' "$status" >&2
  printf 'Log: %s\n' "$LOG_FILE" >&2
  exit "$status"
fi

if [[ "$LANE" == "libsqlite" ]]; then
  (
    cd "$WORKTREE" || exit 1
    if rg -n 'CurrentSourceNext150Plan|CurrentSourceNext150' lanes/libsqlite/src lanes/libsqlite/tests lanes/libsqlite/examples; then
      printf 'libsqlite handoff guard failed: user-named CurrentSourceNext150 suffix remains. No ready marker written.\n' >&2
      exit 4
    fi
    if find lanes/libsqlite/src \( -name '*CurrentSourceNext[0-9]*.php' -o -name '*CurrentNext[0-9]*.php' \) -print -quit | rg .; then
      printf 'libsqlite handoff guard failed: numbered production source filename remains. No ready marker written.\n' >&2
      exit 4
    fi
    if rg -n '^final class .*Current(Source)?Next[0-9]+|^class .*Current(Source)?Next[0-9]+' lanes/libsqlite/src; then
      printf 'libsqlite handoff guard failed: numbered production source class remains. No ready marker written.\n' >&2
      exit 4
    fi
  ) || exit $?
fi

(
  cd "$WORKTREE" || exit 1
  git add -N "lanes/$LANE" >/dev/null 2>&1 || true
  git diff --binary HEAD -- "lanes/$LANE" > "$PATCH_FILE"
) || exit 2

if [[ ! -s "$PATCH_FILE" ]]; then
  printf 'Worker finished cleanly but produced no lane patch. No ready marker written.\n' >&2
  printf 'Log: %s\n' "$LOG_FILE" >&2
  rm -f "$PATCH_FILE"
  exit 3
fi

if [[ "$LANE" == "libsqlite" ]] &&
  grep -E '^\+.*(CurrentSourceNext150Plan|CurrentSourceNext150)' "$PATCH_FILE" >/dev/null; then
  printf 'libsqlite handoff guard failed: patch adds the user-named CurrentSourceNext150 suffix. No ready marker written.\n' >&2
  printf 'Patch: %s\n' "$PATCH_FILE" >&2
  exit 4
fi

PATCH_SHA256="$(sha256sum "$PATCH_FILE" | awk '{print $1}')"
PATCH_BYTES="$(wc -c < "$PATCH_FILE" | tr -d ' ')"

cat > "$META_FILE" <<EOF
# Isolated Lane Worker Handoff - $TIMESTAMP

- Lane: \`$LANE\`
- Slice: \`$SLICE\`
- Session: \`$SESSION\`
- Base accepted HEAD: \`$BASE_SHA\`
- Worktree: \`$WORKTREE\`
- Prompt: \`$PROMPT_FILE\`
- Log: \`$LOG_FILE\`
- Patch: \`$PATCH_FILE\`
- Patch bytes: \`$PATCH_BYTES\`
- Patch sha256: \`$PATCH_SHA256\`
- Ready marker: \`$READY_FILE\`

Integrator notes:

- Apply from the main repo with \`git apply --check "$PATCH_FILE"\` first.
- Inspect worker evidence in the log before accepting.
- This launcher exports only \`lanes/$LANE/**\`; shared checkout dirt is not part of the handoff.
- Successful handoffs snapshot and remove their generated worktree automatically. Failed handoffs leave the worktree in place for inspection.
EOF

cat > "$READY_FILE" <<EOF
ready_at=$TIMESTAMP
lane=$LANE
slice=$SLICE
session=$SESSION
base_sha=$BASE_SHA
worktree=$WORKTREE
prompt=$PROMPT_FILE
log=$LOG_FILE
patch=$PATCH_FILE
metadata=$META_FILE
patch_sha256=$PATCH_SHA256
EOF

printf 'Ready marker written: %s\n' "$READY_FILE"
printf 'Patch: %s\n' "$PATCH_FILE"
printf 'Metadata: %s\n' "$META_FILE"

if [[ "${ISOLATED_REMOVE_WORKTREE_ON_READY:-1}" != "0" ]]; then
  mkdir -p "$WORKTREE_SNAPSHOT_DIR"
  {
    printf 'worktree=%s\n' "$WORKTREE"
    printf 'base_sha=%s\n' "$BASE_SHA"
    printf 'ready_file=%s\n' "$READY_FILE"
    git -C "$WORKTREE" status --short --branch --untracked-files=all 2>/dev/null || true
  } > "$WORKTREE_SNAPSHOT_DIR/status.txt"
  git -C "$WORKTREE" diff --binary > "$WORKTREE_SNAPSHOT_DIR/diff.patch" 2>/dev/null || true
  git -C "$WORKTREE" diff --cached --binary > "$WORKTREE_SNAPSHOT_DIR/diff-cached.patch" 2>/dev/null || true
  git -C "$WORKTREE" ls-files --others --exclude-standard > "$WORKTREE_SNAPSHOT_DIR/untracked-files.txt" 2>/dev/null || true
  if [[ -s "$WORKTREE_SNAPSHOT_DIR/untracked-files.txt" ]]; then
    tar -C "$WORKTREE" -czf "$WORKTREE_SNAPSHOT_DIR/untracked.tar.gz" -T "$WORKTREE_SNAPSHOT_DIR/untracked-files.txt" 2>/dev/null || true
  fi
  git -C "$ROOT" worktree remove --force "$WORKTREE" || true
  git -C "$ROOT" worktree prune || true
  printf 'Worktree snapshot: %s\n' "$WORKTREE_SNAPSHOT_DIR"
fi
