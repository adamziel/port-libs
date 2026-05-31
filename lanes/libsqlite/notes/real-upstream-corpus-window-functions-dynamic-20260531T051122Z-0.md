# real-upstream-corpus-window-functions-dynamic-20260531T051122Z-0

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T051122Z-0`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test`
- Ported sections:
  - `windowB.test` `3.5d`: `json_group_object()` over `ROWS BETWEEN 1 FOLLOWING AND 2 FOLLOWING` preserves inverse-window order and empty-frame output.
  - `windowB.test` `3.7b` through `3.7d`: `FILTER (WHERE id!=2)` is applied before inverse removal for `group_concat()`, `json_group_array(json(...))`, and `json_group_object(...,json(...))` over prior-row frames.
- Added focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindowBFilteredJsonDynamicTest.php`.
- Focused assertions: 1200 generated dynamic cases over variable row counts, filters, and prior/following ROWS boundaries plus fixed upstream section checks.

Verification:

```sh
php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowBFilteredJsonDynamicTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowBFilteredJsonDynamicTest.php
git diff --check -- lanes/libsqlite
```

Non-overlap: this owns only upstream `windowB.test` filtered JSON/object inverse-frame sections `3.5d` and `3.7b-3.7d`. It does not repeat accepted `windowB` range peer behavior, sections `3.2`, `3.4`, `3.5c`, `3.9-3.16`, `7`, `9`, `10`, or `11`, nor existing window1/2/3/4/5/6/7/8/9/A/C/D/E/pushdown/error/fault dynamic coverage, JSON table cursor/source/constraint work, WAL/VFS/B-tree/planner/PRAGMA/trigger clusters, or metadata-only runner rows.

Dependency closure: no new support component is needed. This reuses the lane-local JSON aggregate window-frame helpers, SQLite JSON subtype wrapper, aggregate filter support, and focused TestRunner infrastructure; no Tcl runner, ext/sqlite, or external service is required.
