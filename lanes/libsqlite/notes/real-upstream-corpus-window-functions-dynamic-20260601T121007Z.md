# Real Upstream Window Functions Dynamic Corpus 2026-06-01T121007Z

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
- Owned scenarios:
  - `window3.test 1.1.9.1-1.1.9.6`: `last_value(a+b)` with generated partition/order variants.
  - `window3.test 1.1.10.1-1.1.10.6`: `nth_value(b,b+1)` with generated partition/order variants.
  - `window3.test 1.1.11.1-1.1.11.6`: `first_value(b)` with generated partition/order variants.
  - `window3.test 1.1.12.1-1.1.12.6`: `lead(b,b)` with generated partition/order variants.
  - `window3.test 1.1.13.1-1.1.13.6`: `lag(b,b)` with generated partition/order variants.

## Patch

- Added `SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T121007ZTest.php`.
- The test cites the hydrated upstream `window3.test` source and generates 1,000 deterministic value/navigation cases over the upstream partition/order shapes.
- Each dynamic case compares the port helpers against an independent frame or offset oracle for `last_value`, `nth_value`, `first_value`, `lead`, and `lag`.

## Evidence

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T121007ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T121007ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T121007ZTest.php`
  - `1 test files, 30013 assertions, 0 failures`
  - PASS-line growth: `+1002` focused TestRunner cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowCorpusInventoryTest.php`
  - `1 test files, 27 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 6 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

## Non-Overlap

- This batch owns generated `window3.test` value/navigation sections `1.1.9` through `1.1.13`.
- It avoids accepted windowA/windowB/windowC/windowD/windowE/windowpushd/filter batches, accepted `window3.test 1.20.*` dynamic matrix coverage, and non-window storage/planner/source-neutral cleanup surfaces.

## Dependency Closure

- No new support component is required.
- The batch reuses existing `SQLiteWindowFunction::valueFrameBetweenValues()`, `SQLiteWindowFunction::leadByRow()`, and `SQLiteWindowFunction::lagByRow()` helpers with independent expected-result oracles in the focused test.

## Root Harness

- Not run: isolated micro-slice.
