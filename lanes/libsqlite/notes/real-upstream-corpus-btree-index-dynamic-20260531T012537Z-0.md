# real-upstream-corpus-btree-index-dynamic-20260531T012537Z-0

Base accepted HEAD: `af20380a278ad54b2ad38b5d180ded7ec9aac2e7`.

Ported upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindex5.test`
- Sections `bestindex5-1.1` through `bestindex5-3.5`.

Behavior covered:

- Virtual-table `xBestIndex` constraint propagation for `!=`, `IS`, `IS NOT`,
  `IS NULL`, and `IS NOT NULL`.
- Commuted comparison constraints and join-derived usable constraints.
- Row-value `IS` constraints split into individual virtual-table column
  constraints.
- INTEGER-affinity residual comparisons for virtual-table scans.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex5VirtualTableDynamicTest.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex5VirtualTableDynamicTest.php` passed with `1 test files, 14747 assertions, 0 failures` and `1003` PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex5VirtualTableDynamicTest.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php` passed with `2 test files, 79913 assertions, 0 failures`.

Expected movement:

- Count as focused PHP PASS-line growth: `+1003` PASS lines from the new
  `SQLiteRealUpstreamBestIndex5VirtualTableDynamicTest.php` file.
- Mapped denominator coverage is already complete at `1589 / 1589`; no
  denominator movement claimed.

Non-overlap:

- This slice does not repeat accepted `bestindex2`, `bestindex3`, or
  `bestindex4` behavior. It targets upstream `bestindex5.test`, which was not
  present in the B-tree/index dynamic corpus source-file inventory before this
  patch.

Dependency closure:

- No new support component is needed. The slice reuses lane-local B-tree/index
  dynamic corpus planning plus existing virtual-table constraint, row-value,
  and affinity residual helpers.
