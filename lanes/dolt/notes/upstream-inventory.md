# dolt Upstream Test Inventory

- Upstream: `dolthub/dolt`
- Commit: `b2274926e0dcd84aab000ee242df5b5e75689eef`
- License: Apache-2.0
- Cache: `.upstream-cache/dolt`
- Method: shallow blob-filtered clone with `--filter=blob:none --depth=1 --no-checkout`; counted paths with `git ls-tree -r --name-only HEAD` and hydrated targeted metadata, BATS README, license, diff/schema/table-delta source/test files, then sparse checkout entries `go` and `integration-tests/bats` in `.upstream-cache/dolt` for the bounded upstream package and BATS runner.

## Counted Static Denominator

- Repository paths: 2,387
- Executable upstream test files: 613
- Go `_test.go` files: 399
- BATS files: 214
- BATS `@test` cases counted from targeted BATS hydration before stopping broader Go entry-point hydration: 3,808
- Test-related paths by static pattern: 701
- `testdata/` paths: 57
- Fixture/data artifacts by path (`testdata`, `fixtures`, `.expect`, `.sql`, `.yaml`, `.json`, `.csv`, `.txt`): 256

## Test File Breakdown

Go `_test.go` file concentrations:

- `go/store`: 147
- `go/libraries/doltcore/sqle`: 60
- `go/cmd`: 23
- `integration-tests/go-sql-server-driver`: 21
- `go/performance`: 15
- `go/libraries/doltcore/table`: 15
- `go/libraries/doltcore/schema`: 12
- `go/libraries/doltcore/remotestorage`: 12
- Other Go packages: 94

BATS file concentrations:

- `integration-tests/bats`: 201
- `integration-tests/compatibility`: 7
- `integration-tests/mysql-client-tests`: 2
- `integration-tests/data-dump-loading-tests`: 2
- `integration-tests/orm-tests`: 1
- `go/libraries`: 1

## Focused Schema/Table-Delta Inventory

Targeted upstream files inspected for the current native slice:

- `go/libraries/doltcore/diff/table_deltas.go`
- `go/libraries/doltcore/diff/table_deltas_test.go`: 1 `Test*` function.
- `go/libraries/doltcore/diff/schema_diff.go`
- `go/libraries/doltcore/diff/schema_diff_test.go`: 1 `Test*` function.
- `go/libraries/doltcore/schema/schema.go`
- `go/libraries/doltcore/schema/schema_test.go`: 9 `Test*` functions, including primary-key diffability coverage.
- `go/libraries/doltcore/sqle/dtables/diff_table.go`: diff schema expansion and primary-key warning paths.
- `go/libraries/doltcore/sqle/dtables/diff_iter.go`: target-schema row projection and `to_*` / `from_*` field order.
- `go/libraries/doltcore/sqle/dtables/prolly_row_conv.go`: row conversion through `MapSchemaBasedOnTagAndName`, target type coercion, and warning behavior.
- `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: 22 schema-aware diff ScriptTest names covering column drop/recreate/rename, coercion warnings, and primary-key-change warnings.
- `go/libraries/doltcore/sqle/dtablefunctions/dolt_diff.go`: `filterDeltaSchemaToSkinnyCols` behavior for same-name/same-type non-PK column elision, row-set mismatch fallback, and `--include-cols`.
- `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: the focused `dolt_diff: SELECT * skinny schema visibility` ScriptTest includes direct skinny row assertions plus view column-count assertions for `--skinny` and `--include-cols`.
- `go/libraries/doltcore/sqle/dtablefunctions/dolt_diff.go`: indexed `LookupPartitions` path for table-function predicates over projected `to_*` and `from_*` primary-key columns.
- `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: DiffTableFunction predicate assertions cover `to_pk` / `from_pk` equality, `IS NULL`, `IS NOT NULL`, and compound primary-key range filters.
- `go/libraries/doltcore/sqle/dtablefunctions/dolt_diff_summary.go`: `dolt_diff_summary()` row schema, argument boundary, table-name filtering, primary-key-change skip/error behavior, ignore-pattern boundary, and `getRowFromSummary` projection.
- `go/libraries/doltcore/sqle/dtablefunctions/dolt_diff_stat.go`: `dolt_diff_stat()` row schema, keyed/keyless row projection, `getRowFromDiffStat`, and `GetCellsAddedAndDeleted` arithmetic.
- `go/libraries/doltcore/doltdb/ignore.go`: `ShouldIgnoreDelta` applies ignore patterns only to added and dropped tables; changed tracked tables remain visible.
- `go/libraries/doltcore/doltdb/table_name_patterns.go`: dolt_ignore pattern matching for exact names, `*`, `%`, and `?`, plus more-specific pattern matching for true/false override resolution.
- `go/libraries/doltcore/doltdb/errors.go`: `DoltIgnoreConflictError` message formatting with `ignored:` and `not ignored:` pattern lines.
- `go/libraries/doltcore/sqle/enginetest/dolt_queries.go`: `dolt_status_ignored with conflicting patterns` covers the more-specific false-pattern override boundary.
- `go/libraries/doltcore/sqle/enginetest/dolt_queries_diff.go`: focused DiffSummaryTableFunction and DiffStatTableFunction script tests covering empty table additions, data additions, modifications, reversals, drops, multi-table rows, keyless rows, primary-key-change boundaries, and six `dolt_diff_summary respects dolt_ignore` cases.
- `go/libraries/doltcore/merge`: local merge package tests covering data merges, schema merges, row merges, keyless merges, merge conflicts, and merge commits.
- `go/libraries/doltcore/sqle/integration_test`: focused schema/procedure/history diff table tests.
- `go/libraries/doltcore/diff/column_identity_test.go`: 2 `Test*` functions, read as background for conservative table/column matching semantics.
- `integration-tests/bats/diff.bats`: focused `diff: with where clause`, `diff: with where clause errors`, and `diff: with limit` cases map CLI filtering and limit behavior.
- `integration-tests/bats/rename-tables.bats`: 5 BATS cases.
- `integration-tests/bats/primary-key-changes.bats`: 40 BATS cases.
- `integration-tests/bats/merge.bats`: local merge behavior across table add/delete/edit, schema edits, table renames, conflicts, and merge stats.
- `integration-tests/bats/schema-conflicts.bats`: schema conflict system-table and SQL/CLI merge smoke coverage.
- `integration-tests/bats/conflict-detection.bats`: local data/schema conflict detection coverage.
- `integration-tests/bats/sql-commit-diff.bats`: `DOLT_COMMIT_DIFF` range predicate coverage over `to_` and `from_` keys.
- `integration-tests/bats/log.bats`: local `dolt log` coverage for branch/range selection, table filters, merge parents, graph output, decoration, stat output, pager behavior, and `--all` traversal.
- `go/libraries/doltcore/sqle/dtables/commit_diff_table.go`: `DOLT_COMMIT_DIFF_<table>` required predicate errors for exactly one `to_commit` and `from_commit`, commit-root resolution, target schema reuse, and projected range lookup boundaries.
- `go/libraries/doltcore/sqle/dtables/log_table.go`: `dolt_log` fixed row schema, `commit_order` height, projected-column opt-in behavior for `parents` and `signature`, short/full/no refs decoration, HEAD prefixing, and reachable-head system-table scope.
- `go/libraries/doltcore/sqle/dtables/commits_table.go`: `dolt_commits` all-branch metadata row schema without log-only parents/refs/signature/order columns.
- `go/libraries/doltcore/sqle/dtables/commit_ancestors_table.go`: `dolt_commit_ancestors` row shape (`commit_hash`, `parent_hash`, `parent_index`), root commit null-parent row, `ResolveAllParents` parent-index ordering, and commit-hash point lookup partitions.
- `go/libraries/doltcore/sqle/enginetest/dolt_queries.go`: focused HistorySystemTable assertions for sorting `dolt_commit_ancestors`, commit_hash-filtered merge-parent rows, and joining parent hashes back to `dolt_log` messages.
- `go/libraries/doltcore/sqle/dtables/branches_table.go`: `dolt_branches` / `dolt_remote_branches` schema, latest commit metadata columns, tracking remote/branch fields, dirty bit, remote-name prefixing, and name-index range filtering.
- `go/libraries/doltcore/sqle/dtables/branch_activity_table.go`: `dolt_branch_activity` row schema, tracking-enabled error boundary, current-branch filtering, nullable last-read/write times, active session counts, and system-start time.
- `go/libraries/doltcore/sqle/dfunctions/active_branch.go`: `active_branch()` current branch resolution, detached-head null handling, non-Dolt database null handling, and case-insensitive branch ref matching.
- `go/libraries/doltcore/doltdb/branch_activity.go`: branch activity tracker behavior, read/write event recording, HEAD/stat/event-session ignore rules, current-branch filtering, and sorted branch rows.
- `go/libraries/doltcore/sqle/enginetest/branch_activity_queries.go`: focused branch activity ScriptTests for all branches, branch creation write-only rows, AS OF read updates, checkout read activity, deleted branch filtering, cross-branch writes, and disabled tracking errors.
- `integration-tests/bats/sql-branch.bats` and `integration-tests/bats/branch-activity.bats`: BATS coverage for `dolt_branches` inserts/copies/tracking metadata and SQL-server branch activity behavior.
- `go/libraries/doltcore/sqle/dfunctions/has_ancestor.go`: `has_ancestor(reference, ancestor)` argument/type boundary, ref resolution, self-ancestor check, and commit-closure membership check.
- `go/libraries/doltcore/doltdb/ancestor_spec.go`: `^`, `^N`, and `~N` ancestor suffix parsing and splitting from a commit spec.
- `go/libraries/doltcore/doltdb/ancestor_spec_test.go`: 2 focused `Test*` functions for instruction parsing and commit-spec splitting.
- `go/libraries/doltcore/sqle/enginetest/dolt_queries.go`: focused `test has_ancestor` ScriptTest covering branch heads, current `HEAD`, commit hashes, tags, merge parents, and branch-scoped log visibility.
- `go/libraries/doltcore/sqle/dtablefunctions/dolt_log.go`: `dolt_log()` revision range, `--not`, `--parents`, `--show-signature`, `--decorate`, and table-filter behavior.
- `go/libraries/doltcore/sqle/dtablefunctions/dolt_log_test.go`: focused bind-variable, type-validation, fixed-schema, and `--parents` option tests.
- `integration-tests/bats/status.bats`: focused status coverage for conflict tables, renamed tables, and reset with a renamed table; pristine full file exposed one stale fixed-width commit-hash helper.
- `.upstream-cache/dolt/integration-tests/bats/status-local-fixed.bats`: runner-local copy of `status.bats` with only `get_head_commit()` changed from fixed-width `cut -c 13-44` to full-hash `awk` extraction; used to prove the underlying status/reset behavior after documenting the pristine helper failure.
- `go/libraries/doltcore/sqle/dtables/status_table.go`: `dolt_status` row shape (`table_name`, staged byte, status), staged/unstaged table-delta collection, merge/schema/data conflict rows, constraint violation rows, merged rows, and status strings.
- `go/libraries/doltcore/sqle/dtables/status_ignored_table.go`: `dolt_status_ignored` row shape plus the rule that only unstaged new tables are evaluated against ignore patterns; conflicting ignore rules leave the row visible.
- `go/libraries/doltcore/sqle/dtables/merge_status_table.go`: `dolt_merge_status` row shape (`is_merging`, `source`, `source_commit`, `target`, `unmerged_tables`), inactive null metadata, active source/target metadata, and unmerged data/constraint/schema table aggregation.
- `go/libraries/doltcore/sqle/dtables/table_of_tables_in_conflict.go`: `dolt_conflicts` table-of-tables row shape (`table`, `num_conflicts`), data/schema conflict table-name set construction, and root-object conflict rows.
- `integration-tests/bats/sql-status.bats`: direct SQL assertions for staged new tables, unstaged renames, staged+unstaged duplicate rows, and conflict rows in `dolt_status`.
- `go/libraries/doltcore/sqle/dolt_schemas_history_table.go`: `dolt_history_dolt_schemas` schema, primary key, commit iterator, and commit metadata appended to every `dolt_schemas` row.
- `go/libraries/doltcore/sqle/dolt_schemas_diff_table.go`: `dolt_diff_dolt_schemas` `to_*` / `from_*` schema-object row shape, `EMPTY` initial comparisons, `WORKING` comparisons, case-insensitive `type:name` keys, and diff-type projection.
- `go/libraries/doltcore/sqle/integration_test/dolt_schemas_history_diff_test.go`: focused `TestDoltSchemasHistoryTable` and `TestDoltSchemasDiffTable` assertions for counts, required columns, type/name filters, working changes, added/modified/removed rows, and commit metadata.
- `go/libraries/doltcore/sqle/dolt_procedures_history_table.go`: `dolt_history_dolt_procedures` schema, primary key, commit iterator, and commit metadata appended to every `dolt_procedures` row.
- `go/libraries/doltcore/sqle/dolt_procedures_diff_table.go`: `dolt_diff_dolt_procedures` `to_*` / `from_*` procedure row shape, `EMPTY` initial comparisons, `WORKING` comparisons, case-insensitive procedure-name keys, and diff-type projection.
- `go/libraries/doltcore/sqle/integration_test/dolt_procedures_history_test.go`: focused `TestDoltProceduresHistoryTable` assertions for required columns, history counts, per-procedure filters, latest commit names, create statements, and timestamps.
- `go/libraries/doltcore/sqle/integration_test/dolt_schemas_history_diff_test.go`: adjacent `TestDoltProceduresDiffTable` assertions for required columns, complete history counts, working changes, added/modified/removed procedure rows, definition capture, and from/to name projection.
- `go/libraries/doltcore/doltdb/system_table.go`: `EMPTY`, `WORKING`, `added`, `modified`, and `removed` constants used by schema-object diff rows.

## Runner Status

The full upstream runners were not executed for this lane slice, but bounded upstream Go package, Go engine, Dolt CLI build, and local BATS diff/rename/primary-key/diff-stat/query-diff/schema-change/column-tag/sql-diff/merge/conflict/status/log/commit-ancestor/has_ancestor/branch runners were executed after installing directly relevant tooling.

- Tooling probes now return `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, and `expect version 5.45.4`.
- Installed/probed commands used in this lane: `sudo -n dnf install -y golang bats` and `sudo -n dnf install -y expect`; resulting direct versions include `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64`.
- Fresh prerequisite check `sudo -n dnf install -y golang bats expect` found those packages already installed and returned `Nothing to do.`
- `libicu-devel-77.1-2.fc44.x86_64` is present on the host and satisfies Dolt's `github.com/dolthub/go-icu-regex` compile dependency.
- Sparse checkout state in `.upstream-cache/dolt`: `go` and `integration-tests/bats`; the cache remains a `blob:none` partial clone at `b2274926e0dcd84aab000ee242df5b5e75689eef`.
- Current cache inspection before this runner extension found the hydrated directories present, plus internal upstream-cache index noise: `git -C .upstream-cache/dolt status --short --branch` still reports staged deletions for out-of-cone paths from the earlier no-checkout/sparse hydration and untracked build caches. `git -C .upstream-cache/dolt sparse-checkout reapply` was attempted and did not clear that state; no delete or reset was run.
- Fresh has_ancestor runner refresh:
  - `env GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 5m go test ./libraries/doltcore/doltdb -run 'Test(ParseInstructions|SplitAncestorSpec)$' -count=1 -timeout 5m`: pass in `0.045s`.
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 10m go test ./libraries/doltcore/sqle/enginetest -run 'TestDoltScripts/test has_ancestor$' -count=1 -timeout 10m`: pass in `0.395s`.
- Fresh branch table/activity runner refresh:
  - `env HOME=/home/claude/port-libs/.upstream-cache/dolt/bats-home GOMODCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gomodcache GOCACHE=/home/claude/port-libs/.upstream-cache/dolt/.gocache timeout 20m go test ./libraries/doltcore/sqle/enginetest -run 'Test(DoltBranchesSystemTable|DoltBranchesSystemTablePrepared|BranchActivity)$' -count=1 -timeout 20m`: pass in `10.400s`.
- Bounded Go runner results:
  - `go test ./libraries/doltcore/diff -count=1 -timeout 5m`: pass.
  - `go test ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json -count=1 -timeout 5m`: pass.
  - `go test ./libraries/doltcore/sqle/enginetest -run 'TestDiffTableFunction/dolt_diff: SELECT \* skinny schema visibility' -count=1 -timeout 8m`: pass.
  - From `.upstream-cache/dolt-runner/go`, `timeout 180s go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/sqle/dtables`: diff and schema pass; `sqle/dtables` compiles and reports no test files.
  - `go test ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding -count=1 -timeout 5m`: pass.
  - `go test ./libraries/doltcore/sqle/enginetest -run 'Test(DiffTableFunction|DiffTableFunctionPrepared|DiffSummaryTableFunction|DiffSummaryTableFunctionPrepared|SchemaDiffTableFunction|SchemaDiffTableFunctionPrepared|ColumnDiffSystemTable|ColumnDiffSystemTablePrepared|DiffSystemTable|DiffSystemTablePrepared|UnscopedDiffSystemTable|UnscopedDiffSystemTablePrepared)$' -count=1 -timeout 20m`: pass.
  - `go test ./libraries/doltcore/sqle/enginetest -run 'Test(DiffStatTableFunction|DiffSummaryTableFunction)$' -count=1 -timeout 10m`: pass; fresh rerun for this summary/stat slice completed in `0.620s`.
  - `go test ./libraries/doltcore/sqle/enginetest -run TestDiffSummaryTableFunction -count=1 -timeout 10m`: pass; fresh rerun for the ignore-summary slice completed in `0.674s`.
  - `go test ./libraries/doltcore/sqle/enginetest -run 'TestDoltScripts/dolt_status_ignored with conflicting patterns' -count=1 -timeout 5m`: pass; fresh focused conflicting-pattern subtest completed in `0.340s`.
  - `go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json -count=1 -timeout 10m`: pass.
  - `go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval -count=1 -timeout 10m`: pass for the 12 packages with test files; `rowconv` compiles and reports no test files.
  - `go test ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions -count=1 -timeout 5m`: `dtablefunctions` pass; `dtables` compiles and reports no test files.
  - `go test ./libraries/doltcore/sqle/integration_test -run 'TestDoltSchemas(History|Diff)Table$' -count=1 -timeout 10m`: pass; fresh schema-history/schema-diff integration rerun completed in `0.138s`.
  - `go test ./libraries/doltcore/sqle/integration_test -run 'TestDoltProcedures(History|Diff)Table$' -count=1 -timeout 10m`: pass; fresh procedure-history/procedure-diff integration rerun completed in `0.152s`.
- Cache-local Dolt utility build: `go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv` with `GOBIN=.upstream-cache/dolt/bats-home/go/bin`; `dolt version` reports `dolt version 2.0.5`.
- Bounded BATS runner results from `integration-tests/bats/diff.bats`: `diff: clean working set`, `diff: data diff only`, `diff: schema changes only`, `diff: with limit`, `diff: allowed across primary key renames`, `diff: --filter=renamed filters to only renamed tables`, `diff: row, line, in-place, context diff modes`, `diff: with where clause`, and `diff: with where clause errors` all pass.
- Bounded BATS runner results from `integration-tests/bats/rename-tables.bats`: `rename a table with sql`, `diff a renamed table`, and `sql diff a renamed table` all pass.
- Bounded BATS runner results from `integration-tests/bats/primary-key-changes.bats`: `diff on primary key schema change shows schema level diff but does not show row level diff`, `dolt diff table returns top-down diff until schema change`, and `same primary key set in different order is detected and blocked on merge` all pass.
- Full local three-file BATS slice `bats diff.bats rename-tables.bats primary-key-changes.bats` initially failed at `diff: --system preserves dolt_show_system_tables value in sql-shell` because upstream `diff-system.expect` required missing `expect`. After `sudo -n dnf install -y expect`, the focused sql-shell case passed and the clean three-file rerun passed with `1..108`: 107 runnable tests passed and 1 upstream-declared skip remained for `rename-tables: merge a renamed table`.
- Expanded local eight-file BATS slice `bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats` passed with `1..179`: 177 runnable tests passed and 2 upstream-declared skips remained for `rename-tables: merge a renamed table` and `sql-diff: sql diff ignores dolt docs`.
- Fresh runner refresh rebuilt cache-local `dolt`, `noms`, and `remotesrv`, confirmed `dolt version 2.0.5`, reran the expanded diff/schema/table/rowconv/sqlfmt/expreval Go slice, `sqle/dtables` plus `sqle/dtablefunctions`, the focused `sqle/enginetest` diff/schema/system table-function group, and the expanded eight-file local BATS slice; all reruns passed with two upstream-declared BATS skips.
- Merge/status runner extension confirmed the cache was still a `blob:none` sparse checkout with `go` and `integration-tests/bats`, rechecked `golang`, `bats`, and `expect`, rebuilt cache-local `dolt`, `noms`, and `remotesrv`, and confirmed `dolt version 2.0.5`.
- `go test ./libraries/doltcore/merge -count=1 -timeout 10m`: pass in `6.268s`.
- `go test ./libraries/doltcore/sqle/integration_test -run 'Test(DoltSchemasHistoryTable|DoltSchemasDiffTable|DoltProceduresDiffTable|HistoryTable)$' -count=1 -timeout 5m`: pass in `0.189s`.
- Broad local 13-file BATS attempt `bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats status.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats` exited 1 with `1..277` because `status: dolt reset works with commit hash ref` failed; the status helper uses fixed-width `cut -c 13-44` on `dolt log`, which returned a truncated commit-hash suffix with current output.
- Clean local merge/conflict BATS extension `bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats` passed with `1..74`: 64 runnable tests passed and 10 upstream-declared skips remained.
- Focused status BATS rerun `bats --filter 'status: (tables in conflict|renamed table|dolt reset with a renamed table)' status.bats` passed with `1..3`.
- Native status-table source inventory for this slice inspected `status_table.go`, `status_ignored_table.go`, `merge_status_table.go`, `table_of_tables_in_conflict.go`, `sql-status.bats`, and `status.bats`.
- Fresh combined runner refresh confirmed `.upstream-cache/dolt` still at `b2274926e0dcd84aab000ee242df5b5e75689eef` as a shallow `blob:none` sparse checkout for `go` and `integration-tests/bats`, with the known sparse/no-checkout index deletions left untouched.
- Fresh combined Go runner `go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json ./libraries/doltcore/rowconv ./libraries/doltcore/sqle/sqlfmt ./libraries/doltcore/sqle/expreval ./libraries/doltcore/sqle/dtables ./libraries/doltcore/sqle/dtablefunctions ./libraries/doltcore/merge -count=1 -timeout 15m` passed: 14 packages passed, and `rowconv` plus `sqle/dtables` compiled with no test files.
- Fresh focused sqle runners passed: `sqle/enginetest` diff/schema/system table-function group including `DiffStatTableFunctionPrepared` in `3.825s`, and `sqle/integration_test` schema/procedure/history diff tests in `0.220s`.
- Fresh focused procedure runner passed: `sqle/integration_test -run 'TestDoltProcedures(History|Diff)Table$'` in `0.152s`.
- Fresh focused commit-diff/log engine runner passed: `sqle/enginetest -run 'Test(CommitDiffSystemTable|CommitDiffSystemTablePrepared|LogTableFunction|LogTableFunctionPrepared)$'` in `0.987s`.
- Fresh focused status/conflict Go runners passed: `sqle/enginetest -run 'TestDoltScripts/dolt_status_ignored'` in `0.346s`, `sqle/enginetest -run 'TestDoltDTableScripts(Prepared)?$'` in `0.175s`, and `sqle/enginetest -run 'TestDoltConflictsTableNameTable$'` in `0.363s`.
- Fresh combined local BATS extension `bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats` passed with `1..253`: 238 runnable tests passed and 15 upstream-declared skips remained.
- Fresh focused status BATS rerun passed again with `1..3`.
- Fresh standalone `bats sql-commit-diff.bats` rerun passed with `1..2`, covering `DOLT_COMMIT_DIFF` range predicates over `to_` and `from_` primary-key columns and compound primary-key plan boundaries.
- Fresh local `bats sql-commit-diff.bats log.bats` runner passed with `1..37`: 2 commit-diff tests and 35 log tests passed.
- Fresh pristine one-test `status.bats` rerun still failed with `1..1` because `get_head_commit()` truncated `nadnnhmv0m5703n4pch0qqddolkkg7kp` to `hmv0m5703n4pch0qqddolkkg7kp`.
- Fresh runner-local patched-copy status evidence passed: `bats --filter 'status: dolt reset works with commit hash ref' status-local-fixed.bats` passed with `1..1`; `bats status-local-fixed.bats sql-status.bats` passed with `1..31` (30 runnable passes and 1 upstream-declared skip); and `bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats` passed with `1..68` (67 runnable passes and 1 upstream-declared skip).
- Latest consolidated runner refresh rechecked `sudo -n dnf install -y golang bats expect` (`Nothing to do`), rebuilt cache-local `dolt`, `noms`, and `remotesrv`, confirmed `dolt version 2.0.5`, reran the 16-package Go batch, focused diff/schema/system enginetest group, schema/procedure/history integration group, commit-diff/log/status/conflict engine group, `dolt_status_ignored`, `TestDoltLog`, and focused HistorySystemTable commit-ancestor subtests; all passed.
- Latest pristine one-test `status.bats` repro still failed with `1..1` because `get_head_commit()` truncated `61t2d1teve5iahijb5ptk8bn17ih9uc8` to `1teve5iahijb5ptk8bn17ih9uc8`.
- Latest combined local BATS pass `bats diff.bats rename-tables.bats primary-key-changes.bats diff-stat.bats query-diff.bats schema-changes.bats column_tags.bats sql-diff.bats merge.bats schema-conflicts.bats conflict-detection.bats sql-commit-diff.bats log.bats status-local-fixed.bats sql-status.bats` exited 0 with `1..319`: 303 runnable tests passed and 16 upstream-declared skips remained.
- Full `go test ./...` was not run because it would hydrate and compile the full Dolt workspace and broad dependency graph beyond this lane slice.
- Full BATS directory was not run because upstream BATS also runs Python/parquet/Hadoop/server/compatibility/client integration dependencies.
- Live-service, MySQL-server, cloud, Hadoop/parquet, and benchmark suites were intentionally skipped.

This denominator is therefore a cloned static inventory with bounded upstream Go/BATS evidence for the current slice, not full upstream pass parity.

## Native PHP Mapping Added

The current PHP slice maps focused row-diff semantics from upstream `DOLT_DIFF_*` / `dolt_diff()` behavior:

- Changed rows project into `to_*` columns, `to_commit`, `to_commit_date`, `from_*` columns, `from_commit`, `from_commit_date`, then `diff_type`.
- `diff_type` values are `added`, `removed`, and `modified`.
- Added rows have null `from_*` values; removed rows have null `to_*` values; modified rows include both sides.
- Primary-key indexing supports composite keys without string-collision overwrites and rejects missing, null, and duplicate keys.
- Schema column diffs pair columns by stable Dolt tags and classify unchanged, added, removed, and modified columns across name, type, primary-key, and constraint changes.
- Primary-key set diffability follows Dolt's tag/order/type semantics: same-tag PK renames are diffable, extra non-PK columns do not matter, changed PK tags/types and reordered compound PKs are not diffable.
- Table delta summaries match exact names first, filter unchanged exact-name tables, then classify table renames only when schemas overlap by unchanged column tag and name; changed column names with the same tag are intentionally not enough for rename matching.
- Row-level change types map into Dolt table-level filter names: `added`, `dropped`, and `modified`.
- Schema-aware row projection converts stored rows into target diff schemas before emitting `to_*` and `from_*` fields.
- Non-primary-key row conversion maps by column name rather than tag, so drop/recreate and rename cases follow the upstream `ProllyRowConverter` boundary.
- Integer-to-varchar target conversion emits string values; uncoercible varchar-to-int target conversion emits Dolt warning code 1105 and projects null values.
- Primary-key set changes emit Dolt's primary-key warning and stop non-fuzzy row rendering.
- Skinny diff projection removes unchanged same-name, same-type non-PK columns across aligned rows, keeps `--include-cols` columns, keeps added columns, and falls back to full row shape when rows are deleted.
- Projected diff row filtering maps focused `--where` / `dolt_diff()` semantics: prefixed `to_*` and `from_*` columns are filterable, unprefixed base-table columns are rejected, simple comparison/null predicates can be combined with `AND` and `OR`, and limits are applied after filtering.
- `dolt_diff_summary()` rows now project upstream's five table-function columns with empty strings for missing from/to table names, table-name filtering, and data/schema-change booleans over the existing table-delta matcher.
- `dolt_diff_summary()` rows can apply working-set `dolt_ignore` patterns to added and dropped table summaries while leaving modified and renamed tracked tables visible.
- Table-specific `dolt_diff_summary()` primary-key set changes throw upstream-shaped errors, while unscoped summary scans skip the table and emit warning code 1105.
- Table-specific `dolt_diff_stat()` primary-key set changes throw upstream-shaped errors, while unscoped stat scans emit a zero-count row and the upstream stat warning text.
- Native dolt_ignore pattern matching supports exact names, `*`, `%`, and `?` wildcards, and more-specific false-pattern overrides such as `temp_* = true` plus `temp_important = false`.
- Native dolt_ignore conflict errors include upstream-shaped `ignored:` and `not ignored:` pattern lines for ambiguous true/false matches and equivalent normalized wildcard patterns.
- More-specific true ignore patterns can override broader false patterns, mirroring upstream's specificity rule in the opposite direction from the existing false-override case.
- Commit-to-commit summary rows remain visible when no working/staged ignore patterns are supplied, matching upstream's committed-history boundary.
- `dolt_diff_stat()` rows now compute keyed row counts, unmodified rows, cell insert/delete counts via upstream `GetCellsAddedAndDeleted` arithmetic, modified-cell counts, table add/drop rows, and keyless table insert/delete-only rows.
- `dolt_status` rows now project staged and unstaged table-delta rows as `new table`, `deleted`, `renamed`, and `modified`, with integer-like staged flags and `old -> new` names for renames.
- `dolt_status_ignored` rows mark only unstaged new tables ignored; staged new tables and tracked modified tables stay visible even when names match ignore patterns.
- Native status rows include upstream labels for conflict, schema conflict, constraint violation, and merged tables.
- `dolt_merge_status` rows now project the upstream single-row table shape with null source/target metadata when no merge is active and source branch/spec, source commit, target ref, and comma-separated unmerged tables when active.
- Active merge status includes data-conflict, constraint-violation, and schema-conflict tables in `unmerged_tables`, matching upstream's merge-status aggregation boundary.
- `dolt_conflicts` rows now project upstream's table-of-tables shape with `table` and `num_conflicts`, de-duplicating data/schema table conflicts before appending root-object conflicts.
- `dolt_history_dolt_schemas` rows now append commit hash, committer, and commit date metadata to every versioned schema object row.
- `dolt_diff_dolt_schemas` rows now compare schema objects by case-insensitive `type:name` keys and emit upstream-shaped initial `EMPTY`, commit-to-parent, and optional `WORKING` diff rows.
- Schema-object diff equality compares rendered base fields, including JSON-like `extra` values, so changed definitions project as `modified` rather than add/drop churn.
- `dolt_history_dolt_procedures` rows now append commit hash, committer, and commit date metadata to every versioned procedure row.
- `dolt_diff_dolt_procedures` rows now compare procedures by case-insensitive name keys and emit upstream-shaped initial `EMPTY`, commit-to-parent, and optional `WORKING` diff rows.
- Procedure diff equality compares rendered `name`, `create_stmt`, `created_at`, `modified_at`, and `sql_mode` values, so changed definitions or timestamps project as `modified` rather than add/drop churn.
- `DOLT_COMMIT_DIFF_<table>` rows now require one `to_commit` and one `from_commit` before projecting row diffs, mirroring upstream's required predicate boundary.
- Commit-diff snapshots reuse the Dolt-shaped `to_*` / `from_*` row projection with commit names and commit dates from the selected snapshots.
- Commit-diff rows apply focused `to_*` and `from_*` primary-key predicates after projection, including range predicates and compound primary-key equality predicates from `sql-commit-diff.bats`.
- Commit-diff validation rejects missing, duplicate, or non-string commit filters and missing commit snapshots instead of silently comparing the wrong rows.
- Native `dolt_log` rows now expose upstream's fixed metadata schema with `commit_hash`, committer/email/date/message, computed `commit_order`, nullable opt-in `parents`, refs, nullable opt-in `signature`, and author metadata.
- Native log rows populate `parents` only when requested by projected columns or explicit show flags, keeping the column null by default; requested parents are joined with `, ` and root commits render as an empty string.
- Native log rows populate `signature` only when requested; unsigned commits render an empty string in the requested signature column, while unrequested signatures stay null.
- Native refs formatting maps upstream `--decorate` modes: `short` trims `refs/heads`, `refs/remotes`, and `refs/tags`, tags render as `tag: name`, `full` preserves full ref paths, `no` returns blanks, and `auto` defaults to SQL-style short decoration.
- Native log traversal can restrict rows to a selected head ancestry or include all supplied commits, and computes commit heights from parent links when explicit `commit_order` is absent.
- Native `dolt_commits` rows now project all supplied branch commits into upstream's metadata-only schema without `commit_order`, `parents`, `refs`, or `signature`.
- Native `dolt_commit_ancestors` rows now project upstream's three-column parent-edge table, including one null-parent row for root commits and one row per merge parent with zero-based `parent_index`.
- Native commit ancestor filtering by `commit_hash` preserves all parent rows for merge commits, matching the upstream guard against max1row optimization.
- Native commit ancestor parent hashes can be joined back to `dolt_log` rows to recover parent commit messages in parent-index order.
- Native `has_ancestor()` now resolves commit hashes, `HEAD`, branch refs, tag refs, full refs, and `^`, `^N`, and `~N` suffixes before traversing the commit parent closure.
- Native ancestor-spec parsing maps upstream's `doltdb/ancestor_spec.go` grammar, including second-parent merge traversal and explicit errors for invalid merge-parent numbers.
- Native `dolt_branches` rows now project upstream's local branch metadata shape, including latest committer/author metadata, tracking remote/branch fields, dirty state, and name-index range filtering.
- Native `dolt_remote_branches` rows now prefix remote names with `remotes/` and omit local tracking/dirty fields.
- Native `active_branch()` returns the matched branch name case-insensitively and returns null for detached or missing branch contexts.
- Native `dolt_branch_activity` rows now include every current branch, filter deleted and `HEAD` activity, preserve nullable last-read/last-write times, and attach active session counts plus system-start time.
- WordPress commit-log fixtures now cover an import/review branch merge with `HEAD -> main`, a review tag, side-branch refs, merge parents, and separate author/committer metadata for migration audit UIs.
- WordPress commit-ancestors fixtures now cover the same reviewed import merge as `dolt_commit_ancestors` parent edges joined to parent log messages.
- WordPress has-ancestor fixtures now cover reviewed import branch and tag containment checks, including media-import branch ancestry, `HEAD`, `^2`, and `~2` commit specs.
- WordPress branch-review fixtures now cover active migration branches, dirty media-import work, active reviewer sessions, and branch activity timestamps for migration review queues.
- WordPress fixtures now cover `wp_posts` row-level migration changes, `wp_posts` -> `wp_content_posts` table rename summaries, a plugin table schema-drift projection, skinny post-review diffs, filtered publish-impacting review rows, aggregate migration-review diff stats, ignore-aware generated-table summaries, ambiguous scratch/cache ignore-rule conflict reporting, a `wp_postmeta` primary-key-change warning scenario, a status-review queue that hides generated cache tables without shelling out to Dolt, schema-object history for migration views/triggers/events, and stored-procedure history for import/review routines.
- WordPress commit-diff fixtures now cover a named import commit-to-commit `wp_posts` review window with an upstream-shaped `to_ID` range predicate.
- WordPress merge-review fixtures now surface unresolved import branch state for `wp_posts`, `wp_postmeta`, `wp_options`, and a preview view, including the upstream distinction that constraint-only tables appear in `dolt_merge_status.unmerged_tables` but not in `dolt_conflicts` row counts.
