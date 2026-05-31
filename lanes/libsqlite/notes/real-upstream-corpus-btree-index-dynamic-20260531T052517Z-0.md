# real-upstream-corpus-btree-index-dynamic-20260531T052517Z-0

Base accepted HEAD: `e6f2f82c55065569a50189235fcdfbfbb9091c15`.

Added focused real-upstream coverage for SQLite `test/btree02.test` sections
`btree02-100` and `btree02-110`, covering repeated
`saveCursorPosition()`/`restoreCursorPosition()` behavior while a WITHOUT ROWID
primary-key cursor is scanned through secondary index `t1a` and the underlying
table is mutated across commits.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtree02SkipNextRestoreDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtree02SkipNextRestoreDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtree02SkipNextDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtree02SkipNextRestoreDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` was attempted; this accepted base does not contain that guard path.
- `git diff --check -- lanes/libsqlite`

Focused delta: `1002` new TestRunner PASS cases in the restore companion file,
with `22006` focused assertions. The existing mutation-shape file remains
unchanged.

Dependency closure: no new support component needed; this reuses the
lane-local B-tree/index dynamic corpus planner and cursor restore diagnostics.
