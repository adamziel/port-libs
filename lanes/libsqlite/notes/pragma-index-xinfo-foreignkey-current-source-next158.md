# pragma-index-xinfo-foreignkey-current-source-next158

Behavior slice: adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`,
a current/next source comparator that combines `PRAGMA index_xinfo(...)`
metadata, rootpage integrity rows, and `PRAGMA foreign_key_check(...)` rows
under one stable cursor.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 69 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next158.php --self-test
# wordpress-pragma-index-xinfo-foreignkey-current-source-next158 self-test passed
```

Dependency closure: reuses existing native PHP schema catalog, index_xinfo,
integrity-root, and foreign_key_check primitives. No new support component is
needed.

Non-overlap: this does not repeat accepted `index_list`/FK/rootpage next148,
quickcheck/integrity rootpage variants, or table-valued `foreign_key_list`
catalog rows. The new behavior is specifically `index_xinfo` plus
`foreign_key_check` current/next source admission and cursor validation.
