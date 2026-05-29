# pragma-integrity-quickcheck-partial-index-current-source-next

This slice adds bounded current-source `PRAGMA integrity_check` /
`quick_check` coverage for partial-index membership over copied WordPress
`wp_options` rows.

Behavior:

- full `integrity_check` reports missing partial-index entries, stale entries
  for rows that no longer satisfy the partial `WHERE` clause, and orphan index
  entries whose table row was deleted;
- `quick_check` keeps the shallow upstream shape and reports missing required
  entries without doing the deeper stale/orphan index-entry walk;
- current-source pagination carries a source hash, next cursor, stale cursor
  rejection, offset validation, counts, and per-row diagnostics.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIntegrityPartialIndexCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIntegrityPartialIndexCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-integrity-partial-index-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityPartialIndexCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-integrity-partial-index-current-source-next.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses the
existing `SQLiteIndexPredicate` model and lane test runner; it does not require
new file-format, VFS, pager, or upstream-runner support.

Non-overlap: this avoids accepted index rootpage, pointer-map/freelist,
quick_check/index_xinfo pagination, partial-index planner proof/range-cost,
B-tree overflow/freeblock, VFS/WAL, JSON table, and FK integrity clusters. It
is limited to partial-index membership diagnostics for current row/index
snapshots.
