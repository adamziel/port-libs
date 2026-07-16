# trigger-returning-savepoint-view-current-source-next136

Status: focused PHP behavior growth for INSTEAD OF view-trigger `RETURNING`
across current-source and next-source savepoint boundaries.

This slice adds `SQLiteTriggerReturningSavepointViewCurrentSourceNextPlan`.
It reuses the native view-trigger/savepoint executor and records which
current-source `RETURNING` rows are admitted or suppressed when the current
phase rolls back to its savepoint before the next source is run. The next
source is explicitly tagged as reading from the saved current image, so copied
Application import diagnostics can distinguish suppressed current rows from
admitted next-source rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningSavepointViewCurrentSourceNext136Test.php`
- `php -l lanes/libsqlite/src/SQLiteTriggerReturningSavepointViewCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerReturningSavepointViewCurrentSourceNext136Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-returning-savepoint-view-current-source-next136.php`
- `php lanes/libsqlite/examples/application-trigger-returning-savepoint-view-current-source-next136.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: +65 focused PASS lines after integration, with
`phpPass` updated from `57457` to `57522`. Mapped coverage remains `606 / 1589`
because this is focused PHP behavior over already mapped trigger/view/RETURNING
and savepoint surfaces.

Non-overlap: avoids accepted DML trigger RETURNING conflict handling,
transaction savepoint trigger rollback, trigger/view savepoint recursion
next123, deferred FK release-barrier next127, recursive/deferred view-trigger
RETURNING next131, row-value RETURNING/savepoint next133, and WAL/pager/VFS/
B-tree/JSON/encoding clusters. The new surface is source-transition admission
accounting when a view-trigger current phase rolls back but next-source
`RETURNING` rows are still admitted from the saved current source.

Dependency closure: no new support component is needed. The slice reuses
native PHP schema catalog, view-trigger yielding, RETURNING projection, and
savepoint rollback primitives; it does not require ext/sqlite, upstream
binaries, network services, or a new shared dependency row.
