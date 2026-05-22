# Dolt Upstream Runner Evidence

- Date: 2026-05-22 UTC
- Upstream: `dolthub/dolt`
- Commit: `b2274926e0dcd84aab000ee242df5b5e75689eef`
- Cache used by this runner: `.upstream-cache/dolt`

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
  - `integration-tests/bats/log.bats`: `log: --graph: graph with multiple branches`, covering four side branches merged into main and default graph metadata rows.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 10m bats --filter 'log: --graph: graph with multiple branches' log.bats`
  - Result: `1..1`, exit 0; the focused dense multi-branch graph BATS case passed.
- Native Dolt PHP lane rerun after this slice passed with 10 test files, 97 behavior tests, 521 assertions, and 0 failures.
- Required root `php tools/run-tests.php` passed after this slice with 126 test files, 11,197 assertions, and 0 failures.

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

- `php tools/run-tests.php`
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
  - Dolt lane tests reached by the root runner passed throughout, including `DOLT_COMMIT_DIFF` required-filter/range-predicate behavior, `dolt_merge_status`, `dolt_conflicts`, `dolt_history_dolt_schemas`, `dolt_diff_dolt_schemas`, `dolt_history_dolt_procedures`, and `dolt_diff_dolt_procedures` projection tests.
  - The latest root runner additionally covers native `dolt_log`/`dolt_commits`, native `dolt_commit_ancestors`, native `has_ancestor`, native branch table/activity projection, and the WordPress commit-log, fan-in commit-graph, commit-ancestors, has-ancestor, and branch-review fixtures.
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
  - Current result after native dense multi-branch `dolt log --graph` default rendering: pass with 10 Dolt test files, 97 behavior tests, 521 assertions, and 0 failures.

## Skipped Suites

- Full `go test ./...`: skipped as too broad for this runner slice because it hydrates and compiles the full Dolt workspace and broad dependency graph.
- Full BATS directory: skipped even after the combined 319-plan local BATS diff/schema/merge/conflict/log/status pass because the remaining upstream BATS coverage includes Python package requirements, parquet/Hadoop tooling, server tests, compatibility tests, client integration tests, and other live-service style coverage.
- MySQL-server, cloud, Hadoop/parquet, benchmark, and remote-service suites: intentionally skipped per runner boundary.

## Remaining Runner Boundary

- This is bounded upstream evidence, not full upstream parity.
- The cache has build/test artifacts under `.upstream-cache/dolt/.gomodcache`, `.upstream-cache/dolt/.gocache`, and `.upstream-cache/dolt/bats-home`.
- The pristine upstream `status.bats` helper still fails on fixed-width commit-hash extraction; the runner-local copied `status-local-fixed.bats` file resolves that helper boundary and lets the full local status suite pass, but it is documented as a patched-copy runner aid rather than pristine upstream pass parity.
- Runner metadata is part of the current Dolt lane batch with the skinny projection, where/limit filtering, summary/stat primary-key warning/error boundaries, dolt_ignore implementation evidence, schema-history/schema-diff evidence, procedure-history/procedure-diff evidence, commit-diff/log/commit-ancestors/has_ancestor/branch evidence, focused branch Go engine evidence, and combined local upstream diff/schema/merge/log/status BATS evidence.
