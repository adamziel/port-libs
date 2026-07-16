# real-upstream-corpus-select-core-dynamic-20260530T194754Z-0

Added a real upstream SELECT core dynamic batch for parenthesized `FROM`
name-resolution behavior from the hydrated SQLite upstream corpus.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`
- `selectD-1.1` and `selectD-2.1`: parenthesized table references in comma
  joins resolve table names through the same result path with and without query
  flattening.

## Behavior

- `SQLiteSelectSql::tableReference()` now recognizes a simple parenthesized
  table reference such as `(t1)` after derived-table and parenthesized join
  checks. This keeps `SELECT ... FROM (t1), (t2), ...` on the normal table
  source path without changing derived SELECT handling or join-group handling.
- Added `SQLiteRealUpstreamSelectDParenthesizedFromDynamicTest.php` with 1,250
  dynamic parenthesized source chains plus a source-citation test. Each case
  varies row values and verifies projection values, flat result count,
  first/last sentinels, and a result fingerprint.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDParenthesizedFromDynamicTest.php`
  - `1 test files, 6253 assertions, 0 failures`
  - `1251` distinct TestRunner PASS cases

## Non-Overlap

This slice owns the residual `selectD.test` simple parenthesized table-reference
cluster. It does not repeat accepted `select1` through `selectC` dynamic SELECT
coverage, `selectE`/`selectF` compound collation/copy coverage, expression
`ORDER BY`, grouped SELECT text, JSON table source/cursor/constraint work, or
metadata-only upstream runner rows. Mapped denominator remains unchanged because
`selectD.test` is already present in the hydrated upstream inventory.

## Dependency Closure

No new support component is needed. The existing bounded `SQLiteSelectSql`
parser/executor handles the behavior once parenthesized simple table references
are routed to the table-source resolver.
