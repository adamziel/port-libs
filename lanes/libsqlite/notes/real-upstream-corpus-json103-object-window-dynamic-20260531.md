# real-upstream-corpus-json1-jsonb-dynamic-20260531T014357Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`
- Ported section: `json103-410`, with `json103-400` as the adjacent upstream window aggregate reference.

## Behavior

- Added `SQLiteRealUpstreamJson103ObjectWindowDynamicMegaTest.php`.
- Covers `json_group_object(rowid, x) OVER (ROWS n PRECEDING/FOLLOWING)` style behavior over 1,000 deterministic object-window frames.
- Exercises mixed scalar values, SQL NULL values, escaped text, duplicate labels, frame boundary clipping, text JSON duplicate-label preservation, and JSONB canonical last-label parity.
- This is non-overlapping with accepted JSON table source/cursor/hidden/visible constraint work and with existing `json103` dynamic array-window coverage.

## Evidence

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson103ObjectWindowDynamicMegaTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103ObjectWindowDynamicMegaTest.php`
- Result: `1 test files, 6003 assertions, 0 failures`; `1001` focused PASS lines.

## Dependency Closure

- No new support component required. The slice reuses existing native `SQLiteJsonAggregate`, `SQLiteJsonCanonical`, `SQLiteJsonInspection`, and `SQLiteJsonExtract` behavior.
