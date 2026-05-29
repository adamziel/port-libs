# pragma-quickcheck-index-foreignkey-current-source-next

Slice: `pragma-quickcheck-index-foreignkey-current-source-next`.

Behavior: adds `SQLitePragmaQuickcheckIndexForeignKeyCurrentSourceNext`, a
current-source cursor that composes table-level `PRAGMA index_list`, per-index
`PRAGMA index_xinfo`, `PRAGMA quick_check` rootpage diagnostics, and scoped
`PRAGMA foreign_key_check` rows under one source hash and pagination contract.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaQuickcheckIndexForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 84 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pragma-quickcheck-index-foreignkey-current-source-next.php --self-test`
  - `wordpress-pragma-quickcheck-index-foreignkey-current-source-next self-test passed`

Non-overlap: avoids accepted quickcheck/index_xinfo single-index pagination
next103, table-level index integrity cursor next133, quickcheck/FK/rootpage
next132, PRAGMA FK rootpage/pointer-map next122/127/128, and accepted
PRAGMA index/FK current-source batch135 behavior. The new surface is the
combined table-index enumeration plus quick_check plus foreign_key_check
cursor with stale database/catalog/schema/SQL resume rejection.

Dependency closure: no new support component is needed. The slice reuses the
existing native schema catalog, PRAGMA row cursors, quick_check diagnostics,
and foreign-key checker.
