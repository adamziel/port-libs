# rowvalue-update-delete-returning-savepoint-current-source-next165

Status: focused PHP behavior growth for row-value `UPDATE OR IGNORE` plus `DELETE ... RETURNING` inside a savepoint.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext165Plan`. It models a Application `wp_options` import savepoint where row-value `UPDATE OR IGNORE ... RETURNING` attempts duplicate `(blog_id, option_name)` keys, suppresses those rows from the `RETURNING` stream, restores each ignored row to its pre-update image, and continues later `DELETE ... RETURNING` plus `UPDATE ... RETURNING` statements from that current source. The savepoint is released normally because `OR IGNORE` is not a rollback conflict action.

Application smoke: `application-rowvalue-update-delete-returning-savepoint-current-source-next165.php` covers copied option staging where duplicate `siteurl` rewrites are ignored, transient cleanup still yields deleted rows, and the next row-value update sees the original ignored source row.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext165Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext165Test.php
php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next165.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext165Test.php
php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next165.php
git diff --check -- lanes/libsqlite
```

Expected dashboard delta: `phpPass` moves from `74089` to `74152` from 63 newly passing focused PASS lines. Mapped upstream coverage remains `610 / 1589`; this is fresh PHP behavior over already mapped row-value DML/savepoint surfaces rather than a new upstream inventory unit.

Non-overlap: this avoids accepted next157 nested rollback discard/retry, next162 `OR FAIL` rollback-to-savepoint preservation, next146 `OR ROLLBACK` transaction rollback, next144 nested DELETE rollback, next133 row-value `IS` / `IS NOT`, next134 UPSERT conflict `RETURNING`, trigger RETURNING clusters, and WAL/pager/VFS savepoint application clusters. The new surface is specifically `UPDATE OR IGNORE` row-value conflict handling where ignored rows yield no `RETURNING` rows and following UPDATE/DELETE statements continue inside the same released savepoint.

Dependency closure: no new support component is needed. The slice reuses the lane-local `SQLiteUpdateDeleteReturningSql` row-value executor and adds bounded savepoint current-source orchestration.

Next task: continue with broader SQL executor/planner correctness or another non-overlapping row-value current-source edge; avoid repeating rollback/fail/ignore conflict variants unless the behavior reaches parser-level SQL execution not covered here.
