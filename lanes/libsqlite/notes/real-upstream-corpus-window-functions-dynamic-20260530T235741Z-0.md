# real-upstream-corpus-window-functions-dynamic-20260530T235741Z-0

- Base accepted HEAD: `d045774aa6bf87ca954fff751277766f57e01075`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`.
- Ported scenario: `window6.test` `11.4.1`, where `RANGE BETWEEN UNBOUNDED PRECEDING AND 10 PRECEDING` over text `ORDER BY` keys uses the current peer group boundary rather than numeric subtraction.
- Added focused test file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindow6TextRangeDynamicTest.php`.
- Focused PASS growth: 1,001 distinct TestRunner cases and 3,001 assertions.
- Non-overlap: prior accepted window6 coverage cites `1.*`, `5.*`, `8.*`, `9.*`, `10.*`, and `11.3.*` value/default-frame behavior. This slice owns only `window6.test:11.4.1` text-key RANGE numeric-offset frame behavior and does not add runner metadata, fake upstream script IDs, source-neutral cleanup, JSON/WAL/B-tree/VFS scenarios, or domain-specific APIs.
- Dependency closure: no new support component is needed; the existing generic `SQLiteWindowFunction::aggregateFrameBetweenValues()` frame engine is reused.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow6TextRangeDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow6TextRangeDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
