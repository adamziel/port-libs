# PRAGMA quickcheck foreign-key rootpage current-source next145

This slice extends the existing quick_check plus foreign_key_check rootpage
stream with resolved foreign-key PRAGMA target metadata on each FK row:
`pragma_schema`, `target_schema`, `target`, and `target_source`.

2026-05-30 follow-up for `pragma-quick-check-current`: the quick_check parser
now accepts SQLite's quoted schema-prefix forms, such as
`PRAGMA "archive".quick_check(wp_archive_options)`. The same identifier-prefix
handling is shared with table-scoped `PRAGMA "main".quick_check(...)` and
`PRAGMA "main".integrity_check(...)` dispatch, preserving quoted attached
schema SQL in source identity instead of rejecting the current quick-check
preflight before rows are collected.

The focused coverage uses a WordPress archive attachment and verifies:

- schema-qualified table-valued `archive.pragma_foreign_key_check(...)` rows
  keep their attached schema/target provenance while sharing the quick_check
  rootpage stream;
- quoted qualified target strings such as
  `pragma_foreign_key_check('archive.wp_archive_options')` report
  `qualified-target`;
- current/next pagination and stale source cursors still reject mismatched
  database/schema sources;
- dirty current archive rootpage and FK rows clear against the next source.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext145Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 44 assertions, 0 failures
```

WordPress smoke:

```text
$ php lanes/libsqlite/examples/wordpress-pragma-quickcheck-foreignkey-rootpage-current-source-next145.php --self-test
wordpress-pragma-quickcheck-foreignkey-rootpage-current-source-next145 self-test passed
```

Follow-up verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext145Test.php lanes/libsqlite/tests/SQLitePragmaIntegrityTableScopeCurrentNext65Test.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 145 assertions, 5 failures

$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext145Test.php lanes/libsqlite/tests/SQLitePragmaIntegrityTableScopeCurrentNext65Test.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 154 assertions, 0 failures
```

Dependency closure: no new support component is needed. The slice reuses the
lane-local attached schema catalog, PRAGMA foreign-key parser, quick_check
rootpage analysis, and current-source cursor primitives.

Non-overlap: avoids accepted next142 quick_check/FK rootpage clearing,
accepted next140 FK rootpage integrity pagination, accepted JSON/WAL/B-tree/VFS
clusters, and batch141 PRAGMA quick_check rootpage foreign-key behavior. The
new behavior is only resolved attached-schema table-valued FK PRAGMA
provenance inside the combined current/next rootpage stream.
