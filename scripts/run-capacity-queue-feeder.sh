#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATE_DIR="$ROOT/.tmux-team/tmp"
DEFAULT_QUEUE="$STATE_DIR/capacity-executor-queue.tsv"
QUEUE_FILE="${CAPACITY_EXECUTOR_QUEUE_FILE:-$DEFAULT_QUEUE}"
LOCK_FILE="$STATE_DIR/port-capacity-executor-queue.lock"
DRY_RUN=0
LOCK_HELD=0
MAX_ROWS="${CAPACITY_QUEUE_FEEDER_MAX_ROWS:-12}"
INCLUDE_DIRTY=1

usage() {
  printf 'usage: %s [--queue path] [--dry-run] [--lock-held] [--max-rows n] [--clean-only]\n' "$0" >&2
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --queue)
      QUEUE_FILE="${2:?--queue requires a path}"
      shift 2
      ;;
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    --lock-held)
      LOCK_HELD=1
      shift
      ;;
    --max-rows)
      MAX_ROWS="${2:?--max-rows requires a count}"
      shift 2
      ;;
    --clean-only)
      INCLUDE_DIRTY=0
      shift
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

case "$MAX_ROWS" in
  ''|*[!0-9]*)
    printf 'capacity queue feeder: invalid --max-rows value %s\n' "$MAX_ROWS" >&2
    exit 64
    ;;
esac

case "$QUEUE_FILE" in
  /*) ;;
  *) QUEUE_FILE="$ROOT/$QUEUE_FILE" ;;
esac

mkdir -p "$STATE_DIR" "$(dirname "$QUEUE_FILE")"

if [ "$LOCK_HELD" -eq 0 ]; then
  exec 9>"$LOCK_FILE"
  if ! flock -n 9; then
    printf 'capacity queue feeder: executor lock held at %s; no queue changes\n' "$LOCK_FILE"
    exit 0
  fi
fi

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
    printf 'dry-run: would create queue file %s\n' "$(queue_rel "$QUEUE_FILE")"
    return
  fi
  {
    printf '# capacity executor queue v1\n'
    printf '# Columns are tab-separated:\n'
    printf '# state id kind weight session prompt log audit scratch scope_key args...\n'
    printf '# state: ready, running, done, blocked\n'
    printf '# feeder appends only: php_root_clean, php_root_dirty, php_focused_clean, php_focused_dirty, rclone_go_exact, sqlite_tcl_exact, sqlite_tcl_bounded, dolt_bats_exact\n'
    printf '# paths must stay under .tmux-team/prompts/capacity-*.md, .tmux-team/logs/port-capacity-*.log, audits/capacity-*.md, and .upstream-cache/capacity-*/\n'
  } > "$QUEUE_FILE"
}

expected_weight() {
  case "$1" in
    php_root_clean|php_root_dirty) printf '4' ;;
    php_focused_clean|php_focused_dirty) printf '1' ;;
    rclone_go_exact|sqlite_tcl_exact|sqlite_tcl_bounded) printf '2' ;;
    dolt_bats_exact) printf '1' ;;
    *) return 1 ;;
  esac
}

validate_arg() {
  local kind="$1"
  local arg="$2"
  case "$arg" in
    ''|/*|*..*|*'	'*)
      printf 'invalid arg path %s' "$arg"
      return 1
      ;;
  esac
  case "$kind" in
    php_focused_clean|php_focused_dirty)
      case "$arg" in
        lanes/*/tests|lanes/*/tests/*) ;;
        *)
          printf 'PHP focused arg outside lane tests: %s' "$arg"
          return 1
          ;;
      esac
      ;;
    php_root_clean|php_root_dirty)
      printf '%s must not have args' "$kind"
      return 1
      ;;
    dolt_bats_exact)
      case "$arg" in
        */*)
          printf 'Dolt BATS arg must be a simple file name: %s' "$arg"
          return 1
          ;;
      esac
      case "$arg" in
        *.bats) ;;
        *)
          printf 'Dolt BATS arg must end in .bats: %s' "$arg"
          return 1
          ;;
      esac
      ;;
  esac
}

load_queue_index() {
  QUEUE_IDS=()
  QUEUE_AUDITS=()
  declare -gA EXISTING_IDS=()
  declare -gA EXISTING_SCOPES=()

  if [ ! -e "$QUEUE_FILE" ]; then
    return
  fi

  local state id kind weight session prompt log audit scratch scope rest
  while IFS=$'\t' read -r state id kind weight session prompt log audit scratch scope rest; do
    case "$state" in
      ''|\#*) continue ;;
    esac
    if [ -n "${id:-}" ]; then
      if ! stale_php_queue_row "$kind" "$scope"; then
        EXISTING_IDS["$id"]=1
      fi
      QUEUE_IDS+=("$id")
    fi
    if [ -n "${scope:-}" ]; then
      if ! stale_php_queue_row "$kind" "$scope"; then
        EXISTING_SCOPES["$scope"]=1
      fi
    fi
    if [ -n "${audit:-}" ]; then
      QUEUE_AUDITS+=("$audit")
    fi
  done < "$QUEUE_FILE"
}

php_queue_kind() {
  case "$1" in
    php_root_clean|php_root_dirty|php_focused_clean|php_focused_dirty) return 0 ;;
    *) return 1 ;;
  esac
}

stale_php_queue_row() {
  local kind="$1"
  local scope="$2"
  local prefix row_head row_dirty rest

  php_queue_kind "$kind" || return 1
  IFS=':' read -r prefix row_head row_dirty rest <<< "$scope"
  case "$prefix" in
    php-clean)
      [ "$row_head" != "${HEAD_SHORT:-}" ]
      ;;
    php-dirty|dirty-root)
      [ "$row_head" != "${HEAD_SHORT:-}" ] || [ "$row_dirty" != "${DIRTY_KEY:-}" ]
      ;;
    *)
      return 1
      ;;
  esac
}

refresh_source_header() {
  local source_line="# capacity-source head=$HEAD_SHORT dirty_key=${DIRTY_KEY:-clean} stable_dirty_rows=$LANE_STABLE_DIRTY_COUNT"
  local tmp_queue line wrote=0

  if [ "$DRY_RUN" -eq 1 ]; then
    printf 'dry-run: would record %s\n' "$source_line"
    return
  fi
  if [ ! -e "$QUEUE_FILE" ]; then
    return
  fi

  tmp_queue="$STATE_DIR/capacity-queue-source.$$"
  : > "$tmp_queue"
  while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in
      '# capacity-source '*)
        if [ "$wrote" -eq 0 ]; then
          printf '%s\n' "$source_line" >> "$tmp_queue"
          wrote=1
        fi
        ;;
      *)
        printf '%s\n' "$line" >> "$tmp_queue"
        ;;
    esac
  done < "$QUEUE_FILE"
  if [ "$wrote" -eq 0 ]; then
    printf '%s\n' "$source_line" >> "$tmp_queue"
  fi
  mv "$tmp_queue" "$QUEUE_FILE"
}

queue_id_matches_glob() {
  local pattern="$1"
  local value
  for value in "${QUEUE_IDS[@]}"; do
    if [[ "$value" == $pattern ]]; then
      return 0
    fi
  done
  return 1
}

queue_audit_matches_glob() {
  local pattern="$1"
  local value base
  for value in "${QUEUE_AUDITS[@]}"; do
    base="${value##*/}"
    if [[ "$base" == $pattern ]]; then
      return 0
    fi
  done
  return 1
}

audit_file_matches_glob() {
  local pattern="$1"
  local found
  [ -d "$ROOT/audits" ] || return 1
  found="$(find "$ROOT/audits" -maxdepth 1 -type f -name "$pattern" -print -quit 2>/dev/null || true)"
  [ -n "$found" ]
}

candidate_has_queue_or_audit_coverage() {
  local id="$1"
  local scope_key="$2"
  local audit="$3"
  shift 3
  local glob

  if [ -n "${EXISTING_IDS[$id]:-}" ]; then
    return 0
  fi
  if [ -n "${EXISTING_SCOPES[$scope_key]:-}" ]; then
    return 0
  fi
  for glob in "$@"; do
    if queue_id_matches_glob "${glob%.md}"; then
      return 0
    fi
    if queue_audit_matches_glob "$glob"; then
      return 0
    fi
    if audit_file_matches_glob "$glob"; then
      return 0
    fi
  done
  if [ -e "$ROOT/$audit" ]; then
    return 0
  fi

  return 1
}

source_like_untracked_lane_path() {
  local path="$1"
  case "$path" in
    lanes/*/.phpunit.result.cache|\
    lanes/*/.phpunit.cache/*|\
    lanes/*/.pytest_cache/*|\
    lanes/*/.cache/*|\
    lanes/*/cache/*|\
    lanes/*/coverage/*|\
    lanes/*/dist/*|\
    lanes/*/build/*|\
    lanes/*/tmp/*|\
    lanes/*/temp/*|\
    lanes/*/vendor/*|\
    lanes/*/node_modules/*)
      return 1
      ;;
  esac

  case "$path" in
    *.php|*.phpt|*.inc|*.js|*.mjs|*.cjs|*.ts|*.tsx|*.jsx|\
    *.json|*.md|*.yml|*.yaml|*.xml|*.ini|*.css|*.scss|*.html|\
    *.txt|*.sql|*.tcl|*.go|*.rs|*.patch|*.diff)
      return 0
      ;;
  esac

  return 1
}

source_like_untracked_lane_paths() {
  local status_line path
  git -C "$ROOT" status --porcelain=v1 --untracked-files=all -- lanes |
    while IFS= read -r status_line; do
      case "$status_line" in
        '?? lanes/'*)
          path="${status_line#?? }"
          if source_like_untracked_lane_path "$path"; then
            printf '%s\n' "$path"
          fi
          ;;
      esac
    done |
    sort -u
}

stable_dirty_key_input() {
  git -C "$ROOT" diff --no-ext-diff --binary HEAD -- lanes
  source_like_untracked_lane_paths | sed 's/^/untracked	/'
}

skip_candidate() {
  SKIPPED_ROWS+=("$1	$2")
  printf 'skipped %s: %s\n' "$1" "$2"
}

queue_candidate() {
  local name="$1"
  local kind="$2"
  local id="$3"
  local scope_key="$4"
  shift 4
  local coverage_globs=()
  while [ "$#" -gt 0 ]; do
    case "$1" in
      --)
        shift
        break
        ;;
      *)
        coverage_globs+=("$1")
        shift
        ;;
    esac
  done
  local args=("$@")
  local weight session prompt log audit scratch row glob arg

  weight="$(expected_weight "$kind" 2>/dev/null || true)"
  if [ -z "$weight" ]; then
    skip_candidate "$name" "unsupported executor kind $kind"
    return
  fi

  if { [ "$kind" = "php_root_clean" ] || [ "$kind" = "php_root_dirty" ]; } && [ "${#args[@]}" -ne 0 ]; then
    skip_candidate "$name" "$kind candidate supplied args"
    return
  fi
  for arg in "${args[@]}"; do
    local arg_error
    arg_error="$(validate_arg "$kind" "$arg" 2>&1 || true)"
    if [ -n "$arg_error" ]; then
      skip_candidate "$name" "$arg_error"
      return
    fi
  done

  session="port-$id"
  prompt=".tmux-team/prompts/$id.md"
  log=".tmux-team/logs/port-$id.log"
  audit="audits/$id.md"
  scratch=".upstream-cache/$id"

  if [ -n "${EXISTING_IDS[$id]:-}" ]; then
    skip_candidate "$name" "row id already exists in queue"
    return
  fi
  if [ -n "${EXISTING_SCOPES[$scope_key]:-}" ]; then
    skip_candidate "$name" "scope key already exists in queue"
    return
  fi
  for glob in "${coverage_globs[@]}"; do
    if queue_id_matches_glob "${glob%.md}"; then
      skip_candidate "$name" "compatible row id already exists in queue ($glob)"
      return
    fi
    if queue_audit_matches_glob "$glob"; then
      skip_candidate "$name" "compatible audit path already exists in queue ($glob)"
      return
    fi
    if audit_file_matches_glob "$glob"; then
      skip_candidate "$name" "audit already proves coverage ($glob)"
      return
    fi
  done
  if [ -e "$ROOT/$audit" ]; then
    skip_candidate "$name" "audit already exists"
    return
  fi
  if [ "$APPENDED_COUNT" -ge "$MAX_ROWS" ]; then
    skip_candidate "$name" "max rows $MAX_ROWS reached"
    return
  fi

  row="$(line_from_fields ready "$id" "$kind" "$weight" "$session" "$prompt" "$log" "$audit" "$scratch" "$scope_key" "${args[@]}")"
  if [ "$DRY_RUN" -eq 1 ]; then
    printf 'dry-run: would append %s (%s)\n' "$id" "$kind"
  else
    printf '%s\n' "$row" >> "$QUEUE_FILE"
    EXISTING_IDS["$id"]=1
    EXISTING_SCOPES["$scope_key"]=1
    QUEUE_IDS+=("$id")
    QUEUE_AUDITS+=("$audit")
    printf 'queued %s (%s)\n' "$id" "$kind"
  fi
  QUEUED_ROWS+=("$id	$kind	$scope_key	$audit")
  APPENDED_COUNT=$((APPENDED_COUNT + 1))
}

queue_dirty_lane_candidate() {
  local lane="$1"
  local scope_suffix="$2"
  shift 2
  local args=("$@")
  local coverage_globs=(
    "capacity-feed-dirty-php-$HEAD_SHORT-*-$scope_suffix.md"
    "capacity-dirty-php-$HEAD_SHORT-*-$scope_suffix.md"
    "dirty-php-$HEAD_SHORT-*-$scope_suffix.md"
  )

  queue_candidate \
    "dirty-$scope_suffix" \
    "php_focused_dirty" \
    "capacity-feed-dirty-php-$HEAD_SHORT-$DIRTY_KEY-$scope_suffix" \
    "php-dirty:$HEAD_SHORT:$DIRTY_KEY:$scope_suffix" \
    "${coverage_globs[@]}" \
    -- \
    "${args[@]}"
}

queue_dirty_lane() {
  local lane="$1"
  local tests_dir="lanes/$lane/tests"
  local tests_abs="$ROOT/$tests_dir"
  local tests=()
  local test_path rel_path count start shard scope_suffix
  local chunk_size=16

  case "$lane" in
    ''|*[!A-Za-z0-9_.-]*)
      skip_candidate "dirty-$lane" "invalid lane name"
      return
      ;;
  esac

  if [ ! -d "$tests_abs" ]; then
    skip_candidate "dirty-$lane" "tests directory missing: $tests_dir"
    return
  fi

  while IFS= read -r test_path; do
    rel_path="${test_path#"$ROOT"/}"
    tests+=("$rel_path")
  done < <(find "$tests_abs" -maxdepth 1 -type f -name '*Test.php' -print 2>/dev/null | sort)

  count="${#tests[@]}"
  if [ "$count" -eq 0 ] || [ "$count" -le "$chunk_size" ]; then
    queue_dirty_lane_candidate "$lane" "$lane" "$tests_dir"
    return
  fi

  start=0
  shard=1
  while [ "$start" -lt "$count" ]; do
    scope_suffix="$(printf '%s-part%02d' "$lane" "$shard")"
    queue_dirty_lane_candidate "$lane" "$scope_suffix" "${tests[@]:$start:$chunk_size}"
    start=$((start + chunk_size))
    shard=$((shard + 1))
  done
}

queue_dirty_lanes() {
  local tests_abs rel lane
  while IFS= read -r tests_abs; do
    rel="${tests_abs#"$ROOT"/}"
    lane="${rel#lanes/}"
    lane="${lane%/tests}"
    queue_dirty_lane "$lane"
  done < <(find "$ROOT/lanes" -mindepth 2 -maxdepth 2 -type d -name tests -print 2>/dev/null | sort)
}

safe_id_fragment() {
  local value="$1"
  value="${value//[^A-Za-z0-9_.-]/-}"
  printf '%s' "$value"
}

sqlite_tcl_scope_id_fragment() {
  local scope_key="$1"
  local prefix short_name manifest rest
  IFS=':' read -r prefix short_name manifest rest <<< "$scope_key"

  case "$prefix" in
    sqlite-tcl-exact)
      printf 'exact-%s' "$(safe_id_fragment "$short_name")"
      ;;
    sqlite-tcl-bounded)
      printf 'bounded-%s' "$(safe_id_fragment "$short_name")"
      ;;
    *)
      safe_id_fragment "$scope_key"
      ;;
  esac
}

sqlite_tcl_exclusion_scope_keys() {
  local exclusions="$1"
  printf '%s\n' "$exclusions" |
    grep -Eo 'sqlite-tcl-(exact|bounded):[A-Za-z0-9_.-]+:[A-Za-z0-9]+' |
    sort -u || true
}

sqlite_tcl_scope_has_queue_or_audit_coverage() {
  local scope_key="$1"
  local scope_fragment
  scope_fragment="$(sqlite_tcl_scope_id_fragment "$scope_key")"

  if [ -n "${EXISTING_SCOPES[$scope_key]:-}" ]; then
    return 0
  fi
  if queue_id_matches_glob "capacity-feed-sqlite-tcl-$scope_fragment-*"; then
    return 0
  fi
  if queue_audit_matches_glob "capacity-feed-sqlite-tcl-$scope_fragment-*.md"; then
    return 0
  fi
  if audit_file_matches_glob "capacity-feed-sqlite-tcl-$scope_fragment-*.md"; then
    return 0
  fi

  return 1
}

queue_sqlite_tcl_candidate_rows() {
  local candidates_dir="$ROOT/.tmux-team/tmp/capacity-candidates"
  local tsv=""
  local rel_tsv

  if [ -d "$candidates_dir" ]; then
    tsv="$(find "$candidates_dir" -maxdepth 1 -type f -name 'sqlite-tcl-*.tsv' -print 2>/dev/null | sort | tail -n 1)"
  fi
  if [ -z "$tsv" ]; then
    skip_candidate "sqlite-tcl-candidates" "candidate TSV missing: $(queue_rel "$candidates_dir")/sqlite-tcl-*.tsv"
    return
  fi
  rel_tsv="$(queue_rel "$tsv")"

  if [ ! -f "$tsv" ]; then
    skip_candidate "sqlite-tcl-candidates" "candidate TSV missing: $rel_tsv"
    return
  fi

  printf 'sqlite-tcl-candidates: using %s\n' "$rel_tsv"

  local line_no=0 kind scope_key args reason estimated_weight exclusions extra
  local split_args=()
  local -A selected_scopes=()
  local scope_fragment id coverage_glob expected row_count_before
  local alt_scope alt_covered_reason jobs timeout_seconds
  while IFS=$'\t' read -r kind scope_key args reason estimated_weight exclusions extra || [ -n "${kind:-}" ]; do
    line_no=$((line_no + 1))
    if [ "$line_no" -eq 1 ] && [ "$kind" = "kind" ]; then
      continue
    fi
    case "$kind" in
      ''|\#*)
        continue
        ;;
    esac
    if [ -n "${extra:-}" ]; then
      skip_candidate "sqlite-tcl-row-$line_no" "unexpected extra TSV columns"
      continue
    fi
    case "$kind:$scope_key" in
      sqlite_tcl_exact:sqlite-tcl-exact:*|sqlite_tcl_bounded:sqlite-tcl-bounded:*)
        ;;
      *)
        skip_candidate "sqlite-tcl-row-$line_no" "unsupported SQLite Tcl TSV row: $kind $scope_key"
        continue
        ;;
    esac

    expected="$(expected_weight "$kind" 2>/dev/null || true)"
    if [ -z "$expected" ]; then
      skip_candidate "sqlite-tcl-row-$line_no" "unsupported executor kind $kind"
      continue
    fi
    if [ "$estimated_weight" != "$expected" ]; then
      skip_candidate "sqlite-tcl-row-$line_no" "estimated weight $estimated_weight does not match executor weight $expected"
      continue
    fi

    split_args=()
    read -r -a split_args <<< "$args"
    if [ "$kind" = "sqlite_tcl_exact" ] && [ "${#split_args[@]}" -lt 1 ]; then
      skip_candidate "sqlite-tcl-row-$line_no" "sqlite_tcl_exact row has no test files"
      continue
    fi
    if [ "$kind" = "sqlite_tcl_bounded" ]; then
      if [ "${#split_args[@]}" -lt 3 ]; then
        skip_candidate "sqlite-tcl-row-$line_no" "sqlite_tcl_bounded row requires testset, jobs, timeout, and optional patterns"
        continue
      fi
      jobs="${split_args[1]}"
      timeout_seconds="${split_args[2]}"
      case "$jobs" in
        ''|*[!0-9]*)
          skip_candidate "sqlite-tcl-row-$line_no" "sqlite_tcl_bounded jobs is not numeric"
          continue
          ;;
      esac
      case "$timeout_seconds" in
        ''|*[!0-9]*)
          skip_candidate "sqlite-tcl-row-$line_no" "sqlite_tcl_bounded timeout is not numeric"
          continue
          ;;
      esac
      if [ "$jobs" -gt 2 ]; then
        skip_candidate "sqlite-tcl-row-$line_no" "sqlite_tcl_bounded jobs $jobs exceeds 2"
        continue
      fi
      if [ "$timeout_seconds" -gt 600 ]; then
        skip_candidate "sqlite-tcl-row-$line_no" "sqlite_tcl_bounded timeout $timeout_seconds exceeds 600"
        continue
      fi
    fi

    if [ -n "${selected_scopes[$scope_key]:-}" ]; then
      skip_candidate "sqlite-tcl-row-$line_no" "scope already selected from $rel_tsv"
      continue
    fi

    alt_covered_reason=""
    while IFS= read -r alt_scope; do
      [ -n "$alt_scope" ] || continue
      [ "$alt_scope" = "$scope_key" ] && continue
      if [ -n "${selected_scopes[$alt_scope]:-}" ]; then
        alt_covered_reason="alternative scope already selected from $rel_tsv ($alt_scope)"
        break
      fi
      if sqlite_tcl_scope_has_queue_or_audit_coverage "$alt_scope"; then
        alt_covered_reason="alternative scope already queued or audited ($alt_scope)"
        break
      fi
    done < <(sqlite_tcl_exclusion_scope_keys "$exclusions")
    if [ -n "$alt_covered_reason" ]; then
      skip_candidate "sqlite-tcl-row-$line_no" "$alt_covered_reason"
      continue
    fi

    scope_fragment="$(sqlite_tcl_scope_id_fragment "$scope_key")"
    id="capacity-feed-sqlite-tcl-$scope_fragment-$HEAD_SHORT"
    coverage_glob="capacity-feed-sqlite-tcl-$scope_fragment-*.md"
    row_count_before="$APPENDED_COUNT"
    queue_candidate \
      "sqlite-tcl-$scope_fragment" \
      "$kind" \
      "$id" \
      "$scope_key" \
      "$coverage_glob" \
      -- \
      "${split_args[@]}"
    if [ "$APPENDED_COUNT" -gt "$row_count_before" ]; then
      selected_scopes["$scope_key"]=1
    fi
  done < "$tsv"
}

rclone_candidate_covered() {
  local id="$1"
  local scope_key="$2"
  local safe_scope="$3"

  if [ -n "${EXISTING_IDS[$id]:-}" ]; then
    return 0
  fi
  if [ -n "${EXISTING_SCOPES["rclone-go-exact:8cb1002a3f76:$scope_key"]:-}" ]; then
    return 0
  fi
  if queue_id_matches_glob "capacity-feed-rclone-go-$safe_scope-*"; then
    return 0
  fi
  if queue_audit_matches_glob "capacity-feed-rclone-go-$safe_scope-*.md"; then
    return 0
  fi
  if audit_file_matches_glob "capacity-feed-rclone-go-$safe_scope-*.md"; then
    return 0
  fi
  return 1
}

queue_rclone_go_candidate_rows() {
  local candidate_dir="$ROOT/.tmux-team/tmp/capacity-candidates"
  local tsv=""
  local rel_tsv

  if [ -d "$candidate_dir" ]; then
    tsv="$(find "$candidate_dir" -maxdepth 1 -type f -name 'rclone-go-*.tsv' -print 2>/dev/null | LC_ALL=C sort | tail -n 1)"
  fi

  if [ -z "$tsv" ] || [ ! -f "$tsv" ]; then
    rel_tsv="$(queue_rel "$candidate_dir/rclone-go-*.tsv")"
    skip_candidate "rclone-go-candidates" "candidate TSV missing: $rel_tsv"
    return
  fi
  rel_tsv="$(queue_rel "$tsv")"

  local line_no=0 selected=0
  local kind package selector expected_tests_csv scope_key reason exclusions extra
  local safe_scope id executor_scope coverage_glob row_hash package_fragment
  while IFS=$'\t' read -r kind package selector expected_tests_csv scope_key reason exclusions extra || [ -n "${kind:-}" ]; do
    line_no=$((line_no + 1))
    if [ "$line_no" -eq 1 ] && [ "$kind" = "kind" ]; then
      continue
    fi
    case "$kind" in
      go_test_local|rclone_go_exact) ;;
      *)
      skip_candidate "rclone-go-row-$line_no" "unsupported TSV kind $kind"
      continue
        ;;
    esac
    row_hash="$(printf '%s\t%s\t%s' "$package" "$selector" "$expected_tests_csv" | sha256sum | awk '{print substr($1, 1, 12)}')"
    package_fragment="$(safe_id_fragment "$package")"
    safe_scope="${package_fragment}-${row_hash}"
    id="capacity-feed-rclone-go-$safe_scope-$HEAD_SHORT"
    executor_scope="rclone-go-exact:8cb1002a3f76:$package:$selector"
    coverage_glob="capacity-feed-rclone-go-$safe_scope-*.md"

    if rclone_candidate_covered "$id" "$scope_key" "$safe_scope"; then
      queue_candidate \
        "rclone-go-$safe_scope" \
        "rclone_go_exact" \
        "$id" \
        "$executor_scope" \
        "$coverage_glob" \
        -- \
        "$package" \
        "$selector" \
        "$expected_tests_csv"
      continue
    fi
    if [ "$APPENDED_COUNT" -ge "$MAX_ROWS" ]; then
      skip_candidate "rclone-go-$safe_scope" "max rows $MAX_ROWS reached"
      return
    fi
    if [ "$selected" -ge 5 ]; then
      skip_candidate "rclone-go-$safe_scope" "outside first five uncovered go_test_local TSV rows"
      continue
    fi

    selected=$((selected + 1))
    queue_candidate \
      "rclone-go-$safe_scope" \
      "rclone_go_exact" \
      "$id" \
      "$executor_scope" \
      "$coverage_glob" \
      -- \
      "$package" \
      "$selector" \
      "$expected_tests_csv"
  done < "$tsv"
}

dolt_bats_candidate_covered() {
  local id="$1"
  local scope_key="$2"
  local safe_scope="$3"

  if [ -n "${EXISTING_IDS[$id]:-}" ]; then
    return 0
  fi
  if [ -n "${EXISTING_SCOPES[$scope_key]:-}" ]; then
    return 0
  fi
  if queue_id_matches_glob "capacity-feed-dolt-bats-$safe_scope-*"; then
    return 0
  fi
  if queue_audit_matches_glob "capacity-feed-dolt-bats-$safe_scope-*.md"; then
    return 0
  fi
  if audit_file_matches_glob "capacity-feed-dolt-bats-$safe_scope-*.md"; then
    return 0
  fi
  return 1
}

queue_dolt_bats_candidate_rows() {
  local candidate_dir="$ROOT/.tmux-team/tmp/capacity-candidates"
  local tsv=""
  local rel_tsv candidate_stamp

  if [ -d "$candidate_dir" ]; then
    tsv="$(find "$candidate_dir" -maxdepth 1 -type f -name 'dolt-bats-*.tsv' -print 2>/dev/null | LC_ALL=C sort | tail -n 1)"
  fi

  if [ -z "$tsv" ] || [ ! -f "$tsv" ]; then
    rel_tsv="$(queue_rel "$candidate_dir/dolt-bats-*.tsv")"
    skip_candidate "dolt-bats-candidates" "candidate TSV missing: $rel_tsv"
    return
  fi
  rel_tsv="$(queue_rel "$tsv")"
  candidate_stamp="${tsv##*/}"
  candidate_stamp="${candidate_stamp#dolt-bats-}"
  candidate_stamp="${candidate_stamp%.tsv}"
  printf 'dolt-bats-candidates: using %s\n' "$rel_tsv"

  local line_no=0 selected=0
  local kind scope_name bats_files env_scratch reason status extra
  local split_args=()
  local safe_scope id executor_scope coverage_glob arg
  while IFS=$'\t' read -r kind scope_name bats_files env_scratch reason status extra || [ -n "${kind:-}" ]; do
    line_no=$((line_no + 1))
    if [ "$line_no" -eq 1 ] && [ "$kind" = "kind" ]; then
      continue
    fi
    case "$kind" in
      ''|\#*)
        continue
        ;;
    esac
    if [ -n "${extra:-}" ]; then
      skip_candidate "dolt-bats-row-$line_no" "unexpected extra TSV columns"
      continue
    fi
    if [ "$kind" != "run_shard" ]; then
      skip_candidate "dolt-bats-row-$line_no" "unsupported TSV kind $kind"
      continue
    fi
    case "$scope_name" in
      ''|*[!A-Za-z0-9_.-]*)
        skip_candidate "dolt-bats-row-$line_no" "invalid scope name $scope_name"
        continue
        ;;
    esac
    case "$env_scratch" in
      .upstream-cache/capacity-selector-dolt-bats-*"/$scope_name"|\
      .upstream-cache/capacity-dolt-bats-index-*"/${scope_name#dolt-bats-}") ;;
      *)
        skip_candidate "dolt-bats-row-$line_no" "unexpected selector scratch path $env_scratch"
        continue
        ;;
    esac
    case "$reason" in
      *"Local engine only"*|*"local-engine"* ) ;;
      *)
        skip_candidate "dolt-bats-row-$line_no" "reason does not assert local engine only"
        continue
        ;;
    esac
    case "$status" in
      ran_count_*_count_rc_0_run_rc_0_*_fail_0*|candidate_marker_only_not_enqueued) ;;
      candidate_marker_only_not_enqueued_manual_review)
        skip_candidate "dolt-bats-row-$line_no" "manual-review marker row is excluded until a bounded exact BATS selection is provided"
        continue
        ;;
      *)
        skip_candidate "dolt-bats-row-$line_no" "status does not prove clean selector run"
        continue
        ;;
    esac

    split_args=()
    read -r -a split_args <<< "$bats_files"
    if [ "${#split_args[@]}" -lt 1 ]; then
      skip_candidate "dolt-bats-row-$line_no" "row has no BATS files"
      continue
    fi
    if [ "${#split_args[@]}" -gt 4 ]; then
      skip_candidate "dolt-bats-row-$line_no" "row has more than four BATS files"
      continue
    fi
    for arg in "${split_args[@]}"; do
      case "$arg" in
        ''|/*|*/*|*..*|*'	'*)
          skip_candidate "dolt-bats-row-$line_no" "invalid BATS file arg $arg"
          continue 2
          ;;
      esac
      case "$arg" in
        *.bats) ;;
        *)
          skip_candidate "dolt-bats-row-$line_no" "BATS file arg must end in .bats: $arg"
          continue 2
          ;;
      esac
    done

    safe_scope="$(safe_id_fragment "$scope_name")"
    id="capacity-feed-dolt-bats-$safe_scope-$HEAD_SHORT"
    executor_scope="dolt-bats-exact:$candidate_stamp:$scope_name"
    coverage_glob="capacity-feed-dolt-bats-$safe_scope-*.md"

    if dolt_bats_candidate_covered "$id" "$executor_scope" "$safe_scope"; then
      skip_candidate "dolt-bats-$safe_scope" "scope key already exists in queue or audit"
      continue
    fi
    if [ "$APPENDED_COUNT" -ge "$MAX_ROWS" ]; then
      skip_candidate "dolt-bats-$safe_scope" "max rows $MAX_ROWS reached"
      return
    fi
    if [ "$selected" -ge 4 ]; then
      skip_candidate "dolt-bats-$safe_scope" "outside first four clean Dolt BATS TSV rows"
      continue
    fi

    selected=$((selected + 1))
    queue_candidate \
      "dolt-bats-$safe_scope" \
      "dolt_bats_exact" \
      "$id" \
      "$executor_scope" \
      "$coverage_glob" \
      -- \
      "${split_args[@]}"
  done < "$tsv"
}

queue_lock_held_nonphp_candidates() {
  queue_sqlite_tcl_candidate_rows
  queue_rclone_go_candidate_rows
  queue_dolt_bats_candidate_rows
}

init_queue_file

HEAD_FULL="$(git -C "$ROOT" rev-parse HEAD)"
HEAD_SHORT="$(git -C "$ROOT" rev-parse --short=12 HEAD)"
LANE_DIRTY_COUNT="$(git -C "$ROOT" status --porcelain=v1 -- lanes | wc -l | tr -d ' ')"
LANE_TRACKED_DIRTY_COUNT="$(git -C "$ROOT" diff --name-only HEAD -- lanes | wc -l | tr -d ' ')"
LANE_SOURCE_UNTRACKED_COUNT="$(source_like_untracked_lane_paths | wc -l | tr -d ' ')"
LANE_STABLE_DIRTY_COUNT=$((LANE_TRACKED_DIRTY_COUNT + LANE_SOURCE_UNTRACKED_COUNT))
DIRTY_KEY=""
if [ "$LANE_STABLE_DIRTY_COUNT" -gt 0 ]; then
  DIRTY_KEY="$(stable_dirty_key_input | sha256sum | awk '{print substr($1, 1, 12)}')"
fi
refresh_source_header
load_queue_index

APPENDED_COUNT=0
QUEUED_ROWS=()
SKIPPED_ROWS=()

if [ "$LOCK_HELD" -eq 1 ]; then
  queue_lock_held_nonphp_candidates
fi

queue_candidate \
  "clean-root" \
  "php_root_clean" \
  "capacity-feed-clean-root-$HEAD_SHORT" \
  "php-clean:$HEAD_SHORT:root" \
  "capacity-feed-clean-root-$HEAD_SHORT.md" \
  "capacity-clean-root-$HEAD_SHORT-*.md" \
  --

queue_candidate \
  "clean-gitoxide-dolt" \
  "php_focused_clean" \
  "capacity-feed-clean-php-$HEAD_SHORT-gitoxide-dolt" \
  "php-clean:$HEAD_SHORT:gitoxide-dolt" \
  "capacity-feed-clean-php-$HEAD_SHORT-gitoxide-dolt.md" \
  "capacity-clean-php-$HEAD_SHORT-*-gitoxide-dolt.md" \
  -- \
  "lanes/gitoxide/tests" \
  "lanes/dolt/tests"

queue_candidate \
  "clean-markerpdf-docs" \
  "php_focused_clean" \
  "capacity-feed-clean-php-$HEAD_SHORT-markerpdf-docs" \
  "php-clean:$HEAD_SHORT:markerpdf-docs" \
  "capacity-feed-clean-php-$HEAD_SHORT-markerpdf-docs.md" \
  "capacity-clean-php-$HEAD_SHORT-*-markerpdf-docs.md" \
  -- \
  "lanes/markerpdf/tests" \
  "lanes/pandoc/tests" \
  "lanes/readability/tests"

queue_candidate \
  "clean-rclone-syncthing" \
  "php_focused_clean" \
  "capacity-feed-clean-php-$HEAD_SHORT-rclone-syncthing" \
  "php-clean:$HEAD_SHORT:rclone-syncthing" \
  "capacity-feed-clean-php-$HEAD_SHORT-rclone-syncthing.md" \
  "capacity-clean-php-$HEAD_SHORT-*-rclone-syncthing.md" \
  -- \
  "lanes/rclone/tests" \
  "lanes/syncthing/tests"

queue_candidate \
  "clean-sql-css-quad-diff-esbuild" \
  "php_focused_clean" \
  "capacity-feed-clean-php-$HEAD_SHORT-sql-css-quad-diff-esbuild" \
  "php-clean:$HEAD_SHORT:sql-css-quad-diff-esbuild" \
  "capacity-feed-clean-php-$HEAD_SHORT-sql-css-quad-diff-esbuild.md" \
  "capacity-clean-php-$HEAD_SHORT-*-sql-css-quad-diff-esbuild.md" \
  -- \
  "lanes/libsqlite/tests" \
  "lanes/lightningcss/tests" \
  "lanes/quadrable/tests" \
  "lanes/difftastic/tests" \
  "lanes/esbuild/tests"

if [ "$INCLUDE_DIRTY" -eq 1 ] && [ "$LANE_STABLE_DIRTY_COUNT" -gt 0 ]; then
  queue_candidate \
    "dirty-root" \
    "php_root_dirty" \
    "capacity-feed-dirty-root-$HEAD_SHORT-$DIRTY_KEY" \
    "dirty-root:$HEAD_SHORT:$DIRTY_KEY" \
    "capacity-feed-dirty-root-$HEAD_SHORT-*.md" \
    "capacity-dirty-root-$HEAD_SHORT-*.md" \
    "dirty-root-$HEAD_SHORT-*.md" \
    --

  queue_dirty_lanes
else
  skip_candidate "dirty-moving-tree" "lane tree has no tracked/source-like dirty rows or --clean-only was set"
fi

if [ "$LOCK_HELD" -eq 0 ]; then
  skip_candidate "rclone-go-exact" "no newly vetted local-only exact selector was supplied"
  skip_candidate "sqlite-tcl-exact" "no newly vetted local-only exact Tcl test file was supplied"
  skip_candidate "sqlite-tcl-bounded" "no newly vetted bounded Tcl testset was supplied"
fi

printf 'capacity queue feeder: queued=%s skipped=%s dry_run=%s head=%s lane_dirty_rows=%s stable_dirty_rows=%s queue=%s\n' \
  "$APPENDED_COUNT" "${#SKIPPED_ROWS[@]}" "$DRY_RUN" "$HEAD_FULL" "$LANE_DIRTY_COUNT" "$LANE_STABLE_DIRTY_COUNT" "$(queue_rel "$QUEUE_FILE")"
