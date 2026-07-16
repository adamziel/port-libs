# rowvalue-update-delete-returning-savepoint-current-source-next196

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
savepoint current-source behavior.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext196Plan`
for SQLite `UPDATE OR FAIL ... RETURNING` semantics inside a savepoint. Unlike
the accepted next192 `OR ABORT` surface, `OR FAIL` preserves row changes made
before the constraint violation in the same statement. The new plan records
those yielded prefix rows as the current source, keeps the savepoint active,
then proves retry UPDATE/DELETE RETURNING statements read the partially changed
current source.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext196Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext196Test.php
php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next196.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext196Test.php
php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next196.php
git diff --check -- lanes/libsqlite
```

Dashboard delta: `phpPass` moves from `94386` to `94454` from 68 newly passing
focused PASS lines. Mapped upstream coverage remains `604 / 1589` in this
worktree's current accepted source because this is current-source executor
behavior over already mapped row-value/update-delete RETURNING inventory rather
than a newly hydrated upstream Tcl unit.

Non-overlap: avoids accepted next133 row-value `IS` / `IS NOT`, next176 nullable
row-value equality/inequality, next192 `OR ABORT` statement rollback,
savepoint page-image/WAL byte truncation, trigger/view RETURNING, and WAL/pager
current-source clusters. The new surface is specifically row-value
`UPDATE OR FAIL ... RETURNING` preserving successful prefix changes inside a
savepoint before retry UPDATE/DELETE statements.

Dependency closure: no new support component is needed. The slice reuses the
native PHP UPDATE/DELETE RETURNING executor's existing conflict-preservation
mode and adds a bounded savepoint/current-source behavior plan around it.

Next task: continue with a non-overlapping row-value executor gap or pivot to
another higher-yield libsqlite current-source behavior bucket after integration.
