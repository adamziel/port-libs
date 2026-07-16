# real-upstream-corpus-btree-index-dynamic-20260530T165755Z-0

Ported a focused real upstream B-tree/index cluster from the hydrated SQLite
checkout in `/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Upstream source truth:

- `index.test` scenarios `index-4.1` through `index-4.13`: create two indexes
  over `cnt` and `power`, seek through each index, drop/recreate `indext`, and
  keep lookups stable across index catalog changes.
- `index2.test` scenarios `index2-2.1` and `index2-2.2`: create a wide
  multi-column index and satisfy ordered prefix reads over the first indexed
  columns.

Native behavior:

- Added `SQLiteRealUpstreamBTreeIndexDynamicCorpus`, which builds actual
  `SQLiteIndexLeafPage` images from row values, encodes records through
  `SQLiteRecord`/`SQLiteIndexCell`, parses the leaf page back through the
  B-tree header/cell reader, seeks records by index prefix, and models the
  bounded create/drop/recreate index catalog state.
- Added `SQLiteRealUpstreamCorpusBTreeIndexDynamicTest.php` with 125 focused
  PASS cases and 824 assertions over real encoded index leaf ordering, prefix
  seek results, wide-index ordering, catalog state transitions, and malformed
  input guards.

Non-overlap:

- This does not repeat accepted page relocation, root collapse, overflow
  freelist release, partial-index admission, indexed-by access-path planning,
  expression-index range cost, or SQL expression ORDER BY coverage. The slice
  is focused on dynamic index leaf materialization and scan/seek behavior from
  `index.test` and `index2.test`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamBTreeIndexDynamicCorpus.php`
  - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusBTreeIndexDynamicTest.php`
  - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusBTreeIndexDynamicTest.php`
  - `1 test files, 824 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. The slice reuses existing native
  `SQLiteRecord`, `SQLiteIndexCell`, `SQLiteIndexLeafPage`, and
  `SQLiteBTreePageHeader` behavior.
