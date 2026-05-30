# PRAGMA foreign key pointer-map integrity current next52

This slice adds `SQLitePragmaForeignKeyPointerMapIntegrityYield`, a bounded
current/next52 yield over the two integrity surfaces a Application import repair
screen needs to page together: pointer-map/freelist integrity diagnostics and
`PRAGMA foreign_key_check` rows. The row shape keeps pointer-map page metadata
and foreign-key schema/table/rowid metadata in one cursor without materializing
an unbounded UI page.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyPointerMapIntegrityCurrentNext52Test.php`
  - Result: `1 test files, 84 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLitePragmaForeignKeyPointerMapIntegrityYield.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaForeignKeyPointerMapIntegrityCurrentNext52Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-foreignkey-pointermap-integrity-current-next52.php`
  - Result: no syntax errors in all three changed PHP files.
- `php lanes/libsqlite/examples/application-pragma-foreignkey-pointermap-integrity-current-next52.php`
  - Result: `status=ok`, `count=52`, `total=55`, `next_offset=52`,
    `integrity_pointer_map=53`, `foreign_key_violations=2`.
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no whitespace errors.

Non-overlap: this does not repeat accepted current/next32 raw integrity plus
foreign-key pagination, current/next48 pointer-map/freelist-only pagination,
batch49 freelist/foreign-key preflight blockers, or accepted B-tree
page-move/root-collapse/overflow-freelist/VFS/WAL/JSON/SELECT clusters. The new
surface is the normalized combined yield for pointer-map integrity rows and
foreign-key rows with a current/next52 page boundary.

Dependency closure: no new support component is needed. This reuses the
lane-local native PHP integrity checker, pointer-map/freelist classifier, and
foreign-key checker.
