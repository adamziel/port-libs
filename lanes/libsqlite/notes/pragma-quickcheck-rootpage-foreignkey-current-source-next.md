# pragma-quickcheck-rootpage-foreignkey-current-source-next

Slice: `pragma-quickcheck-rootpage-foreignkey-current-source-next`.

This adds `SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan`,
a resumable current/next source stream that combines `PRAGMA quick_check`
rootpage diagnostics with `PRAGMA foreign_key_check` rows annotated by child
and parent rootpage / pointer-map status. The source hash includes current and
next database images, catalogs, schema rowsets, normalized FK SQL, normalized
quick_check SQL, and quick_check table scope, so stale resumes fail after
rootpage, FK, schema, catalog, or target-scope drift.

Application path: copied `wp_options` import preflight can page one combined
stream and only resume when quick_check rootpage blockers and FK rootpage
blockers still describe the same current/next database images.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNext142Test.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/examples/application-pragma-quickcheck-rootpage-foreignkey-current-source-next.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNext142Test.php`
  - `1 test files, 79 assertions, 0 failures`
  - 60 focused PASS lines
- `php lanes/libsqlite/examples/application-pragma-quickcheck-rootpage-foreignkey-current-source-next.php --self-test`
  - `application-pragma-quickcheck-rootpage-foreignkey-current-source-next self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap: avoids accepted quickcheck/index/FK next138, FK/rootpage current
next140, table-level index/rootpage cursors next133, and table-valued FK/root
pagination next120 by adding a combined quick_check-rootpage plus FK-rootpage
current/next source and cursor contract.

Dependency closure: no new support component is needed. The slice reuses the
existing native SQLite schema/rootpage parser, attached schema catalog, FK
pragma executor, pointer-map rootpage analysis, and page fixture assemblers.
