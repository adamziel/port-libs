# real-upstream-corpus-window-functions-dynamic-20260530T233704Z-0

Slice: `real-upstream-corpus-window-functions-dynamic-20260530T233704Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`

Ported scenarios:

- `window8.test:1.7` through `1.19` for additional `GROUPS` frame boundaries over the real upstream 80-row `t3(a,b,c)` corpus.
- `window8.test:1.*` `EXCLUDE GROUP` and `EXCLUDE TIES` variants for `sum()`, `max()`, and `min()` window aggregates over `ORDER BY a` and `ORDER BY a,b` peer groups.

Implementation delta:

- Extended `SQLiteRealUpstreamWindow8GroupsExtendedDynamicTest.php` to cover the remaining bounded preceding/following `GROUPS` frame matrix and peer exclusion modes against an independent PHP oracle.
- No production source change was required; the existing native `SQLiteWindowFunction::aggregateFrameBetweenValues()` behavior already matches these upstream scenarios.

Focused assertion and PASS-line evidence:

- Before this slice, the file covered 2,801 focused TestRunner cases from `window8.test:1.2` through `1.6`.
- After this slice, the focused command passes `1 test files, 56161 assertions, 0 failures`.
- This adds 15,920 focused TestRunner PASS cases and 47,760 behavior assertions over the prior matrix.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow8GroupsExtendedDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow8GroupsExtendedDynamicTest.php`: `1 test files, 56161 assertions, 0 failures`.

Non-overlap:

- This slice extends the accepted `window8.test` GROUPS frame coverage beyond the prior `1.2` through `1.6` focused matrix. It does not repeat accepted `window4`, `window5`, `window6`, `window7`, `window9`, `windowA`, `windowB`, `windowC`, `windowD`, `windowE`, `windowerr`, `windowfault`, `windowpushd`, JSON window, row-value/window, compound/window, WAL, VFS, B-tree, PRAGMA, trigger, or suite evidence clusters.

Dependency closure:

- No new support component is needed. This reuses native PHP window aggregate helpers and the existing focused TestRunner infrastructure; no ext/sqlite, Tcl runner, or new dependency activation is required.
