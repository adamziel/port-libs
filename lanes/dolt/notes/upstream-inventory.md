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
- `integration-tests/bats/status.bats`: focused status coverage for conflict tables, renamed tables, and reset with a renamed table; full file exposed one stale fixed-width commit-hash helper.

## Runner Status

The full upstream runners were not executed for this lane slice, but bounded upstream Go package, Go engine, Dolt CLI build, and local BATS diff/rename/primary-key/diff-stat/query-diff/schema-change/column-tag/sql-diff runners were executed after installing directly relevant tooling.

- Tooling probes now return `go version go1.26.3-X:nodwarf5 linux/amd64`, `Bats 1.13.0`, and `expect version 5.45.4`.
- Installed/probed commands used in this lane: `sudo -n dnf install -y golang bats` and `sudo -n dnf install -y expect`; resulting direct versions include `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, `bats-1.13.0-3.fc44.noarch`, and `expect-5.45.4-31.fc44.x86_64`.
- Fresh prerequisite check `sudo -n dnf install -y golang bats expect` found those packages already installed and returned `Nothing to do.`
- `libicu-devel-77.1-2.fc44.x86_64` is present on the host and satisfies Dolt's `github.com/dolthub/go-icu-regex` compile dependency.
- Sparse checkout state in `.upstream-cache/dolt`: `go` and `integration-tests/bats`; the cache remains a `blob:none` partial clone at `b2274926e0dcd84aab000ee242df5b5e75689eef`.
- Current cache inspection before this runner extension found the hydrated directories present, plus internal upstream-cache index noise: `git -C .upstream-cache/dolt status --short --branch` still reports staged deletions for out-of-cone paths from the earlier no-checkout/sparse hydration and untracked build caches. `git -C .upstream-cache/dolt sparse-checkout reapply` was attempted and did not clear that state; no delete or reset was run.
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
- WordPress fixtures now cover `wp_posts` row-level migration changes, `wp_posts` -> `wp_content_posts` table rename summaries, a plugin table schema-drift projection, skinny post-review diffs, filtered publish-impacting review rows, aggregate migration-review diff stats, ignore-aware generated-table summaries, ambiguous scratch/cache ignore-rule conflict reporting, and a `wp_postmeta` primary-key-change warning scenario without shelling out to Dolt.
