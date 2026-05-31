# real-upstream-corpus-btree-index-dynamic-20260531T073754Z-0

Base accepted HEAD: `9c30c680e4b44fbeb2fc11612b28622bb7d8e322`.

Implemented a focused real-upstream B-tree/index dynamic corpus slice from
SQLite upstream `test/index.test`, sections `index-18.1` through `index-18.5`.
The new corpus covers reserved `sqlite_` schema-object names for table, index,
view, and trigger creation, plus the following explicit `DROP TABLE t7`
cleanup. This is non-overlapping with the accepted broad late-lifecycle index
aggregate because it adds a direct dynamic helper and focused test file for the
reserved-name behavior itself.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - passed
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexReservedSchemaNameDynamicTest.php`
  - passed
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexReservedSchemaNameDynamicTest.php`
  - `1 test files, 13839 assertions, 0 failures`
  - `1003` focused PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run: guard path does not exist in this worktree
- `git diff --check -- lanes/libsqlite`
  - passed

Status delta:

- `lanes/libsqlite/lane-status.json` `phpPass` moved from `2717884` to
  `2718887` for the verified `+1003` focused PASS lines.
- Mapped denominator coverage is unchanged.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local
  B-tree/index dynamic corpus planner and schema catalog validation helpers.

Follow-up:

- Continue B-tree/index real-upstream dynamic coverage on sections not already
  represented by focused dynamic files, or fix the remaining broad release/all
  runner clusters before libsqlite closure.
