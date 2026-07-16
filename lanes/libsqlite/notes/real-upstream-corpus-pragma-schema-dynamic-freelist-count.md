# Real Upstream PRAGMA Schema Dynamic Freelist Count

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T034051Z-0`

Accepted base: `eb22516d8f29af7145a28b1cc2453b19311c1d0b`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`
- Sections `pragma2-1.1` through `pragma2-1.12`

Implemented behavior:

- Added `SQLitePragmaFreelistCountState` for bounded `PRAGMA freelist_count`
  execution.
- Covered unqualified main reads, schema-qualified main reads, attached schema
  independence, row/header result shape, and ignored assignment forms
  `PRAGMA freelist_count = N` / `PRAGMA aux.freelist_count(N)`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFreelistCountTest.php`
  - `1 test files, 7760 assertions, 0 failures`
  - `1003` PASS lines, including `1000` generated real upstream dynamic cases.
- `php -l lanes/libsqlite/src/SQLitePragmaFreelistCountState.php`
  - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFreelistCountTest.php`
  - no syntax errors.
- `git diff --check -- lanes/libsqlite`
  - passed.

The generic API naming guard requested by the supervisor is not present in
this worktree.

Dependency closure:

- No new support component is needed. The slice reuses bounded in-memory PRAGMA
  state modeling and does not require filesystem, pager, or upstream Tcl runner
  support.

Non-overlap:

- This slice does not repeat accepted cache-spill, page_count,
  max_page_count, application_id, schema_version/user_version, table_info,
  table_xinfo, table_list, index_xinfo, data_version, corrupt-view, or schema
  invalidation batches. It owns only the `pragma2.test` freelist_count cluster.
