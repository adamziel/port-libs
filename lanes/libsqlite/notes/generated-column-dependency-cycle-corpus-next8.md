# Generated Column Dependency Cycle Corpus Next8

2026-05-27 isolated slice `yield-sqlite-generated-column-dependency-cycle-corpus-next8`.

## Scope

- Added `SQLiteGeneratedColumnDependencyPlan` to parse generated-column expressions from `CREATE TABLE` SQL, collect generated/base-column dependencies, topologically order acyclic generated columns, and report SQLite-shaped dependency loop diagnostics.
- Covered direct self-reference, indirect generated-column cycles, quoted/bracketed identifiers, function-name disambiguation, stored/virtual storage tags, and an acyclic Application-shaped `wp_options` generated-column chain.
- Added a Application smoke that preflights copied `wp_options` generated-column expressions before native import or repair without `ext/sqlite`.

## Verification

- `sqlite3 :memory: "CREATE TABLE t(a INT,b INT AS (b));"`
  - Oracle stderr: `generated column loop on "b"`.
- `sqlite3 :memory: "CREATE TABLE t(a INT,b INT AS (c), c INT AS (b));"`
  - Oracle stderr: `generated column loop on "c"`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteGeneratedColumnDependencyCycleCorpusTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `32` PASS lines, `1 test files, 32 assertions, 0 failures`.

## Status Delta

- `phpPass`: `2311 -> 2343` (`+32` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this slice adds focused PHP corpus coverage without claiming a newly hydrated upstream inventory unit.

## Non-Overlap

This avoids accepted generated-column CHECK/schema catalog handling, JSON table, WAL, B-tree, VFS, SELECT SQL, Unicode GLOB, overflow freelist, rollback-commit, and later accepted storage/executor clusters. The new behavior is generated-column dependency graph and loop detection only.

## Dependency Closure

No new support component is needed. The slice reuses lane-local SQL text scanning and `TestRunner`; it does not require `ext/sqlite`, shelling out to SQLite at runtime, or shared dependency activation.
