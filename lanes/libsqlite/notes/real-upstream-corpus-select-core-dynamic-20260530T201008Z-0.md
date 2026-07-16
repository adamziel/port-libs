# real-upstream-corpus-select-core-dynamic-20260530T201008Z-0

Added a real upstream SELECT core dynamic corpus batch from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`

## Upstream Scenarios

- `limit-1.2.1` through `limit-1.2.7`: ordered `LIMIT`, `OFFSET`, comma-form `LIMIT offset,count`, negative offset, and negative count behavior.
- `limit-6.2` through `limit-6.8`: negative `LIMIT` / `OFFSET`, `LIMIT 0`, and unbounded negative limit behavior.
- `limit-7.2` through `limit-7.12`: compound SELECT `LIMIT` / `OFFSET` applies to the whole compound query.
- `limit-10.1` through `limit-10.3`: dynamically varied numeric LIMIT/OFFSET shapes over insertion-order results.

## Behavior

- Fixed `SQLiteSelectResult::limitOffset()` to match SQLite's upstream behavior for negative offsets: a negative `OFFSET` is treated as zero instead of rejected.
- Added `SQLiteRealUpstreamLimitDynamicCorpusTest.php` with 1,001 distinct TestRunner cases and 5,681 focused assertions across ordered SELECT limits, comma LIMIT, negative limit/offset combinations, insertion-order LIMIT, and compound SELECT limit windows.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamLimitDynamicCorpusTest.php`
  - `1 test files, 5681 assertions, 0 failures`
  - `1001` distinct selected PASS cases

## Non-Overlap

This batch owns the `limit.test` SELECT LIMIT/OFFSET behavior cluster. It does not repeat the accepted `select1` through `selectH` focused corpus batches, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, B-tree/WAL/VFS storage clusters, or metadata-only upstream runner rows. Mapped denominator remains unchanged because this is focused PHP behavior admission from an already hydrated upstream source.

## Dependency Closure

No new support component is needed. The existing bounded `SQLiteSelectSql` and `SQLiteSelectResult` execution path handles the corpus once negative offsets are normalized to SQLite's zero-offset semantics.
