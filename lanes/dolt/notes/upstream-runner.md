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
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats diff.bats rename-tables.bats primary-key-changes.bats`
  - Result: `1..108`, exit 0; all 107 runnable tests passed and 1 upstream-declared skip remained for `rename-tables: merge a renamed table`.

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
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json -count=1 -timeout 10m`
  - Result: all 10 diff/schema/table packages passed.
- `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions -count=1 -timeout 5m`
  - Result: `dtables` compiled with no test files; `ok github.com/dolthub/dolt/go/libraries/doltcore/sqle/dtablefunctions 0.043s`.

## Failed Then Resolved Upstream Command

- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats diff.bats rename-tables.bats primary-key-changes.bats`
  - Initial result: failed at `diff: --system preserves dolt_show_system_tables value in sql-shell` because the upstream `.expect` helper required the missing local `expect` package.
  - Remediation: `sudo -n dnf install -y expect`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 5m bats --filter 'diff: --system preserves dolt_show_system_tables value in sql-shell' diff.bats`
  - Result after installing `expect`: `1..1`, `ok 1 diff: --system preserves dolt_show_system_tables value in sql-shell`.
- `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home BATS_TMPDIR=/home/claude/port-libs/.upstream-cache/dolt/bats-tmp PATH=/home/claude/port-libs/.upstream-cache/dolt/bats-home/go/bin:$PATH timeout 20m bats diff.bats rename-tables.bats primary-key-changes.bats`
  - Clean rerun result: `1..108`, exit 0; all 107 runnable tests passed and 1 upstream-declared skip remained for `rename-tables: merge a renamed table`.

## Repository Check

- `php tools/run-tests.php`
  - Result after this where/limit slice: pass.
  - Summary: 67 test files, 3,708 assertions, 0 failures.
  - Dolt lane tests reached by the root runner all passed, including the 26 current Dolt behavior tests and 118 Dolt assertions.

## Skipped Suites

- Full `go test ./...`: skipped as too broad for this runner slice because it hydrates and compiles the full Dolt workspace and broad dependency graph.
- Full BATS directory: skipped because upstream BATS includes Python package requirements, parquet/Hadoop tooling, server tests, compatibility tests, client integration tests, and other live-service style coverage.
- MySQL-server, cloud, Hadoop/parquet, benchmark, and remote-service suites: intentionally skipped per runner boundary.

## Remaining Runner Boundary

- This is bounded upstream evidence, not full upstream parity.
- The cache has build/test artifacts under `.upstream-cache/dolt/.gomodcache`, `.upstream-cache/dolt/.gocache`, and `.upstream-cache/dolt/bats-home`.
- Runner metadata is part of the current Dolt lane batch with the skinny projection and where/limit filtering implementation.
