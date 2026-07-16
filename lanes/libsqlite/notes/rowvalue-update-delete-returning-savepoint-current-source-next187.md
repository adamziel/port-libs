# rowvalue-update-delete-returning-savepoint-current-source-next187

Status: focused PHP behavior growth for current-source row-value UPDATE/DELETE
RETURNING savepoint retry after an `OR ABORT` uniqueness conflict.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext187Plan`.
It models a Application `wp_options` import cleanup where outer transaction work
has already updated/deleted rows, a savepoint batch deletes and updates rows
with row-value `IN (VALUES ...)` predicates, and a later `UPDATE OR ABORT`
hits the `(blog_id, option_name)` unique key. The abort rolls back only the
savepoint batch, discards attempted savepoint RETURNING rows, preserves the
outer current source, and retries UPDATE/DELETE from the savepoint image.

Application smoke:
`application-rowvalue-abort-savepoint-current-source-next187.php` covers copied
`wp_options` rewrite-rule and transient cleanup where the outer rewrite-rule
change remains visible while the aborted savepoint transient/orphan work is
retried.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext187Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

Expected dashboard movement: `phpPass +55`, from `88817` to `88872`. Mapped
upstream coverage remains `616 / 1589`; this is current-source PHP behavior
over already mapped row-value UPDATE/DELETE RETURNING and savepoint primitives.

Non-overlap: avoids accepted row-value `OR ROLLBACK` transaction rollback,
row-value `VALUES` savepoint next184, NULL inequality next176, `IGNORE`/`IN`/
release-inner rollback next180-184, row-value assignment parsing, trigger/FK
RETURNING clusters, and pager/WAL/VFS savepoint application surfaces. The new
surface is specifically `OR ABORT` savepoint rollback preserving outer
transaction current-source changes while discarding attempted savepoint
RETURNING streams.

Dependency closure: no new support component is needed. The slice reuses
lane-local native PHP row-value UPDATE/DELETE RETURNING execution and bounded
current-source savepoint modeling.

Next task: continue with a non-overlapping SQL executor/planner or pager/VFS
durability gap; avoid another row-value savepoint variant unless it proves a
different conflict action or upstream runner blocker.
