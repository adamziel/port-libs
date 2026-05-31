# Real Upstream Corpus Select Core Dynamic Select1

Session: `port-dev-sqlite-yield-dyn-real-select-20260531T045255Z`
Micro-slice: `real-upstream-corpus-select-core-dynamic-20260531T045255Z-0`
Base accepted HEAD: `d470482ec8f04bd52049cae518f9a06a2103fe0c`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- Ported sections:
  - `select1-1.8.3`: wildcard/literal projection source-order behavior.
  - `select1-3.7`: scalar `min(f1,f2)` predicate behavior in `WHERE`.
  - `select1-4.11`: ordinal `ORDER BY 2, 1 DESC` tie-break behavior.
  - `select1-5.1`: implicit aggregate `ORDER BY` on a source column is ignored.

## Behavior Delta

- `SQLiteSelectSql` now skips materializing `ORDER BY` terms for implicit aggregate SELECT plans without `GROUP BY`.
- This matches SQLite's single-row aggregate behavior from `select1-5.1` and avoids hidden source-column projection after aggregation.
- Added `SQLiteRealUpstreamCorpusSelectCoreDynamicSelect1Test.php` with 1000 dynamic upstream-backed behavior cases plus one source-citation case.

## Verification

- Focused behavior test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicSelect1Test.php`
  - Result: `1 test files, 5005 assertions, 0 failures`
  - PASS lines: `1001`

## Non-Overlap

This slice does not repeat existing `select5.test`/`select6.test` aggregate-group batch2 coverage, `select1-18.2` correlated nested BETWEEN coverage, `select4-2.5` scalar subquery ORDER BY alias coverage, accepted grouped SELECT SQL text, expression ORDER BY, JSON table sources/constraints, or storage/VFS batches.

## Dependency Closure

No new support component is needed. The slice reuses existing native `SQLiteSelectSql`, `SQLiteSelectQuery`, projection, predicate, aggregate, and result-order helpers.
