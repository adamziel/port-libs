# PRAGMA index_xinfo foreign_key current-source next703-718

## Scope

Extends the consolidated `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`
current-source wrappers from next687-702 through next703-718. The slice keeps
using the shared `actionRelationshipDiagnosticPage311()` factory for mixed
foreign-key action diagnostics over `PRAGMA foreign_key_list`, child
`PRAGMA table_info`, `PRAGMA index_list`, and `PRAGMA index_xinfo`.

## Coverage

- next703-718 repeat the accepted order, collation, and DESC child lookup
  mismatch status rotation as a direct follow-on to next687-702.
- The focused tests assert current clean lookup rows stay empty, next broken
  lookup rows appear once, operation names are slice-specific, and action
  column/action metadata is preserved.
- The WordPress example self-test verifies all page methods exist and the
  current/next row counts remain `0 -> 1` for each next703-718 status.

## Validation

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext703718Test.php
php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next703-718.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext703718Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext687702Test.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next703-718.php --self-test
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next687-702.php --self-test
git diff --check
```

## Non-overlap

This touches only the consolidated PRAGMA index_xinfo/foreign-key
current-source lane plus the directly corresponding next703-718 test, example,
and note. It does not add duplicate numbered source classes or alter unrelated
PRAGMA, pager, planner, or upstream-runner slices.
