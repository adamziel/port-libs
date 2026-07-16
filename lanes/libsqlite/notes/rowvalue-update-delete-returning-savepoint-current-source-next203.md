# rowvalue-update-delete-returning-savepoint-current-source-next203

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
savepoint current-source behavior.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext203Plan`
for SQLite `UPDATE OR IGNORE` and `UPDATE OR REPLACE` behavior inside one
savepoint. The new current-source boundary proves ignored row-value conflicts
yield no `RETURNING` rows and restore their source rows, while a later
`OR REPLACE` row-value update deletes its conflicting current row before a
follow-up `DELETE ... RETURNING` reads that replaced source.

Application smoke:
`application-rowvalue-update-delete-returning-savepoint-current-source-next203.php`
models copied `wp_options` cleanup where duplicate `(blog_id, autoload)` pairs
are first suppressed by `OR IGNORE`, then one orphaned option replaces a
conflicting site option and the final delete observes that replacement.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext203Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext203Test.php
php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next203.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext203Test.php
php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next203.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
```

Expected dashboard delta: `phpPass` moves from `97068` to `97127` from 59 newly
passing focused PASS lines. Mapped upstream coverage remains `619 / 1589`; this
is current-source PHP executor behavior over already mapped row-value
UPDATE/DELETE RETURNING inventory rather than a newly hydrated upstream Tcl
unit.

Non-overlap: avoids accepted next176 nullable row-value equality/inequality,
next192 `OR ABORT` statement rollback, next193 fail-stream rollback, next196
`OR FAIL` prefix-preservation, trigger/view RETURNING, savepoint page-image,
WAL byte truncation, and pager/VFS current-source clusters. The new surface is
specifically row-value `UPDATE OR IGNORE` suppression followed by `UPDATE OR
REPLACE` conflict deletion and DELETE RETURNING against that current source.

Dependency closure: no new support component is needed. The slice reuses the
native PHP UPDATE/DELETE RETURNING executor's conflict actions and adds a
bounded savepoint/current-source behavior plan around them.

Next task: continue with a non-overlapping row-value executor gap only if it
adds comparable focused test growth; otherwise pivot to higher-yield SQL/WAL/
B-tree/JSON current-source behavior.
