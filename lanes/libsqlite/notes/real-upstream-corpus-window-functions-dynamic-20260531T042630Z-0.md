# real-upstream-corpus-window-functions-dynamic-20260531T042630Z-0

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T042630Z-0`
- Base accepted HEAD: `9c639ff85ec75b07f4dd143b6bbb0e832cdb6a85`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported sections:
  - `window1.test` `12.100`: `lead(c,1) OVER(ORDER BY c)` is evaluated after `WHERE id>1`, then the outer `ORDER BY b LIMIT 1` returns the first row without looping.
  - `window1.test` `12.110`: appended rows preserve the same window/outer-order pipeline with `LIMIT 2`.
- Added focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindow1LeadLimitDynamicTest.php`
- Focused assertions: 1847 assertions in 164 TestRunner PASS cases.
- Non-overlap: this owns only the upstream `window1.test` section-12 lead/WHERE/outer-ORDER/LIMIT endless-loop regression. It avoids accepted window1 basic frames, lead sales sections, compound rank sections, subquery/partition/unary-plus sections, chained windows, mixed RANGE offsets, window2-9, windowA-E, windowerr, windowfault, windowpushd, JSON/WAL/VFS/B-tree, grouped SELECT text, expression ORDER BY, and metadata-only runner rows.
- Dependency closure: no new support component is needed; the test reuses `SQLiteWindowFunction::lead()` and lane-local row-array ordering to assert the upstream execution pipeline.

Verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1LeadLimitDynamicTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1LeadLimitDynamicTest.php
  1 test files, 1847 assertions, 0 failures
git diff --check -- lanes/libsqlite
```
