# real-upstream-corpus-window-functions-dynamic-20260531T074234Z-0

Base accepted HEAD: `9c30c680e4b44fbeb2fc11612b28622bb7d8e322`.

Owned non-overlapping upstream sources:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test` sections `3.1`, `4.1`, `4.2`, `5.1`, and `5.2`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test` sections `1.1` through `1.8`, `3.0`, and `3.2`.

Patch adds `SQLiteRealUpstreamWindowEWindowErrDynamicTest.php` with 1,010 distinct TestRunner cases and 4,017 assertions. The batch ports sparse numeric `RANGE` frame behavior, `total()` and `sum()` current-to-following frames over large/mixed numeric values, and invalid frame-boundary rejection. The generated dynamic cases expand those same upstream sections across 1,000 deterministic row windows.

Expected dashboard movement: count as PASS-line growth only. Mapped denominator coverage is already complete at `1589 / 1589`; no new mapped denominator row is claimed.

Dependency closure: no new support component needed. The batch reuses existing `SQLiteWindowFunction` frame helpers and adds no source API.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowEWindowErrDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowEWindowErrDynamicTest.php` -> `1 test files, 4017 assertions, 0 failures` and 1,010 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` -> clean.
