#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATE_DIR="$ROOT/.tmux-team/tmp"
LOG_DIR="$ROOT/.tmux-team/logs"
DEFAULT_QUEUE="$STATE_DIR/capacity-executor-queue.tsv"

QUEUE_FILE="${CAPACITY_EXECUTOR_QUEUE_FILE:-$DEFAULT_QUEUE}"
TARGET_WEIGHT="${CAPACITY_EXECUTOR_TARGET_WEIGHT:-12}"
MAX_WEIGHT="${CAPACITY_EXECUTOR_MAX_WEIGHT:-14}"
MAX_LAUNCH="${CAPACITY_EXECUTOR_MAX_LAUNCH_PER_PASS:-6}"
GO_ACTIVE_WEIGHT="${CAPACITY_EXECUTOR_GO_ACTIVE_WEIGHT:-4}"
GO_ROW_CAP="${CAPACITY_EXECUTOR_GO_ROW_CAP:-4}"
ALLOW_ROOT_PHP="${CAPACITY_EXECUTOR_ALLOW_ROOT_PHP:-1}"
MODE="once"
INTERVAL_SECONDS="${CAPACITY_EXECUTOR_INTERVAL_SECONDS:-15}"
DRY_RUN=0
AUDIT_ALWAYS=0
FEEDER_ENABLED="${CAPACITY_EXECUTOR_FEEDER_ENABLED:-1}"
FEEDER_MAX_ROWS="${CAPACITY_EXECUTOR_FEEDER_MAX_ROWS:-12}"

usage() {
  printf 'usage: %s [--once|--loop] [--queue path] [--target slots] [--max-launch n] [--dry-run] [--audit-always] [--feed|--no-feed] [--feeder-max-rows n]\n' "$0" >&2
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --once)
      MODE="once"
      shift
      ;;
    --loop)
      MODE="loop"
      shift
      ;;
    --queue)
      QUEUE_FILE="${2:?--queue requires a path}"
      shift 2
      ;;
    --target)
      TARGET_WEIGHT="${2:?--target requires a weighted slot count}"
      shift 2
      ;;
    --max-launch)
      MAX_LAUNCH="${2:?--max-launch requires a count}"
      shift 2
      ;;
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    --audit-always)
      AUDIT_ALWAYS=1
      shift
      ;;
    --feed)
      FEEDER_ENABLED=1
      shift
      ;;
    --no-feed)
      FEEDER_ENABLED=0
      shift
      ;;
    --feeder-max-rows)
      FEEDER_MAX_ROWS="${2:?--feeder-max-rows requires a count}"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      usage
      exit 64
      ;;
  esac
done

case "$GO_ACTIVE_WEIGHT" in
  ''|*[!0-9]*)
    printf 'CAPACITY_EXECUTOR_GO_ACTIVE_WEIGHT must be a positive integer\n' >&2
    exit 64
    ;;
esac
if [ "$GO_ACTIVE_WEIGHT" -lt 1 ]; then
  printf 'CAPACITY_EXECUTOR_GO_ACTIVE_WEIGHT must be a positive integer\n' >&2
  exit 64
fi

case "$GO_ROW_CAP" in
  ''|*[!0-9]*)
    printf 'CAPACITY_EXECUTOR_GO_ROW_CAP must be a positive integer\n' >&2
    exit 64
    ;;
esac
if [ "$GO_ROW_CAP" -lt 1 ]; then
  printf 'CAPACITY_EXECUTOR_GO_ROW_CAP must be a positive integer\n' >&2
  exit 64
fi

case "$ALLOW_ROOT_PHP" in
  0|1) ;;
  *)
    printf 'CAPACITY_EXECUTOR_ALLOW_ROOT_PHP must be 0 or 1\n' >&2
    exit 64
    ;;
esac

case "$QUEUE_FILE" in
  /*) ;;
  *) QUEUE_FILE="$ROOT/$QUEUE_FILE" ;;
esac

mkdir -p "$STATE_DIR" "$LOG_DIR" "$ROOT/audits" "$(dirname "$QUEUE_FILE")"

LOCK_FILE="$STATE_DIR/port-capacity-executor-queue.lock"
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  printf '%s another capacity executor queue already holds %s; exiting\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$LOCK_FILE" >&2
  exit 0
fi

utc() {
  date -u +%Y-%m-%dT%H:%M:%SZ
}

compact_utc() {
  date -u +%Y%m%dT%H%M%SZ
}

line_from_fields() {
  local first=1 field
  for field in "$@"; do
    if [ "$first" -eq 0 ]; then
      printf '\t'
    fi
    printf '%s' "$field"
    first=0
  done
}

split_tsv_line() {
  local input="$1"
  fields=()
  while [[ "$input" == *$'\t'* ]]; do
    fields+=("${input%%$'\t'*}")
    input="${input#*$'\t'}"
  done
  fields+=("$input")
}

append_action() {
  ACTIONS+=("$1")
}

append_changed_file() {
  CHANGED_FILES+=("$1")
}

run_queue_feeder() {
  local feeder="$ROOT/scripts/run-capacity-queue-feeder.sh"
  local status line changed=0 feeder_output
  local feeder_lines=()

  [ "$FEEDER_ENABLED" -eq 1 ] || return 0
  if [ ! -f "$feeder" ]; then
    append_action "feeder skipped: scripts/run-capacity-queue-feeder.sh not present"
    return 0
  fi

  feeder_output="$STATE_DIR/capacity-feeder-output.$$"
  status=0
  if [ "$DRY_RUN" -eq 1 ]; then
    bash "$feeder" --lock-held --dry-run --queue "$QUEUE_FILE" --max-rows "$FEEDER_MAX_ROWS" > "$feeder_output" 2>&1 || status=$?
  else
    bash "$feeder" --lock-held --queue "$QUEUE_FILE" --max-rows "$FEEDER_MAX_ROWS" > "$feeder_output" 2>&1 || status=$?
  fi
  mapfile -t feeder_lines < "$feeder_output" || true
  rm -f "$feeder_output"
  for line in "${feeder_lines[@]}"; do
    [ -n "$line" ] || continue
    append_action "feeder: $line"
    case "$line" in
      queued\ *)
        changed=1
        ;;
    esac
  done
  if [ "$status" -ne 0 ]; then
    append_action "feeder failed with exit $status"
    return 0
  fi
  if [ "$changed" -eq 1 ]; then
    QUEUE_CHANGED=1
    append_changed_file "$(queue_rel "$QUEUE_FILE")"
  fi
}

queue_rel() {
  local path="$1"
  case "$path" in
    "$ROOT"/*) printf '%s' "${path#"$ROOT"/}" ;;
    *) printf '%s' "$path" ;;
  esac
}

init_queue_file() {
  if [ -e "$QUEUE_FILE" ]; then
    return
  fi
  if [ "$DRY_RUN" -eq 1 ]; then
    append_action "dry-run: would create queue file $(queue_rel "$QUEUE_FILE")"
    return
  fi
  {
    printf '# capacity executor queue v1\n'
    printf '# Columns are tab-separated:\n'
    printf '# state id kind weight session prompt log audit scratch scope_key args...\n'
    printf '# state: ready, running, done, blocked\n'
    printf '# kinds: sqlite_tcl_exact, sqlite_tcl_bounded, rclone_go_exact, dolt_bats_exact, php_focused_dirty, php_focused_clean, php_root_dirty, php_root_clean\n'
    printf '# paths must stay under .tmux-team/prompts/capacity-*.md, .tmux-team/logs/port-capacity-*.log, audits/capacity-*.md, and .upstream-cache/capacity-*/\n'
  } > "$QUEUE_FILE"
  append_changed_file "$(queue_rel "$QUEUE_FILE")"
  append_action "created empty queue file $(queue_rel "$QUEUE_FILE")"
}

ps_count() {
  local awk_program="$1"
  ps -eo args=ww | awk "$awk_program"
}

count_root_php() {
  ps -eo comm=,args=ww | awk '$1 == "php" && $3 ~ /(^|\/)tools\/run-tests\.php$/ && NF == 3 { count++ } END { print count + 0 }'
}

count_focused_php() {
  ps -eo comm=,args=ww | awk '$1 == "php" && $3 ~ /(^|\/)tools\/run-tests\.php$/ && NF > 3 { count++ } END { print count + 0 }'
}

count_cargo() {
  ps -eo comm=,args=ww | awk '$1 == "cargo" && $3 == "test" { count++ } END { print count + 0 }'
}

count_go() {
  ps -eo comm=,args=ww | awk '$1 == "go" && $3 == "test" { count++ } END { print count + 0 }'
}

count_bats() {
  ps -eo comm=,args=ww | awk '$1 == "bats" { count++ } END { print count + 0 }'
}

count_testfixture() {
  ps -eo comm=,args=ww | awk '$1 == "testfixture" { count++ } END { print count + 0 }'
}

count_sqlite_broad_testrunner() {
  ps -eo comm=,args=ww | awk '
    $1 == "testfixture" &&
    $0 ~ /(^|[[:space:]])[^[:space:]]*testrunner\.tcl([[:space:]]|$)/ &&
    $0 ~ /--jobs(=|[[:space:]])/ {
      jobs = 0
      for (i = 2; i <= NF; i++) {
        if ($i == "--jobs" && i + 1 <= NF && $(i + 1) ~ /^[0-9]+$/) {
          jobs = $(i + 1) + 0
          break
        }
        if ($i ~ /^--jobs=[0-9]+$/) {
          split($i, parts, "=")
          jobs = parts[2] + 0
          break
        }
      }
      if (jobs < 1) {
        jobs = 2
      }
      count++
      slots += jobs
    }
    END { printf "%d\t%d\n", count + 0, slots + 0 }
  '
}

count_sqlite_child_testfixture() {
  ps -eo comm=,args=ww | awk '$1 == "testfixture" && $0 !~ /--jobs(=|[[:space:]])/ { count++ } END { print count + 0 }'
}

count_capacity_launchers() {
  ps_count '$0 ~ /run-tmux-agent\.sh[[:space:]]+port-capacity-/ && $0 !~ /awk / { count++ } END { print count + 0 }'
}

sample_resources() {
  local sqlite_counts
  LOADAVG="$(cut -d' ' -f1-3 /proc/loadavg)"
  LOAD1="${LOADAVG%% *}"
  MEM_KB="$(awk '/MemAvailable:/ {print $2}' /proc/meminfo)"
  ROOT_AVAIL_KB="$(df -Pk / | awk 'NR==2 {print $4}')"
  TMP_USE_PCT="$(df -P /tmp | awk 'NR==2 {gsub(/%/, "", $5); print $5}')"
  TMP_INODE_PCT="$(df -Pi /tmp | awk 'NR==2 {gsub(/%/, "", $5); print $5}')"
  ROOT_PHP_COUNT="$(count_root_php)"
  FOCUSED_PHP_COUNT="$(count_focused_php)"
  CARGO_COUNT="$(count_cargo)"
  GO_COUNT="$(count_go)"
  BATS_COUNT="$(count_bats)"
  TESTFIXTURE_COUNT="$(count_testfixture)"
  sqlite_counts="$(count_sqlite_broad_testrunner)"
  SQLITE_BROAD_RUNNER_COUNT="${sqlite_counts%%$'\t'*}"
  SQLITE_BROAD_JOB_SLOTS="${sqlite_counts#*$'\t'}"
  if [ "$SQLITE_BROAD_JOB_SLOTS" = "$sqlite_counts" ]; then
    SQLITE_BROAD_JOB_SLOTS=0
  fi
  SQLITE_CHILD_COUNT="$(count_sqlite_child_testfixture)"
  SQLITE_ACCOUNTED_SLOTS=$((TESTFIXTURE_COUNT * 2))
  SQLITE_ACCOUNTING_MODE="individual-testfixture-count"
  SQLITE_EXCLUSIVE_ACTIVE=0
  if [ "$SQLITE_BROAD_RUNNER_COUNT" -gt 0 ]; then
    SQLITE_EXCLUSIVE_ACTIVE=1
    if awk -v x="$LOAD1" 'BEGIN { exit !(x >= 8) }'; then
      SQLITE_ACCOUNTED_SLOTS="$SQLITE_BROAD_JOB_SLOTS"
      [ "$SQLITE_ACCOUNTED_SLOTS" -lt 2 ] && SQLITE_ACCOUNTED_SLOTS=2
      SQLITE_ACCOUNTING_MODE="broad-testrunner-jobs-load-ge-8"
    else
      SQLITE_ACCOUNTED_SLOTS="$SQLITE_CHILD_COUNT"
      [ "$SQLITE_ACCOUNTED_SLOTS" -lt 2 ] && SQLITE_ACCOUNTED_SLOTS=2
      SQLITE_ACCOUNTING_MODE="broad-testrunner-children-load-lt-8"
    fi
  fi
  CAPACITY_LAUNCHER_COUNT="$(count_capacity_launchers)"
  ACTIVE_WEIGHT=$((ROOT_PHP_COUNT * 4 + FOCUSED_PHP_COUNT + CARGO_COUNT * 2 + GO_COUNT * GO_ACTIVE_WEIGHT + BATS_COUNT + SQLITE_ACCOUNTED_SLOTS))
}

resource_stop_reason() {
  if awk -v x="$LOAD1" 'BEGIN { exit !(x >= 14.3) }'; then
    printf 'load1 %s >= 14.3' "$LOAD1"
    return
  fi
  if [ "$MEM_KB" -lt $((5 * 1024 * 1024)) ]; then
    printf 'MemAvailable %s KiB < 5 GiB' "$MEM_KB"
    return
  fi
  if [ "$ROOT_AVAIL_KB" -lt $((80 * 1024 * 1024)) ]; then
    printf 'root free %s KiB < 80 GiB' "$ROOT_AVAIL_KB"
    return
  fi
  if [ "$TMP_USE_PCT" -gt 85 ]; then
    printf '/tmp use %s%% > 85%%' "$TMP_USE_PCT"
    return
  fi
  if [ "$TMP_INODE_PCT" -gt 89 ]; then
    printf '/tmp inode use %s%% > 89%%' "$TMP_INODE_PCT"
    return
  fi
  if [ "$CAPACITY_LAUNCHER_COUNT" -gt 28 ]; then
    printf 'live capacity launchers %s > 28' "$CAPACITY_LAUNCHER_COUNT"
    return
  fi
  if ! command -v tmux >/dev/null 2>&1; then
    printf 'tmux not found'
    return
  fi
}

session_active() {
  local session="$1"
  command -v tmux >/dev/null 2>&1 || return 1
  tmux has-session -t "$session" >/dev/null 2>&1
}

expected_weight() {
  case "$1" in
    php_root_dirty|php_root_clean) printf '4' ;;
    php_focused_dirty|php_focused_clean) printf '1' ;;
    rclone_go_exact) printf '2' ;;
    sqlite_tcl_exact|sqlite_tcl_bounded) printf '2' ;;
    dolt_bats_exact) printf '1' ;;
    *) return 1 ;;
  esac
}

sqlite_kind() {
  case "$1" in
    sqlite_tcl_exact|sqlite_tcl_bounded) return 0 ;;
    *) return 1 ;;
  esac
}

root_php_kind() {
  case "$1" in
    php_root_dirty|php_root_clean) return 0 ;;
    *) return 1 ;;
  esac
}

php_queue_kind() {
  case "$1" in
    php_root_dirty|php_root_clean|php_focused_dirty|php_focused_clean) return 0 ;;
    *) return 1 ;;
  esac
}

go_kind() {
  case "$1" in
    rclone_go_exact) return 0 ;;
    *) return 1 ;;
  esac
}

load_source_header() {
  CURRENT_HEAD_SHORT="$(git -C "$ROOT" rev-parse --short=12 HEAD 2>/dev/null || printf unknown)"
  CURRENT_SOURCE_DIRTY_KEY=""
  if [ -e "$QUEUE_FILE" ]; then
    local source_line part
    source_line="$(grep -E '^# capacity-source ' "$QUEUE_FILE" | tail -n 1 || true)"
    for part in $source_line; do
      case "$part" in
        dirty_key=*)
          CURRENT_SOURCE_DIRTY_KEY="${part#dirty_key=}"
          ;;
      esac
    done
  fi
}

stale_php_ready_row() {
  local kind="$1"
  local scope="$2"
  local prefix row_head row_dirty rest

  php_queue_kind "$kind" || return 1
  IFS=':' read -r prefix row_head row_dirty rest <<< "$scope"
  case "$prefix" in
    php-clean)
      [ "$row_head" != "$CURRENT_HEAD_SHORT" ]
      ;;
    php-dirty|dirty-root)
      [ "$row_head" != "$CURRENT_HEAD_SHORT" ] && return 0
      if [ -n "$CURRENT_SOURCE_DIRTY_KEY" ] && [ "$CURRENT_SOURCE_DIRTY_KEY" != clean ]; then
        [ "$row_dirty" != "$CURRENT_SOURCE_DIRTY_KEY" ]
      else
        return 1
      fi
      ;;
    *)
      return 1
      ;;
  esac
}

accounting_weight() {
  local kind="$1"
  local queue_weight="$2"
  case "$kind" in
    rclone_go_exact) printf '%s' "$GO_ACTIVE_WEIGHT" ;;
    *) printf '%s' "$queue_weight" ;;
  esac
}

validate_rel_path() {
  local value="$1"
  local pattern="$2"
  case "$value" in
    /*|*..*|*'	'*|'') return 1 ;;
  esac
  case "$value" in
    $pattern) return 0 ;;
    *) return 1 ;;
  esac
}

validate_job_fields() {
  local -n fields_ref="$1"
  local state="${fields_ref[0]}"
  local id="${fields_ref[1]}"
  local kind="${fields_ref[2]}"
  local weight="${fields_ref[3]}"
  local session="${fields_ref[4]}"
  local prompt="${fields_ref[5]}"
  local log="${fields_ref[6]}"
  local audit="${fields_ref[7]}"
  local scratch="${fields_ref[8]}"
  local scope_key="${fields_ref[9]}"
  local want_weight

  case "$state" in
    ready|running|done|blocked) ;;
    *) printf 'invalid state %s' "$state"; return 1 ;;
  esac
  case "$id" in
    *[!A-Za-z0-9_.:-]*|'') printf 'invalid id %s' "$id"; return 1 ;;
  esac
  case "$session" in
    port-capacity-*) ;;
    *) printf 'session must start with port-capacity-'; return 1 ;;
  esac
  case "$session" in
    *[!A-Za-z0-9_.:-]*) printf 'invalid session %s' "$session"; return 1 ;;
  esac
  want_weight="$(expected_weight "$kind" 2>/dev/null || true)"
  if [ -z "$want_weight" ]; then
    printf 'unsupported kind %s' "$kind"
    return 1
  fi
  case "$weight" in
    ''|*[!0-9]*) printf 'invalid weight %s' "$weight"; return 1 ;;
  esac
  if [ "$weight" -ne "$want_weight" ]; then
    printf 'weight %s does not match %s expected weight %s' "$weight" "$kind" "$want_weight"
    return 1
  fi
  validate_rel_path "$prompt" '.tmux-team/prompts/capacity-*.md' || {
    printf 'prompt path outside allowed capacity prompt scope'
    return 1
  }
  validate_rel_path "$log" '.tmux-team/logs/port-capacity-*.log' || {
    printf 'log path outside allowed capacity log scope'
    return 1
  }
  validate_rel_path "$audit" 'audits/capacity-*.md' || {
    printf 'audit path outside allowed capacity audit scope'
    return 1
  }
  validate_rel_path "$scratch" '.upstream-cache/capacity-*' || {
    printf 'scratch path outside root-backed capacity scratch scope'
    return 1
  }
  case "$scope_key" in
    ''|*'	'*) printf 'invalid scope key'; return 1 ;;
  esac
}

make_prompt_file() {
  local id="$1"
  local kind="$2"
  local weight="$3"
  local session="$4"
  local prompt="$5"
  local log="$6"
  local audit="$7"
  local scratch="$8"
  local scope_key="$9"
  shift 9
  local args=("$@")
  local prompt_abs="$ROOT/$prompt"
  local arg

  if [ -e "$prompt_abs" ]; then
    return 0
  fi
  if [ "$DRY_RUN" -eq 1 ]; then
    append_action "dry-run: would create reservation prompt $prompt"
    return 0
  fi
  mkdir -p "$(dirname "$prompt_abs")"
  {
    printf 'You are a direct capacity executor reservation for the supervised native PHP porting project at `/home/claude/port-libs`.\n\n'
    printf 'This prompt is an inspectable reservation record for a shell-dispatched runner. It is not intended for a Codex planning pass.\n\n'
    printf '## Reservation\n\n'
    printf -- '- ID: `%s`\n' "$id"
    printf -- '- Kind: `%s`\n' "$kind"
    printf -- '- Weighted slots: `%s`\n' "$weight"
    printf -- '- Session: `%s`\n' "$session"
    printf -- '- Log: `%s`\n' "$log"
    printf -- '- Audit: `%s`\n' "$audit"
    printf -- '- Scratch: `%s`\n' "$scratch"
    printf -- '- Scope key: `%s`\n\n' "$scope_key"
    printf '## Hard Constraints\n\n'
    printf -- '- Do not read, print, copy, or dump secret values.\n'
    printf -- '- Do not inspect process environments, credential stores, provider config files, OAuth/browser auth state, cloud remotes, or other secret-bearing inputs.\n'
    printf -- '- Do not edit lane source, tests, fixtures, examples, manifests, lane status files, `progress.md`, `porting.html`, or `porting-summary.json`.\n'
    printf -- '- Do not stage, commit, push, publish, merge, rebase, reset, or revert.\n'
    printf -- '- Do not run live-service provider tests or unbounded upstream sweeps.\n'
    printf -- '- Use the owned root-backed scratch path above for temp/cache output.\n\n'
    printf '## Executor Args\n\n'
    if [ "${#args[@]}" -eq 0 ]; then
      printf 'No additional args.\n'
    else
      for arg in "${args[@]}"; do
        printf -- '- `%s`\n' "$arg"
      done
    fi
  } > "$prompt_abs"
  append_changed_file "$prompt"
  append_action "created reservation prompt $prompt"
}

command_for_job() {
  local cmd_file="$1"
  local id="$2"
  local kind="$3"
  local audit="$4"
  local scratch="$5"
  local log="$6"
  shift 6
  local args=("$@")
  local helper
  local cmd=()

  case "$kind" in
    sqlite_tcl_exact)
      if [ "${#args[@]}" -lt 1 ]; then
        printf 'sqlite_tcl_exact requires at least one exact test file'
        return 1
      fi
      helper="$ROOT/scripts/run-sqlite-tcl-exact-shard.sh"
      cmd=("$helper" "$id" "$audit" "$scratch" "$log" "${args[@]}")
      ;;
    sqlite_tcl_bounded)
      if [ "${#args[@]}" -lt 3 ]; then
        printf 'sqlite_tcl_bounded requires testset, jobs, timeout, and optional patterns'
        return 1
      fi
      helper="$ROOT/scripts/run-sqlite-tcl-bounded-runner.sh"
      cmd=("$helper" "$id" "$audit" "$scratch" "$log" "${args[@]}")
      ;;
    rclone_go_exact)
      if [ "${#args[@]}" -ne 3 ]; then
        printf 'rclone_go_exact requires package, selector, expected-tests-csv'
        return 1
      fi
      helper="$ROOT/scripts/run-rclone-go-exact-selector.sh"
      cmd=("$helper" "$id" "$audit" "$scratch" "$log" "${args[@]}")
      ;;
    dolt_bats_exact)
      if [ "${#args[@]}" -lt 1 ]; then
        printf 'dolt_bats_exact requires at least one BATS file'
        return 1
      fi
      local arg
      for arg in "${args[@]}"; do
        case "$arg" in
          ''|/*|*/*|*..*|*'	'*)
            printf 'dolt_bats_exact arg must be a simple .bats file name: %s' "$arg"
            return 1
            ;;
        esac
        case "$arg" in
          *.bats) ;;
          *)
            printf 'dolt_bats_exact arg must end in .bats: %s' "$arg"
            return 1
            ;;
        esac
      done
      helper="$ROOT/scripts/run-dolt-bats-exact-shard.sh"
      cmd=("$helper" "$id" "$audit" "$scratch" "$log" "${args[@]}")
      ;;
    php_focused_dirty)
      if [ "${#args[@]}" -lt 1 ]; then
        printf 'php_focused_dirty requires at least one focused path'
        return 1
      fi
      helper="$ROOT/scripts/run-php-focused-shard.sh"
      cmd=("$helper" "$id" "$audit" "$scratch" "$log" "${args[@]}")
      ;;
    php_focused_clean)
      if [ "${#args[@]}" -lt 1 ]; then
        printf 'php_focused_clean requires at least one focused path'
        return 1
      fi
      helper="$ROOT/scripts/run-php-clean-head-focused-shard.sh"
      cmd=("$helper" "$id" "$audit" "$scratch" "$log" "${args[@]}")
      ;;
    php_root_dirty)
      if [ "${#args[@]}" -ne 0 ]; then
        printf 'php_root_dirty does not accept additional args'
        return 1
      fi
      helper="$ROOT/scripts/run-php-dirty-root.sh"
      cmd=("$helper" "$id" "$audit" "$scratch" "$log")
      ;;
    php_root_clean)
      if [ "${#args[@]}" -ne 0 ]; then
        printf 'php_root_clean does not accept additional args'
        return 1
      fi
      helper="$ROOT/scripts/run-php-clean-head-root.sh"
      cmd=("$helper" "$id" "$audit" "$scratch" "$log")
      ;;
    *)
      printf 'unsupported kind %s' "$kind"
      return 1
      ;;
  esac

  if [ ! -x "$helper" ]; then
    printf 'helper is missing or not executable: %s' "$(queue_rel "$helper")"
    return 1
  fi

  if [ "$DRY_RUN" -eq 1 ]; then
    append_action "dry-run: would write command file $(queue_rel "$cmd_file")"
    return 0
  fi
  {
    printf '#!/usr/bin/env bash\n'
    printf 'set -euo pipefail\n'
    printf 'cd %q\n' "$ROOT"
    printf 'exec'
    local part
    for part in "${cmd[@]}"; do
      printf ' %q' "$part"
    done
    printf '\n'
  } > "$cmd_file"
  chmod +x "$cmd_file"
  append_changed_file "$(queue_rel "$cmd_file")"
}

write_report() {
  local path="$1"
  local rel_path
  rel_path="$(queue_rel "$path")"
  mkdir -p "$(dirname "$path")"
  {
    printf '# Capacity Executor Queue Report - %s\n\n' "$REPORT_STAMP"
    printf '## Status\n\n'
    printf -- '- Queue: `%s`\n' "$(queue_rel "$QUEUE_FILE")"
    printf -- '- Dry run: `%s`\n' "$DRY_RUN"
    printf -- '- Target weighted slots: `%s`\n' "$TARGET_WEIGHT"
    printf -- '- Max weighted slots: `%s`\n' "$MAX_WEIGHT"
    printf -- '- Max launches this pass: `%s`\n' "$MAX_LAUNCH"
    printf -- '- Root PHP launch enabled: `%s` (`CAPACITY_EXECUTOR_ALLOW_ROOT_PHP`)\n' "$ALLOW_ROOT_PHP"
    printf -- '- Current source head: `%s`\n' "${CURRENT_HEAD_SHORT:-unknown}"
    printf -- '- Current source dirty key: `%s`\n' "${CURRENT_SOURCE_DIRTY_KEY:-unknown}"
    printf -- '- Go live process accounting weight: `%s` (`CAPACITY_EXECUTOR_GO_ACTIVE_WEIGHT`)\n' "$GO_ACTIVE_WEIGHT"
    printf -- '- Go active/proposed row cap: `%s` (`CAPACITY_EXECUTOR_GO_ROW_CAP`)\n' "$GO_ROW_CAP"
    printf -- '- Stop reason: `%s`\n' "${STOP_REASON:-none}"
    printf -- '- Launched: `%s`\n' "$LAUNCHED_COUNT"
    printf -- '- Blocked: `%s`\n' "$BLOCKED_COUNT"
    printf -- '- Marked done: `%s`\n' "$DONE_COUNT"
    printf -- '- Queue changed: `%s`\n\n' "$QUEUE_CHANGED"
    printf '## Resources\n\n'
    printf -- '- loadavg: `%s`\n' "$LOADAVG"
    printf -- '- MemAvailable: `%s KiB`\n' "$MEM_KB"
    printf -- '- root free: `%s KiB`\n' "$ROOT_AVAIL_KB"
    printf -- '- /tmp use: `%s%%`\n' "$TMP_USE_PCT"
    printf -- '- /tmp inode use: `%s%%`\n' "$TMP_INODE_PCT"
    printf -- '- live capacity launchers: `%s`\n\n' "$CAPACITY_LAUNCHER_COUNT"
    printf -- '- SQLite broad testrunner count: `%s`\n' "$SQLITE_BROAD_RUNNER_COUNT"
    printf -- '- SQLite broad declared job slots: `%s`\n' "$SQLITE_BROAD_JOB_SLOTS"
    printf -- '- SQLite child testfixtures: `%s`\n' "$SQLITE_CHILD_COUNT"
    printf -- '- SQLite accounting mode: `%s`\n' "$SQLITE_ACCOUNTING_MODE"
    printf -- '- SQLite exclusive active: `%s`\n\n' "$SQLITE_EXCLUSIVE_ACTIVE"
    printf '## Active Weighted Slots\n\n'
    printf '| Runner | Count | Queue Row Weight | Active Weight Each | Slots |\n'
    printf '| --- | ---: | ---: | ---: | ---: |\n'
    printf '| root PHP | `%s` | 4 | 4 | `%s` |\n' "$ROOT_PHP_COUNT" "$((ROOT_PHP_COUNT * 4))"
    printf '| focused PHP | `%s` | 1 | 1 | `%s` |\n' "$FOCUSED_PHP_COUNT" "$FOCUSED_PHP_COUNT"
    printf '| Cargo | `%s` | n/a | 2 | `%s` |\n' "$CARGO_COUNT" "$((CARGO_COUNT * 2))"
    printf '| Go | `%s` | 2 | `%s` | `%s` |\n' "$GO_COUNT" "$GO_ACTIVE_WEIGHT" "$((GO_COUNT * GO_ACTIVE_WEIGHT))"
    printf '| BATS | `%s` | n/a | 1 | `%s` |\n' "$BATS_COUNT" "$BATS_COUNT"
    printf '| SQLite testfixture executables | `%s` | 2 | measured | `%s` |\n' "$TESTFIXTURE_COUNT" "$SQLITE_ACCOUNTED_SLOTS"
    printf '| SQLite broad declared jobs | `%s` | 2 | declared | `%s` |\n' "$SQLITE_BROAD_RUNNER_COUNT" "$SQLITE_BROAD_JOB_SLOTS"
    printf '| SQLite broad child testfixtures | `%s` | 2 | measured | `%s` |\n\n' "$SQLITE_CHILD_COUNT" "$SQLITE_CHILD_COUNT"
    printf '## Go Row Cap\n\n'
    printf -- '- Active Go queue rows at pass sample: `%s`\n' "$GO_ACTIVE_ROW_COUNT"
    printf -- '- Proposed Go rows this pass: `%s`\n' "$GO_PROPOSED_ROW_COUNT"
    printf -- '- Proposed Go accounting slots this pass: `%s`\n' "$GO_PROPOSED_WEIGHT"
    printf -- '- Go row cap check: active + proposed must stay <= `%s`\n\n' "$GO_ROW_CAP"
    printf -- '- Active weighted total before launches: `%s`\n' "$ACTIVE_WEIGHT"
    printf -- '- Proposed weighted slots launched this pass: `%s`\n' "$PROPOSED_WEIGHT"
    printf -- '- Root PHP row proposed/launched this pass: `%s`\n\n' "${ROOT_PHP_PROPOSED_ID:-none}"
    printf '## Actions\n\n'
    if [ "${#ACTIONS[@]}" -eq 0 ]; then
      printf 'No queue action this pass.\n'
    else
      local action
      for action in "${ACTIONS[@]}"; do
        printf -- '- %s\n' "$action"
      done
    fi
    printf '\n## Changed Files\n\n'
    if [ "${#CHANGED_FILES[@]}" -eq 0 ]; then
      printf 'No files changed by this pass.\n'
    else
      local changed
      for changed in "${CHANGED_FILES[@]}"; do
        printf -- '- `%s`\n' "$changed"
      done
    fi
    printf '\n## Boundary\n\n'
    printf 'The executor dispatches only pre-vetted queue rows mapped to known bounded helper scripts. It does not run arbitrary shell commands, inspect secrets, edit lane source, stage, commit, push, publish, or run live-service provider tests.\n'
  } > "$path"
  append_changed_file "$rel_path"
}

process_once() {
  ACTIONS=()
  CHANGED_FILES=()
  LAUNCHED_COUNT=0
  BLOCKED_COUNT=0
  DONE_COUNT=0
  QUEUE_CHANGED=0
  PROPOSED_WEIGHT=0
  GO_ACTIVE_ROW_COUNT=0
  GO_PROPOSED_ROW_COUNT=0
  GO_PROPOSED_WEIGHT=0
  ROOT_PHP_PROPOSED=0
  ROOT_PHP_PROPOSED_ID=""
  REPORT_STAMP="$(compact_utc)"

  init_queue_file
  run_queue_feeder
  load_source_header
  sample_resources
  STOP_REASON="$(resource_stop_reason || true)"

  local queue_lines=()
  local first_pass_lines=()
  local final_lines=()
  local line
  local validation_error
  if [ -e "$QUEUE_FILE" ]; then
    mapfile -t queue_lines < "$QUEUE_FILE" || true
  fi

  declare -A RUNNING_SCOPES=()

  for line in "${queue_lines[@]}"; do
    if [ -z "$line" ] || [[ "$line" == \#* ]]; then
      first_pass_lines+=("$line")
      continue
    fi

    case "$line" in
      ready$'\t'*|done$'\t'*)
        first_pass_lines+=("$line")
        continue
        ;;
    esac

    split_tsv_line "$line"
    if [ "${#fields[@]}" -lt 10 ]; then
      first_pass_lines+=("$line")
      append_action "blocked malformed row with fewer than 10 columns"
      BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
      continue
    fi

    if [ "${fields[0]}" = "blocked" ] && [ "${fields[2]}" = "dolt_bats_exact" ]; then
      last_field_index=$((${#fields[@]} - 1))
      if [ "${fields[$last_field_index]}" = "blocked:unsupported kind dolt_bats_exact" ]; then
        unset 'fields[$last_field_index]'
        fields[0]="ready"
        first_pass_lines+=("$(line_from_fields "${fields[@]}")")
        append_action "requeued ${fields[1]}: dolt_bats_exact is now supported"
        QUEUE_CHANGED=1
        continue
      fi
    fi

    case "${fields[0]}" in
      ready|done|blocked)
        first_pass_lines+=("$line")
        continue
        ;;
    esac

    validation_error="$(validate_job_fields fields 2>&1 || true)"
    if [ -n "$validation_error" ]; then
      fields[0]="blocked"
      fields+=("blocked:$validation_error")
      first_pass_lines+=("$(line_from_fields "${fields[@]}")")
      append_action "blocked ${fields[1]}: $validation_error"
      BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
      QUEUE_CHANGED=1
      continue
    fi

    if [ "${fields[0]}" = "running" ]; then
      if session_active "${fields[4]}"; then
        RUNNING_SCOPES["${fields[9]}"]=1
        if go_kind "${fields[2]}"; then
          GO_ACTIVE_ROW_COUNT=$((GO_ACTIVE_ROW_COUNT + 1))
        fi
      elif [ -e "$ROOT/${fields[7]}" ]; then
        fields[0]="done"
        first_pass_lines+=("$(line_from_fields "${fields[@]}")")
        append_action "marked ${fields[1]} done; session ended and audit exists"
        DONE_COUNT=$((DONE_COUNT + 1))
        QUEUE_CHANGED=1
        continue
      else
        fields[0]="blocked"
        fields+=("blocked:session ended without expected audit")
        first_pass_lines+=("$(line_from_fields "${fields[@]}")")
        append_action "blocked ${fields[1]}: session ended without expected audit"
        BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
        QUEUE_CHANGED=1
        continue
      fi
    fi

    first_pass_lines+=("$(line_from_fields "${fields[@]}")")
  done

  for line in "${first_pass_lines[@]}"; do
    if [ -z "$line" ] || [[ "$line" == \#* ]]; then
      final_lines+=("$line")
      continue
    fi

    case "$line" in
      ready$'\t'*) ;;
      *)
        final_lines+=("$line")
        continue
        ;;
    esac

    if [ -n "$STOP_REASON" ]; then
      final_lines+=("$line")
      continue
    fi
    if [ "$LAUNCHED_COUNT" -ge "$MAX_LAUNCH" ]; then
      final_lines+=("$line")
      continue
    fi
    if [ $((ACTIVE_WEIGHT + PROPOSED_WEIGHT)) -ge "$TARGET_WEIGHT" ]; then
      final_lines+=("$line")
      continue
    fi

    split_tsv_line "$line"
    if [ "${#fields[@]}" -lt 10 ]; then
      final_lines+=("$line")
      append_action "blocked malformed ready row with fewer than 10 columns"
      BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
      continue
    fi
    if [ "${fields[0]}" != "ready" ]; then
      final_lines+=("$line")
      continue
    fi

    validation_error="$(validate_job_fields fields 2>&1 || true)"
    if [ -n "$validation_error" ]; then
      fields[0]="blocked"
      fields+=("blocked:$validation_error")
      final_lines+=("$(line_from_fields "${fields[@]}")")
      append_action "blocked ${fields[1]}: $validation_error"
      BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
      QUEUE_CHANGED=1
      continue
    fi

    local id="${fields[1]}"
    local kind="${fields[2]}"
    local weight="${fields[3]}"
    local session="${fields[4]}"
    local prompt="${fields[5]}"
    local log="${fields[6]}"
    local audit="${fields[7]}"
    local scratch="${fields[8]}"
    local scope_key="${fields[9]}"
    local args=("${fields[@]:10}")
    local command_file="$STATE_DIR/capacity-executor-command-$id.sh"
    local command_error=""
    local account_weight
    account_weight="$(accounting_weight "$kind" "$weight")"

    if stale_php_ready_row "$kind" "$scope_key"; then
      fields[0]="blocked"
      fields+=("blocked:stale source snapshot current_head=$CURRENT_HEAD_SHORT current_dirty_key=${CURRENT_SOURCE_DIRTY_KEY:-unknown}")
      final_lines+=("$(line_from_fields "${fields[@]}")")
      append_action "blocked $id: stale PHP source snapshot ($scope_key; current head=$CURRENT_HEAD_SHORT dirty_key=${CURRENT_SOURCE_DIRTY_KEY:-unknown})"
      BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
      QUEUE_CHANGED=1
      continue
    fi

    if [ "$SQLITE_EXCLUSIVE_ACTIVE" -eq 1 ] && sqlite_kind "$kind"; then
      append_action "skipped $id: broad SQLite testrunner active; SQLite work remains exclusive"
      final_lines+=("$line")
      continue
    fi
    if root_php_kind "$kind"; then
      if [ "$ALLOW_ROOT_PHP" -eq 0 ]; then
        append_action "skipped $id: root PHP launches disabled by CAPACITY_EXECUTOR_ALLOW_ROOT_PHP=0"
        final_lines+=("$line")
        continue
      fi
      if [ "$ROOT_PHP_COUNT" -gt 0 ]; then
        append_action "skipped $id: root PHP already active at pass sample ($ROOT_PHP_COUNT no-argument php tools/run-tests.php)"
        final_lines+=("$line")
        continue
      fi
      if [ "$ROOT_PHP_PROPOSED" -eq 1 ]; then
        append_action "skipped $id: root PHP already proposed this pass ($ROOT_PHP_PROPOSED_ID); leaving row ready"
        final_lines+=("$line")
        continue
      fi
    fi
    if go_kind "$kind" && [ $((GO_ACTIVE_ROW_COUNT + GO_PROPOSED_ROW_COUNT)) -ge "$GO_ROW_CAP" ]; then
      append_action "skipped $id: Go row cap reached (active=$GO_ACTIVE_ROW_COUNT proposed=$GO_PROPOSED_ROW_COUNT cap=$GO_ROW_CAP)"
      final_lines+=("$line")
      continue
    fi
    if [ $((ACTIVE_WEIGHT + PROPOSED_WEIGHT + account_weight)) -gt "$MAX_WEIGHT" ]; then
      append_action "skipped $id: active plus proposed weight would exceed $MAX_WEIGHT"
      final_lines+=("$line")
      continue
    fi
    if [ -n "${RUNNING_SCOPES[$scope_key]:-}" ]; then
      append_action "skipped $id: scope key already running ($scope_key)"
      final_lines+=("$line")
      continue
    fi
    if session_active "$session"; then
      fields[0]="running"
      RUNNING_SCOPES["$scope_key"]=1
      if root_php_kind "$kind"; then
        ROOT_PHP_PROPOSED=1
        ROOT_PHP_PROPOSED_ID="$id"
      fi
      if go_kind "$kind"; then
        GO_ACTIVE_ROW_COUNT=$((GO_ACTIVE_ROW_COUNT + 1))
      fi
      final_lines+=("$(line_from_fields "${fields[@]}")")
      append_action "marked $id running; tmux session already exists"
      QUEUE_CHANGED=1
      continue
    fi
    if [ -e "$ROOT/$audit" ]; then
      fields[0]="blocked"
      fields+=("blocked:audit already exists")
      final_lines+=("$(line_from_fields "${fields[@]}")")
      append_action "blocked $id: audit already exists ($audit)"
      BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
      QUEUE_CHANGED=1
      continue
    fi
    if [ -e "$ROOT/$scratch" ]; then
      fields[0]="blocked"
      fields+=("blocked:scratch already exists")
      final_lines+=("$(line_from_fields "${fields[@]}")")
      append_action "blocked $id: scratch already exists ($scratch)"
      BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
      QUEUE_CHANGED=1
      continue
    fi
    if [ -e "$ROOT/$log" ]; then
      fields[0]="blocked"
      fields+=("blocked:log already exists")
      final_lines+=("$(line_from_fields "${fields[@]}")")
      append_action "blocked $id: log already exists ($log)"
      BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
      QUEUE_CHANGED=1
      continue
    fi

    make_prompt_file "$id" "$kind" "$weight" "$session" "$prompt" "$log" "$audit" "$scratch" "$scope_key" "${args[@]}"
    command_error="$(command_for_job "$command_file" "$id" "$kind" "$audit" "$scratch" "$log" "${args[@]}" 2>&1 || true)"
    if [ -n "$command_error" ]; then
      fields[0]="blocked"
      fields+=("blocked:$command_error")
      final_lines+=("$(line_from_fields "${fields[@]}")")
      append_action "blocked $id: $command_error"
      BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
      QUEUE_CHANGED=1
      continue
    fi

    if [ "$DRY_RUN" -eq 1 ]; then
      append_action "dry-run: would launch $session for $id"
      final_lines+=("$line")
      LAUNCHED_COUNT=$((LAUNCHED_COUNT + 1))
      PROPOSED_WEIGHT=$((PROPOSED_WEIGHT + account_weight))
      RUNNING_SCOPES["$scope_key"]=1
      if root_php_kind "$kind"; then
        ROOT_PHP_PROPOSED=1
        ROOT_PHP_PROPOSED_ID="$id"
      fi
      if go_kind "$kind"; then
        GO_PROPOSED_ROW_COUNT=$((GO_PROPOSED_ROW_COUNT + 1))
        GO_PROPOSED_WEIGHT=$((GO_PROPOSED_WEIGHT + account_weight))
      fi
      continue
    fi

    if tmux new-session -d -s "$session" "$command_file"; then
      fields[0]="running"
      final_lines+=("$(line_from_fields "${fields[@]}")")
      append_action "launched $session for $id"
      LAUNCHED_COUNT=$((LAUNCHED_COUNT + 1))
      PROPOSED_WEIGHT=$((PROPOSED_WEIGHT + account_weight))
      RUNNING_SCOPES["$scope_key"]=1
      if root_php_kind "$kind"; then
        ROOT_PHP_PROPOSED=1
        ROOT_PHP_PROPOSED_ID="$id"
      fi
      if go_kind "$kind"; then
        GO_PROPOSED_ROW_COUNT=$((GO_PROPOSED_ROW_COUNT + 1))
        GO_PROPOSED_WEIGHT=$((GO_PROPOSED_WEIGHT + account_weight))
      fi
      QUEUE_CHANGED=1
    else
      fields[0]="blocked"
      fields+=("blocked:tmux new-session failed")
      final_lines+=("$(line_from_fields "${fields[@]}")")
      append_action "blocked $id: tmux new-session failed"
      BLOCKED_COUNT=$((BLOCKED_COUNT + 1))
      QUEUE_CHANGED=1
    fi
  done

  if [ "$QUEUE_CHANGED" -eq 1 ] && [ "$DRY_RUN" -eq 0 ]; then
    local tmp_queue="$STATE_DIR/capacity-executor-queue.$REPORT_STAMP.$$"
    local out_line
    : > "$tmp_queue"
    for out_line in "${final_lines[@]}"; do
      printf '%s\n' "$out_line" >> "$tmp_queue"
    done
    mv "$tmp_queue" "$QUEUE_FILE"
    append_changed_file "$(queue_rel "$QUEUE_FILE")"
  fi

  local last_report="$STATE_DIR/capacity-executor-last.md"
  write_report "$last_report"

  if [ "$AUDIT_ALWAYS" -eq 1 ] || [ "$LAUNCHED_COUNT" -gt 0 ] || [ "$BLOCKED_COUNT" -gt 0 ] || [ "$DONE_COUNT" -gt 0 ]; then
    write_report "$ROOT/audits/capacity-executor-queue-$REPORT_STAMP.md"
  fi

  printf 'capacity executor queue: launched=%s blocked=%s done=%s active_weight=%s proposed_weight=%s go_active_weight=%s go_rows=%s/%s/%s sqlite_slots=%s sqlite_mode=%s stop=%s\n' \
    "$LAUNCHED_COUNT" "$BLOCKED_COUNT" "$DONE_COUNT" "$ACTIVE_WEIGHT" "$PROPOSED_WEIGHT" "$GO_ACTIVE_WEIGHT" "$GO_ACTIVE_ROW_COUNT" "$GO_PROPOSED_ROW_COUNT" "$GO_ROW_CAP" "$SQLITE_ACCOUNTED_SLOTS" "$SQLITE_ACCOUNTING_MODE" "${STOP_REASON:-none}"
}

case "$MODE" in
  once)
    process_once
    ;;
  loop)
    while true; do
      process_once
      sleep "$INTERVAL_SECONDS"
    done
    ;;
  *)
    usage
    exit 64
    ;;
esac
