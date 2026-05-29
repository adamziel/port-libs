# pragma-index-rootpage-quickcheck-current-source-next

This consolidation replaces the numbered
`SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext129` and
`SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext146` production classes
with the canonical `SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext`
class. The canonical class keeps both accepted entry points:

- `page()` for the single-image quick-check current-source paginator.
- `currentNextPage()` for the current/next quick-check repair gate.

The behavior remains scoped to copied WordPress SQLite images: `PRAGMA
index_xinfo` rows stay tied to the same database/catalog source hash, exact
`PRAGMA quick_check` rows are appended and enriched with rootpage diagnostics,
and current/next admission rejects stale cursors until the next database image
has cleared target index rootpage corruption.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexRootpageQuickcheckCurrentSourceNextPageTest.php lanes/libsqlite/tests/SQLitePragmaIndexRootpageQuickcheckCurrentSourceNextCurrentNextPageTest.php
# 2 test files, 162 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-pragma-index-rootpage-quickcheck-current-source-next-page.php --self-test
# wordpress-pragma-index-rootpage-quickcheck-current-source-next-page self-test passed

php lanes/libsqlite/examples/wordpress-pragma-index-rootpage-quickcheck-current-source-next-current-next-page.php --self-test
# wordpress-pragma-index-rootpage-quickcheck-current-source-next-current-next-page self-test passed
```

Non-overlap: this is a consolidation-only patch for the assigned
`SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext` family. It does not add a
new numbered current-source behavior slice and does not touch PRAGMA
index_xinfo/foreign-key, VFS, attach, WAL, planner, or unrelated quick_check
families.

Dependency closure: no new support component is needed. The slice reuses the
lane-local SQLite page-image parser, attached schema catalog,
`SQLitePragmaIntegrityCheck`, and current-source hashing already present under
`lanes/libsqlite`.
