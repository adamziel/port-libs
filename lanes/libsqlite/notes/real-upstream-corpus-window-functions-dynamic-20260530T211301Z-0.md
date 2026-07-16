# real-upstream-corpus-window-functions-dynamic-20260530T211301Z-0

Base accepted HEAD for this isolated worker: `bbccc1d8f736962c4f86ebb79411aec5c77c5f5a`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test`
- Ported scenarios:
  - `window7.test` `1.2`: `GROUPS BETWEEN CURRENT ROW AND CURRENT ROW`
  - `window7.test` `1.3`: `GROUPS BETWEEN 0 PRECEDING AND 0 FOLLOWING`
  - `window7.test` `1.4`: `GROUPS BETWEEN 2 PRECEDING AND 2 FOLLOWING`
  - `window7.test` `1.5`: `RANGE BETWEEN 0 PRECEDING AND 0 FOLLOWING`
  - `window7.test` `1.6`: `RANGE BETWEEN 2 PRECEDING AND 2 FOLLOWING`
  - `window7.test` `1.7`: `RANGE BETWEEN 2 PRECEDING AND 1 FOLLOWING`
  - `window7.test` `1.8.1`: `RANGE BETWEEN 0 PRECEDING AND 1 FOLLOWING`

## Delta

- Added `SQLiteRealUpstreamWindow7GroupRangeDynamicTest.php`.
- The test rebuilds the real upstream 100-row `t3(a,b)` corpus and checks native `SQLiteVdbeWindowAggregateCursor` behavior against an independent PHP oracle for peer-group and range frames.
- New focused TestRunner growth: `+2801` PASS cases / assertions.
- Countable as PASS-line growth only. Mapped denominator coverage was already complete at `1589 / 1589`, so this does not claim new mapped inventory.

## Non-Overlap

This batch does not repeat the accepted `window4`, `window8`, `window9`, `windowA`, `windowB`, `windowC`, `windowD`, `windowE`, `windowerr`, `windowfault`, or existing dynamic cursor scenarios. It specifically owns the `window7.test` grouped/range aggregate frame matrix over the upstream `t3` 100-row data set.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow7GroupRangeDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow7GroupRangeDynamicTest.php`: `1 test files, 2801 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`: passed.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP window aggregate cursor and focused TestRunner infrastructure; no ext/sqlite, Tcl runner, or new dependency activation is required.
