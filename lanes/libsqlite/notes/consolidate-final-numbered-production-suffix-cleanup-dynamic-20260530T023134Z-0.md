# PRAGMA Index Xinfo Foreign Key Helper Consolidation

Consolidated the production helper `childIndexRows204()` into the stable
`childIndexRows()` helper on `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`.
The direct current-source next204 test now calls the stable helper while keeping
the existing `page204()` output keys, dependency labels, action labels, and row
metadata unchanged.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext204Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext204Test.php`
  - `1 test files, 60 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext*Test.php`
  - `168 test files, 13862 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is production
helper naming consolidation only.

Non-overlap: avoided pager, STAT4, compound, row-value, trigger, JSON table,
and B-tree suffix-cleanup families; this patch only touches the PRAGMA
index_xinfo/foreign-key child-index helper and its direct test.
