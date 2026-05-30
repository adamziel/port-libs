# yield-trigger-recursive-view-upsert-current-source-next234

Status: focused PHP behavior growth for recursive INSTEAD OF view UPSERT current-source admission before next-source rows are published.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext234Plan`. It builds on the accepted next231 recursive view RETURNING cursor-close handoff and adds a later current-source guard: next-source RETURNING rows remain held until every current-source UPSERT conflict-key decision has a matching receipt for the current view and trigger source.

Application smoke: `application-trigger-recursive-view-upsert-current-source-next234.php` covers a copied `wp_options` recursive import view where current-source recursive UPSERT rows publish first, and staged `home` / `next_plugin` rows become visible only after the current conflict-key receipts are acknowledged.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext234Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 91 assertions, 0 failures
```

Expected dashboard movement: `phpPass +91`, from `115305` to `115396`. Mapped upstream coverage remains `637 / 1589`; this is current-source PHP behavior over already mapped trigger/view/UPSERT inventory rather than a fresh upstream manifest row.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view trigger RETURNING, next231 cursor-close, current-source, and UPSERT conflict-key receipt modeling.

Non-overlap: this adds recursive INSTEAD OF view UPSERT conflict-key receipt admission after accepted next231 cursor-close behavior. It avoids accepted next230-next231 recursive view RETURNING close/epoch surfaces, batch202 trigger recursive/view RETURNING coverage, trigger RETURNING conflicts, row-value savepoints, schema reparse, WAL/VFS, JSON, planner, encoding, and B-tree clusters.
