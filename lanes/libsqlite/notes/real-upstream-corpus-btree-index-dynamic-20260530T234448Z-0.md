# real-upstream-corpus-btree-index-dynamic-20260530T234448Z-0

Base accepted HEAD: `1e28a5dbe5f8813a907a64ec2d403f8339418de7`.

Added a non-overlapping B-tree/index dynamic corpus batch from hydrated
upstream SQLite `test/index5.test`, sections `index5-1.1` through `index5-1.3`.
The batch models the upstream 1024-byte page, 100000-row `CREATE INDEX i1 ON
t1(x)` VFS `xWrite` trace and preserves the upstream assertion that forward
page writes dominate backward plus noncontiguous writes.

Focused movement:

- `SQLiteBTreeIndexDynamicCorpusIndex5Test.php`: 1002 TestRunner PASS lines,
  15008 assertions, 0 failures.
- Expected lane-local selected pass movement: `1170170 -> 1171172`.
- Mapped denominator remains `1589 / 1589`; this is PASS-line growth, not new
  mapped denominator growth.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusIndex5Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusIndex5Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this reuses the
lane-local B-tree/index dynamic corpus planner and VFS write-offset accounting.

Root harness: not run - isolated micro-slice.
