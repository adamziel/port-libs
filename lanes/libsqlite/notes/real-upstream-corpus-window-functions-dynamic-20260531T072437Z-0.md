# real-upstream-corpus-window-functions-dynamic-20260531T072437Z-0

Base accepted HEAD: `9d0b0fe07345f3693373fb79bddfe1aa2564a7a2`.

Added `SQLiteRealUpstreamWindowADescNullsDynamic20260531Test.php` with 1000 generated real-upstream behavior cases plus source and dependency-closure tests. The batch is sourced from `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test` sections 1.1-1.6, 2.1-2.6, and 3.1-4.0.

Focused behavior: DESC RANGE window frames with NULLS FIRST/LAST over finite, current-row, preceding-only, following, and unbounded boundaries. Each generated case checks `group_concat`, `sum`, `count`, and `max`; every fourth case also checks SQL-truthy FILTER handling.

Non-overlap: this extends accepted windowA/windowE/window8 coverage with dynamic DESC RANGE NULLS FIRST/LAST aggregates and filters. It does not repeat value-offset, GROUPS, windowE numeric range, window8 null-range cursor, windowC separator, windowfault large-frame, or parser-level SELECT-source batches.

Dependency closure: no new support component needed; the batch reuses `SQLiteWindowFunction::aggregateOrderedRangeValues`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowADescNullsDynamic20260531Test.php` passed.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowADescNullsDynamic20260531Test.php` passed: 1 test file, 5003 assertions, 0 failures, 1002 PASS lines.
