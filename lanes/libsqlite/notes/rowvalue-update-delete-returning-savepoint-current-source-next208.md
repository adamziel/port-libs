# rowvalue-update-delete-returning-savepoint-current-source-next208

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING `OR FAIL` current-source handling inside an active savepoint.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext208Plan`. It models the upstream SQLite boundary where `UPDATE OR FAIL ... RETURNING` preserves rows changed before the first unique conflict, exposes the partial RETURNING stream to the current statement, lets follow-up UPDATE/DELETE RETURNING statements read that partial current source, and then `ROLLBACK TO` the still-active savepoint discards the partial FAIL and retry changes.

Application smoke: `application-rowvalue-fail-savepoint-current-source-next208.php` models a copied `wp_options` plugin import where a row-value multisite option rewrite hits a `(blog_id, option_name)` conflict after one row has already yielded RETURNING output.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext208Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 77 assertions, 0 failures
```

Expected dashboard delta: `phpPass` moves from `100087` to `100164` from 77 newly passing focused PASS lines. Mapped upstream coverage remains `621 / 1589`; this is current-source PHP behavior over already mapped row-value UPDATE/DELETE RETURNING and savepoint inventory rather than a fresh upstream denominator row.

Non-overlap: avoids accepted rowvalue next156/158/161/172/178/180/189/192/200/206 savepoint rollback, ABORT statement rollback, released-inner outer rollback, and retry-after-inner rollback surfaces. The new surface is specifically `OR FAIL` partial row preservation and RETURNING visibility before a later `ROLLBACK TO` savepoint discards that partial current source.

Dependency closure: no new support component is needed. The slice reuses the existing native PHP UPDATE/DELETE RETURNING executor, its `OR FAIL` partial-change switch, row-value predicates, and row-array savepoint modeling.

Next task: continue with non-overlapping SQL executor/planner, B-tree, WAL, JSON, or encoding closure gaps; avoid another row-value savepoint variant unless it removes a named upstream runner blocker or adds materially distinct assertion growth.
