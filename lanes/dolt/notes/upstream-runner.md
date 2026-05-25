# Dolt Upstream Runner Evidence

- Date: 2026-05-23 UTC
- Upstream: `dolthub/dolt`
- Commit: `b2274926e0dcd84aab000ee242df5b5e75689eef`
- Cache used by this runner: `.upstream-cache/dolt`

## Runner Refresh 2026-05-23 06:38 UTC Preview Merge Conflicts Rerun

- Cache inspection before building/running:
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository --filter --show-toplevel HEAD`: `true`, `--filter`, `/home/claude/port-libs/.upstream-cache/dolt`, `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt status --short --branch --untracked-files=no`: known sparse/no-checkout out-of-cone deletions remain in the upstream cache index. No delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: exit `0`; all four packages were already installed and DNF returned `Nothing to do`.
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
  - `go version`: `go version go1.26.3-X:nodwarf5 linux/amd64`.
  - `bats --version`: `Bats 1.13.0`.
  - `expect -v`: `expect version 5.45.4`.
- Cache-local build:
  - `mkdir -p /home/claude/port-libs/.upstream-cache/dolt/tmp /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin /home/claude/port-libs/.upstream-cache/dolt/bats-tmp`
  - `env DOLT_DISABLE_VERSION_CHECK=1 DOLT_DISABLE_ANALYTICS=1 TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit `0`; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: `dolt version 2.0.5`.
- Focused upstream source/test inventory for this rerun:
  - `8` focused upstream source/test paths and `286` current targeted references across `go/libraries/doltcore/sqle/dtablefunctions/dolt_preview_merge_conflicts.go`, `go/libraries/doltcore/sqle/dtablefunctions/dolt_preview_merge_conflicts_summary.go`, `go/libraries/doltcore/sqle/enginetest/dolt_engine_test.go`, `go/libraries/doltcore/sqle/enginetest/dolt_engine_tests.go`, `go/libraries/doltcore/sqle/enginetest/dolt_queries_merge.go`, `go/libraries/doltcore/sqle/enginetest/dolt_queries_schema_merge.go`, `go/libraries/doltcore/sqle/enginetest/dolt_privilege_test.go`, and `integration-tests/bats/system-tables.bats`.
- Bounded Go evidence:
  - `env DOLT_DISABLE_VERSION_CHECK=1 DOLT_DISABLE_ANALYTICS=1 TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 25m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltPreviewMergeConflicts(Prepared)?$' -count=1 -timeout 25m`
  - Result: exit `0`; package result `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.777s`.
  - `env DOLT_DISABLE_VERSION_CHECK=1 DOLT_DISABLE_ANALYTICS=1 TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltUserPrivileges$' -count=1 -timeout 20m`
  - Result: exit `0`; package result `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.176s`.
  - `env DOLT_DISABLE_VERSION_CHECK=1 DOLT_DISABLE_ANALYTICS=1 TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 30m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestThreeWayMergeWithSchemaChangeScripts(Prepared)?$' -count=1 -timeout 30m`
  - Result: exit `0`; package result `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 8.981s`.
- Bounded BATS evidence from `.upstream-cache/dolt/integration-tests/bats`:
  - `env DOLT_DISABLE_VERSION_CHECK=1 DOLT_DISABLE_ANALYTICS=1 SQL_ENGINE=local TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 15m bats --filter 'system-tables: dolt_preview_merge_conflicts bind variable support - dynamic table function' system-tables.bats`
  - Result: exit `0`, plan `1..1`; dynamic table-function prepared/bind-variable smoke passed.
  - `env DOLT_DISABLE_VERSION_CHECK=1 DOLT_DISABLE_ANALYTICS=1 SQL_ENGINE=local TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit `1`, plan `1..1`; pristine helper truncated `7cpvkb7glode3pv319ope7dj7s9mbk28` to `b7glode3pv319ope7dj7s9mbk28`, and `dolt reset` reported `branch not found`.
  - `env DOLT_DISABLE_VERSION_CHECK=1 DOLT_DISABLE_ANALYTICS=1 SQL_ENGINE=local TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: exit `0`, plan `1..1`; runner-local fixed helper passed the exact repro.
- Direct cache-local CLI probe:
  - Throwaway repo: `/home/claude/port-libs/.upstream-cache/dolt/tmp/preview-conflicts-k6Rd8q`.
  - `dolt_preview_merge_conflicts_summary('main','import')` returned CSV header plus `wp_posts,1,0`.
  - `dolt_preview_merge_conflicts('main','import','wp_posts')` for selected keyed columns returned `1,Hello,1,Edited Hello,modified,1,Imported Hello,modified`.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.
- Required repository check after this metadata update:
  - `php tools/run-tests.php`
  - Result: exit `0`; `189` test files, `20,443` assertions, and `0` failures.
  - A final post-recording rerun of `php tools/run-tests.php` was started after the lane-status update and exited `1` with `189` test files, `20,484` assertions, and `1` failure; streamed output truncated before the failure.
  - Captured rerun `php tools/run-tests.php > /tmp/dolt-final-root-rerun.log 2>&1` exited `1` with `189` test files, `20,493` assertions, and `2` failures outside Dolt: `lanes/libsqlite/tests/SQLiteHeaderTest.php` (`rejects wordpress indexed insert plans that would split a multi-page index leaf`, message `SQLite index overflow cell requires a valid first overflow page`) and `lanes/lightningcss/tests/TransitionPrefixerTest.php` (`wordpress editor color-scheme fallback flags prefix without node`, expected split `var(--lightningcss-light,...) var(--lightningcss-dark,...)` alpha fallback but actual preserved `rgb(from light-dark(yellow,red) r g b/var(--wp--custom--alpha))`).
  - Focused Dolt-only PHP verification with direct `TestRunner` passed after the aggregate failures: `20` Dolt test files, `1,092` assertions, and `0` failures.

## Root Harness Guard 2026-05-23 06:18 UTC

- After committing the Dolt lane batch and status stamp, a final duplicate-root guard found an active root harness: PID `1158397`, user `claude`, elapsed `00:13`, command `php tools/run-tests.php`.
- Per lane instructions, no duplicate root harness was started. The latest completed root result for the Dolt implementation batch remains the earlier green run in this note (`186` files, `20,050` assertions, `0` failures); any post-status-stamp aggregate result is pending for the supervisor/integrator or the active root runner owner.

## Root Harness Guard 2026-05-23 06:21 UTC

- Resume check found another active root harness: PID `1184089`, user `claude`, elapsed `00:08`, command `php tools/run-tests.php`.
- Per lane instructions, no duplicate root harness was started. Dolt lane-focused PHP remains green at `19` files, `202` behavior tests, `1,075` assertions, `0` failures; current aggregate root status is pending for the active root runner owner/integrator.

## Root Harness Rerun 2026-05-23 06:24 UTC

- After the active root harness exited, a new guard check returned empty, so this lane ran `php tools/run-tests.php > /tmp/port-dolt-root-rerun.log 2>&1`.
- Result: red aggregate root run, `187` test files, `20,159` assertions, `2` failures.
- Both failures were outside Dolt in `lanes/rclone/tests/CopyUrlTest.php`: `copyurl uploads explicit destinations and enforces no clobber` expected `2026-05-22T10:00:00Z` but saw `2026-05-27T10:00:00Z`, and `wordpress copyurl remote media example imports without live http` failed because `lanes/rclone/examples/wordpress-copyurl-remote-media-import.php` was missing.
- Dolt lane-focused PHP remains green at `19` files, `202` behavior tests, `1,075` assertions, `0` failures.

## Root Harness Guard 2026-05-23 06:41 UTC

- After the preview merge-conflicts implementation and focused lane tests, the required duplicate-root guard returned active processes: PID `1280441`, user `claude`, elapsed `00:09`, command `php tools/run-tests.php`; and focused lane PID `1280431`, user `claude`, elapsed `00:09`, command `php tools/run-tests.php lanes/syncthing/tests`.
- Per lane instructions, no duplicate root harness was started. Dolt lane-focused PHP remains green at `20` files, `206` behavior tests, `1,092` assertions, `0` failures; current aggregate root result is pending for the active root runner owner/integrator.

## Root Harness Rerun 2026-05-23 06:43 UTC

- After the active root harness cleared, a fresh duplicate-root guard returned empty and this lane ran `php tools/run-tests.php`.
- Result: pass; `189` test files, `20,479` assertions, `0` failures.
- Dolt lane-focused PHP remains green at `20` files, `206` behavior tests, `1,092` assertions, `0` failures.

## Root Harness Rerun 2026-05-23 06:47 UTC

- After the later outside-Dolt aggregate failures recorded above, a fresh duplicate-root guard returned empty and this lane ran `php tools/run-tests.php`.
- Result: pass; `189` test files, `20,500` assertions, `0` failures.
- Dolt lane-focused PHP remains green at `20` files, `206` behavior tests, `1,092` assertions, `0` failures.

## Implementation Lane 2026-05-23 06:34 UTC Preview Merge Conflicts

- Upstream denominator/evidence reused the cloned static inventory plus a fresh focused preview-conflicts slice: `8` focused upstream source/test paths and `339` targeted references across `go/libraries/doltcore/sqle/dtablefunctions/dolt_preview_merge_conflicts.go`, `go/libraries/doltcore/sqle/dtablefunctions/dolt_preview_merge_conflicts_summary.go`, focused `sqle/enginetest` merge/schema/privilege tests, and `integration-tests/bats/system-tables.bats`.
- The full upstream `go test ./...` and full BATS directory were not rerun for this implementation slice because they require broad workspace hydration plus Python, parquet, Hadoop, server, compatibility, client integration, and benchmark-style dependencies beyond the bounded local runner. The defensible denominator remains the cloned static inventory of 613 executable upstream test files plus this focused 8-path / 339-reference runner slice.
- Bounded upstream runner:
  - From `.upstream-cache/dolt/go`: `env DOLT_DISABLE_VERSION_CHECK=1 DOLT_DISABLE_ANALYTICS=1 TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 25m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltPreviewMergeConflicts(Prepared)?$' -count=1 -timeout 25m`
  - Result: exit `0`; focused preview merge conflicts and prepared-statement coverage passed; package result `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.791s`.
  - From `.upstream-cache/dolt/integration-tests/bats`: `env DOLT_DISABLE_VERSION_CHECK=1 DOLT_DISABLE_ANALYTICS=1 SQL_ENGINE=local TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 15m bats --filter 'system-tables: dolt_preview_merge_conflicts bind variable support - dynamic table function' system-tables.bats`
  - Result: exit `0`, plan `1..1`; prepared/bind-variable dynamic table-function smoke passed.
- Native PHP implementation:
  - Added `PreviewMergeConflictsTable::summaryRows()` for upstream `dolt_preview_merge_conflicts_summary` rows, including `NULL` data counts when schema conflicts make row previews unavailable.
  - Added `PreviewMergeConflictsTable::conflictRows()` for keyed data-conflict preview rows with `from_root_ish`, `base_*`, `our_*`, `our_diff_type`, `their_*`, `their_diff_type`, and `dolt_conflict_id` fields. The slice maps divergent modify/modify, add/add, and delete/modify rows and preserves the upstream `schema conflicts found: N` table-function error boundary.
  - `fixtures/wp-merge-review.php` and `examples/wordpress-merge-status-review.php` now expose WordPress import preflight conflict summary rows and `wp_posts` row-level conflict previews before an import merge is executed.
- Focused PHP evidence:
  - `php tools/run-tests.php lanes/dolt/tests/PreviewMergeConflictsTableTest.php`: pass; 1 file, 4 behavior tests, 10 assertions, 0 failures.
  - `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php`: pass; 1 file, 12 behavior tests, 121 assertions, 0 failures.
  - `php tools/run-tests.php lanes/dolt/tests`: pass; 20 Dolt files, 206 behavior tests, 1,092 assertions, 0 failures.

## Implementation Lane 2026-05-23 06:15 UTC Native CALL DOLT_MERGE Result Rows

- Upstream denominator/evidence reused the cloned static inventory plus a fresh focused stored-procedure row slice: 6 focused upstream command/source/BATS paths and 689 targeted merge-result references across `go/libraries/doltcore/sqle/dprocedures/dolt_merge.go`, `go/libraries/doltcore/sqle/enginetest/dolt_queries_merge.go`, `go/cmd/dolt/commands/merge.go`, `integration-tests/bats/merge.bats`, `integration-tests/bats/conflict-detection.bats`, and `integration-tests/bats/status.bats`.
- The full upstream `go test ./...` and full BATS directory were not rerun for this implementation slice because they require broad workspace hydration plus Python, parquet, Hadoop, server, compatibility, client integration, and benchmark-style dependencies beyond the bounded local runner. The defensible denominator remains the cloned static inventory of 613 executable upstream test files plus this focused 6-path / 689-reference runner slice.
- Bounded upstream runner:
  - From `.upstream-cache/dolt/go`: `env DOLT_DISABLE_VERSION_CHECK=1 DOLT_DISABLE_ANALYTICS=1 TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltMerge/(CALL DOLT_MERGE ff correctly works with autocommit off|CALL DOLT_MERGE no-ff correctly works with autocommit off|CALL DOLT_MERGE without conflicts correctly works with autocommit off and no commit flag|CALL DOLT_MERGE when current or ahead results in a no-op|CALL DOLT_MERGE with conflict is queryable and committable with dolt_allow_commit_conflicts on|--ff-only flag success when fast-forward is possible|--ff-only flag failure when fast-forward is not possible|--ff-only with no-commit flag should work)$' -count=1 -timeout 20m -v`
  - Result: exit `0`; focused `TestDoltMerge`, `TestDoltMergePrepared`, and matched artifact setup passed; package result `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 1.002s`.
- Native PHP implementation:
  - `MergeStatusTable::mergeProcedureRow()` now projects upstream `CALL DOLT_MERGE` rows with `hash`, `fast_forward`, `conflicts`, and `message` columns.
  - The native row slice maps committed fast-forward rows, `--ff-only --no-commit` rows that still return the fast-forward commit hash, committed `--no-ff` rows, non-fast-forward `--no-commit` rows with empty hash, unresolved conflict/constraint rows with `conflicts = 1`, current/ahead no-op messages, active abort rows, and rowless error boundaries for impossible `--ff-only`, incompatible flags, and invalid commit/no-commit combinations.
  - `fixtures/wp-merge-review.php` and `examples/wordpress-merge-status-review.php` now expose WordPress import-review `CALL DOLT_MERGE` result rows alongside the existing CLI transcript/status projections.
- Focused PHP evidence:
  - `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php`: pass; 1 file, 12 behavior tests, 114 assertions, 0 failures.
  - `php tools/run-tests.php lanes/dolt/tests`: pass; 19 Dolt files, 202 behavior tests, 1,075 assertions, 0 failures.
- Root harness:
  - Required guard `pgrep -af '^php tools/run-tests\.php( |$)'` returned no active process before starting.
  - `php tools/run-tests.php`: pass; 186 files, 20,050 assertions, 0 failures.

## Implementation Lane 2026-05-23 06:01 UTC Native Merge FF-Only / No-FF Slice

- Upstream denominator/evidence reused the cloned static inventory plus the fresh 05:53 UTC focused runner slice: 6 focused upstream command/source/BATS paths and 51 targeted `--ff-only` / `--no-ff` references across `go/cmd/dolt/commands/merge.go`, `go/libraries/doltcore/sqle/dprocedures/dolt_merge.go`, `go/libraries/doltcore/sqle/enginetest/dolt_queries_merge.go`, `integration-tests/bats/merge.bats`, `integration-tests/bats/conflict-detection.bats`, and `integration-tests/bats/status.bats`.
- The full upstream `go test ./...` and full BATS directory were not rerun for this implementation slice because they require broad workspace hydration plus Python, parquet, Hadoop, server, compatibility, client integration, and benchmark-style dependencies beyond the bounded local runner. The defensible denominator remains the cloned static inventory of 613 executable upstream test files plus the focused 6-path / 51-reference runner slice.
- Native PHP implementation:
  - `MergeStatusTable::mergeCliTranscript()` now returns upstream-shaped CLI text for successful fast-forward-only merges, no-ff non-fast-forward merges, fast-forward-only impossible merges, and incompatible flag validation before merge execution.
  - `MergeStatusTable::mergeFlagError()` now maps the exact upstream flag errors for `--ff-only` with `--no-ff`, `--ff-only` with `--squash`, `--squash` with `--no-ff`, and `--commit` with `--no-commit`.
  - `MergeStatusTable::mergeSuccessTranscript()` now rejects success transcripts that combine incompatible merge flags or attempt to print `Fast-forward` while `--no-ff` is set.
  - `fixtures/wp-merge-review.php` and `examples/wordpress-merge-status-review.php` now expose WordPress import-review outputs for ff-only fast-forward, no-ff merge commit, ff-only failure, and ff-only incompatible flag errors.
- Focused PHP evidence:
  - `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php`: pass; 1 file, 11 behavior tests, 91 assertions, 0 failures.
  - `php tools/run-tests.php lanes/dolt/tests`: pass; 19 Dolt files, 201 behavior tests, 1,052 assertions, 0 failures.
- Root harness:
  - Required guard `pgrep -af '^php tools/run-tests\.php( |$)'` returned no active process before starting.
  - `php tools/run-tests.php` initially waited on `/home/claude/port-libs/.upstream-cache/run-tests.lock`, then acquired the lock and completed successfully.
  - Result: pass; 185 files, 19,724 assertions, 0 failures.

## Runner Refresh 2026-05-23 05:53 UTC Merge Fast-Forward / No-FF Evidence

- Cache inspection before changing/building:
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository HEAD`: `true` and `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`; no delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: exit `0`; all packages were already installed. Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`; RPMs include `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, and `libicu-devel-77.1-2.fc44.x86_64`.
- Static inventory refresh:
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '(^go/cmd/dolt/commands/merge\.go$|^go/libraries/doltcore/sqle/dprocedures/dolt_merge\.go$|^go/libraries/doltcore/sqle/enginetest/dolt_queries_merge\.go$|^integration-tests/bats/(merge|conflict-detection|status)\.bats$)' | wc -l`: `6` focused upstream source/test paths.
  - `rg -n 'ff-only|--no-ff|Fast-forward|Not possible to fast-forward|Flags .*ff-only|Everything up-to-date|Already up to date|no-ff merge|non-fast-forward' ... | wc -l`: `51` targeted fast-forward/no-ff references.
- Cache-local build:
  - `mkdir -p /home/claude/port-libs/.upstream-cache/dolt/tmp /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin /home/claude/port-libs/.upstream-cache/dolt/bats-tmp`
  - From `.upstream-cache/dolt/go`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit `0`; cache-local `dolt`, `noms`, and `remotesrv` rebuilt. `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - From `.upstream-cache/dolt/go`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltMerge/(--ff-only flag success when fast-forward is possible|--ff-only flag failure when fast-forward is not possible|--ff-only flag with already up-to-date branch|--ff-only conflicts with --no-ff|--ff-only conflicts with --squash|--ff-only with no-commit flag should work)$' -count=1 -timeout 20m -v`
  - Result: exit `0`; focused `TestDoltMerge` and `TestDoltMergePrepared` `--ff-only` success, diverged failure, up-to-date, incompatible `--no-ff`, incompatible `--squash`, and `--no-commit` subtests passed; package result `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 1.024s`.
- Bounded BATS evidence from `.upstream-cache/dolt/integration-tests/bats`:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 45m bats --filter 'merge: (ff merge doesn.t stomp working changes|no-ff merge|no-ff merge doesn.t stomp working changes and doesn.t fast forward|dolt merge commits successful non-fast-forward merge|dolt merge does not ff and not commit with --no-ff and --no-commit)' merge.bats`
  - Result: exit `0`, plan `1..5`; all five focused local fast-forward/no-ff merge tests passed.
- Direct cache-local CLI probes in throwaway `.upstream-cache/dolt/tmp/merge-ff-probe-*` and `.upstream-cache/dolt/tmp/merge-ff-only-probe-*` repositories:
  - First direct probe harness exited `2` before Dolt behavior because Bash `printf` treated a leading `---` format string as an option; the corrected harness used `printf --` and passed.
  - Corrected fast-forward/no-ff probe result: exit `0`; `dolt merge feature` printed `Fast-forward`, `Updating <hash>..<hash>`, `t | 1 +`, and `1 tables changed, 1 rows added(+), 0 rows modified(*), 0 rows deleted(-)`.
  - Corrected ff-only failure result: expected exit `1`; `dolt merge --ff-only feature` printed `fatal: Not possible to fast-forward, aborting`.
  - Corrected incompatible flag results: expected exit `1`; `dolt merge --ff-only --no-ff feature` printed `error: Flags '--ff-only' and '--no-ff' cannot be used together`, and `dolt merge --ff-only --squash feature` printed `error: Flags '--ff-only' and '--squash' cannot be used together`.
  - Corrected up-to-date result: exit `0`; `dolt merge --ff-only feature` printed exactly `Everything up-to-date`.
  - Corrected no-ff result: exit `0`; `dolt merge feature --no-ff -m 'no-ff merge'` printed `Updating <hash>..<hash>` plus the one-table stat block and did not print `Fast-forward`; `dolt log --oneline -n 1` showed the merge commit message `no-ff merge`.
  - Additional ff-only/no-commit probe result: exit `0`; `dolt merge --ff-only feature` printed `Fast-forward` plus the one-table stat block and advanced the top log to `feature adds row`; `dolt merge --ff-only --no-commit feature` printed `Fast-forward`, `Automatic merge went well; stopped before committing as requested`, and `Everything up-to-date`, and the subsequent `dolt status` was clean.
- Required repository check after this metadata update:
  - `php tools/run-tests.php`
  - Result: exit `0`; `185` test files, `19,568` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run. This runner slice supplied upstream evidence for the 06:01 UTC native `--ff-only` / `--no-ff` PHP implementation.

## Runner Refresh 2026-05-23 05:43 UTC Merge Control Tooling Verification

- Cache inspection before changing/building:
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - No delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: exit `0`; all packages were already installed. Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`; RPMs include `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, and `libicu-devel-77.1-2.fc44.x86_64`.
- Static inventory refresh:
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '(^go/cmd/dolt/commands/merge\.go$|^go/libraries/doltcore/merge/.*\.go$|^go/libraries/doltcore/sqle/dprocedures/dolt_merge\.go$|^go/libraries/doltcore/sqle/enginetest/dolt_queries_merge\.go$|^integration-tests/bats/(merge|conflict-detection|constraint-violations)\.bats$)' | wc -l`: `30` focused/adjacent upstream source/test paths.
  - `rg -n 'Everything up-to-date|Squash commit|--squash|--no-commit|--abort|Aborting|abort|merge --abort|Automatic merge failed|All conflicts and constraint violations fixed|still merging' ... | wc -l`: `82` targeted merge-control references.
- Cache-local build:
  - `mkdir -p /home/claude/port-libs/.upstream-cache/dolt/tmp /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin /home/claude/port-libs/.upstream-cache/dolt/bats-tmp`
  - From `.upstream-cache/dolt/go`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit `0`; cache-local `dolt`, `noms`, and `remotesrv` rebuilt. `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - From `.upstream-cache/dolt/go`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit `0`; `ok github.com/dolthub/dolt/go/libraries/doltcore/merge 5.717s`.
  - From `.upstream-cache/dolt/go`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltMerge(Prepared|Artifacts)?$' -count=1 -timeout 20m`
  - Result: exit `0`; `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 3.558s`.
- Bounded BATS evidence from `.upstream-cache/dolt/integration-tests/bats`:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 45m bats --filter 'merge: (db collation ff merge|db collation non ff merge|db collation merge conflict|--abort restores working changes|--abort leaves clean working, staging roots|squash merge|dolt merge commits successful non-fast-forward merge|dolt merge does not ff and not commit with --no-ff and --no-commit|merge with --no-commit prints correct merge stats)' merge.bats`
  - Result: exit `0`, plan `1..9`; all nine focused local merge-control tests passed.
- Direct cache-local CLI probes in throwaway `.upstream-cache/dolt/tmp/merge-boundary-probe-*` repositories:
  - First probe command used `dolt config --local user.name ...` and failed before merge behavior with exit `1`; Dolt requires `dolt config --local --set <name> <value>`.
  - Corrected probe command used cache-local `dolt init`, `dolt config --local --set user.name 'Dolt Runner'`, `dolt config --local --set user.email 'runner@example.com'`, `dolt sql -q 'CREATE TABLE t ...; CREATE TABLE dirty ...;'`, `dolt commit -Am 'base'`, branch creation/checkouts, `dolt merge same`, `dolt merge feature --no-ff --no-commit`, `dolt merge --abort`, and `dolt merge --squash squash_feature --no-commit`.
  - Result: exit `0`; `dolt merge same` printed exactly `Everything up-to-date`.
  - Result: exit `0`; `dolt merge feature --no-ff --no-commit` printed `Updating <head>..<merge>`, `Automatic merge went well; stopped before committing as requested`, `t | 1 +`, and `1 tables changed, 1 rows added(+), 0 rows modified(*), 0 rows deleted(-)`. Subsequent `dolt status` printed `All conflicts and constraint violations fixed but you are still merging.` and `modified:         t`.
  - Result: exit `0`; `dolt merge --abort` after the no-commit merge returned the repository to `On branch main` and `nothing to commit, working tree clean`.
  - Result: exit `0`; `dolt merge --squash squash_feature --no-commit` printed `Updating <head>..<merge>`, `Squash commit -- not updating HEAD`, `Automatic merge went well; stopped before committing as requested`, `t | 1 +`, and `1 tables changed, 1 rows added(+), 0 rows modified(*), 0 rows deleted(-)`.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run. Full upstream parity still requires hydrating and compiling substantially more of the Dolt workspace and running suites outside this local bounded runner.

## Implementation Lane 2026-05-23 05:42 UTC Merge Control Transcript And Abort Boundary

- Upstream source/test boundary inspected:
  - `go/cmd/dolt/commands/merge.go`: CLI prints `Everything up-to-date` before stats, `Updating <head>..<merge>`, `Squash commit -- not updating HEAD`, `Automatic merge went well; stopped before committing as requested`, and no success text for `--abort`.
  - `go/libraries/doltcore/sqle/dprocedures/dolt_merge.go`: stored procedure returns `merge aborted` internally for a valid abort and `fatal: There is no merge to abort` when no merge is active.
  - `integration-tests/bats/merge.bats`: focused cases cover up-to-date merges, `--abort` preserving working changes and cleaning merge status, squash merge output, and `--no-ff --no-commit` status output.
  - `integration-tests/bats/conflict-detection.bats` and `integration-tests/bats/status.bats`: adjacent no-commit / active-merge status assertions.
- Static inventory for this slice:
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '(^go/cmd/dolt/commands/merge\.go$|^go/libraries/doltcore/sqle/dprocedures/dolt_merge\.go$|^integration-tests/bats/(merge|conflict-detection|status)\.bats$)' | wc -l`: `5` focused upstream source/test paths.
  - `rg -n 'Everything up-to-date|Squash commit -- not updating HEAD|Automatic merge went well; stopped before committing as requested|--abort restores working changes|--abort leaves clean working|fatal: There is no merge to abort|merge aborted|--no-commit|--squash|Already up to date|not updating HEAD' ... | wc -l`: `37` targeted merge-control references.
- Focused upstream BATS evidence:
  - Command from `.upstream-cache/dolt/integration-tests/bats`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 30m bats --filter 'merge: (db collation ff merge|db collation non ff merge|--abort restores working changes|--abort leaves clean working, staging roots|squash merge|dolt merge does not ff and not commit with --no-ff and --no-commit)' merge.bats`
  - Result: exit `0`, plan `1..6`; all six focused tests passed.
- Direct cache-local CLI probes in throwaway `.upstream-cache/dolt/tmp/merge-control-probe-*` repositories:
  - Up-to-date merge printed exactly `Everything up-to-date`.
  - `dolt merge main --no-ff --no-commit` printed `Updating <head>..<merge>`, `Automatic merge went well; stopped before committing as requested`, `test1 | 1 +`, and `1 tables changed, 1 rows added(+), 0 rows modified(*), 0 rows deleted(-)`.
  - `dolt merge --squash merge_branch --no-commit` printed `Updating <head>..<merge>`, `Squash commit -- not updating HEAD`, `Automatic merge went well; stopped before committing as requested`, and the same one-table stat shape.
  - A conflicting `dolt merge other --no-commit` followed by `dolt merge --abort` exited `1` for the conflict, exited `0` for abort, printed no abort success body, returned `dolt_merge_status.is_merging = false`, and preserved the pre-merge working-set row in `test2`.
- Native PHP evidence:
  - `MergeStatusTable::mergeSuccessTranscript()` now renders the up-to-date early return, `Updating`, squash, no-commit, and success-stat transcript boundary.
  - `MergeStatusTable::abortMergeState()` now models valid abort as empty CLI output plus inactive `dolt_merge_status`, preserves caller-supplied working-table names, and throws upstream-shaped `fatal: There is no merge to abort` when no merge is active.
  - `examples/wordpress-merge-status-review.php` now includes no-commit, squash/no-commit, up-to-date, and abort-state outputs for a WordPress import merge review.
  - Focused PHP rerun: `MergeStatusTableTest.php` passed with `11` behavior tests, `67` assertions, and `0` failures.
  - Dolt lane-only PHP rerun: `19` Dolt test files, `201` behavior tests, `1028` assertions, and `0` failures.
- Root harness:
  - An initial guard found an active root harness, so this lane did not start a duplicate run while it was active.
  - After the guard cleared, `php tools/run-tests.php` was run once.
  - Result: exit `1`; `184` test files, `19,485` assertions, and `1` failure outside Dolt.
  - Failure: `lanes/rclone/tests/DeletePlanningTest.php` test `operations delete dry run accounts file attempts without provider mutation`, where `deleteBytes` expected `18` and actual was `17`.
  - Dolt tests reached by the root harness passed.
- Boundary unchanged: this maps CLI transcript and projected abort state for review tooling. It does not implement a full Dolt working-set merge engine, full `go test ./...`, full BATS parity, SQL-server coverage, or compatibility/client suites.

## Runner Refresh 2026-05-23 05:31 UTC Merge Artifact Prelude And Success Stats

- Cache inspection before changing/building:
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - No delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: exit `0`; all four packages were already installed. Tool probes remain `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`, with RPMs `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, and `libicu-devel-77.1-2.fc44.x86_64`.
- Static inventory refresh:
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '(^go/cmd/dolt/commands/merge\.go$|^go/libraries/doltcore/merge/(merge_stats|merge|merge_rows|merge_prolly_rows).*\.go$|^go/libraries/doltcore/sqle/dprocedures/dolt_merge\.go$|^go/libraries/doltcore/sqle/enginetest/dolt_queries_merge\.go$|^integration-tests/bats/(merge|1pk5col-ints|column_tags|conflict-detection|constraint-violations)\.bats$)' | wc -l`: `16` focused upstream source/test paths.
  - `rg -n 'Auto-merging|CONFLICT \((content|schema)\): Merge conflict|CONSTRAINT VIOLATION \(content\): Merge created constraint violation|tables changed, [0-9]+ rows added\(\+\), [0-9]+ rows modified\(\*\), [0-9]+ rows deleted\(-\)| added$| deleted$|printSuccessStats|print(Modifications|Additions|Deletions|ConflictsAndViolations)|calculateMergeStats|MergeStats|TableAdded|TableRemoved|TableModified' ... | wc -l`: `100` targeted merge-output/stat references.
- Cache-local build:
  - `mkdir -p /home/claude/port-libs/.upstream-cache/dolt/tmp /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin /home/claude/port-libs/.upstream-cache/dolt/bats-tmp`
  - From `.upstream-cache/dolt/go`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit `0`; cache-local `dolt`, `noms`, and `remotesrv` rebuilt. `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - From `.upstream-cache/dolt/go`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit `0`; `ok github.com/dolthub/dolt/go/libraries/doltcore/merge 6.206s`.
  - From `.upstream-cache/dolt/go`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltMerge(Prepared|Artifacts)?$' -count=1 -timeout 20m`
  - Result: exit `0`; `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 4.795s`.
- Bounded BATS evidence from `.upstream-cache/dolt/integration-tests/bats`:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 20m bats --filter 'merge: (prints merge stats|merge with --no-commit prints correct merge stats)' merge.bats`
  - Result: exit `0`, plan `1..2`; both merge-stat cases passed.
  - Same environment with `timeout 15m bats --filter '1pk5col-ints: display correct merge stats' 1pk5col-ints.bats`
  - Result: exit `0`, plan `1..1`; exact single-table merge-stat assertion passed.
  - Same environment with `timeout 15m bats --filter 'conflict-detection: two branches modify same cell. merge. conflict' conflict-detection.bats`
  - Result: exit `0`, plan `1..1`; content-conflict merge case passed.
  - Same environment with `timeout 15m bats --filter 'constraint-violations: unique key violations create unmerged tables' constraint-violations.bats`
  - Result: exit `0`, plan `1..1`; unique-key constraint-violation merge case passed.
- Direct cache-local CLI probes in throwaway `.upstream-cache/dolt/tmp/merge-artifact-probe-*` repositories:
  - Initial success-stat probe expected `1 tables changed, 3 rows added(+), 1 rows modified(*), 1 rows deleted(-)` and failed. The actual upstream output was `1 tables changed, 2 rows added(+), 1 rows modified(*), 1 rows deleted(-)`, with `added_t added` and `deleted_t deleted` printed separately. The rerun used the upstream-observed boundary and passed.
  - Success-stat/add/delete probe commands used cache-local `dolt init`, local test user config, `dolt sql -q "CREATE TABLE t ...; CREATE TABLE deleted_t ...; INSERT ...;"`, `dolt commit -Am 'base'`, branch `right`, `dolt sql -q "INSERT ...; DELETE ...; UPDATE ...; CREATE TABLE added_t ...; DROP TABLE deleted_t;"`, `dolt commit -Am 'right changes'`, checkout `main`, `dolt sql -q "INSERT INTO t VALUES (5,5);"`, `dolt commit -Am 'left changes'`, and `dolt merge right -m 'merge right'`.
  - Result: exit `0`; output contained `Updating`, `t | 4 ++*-`, `1 tables changed, 2 rows added(+), 1 rows modified(*), 1 rows deleted(-)`, `added_t added`, and `deleted_t deleted`.
  - Content-conflict probe used a keyed table `t`, divergent updates on `main` and `right`, and `dolt merge right -m 'merge right'`.
  - Result: expected exit `1`; output contained `Auto-merging t`, `CONFLICT (content): Merge conflict in t`, `Automatic merge failed; 1 table(s) are unmerged.`, and `Use 'dolt conflicts' to investigate and resolve conflicts.`
  - Schema-conflict probe used `DROP TABLE t` on `main`, `ALTER TABLE t ADD COLUMN z int` on `right`, and `dolt merge right -m 'merge right'`.
  - Result: expected exit `1`; output contained `Auto-merging t`, `CONFLICT (schema): Merge conflict in t`, `Automatic merge failed; 1 table(s) are unmerged.`, and `Use 'dolt conflicts' to investigate and resolve conflicts.`
  - Constraint-violation probe mirrored the focused upstream unique-key case with `CALL DOLT_BRANCH('right')`, `ALTER TABLE t ADD UNIQUE uniq_col1 (col1)`, duplicate rows on `right`, checkout `main`, and `dolt merge right`.
  - Result: expected exit `1`; output contained `Auto-merging t`, `CONSTRAINT VIOLATION (content): Merge created constraint violation in t`, `Automatic merge failed; 1 table(s) are unmerged.`, `Fix constraint violations and then commit the result.`, and the `dolt_constraint_violations` system-table guidance.
- Required repository check after this metadata update:
  - `php tools/run-tests.php`
  - Result: exit `0`; `183` test files, `19,153` assertions, and `0` failures.
  - Subsequent logged rerun for final-state capture: `php tools/run-tests.php > /tmp/dolt-final-root-run.log 2>&1`
  - Result: exit `1`; `183` test files, `19,242` assertions, and `1` failure outside Dolt: `purge falls back when direct provider returns cant purge` in `lanes/rclone/tests/DeletePlanningTest.php`.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Refresh 2026-05-23 05:15 UTC Tooling And Local Constraint/Diff Slice

- Cache inspection before changing/building:
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - No delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: all four packages were already installed; `Nothing to do.`
  - Tool probes and RPMs: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`, `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
- Cache-local build:
  - `mkdir -p /home/claude/port-libs/.upstream-cache/dolt/tmp /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin /home/claude/port-libs/.upstream-cache/dolt/bats-tmp`
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit `0`; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit `0`; 14 packages with tests passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.066s`, and `merge` passed in `7.573s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 30m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|PatchTableFunction|PatchTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable|DoltUserPrivileges)$' -count=1 -timeout 30m`
  - Result: exit `0`; focused sqle/enginetest diff, summary, stat, schema, patch, column/system diff, commit-diff, log, branch, branch-activity, status/conflict, and user-privilege coverage passed in `17.336s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test ./libraries/doltcore/doltdb -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable|ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 10m`
  - Result: exit `0`; focused schema/procedure/history integration tests passed in `0.375s`, and ancestor spec unit tests passed in `0.039s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltVerifyConstraints$|TestDoltMerge/(keyless table merge with constraint violations|keyless table merge with constraint violation on duplicate rows|Constraint violations are persisted|violation system table supports multiple violations per row|clearing constraint violations \(MySQL\): single delete, bulk delete, and commit|merge error lists all constraint violations when table has multiple violations|merge error includes row count for foreign key violations|merge error includes row count for null constraint violations|merge error includes row count for check constraint violations)$' -count=1 -timeout 20m -v`
  - Result: exit `0`; full `TestDoltVerifyConstraints` and focused `TestDoltMerge`, `TestDoltMergePrepared`, and `TestDoltMergeArtifacts` constraint-violation subtests passed; package result `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 1.872s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'constraint-violations: functions blocked with violations' constraint-violations.bats`
  - Result: exit `0`, plan `1..1`; focused status/commit guidance case passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 90m bats verify-constraints.bats constraint-violations.bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats branch.bats sql-branch.bats keyless.bats keyless-foreign-keys.bats`
  - Result: exit `0`, plan `1..543`; `499` runnable tests passed and `44` upstream-declared skips remained across verify-constraints, constraint-violations, diff/schema/rename/primary-key/diff-stat/query-diff/column-tag/sql-diff, merge/schema-conflict/conflict-detection, commit-diff/log/status/sql-status, branch/sql-branch, keyless, and keyless foreign-key behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit `1`, plan `1..1`; pristine `status.bats` still truncates `s04qh6rqmg3uhq6nmq034soc5h12jljm` to `6rqmg3uhq6nmq034soc5h12jljm`, and `dolt reset` reports `branch not found`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: exit `0`, plan `1..1`; runner-local fixed helper passed the exact status repro.
- Required repository check after this metadata update:
  - `php tools/run-tests.php`
  - Result: exit `0`; `183` test files, `18,901` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Implementation Lane 2026-05-23 Constraint-Violation Status And Commit Guidance

- Upstream source/test boundary inspected:
  - `integration-tests/bats/constraint-violations.bats`: `constraint-violations: functions blocked with violations` asserts `Fix constraint violations`, blocked `dolt commit` output containing `constraint violation`, and `constraint violations fixed` after deleting per-table violation rows.
  - `integration-tests/bats/status.bats`: active merge status assertions cover `You have unmerged tables.`, `fix conflicts and run "dolt commit"`, `use "dolt merge --abort"`, `Unmerged paths:`, and `use "dolt add <table>..." to mark resolution`.
  - `integration-tests/bats/merge.bats`: no-commit merge status asserts `All conflicts and constraint violations fixed but you are still merging.` before a successful merge commit.
  - `go/cmd/dolt/commands/status.go`: `printEverything()` chooses the status guidance phrase for conflicts, constraint violations, both, or all-fixed active merges, then prints constraint-only tables as `modified`.
  - `go/cmd/dolt/commands/commit.go`: `PrintDiffsNotStaged()` prints the same unresolved-path block for commit attempts and sorts constraint-only tables before rendering them as `modified`.
- Static inventory for this slice:
  - `rg -n "All conflicts and constraint violations fixed|fix constraint violations|constraint violations fixed|You have unmerged tables|Unmerged paths|dolt add <table>|constraint violation" ... | wc -l`: `41` targeted guidance references across the five focused upstream files above.
- Focused upstream runner:
  - Command from `.upstream-cache/dolt/integration-tests/bats`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'constraint-violations: functions blocked with violations' constraint-violations.bats`
  - Result: exit `0`, plan `1..1`; the focused BATS case passed.
- Native PHP evidence:
  - `MergeStatusTable::statusGuidance()` now renders Dolt's active-merge status guidance for conflicts, constraint violations, mixed unresolved state, and the all-fixed still-merging state.
  - `MergeStatusTable::commitUnmergedPaths()` now renders the unresolved-path block used by `dolt commit`, including schema conflicts, data conflicts, and constraint-only tables displayed as `modified`.
  - `examples/wordpress-merge-status-review.php` now includes the status and commit guidance strings for an import merge where `wp_posts` has conflicts, `wp_options` has a schema conflict, and `wp_postmeta` / `wp_import_audit` have constraint-only blockers.
  - Focused PHP rerun: `MergeStatusTableTest.php` plus `ConstraintViolationsTableTest.php` passed with `65` assertions and `0` failures.
  - Dolt lane PHP rerun: `19` test files, `198` behavior tests, `989` assertions, `0` failures.
  - Root harness note: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php lanes/dolt/tests/ConstraintViolationsTableTest.php` ignores file arguments and ran the full root harness; it exited `0` with `183` test files, `18,469` assertions, and `0` failures.
  - Final guarded root run: after `pgrep -af '^php tools/run-tests\.php( |$)'` showed no active root harness, `php tools/run-tests.php` exited `0` with `183` test files, `18,644` assertions, and `0` failures.
- Boundary unchanged: this maps CLI guidance text and system-table-derived unresolved path rendering. It does not implement a full SQL transaction engine, full Dolt merge execution, full `go test ./...`, or full BATS parity.

## Runner Refresh 2026-05-23 04:23-04:43 UTC Expanded Local Constraint/Diff Slice

- Cache inspection before changing/building:
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - No delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: all four packages were already installed; `Nothing to do.`
  - Tool probes and RPMs: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`, `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
- Static inventory refresh:
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '(constraint|violation|foreign-keys|merge).*(_test\.go|\.bats$)|constraint_violations|dolt_verify_constraints|dolt_queries_verify_constraints|dolt_queries_merge\.go|table_of_tables_with_violations\.go' | wc -l`: `28` focused upstream source/test paths.
  - `rg -n "dolt_constraint_violations|DOLT_VERIFY_CONSTRAINTS|constraint-violations:|verify-constraints:" .upstream-cache/dolt/integration-tests/bats/constraint-violations.bats .upstream-cache/dolt/integration-tests/bats/verify-constraints.bats .upstream-cache/dolt/integration-tests/bats/merge.bats .upstream-cache/dolt/integration-tests/bats/keyless-foreign-keys.bats .upstream-cache/dolt/go/libraries/doltcore/sqle/dtables .upstream-cache/dolt/go/libraries/doltcore/sqle/dprocedures .upstream-cache/dolt/go/libraries/doltcore/sqle/enginetest | wc -l`: `466` targeted references.
  - `rg -c 'foreign key,.*\{""Index""' .upstream-cache/dolt/integration-tests/bats/constraint-violations.bats`: `69` static FK metadata assertion lines.
- Cache-local build:
  - Command from `.upstream-cache/dolt/go`: `mkdir -p /home/claude/port-libs/.upstream-cache/dolt/tmp /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin /home/claude/port-libs/.upstream-cache/dolt/bats-tmp && env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv && env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: exit `0`; cache-local `dolt`, `noms`, and `remotesrv` rebuilt; `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit `0`; 14 packages with tests passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.043s`, and `merge` passed in `6.062s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 30m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|PatchTableFunction|PatchTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable|DoltUserPrivileges)$' -count=1 -timeout 30m`
  - Result: exit `0`; focused sqle/enginetest diff, summary, stat, schema, patch, column/system diff, commit-diff, log, branch, branch-activity, status/conflict, and user-privilege coverage passed in `16.086s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltVerifyConstraints$|TestDoltMerge/(keyless table merge with constraint violations|keyless table merge with constraint violation on duplicate rows|Constraint violations are persisted|violation system table supports multiple violations per row|clearing constraint violations \(MySQL\): single delete, bulk delete, and commit|merge error lists all constraint violations when table has multiple violations|merge error includes row count for foreign key violations|merge error includes row count for null constraint violations|merge error includes row count for check constraint violations)$' -count=1 -timeout 20m -v`
  - Result: exit `0`; full `TestDoltVerifyConstraints` and focused `TestDoltMerge`, `TestDoltMergePrepared`, and `TestDoltMergeArtifacts` constraint-violation subtests passed in `2.311s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test ./libraries/doltcore/doltdb -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable|ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 10m`
  - Result: exit `0`; focused schema/procedure/history integration tests passed in `0.359s`, and ancestor spec unit tests passed in `0.075s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 90m bats verify-constraints.bats constraint-violations.bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats branch.bats sql-branch.bats keyless.bats keyless-foreign-keys.bats`
  - Result: exit `0`, plan `1..543`; `500` runnable tests passed and `43` upstream-declared skips remained across verify-constraints, constraint-violations, diff/schema/rename/primary-key/diff-stat/query-diff/column-tag/sql-diff, merge/schema-conflict/conflict-detection, commit-diff/log/status/sql-status, branch/sql-branch, keyless, and keyless foreign-key behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit `1`, plan `1..1`; pristine `status.bats` still truncates `va05d2oufd14jer8uifte2mp8buks94q` to `2oufd14jer8uifte2mp8buks94q`, and `dolt reset` reports `branch not found`.
- Required repository check after this metadata update:
  - `php tools/run-tests.php > /tmp/dolt-runner-root-php-0443.log 2>&1; status=$?; tail -60 /tmp/dolt-runner-root-php-0443.log; exit $status`
  - Result: exit `0`; `183` test files, `18,405` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Implementation Lane 2026-05-23 Constraint-Violation Merge Error Text

- Upstream source/test boundary inspected:
  - `go/libraries/doltcore/sqle/dsess/transactions.go`: `ErrUnresolvedConstraintViolationsCommit`, `ConstraintViolationsListPrefix`, per-violation description formatting, sorted description grouping, and `(%d row(s))` suffix only when duplicate descriptions are counted.
  - `go/libraries/doltcore/sqle/enginetest/dolt_queries_merge.go`: focused `TestDoltMergeArtifacts` script cases for multiple unique-index violations and row-count text for foreign-key, not-null, and CHECK violations.
  - `go/libraries/doltcore/sqle/enginetest/dolt_transaction_queries.go`: transaction commit error text uses the same prefix and violation-list boundary.
  - `go/libraries/doltcore/merge/violations_unique_prolly.go` and `go/libraries/doltcore/merge/violations_fk_prolly.go`: metadata field names for unique, null, CHECK, and foreign-key violation descriptions.
  - `go/cmd/dolt/commands/merge.go`: CLI merge failure guidance remains separate from the transaction error text.
- Static inventory for this slice:
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '(^go/libraries/doltcore/sqle/dsess/transactions\.go$|^go/libraries/doltcore/sqle/enginetest/dolt_queries_merge\.go$|^go/libraries/doltcore/sqle/enginetest/dolt_transaction_queries\.go$|^go/libraries/doltcore/merge/violations_.*\.go$|^go/cmd/dolt/commands/merge\.go$)' | wc -l`: `9` focused upstream source/test paths.
  - `rg -n 'ConstraintViolationsListPrefix|ErrUnresolvedConstraintViolationsCommit|merge error (lists all constraint violations|includes row count)|Type: (Unique Key|Foreign Key|Null|Check) Constraint Violation|row\(s\)' ... | wc -l`: `34` targeted references across the files above.
- Focused upstream runner:
  - Command from `.upstream-cache/dolt/go`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltMerge/(merge error lists all constraint violations when table has multiple violations|merge error includes row count for foreign key violations|merge error includes row count for null constraint violations|merge error includes row count for check constraint violations)$' -count=1 -timeout 20m -v`.
  - Result: exit `0`; `TestDoltMergeArtifacts` ran and passed all four focused merge-error cases in `0.24s`; package result `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 1.046s`.
- Native PHP evidence:
  - `ConstraintViolationsTable::unresolvedMergeError()` now emits the upstream transaction error prefix and grouped violation-list suffix for unique-index, foreign-key, not-null, and CHECK metadata.
  - `ConstraintViolationsTable::mergeViolationSummaryText()` groups identical descriptions, sorts descriptions within each table, joins table groups with upstream's comma boundary, and adds row-count suffixes only when duplicate descriptions occur.
  - `examples/wordpress-merge-status-review.php` now includes WordPress import merge constraint-error text covering orphaned `wp_postmeta` foreign keys, invalid `wp_import_audit` CHECK rows, null `wp_posts.post_title` rows, and duplicate `wp_options.option_name` rows.
  - Focused PHP rerun: `ConstraintViolationsTableTest.php` plus `MergeStatusTableTest.php` passed with `55` assertions and `0` failures.
  - Lane PHP rerun: `19` Dolt test files, `195` behavior tests, `979` assertions, `0` failures.
  - Required root harness was not started by this lane because `pgrep -af '^php tools/run-tests\.php( |$)'` showed active root runners: PID `182226` (`claude`, started `2026-05-23 04:46:03 UTC`, parent `180401`) and PID `183396` (`claude`, started `2026-05-23 04:46:10 UTC`, parent `4121529`).
- Boundary unchanged: this is native formatting and grouping for Dolt's unresolved constraint-violation transaction/merge error text. It does not execute full Dolt merge resolution, SQL transaction management, full `go test ./...`, or full BATS parity.

## Implementation Lane 2026-05-23 Constraint-Violation Delete Semantics

- Upstream source/test boundary inspected:
  - `go/libraries/doltcore/sqle/dtables/constraint_violations_prolly.go`: `prollyCVDeleter.Delete` builds artifact delete keys from row key columns / `dolt_row_hash`, `from_root_ish`, violation type, and a `ConstraintViolationInfoHash`, then also attempts the legacy no-info-hash key.
  - `go/libraries/doltcore/sqle/enginetest/dolt_queries_merge.go`: focused scripts cover keyless unique/FK cleanup, duplicate keyless FK row representation, multiple violations on the same keyed row, and MySQL JSON-filtered single-violation deletion before row-key bulk deletion and commit.
- Bounded upstream runner:
  - Command from `.upstream-cache/dolt/go`: `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltMerge/(keyless table merge with constraint violations|keyless table merge with constraint violation on duplicate rows|violation system table supports multiple violations per row|clearing constraint violations \(MySQL\): single delete, bulk delete, and commit)$' -count=1 -timeout 20m -v`.
  - Result: exit `0`; `TestDoltMerge`, `TestDoltMergePrepared`, and `TestDoltMergeArtifacts` matched focused subtests and passed in `0.859s`.
- Native PHP verification:
  - Focused `ConstraintViolationsTableTest.php`: `9` behavior tests, `34` assertions, `0` failures.
  - Dolt lane-only PHP: `19` test files, `193` behavior tests, `972` assertions, `0` failures.
  - Required root `php tools/run-tests.php`: exit `0`; `183` test files, `18,203` assertions, `0` failures.
- Boundary unchanged: this maps per-table constraint-violation cleanup semantics, not a general SQL DELETE engine or full upstream `go test ./...` / full BATS parity.

## Implementation Lane 2026-05-23 Foreign-Key Constraint-Violation Metadata

- Upstream oracle used without launching additional upstream runners:
  - `go/libraries/doltcore/merge/violations_fk_prolly.go`: `FkCVMeta` exposes `Index`, `Table`, `Columns`, `OnDelete`, `OnUpdate`, `ForeignKey`, `ReferencedIndex`, `ReferencedTable`, and `ReferencedColumns`.
  - `go/libraries/doltcore/sqle/dtables/constraint_violations_prolly.go`: `ArtifactTypeForeignKeyViol` unmarshals `FkCVMeta` into the final per-table `violation_info` cell.
  - `rg -c 'foreign key,.*\{""Index""' .upstream-cache/dolt/integration-tests/bats/constraint-violations.bats`: `69` static FK metadata assertion lines covering restrict, cascade, set-null, chained, cyclic, and self-referential cases.
- Native PHP verification:
  - `php -l lanes/dolt/src/ConstraintViolationsTable.php`: pass.
  - `php -l lanes/dolt/tests/ConstraintViolationsTableTest.php`: pass.
  - `php -l lanes/dolt/fixtures/wp-foreign-key-constraint-violation-review.php`: pass.
  - `php -l lanes/dolt/examples/wordpress-foreign-key-constraint-violation-review.php`: pass.
  - Focused `ConstraintViolationsTableTest.php`: exit `0`; `7` behavior tests, `13` assertions, `0` failures.
  - Dolt lane-only PHP: exit `0`; `19` test files, `191` behavior tests, `951` assertions, `0` failures.
- Root PHP status:
  - A separate root `php tools/run-tests.php` was active when checked (`pgrep -af "php tools/run-tests.php"` returned PID `3914252`), so this lane did not start a duplicate root run at that point.
  - Final current-snapshot root check `php tools/run-tests.php > /tmp/dolt-fk-root-php.log 2>&1`: exit `1`; `183` test files, `17,925` assertions, `2` failures outside Dolt.
  - Failing root tests: `lanes/quadrable/tests/QuadbStoreTest.php` tests `native quadb store restores full-head raw LMDB cursor dumps without portable state` and `native quadb store restores upstream full-head LMDB cursor slices and rejects proof witnesses`; both failed after `QuadbStore.php` reported missing `partialProofHeads` / `partialDetachedHead` state.

## Runner Refresh 2026-05-23 04:11 UTC Constraint-Violation Merge/Verify Slice

- Cache inspection before changing/building:
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`.
  - No delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: all four packages were already installed; `Nothing to do.`
  - Tool probes and RPMs: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`, `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
- Static inventory refresh:
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '(constraint|violation|foreign-keys|merge).*(_test\.go|\.bats$)|constraint_violations|dolt_verify_constraints|dolt_queries_verify_constraints|dolt_queries_merge\.go|table_of_tables_with_violations\.go' | wc -l`: `28` focused upstream source/test paths.
  - `rg -n "dolt_constraint_violations|DOLT_VERIFY_CONSTRAINTS|constraint-violations:|verify-constraints:" ... | wc -l`: `415` targeted references across constraint-violation BATS, verify-constraints BATS, focused merge/keyless-FK BATS, dtables, dprocedures, and enginetest files.
- Cache-local build:
  - `mkdir -p /home/claude/port-libs/.upstream-cache/dolt/tmp /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin /home/claude/port-libs/.upstream-cache/dolt/bats-tmp && env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv && env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: exit `0`; cache-local `dolt`, `noms`, and `remotesrv` rebuilt; `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltVerifyConstraints$' -count=1 -timeout 20m -v`
  - Result: exit `0`; full `TestDoltVerifyConstraints` passed in `0.773s`, covering no-FK violations, FK violations for named tables / all tables / output-only, NULL FK handling, unique violations, CHECK violations, working-set verification, and `dolt_constraint_violations` / `dolt_constraint_violations_<table>` rows.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltMerge/(keyless table merge with constraint violations|keyless table merge with constraint violation on duplicate rows|Constraint violations are persisted|violation system table supports multiple violations per row|clearing constraint violations \(MySQL\): single delete, bulk delete, and commit|merge error lists all constraint violations when table has multiple violations|merge error includes row count for foreign key violations|merge error includes row count for null constraint violations|merge error includes row count for check constraint violations)$' -count=1 -timeout 20m -v`
  - Result: exit `0`; the regex matched and passed focused `TestDoltMerge`, `TestDoltMergePrepared`, and `TestDoltMergeArtifacts` constraint-violation subtests in `1.109s`, covering keyless unique/FK violations, persisted FK violations, case-insensitive per-table system-table access, multi-violation rows, single/bulk delete cleanup, merge commit after cleanup, and merge-error row-count text for unique/FK/null/CHECK violations.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/merge ./libraries/doltcore/schema -run 'Test(FkIdxKeyDescs_FkColNotAtFront|ColConstraintsAreEqual)$' -count=1 -timeout 10m -v`
  - Result: exit `0`; `merge` passed `TestFkIdxKeyDescs_FkColNotAtFront` in `1.497s`, and `schema` passed `TestColConstraintsAreEqual` in `0.045s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 30m bats verify-constraints.bats`
  - Result: exit `0`, plan `1..9`; all 9 tests passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 60m bats constraint-violations.bats`
  - Result: exit `0`, plan `1..57`; all 57 tests passed across function blocking, forced commits, ancestor-present / ancestor-missing FK matrices for `restrict`, `cascade`, and `set null`, missing parent/child cases, chained/cyclic/self-referential FKs, unique-key violations, altered FK-over-PK behavior, and keyless constraint violations.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 20m bats --filter 'merge: (non-violating merge succeeds when violations already exist|non-conflicting / non-violating merge succeeds when conflicts and violations already exist|conflicting merge should retain previous conflicts and constraint violations|violated check constraint)' merge.bats`
  - Result: exit `0`, plan `1..4`; 3 runnable tests passed and 1 upstream-declared skip remained for `merge: violated check constraint`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 20m bats --filter 'keyless-foreign-keys: (Resolve catches violations|child violation correctly detected|insert ignore into works correctly w/ FK violations)' keyless-foreign-keys.bats`
  - Result: exit `0`, plan `1..3`; 2 runnable tests passed and 1 upstream-declared skip remained for `keyless-foreign-keys: Resolve catches violations`.
- Required repository check after this metadata update:
  - `php tools/run-tests.php`
  - Result: exit `0` with `183` test files, `17,934` assertions, and `0` failures.
  - Final post-recording rerun `jq empty lanes/dolt/UPSTREAM_TEST_MANIFEST.json && jq empty lanes/dolt/lane-status.json && php tools/run-tests.php > /tmp/dolt-root-after-latest-note.log 2>&1; status=$?; tail -40 /tmp/dolt-root-after-latest-note.log; exit $status`
  - Result: exit `1` with `183` test files, `17,951` assertions, and `7` unrelated failures after Dolt tests had passed: six LightningCSS failures in `CssMinifierTest.php` / `NestingTransformerTest.php` and one Quadrable failure in `QuadbStoreTest.php`.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Tooling Refresh 2026-05-23 03:54 UTC

- Cache inspection before changing/building:
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`.
  - No delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: all four packages were already installed; `Nothing to do.`
  - Tool probes and RPMs: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`, `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
- Cache-local build:
  - Initial diagnostic command from `.upstream-cache/dolt`: `env ... go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit `1`; `go: cannot find main module`, because the upstream Go module is `.upstream-cache/dolt/go`.
  - Corrected command from `.upstream-cache/dolt/go`: `mkdir -p ../tmp ../bats-home/go/bin ../bats-tmp && env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv && env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: exit `0`; cache-local `dolt`, `noms`, and `remotesrv` rebuilt; `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit `0`; 14 packages with tests passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.044s`, and `merge` passed in `6.061s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 30m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|PatchTableFunction|PatchTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable|DoltUserPrivileges)$' -count=1 -timeout 30m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 15.825s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.267s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/doltdb -run 'Test(ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/doltdb 0.045s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestHistorySystemTable/(can sort by dolt_log.commit|dolt_commit_ancestors table with commit_hash filter ignored for max1row optimization)$|TestDoltScripts/test has_ancestor$' -count=1 -timeout 10m -v`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.428s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats branch.bats sql-branch.bats keyless.bats`
  - Result: exit `0` with plan `1..419`; `394` runnable tests passed and `25` upstream-declared skips remained across diff/schema/rename/primary-key/diff-stat/query-diff/column-tag/sql-diff/merge/schema-conflict/conflict-detection/commit-diff/log/status/sql-status/branch/sql-branch/keyless behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit `1` with plan `1..1`; pristine `status.bats` still truncates `fd1olangknu5apb3keg0kp0aa5mnb01o` to `angknu5apb3keg0kp0aa5mnb01o`, and `dolt reset` reports `branch not found`.
- Required repository check after this metadata update:
  - `php tools/run-tests.php`
  - Result: exit `0` with `182` test files, `17,632` assertions, and `0` failures.
- Final quota cleanup and repository check:
  - Intermediate final rerun `php tools/run-tests.php`: exit `1` with `182` test files, `16,857` assertions, and `87` failures after MarkerPDF tests hit `Disk quota exceeded` while writing `/tmp/markerpdf-supplied-document-*`; Dolt tests reached by the run passed.
  - Inspected disk pressure with `df -h . /tmp /home/claude/port-libs/.upstream-cache/dolt`, `du -sh .upstream-cache/dolt/tmp .upstream-cache/dolt/bats-tmp .upstream-cache/dolt/.gocache .upstream-cache/dolt/.gomodcache .upstream-cache/dolt/bats-home`, and `/tmp` temp scans; identified stale `/tmp/dolt-runner-fMpBoFL9` at `1.9G`.
  - `rm -rf /tmp/dolt-runner-fMpBoFL9 && df -h /tmp`: removed only the stale Dolt runner temp checkout/cache, not `.upstream-cache/dolt`; `/tmp` free space rose to `5.7G`.
  - Final rerun `php tools/run-tests.php`: exit `0` with `182` test files, `17,666` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Refresh 2026-05-23 Constraint Violations System Table Slice

- Focused upstream source/test inspection:
  - `go/libraries/doltcore/sqle/dtables/table_of_tables_with_violations.go`: `dolt_constraint_violations` exposes `table` and `num_violations` rows for tables that currently carry constraint violation artifacts.
  - `go/libraries/doltcore/sqle/dtables/constraint_violations_prolly.go`: per-table `dolt_constraint_violations_<table>` rows emit `from_root_ish`, `violation_type`, primary-key columns or keyless `dolt_row_hash`, non-primary-key columns, and `violation_info`.
  - `go/libraries/doltcore/merge/violations_unique_prolly.go` and `go/libraries/doltcore/merge/violations_fk_prolly.go`: focused metadata shapes are `{"Name","Columns"}` for unique indexes, `{"Columns"}` for not-null, `{"Name","Expression"}` for CHECK, and the foreign-key table/index/referenced-table fields for FK violations.
  - `go/libraries/doltcore/sqle/enginetest/dolt_queries_verify_constraints.go`: focused CHECK verify assertions select `violation_type`, primary key, row columns, and JSON `violation_info` from `dolt_constraint_violations_t`.
- Static inventory refresh:
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '(constraint.*viol|verify-constraints|violations).*(_test\.go|\.bats$)|constraint_violations|table_of_tables_with_violations|violations_(unique|fk|prolly)' | wc -l`: `11` focused upstream source/test paths.
  - `rg -n "dolt_constraint_violations|CheckCVMeta|UniqCVMeta|FkCVMeta|NullViolationMeta|table,num_violations|constraint violations" ... | wc -l`: `376` targeted references inspected without widening sparse checkout.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltVerifyConstraints/verify-constraints: check violations: working set$' -count=1 -timeout 10m -v`
  - Result: exit `0`; focused `TestDoltVerifyConstraints/verify-constraints:_check_violations:_working_set` passed in `0.351s`, including `select * from dolt_constraint_violations` and per-table CHECK violation-info assertions.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'constraint-violations: functions blocked with violations' constraint-violations.bats`
  - Result: exit `0`, plan `1..1`; confirmed `dolt_constraint_violations` reports `test,2` for a unique-index merge violation and that the per-table table can be deleted before committing.
- Direct cache-local CLI probe:
  - A throwaway repo created a unique-index conflict and ran `SELECT * FROM dolt_constraint_violations_test -r=csv`; output columns were `from_root_ish,violation_type,pk,v1,violation_info`, with two `unique index` rows and `{"Name": "v1", "Columns": ["v1"]}` metadata.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `19` Dolt test files, `189` behavior tests, `947` assertions, and `0` failures.
- Required repository check after this slice:
  - `php tools/run-tests.php`
  - Result: exit `0` with `183` test files, `17,837` assertions, and `0` failures.
- Boundary unchanged: this maps bounded constraint-violation system table row projection. It does not claim full constraint validation, full merge artifact storage, per-table deletion/root update semantics, full BATS, or full `go test ./...` parity.

## Runner Refresh 2026-05-23 Schema Show Check Preservation Slice

- Focused upstream source/test inspection:
  - `go/cmd/dolt/commands/schcmds/show.go`: `dolt schema show` renders `table @ <commit>` headers, uses the working root by default, skips internal full-text tables, reports missing requested tables on stderr, and prints the engine `SHOW CREATE TABLE` statement.
  - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: CREATE TABLE statement generation appends check constraints after indexes and foreign keys through `GenerateCreateTableCheckConstraintClause`.
  - `integration-tests/bats/sql-check-constraints.bats`: seven `check constraints survive ...` cases assert that `dolt schema show` still includes `CHECK` and `` `c1` > 3 `` after adding, renaming, modifying, or dropping an unrelated column, adding or dropping a primary key, and renaming the table.
- Static inventory refresh:
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '(schema|check|constraint).*(_test\.go|\.bats$)|schcmds|sqlfmt/schema_fmt.go|information_schema_database_schema.go|schema_table' | wc -l`: `42` targeted schema/check executable/source paths.
  - `rg -n "dolt schema show|check constraints survive" .upstream-cache/dolt/integration-tests/bats .upstream-cache/dolt/go/cmd/dolt/commands/schcmds .upstream-cache/dolt/go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go | wc -l`: `296` references inspected without hydrating broader blobs.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 20m bats --filter 'sql-check-constraints: check constraints survive (adding a new column|renaming a column|modifying a column|dropping a column|adding a primary key|dropping a primary key|renaming a table)' sql-check-constraints.bats`
  - Result: exit `0`, plan `1..7`, `7` passed, `0` failed, `0` skipped.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `18` Dolt test files, `184` behavior tests, `938` assertions, and `0` failures.
- Boundary unchanged: this maps a bounded `dolt schema show` CHECK-preservation surface. It does not claim full schema command coverage, schema import/export/tag parity, full BATS, or full `go test ./...` parity.

## Runner Refresh 2026-05-23 Check Constraint Information Schema Slice

- Focused upstream source/test inspection:
  - `integration-tests/bats/sql-check-constraints.bats`: the `basic tests for check constraints` case accepts valid rows, rejects `a > 3` and `b > a` violations, allows a `NULL` comparison result, and proves dropped checks stop enforcing while checks on another table remain active.
  - `integration-tests/bats/sql-check-constraints.bats`: the `check constraints survive adding a new column` case confirms `information_schema.CHECK_CONSTRAINTS` exposes `constraint_catalog`, `constraint_name`, and `check_clause` as `def,foo_chk_rvgogafi,(`c1` > 3)`.
  - `integration-tests/bats/sql-create-tables.bats`: the `tables should not reuse constraint names` case confirms copied-table CHECK constraints surface as distinct names in `information_schema.table_constraints` with `CONSTRAINT_TYPE="CHECK"`.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 20m bats --filter 'sql-check-constraints: (basic tests for check constraints|check constraints survive adding a new column)' sql-check-constraints.bats`
  - Result: exit `0`, plan `1..2`, `2` passed, `0` failed, `0` skipped.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'sql-create-tables: tables should not reuse constraint names' sql-create-tables.bats`
  - Result: exit `0`, plan `1..1`, `1` passed, `0` failed, `0` skipped.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `17` Dolt test files, `180` behavior tests, `901` assertions, and `0` failures.
  - Required root `php tools/run-tests.php`: exit `0` with `180` test files, `17,431` assertions, and `0` failures.
- Boundary unchanged: this maps a bounded CHECK validation and `information_schema` metadata surface; it does not claim a general SQL expression engine, SQL server compatibility, full BATS, or full `go test ./...` parity.

## Runner Refresh 2026-05-23 Patch Generated/Default Column Slice

- Focused upstream source inspection:
  - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: `GenerateCreateTableColumnDefinition` passes column defaults, generated expressions, virtual/stored generated state, and `ON UPDATE` expressions through the MySQL schema formatter for both `CREATE TABLE` and `ALTER TABLE ... ADD/MODIFY COLUMN` patch rows.
  - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: non-create/drop schema patch generation only emits `MODIFY COLUMN` when `TypeInfo` changes, so this PHP slice treats default/generated/on-update metadata as part of the focused native column-definition boundary rather than claiming broad upstream schema-diff parity for all column metadata.
  - `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: `PatchTableFunctionScriptTests` remains the bounded upstream patch runner denominator for patch row shape, DDL ordering, and WORKING/STAGED revision boundaries.
- Direct cache-local CLI probes:
  - Created a temporary Dolt repo with `title varchar(40) default 'untitled'`, a virtual generated `slug`, and `updated timestamp default current_timestamp on update current_timestamp`; then modified the working schema to change `title` to `varchar(80) default 'reviewed'`, change `slug` to a stored generated column, and add `status varchar(20) default 'draft'`.
  - `dolt_patch('HEAD','WORKING','t')` emitted:
    - ``ALTER TABLE `t` MODIFY COLUMN `title` varchar(80) DEFAULT 'reviewed';``
    - ``ALTER TABLE `t` MODIFY COLUMN `slug` varchar(120) GENERATED ALWAYS AS ((concat('wp-',t.id))) STORED;``
    - ``ALTER TABLE `t` ADD `status` varchar(20) DEFAULT 'draft';``
  - A create-table probe for `wp_import_queue` confirmed `DEFAULT 'pending'`, ``GENERATED ALWAYS AS ((concat('wp-',`id`))) STORED``, and `DEFAULT 'CURRENT_TIMESTAMP' ON UPDATE CURRENT_TIMESTAMP` formatting in `dolt_patch('HEAD','WORKING','wp_import_queue')`.
  - A data probe with a generated column confirmed upstream data SQL includes the generated column value in an `UPDATE` row when row values change after a generated-expression schema change.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(PatchTableFunction|PatchTableFunctionPrepared)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.596s`.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `16` Dolt test files, `168` behavior tests, `853` assertions, and `0` failures.
  - Required root `php tools/run-tests.php`: exit `0` with `174` test files, `16,538` assertions, and `0` failures after lane-status correction.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Tooling Refresh 2026-05-23 03:08 UTC

- Cache inspection before changing/building:
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`.
  - No delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: all four packages were already installed; `Nothing to do.`
  - `go version && bats --version && expect -v && rpm -q golang golang-bin golang-src bats expect libicu-devel`: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`, `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
- Cache-local build:
  - `mkdir -p tmp bats-home/go/bin bats-tmp && env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv && env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt; `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages with tests passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.048s`, and `merge` passed in `6.260s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 30m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|PatchTableFunction|PatchTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable|DoltUserPrivileges)$' -count=1 -timeout 30m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 15.633s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.248s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/doltdb -run 'Test(ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/doltdb 0.048s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestHistorySystemTable/(can sort by dolt_log.commit|dolt_commit_ancestors table with commit_hash filter ignored for max1row optimization)$|TestDoltScripts/test has_ancestor$' -count=1 -timeout 10m -v`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.422s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats branch.bats sql-branch.bats keyless.bats`
  - Result: exit 0 with plan `1..419`; 394 runnable tests passed and 25 upstream-declared skips remained across diff/schema/rename/primary-key/diff-stat/query-diff/column-tag/sql-diff/merge/schema-conflict/conflict-detection/commit-diff/log/status/sql-status/branch/sql-branch/keyless behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `vb2dcuqqpd8u5u02kpparc87g0g7oqk3` to `uqqpd8u5u02kpparc87g0g7oqk3`, and `dolt reset` reports `branch not found`.
- Direct cache-local `dolt_patch()` generated/default probe:
  - A temporary repo under `.upstream-cache/dolt/tmp/patch-generated-default.ae7kJo` was initialized with `create table t (pk int primary key, c int);`, committed, then altered with `ADD cdef int not null default 42`, `ADD gv int generated always as (c + 1) virtual`, `ADD gs int generated always as (c + 2) stored`, and `MODIFY c bigint default 9`.
  - Probe command: `dolt sql -r csv -q "select statement_order,diff_type,statement from dolt_patch('HEAD','WORKING','t') where diff_type='schema' order by statement_order;"`
  - Result rows: `ALTER TABLE t MODIFY COLUMN c bigint DEFAULT '9';`, `ALTER TABLE t ADD cdef int NOT NULL DEFAULT '42';`, `ALTER TABLE t ADD gv int GENERATED ALWAYS AS (((c + 1)));`, and `ALTER TABLE t ADD gs int GENERATED ALWAYS AS (((c + 2))) STORED;` with upstream quoting around identifiers in the raw CSV output.
- Direct cache-local `dolt_patch()` auto-increment probes:
  - A temporary repo under `.upstream-cache/dolt/tmp/autoinc-create.*` created `wp_posts (ID bigint primary key auto_increment, post_title varchar(255))` and inserted two rows before any commit. `dolt_patch('HEAD','WORKING','wp_posts')` returned a `CREATE TABLE` row with `` `ID` bigint NOT NULL AUTO_INCREMENT`` followed by two explicit `INSERT` rows; `SHOW CREATE TABLE` separately displayed table-level `AUTO_INCREMENT=3`, which `dolt_patch()` did not include.
  - A temporary repo under `.upstream-cache/dolt/tmp/autoinc-alter.*` changed `ID bigint primary key` to `ID bigint auto_increment`; `dolt_patch()` returned only the CSV header, confirming the metadata-only no-row boundary.
  - A temporary repo under `.upstream-cache/dolt/tmp/autoinc-modify.*` changed `ID bigint primary key` to `ID int auto_increment`; `dolt_patch()` returned `ALTER TABLE ... MODIFY COLUMN ... int NOT NULL AUTO_INCREMENT;`, `DROP PRIMARY KEY;`, and `ADD PRIMARY KEY (ID);`.
- Required repository check before this metadata edit:
  - `php tools/run-tests.php`
  - Result: exit 0 with `176` test files, `16,810` assertions, and `0` failures.
- Required repository check after this metadata edit:
  - `php tools/run-tests.php`
  - Result: exit 1 with `177` test files, `16,002` assertions, and `118` failures outside Dolt. The failures are in `lanes/pandoc/tests/MarkdownReaderTest.php` and share the same root cause: `Call to undefined method PortLibs\Pandoc\MarkdownReader::collectSpannedGridTableLines()`.
  - Final captured rerun `php tools/run-tests.php > /tmp/dolt-final-root-php.log 2>&1`: exit 0 with `177` test files, `16,958` assertions, and `0` failures.
- Dolt lane-only verification after the red root check:
  - `php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $r=new TestRunner(); foreach (glob("lanes/dolt/tests/*Test.php") as $f) { $r->runTests(require $f, $f); } fwrite(STDOUT, "\nDolt: " . count(glob("lanes/dolt/tests/*Test.php")) . " test files, " . $r->assertions() . " assertions, " . $r->failures() . " failures\n"); exit($r->failures() === 0 ? 0 : 1);'`
  - Result: exit 0 with `16` Dolt test files, `874` assertions, and `0` failures.
- Current native auto-increment verification:
  - Dolt lane-only PHP: exit 0 with `16` Dolt test files, `176` behavior tests, `890` assertions, and `0` failures.
  - Required root `php tools/run-tests.php`: exit 1 with `178` test files, `17,196` assertions, and `1` failure outside Dolt in `lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` (`rejects malformed supplied document options before benchmark import`: expected `InvalidArgumentException` was not thrown).
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Tooling Refresh 2026-05-23 02:23 UTC

- Cache inspection before changing/building:
  - `git -C .upstream-cache/dolt status --short --branch`: still shows the known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - No delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: all four packages were already installed; `Nothing to do.`
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
  - Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`.
- Cache-local build:
  - `mkdir -p /home/claude/port-libs/.upstream-cache/dolt/tmp /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin /home/claude/port-libs/.upstream-cache/dolt/bats-tmp`
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.043s`, and `merge` passed in `6.287s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 30m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|PatchTableFunction|PatchTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable|DoltUserPrivileges)$' -count=1 -timeout 30m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 15.947s`; this focused batch covers diff/summary/stat/schema/patch/column/system diff, commit diff, log, branch, branch activity, status/conflict dtable, and user-privilege table-function behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.234s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/doltdb -run 'Test(ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/doltdb 0.050s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestHistorySystemTable/(can sort by dolt_log.commit|dolt_commit_ancestors table with commit_hash filter ignored for max1row optimization)$|TestDoltScripts/test has_ancestor$' -count=1 -timeout 10m -v`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.402s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats branch.bats sql-branch.bats keyless.bats`
  - Result: exit 0 with plan `1..419`; 394 runnable tests passed and 25 upstream-declared skips remained. This local slice covered diff/schema/rename/primary-key/diff-stat/query-diff/column-tag/sql-diff/merge/schema-conflict/conflict-detection/commit-diff/log/status/sql-status/branch/sql-branch/keyless behavior, including the `expect`-driven sql-shell system-table test, SQL diff statement rendering, keyless multiset diffs, check-constraint regression coverage, and local branch/log/status behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `ppt35edsir31ccrob4qbo1siovcbcmnr` to `edsir31ccrob4qbo1siovcbcmnr`, and `dolt reset` reports `branch not found`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: exit 0 with plan `1..1`; the runner-local fixed helper passed the exact status reset repro.
- Required repository check after this runner metadata update:
  - `php tools/run-tests.php`
  - Initial streamed result: exit 1 with `174` test files, `16,274` assertions, and `1` failure while the shared worktree was moving.
  - Immediate captured rerun `php tools/run-tests.php > /tmp/dolt-root-php-20260523T0223.log 2>&1`: exit 0 with `174` test files, `16,300` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Refresh 2026-05-23 Patch Modified/Drop Foreign-Key Slice

- Focused upstream source inspection:
  - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: non-create/drop schema patch generation emits secondary-index diffs before foreign-key diffs; modified indexes render `DROP INDEX` then `ADD INDEX`, removed indexes render `DROP INDEX`, modified foreign keys render `DROP FOREIGN KEY` then `ADD CONSTRAINT`, and removed foreign keys render `DROP FOREIGN KEY`.
  - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: `AlterTableAddForeignKeyStmt` renders only child columns, referenced table, and parent columns; referential actions are not included on ALTER ADD patch rows.
  - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: `GenerateCreateTableForeignKeyDefinition` still delegates to the MySQL formatter with `onDelete` / `onUpdate`, so CREATE TABLE foreign-key definitions preserve referential actions.
- Direct cache-local CLI probe:
  - Created a temporary Dolt repo, created `parent` and `child`, committed a base where `child.fk_review` referenced `parent.legacy_id` with `ON DELETE CASCADE`, then changed the working tree so the same index/key name referenced `new_id` with `ON UPDATE CASCADE`.
  - Command boundary: cache-local `dolt_patch('HEAD','WORKING','child')` via `dolt sql -r csv`.
  - Result rows:
    - ``ALTER TABLE `child` DROP INDEX `fk_review`;``
    - ``ALTER TABLE `child` ADD INDEX `fk_review`(`new_id`);``
    - ``ALTER TABLE `child` DROP FOREIGN KEY `fk_review`;``
    - ``ALTER TABLE `child` ADD CONSTRAINT `fk_review` FOREIGN KEY (`new_id`) REFERENCES `parent` (`new_id`);``
  - The direct probe confirms ALTER ADD foreign-key patch rows omit `ON UPDATE CASCADE`; native PHP now keeps that omission while preserving actions in CREATE TABLE definitions.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `16` Dolt test files, `165` behavior tests, `841` assertions, and `0` failures.
  - Required root `php tools/run-tests.php`: exit `0` with `174` test files, `16,300` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Refresh 2026-05-23 Patch Target Row Size Slice

- Focused upstream source inspection:
  - `go/libraries/doltcore/schema/schema.go`: `SchemasAreEqual` compares `GetTargetRowSize()`, so target-row-size-only differences are schema changes.
  - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: non-create/drop schema patch generation emits target-row-size DDL after charset/collation changes as `ALTER TABLE <table> TARGET_ROW_SIZE=<bytes>;`.
  - `.upstream-cache/dolt/.gomodcache/github.com/dolthub/go-mysql-server@v0.20.1-0.20260521203635-622656d89ca9/enginetest/queries/create_table_queries.go`: upstream show-create appends `TARGET_ROW_SIZE=<bytes>` for non-default target-row-size table options.
  - `go/libraries/doltcore/sqle/enginetest/ddl_queries.go` and `go/libraries/doltcore/sqle/tables.go`: the target-row-size upper bound is `65535`, and larger values fail with the upstream `target_row_size <n> exceeds maximum allowed value 65535` error.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltDdlScripts/create_table_with_too_large_target_row_size' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.289s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(PatchTableFunction|PatchTableFunctionPrepared)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.612s`.
- Direct cache-local CLI probe:
  - Created a temporary Dolt repo, ran `create table t (pk int primary key) target_row_size=4096`, then checked `SHOW CREATE TABLE t` and `dolt_patch('HEAD','WORKING','t')`.
  - Result: `SHOW CREATE TABLE` included `TARGET_ROW_SIZE=4096`; the `dolt_patch()` create-table schema row omitted the target-row-size table option, matching `sqle/sqlfmt/schema_fmt.go` create-table generation rather than show-create output.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `16` Dolt test files, `162` behavior tests, `829` assertions, and `0` failures.
  - Required root `php tools/run-tests.php`: exit `0` with `172` test files, `16,079` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Refresh 2026-05-23 Patch Table Collation Slice

- Focused upstream source inspection:
  - `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: the `charset and collation changes` `dolt_patch()` assertions expect `ALTER TABLE \`t\` COLLATE='utf8mb4_0900_bin';` when reverting to the default table collation and `ALTER TABLE \`t\` COLLATE='utf8mb3_general_ci';` when applying a character-set change, with schema rows preceding data rows.
  - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: non-create/drop schema patch generation emits collation DDL after column, primary-key, secondary-index, and foreign-key diffs.
  - `.upstream-cache/dolt/.gomodcache/github.com/dolthub/go-mysql-server@v0.20.1-0.20260521203635-622656d89ca9/sql/parser.go`: `GenerateCreateTableStatement` renders `DEFAULT CHARSET=<charset> COLLATE=<collation>` from the table collation metadata.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(PatchTableFunction|PatchTableFunctionPrepared)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.630s`.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `16` Dolt test files, `159` behavior tests, `818` assertions, and `0` failures.
  - Required root `php tools/run-tests.php` was run in the shared moving worktree and did not pass because of unrelated lane failures. The latest failure-filtered rerun reported `171` test files, `15,937` assertions, and `1` failure in `lanes/esbuild/tests/TypeScriptModuleLowererTest.php` (`lowers wordpress async generator asset queue class cleanup without node`).
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Refresh 2026-05-23 Patch Check Constraint Slice

- Focused upstream source inspection:
  - `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: the `CHECK CONSTRAINTS` `dolt_patch('HEAD', 'WORKING')` assertion expects a `CREATE TABLE` patch for `foo` with ``CONSTRAINT `foo_chk_rvgogafi` CHECK ((`c1` > 3))`` after the primary key clause.
  - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: `GenerateCreateTableStatement` appends check constraints after indexes and foreign keys, and `GenerateCreateTableCheckConstraintClause` delegates to the MySQL schema formatter.
  - `.upstream-cache/dolt/.gomodcache/github.com/dolthub/go-mysql-server@v0.20.1-0.20260521203635-622656d89ca9/sql/parser.go`: MySQL formatter emits `CONSTRAINT <name> CHECK (<expr>)` and appends `/*!80016 NOT ENFORCED */` only for unenforced checks.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(PatchTableFunction|PatchTableFunctionPrepared)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.721s`.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `16` Dolt test files, `156` behavior tests, `807` assertions, and `0` failures.
  - Required root `php tools/run-tests.php`: exit `0` with `171` test files, `15,819` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Tooling Refresh 2026-05-23 01:11-01:43 UTC

- Cache inspection before changing/building:
  - `find .upstream-cache/dolt -maxdepth 3 -mindepth 1 -printf '%y %p\n'`: cache already had hydrated `go`, runner `tmp`, and the existing sparse checkout state; no delete, reset, or wider checkout was run.
  - `git -C .upstream-cache/dolt status --short --branch`: still shows the known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: all four packages were already installed; `Nothing to do.`
  - Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`.
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
- Cache-local build:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.061s`, and `merge` passed in `6.386s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 30m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|PatchTableFunction|PatchTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable|DoltUserPrivileges)$' -count=1 -timeout 30m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 15.755s`; this focused batch covers diff/summary/stat/schema/patch/column/system diff, commit diff, log, branch, branch activity, status/conflict dtable, and user-privilege table-function behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test ./libraries/doltcore/doltdb -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable|ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 10m`
  - Result: `sqle/integration_test` passed in `0.238s`; `doltdb` passed in `0.049s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats branch.bats sql-branch.bats keyless.bats`
  - Result: exit 0 with plan `1..419`; 394 runnable tests passed and 25 upstream-declared skips remained. This local slice covered diff/schema/rename/primary-key/diff-stat/query-diff/column-tag/sql-diff/merge/schema-conflict/conflict-detection/commit-diff/log/status/sql-status/branch/sql-branch/keyless behavior, including primary-key-change diff boundaries, SQL diff statement rendering, keyless multiset diffs, and local branch/log/status behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `lrr00mebqs0pp3cm9cru0g4a1vm4104k` to `mebqs0pp3cm9cru0g4a1vm4104k`, and `dolt reset` reports `branch not found`.
- Required repository check after this runner metadata update:
  - `php tools/run-tests.php`
  - Result: exit 0 with `170` test files, `15,665` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Refresh 2026-05-23 Patch Secondary Index And Foreign Key Slice

- Focused upstream source inspection:
  - `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: the `multi PRIMARY KEY and FOREIGN KEY` assertion expects `dolt_patch('HEAD', 'STAGED')` to emit child `ADD INDEX` before child `ADD CONSTRAINT`, then parent `DROP PRIMARY KEY` and `ADD PRIMARY KEY`, with one warning for skipped parent data SQL.
  - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: non-create/drop schema patch generation emits column diffs, then primary-key replacement, then secondary index diffs, then foreign-key diffs; create-table statements include primary key, non-primary indexes, and foreign keys.
  - `go/libraries/doltcore/sqle/dtablefunctions/dolt_patch.go`: `dolt_patch()` keeps schema rows when `canGetDataDiff` records warning code `1235` and suppresses unsafe data patch rows.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(PatchTableFunction|PatchTableFunctionPrepared)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.609s`.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `16` Dolt test files, `152` behavior tests, `796` assertions, and `0` failures.
  - Required root `php tools/run-tests.php`: exit `0` with `167` test files, `15,434` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Refresh 2026-05-23 Patch Primary-Key Warning Slice

- Focused upstream source inspection:
  - `go/libraries/doltcore/sqle/dtablefunctions/dolt_patch.go`: `canGetDataDiff` returns false when primary-key sets are not diffable, records warning code `mysql.ERNotSupportedYet` (`1235`), and formats `Primary key sets differ between revisions for table '%s', skipping data diff`.
  - `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: the `multi PRIMARY KEY and FOREIGN KEY` patch assertion expects `dolt_patch('HEAD', 'STAGED')` to emit schema rows for the changed `parent` table, skip data rows, and expose warning code `1235`.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(PatchTableFunction|PatchTableFunctionPrepared)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.665s`.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `16` Dolt test files, `149` behavior tests, `780` assertions, and `0` failures.
  - Required root `php tools/run-tests.php`: exit `1` with `164` test files, `15,227` assertions, and `1` failure outside Dolt in `lanes/lightningcss/tests/CssMinifierTest.php`: expected `.foo{scale:2 1}` but actual was `.foo{scale:2}`.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Tooling Refresh 2026-05-23 01:05 UTC

- Cache inspection before changing anything:
  - `ls -la .upstream-cache/dolt`: cache already contained `.git`, `.gocache`, `.gomodcache`, `bats-home`, `bats-tmp`, hydrated `go`, hydrated `integration-tests`, and runner `tmp`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt status --short --branch`: sparse/no-checkout out-of-cone deletions were still present, plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`; no delete, reset, or wider checkout was run.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: all four packages were already installed; `Nothing to do.`
  - Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`.
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
- Cache-local build:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.052s`, and `merge` passed in `6.301s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 30m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|PatchTableFunction|PatchTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable|DoltUserPrivileges)$' -count=1 -timeout 30m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 15.809s`; this focused batch covers diff/summary/stat/schema/patch/column/system diff, commit diff, log, branch, branch activity, status/conflict dtable, and user-privilege table-function behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test ./libraries/doltcore/doltdb -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable|ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 10m`
  - Result: `sqle/integration_test` passed in `0.246s`; `doltdb` passed in `0.050s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats branch.bats sql-branch.bats keyless.bats`
  - Result: exit 0 with plan `1..419`; 394 runnable tests passed and 25 upstream-declared skips remained. This broad local slice covered diff/schema/rename/primary-key/diff-stat/query-diff/column-tag/sql-diff/merge/schema-conflict/conflict-detection/commit-diff/log/status/sql-status/branch/sql-branch/keyless behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `ubrqj7nuo8i3gpk8br9ugo266crubqbi` to `7nuo8i3gpk8br9ugo266crubqbi`, and `dolt reset` reports `branch not found`.
- Required repository check after this runner metadata update:
  - `php tools/run-tests.php`
  - Result: exit 0 with `164` test files, `15,108` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.

## Runner Tooling Refresh 2026-05-23 00:20-00:25 UTC

- Cache inspection before running evidence:
  - `git -C .upstream-cache/dolt status --short --branch`: expected sparse/no-checkout out-of-cone deletions, plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`; no delete, reset, or wider checkout was run.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: `golang`, `bats`, `expect`, and `libicu-devel` were already installed; `Nothing to do.`
  - Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`.
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
- Cache-local build:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, and `merge` passed in `6.062s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 30m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(PatchTableFunction|PatchTableFunctionPrepared|DoltUserPrivileges)$' -count=1 -timeout 30m -v`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.648s`; this covered `dolt_patch()` unprepared/prepared script tests plus upstream `DoltUserPrivTests`, including database-access denial, table-level SELECT, database-level SELECT, revoke paths, dot-range and three-dot access checks, binary hex SQL patch statements, WORKING/STAGED same-ref no-op rows, and `dolt_patch()` table-function privilege boundaries.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 45m bats sql-diff.bats keyless.bats`
  - Result: exit 0 with plan `1..89`; 79 runnable tests passed and 10 upstream-declared skips remained. The passed cases cover SQL diff INSERT/UPDATE/DELETE/primary-key/DDL/rename/view/all-types/escaping/NULL/stat/skinny/ignore behavior plus keyless create/delete/update/import/export/diff/stat/dolt_diff_/merge/duplicate/cardinality/secondary-index/constraint/unique-index behavior. The skips are upstream-declared for Dolt docs SQL diff, keyless column add/drop diff, keyless SQL diff/patch variants, one incorrect-resolve merge case, replace-into unique-index semantics, bulk-import index error handling, and unique-key representation.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `fhkrvqkng499ht7mrgbif1008ot75sfg` to `qkng499ht7mrgbif1008ot75sfg`, and `dolt reset` reports `branch not found`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: exit 0 with plan `1..1`; the runner-local fixed helper passed the same commit-hash reset case.
- Required repository check after this runner metadata update:
  - Native Dolt-only PHP verification: `php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; ... lanes/dolt/tests/*Test.php ...'`
  - Result: `16` Dolt test files, `142` behavior tests, `733` assertions, and `0` failures.
  - `php tools/run-tests.php`
  - Result: exit 0 with 162 test files, 14,732 assertions, and 0 failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, server, or benchmark suites were run.

## Runner Refresh 2026-05-23 Patch Ref Resolution Slice

- Focused upstream source inspection:
  - `go/libraries/doltcore/sqle/dtablefunctions/dolt_patch.go`: `PartitionRows` evaluates literal arguments, calls `loadDetailsForRefs`, then emits patch rows using resolved `fromRefDetails.hashStr` and `toRefDetails.hashStr`.
  - `go/libraries/doltcore/sqle/dtablefunctions/dolt_diff.go`: `loadCommitStrings` splits `A..B` ranges and resolves `A...B` to a merge base; `loadDetailsForRefs` calls `ResolveRootForRef` for branch, tag, hash, ancestor, `HEAD`, `WORKING`, and `STAGED` specs.
- Direct cache-local CLI probe:
  - Built a small temporary Dolt repo under `.upstream-cache/dolt/tmp/patch-rev-probe.*`, tagged the main commit as `tag_main`, branched to `branch1`, edited table `t`, and ran `dolt_patch('tag_main', 'branch1', 't')`.
  - Observed CSV row: `from_commit_hash=qj946k1l60pehco463tj4bstla7jno4m`, `to_commit_hash=cn1m2jd1onp2fo1cup0d9t3slpt7k317`, `table_name=t`, `diff_type=data`, and `UPDATE \`t\` SET \`c1\`='branch' WHERE \`pk\`=1;`.
  - The same probe confirmed upstream-shaped errors: `branch not found: missing-branch` for a missing to-ref and `table not found: missing_table` for a missing requested table.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(PatchTableFunction|PatchTableFunctionPrepared)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.724s`.
- Native PHP verification after this slice:
  - Dolt lane-only PHP: `16` Dolt test files, `144` behavior tests, `744` assertions, and `0` failures.
  - Required root `php tools/run-tests.php`: exit `0` with `162` test files, `14,874` assertions, and `0` failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, server, or benchmark suites were run.

## Runner Refresh 2026-05-23 Binary Patch Slice

- Static denominator refresh:
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '_test\.go$' | wc -l`: `399`.
  - `git -C .upstream-cache/dolt ls-tree -r --name-only HEAD | rg '\.bats$' | wc -l`: `214`.
  - `rg -n '^func (Test|Benchmark|Fuzz)[A-Za-z0-9_]*\(' .upstream-cache/dolt/go -g '*_test.go' | wc -l`: `1,420` inspected Go test/benchmark functions in the hydrated sparse `go` tree.
  - Breakdown: `1,369` `Test*` functions, `51` `Benchmark*` functions, `0` `Fuzz*` functions across `377` checked-out Go test files.
  - `rg -n '^@test ' .upstream-cache/dolt -g '*.bats' | wc -l`: `3,630` BATS cases currently present in the hydrated sparse checkout (`203` BATS files). The manifest keeps the broader static BATS denominator at `214` files and the earlier targeted total at `3,808` cases.
- Focused upstream source inspection:
  - `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: `binary data in patch statements is hex encoded` expects `dolt_patch()` statements such as `0x012345`, fixed `binary(16)` padding as `0x6566675f213400000000000000000000`, and an update predicate `WHERE \`pk\`=0x42`.
  - `go/libraries/doltcore/sqle/sqlfmt/row_fmt.go`: `interfaceValueAsSqlString` emits `0x` hex for `BINARY`, `VARBINARY`, and `VECTOR` values, while text-like values remain quoted.
  - The same patch table-function suite includes same-ref `WORKING`/`STAGED` no-row assertions.
- Focused upstream runner:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(PatchTableFunction|PatchTableFunctionPrepared)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.676s`.
- Native PHP lane-only verification before root run:
  - `php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; ... lanes/dolt/tests/*Test.php ...'`
  - Result: `16` Dolt test files, `140` behavior tests, `721` assertions, `0` failures.
- Required root verification after lane code, notes, manifest, and status updates:
  - `php tools/run-tests.php`
  - Result: exit `0` with `159` test files, `14,515` assertions, and `0` failures.

## Runner Tooling Refresh 2026-05-23 00:00-00:07 UTC

- Cache inspection before running evidence:
  - `git -C .upstream-cache/dolt status --short --branch`: expected sparse/no-checkout out-of-cone deletions, plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`; no delete, reset, or wider checkout was run.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: `golang`, `bats`, `expect`, and `libicu-devel` were already installed; `Nothing to do.`
  - Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`.
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
- Cache-local build:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, and `merge` passed in `6.195s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 25m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|PatchTableFunction|PatchTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared)$' -count=1 -timeout 25m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 5.138s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test ./libraries/doltcore/doltdb -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable|ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 10m`
  - Result: `sqle/integration_test` passed in `0.226s`; `doltdb` passed in `0.045s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 25m bats --filter 'sql-diff: (output reconciles INSERT query|output reconciles UPDATE query|output reconciles DELETE query|output reconciles column rename|reconciles CREATE TABLE|includes row INSERTSs to new tables after CREATE TABLE|reconciles DROP TABLE|reconciles RENAME TABLE|reconciles RENAME TABLE with schema changes|handles NULL cells|skinny flag comparison between CLI and SQL table function|ignored tables in working set are skipped)' sql-diff.bats`
  - Result: exit 0 with plan `1..12`; DML, DDL, rename, NULL, skinny, and ignore SQL diff cases passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats --filter 'keyless: (table replace|diff with in-place updates)' keyless.bats`
  - Result: exit 0 with plan `1..3`; keyless table replace and working-set/branch in-place update diff cases passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats --filter 'keyless: (sql diff as a patch|updates as a sql diff patch)' keyless.bats`
  - Result: exit 0 with plan `1..2`; both upstream cases are declared `skip unimplemented`, so they remain recorded as skipped rather than passing behavior.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `u3iim4u8qdtcr61ic3d9gpcsg4fr5ejb` to `4u8qdtcr61ic3d9gpcsg4fr5ejb`, and `dolt reset` reports `branch not found`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: exit 0 with plan `1..1`; the runner-local fixed helper passed the same commit-hash reset case.
- Required repository check after this runner metadata update:
  - `tmp=.upstream-cache/dolt/tmp/root-php-after-runner-evidence-20260523T0010.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; tail -n 160 "$tmp"; exit $status`
  - Result: exit 0 with 157 test files, 14,270 assertions, and 0 failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, server, or benchmark suites were run.

## Runner Tooling Refresh 2026-05-22 23:26-23:52 UTC

- Cache inspection before running evidence:
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone staged deletions plus runner-local caches and `integration-tests/bats/status-local-fixed.bats`; no delete, reset, or broader hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect`
  - Result: `golang`, `bats`, and `expect` were already installed; `Nothing to do.`
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
  - Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`.
- Cache-local build:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.074s`, and `merge` passed in `9.909s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 25m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltCommit|DoltCommitPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable)$' -count=1 -timeout 25m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 16.749s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test ./libraries/doltcore/doltdb -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable|ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 10m`
  - Result: `sqle/integration_test` passed in `0.284s`; `doltdb` passed in `0.054s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.070s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestHistorySystemTable/(can sort by dolt_log.commit|dolt_commit_ancestors table with commit_hash filter ignored for max1row optimization)$|TestDoltScripts/test has_ancestor$' -count=1 -timeout 10m -v`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.643s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats`
  - Result: exit 0 with plan `1..319`; 303 runnable tests passed and 16 upstream-declared skips remained.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 25m bats branch.bats sql-branch.bats`
  - Result: exit 0 with plan `1..39`; 30 local branch CLI tests and 9 SQL branch procedure/table tests passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `afh8ds3fo57l3ruubmnhrtrpnmuqn3m7` to `s3fo57l3ruubmnhrtrpnmuqn3m7`, and `dolt reset` reports `branch not found`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: exit 0 with plan `1..1`; the runner-local fixed helper passed the same commit-hash reset case.
- Direct cache-local `dolt diff --stat` keyless probes:
  - In `.upstream-cache/dolt/tmp/keyless-stat-runner-20260522T2351.kQW6m3`, insert-only text printed `1 Row Added` and `0 Rows Deleted`; JSON emitted `rows_added:1`, `rows_deleted:0`, `cells_added:3`, and `cells_deleted:3`. A delete-one/insert-two churn printed `2 Rows Added` and `1 Row Deleted`; JSON emitted `rows_added:2`, `rows_deleted:1`, `rows_unmodified:18446744073709551615`, `cells_added:6`, and `cells_deleted:6`. A delete-only churn printed `0 Rows Added` and `1 Row Deleted`; JSON emitted the same unsigned `rows_unmodified` underflow with `cells_added:3` and `cells_deleted:3`.
  - In `.upstream-cache/dolt/tmp/keyless-stat-replace-plus-two-20260522T2352.zQMsFG`, the exact replace-plus-two keyless probe printed `3 Rows Added` and `1 Row Deleted`; JSON emitted `rows_added:3`, `rows_deleted:1`, `rows_unmodified:18446744073709551615`, `cells_added:9`, and `cells_deleted:9`.
- Required repository check before this metadata edit:
  - `tmp=.upstream-cache/dolt/tmp/root-php-before-dolt-runner-metadata-20260522T2353.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; tail -n 160 "$tmp"; exit $status`
  - Result: exit 1 with 152 test files, 13,837 assertions, and 2 failures outside Dolt in `lanes/readability/tests/ArticleExtractorTest.php`; Dolt tests reached by the root run passed.
- Required repository check after this runner metadata update:
  - `tmp=.upstream-cache/dolt/tmp/root-php-after-dolt-runner-metadata-20260523T0000.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; tail -n 160 "$tmp"; exit $status`
  - Result: exit 0 with 153 test files, 13,923 assertions, and 0 failures.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, or benchmark suites were run.

## Runner Tooling Refresh 2026-05-22 23:04-23:18 UTC

- Cache inspection before running evidence:
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone staged deletions plus runner-local caches and `integration-tests/bats/status-local-fixed.bats`; no delete, reset, or broader hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect`
  - Result: `golang`, `bats`, and `expect` were already installed; `Nothing to do.`
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
  - Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`.
- Cache-local build:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.052s`, and `merge` passed in `7.373s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 25m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltCommit|DoltCommitPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable)$' -count=1 -timeout 25m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 18.667s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test ./libraries/doltcore/doltdb -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable|ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 10m`
  - Result: `sqle/integration_test` passed in `0.372s`; `doltdb` passed in `0.059s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.202s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestHistorySystemTable/(can sort by dolt_log.commit|dolt_commit_ancestors table with commit_hash filter ignored for max1row optimization)$|TestDoltScripts/test has_ancestor$' -count=1 -timeout 10m -v`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.602s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats`
  - Result: exit 0 with plan `1..319`; 303 runnable tests passed and 16 upstream-declared skips remained.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 25m bats branch.bats sql-branch.bats`
  - Result: exit 0 with plan `1..39`; 30 local branch CLI tests and 9 SQL branch procedure/table tests passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'diff-stat: stat/summary gets summaries for all tables with changes' diff-stat.bats`
  - Result: exit 0 with plan `1..1`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `33bdvqilukv57k4fdld77cilsgg1ds3d` to `qilukv57k4fdld77cilsgg1ds3d`, and `dolt reset` reports `branch not found`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: exit 0 with plan `1..1`; the runner-local fixed helper passed the same commit-hash reset case.
- Direct cache-local `dolt diff --stat [tables...]` probes:
  - A first probe command successfully built `.upstream-cache/dolt/tmp/stat-table-boundary-20260522T231710` but the shell wrapper exited 2 before running `dolt diff --stat` because `printf '--- stat all ---\n'` was parsed as an option; the same temp repo was reused for the actual Dolt probes.
  - In `.upstream-cache/dolt/tmp/stat-table-boundary-20260522T231710`, `dolt diff --stat` printed both changed tables `aaa` and `zzz`; `dolt diff --stat zzz` skipped earlier changed table `aaa` and printed `zzz`; `dolt diff --stat aaa` printed only `aaa`; `dolt diff --stat missing` exited 1 with `table missing does not exist in either revision`.
  - In `.upstream-cache/dolt/tmp/stat-table-unchanged-20260522T231738`, `dolt diff --stat bbb` for an unchanged existing table exited 0 with empty output, and `dolt diff --stat aaa zzz` printed both requested changed tables.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, or benchmark suites were run.
- Required repository check after this runner metadata update:
  - `tmp=.upstream-cache/dolt/tmp/root-php-after-dolt-runner-20260522T2320.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; tail -n 140 "$tmp"; exit $status`
  - Result: exit 0 with 148 test files, 13,303 assertions, and 0 failures.

## PHP Summary Table-Argument Slice Refresh 2026-05-22

- Targeted source inspection covered `go/cmd/dolt/commands/diff.go` `printDiffSummary`, which writes matching summary rows but returns immediately at the first changed table whose from/to names are outside the CLI table-argument set.
- Related upstream BATS coverage remains `integration-tests/bats/diff.bats` `diff: table-only option` and `integration-tests/bats/diff-stat.bats` `diff-stat: stat/summary gets summaries for all tables with changes`; those establish the fixed-width `--summary` frame and a table-specific summary case when the requested table is encountered first.
- Direct cache-local probe built a temporary Dolt repository under `.upstream-cache/dolt/tmp/summary-table-boundary.*`, changed two tables (`aaa` and `zzz`), and ran:
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --summary`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --summary zzz`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --summary aaa`
- Probe result: the unscoped summary printed both `aaa` and `zzz`; `dolt diff --summary zzz` exited successfully with empty output because `aaa` was the first summary row and was outside the requested table set; `dolt diff --summary aaa` printed only `aaa` and stopped before `zzz`.
- Native PHP lane-only verification after this slice:
  - `php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; ... lanes/dolt/tests/*Test.php ...'`
  - Result: 13 Dolt test files, 115 behavior tests, 602 assertions, 0 failures.
- Required root verification after this slice:
  - `php tools/run-tests.php`
  - Result: latest captured rerun exited 0 with 143 test files, 12,874 assertions, and 0 failures. The immediately preceding root run failed once outside Dolt in `lanes/readability/tests/ArticleExtractorTest.php` while the shared tree was moving.

## PHP Diff-Stat Table-Argument Slice Refresh 2026-05-22

- Targeted source inspection covered `go/cmd/dolt/commands/diff.go` `diffUserTables` / `diffUserTable` and `go/cmd/dolt/commands/diff_output.go` `WriteTableDiffStats`. Unlike `printDiffSummary`, stat output filters table deltas with `continue`, so a later requested changed table is still rendered.
- Existing bounded upstream BATS coverage includes `integration-tests/bats/diff-stat.bats` `diff-stat: stat/summary gets summaries for all tables with changes`, including `dolt diff --stat employees`.
- Direct cache-local probe built a temporary Dolt repository under `.upstream-cache/dolt/tmp/stat-table-boundary.*`, changed `aaa` and `zzz`, and ran:
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --stat`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --stat zzz`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --stat same` after `same` had no delta
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --stat same` after a schema-only column add with no rows
- Probe result: unscoped stat printed `aaa` and `zzz`; `--stat zzz` skipped the earlier changed `aaa` and printed the later `zzz`; unchanged requested `same` exited successfully with empty output; schema-only `same` printed `No data changes. See schema changes by using -s or --schema.`
- Native PHP lane-only verification after this slice:
  - `php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; ... lanes/dolt/tests/*Test.php ...'`
  - Result: 14 Dolt test files, 119 behavior tests, 616 assertions, 0 failures.
- Required root verification after this slice:
  - `php tools/run-tests.php`
  - Result: exit 1 with 146 test files, 12,985 assertions, and 7 failures outside Dolt in `lanes/lightningcss/tests/TransitionPrefixerTest.php`; all failures were `@supports (` spacing mismatches such as expected `@supports(color:lab(0% 0 0))` versus actual `@supports (color:lab(0% 0 0))`.

## PHP Diff-Stat JSON Slice Refresh 2026-05-22

- Targeted source inspection covered `go/cmd/dolt/commands/diff.go` `parseDiffDisplaySettings` / `diffUserTable` and `go/cmd/dolt/commands/diff_output.go` `jsonDiffWriter.WriteTableDiffStats`. Upstream accepts `-r json` / `--result-format=json`; a probe confirmed `--format=json` is rejected as an unknown option.
- Direct cache-local probe built temporary Dolt repositories under `.upstream-cache/dolt/tmp/stat-json-probe.*` and `.upstream-cache/dolt/tmp/stat-json-empty-schema.*`, changed `aaa`, `zzz`, and `same`, and ran:
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --stat -r json`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --stat -r json zzz`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --stat -r json same` before and after schema-only changes
- Probe result: unscoped JSON emitted `{"tables":[...]}` for `aaa` and `zzz`; table-specific `zzz` scanned past `aaa` and emitted one table object; unchanged requested `same` exited successfully with empty output; schema-only no-row `empty_schema` emitted `{"tables":[{"name":"empty_schema","stats":{}}]}`; schema-only with one existing row emitted a stats object with `cells_added:1`.
- Native PHP lane-only verification after this slice:
  - `php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; ... lanes/dolt/tests/*Test.php ...'`
  - Result: 14 Dolt test files, 120 behavior tests, 623 assertions, 0 failures.
- Required root verification after this slice:
  - `php tools/run-tests.php`
  - Earlier captured result during this slice: exit 0 with 148 test files, 13,200 assertions, and 0 failures.
  - Latest exact required rerun after lane notes/status edits: exit 1 with 148 test files, 13,259 assertions, and 1 failure outside Dolt in `lanes/libsqlite/tests/SQLiteHeaderTest.php`; the failing test was `uses json_extract expression index for wordpress plugin settings reverse array paths` with message `SQLite wp_options json_extract(option_value) expression index is not present`. Dolt tests reached by the root run passed.

## PHP Diff-Stat Keyless CLI/JSON Slice Refresh 2026-05-22

- Targeted source inspection covered `go/cmd/dolt/commands/diff_output.go` `tabularDiffWriter.printKeylessStat` and `jsonDiffWriter.WriteTableDiffStats`, `go/libraries/doltcore/diff/diff_stat.go` `reportKeylessChanges`, `go/libraries/doltcore/sqle/dtablefunctions/dolt_diff_stat.go` `getRowFromDiffStat`, and `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go` `basic case with single keyless table`.
- Direct cache-local probe built a temporary Dolt repository under `.upstream-cache/dolt/tmp/keyless-stat.*`, created a keyless table, and ran:
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --stat keyless`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --stat -r json keyless`
- Probe result: insert-only text printed `1 Row Added` and `0 Rows Deleted`; update-plus-two text printed `3 Rows Added` and `1 Row Deleted`; delete-only text printed `0 Rows Added` and `1 Row Deleted`. JSON still emitted row/cell fields: insert-only returned `cells_added:3` and `cells_deleted:3`; update-plus-two returned `rows_added:3`, `rows_deleted:1`, `rows_unmodified:18446744073709551615`, `cells_added:9`, and `cells_deleted:9`; delete-only returned the same unsigned underflow for `rows_unmodified`.
- Native PHP lane-only verification after this slice:
  - `php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; ... lanes/dolt/tests/*Test.php ...'`
  - Result: 14 Dolt test files, 121 behavior tests, 630 assertions, 0 failures.
- Required root verification after this slice:
  - `php tools/run-tests.php`
  - Result: exit 0 with 148 test files, 13,358 assertions, and 0 failures.

## PHP Keyless Row Diff SQL/Tabular Slice Refresh 2026-05-22

- Targeted source inspection covered `go/libraries/doltcore/sqle/dtables/diff_iter.go` keyless `getDiffRowAndCardinality`, `go/libraries/doltcore/diff/diff_stat.go` `reportKeylessChanges`, `go/libraries/doltcore/diff/diffsplitter.go`, `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go` `GenerateDataDiffStatement`, `go/libraries/doltcore/sqle/sqlfmt/row_fmt.go` `SqlRowAsDeleteStmt`, `go/libraries/doltcore/table/untyped/sqlexport/sql_diff_writer.go`, and `go/libraries/doltcore/table/untyped/tabular/fixedwidth_diff_tablewriter.go`.
- Direct cache-local probe built a temporary Dolt repository under `.upstream-cache/dolt/tmp/keyless-row-diff.nTM3Wa`, created keyless `wp_import_log`, inserted duplicate rows, committed, then deleted one duplicate and inserted one duplicate plus one new row. Commands run:
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff -r sql wp_import_log`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff -r sql --filter=added wp_import_log`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff -r sql --filter=removed wp_import_log`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff wp_import_log`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --filter=added wp_import_log`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff --filter=removed wp_import_log`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt sql -r csv -q "select to_event_type,to_message,from_event_type,from_message,diff_type from dolt_diff('HEAD','WORKING','wp_import_log');"`
- Probe result: keyless row output emitted no `modified` rows. Cardinality increases were repeated `added` rows, the duplicate decrease was one `removed` row, SQL deletes used every table column in the `WHERE` predicate, and tabular output used only `+` / `-` markers. Observed SQL included `INSERT INTO ... ('post','queued')`, `DELETE FROM ... WHERE event_type='scan' AND message='started'`, and `INSERT INTO ... ('media','done')`; observed tabular rows were `| + | post | queued |`, `| - | scan | started |`, and `| + | media | done |`.
- Native PHP lane-only verification after this slice:
  - `php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; ... lanes/dolt/tests/*Test.php ...'`
  - Result: 14 Dolt test files, 126 behavior tests, 660 assertions, 0 failures.
- Required root verification after this slice:
  - `tmp=.upstream-cache/dolt/tmp/root-php-keyless-row-20260522T233925.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; tail -n 100 "$tmp"; exit $status`
  - Initial result: exit 0 with 149 test files, 13,615 assertions, and 0 failures.
  - A post-status exact rerun first failed outside Dolt with 149 test files, 13,616 assertions, and 3 failures in dirty `lanes/esbuild/tests/TypeScriptModuleLowererTest.php` and `lanes/quadrable/tests/QuadbStoreTest.php`; focused reruns of those two dirty-lane test files immediately passed with 687 assertions and 0 failures.
  - Final exact rerun: `tmp=.upstream-cache/dolt/tmp/root-php-keyless-row-final-rerun-20260522T234141.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; tail -n 100 "$tmp"; exit $status`
  - Final result: exit 0 with 150 test files, 13,659 assertions, and 0 failures.

## PHP Diff-Mode Slice Refresh 2026-05-22

- Targeted upstream evidence reused the hydrated `integration-tests/bats/diff.bats` case `diff: row, line, in-place, context diff modes`, which had already passed in the bounded BATS runner.
- Direct cache-local probe built a temporary Dolt repository under `.upstream-cache/dolt/tmp/php-diffmode.*`, changed multiline and single-line procedure definitions, and ran:
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff original --diff-mode=row procedures`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff original --diff-mode=line procedures`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff original --diff-mode=in-place procedures`
  - `/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt diff original --diff-mode=context procedures`
- Probe result: `row` emitted `<` and `>` modified rows, `line` emitted combined `*` rows with ` ` / `-` / `+` cell lines, `in-place` emitted uncolored side-by-side text merges such as `SELECT a2` and `SELECT 423`, and `context` used a line diff for the multiline procedure while leaving the single-line procedure as `<` / `>` rows.
- Native PHP lane-only verification after this slice:
  - `php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; ... lanes/dolt/tests/*Test.php ...'`
  - Result: 13 Dolt test files, 113 behavior tests, 594 assertions, 0 failures.
- Required root verification after this slice:
  - `php tools/run-tests.php`
  - Initial result: exit 1 with 141 test files, 12,572 assertions, and 2 failures in `lanes/readability/tests/ArticleExtractorTest.php`; Dolt tests reached by the root run passed.
  - Final captured rerun after the moving tree settled: exit 0 with 142 test files, 12,668 assertions, and 0 failures.

## Runner Tooling Refresh 2026-05-22 22:22-22:43 UTC

- Cache inspection before running evidence:
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone staged deletions plus runner-local caches and `integration-tests/bats/status-local-fixed.bats`; no delete, reset, or broader hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect`
  - Result: packages were already installed; `Nothing to do.`
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
  - Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`.
- Cache-local build:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.048s`, and `merge` passed in `7.372s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 25m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltCommit|DoltCommitPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable)$' -count=1 -timeout 25m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 15.411s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test ./libraries/doltcore/doltdb ./libraries/doltcore/sqle/dtablefunctions -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable|ParseInstructions|SplitAncestorSpec|DoltLog)$' -count=1 -timeout 10m`
  - Result: `sqle/integration_test` passed in `0.230s`, `doltdb` passed in `0.047s`, and dtablefunctions reported no tests for that combined regex.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.037s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestHistorySystemTable/(can sort by dolt_log.commit|dolt_commit_ancestors table with commit_hash filter ignored for max1row optimization)$|TestDoltScripts/test has_ancestor$' -count=1 -timeout 10m -v`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.412s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats`
  - Result: exit 0 with plan `1..319`; 303 runnable tests passed and 16 upstream-declared skips remained.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 25m bats branch.bats sql-branch.bats`
  - Result: exit 0 with plan `1..39`; 30 local branch CLI tests and 9 SQL branch procedure/table tests passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `jvktivfmfv2qj7pi971f8rsdumq3qr0o` to `vfmfv2qj7pi971f8rsdumq3qr0o`, and `dolt reset` reports `branch not found`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: exit 0 with plan `1..1`; the runner-local fixed helper passed the same commit-hash reset case.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, or benchmark suites were run.
- Required repository check after this runner metadata update:
  - `tmp=.upstream-cache/dolt/tmp/root-php-after-runner-20260522T2243.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; tail -n 120 "$tmp"; exit $status`
  - Initial result: exit 1 with 143 test files, 12,767 assertions, and 1 failure in `lanes/pandoc/tests/MarkdownReaderTest.php` test `writes wordpress imported fancy ordered lists with nested starts`; Dolt tests reached by the root run passed.
  - `tmp=.upstream-cache/dolt/tmp/root-php-after-runner-rerun-20260522T2243.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; tail -n 120 "$tmp"; exit $status`
  - Immediate rerun result: exit 0 with 143 test files, 12,794 assertions, and 0 failures.

## Runner Tooling Refresh 2026-05-22 22:12 UTC

- Cache inspection before running evidence:
  - `.upstream-cache/dolt` remained at `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch` still showed the known sparse/no-checkout out-of-cone staged deletions plus runner-local caches; no delete, reset, or broader hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, and `libicu-devel-77.1-2.fc44.x86_64`.
  - Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`.
- Cache-local build:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, and `merge` passed in `6.781s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 25m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity)$' -count=1 -timeout 25m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 15.467s`.
  - Focused follow-up Go slices also passed: schema/procedure/history integration tests in `0.238s`, ancestor spec tests in `0.042s`, and status/conflict dtable scripts in `0.393s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 45m bats diff-stat.bats query-diff.bats log.bats status-local-fixed.bats sql-status.bats branch.bats sql-branch.bats`
  - Result: exit 0 with plan `1..118`; 117 runnable tests passed and 1 upstream-declared skip remained.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats`
  - Result: exit 0 with plan `1..319`; 303 runnable tests passed and 16 upstream-declared skips remained. This broad local slice covered diff/schema/rename/primary-key/diff-stat/query-diff/column-tag/sql-diff/merge/schema-conflict/conflict-detection/commit-diff/log/status/sql-status behavior without live service suites.
- Fresh negative-control blocker:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `fpcffikekf0l3kdilo3ghvdnooo3qp6t` to `ikekf0l3kdilo3ghvdnooo3qp6t` through fixed-width `cut -c 13-44`, and `dolt reset` reports `branch not found`.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, or benchmark suites were run.
- Required repository check after this runner metadata update:
  - `php tools/run-tests.php`
  - Initial streamed result: exit 1 with 138 test files, 12,225 assertions, and 1 failure; the failure detail was not visible in the streamed/truncated output.
  - Immediate captured rerun command: `tmp=.upstream-cache/dolt/tmp/root-php-20260522T2212.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; tail -n 140 "$tmp"; exit $status`
  - Result: exit 0 with 138 test files, 12,242 assertions, and 0 failures.
  - Final post-lane-status edit command: `tmp=.upstream-cache/dolt/tmp/root-php-final-20260522T2212.log; php tools/run-tests.php > "$tmp" 2>&1; status=$?; tail -n 80 "$tmp"; exit $status`
  - Result: exit 0 with 139 test files, 12,328 assertions, and 0 failures.

## Runner Tooling Refresh 2026-05-22 21:38 UTC

- Cache inspection before running evidence:
  - `.upstream-cache/dolt` remained at `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch` still showed the known sparse/no-checkout out-of-cone staged deletions plus runner-local caches; no delete, reset, or broader hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
  - Tool probes remained `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, and `expect version 5.45.4`; `libicu-devel-77.1-2.fc44.x86_64` was present.
- Cache-local build:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, and `merge` passed in `6.166s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 25m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity)$' -count=1 -timeout 25m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 15.024s`.
  - Focused follow-up Go slices also passed: schema/procedure/history integration tests in `0.297s`, ancestor spec tests in `0.054s`, `TestDoltLog` in `0.087s`, status/conflict dtable scripts in `0.505s`, and `dolt_status_ignored` / `has_ancestor` scripts in `0.473s`.
- Bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 45m bats diff-stat.bats query-diff.bats log.bats status-local-fixed.bats sql-status.bats branch.bats sql-branch.bats`
  - Result: exit 0 with plan `1..118`; 117 runnable tests passed and 1 upstream-declared skip remained. This covered diff stat/summary, query-diff, `log -n`, table-filtered log, graph/decorate/stat/all-branch log cases, the runner-local fixed status helper, SQL status rows, and branch/sql-branch cases.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats --filter 'log: (with -n specified|Log on a table works with -n)' log.bats`
  - Result: exit 0 with plan `1..2`; the upstream CLI log limit cases passed.
  - Direct cache-local `dolt log` limit probe in a temporary repository under `.upstream-cache/dolt/tmp/log-limit.NuH5sa`: `dolt log -n 0` exited 0 with 0 output bytes, `dolt log --number=1` included the latest `second` commit and excluded `first` / `Initialize data repository`, and `dolt log -n -1` exited 1 with `fatal: invalid --number argument: -1`.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'diff: table-only option' diff.bats`
  - Result: exit 0 with plan `1..1`; the focused upstream `dolt diff --summary` fixed-width table and `--name-only --summary` invalid-argument boundary passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 15m bats --filter 'diff: (--filter with invalid value returns error|--filter=renamed filters to only renamed tables|--filter=dropped filters to only dropped tables)' diff.bats`
  - Result: exit 0 with plan `1..3`; the focused upstream invalid-filter, renamed-only, dropped-only, and `removed` alias boundaries passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats --filter 'diff: --filter option filters by diff type' diff.bats`
  - Result: exit 0 with plan `1..1`; the focused upstream row-level `-r sql` diff-filter case passed, including INSERT output for `--filter=added`, UPDATE output for `--filter=modified`, DELETE output for `--filter=removed`, and empty output for mismatched row filters.
  - Direct cache-local row-mode tabular probe in a temporary repository under `.upstream-cache/dolt/tmp/tabular-filter-*`: `dolt diff HEAD~1 --filter=added` emitted `+` rows for inserted rows, `--filter=modified` emitted `<` and `>` rows for updated rows, `--filter=removed` emitted `-` rows for deleted rows, `--filter=dropped` matched the removed-row output, and mismatched row filters emitted empty output with no table frame.
- Fresh negative-control blocker:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates `56ljsj9n6s80tjs36dpecbok93q8431p` to `j9n6s80tjs36dpecbok93q8431p` through fixed-width `cut -c 13-44`, and `dolt reset` reports `branch not found`.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, or benchmark suites were run.
- Required repository check after this runner metadata update:
  - `php tools/run-tests.php`
  - Result: pass with 131 test files, 11,742 assertions, and 0 failures.

## Current Runner Evidence Refresh

- Time: 2026-05-22 21:24 UTC.
- Cache inspection before running evidence:
  - `.upstream-cache/dolt` remained at `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch` still showed the known sparse/no-checkout out-of-cone staged deletions plus local Go/BATS build caches; no delete, reset, or broader hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
  - Tool probes: `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, `expect version 5.45.4`.
- Cache-local build:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit 0; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.
- Fresh bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, and `merge` passed in `6.904s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 25m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity)$' -count=1 -timeout 25m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 15.391s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/sqle/integration_test -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable)$' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.272s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/doltdb -run 'Test(ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/doltdb 0.056s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 8m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable)$' -count=1 -timeout 8m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.437s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 8m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltScripts/(dolt_status_ignored|test has_ancestor)$' -count=1 -timeout 8m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.404s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.057s`.
- Fresh bounded BATS evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats`
  - Result: exit 0 with plan `1..319`; 303 runnable tests passed and 16 upstream-declared skips remained.
  - This included the `expect`-dependent `diff: --system preserves dolt_show_system_tables value in sql-shell`, the log rendering cases for `--decorate=auto`, `--stat`, `--graph`, and `--all`, plus the patched-copy status reset helper case.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 25m bats branch.bats sql-branch.bats`
  - Result: exit 0 with plan `1..39`; 30 local branch CLI tests and 9 SQL branch procedure/table tests passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats --filter 'log: (with -n specified|Log on a table works with -n)' log.bats`
  - Result: exit 0 with plan `1..2`; the focused upstream `-n` log-limit cases passed.
  - Direct cache-local probe recreated a one-table repository and confirmed `dolt log -n 0` exits 0 with zero output lines, `dolt log --number=1` includes the latest commit but not `Initialize data repository`, and `dolt log -n -1` exits 1 with `fatal: invalid --number argument: -1`.
- Fresh negative-control blocker:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine `status.bats` still truncates commit hashes with `cut -c 13-44`, turning `hg00qu4ih64urob79vp4t34j3a731gd5` into `u4ih64urob79vp4t34j3a731gd5`, and `dolt reset` reports `branch not found`.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, or benchmark suites were run.

## Tooling Installed

- `sudo -n dnf install -y golang bats`
- `sudo -n dnf install -y expect`
- Fresh prerequisite check this run:
  - `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
- Installed/probed package versions:
  - `golang-1.26.3-2.fc44.x86_64`
  - `golang-bin-1.26.3-2.fc44.x86_64`
  - `golang-src-1.26.3-2.fc44.noarch`
  - `bats-1.13.0-3.fc44.noarch`
  - `expect-5.45.4-31.fc44.x86_64`
  - `libicu-devel-77.1-2.fc44.x86_64` present for Dolt's ICU regex dependency
- Tool probes:
  - `go version`: `go version go1.26.3-X:nodwarf5 linux/amd64`
  - `bats --version`: `Bats 1.13.0`
  - `expect -version`: `expect version 5.45.4`

## Checkout And Build State

- Current cache inspection before this runner extension found `.upstream-cache/dolt` as a shallow partial clone at the expected commit with `remote.origin.promisor=true`, `remote.origin.partialclonefilter=blob:none`, and hydrated sparse checkout entries `go` and `integration-tests/bats`.
- The internal upstream-cache status still reports staged deletions for out-of-cone paths from the earlier no-checkout/sparse hydration, plus untracked build caches. `git -C .upstream-cache/dolt sparse-checkout reapply` was attempted and did not clear that index state; no delete or reset was run.
- Earlier hydration command recorded for this runner cache:
  - `git -C .upstream-cache/dolt sparse-checkout init --cone`
  - `git -C .upstream-cache/dolt sparse-checkout set go integration-tests/bats`
  - `git -C .upstream-cache/dolt checkout HEAD -- go integration-tests/bats`
- Sparse checkout list after hydration: `go`, `integration-tests/bats`.
- Build/cache env kept artifacts inside `.upstream-cache/dolt`:
  - `GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache`
  - `GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache`
  - `HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home`
  - `GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin`
- Utility build command:
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
- Built binaries: `dolt`, `noms`, `remotesrv`.
- `HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`: `dolt version 2.0.5`.

## Fresh Runner Refresh

- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv && env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: cache-local utilities rebuilt; `dolt version 2.0.5`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json -count=1 -timeout 10m`
  - Result: all 10 packages passed; observed package timings included `diff 0.048s`, `schema 0.236s`, `schema/encoding 0.548s`, and all table packages under `0.12s`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions -count=1 -timeout 5m`
  - Result: `dtables` compiled with `[no test files]`; `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.055s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 3.448s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run 'Test(DiffStatTableFunction|DiffSummaryTableFunction)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.620s`; fresh rerun for the native summary/stat slice.
- Latest primary-key warning refresh of the same focused `DiffStatTableFunction` / `DiffSummaryTableFunction` group passed in `0.641s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run TestDiffSummaryTableFunction -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.674s`; fresh rerun for the native `dolt_ignore` summary-filter slice.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats diff.bats rename-tables.bats primary-key-changes.bats`
  - Result: `1..108`, exit 0; all 107 runnable tests passed and 1 upstream-declared skip remained for `rename-tables: merge a renamed table`.

## Expanded Local Runner Refresh

- `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv && env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: cache-local `dolt`, `noms`, and `remotesrv` rebuilt; `dolt version 2.0.5`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval -count=1 -timeout 10m`
  - Result: diff, schema, typecompatibility, schema/encoding, table, table/untyped, table/untyped/csv, table/untyped/tabular, table/untyped/sqlexport, table/typed/json, sqle/sqlfmt, and sqle/expreval passed; rowconv compiled with `[no test files]`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 3.332s`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions -count=1 -timeout 5m`
  - Result: `dtables` compiled with `[no test files]`; `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.047s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 45m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats`
  - Result: `1..179`, exit 0; 177 runnable tests passed and 2 upstream-declared skips remained for `rename-tables: merge a renamed table` and `sql-diff: sql diff ignores dolt docs`.

## Merge And Status Runner Extension

- Cache inspection before extending this runner again:
  - `.upstream-cache/dolt` remained at `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - Internal upstream-cache status still showed staged deletions for out-of-cone paths from the sparse/no-checkout state and untracked build caches; no delete or reset was run.
- `sudo -n dnf install -y golang bats expect`
  - Result: all three packages were already installed; `Nothing to do.`
- Tool probes:
  - `go version`: `go version go1.26.3-X:nodwarf5 linux/amd64`
  - `bats --version`: `Bats 1.13.0`
  - `expect -version`: `expect version 5.45.4`
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/merge -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/merge 6.268s`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/integration_test -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresDiffTable|HistoryTable)$' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.189s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: `dolt version 2.0.5`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 60m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats status.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats`
  - Result: exit 1 with plan `1..277`; 276 tests reached without non-skip failures, but `status: dolt reset works with commit hash ref` failed because `status.bats` uses `dolt log -n 1 | grep -m 1 commit | cut -c 13-44`, which truncated current `dolt log` commit hashes and passed a suffix to `dolt reset`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 30m bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats`
  - Result: `1..74`, exit 0; 64 runnable tests passed and 10 upstream-declared skips remained across merge hangs/key-collision/check-constraint/schema-conflict TODOs and documented conflict-detection TODOs.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: (tables in conflict|renamed table|dolt reset with a renamed table)' status.bats`
  - Result: `1..3`, exit 0; all three focused status tests passed.
- Native status-table source inventory for this slice inspected `go/libraries/doltcore/sqle/dtables/status_table.go`, `status_ignored_table.go`, `merge_status_table.go`, `table_of_tables_in_conflict.go`, `integration-tests/bats/sql-status.bats`, and `integration-tests/bats/status.bats`.

## Combined Local Runner Refresh

- Cache inspection before this runner refresh:
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch` still reports the known staged out-of-cone deletions from sparse/no-checkout hydration and untracked build caches. No delete, reset, or broader hydration was run.
- `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
- `rpm -q golang golang-bin golang-src bats expect libicu-devel`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, and `libicu-devel-77.1-2.fc44.x86_64`.
- `go version`; `bats --version`; `expect -version`
  - Result: `go version go1.26.3-X:nodwarf5 linux/amd64`; `Bats 1.13.0`; `expect version 5.45.4`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: `dolt version 2.0.5`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 15m`
  - Result: diff, schema, typecompatibility, schema/encoding, table, table/untyped, table/untyped/csv, table/untyped/tabular, table/untyped/sqlexport, table/typed/json, sqle/sqlfmt, sqle/expreval, sqle/dtablefunctions, and merge passed; rowconv and sqle/dtables compiled with `[no test files]`; merge completed in `5.999s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 3.825s`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/integration_test -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresDiffTable|HistoryTable)$' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.220s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/integration_test -run 'TestDoltSchemas(History|Diff)Table$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.138s`; fresh rerun for the native schema-history/schema-diff slice.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/integration_test -run 'TestDoltProcedures(History|Diff)Table$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.152s`; fresh rerun for the native procedure-history/procedure-diff slice.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 75m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats`
  - Result: `1..253`, exit 0; 238 runnable tests passed and 15 upstream-declared skips remained.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 15m bats sql-commit-diff.bats`
  - Result: `1..2`, exit 0; both standalone `DOLT_COMMIT_DIFF` tests passed for `to_*` and `from_*` primary-key range predicates.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: (tables in conflict|renamed table|dolt reset with a renamed table)' status.bats`
  - Result: `1..3`, exit 0; all three focused status tests passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: `1..1`, exit 1; reproduced the stale upstream helper boundary. `get_head_commit()` uses `dolt log -n 1 | grep -m 1 commit | cut -c 13-44`, which truncated the current commit hash and caused `dolt reset` to report `branch not found`.

## Passed Upstream Commands

- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache go test ./libraries/doltcore/diff -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/diff 0.039s`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache go test ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json -count=1 -timeout 5m`
  - Result: all 6 packages passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache go test ./libraries/doltcore/sqle/enginetest -run 'TestDiffTableFunction/dolt_diff: SELECT \* skinny schema visibility' -count=1 -timeout 8m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.354s`; rerun for this skinny projection slice also passed in `0.387s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 5m bats --filter 'diff: clean working set' diff.bats`
  - Result: `1..1`, `ok 1 diff: clean working set`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 8m bats --filter 'diff: (data diff only|schema changes only|with limit|allowed across primary key renames|--filter=renamed filters to only renamed tables)' diff.bats`
  - Result: `1..5`, all 5 named tests passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 8m bats --filter 'diff: row, line, in-place, context diff modes' diff.bats`
  - Result: `1..1`, `ok 1 diff: row, line, in-place, context diff modes`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'diff: with where clause' diff.bats`
  - Result: `1..2`, `ok 1 diff: with where clause`, `ok 2 diff: with where clause errors`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/schema 0.133s`; `ok github.com/dolthub/dolt/go/libraries/doltcore/schema/typecompatibility 0.020s`; `ok github.com/dolthub/dolt/go/libraries/doltcore/schema/encoding 0.453s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 8m bats --filter 'rename-tables: (rename a table with sql|diff a renamed table|sql diff a renamed table)' rename-tables.bats`
  - Result: `1..3`, all 3 named tests passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'primary-key-changes: (diff on primary key schema change shows schema level diff but does not show row level diff|dolt diff table returns top-down diff until schema change|same primary key set in different order is detected and blocked on merge)' primary-key-changes.bats`
  - Result: `1..3`, all 3 named tests passed.
- From `.upstream-cache/dolt-runner/go`, `timeout 180s go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/sqle/dtables`
  - Result: diff and schema packages passed; `sqle/dtables` compiled and reported no test files.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 8m go test ./libraries/doltcore/sqle/enginetest -run TestDiffTableFunction -count=1 -timeout 8m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.988s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 2.989s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run TestDiffSummaryTableFunction -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.674s`; focused rerun covers the upstream `dolt_diff_summary respects dolt_ignore` ScriptTests.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/enginetest -run 'TestDoltScripts/dolt_status_ignored with conflicting patterns' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.340s`; focused rerun covers the upstream conflicting-pattern specificity boundary.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json -count=1 -timeout 10m`
  - Result: all 10 diff/schema/table packages passed.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions -count=1 -timeout 5m`
  - Result: `dtables` compiled with no test files; `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.043s`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/merge -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/merge 6.268s`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/integration_test -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresDiffTable|HistoryTable)$' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.189s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/integration_test -run 'TestDoltSchemas(History|Diff)Table$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.138s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 30m bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats`
  - Result: `1..74`, exit 0; 64 runnable tests passed and 10 upstream-declared skips remained.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: (tables in conflict|renamed table|dolt reset with a renamed table)' status.bats`
  - Result: `1..3`, exit 0; all three focused status tests passed.

## Failed Then Resolved Upstream Command

- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats diff.bats rename-tables.bats primary-key-changes.bats`
  - Initial result: failed at `diff: --system preserves dolt_show_system_tables value in sql-shell` because the upstream `.expect` helper required the missing local `expect` package.
  - Remediation: `sudo -n dnf install -y expect`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 5m bats --filter 'diff: --system preserves dolt_show_system_tables value in sql-shell' diff.bats`
  - Result after installing `expect`: `1..1`, `ok 1 diff: --system preserves dolt_show_system_tables value in sql-shell`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats diff.bats rename-tables.bats primary-key-changes.bats`
  - Clean rerun result: `1..108`, exit 0; all 107 runnable tests passed and 1 upstream-declared skip remained for `rename-tables: merge a renamed table`.

## Failed Upstream Command Still Bounded

- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 60m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats status.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats`
  - Result: exit 1 with plan `1..277`.
  - Failure: `status: dolt reset works with commit hash ref`.
  - Observed cause: `status.bats` defines `get_head_commit()` with a fixed `cut -c 13-44` over `dolt log`; current Dolt log output made that return a truncated commit-hash suffix, and `dolt reset` reported `branch not found`.
  - Boundary: the pristine upstream `status.bats` file was left unchanged. A later runner-local copied file fixes only this helper so the bounded status suite can be exercised without claiming pristine upstream pass parity.
- Focused repro command this session:
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; the helper returned a truncated suffix such as `5o2n53uuvt9n1uanth99i3igh4r`, and `dolt reset` failed with `branch not found`.

## Commit Diff And Log Runner Refresh

- Cache inspection before this refresh:
  - `.upstream-cache/dolt` remained at `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - The known sparse/no-checkout index deletions and untracked local build caches remained; no delete, reset, or broad hydration was run.
- `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
- `rpm -q golang golang-bin golang-src bats expect libicu-devel`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, and `libicu-devel-77.1-2.fc44.x86_64`.
- `go version`; `bats --version`; `expect -version`
  - Result: `go version go1.26.3-X:nodwarf5 linux/amd64`; `Bats 1.13.0`; `expect version 5.45.4`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: `dolt version 2.0.5`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.987s`; this covers bounded `DOLT_COMMIT_DIFF_<table>` and `dolt_log()` engine script tests without a live SQL server.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(LogTableFunction|LogTableFunctionPrepared|DoltCommit)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.749s`; fresh focused log/commit metadata engine coverage for this native slice.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.045s`; fresh focused `dolt_log()` option/type/bind-variable unit coverage.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run 'TestHistorySystemTable/(can sort by dolt_log.commit|dolt_commit_ancestors table with commit_hash filter ignored for max1row optimization)$' -count=1 -timeout 10m -v`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.180s`; focused normal and prepared HistorySystemTable subtests passed for `dolt_commit_ancestors` sorting, commit_hash-filtered merge-parent rows, and parent-hash joins back to `dolt_log` messages.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 15m`
  - Result: diff, schema, typecompatibility, schema/encoding, table, table/untyped, table/untyped/csv, table/untyped/tabular, table/untyped/sqlexport, table/typed/json, sqle/sqlfmt, sqle/expreval, sqle/dtablefunctions, and merge passed; rowconv and sqle/dtables compiled with `[no test files]`; merge completed in `6.434s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 30m bats sql-commit-diff.bats log.bats`
  - Result: `1..37`, exit 0; 2 `sql-commit-diff.bats` tests and 35 `log.bats` tests passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; the helper's `cut -c 13-44` returned truncated suffix `joqcbc13neenpuul9iscj3vartf` from commit `qmvnfjoqcbc13neenpuul9iscj3vartf`, and `dolt reset` reported `branch not found`.

## Status Helper Patched-Copy Runner Refresh

- Cache inspection before this refresh:
  - `.upstream-cache/dolt` remained at `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - The known sparse/no-checkout index deletions and untracked build caches remained; no delete, reset, or broad hydration was run.
- `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
- `rpm -q golang golang-bin golang-src bats expect libicu-devel`; `go version`; `bats --version`; `expect -version`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`; `go version go1.26.3-X:nodwarf5 linux/amd64`; `Bats 1.13.0`; `expect version 5.45.4`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: `dolt version 2.0.5`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; the pristine upstream helper truncated `nadnnhmv0m5703n4pch0qqddolkkg7kp` to `hmv0m5703n4pch0qqddolkkg7kp`, and `dolt reset` reported `branch not found`.
- `cp status.bats status-local-fixed.bats`; runner-local patch to `status-local-fixed.bats`
  - Patch: only `get_head_commit()` changed from fixed-width `cut -c 13-44` to `awk '/^commit / { print $2; exit }'`.
  - Boundary: this copied file lives under `.upstream-cache/dolt/integration-tests/bats`; the pristine upstream `status.bats` file was not changed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 5m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: `1..1`, exit 0; the copied helper version of the stale repro passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 30m bats status-local-fixed.bats sql-status.bats`
  - Result: `1..31`, exit 0; 30 runnable status/sql-status tests passed and 1 upstream-declared skip remained for `status: roots runs even if status fails`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run 'TestDoltScripts/dolt_status_ignored' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.346s`; focused `dolt_status_ignored` engine script coverage passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run 'TestDoltDTableScripts(Prepared)?$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.175s`; focused `dolt_status` normal and prepared table script coverage passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run 'TestDoltConflictsTableNameTable$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.363s`; focused `dolt_conflicts` table-name evidence passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 40m bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats`
  - Result: `1..68`, exit 0; 67 runnable commit-diff/log/status/sql-status tests passed and 1 upstream-declared skip remained.

## Consolidated Bounded Runner Refresh

- Cache inspection before this refresh:
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone staged deletions plus untracked build caches and `integration-tests/bats/status-local-fixed.bats`; no delete or reset was run.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
- `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
- `rpm -q golang golang-bin golang-src bats expect libicu-devel`; `go version`; `bats --version`; `expect -version`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`; `go version go1.26.3-X:nodwarf5 linux/amd64`; `Bats 1.13.0`; `expect version 5.45.4`.
- `rg -n "get_head_commit|cut -c 13-44|awk" status.bats status-local-fixed.bats`
  - Result: pristine `status.bats` still uses `grep -m 1 commit | cut -c 13-44`; runner-local `status-local-fixed.bats` uses `awk '/^commit / { print $2; exit }'`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: `dolt version 2.0.5`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 15m`
  - Result: diff, schema, typecompatibility, schema/encoding, table, table/untyped, table/untyped/csv, table/untyped/tabular, table/untyped/sqlexport, table/typed/json, sqle/sqlfmt, sqle/expreval, sqle/dtablefunctions, and merge passed; rowconv and sqle/dtables compiled with `[no test files]`; merge completed in `6.439s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 3.931s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/integration_test -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.320s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltCommit|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 1.377s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run 'TestDoltScripts/dolt_status_ignored' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.365s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.051s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run 'TestHistorySystemTable/(can sort by dolt_log.commit|dolt_commit_ancestors table with commit_hash filter ignored for max1row optimization)$' -count=1 -timeout 10m -v`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.195s`; focused normal and prepared HistorySystemTable subtests passed for `dolt_commit_ancestors` ordering, commit-hash-filtered merge-parent rows, and parent-message joins through `dolt_log`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/doltdb -run 'Test(ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/doltdb 0.045s`; focused ancestor-spec parser and splitter tests passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run 'TestDoltScripts/test has_ancestor$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.395s`; focused `has_ancestor` branch/tag/HEAD/hash engine script passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 10.400s`; focused `dolt_branches`, prepared `dolt_branches`, and `dolt_branch_activity` engine tests passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine helper truncated commit `61t2d1teve5iahijb5ptk8bn17ih9uc8` to `1teve5iahijb5ptk8bn17ih9uc8`, and `dolt reset` reported `branch not found`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats`
  - Result: `1..319`, exit 0; 303 runnable tests passed and 16 upstream-declared skips remained. This extends the prior 253-plan diff/schema/merge/conflict/sql-commit-diff slice with local `log.bats`, runner-local fixed status coverage, and `sql-status.bats` in one bounded pass.

## Dolt Log Revision Range Refresh

- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(LogTableFunction|LogTableFunctionPrepared)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.623s`; focused upstream table-function script tests passed for `dolt_log()` revision arguments, range/exclusion validation, parents, refs, and row content.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.043s`; focused upstream option/type/bind-variable unit tests passed.
- Native slice added `revisionSpecs` / `notRevisionSpecs` handling for caret exclusions, `--not`-style exclusions, `A..B`, `A...B`, multi-ref unions, HEAD/ref/tag/hash/ancestor-suffix resolution, and invalid range-mixing boundaries.

## Dolt Log Table Filter Refresh

- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(LogTableFunction|LogTableFunctionPrepared)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.841s`; this rerun includes the upstream `dolt_log('--tables', ...)` table-filter script tests.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.071s`; focused upstream option/type/bind-variable unit tests still pass after the table-filter mapping.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 15m bats --filter 'log: (branch with multiple tables|--all works when specifying tables)' log.bats`
  - Result: `1..2`, exit 0; the focused CLI table-filter slice passed.
- Native slice added `tableNames` / `tables` filtering to `CommitLogTable::logRows()`. It accepts either per-commit `changedTables` metadata or root-value-style `tableHashes`, skips root and empty commits when a table filter is active, includes merge commits when the filtered table differs from the relevant parent roots, and applies limits after table filtering.

## Fresh Branch/Status/Table-Filter Runner Refresh

- Cache inspection before this refresh:
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch`: still shows the known sparse/no-checkout out-of-cone staged deletions plus untracked build caches and `integration-tests/bats/status-local-fixed.bats`; no delete, reset, or broader hydration was run.
- `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
- `rpm -q golang golang-bin golang-src bats expect libicu-devel`; `go version`; `bats --version`; `expect -v`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`; `go version go1.26.3-X:nodwarf5 linux/amd64`; `Bats 1.13.0`; `expect version 5.45.4`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: `dolt version 2.0.5`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 15m`
  - Result: diff, schema, typecompatibility, schema/encoding, table, table/untyped, table/untyped/csv, table/untyped/tabular, table/untyped/sqlexport, table/typed/json, sqle/sqlfmt, sqle/expreval, sqle/dtablefunctions, and merge passed; rowconv and sqle/dtables compiled with `[no test files]`; merge completed in `6.024s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(LogTableFunction|LogTableFunctionPrepared|DoltCommit|DoltCommitPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 11.473s`; focused log, commit metadata, branch table, branch activity, status dtable, and conflicts table-name engine evidence passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.046s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine helper truncated commit `m2ldcl19chhplrl5lidvavr13o8uf12i` to `l19chhplrl5lidvavr13o8uf12i`, and `dolt reset` reported `branch not found`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: `1..1`, exit 0; the runner-local fixed helper passed the exact stale-helper repro.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 30m bats status-local-fixed.bats sql-status.bats`
  - Result: `1..31`, exit 0; 30 runnable status/sql-status tests passed and 1 upstream-declared skip remained for `status: roots runs even if status fails`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats --filter 'log: (Log on a table has basic functionality|Log on a table works with -n|dolt log with ref and table|--all works when specifying tables)' log.bats`
  - Result: `1..4`, exit 0; focused table-filtered `dolt log` CLI coverage passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 45m bats branch.bats sql-branch.bats`
  - Result: `1..39`, exit 0; 30 local branch CLI tests and 9 SQL branch procedure/table tests passed.
- `branch-activity.bats` was intentionally not run because it starts a Dolt SQL server; the local in-process Go `TestBranchActivity` runner above remains the bounded branch-activity evidence for this lane.

## Fresh Log Merge/Min-Parent Runner Refresh

- Focused upstream source reads for this slice:
  - `go/libraries/doltcore/sqle/dtablefunctions/dolt_log.go`: `LogTableFunctionArgs.minParents`, `--merges` overriding `--min-parents`, the fixed `parents` column schema, and the iterator `matchFunc` boundary.
  - `go/libraries/doltcore/sqle/enginetest/dolt_queries.go`: `min parents, merges, show parents, decorate` script assertions for `--merges`, `--min-parents 2`, `--min-parents 1`, `--merges` overriding a lower min-parent setting, parent column projection, and empty results for high min-parent values.
  - `integration-tests/bats/log.bats`: `log: --merges, --parents, --min-parents option`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run 'Test(LogTableFunction|LogTableFunctionPrepared)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.618s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.052s`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 15m bats --filter 'log: --merges, --parents, --min-parents option' log.bats`
  - Result: `1..1`, exit 0; the focused BATS case passed.
- Native Dolt PHP lane rerun after this slice passed with 10 test files, 90 behavior tests, 465 assertions, and 0 failures.

## Fresh Log All-Branches Runner Refresh

- Focused upstream source reads for this slice:
  - `go/cmd/dolt/commands/log.go`: `collectRevisions()` expands `--all` to every branch returned by `dolt_branches` / `dolt_remote_branches` before constructing `dolt_log(...)`.
  - `go/cmd/dolt/cli/arg_parser_helpers.go`: `CreateLogArgParser(false)` supports `--all` for CLI log calls.
  - `go/libraries/doltcore/doltdb/commit_itr.go`: `CommitItrForAllBranches` walks commits reachable from branch refs and de-duplicates shared ancestors.
  - `integration-tests/bats/log.bats`: `log: --all correctly gets branches` and `log: --all works when specifying tables`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 15m bats --filter 'log: --all (correctly gets branches|works when specifying tables)' log.bats`
  - Result: `1..2`, exit 0; both focused `--all` BATS cases passed.
- Native Dolt PHP lane rerun after this slice passed with 10 test files, 91 behavior tests, 475 assertions, and 0 failures.

## Fresh Log Oneline/Stat Runner Refresh

- Focused upstream source reads for this slice:
  - `go/cmd/dolt/commands/log.go`: `logCompact`, `logDefault`, `printDiffStats`, `visualizeChangesForLog`, and the merge-commit guard that skips `--stat` output unless a commit has exactly one parent.
  - `go/cmd/dolt/commands/utils.go`: `PrintCommitInfo` and `printRefs` for default commit headers, `Merge:` lines, author/date/message blocks, and decorated ref parentheses.
  - `integration-tests/bats/log.bats`: `log: --oneline only shows commit message in one line`, `log: decorate and oneline work together`, `log: --stat shows diffstat`, `log: --stat works with --oneline`, and `log: --stat doesn't print diffstat for merge commits`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: `dolt version 2.0.5`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats --filter 'log: --oneline only shows commit message in one line|log: decorate and oneline work together' log.bats`
  - Result: `1..2`, exit 0; both focused `--oneline` BATS cases passed.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats --filter 'log: --stat' log.bats`
  - Result: `1..3`, exit 0; the focused `--stat`, `--stat --oneline`, and merge-stat-skip BATS cases passed.
- Native Dolt PHP lane rerun after this slice passed with 10 test files, 93 behavior tests, 495 assertions, and 0 failures.
- Required root `php tools/run-tests.php` initially failed outside Dolt: `lanes/pandoc/tests/MarkdownReaderTest.php` reported 2 failures (`writes wordpress nested html tables inside table cells for legacy imports` and `writes wordpress third-level nested html tables without asciidoc downgrade`), with 122 test files, 9,468 assertions, and 2 failures. Later exact reruns passed with 123 test files, 9,537 assertions, then 10,675 assertions, and 0 failures; the latest exact rerun is recorded in Repository Check.

## Fresh Log Graph Dense Fan-In Runner Refresh

- Focused upstream source reads for this slice:
  - `go/cmd/dolt/commands/log_graph.go`: `printLine`, `printCommitMetadata`, `expandGraphBasedOnCommitMetaDataHeight`, and `drawCommitDotsAndBranchPaths` for default multi-line graph rows, ref suffix placement, dense merge lanes, and branch crossings.
  - `go/cmd/dolt/commands/log_graph.go`: `printOneLineGraph` and `expandGraphBasedOnGraphShape` for compact graph rows and the upstream spacing/ref-placement boundary.
  - `integration-tests/bats/log.bats`: `log: --graph: graph with multiple branches`, covering four side branches merged into main, default graph metadata rows, and compact graph patterns.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'log: --graph: graph with multiple branches' log.bats`
  - Result: `1..1`, exit 0; the focused dense multi-branch graph BATS case passed.
- Exact graph-oneline probe recreated the same four-branch merge setup with the cache-local `dolt` binary and ran `dolt log --graph --oneline --decorate=short`.
  - Result: exit 0; captured output confirmed the head line has one post-hash space, while later commit lines have two post-hash spaces before refs or messages.
- Native Dolt PHP lane rerun after this slice passed with 10 test files, 98 behavior tests, 524 assertions, and 0 failures.
- Required root `php tools/run-tests.php` passed after this slice with 127 test files, 11,352 assertions, and 0 failures.

## Fresh Tooling/Quota Runner Refresh

- Cache inspection before this refresh:
  - `git -C .upstream-cache/dolt rev-parse HEAD`: `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.promisor`: `true`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt status --short --branch --untracked-files=no`: still shows the known sparse/no-checkout out-of-cone staged deletions; no delete, reset, or broader hydration was run.
- `sudo -n dnf install -y golang bats expect`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64` were already installed; `Nothing to do.`
- `rpm -q golang golang-bin golang-src bats expect libicu-devel`; `go version`; `bats --version`; `expect -v`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`; `go version go1.26.3-X:nodwarf5 linux/amd64`; `Bats 1.13.0`; `expect version 5.45.4`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: `dolt version 2.0.5`.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 15m`
  - Initial result: failed during Go linker output creation with `disk quota exceeded` for multiple test binaries.
  - Remediation: use runner-local `TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp` and `go test -p 1` to avoid `/tmp` pressure and reduce concurrent linker output. No checkout reset or cache deletion was run.
- `mkdir -p /home/claude/port-libs/.upstream-cache/dolt/tmp && env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 15m`
  - Result: exit 0; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, and `merge` passed in `9.135s`.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltCommit|DoltCommitPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable)$' -count=1 -timeout 20m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 17.012s`.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable)$' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/integration_test 0.308s`.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/sqle/dtablefunctions -run TestDoltLog -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.044s`.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestHistorySystemTable/(can sort by dolt_log.commit|dolt_commit_ancestors table with commit_hash filter ignored for max1row optimization)$|TestDoltScripts/test has_ancestor$' -count=1 -timeout 10m -v`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.506s`.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltScripts/dolt_status_ignored' -count=1 -timeout 10m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.392s`.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test -p 1 ./libraries/doltcore/doltdb -run 'Test(ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 5m`
  - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/doltdb 0.047s`.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 15m bats --filter 'log: --merges, --parents, --min-parents option' log.bats`
  - Result: `1..1`, exit 0; the focused BATS case passed.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 90m bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats`
  - Result: `1..319`, exit 0; 303 runnable tests passed and 16 upstream-declared skips remained.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit 1 with plan `1..1`; pristine helper truncated `67lim72822k6qpm9kg6gofum2org0oak` to `72822k6qpm9kg6gofum2org0oak`, and `dolt reset` reported `branch not found`.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: `1..1`, exit 0; the runner-local fixed helper passed the same repro.
- `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 45m bats branch.bats sql-branch.bats`
  - Result: `1..39`, exit 0; 30 local branch CLI tests and 9 SQL branch procedure/table tests passed.
- Runner-local artifact sizes after this refresh:
  - `.upstream-cache/dolt/.gocache`: `2.3G`
  - `.upstream-cache/dolt/.gomodcache`: `1.3G`
  - `.upstream-cache/dolt/tmp`: `7.8M`
  - `.upstream-cache/dolt/bats-home`: `383M`
  - `.upstream-cache/dolt/bats-tmp`: `4.0K`

## Repository Check

- Fresh `dolt_patch()` runner refresh for this slice:
  - Focused upstream source reads:
    - `go/libraries/doltcore/sqle/dtablefunctions/dolt_patch.go`: `dolt_patch()` row schema, schema/data partition key handling, schema-before-data statement iteration, drop-table data suppression, table sorting, and statement-order incrementing.
    - `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: `PatchTableFunctionScriptTests` contains 13 script tests and 70 query assertions for invalid arguments, create/drop/data patch rows, DDL ordering, WORKING/STAGED refs, binary values, tag collision, and ignore-after-commit behavior.
    - `go/libraries/doltcore/sqle/enginetest/system_table_function_index_tests.go`: `diff_type` lookup tests for schema-only/data-only partitions and lookup joins/subqueries.
    - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`, `go/libraries/doltcore/sqle/sqlfmt/row_fmt.go`, and `go/libraries/doltcore/table/untyped/sqlexport/sql_diff_writer.go`: schema/data statement generation shared by `dolt_patch()` and row SQL diff output.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 12m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestPatchTableFunction(Prepared)?$' -count=1 -timeout 12m`
    - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.579s`.
  - Native Dolt PHP lane rerun after this slice passed with 15 Dolt test files, 131 behavior tests, 687 assertions, and 0 failures.
- Fresh 2026-05-23 `dolt_patch()` argument-boundary runner refresh:
  - Focused upstream source reads:
    - `go/libraries/doltcore/sqle/dtablefunctions/dolt_patch.go`: `WithExpressions`, `evaluateArguments`, `CheckAuth`, optional table-name evaluation, text-argument validation, explicit-ref and dot-range arity, and table-not-found handling.
    - `go/libraries/doltcore/sqle/dtablefunctions/dolt_diff.go`: shared `findMatchingDelta`, `loadDetailsForRefs`, `loadCommitStrings`, `resolveCommitStrings`, two-dot splitting, and three-dot merge-base resolution.
    - `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: focused invalid-argument, table-not-found, branch-ref, two-dot, three-dot, WORKING/STAGED, and no-op patch assertions.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(PatchTableFunction|PatchTableFunctionPrepared)$' -count=1 -timeout 10m`
    - Result: `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 0.598s`.
  - Native Dolt PHP lane rerun after this slice passed with 16 Dolt test files, 136 behavior tests, 710 assertions, and 0 failures.

- Fresh 2026-05-23 `dolt_patch()` metadata-only column/check-constraint boundary refresh:
  - Focused upstream source reads:
    - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: non-create/non-drop patch generation only appends `MODIFY COLUMN` when `TypeInfo` changes, while defaults, generated expressions, on-update expressions, and nullability are formatter fields used when a supported CREATE/ADD/MODIFY row is already emitted.
    - `go/libraries/doltcore/sqle/sqlfmt/schema_fmt.go`: `GenerateCreateTableStatement()` appends check constraints for create-table output, but `generateNonCreateNonDropTableSqlSchemaDiff()` has no add/drop/modify check-constraint patch branch.
    - `integration-tests/bats/sql-check-constraints.bats`: upstream add/drop check-constraint enforcement and survival behavior, used as support for the schema metadata boundary rather than as patch-row evidence.
  - Direct cache-local CLI probes:
    - `dolt_patch('HEAD','WORKING','foo')` after `ALTER TABLE foo ADD CONSTRAINT status_chk CHECK (...)` returned only the CSV header.
    - `dolt_patch('HEAD','WORKING','foo')` after `ALTER TABLE foo DROP CONSTRAINT status_chk` returned only the CSV header.
    - `dolt_patch('HEAD','WORKING','q')` after `ALTER TABLE q ALTER COLUMN title SET DEFAULT 'reviewed'` returned only the CSV header.
    - `dolt_patch('HEAD','WORKING','q')` after `ALTER TABLE q MODIFY COLUMN title varchar(100) DEFAULT 'reviewed'` emitted ``ALTER TABLE `q` MODIFY COLUMN `title` varchar(100) DEFAULT 'reviewed';``.
    - `dolt_patch('HEAD','WORKING','q')` after `ALTER TABLE q MODIFY COLUMN slug varchar(320) GENERATED ALWAYS AS (...) STORED` emitted a generated-column `MODIFY COLUMN` row.
  - Native Dolt PHP lane rerun after this slice passed with 16 Dolt test files, 171 behavior tests, 863 assertions, and 0 failures.

- `php tools/run-tests.php`
  - Required rerun after the metadata-only column/check-constraint patch boundary slice passed with 176 test files, 16,734 assertions, and 0 failures.
  - Required rerun after native `dolt_patch()` argument-boundary slice passed with 157 test files, 14,252 assertions, and 0 failures; exact rerun after final lane-status metadata passed with 157 test files, 14,270 assertions, and 0 failures.
  - Required rerun after native `dolt_patch()` patch-row rendering initially failed outside Dolt in `lanes/esbuild/tests/TypeScriptModuleLowererTest.php` (`keeps upstream local exports outside top level using helper scopes`); Dolt tests reached by that run passed. Immediate rerun passed with 153 test files, 13,910 assertions, and 0 failures.
  - Initial required rerun after the 2026-05-22 22:12 UTC runner/tooling metadata refresh exited 1 with 138 test files, 12,225 assertions, and 1 failure; the streamed output did not retain the specific failing assertion.
  - Immediate captured rerun after the same metadata refresh passed: 138 test files, 12,242 assertions, 0 failures.
  - Final required rerun after the lane-status recording edit passed: 139 test files, 12,328 assertions, 0 failures.
  - Required rerun after native `dolt diff --summary` fixed-width rendering passed: 133 test files, 11,879 assertions, 0 failures.
  - Required rerun after the 2026-05-22 21:38 UTC runner/tooling metadata refresh passed: 131 test files, 11,742 assertions, 0 failures.
  - Required rerun after the current 2026-05-22 21:24 UTC runner metadata update passed: 127 test files, 11,352 assertions, 0 failures.
  - Post-metadata rerun passed after the commit-diff/log runner refresh: 102 test files, 6,868 assertions, 0 failures.
  - Two transient non-Dolt failures were observed while other lanes were active: one readability fixture failure and one pandoc footnote fixture failure.
  - A later required rerun after those transient failures passed: 102 test files, 6,955 assertions, 0 failures.
  - Required rerun after the native commit-log slice passed: 104 test files, 7,219 assertions, 0 failures.
  - Current reruns after the status-helper runner refresh passed: 106 test files, 7,406 assertions, 0 failures; then 106 test files, 7,467 assertions, 0 failures after a transient aggregate failure cleared on immediate rerun.
  - Latest required post-edit reruns are red in active non-Dolt lanes. Two reruns failed in `lanes/pandoc/tests/MarkdownReaderTest.php` test `maps remaining upstream pipe table default headerless one-column and width cases` with `Expected: 'table'`, `Actual: 'paragraph'` (106 files/7,476 assertions/1 failure, then 106 files/7,480 assertions/1 failure). A later rerun after recording that blocker failed in `lanes/difftastic/tests/TokenDifferTest.php` test `maps upstream simple scss sample through mixin and nested rule alignment` with 106 files, 7,486 assertions, and 1 failure.
  - A required rerun after the native commit-ancestors slice initially failed in an unrelated active rclone lane: `lanes/rclone/tests/DeletePlanningTest.php` test `immutable wordpress archive sync preserves existing backup artifacts` reported `immutable file modified`; summary was 107 test files, 7,572 assertions, 1 failure. Dolt tests reached by that root runner passed.
  - Required rerun after the finished Dolt batch passed: 108 test files, 7,683 assertions, 0 failures.
  - Final aggregate rerun after concurrent-lane updates also passed: 108 test files, 7,782 assertions, 0 failures.
  - Required rerun after the consolidated runner metadata update passed: 109 test files, 7,887 assertions, 0 failures.
  - Required rerun after the native `has_ancestor` slice passed: 110 test files, 8,021 assertions, 0 failures.
  - Required rerun after the native branch table/activity slice passed: 112 test files, 8,270 assertions, 0 failures.
  - Required rerun after the current runner metadata update initially failed in an unrelated esbuild lane: 113 test files, 8,290 assertions, 7 failures. The failing tests were in `lanes/esbuild/tests/TypeScriptModuleLowererTest.php` and `lanes/esbuild/tests/TypeScriptNamespaceLowererTest.php`, rooted in missing `PortLibs\Esbuild\TypeScriptModuleLowerer::classHeaderHasExtends()` or the resulting wrong error type.
  - Immediate required rerun after the unrelated esbuild lane changed passed: 113 test files, 8,366 assertions, 0 failures.
  - Latest required rerun after later unrelated-lane changes failed in `lanes/esbuild/tests/TypeScriptModuleLowererTest.php` test `lowers wordpress class field assign semantics without node`: 113 test files, 8,389 assertions, 1 failure. The Dolt tests in that root run passed.
  - Final required rerun after the current runner metadata correction passed: 113 test files, 8,396 assertions, 0 failures.
  - Required rerun after native `dolt_log()` revision-range filtering passed: 115 test files, 8,634 assertions, 0 failures.
  - Required rerun after the fresh branch/status/table-filter runner metadata initially failed outside Dolt with 116 test files, 8,862 assertions, and 2 failures in libsqlite/syncthing. A later required rerun after concurrent lane fixes passed with 116 test files, 8,875 assertions, and 0 failures. Final post-metadata rerun passed with 116 test files, 8,947 assertions, and 0 failures. Dolt tests reached by all three root runs passed.
  - Required rerun after native `dolt_log()` merge/min-parent filtering passed: 116 test files, 8,955 assertions, 0 failures.
  - Final current-HEAD rerun after intervening lane commits passed: 116 test files, 8,974 assertions, 0 failures.
  - Required rerun after native `dolt_log()` `--all` branch traversal passed: 120 test files, 9,245 assertions, 0 failures.
  - Final current-HEAD rerun after concurrent lane additions passed: 120 test files, 9,276 assertions, 0 failures.
  - Required rerun after native `dolt log --oneline` / `--stat` rendering initially failed in an unrelated Pandoc lane: 122 test files, 9,468 assertions, 2 failures in `lanes/pandoc/tests/MarkdownReaderTest.php`. A later exact rerun passed with 123 test files, 9,537 assertions, and 0 failures.
  - Required rerun after the fresh tooling/quota runner metadata update passed: 123 test files, 9,537 assertions, 0 failures.
  - An intervening required rerun after the final lane-status wording update failed outside Dolt: 123 test files, 9,504 assertions, 24 failures, including the two Difftastic failures below plus 22 active LightningCSS `TransitionPrefixer::rewriteTextDecorationPrefixEntries()` failures.
  - A later required rerun after concurrent LightningCSS fixes still failed outside Dolt: 123 test files, 10,582 assertions, 2 failures in `lanes/difftastic/tests/TokenDifferTest.php` (`maps typescript default namespace and re-export source changes`, `wordpress block module asset diff keeps default and namespace imports aligned`). Dolt tests reached by that root runner passed.
  - Final required rerun after this lane-status cleanup passed: 123 test files, 10,675 assertions, 0 failures.
  - Final current-HEAD rerun after concurrent Difftastic fixes passed: 123 test files, 10,689 assertions, 0 failures.
  - Required rerun after the final runner-status metadata cleanup passed: 123 test files, 10,737 assertions, 0 failures.
  - Required rerun after native `dolt log --graph` / `--decorate=auto` rendering passed: 124 test files, 10,849 assertions, 0 failures.
  - Required rerun after native dense multi-branch `dolt log --graph` default rendering passed: 126 test files, 11,197 assertions, 0 failures.
  - Required rerun after native dense multi-branch `dolt log --graph --oneline` exact spacing/ref-placement rendering passed: 127 test files, 11,352 assertions, 0 failures.
  - Required rerun after native `dolt log -n` / `--number` limit aliases and zero-limit handling passed: 129 test files, 11,605 assertions, 0 failures.
  - Dolt lane tests reached by the root runner passed throughout, including `DOLT_COMMIT_DIFF` required-filter/range-predicate behavior, `dolt_merge_status`, `dolt_conflicts`, `dolt_history_dolt_schemas`, `dolt_diff_dolt_schemas`, `dolt_history_dolt_procedures`, and `dolt_diff_dolt_procedures` projection tests.
  - The latest root runner additionally covers native `dolt_log`/`dolt_commits`, native `dolt_commit_ancestors`, native `has_ancestor`, native branch table/activity projection, and the WordPress commit-log, fan-in commit-graph, commit-ancestors, has-ancestor, and branch-review fixtures.
  - Required rerun after native diff-summary diff-type filtering failed outside Dolt: 135 test files, 12,033 assertions, 1 failure in `lanes/pandoc/tests/MarkdownReaderTest.php` test `maps upstream html table caption colgroup thead and tfoot structure`; expected `softbreak`, actual `linebreak`. Dolt tests reached by this root run passed.
  - Required rerun after native row SQL diff-filter rendering passed: 138 test files, 12,193 assertions, 0 failures.
  - Required rerun after native row-mode tabular diff-filter rendering passed: 140 test files, 12,359 assertions, 0 failures.
  - Final post-metadata rerun failed outside Dolt: 140 test files, 12,435 assertions, 1 failure in `lanes/syncthing/tests/RequestServerTest.php` (`maps upstream temporary file prefix recognition`) because that uncommitted Syncthing test calls missing `TestRunner::false()`. Dolt tests reached by this root run passed.
  - Initial required rerun after native tabular diff-mode rendering failed outside Dolt: 141 test files, 12,572 assertions, 2 failures in `lanes/readability/tests/ArticleExtractorTest.php`. Dolt tests reached by this root run passed.
  - Final captured rerun after native tabular diff-mode rendering passed: 142 test files, 12,668 assertions, 0 failures.
  - Early required reruns during the check-constraint maintenance slice invoked the root runner and failed outside Dolt: 176 test files, 16,798 assertions, 5 failures in `lanes/difftastic/tests/TokenDifferTest.php` tab/display wrapping expectations. Dolt tests reached by those root runs passed.
  - Final required rerun after concurrent lane fixes passed: 177 test files, 16,954 assertions, 0 failures.
- Lane-only Dolt PHP test command:
  - `php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $runner=new TestRunner(); foreach (glob("lanes/dolt/tests/*Test.php") as $file) { $runner->runTests(require $file, $file); } fwrite(STDOUT, "\nDolt: " . count(glob("lanes/dolt/tests/*Test.php")) . " test files, " . $runner->assertions() . " assertions, " . $runner->failures() . " failures\n"); exit($runner->failures() === 0 ? 0 : 1);'`
  - Previous result before the commit-log slice: pass with 6 Dolt test files, 64 behavior tests, 273 assertions, and 0 failures.
  - Prior result after the commit-log slice: pass with 7 Dolt test files, 70 behavior tests, 306 assertions, and 0 failures.
  - Prior rerun after the status-helper runner refresh also passed with 7 Dolt test files, 70 behavior tests, 306 assertions, and 0 failures.
  - Prior result after the commit-ancestors slice: pass with 8 Dolt test files, 74 behavior tests, 323 assertions, and 0 failures.
  - Prior result after the native `has_ancestor` slice: pass with 9 Dolt test files, 78 behavior tests, 380 assertions, and 0 failures.
  - Prior result after native `dolt_log()` revision-range filtering: pass with 10 Dolt test files, 87 behavior tests, 438 assertions, and 0 failures.
  - Current result after native `dolt_log()` table filtering: pass with 10 Dolt test files, 89 behavior tests, 451 assertions, and 0 failures.
  - Current result after native `dolt_log()` merge/min-parent filtering: pass with 10 Dolt test files, 90 behavior tests, 465 assertions, and 0 failures.
  - Current result after native `dolt_log()` `--all` branch traversal: pass with 10 Dolt test files, 91 behavior tests, 475 assertions, and 0 failures.
  - Current result after native `dolt log --oneline` / `--stat` rendering: pass with 10 Dolt test files, 93 behavior tests, 495 assertions, and 0 failures.
  - Current result after native `dolt log --graph` / `--decorate=auto` rendering: pass with 10 Dolt test files, 95 behavior tests, 515 assertions, and 0 failures.
  - Current result after native dense multi-branch `dolt log --graph --oneline` exact spacing/ref-placement rendering: pass with 10 Dolt test files, 98 behavior tests, 524 assertions, and 0 failures.
  - Current result after native `dolt log -n` / `--number` limit aliases and zero-limit handling: pass with 10 Dolt test files, 99 behavior tests, 534 assertions, and 0 failures.
  - Current result after native `dolt diff --summary` fixed-width rendering: pass with 11 Dolt test files, 102 behavior tests, 543 assertions, and 0 failures.
  - Current result after native diff-summary diff-type filtering: pass with 11 Dolt test files, 104 behavior tests, 552 assertions, and 0 failures.
  - Current result after native row SQL diff-filter rendering: pass with 12 Dolt test files, 107 behavior tests, 566 assertions, and 0 failures.
  - Current result after native row-mode tabular diff-filter rendering: pass with 13 Dolt test files, 111 behavior tests, 582 assertions, and 0 failures.
  - Current result after native tabular diff-mode rendering: pass with 13 Dolt test files, 113 behavior tests, 594 assertions, and 0 failures.
  - Current result after native check-constraint maintenance classification: pass with 16 Dolt test files, 173 behavior tests, 874 assertions, and 0 failures.
  - Current result after native auto-increment patch rendering: pass with 16 Dolt test files, 176 behavior tests, 890 assertions, and 0 failures.

- Focused upstream local command:
  - Fresh direct cache-local Dolt CLI probe in a throwaway `/tmp` repo confirmed `dolt_patch('HEAD','WORKING','wp_import_audit')` returns no rows for existing-table `ALTER TABLE ... ADD CONSTRAINT`, `ALTER TABLE ... DROP CONSTRAINT`, and drop-plus-add check-constraint modification; each CSV query printed only the header.

- Fresh 2026-05-23 `dolt merge` failure-summary evidence:
  - Static source inspection counted 6 focused upstream paths and 95 targeted references in `go/cmd/dolt/commands/merge.go`, `go/libraries/doltcore/merge/merge_stats.go`, `go/libraries/doltcore/sqle/dtables/merge_status_table.go`, `integration-tests/bats/conflict-detection.bats`, `integration-tests/bats/constraint-violations.bats`, and `integration-tests/bats/merge.bats`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 15m bats --filter 'conflict-detection: two branches modify same cell. merge. conflict' conflict-detection.bats`
  - Result: pass with plan `1..1`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 15m bats --filter 'constraint-violations: unique key violations create unmerged tables' constraint-violations.bats`
  - Result: pass with plan `1..1`.
  - Direct cache-local mixed probe in a throwaway repo produced exit `1` and the expected final block: `Automatic merge failed; 2 table(s) are unmerged.`, `Fix conflicts and constraint violations and then commit the result.`, and `Use 'dolt conflicts' to investigate and resolve conflicts.`.
  - Focused native PHP runner using `TestRunner` directly passed `MergeStatusTableTest.php` plus `ConstraintViolationsTableTest.php` with 2 files, 73 assertions, and 0 failures.
  - Dolt lane-only PHP passed with 19 files, 199 behavior tests, 997 assertions, and 0 failures.
  - Required guarded root `php tools/run-tests.php` passed with 183 test files, 18,832 assertions, and 0 failures after `pgrep -af '^php tools/run-tests\\.php( |$)'` showed no active root harness. Two attempted focused `tools/run-tests.php ...` commands were stopped earlier because the harness ignores file arguments and another root run had acquired `.upstream-cache/run-tests.lock`; focused lane verification used `TestRunner` directly instead.

- Fresh 2026-05-23 `dolt merge` transcript/stat evidence:
  - Static source inspection counted 4 focused upstream paths and 127 targeted references in `go/cmd/dolt/commands/merge.go`, `integration-tests/bats/conflict-detection.bats`, `integration-tests/bats/constraint-violations.bats`, and `integration-tests/bats/merge.bats`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 20m bats --filter 'conflict-detection: two branches modify different cell different row. merge. no conflict|conflict-detection: two branches modify same cell. merge. conflict' conflict-detection.bats`
  - Result: exit `0`, plan `1..2`; both focused success/conflict transcript cases passed.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 20m bats --filter 'merge: unique index conflict|constraint-violations: unique key violations create unmerged tables' merge.bats constraint-violations.bats`
  - Result: exit `0`, plan `1..2`; both focused constraint-violation transcript cases passed.
  - Direct cache-local Dolt CLI probes in throwaway `.upstream-cache/dolt/tmp` repos captured exact transcript lines for a divergent successful WordPress-style merge (`wp_posts | 2 +*`, `1 tables changed, 1 rows added(+), 1 rows modified(*), 0 rows deleted(-)`, `wp_import_audit added`, `wp_terms deleted`), a content conflict (`Auto-merging wp_posts`, `CONFLICT (content): Merge conflict in wp_posts`), and a constraint violation (`Auto-merging wp_import_audit`, `CONSTRAINT VIOLATION (content): Merge created constraint violation in wp_import_audit`).
  - Dolt lane-only PHP passed with 19 files, 200 behavior tests, 1009 assertions, and 0 failures after adding native artifact-prelude and success-stat renderers.
  - Guarded root `php tools/run-tests.php` passed with 183 test files, 19,152 assertions, and 0 failures. The pre-run `pgrep -af '^php tools/run-tests\\.php( |$)'` check returned no active root harness; the root runner initially reported `.upstream-cache/run-tests.lock` was busy, then acquired it as PID 701016 and completed.

## Runner Refresh 2026-05-23 06:28 UTC Tooling And 543-Plan Local BATS Shard

- Cache inspection before building/running:
  - `git -C .upstream-cache/dolt status --short --branch`: known sparse/no-checkout out-of-cone deletions plus runner-local `.gocache/`, `.gomodcache/`, `bats-home/`, `tmp/`, and `integration-tests/bats/status-local-fixed.bats`.
  - `git -C .upstream-cache/dolt rev-parse --is-shallow-repository --filter --show-toplevel HEAD`: `true`, `--filter`, `/home/claude/port-libs/.upstream-cache/dolt`, `b2274926e0dcd84aab000ee242df5b5e75689eef`.
  - `git -C .upstream-cache/dolt sparse-checkout list`: `go`, `integration-tests/bats`.
  - `git -C .upstream-cache/dolt config --get remote.origin.partialclonefilter`: `blob:none`.
  - No delete, reset, or wider sparse hydration was run.
- Tooling check:
  - `sudo -n dnf install -y golang bats expect libicu-devel`
  - Result: exit `0`; all four packages were already installed and DNF returned `Nothing to do`.
  - `rpm -q golang golang-bin golang-src bats expect libicu-devel`
  - Result: `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, `expect-5.45.4-31.fc44.x86_64`, `libicu-devel-77.1-2.fc44.x86_64`.
  - `go version`: `go version go1.26.3-X:nodwarf5 linux/amd64`.
  - `bats --version`: `Bats 1.13.0`.
  - `expect -v`: `expect version 5.45.4`.
- Cache-local build:
  - `mkdir -p /home/claude/port-libs/.upstream-cache/dolt/tmp /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin /home/claude/port-libs/.upstream-cache/dolt/bats-tmp`
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOBIN=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 15m go install -p 1 ./cmd/dolt ./store/cmd/noms ./utils/remotesrv`
  - Result: exit `0`; cache-local `dolt`, `noms`, and `remotesrv` rebuilt.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home /home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin/dolt version`
  - Result: `dolt version 2.0.5`.
- Bounded Go evidence:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 20m`
  - Result: exit `0`; 14 packages passed, `rowconv` and `sqle/dtables` compiled with no test files, `sqle/dtablefunctions` passed in `0.040s`, and `merge` passed in `6.464s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 30m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|DiffStatTableFunction|DiffStatTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|PatchTableFunction|PatchTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared|CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared|DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity|DoltDTableScripts|DoltDTableScriptsPrepared|DoltConflictsTableNameTable|DoltUserPrivileges)$' -count=1 -timeout 30m`
  - Result: exit `0`; focused sqle/enginetest diff, summary, stat, schema, patch, column/system diff, commit-diff, log, branch, branch-activity, status/conflict, and user-privilege coverage passed in `16.855s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test -p 1 ./libraries/doltcore/sqle/integration_test ./libraries/doltcore/doltdb -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresHistoryTable|DoltProceduresDiffTable|HistoryTable|ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 10m`
  - Result: exit `0`; focused schema/procedure/history integration tests passed in `0.235s` and ancestor spec unit tests passed in `0.050s`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test -p 1 ./libraries/doltcore/sqle/enginetest -run 'TestDoltVerifyConstraints$|TestDoltMerge/(keyless table merge with constraint violations|keyless table merge with constraint violation on duplicate rows|Constraint violations are persisted|violation system table supports multiple violations per row|clearing constraint violations \(MySQL\): single delete, bulk delete, and commit|merge error lists all constraint violations when table has multiple violations|merge error includes row count for foreign key violations|merge error includes row count for null constraint violations|merge error includes row count for check constraint violations)$' -count=1 -timeout 20m -v`
  - Result: exit `0`; full `TestDoltVerifyConstraints` and focused `TestDoltMerge`, `TestDoltMergePrepared`, and `TestDoltMergeArtifacts` constraint-violation subtests passed; package result `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/enginetest 1.913s`.
- Bounded BATS evidence from `.upstream-cache/dolt/integration-tests/bats`:
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 90m bats verify-constraints.bats constraint-violations.bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats branch.bats sql-branch.bats keyless.bats keyless-foreign-keys.bats`
  - Result: exit `0`, plan `1..543`; all non-skipped local tests passed across verify-constraints, constraint-violations, diff/schema/rename/primary-key/diff-stat/query-diff/column-tag/sql-diff, merge/schema-conflict/conflict-detection, commit-diff/log/status/sql-status, branch/sql-branch, keyless, and keyless foreign-key behavior. Upstream-declared skips were preserved.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status.bats`
  - Result: exit `1`, plan `1..1`; pristine `status.bats` still truncates `2krfqep8fmuvfmdr4k1qnca7ijfefstc` to `ep8fmuvfmdr4k1qnca7ijfefstc`, and `dolt reset` reports `branch not found`.
  - `env TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/tmp HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp DOLT_DISABLE_VERSION_CHECK=1 SQL_ENGINE=local PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin timeout 10m bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats`
  - Result: exit `0`, plan `1..1`; runner-local fixed helper passed the exact status repro.
- Boundary unchanged: no full `go test ./...`, full BATS directory, live-service, MySQL-server, cloud, Hadoop/parquet, client-compatibility, SQL-server, or benchmark suites were run.
- Required repository check after this metadata update:
  - `php tools/run-tests.php`
  - Result: exit `0`; `187` test files, `20,272` assertions, and `0` failures.

## Skipped Suites

- Full `go test ./...`: skipped as too broad for this runner slice because it hydrates and compiles the full Dolt workspace and broad dependency graph.
- Full BATS directory: skipped even after the combined 319-plan local BATS diff/schema/merge/conflict/log/status pass because the remaining upstream BATS coverage includes Python package requirements, parquet/Hadoop tooling, server tests, compatibility tests, client integration tests, and other live-service style coverage.
- MySQL-server, cloud, Hadoop/parquet, benchmark, and remote-service suites: intentionally skipped per runner boundary.

## Remaining Runner Boundary

- This is bounded upstream evidence, not full upstream parity.
- The cache has build/test artifacts under `.upstream-cache/dolt/.gomodcache`, `.upstream-cache/dolt/.gocache`, and `.upstream-cache/dolt/bats-home`.
- The pristine upstream `status.bats` helper still fails on fixed-width commit-hash extraction; the runner-local copied `status-local-fixed.bats` file resolves that helper boundary and lets the full local status suite pass, but it is documented as a patched-copy runner aid rather than pristine upstream pass parity.
- Runner metadata is part of the current Dolt lane batch with the skinny projection, where/limit filtering, summary/stat primary-key warning/error boundaries, dolt_ignore implementation evidence, schema-history/schema-diff evidence, procedure-history/procedure-diff evidence, commit-diff/log/commit-ancestors/has_ancestor/branch evidence, focused branch Go engine evidence, and combined local upstream diff/schema/merge/log/status BATS evidence.

## Supervisor Rearm 2026-05-25 Schema-Conflict Description Slice

- Upstream source inspection: `go/libraries/doltcore/sqle/dtables/schema_conflicts_table.go` exposes `dolt_schema_conflicts` rows with `table_name`, `base_schema`, `our_schema`, `their_schema`, and `description`; `go/libraries/doltcore/merge/merge_schema.go` formats column, check, index, and modify/delete schema conflict descriptions; focused schema merge cases in `dolt_queries_merge.go` cover preview schema-conflict counts and the `schema conflicts found: 1` data-preview error boundary.
- Native delta: `PreviewMergeConflictsTable::schemaConflictRows()` now projects schema-conflict description rows for WordPress merge review, including CREATE TABLE text, `<deleted>` table sides, column tag-collision text, check name-collision text, and modify/delete text.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/PreviewMergeConflictsTableTest.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 2 files, 141 assertions, and 0 failures.
- Example smoke: `php -r '$r=require "lanes/dolt/examples/wordpress-merge-status-review.php"; echo count($r["previewSchemaConflictDescriptionRows"])."\n"; echo $r["previewSchemaConflictDescriptionRows"][0]["description"]."\n";'` returned one row and the expected `wp_options` column/check description text.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge-preview projection surface and static schema description formatting, with no shell-outs and no activation of a shared dependency.

## Supervisor Rearm 2026-05-25 Schema-Conflict Resolution Slice

- Upstream evidence reused: `dolt status` and `dolt commit` expose unresolved schema conflicts as commit-blocking unmerged paths, and upstream guidance tells users to run `dolt add <table>...` to mark resolution. Prior focused merge/status/schema-conflict Go/BATS evidence remains the bounded denominator for this lane slice; no wider upstream runner was executed in this isolated worktree.
- Native delta: `MergeStatusTable::resolveSchemaConflicts()` now projects the visible state after `dolt add <table>` clears schema-conflict paths, including remaining `dolt_conflicts`-style schema rows, status guidance, and commit guidance. The WordPress fixture marks `wp_options` resolved and returns the all-merged status prompt while preserving the active merge conclusion state.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 133 assertions, and 0 failures.
- Example smoke: `php -r '$r=require "lanes/dolt/examples/wordpress-merge-status-review.php"; echo count($r["resolvedSchemaConflictState"]["remaining_schema_conflicts"])."\n"; echo $r["resolvedSchemaConflictState"]["status_guidance"]."\n";'` returned `0` and `All conflicts and constraint violations fixed but you are still merging.` followed by the upstream commit conclusion hint.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status projection surface and table-name normalization, with no shell-outs and no activation of a shared dependency.

## Supervisor Rearm 2026-05-25 Root-Object Conflict Resolution Slice

- Upstream evidence reused: `dolt_conflicts` includes root-object conflict names alongside table conflicts, while root-object details are separate schema-object rows. Prior focused merge/conflict Go/BATS evidence remains the bounded denominator for this lane slice; no wider upstream runner was executed in this isolated worktree.
- Native delta: `MergeStatusTable::resolveRootObjectConflicts()` now projects the visible state after root-object conflict resolution, removing resolved view/procedure names from `dolt_conflicts`-style rows and clearing the root-object-only failure summary once all supplied root objects are resolved.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 138 assertions, and 0 failures.
- Example smoke: `php -r '$r=require "lanes/dolt/examples/wordpress-merge-status-review.php"; echo count($r["resolvedRootObjectConflictState"]["remaining_root_object_conflicts"])."\n"; var_export($r["resolvedRootObjectConflictState"]["merge_failure_summary"]); echo "\n";'` returned `0` and `NULL`.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status projection surface and root-object table-name normalization, with no shell-outs and no activation of a shared dependency.

## Watchdog Next 2026-05-25 Mixed Merge Artifact Resolution Slice

- Upstream evidence reused: Dolt exposes unresolved merge artifacts through `dolt_conflicts`, `dolt_merge_status`, `dolt status`, commit guidance, immediate merge failure summaries, and constraint-violation system tables. Prior focused merge/status/conflict/constraint Go, BATS, and CLI evidence remains the bounded denominator for this lane slice; no wider upstream runner was executed in this isolated worktree.
- Native delta: `MergeStatusTable::resolveMergeArtifacts()` now projects the visible state after selected data conflicts, schema conflicts, constraint-violation tables, and root-object conflicts are resolved, preserving only remaining blockers in conflict rows, status guidance, commit guidance, and merge failure summaries.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 145 assertions, and 0 failures.
- Example smoke: `php -r '$r=require "lanes/dolt/examples/wordpress-merge-status-review.php"; echo count($r["partiallyResolvedMergeState"]["remaining_data_conflicts"])."\n"; echo implode(",", $r["partiallyResolvedMergeState"]["remaining_constraint_violations"])."\n"; echo $r["partiallyResolvedMergeState"]["merge_failure_summary"]."\n";'` returned `1`, `wp_postmeta,wp_import_audit`, and an `Automatic merge failed; 3 table(s) are unmerged.` summary.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status/constraint projection surface, with no shell-outs and no activation of a shared dependency.

## Watchdog Next 2026-05-25 Schema-Conflict Side Resolution Slice

- Upstream evidence reused: `schema-conflicts.bats` documents `dolt_conflicts_resolve('--ours', 't')` and `--theirs` schema-conflict resolution cases, currently skipped upstream behind Dolt issue 6616, while active schema-conflict BATS and Go cases verify `dolt_schema_conflicts` rows and merge/status conflict visibility. No wider upstream runner was executed in this isolated worktree.
- Native delta: `MergeStatusTable::resolveSchemaConflictSide()` now projects side-selection state for `dolt_conflicts_resolve('--ours'|'--theirs', table)`, returning the chosen schema text, clearing the selected schema-conflict table, and preserving remaining schema-conflict status/commit guidance.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 150 assertions, and 0 failures.
- Example smoke: `php -r '$r=require "lanes/dolt/examples/wordpress-merge-status-review.php"; echo $r["resolvedSchemaConflictSideState"]["table"]."\n"; echo $r["resolvedSchemaConflictSideState"]["resolution"]."\n"; echo count($r["resolvedSchemaConflictSideState"]["remaining_schema_conflicts"])."\n"; echo strpos($r["resolvedSchemaConflictSideState"]["selected_schema"], "idx_meta_review") !== false ? "selected-theirs-index\n" : "missing\n";'` returned `wp_postmeta`, `theirs`, `2`, and `selected-theirs-index`.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status/schema-conflict projection surface and supplied schema strings, with no shell-outs and no activation of a shared dependency.

## Watchdog Next 2026-05-25 SQL Merge Transaction Conflict Error Slice

- Upstream evidence reused: `go/libraries/doltcore/sqle/dsess/transactions.go` defines distinct unresolved-conflict errors for normal transaction commit versus `@autocommit` rollback, and focused `CALL DOLT_MERGE` autocommit-off tests in `dolt_queries_merge.go` / `dolt_transaction_queries.go` document that unresolved conflicts remain queryable when autocommit is disabled or `@@dolt_allow_commit_conflicts` is enabled. No wider upstream runner was executed in this isolated worktree.
- Native delta: `MergeStatusTable::mergeTransactionConflictError()` now projects the upstream SQL unresolved-merge-conflict error boundary, returning the `@autocommit transaction rolled back` guidance when autocommit is enabled, the normal transaction rollback guidance when autocommit is disabled, and no error when conflicts are allowed or absent.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 159 assertions, and 0 failures.
- Example smoke: `php -r '$r=require "lanes/dolt/examples/wordpress-merge-status-review.php"; echo $r["sqlAutocommitConflictError"]."\n"; echo $r["mergeProcedureRows"]["conflicts"]["message"]."\n";'` returned the upstream `@autocommit transaction rolled back` guidance and `conflicts found`.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status/procedure projection surface and static upstream error text, with no shell-outs and no activation of a shared dependency.

## Watchdog Next 2026-05-25 SQL Merge Rollback Visibility Slice

- Upstream evidence reused: the same `dsess/transactions.go` transaction boundary and focused `CALL DOLT_MERGE` autocommit-off evidence distinguish rollback cleanup from queryable unresolved conflict state. No wider upstream runner was executed in this isolated worktree.
- Native delta: `MergeStatusTable::mergeRollbackState()` now projects the SQL-visible state after unresolved merge conflicts: autocommit rollback returns the upstream rollback error with inactive merge status and empty conflict/guidance rows, while autocommit-disabled unresolved conflicts retain `dolt_merge_status`, `dolt_conflicts`, status guidance, commit guidance, and failure summary rows for resolution.
- Focused evidence: `php tools/run-tests.php lanes/dolt/tests/MergeStatusTableTest.php` passed with 1 file, 181 assertions, and 0 failures.
- Example smoke: `php -r '$r=require "lanes/dolt/examples/wordpress-merge-status-review.php"; echo ($r["sqlRollbackState"]["rolled_back"] ? "rolled-back" : "queryable")."\n"; echo ($r["sqlRollbackState"]["merge_status"]["is_merging"] ? "merging" : "inactive")."\n"; echo count($r["sqlRollbackState"]["conflict_rows"])."\n"; echo count($r["sqlQueryableConflictState"]["conflict_rows"])."\n";'` returned `rolled-back`, `inactive`, `0`, and `4`.
- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses the existing bounded PHP merge/status/procedure projection surface and static upstream transaction evidence, with no shell-outs and no activation of a shared dependency.
