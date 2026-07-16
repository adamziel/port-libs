# real-upstream-corpus-window-functions-dynamic-20260530T225651Z-0

- Base accepted HEAD: `6e94a67dd020b9cfec1567bd7fbc6ebe5e036bda`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test`.
- Ported upstream section: `windowfault.test` section `9`, the 1900-row large
  `ROWS BETWEEN UNBOUNDED PRECEDING AND 1800 FOLLOWING` frame that upstream
  runs under temporary-read fault injection.
- Added PHP focused coverage:
  - `SQLiteRealUpstreamWindowFault9LargeFrameDynamicTest.php`
  - 1901 distinct TestRunner PASS cases.
  - 3802 behavior assertions.
- Behavior covered:
  - Large following-frame membership over the upstream 1900-row shape.
  - Native window `count()` and `total()` over the same large dynamic frame,
    including saturated frames near the tail of the partition.
- Exclusion:
  - Upstream section 9 uses `sum(y)` where `y` is nonnumeric text. The accepted
    lane status currently records a separate rejected numeric-text aggregate
    parity batch with broad regression failures, so this slice intentionally
    does not change or assert that numeric coercion path.
- Non-overlap:
  - Earlier accepted `windowfault.test` coverage owned sections `1-8`, `10-13`,
    and a generic dynamic OOM-stable matrix. This slice owns the previously
    unported section `9` large-frame membership behavior and avoids accepted
    `window1` through `windowE`, `windowerr`, `windowpushd`, JSON, WAL, B-tree,
    VFS, PRAGMA, trigger/FK, and runner metadata batches.
- Dependency closure:
  - No new support component is needed; this reuses native
    `SQLiteWindowFunction::aggregateFrameBetweenValues()` and the focused
    TestRunner harness.

Verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowFault9LargeFrameDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindowFault9LargeFrameDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowFault9LargeFrameDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 3802 assertions, 0 failures
```
