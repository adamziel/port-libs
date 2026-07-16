# real-upstream-corpus-window-functions-dynamic-20260531T005313Z-0

## Scope

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`.
- Ported sections:
  - `window2.test:4.0-4.8` aggregate window frame families over partitioned rows.
  - `window2.test:4.6.1-4.8.4` `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, and `EXCLUDE TIES` variants.
  - `window2.test:6.0-6.2` aggregate window `FILTER` behavior.

## Delta

- Added `SQLiteRealUpstreamWindow2ExcludeDynamicMatrixTest.php`.
- The test generates 1,200 distinct dynamic `ROWS`, `GROUPS`, and `RANGE` frame cases across `sum`, `count`, `avg`, `min`, `max`, `total`, and `group_concat`, with partitioning, exclusion modes, and optional filters.
- This is behavior-backed PHP TestRunner growth only. Mapped denominator coverage remains complete at `1589 / 1589`.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow2ExcludeDynamicMatrixTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow2ExcludeDynamicMatrixTest.php` -> `1 test files, 1201 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` -> passed.

## Non-Overlap

This avoids accepted `window3`, `window4`, `window5`, `window6`, `window7`, `window8`, `window9`, `windowA`, `windowB`, `windowC`, `windowD`, `windowE`, `windowerr`, `windowfault`, and `windowpushd` slices. The owned surface is a larger real-upstream `window2.test` dynamic matrix for aggregate frame/exclude/filter behavior using existing native `SQLiteWindowFunction` helpers.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP window aggregate frame machinery and focused TestRunner infrastructure; no upstream runner, Tcl bridge, external database extension, or new dependency activation is required.
