# dolt Upstream Test Inventory

- Upstream: `dolthub/dolt`
- Commit: `b2274926e0dcd84aab000ee242df5b5e75689eef`
- License: Apache-2.0
- Cache: `.upstream-cache/dolt`
- Method: shallow blob-filtered clone with `--filter=blob:none --depth=1 --no-checkout`; counted paths with `git ls-tree -r --name-only HEAD` and hydrated only targeted metadata, BATS README, license, and diff-related source/test files.

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

## Runner Status

The full upstream runners were not executed for this lane slice.

- `go version` returns `go: command not found`, so `go test ./...` and Dolt's Go package suites cannot run here.
- `bats --version` returns `bats: command not found`.
- Upstream BATS documentation requires installing BATS, building `dolt`, `noms`, and `remotesrv` with Go, then installing Python packages such as `mysql-connector-python`, `pyarrow`, and `pandas`; some suites also rely on parquet/hadoop tooling.
- The upstream cache is intentionally blob-filtered and not fully checked out to keep this worker run modest.

This denominator is therefore a cloned static inventory, not upstream pass parity.

## Native PHP Mapping Added

The current PHP slice maps focused row-diff semantics from upstream `DOLT_DIFF_*` / `dolt_diff()` behavior:

- Changed rows project into `to_*` columns, `to_commit`, `to_commit_date`, `from_*` columns, `from_commit`, `from_commit_date`, then `diff_type`.
- `diff_type` values are `added`, `removed`, and `modified`.
- Added rows have null `from_*` values; removed rows have null `to_*` values; modified rows include both sides.
- Primary-key indexing supports composite keys without string-collision overwrites and rejects missing, null, and duplicate keys.
- A WordPress `wp_posts` migration fixture demonstrates inspectable content import changes without shelling out to Dolt.
