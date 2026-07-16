# pragma-foreignkey-integrity-partial-root-current-source-next128

This slice extends `SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield` with
table-scoped root integrity SQL for `PRAGMA integrity_check(table)` and
`PRAGMA quick_check(table)` while preserving the existing full-database default.

Behavior added:

- current-source hashes now include normalized integrity SQL, scope, and target;
- table-scoped integrity filters root-page diagnostics to the target table and
  its indexes before appending `foreign_key_check` rows;
- stale cursors are rejected when a resume changes from table-scoped integrity
  to full-database integrity;
- missing or unsupported integrity targets fail fast instead of silently
  resuming over the wrong root set.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIntegrityPartialRootCurrentSourceNext128Test.php
# 1 test files, 103 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyRootIntegrityCurrentSourceNext117Test.php lanes/libsqlite/tests/SQLitePragmaForeignKeyRootIntegrityCurrentSourceNext120Test.php
# 2 test files, 192 assertions, 0 failures

php lanes/libsqlite/examples/application-pragma-foreignkey-integrity-partial-root-current-source-next128.php --self-test
# application-pragma-foreignkey-integrity-partial-root-current-source-next128 self-test passed
```

New focused PASS-line delta: `+66` from
`SQLitePragmaForeignKeyIntegrityPartialRootCurrentSourceNext128Test.php`.

Non-overlap:

This avoids accepted partial-index integrity next126, root/FK current-source
pagination next117/next120, rootpage/pointer-map/FK next122, FK/index/root
next125, and broad pointer-map/freelist integrity slices. The new behavior is
the table-scoped root-diagnostic filter inside the existing FK/root current
source stream, not another standalone partial-index checker or FK parser.

Dependency closure:

No new support component is needed. The slice reuses native PHP SQLite database
image parsing, schema record parsing, rootpage integrity analysis, and
foreign-key check helpers.
