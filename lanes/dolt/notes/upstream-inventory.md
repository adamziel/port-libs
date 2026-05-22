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
- `go/libraries/doltcore/diff/column_identity_test.go`: 2 `Test*` functions, read as background for conservative table/column matching semantics.
- `integration-tests/bats/rename-tables.bats`: 5 BATS cases.
- `integration-tests/bats/primary-key-changes.bats`: 40 BATS cases.

## Runner Status

The full upstream runners were not executed for this lane slice, but bounded upstream Go package, Go engine, Dolt CLI build, and BATS diff/rename/primary-key runners were executed after installing directly relevant tooling.

- Tooling probes now return `go version go1.26.3-X:nodwarf5 linux/amd64` and `Bats 1.13.0`.
- Installed/probed commands used in this lane: `sudo -n dnf install -y golang bats`; resulting direct versions include `golang-1.26.3-2.fc44.x86_64`, `golang-bin-1.26.3-2.fc44.x86_64`, `golang-src-1.26.3-2.fc44.noarch`, and `bats-1.13.0-3.fc44.noarch`.
- `libicu-devel-77.1-2.fc44.x86_64` is present on the host and satisfies Dolt's `github.com/dolthub/go-icu-regex` compile dependency.
- Sparse checkout state in `.upstream-cache/dolt`: `go` and `integration-tests/bats`; the cache remains a `blob:none` partial clone at `b2274926e0dcd84aab000ee242df5b5e75689eef`.
- Current cache inspection before this runner extension found the hydrated directories present, plus internal upstream-cache index noise: `git -C .upstream-cache/dolt status --short --branch` still reports staged deletions for out-of-cone paths from the earlier no-checkout/sparse hydration and untracked build caches. `git -C .upstream-cache/dolt sparse-checkout reapply` was attempted and did not clear that state; no delete or reset was run.
- Bounded Go runner results:
  - `go test ./libraries/doltcore/diff -count=1 -timeout 5m`: pass.
  - `go test ./libraries/doltcore/table ./libraries/doltcore/table/untyped ./libraries/doltcore/table/untyped/csv ./libraries/doltcore/table/untyped/tabular ./libraries/doltcore/table/untyped/sqlexport ./libraries/doltcore/table/typed/json -count=1 -timeout 5m`: pass.
  - `go test ./libraries/doltcore/sqle/enginetest -run 'TestDiffTableFunction/dolt_diff: SELECT \* skinny schema visibility' -count=1 -timeout 8m`: pass.
  - From `.upstream-cache/dolt-runner/go`, `timeout 180s go test ./libraries/doltcore/diff ./libraries/doltcore/schema ./libraries/doltcore/sqle/dtables`: diff and schema pass; `sqle/dtables` compiles and reports no test files.
  - `go test ./libraries/doltcore/schema ./libraries/doltcore/schema/typecompatibility ./libraries/doltcore/schema/encoding -count=1 -timeout 5m`: pass.
- Cache-local Dolt utility build: `go install ./cmd/dolt ./store/cmd/noms ./utils/remotesrv` with `GOBIN=.upstream-cache/dolt/bats-home/go/bin`; `dolt version` reports `dolt version 2.0.5`.
- Bounded BATS runner results from `integration-tests/bats/diff.bats`: `diff: clean working set`, `diff: data diff only`, `diff: schema changes only`, `diff: with limit`, `diff: allowed across primary key renames`, `diff: --filter=renamed filters to only renamed tables`, and `diff: row, line, in-place, context diff modes` all pass.
- Bounded BATS runner results from `integration-tests/bats/rename-tables.bats`: `rename a table with sql`, `diff a renamed table`, and `sql diff a renamed table` all pass.
- Bounded BATS runner results from `integration-tests/bats/primary-key-changes.bats`: `diff on primary key schema change shows schema level diff but does not show row level diff`, `dolt diff table returns top-down diff until schema change`, and `same primary key set in different order is detected and blocked on merge` all pass.
- Full `go test ./...` was not run because it would hydrate and compile the full Dolt workspace and broad dependency graph beyond this lane slice.
- Full BATS was not run because upstream BATS also runs Python/parquet/Hadoop/server/compatibility/client integration dependencies.
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
- WordPress fixtures now cover `wp_posts` row-level migration changes, `wp_posts` -> `wp_content_posts` table rename summaries, a plugin table schema-drift projection, and a skinny post-review diff without shelling out to Dolt.
