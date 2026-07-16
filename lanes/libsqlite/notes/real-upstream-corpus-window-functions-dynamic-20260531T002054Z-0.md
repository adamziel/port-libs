# real-upstream-corpus-window-functions-dynamic-20260531T002054Z-0

- Base accepted HEAD: `aab498f11db56174605363e36ca7a662eb3a6384`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` sections `8.1.1-8.2.2`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` sections `9.1.1-9.3`
- Ported behavior: view/reopened-view running `sum`/`min`/`max` window aggregates, trigger-shaped partitioned `max()` refresh after insert, CTE reuse of partitioned window aggregates, and a nested running `min()` over a prior window output.
- Focused test added: `lanes/libsqlite/tests/SQLiteRealUpstreamWindow1ViewTriggerDynamicTest.php`.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1ViewTriggerDynamicTest.php` passed with `1 test files, 5009 assertions, 0 failures` and `1006` PASS lines.
- Non-overlap: this avoids accepted `window1.test` sections `1.1-5.4`, `7.2-7.4`, and `10.1-10.8`, plus existing `window2` through `windowE`, `windowerr`, `windowfault`, and `windowpushd` dynamic batches. It adds no metadata-only runner rows, fake upstream script IDs, domain-specific APIs, or JSON/WAL/B-tree/VFS behavior.
- Dependency closure: no new support component is needed; the slice reuses the existing native `SQLiteWindowFunction` aggregate frame helpers.
