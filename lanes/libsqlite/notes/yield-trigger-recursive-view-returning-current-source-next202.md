# yield-trigger-recursive-view-returning-current-source-next202

Status: focused PHP behavior growth for recursive view trigger RETURNING current-source generation and recursive-depth fencing before next-source rows are published.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext202Plan`. It builds on the accepted next196 recursive child drain model and adds a later current-source guard: even after next196 allows next-source publication, the next view generation is held until the current view generation token matches and every required recursive depth has been acknowledged.

WordPress smoke: `wordpress-trigger-recursive-view-returning-current-source-next202.php` covers a copied `wp_options` recursive import view where following current rows and recursive child RETURNING rows must publish under the current view generation before next-source `home` / `next_plugin` rows become visible.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext202Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 77 assertions, 0 failures
```

Expected dashboard movement: `phpPass +77`, from `97068` to `97145`. Mapped upstream coverage remains `619 / 1589`; this is current-source PHP behavior over already mapped trigger/view/RETURNING inventory rather than a fresh upstream manifest row.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view trigger RETURNING, next196 child-drain, cursor, and current-source modeling.

Non-overlap: this adds current view generation and recursive depth acknowledgement fencing after accepted next196 child-ordinal drains. It avoids next195 receipt fences, next196 child drain, row-value RETURNING, schema reparse, FK, WAL, VFS, JSON, planner, and B-tree slices.
