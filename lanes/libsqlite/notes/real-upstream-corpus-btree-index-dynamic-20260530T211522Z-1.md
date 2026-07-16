# real-upstream-corpus-btree-index-dynamic-20260530T211522Z-1

Status: focused PHP behavior growth for a real upstream SQLite B-tree/index
corpus slice.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index4.test`
- Upstream scenarios: `index4-1.1` through `index4-2.2`

Ported behavior:

- CREATE INDEX over a 65536-row fixed-width blob table.
- Repeated CREATE INDEX with a tiny cache-size setup.
- CREATE INDEX over mixed text, NULL, and growing blob payloads.
- One-row and empty-table CREATE INDEX integrity behavior.
- CREATE UNIQUE INDEX duplicate-key failure preserving the existing table state.

Focused coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::index4CreateIndexStressCases()`.
- Added `SQLiteBTreeIndexDynamicCorpusIndex4Test.php` with 1202 focused
  TestRunner cases derived from the upstream `index4.test` sections above.

Non-overlap:

This does not repeat accepted B-tree page relocation, root collapse, index
interior merge, overflow freelist release, bulk overflow freeblocks, existing
`index.test` duplicate delete rows, `index3`, `index5`, `index6`, `index7`,
`index9`, `indexA`, `indexedby`, `indexexpr`, or `numindex1` dynamic corpus
coverage. The new surface is specifically upstream `index4.test` CREATE INDEX
bulk-build, low-cache build, empty/one-row build, and duplicate UNIQUE failure
behavior.

Dependency closure:

No new support component is needed. The slice reuses lane-local B-tree/index
dynamic corpus planner, record sizing, sorter-page, duplicate-key, and
integrity-result helpers.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusIndex4Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusIndex4Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
