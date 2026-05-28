# PRAGMA Index Rootpage Quickcheck Current Source Next146

This slice adds `SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext146`, a
current/next admission helper for copied WordPress SQLite database images. It
combines existing `PRAGMA index_xinfo(...)` metadata with `PRAGMA quick_check`
rootpage diagnostics so a migration runner can reject stale cursors and only
resume expression-index analysis when the next database image has cleared the
target index rootpage corruption.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext146Test.php`
- Result: `1 test files, 82 assertions, 0 failures`
- PASS-line delta: `+82`

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-pragma-index-rootpage-quickcheck-current-source-next146.php --self-test`
- Result: `wordpress-pragma-index-rootpage-quickcheck-current-source-next146 self-test passed`

Non-overlap:

- Avoids accepted next129 single-image index rootpage quickcheck behavior by
  adding current/next delta admission and stale cursor validation.
- Avoids accepted next132/136/141 foreign-key/root quickcheck clusters by not
  adding FK rows or index-list current/next behavior.
- Avoids accepted batch141 PRAGMA quick_check rootpage FK behavior by focusing
  on a single expression index's `index_xinfo` quickcheck repair gate.

Dependency closure:

- No new support component is needed. The slice reuses existing schema catalog,
  page assembly, pointer-map, rootpage integrity, and quick_check primitives.
