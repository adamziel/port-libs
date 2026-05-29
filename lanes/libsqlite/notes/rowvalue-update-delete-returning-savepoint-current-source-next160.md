# rowvalue-update-delete-returning-savepoint-current-source-next160

Status: focused PHP behavior growth for row-value `UPDATE`/`DELETE ... RETURNING`
inside an explicit `ROLLBACK TO` savepoint.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan`.
It models a WordPress `wp_options` cleanup/import batch where a prepared outer
update is visible at the savepoint image, a protected row-value update and
delete both produce attempted `RETURNING` rows, and an explicit rollback to the
savepoint discards those protected yields. Later update/delete statements
restart from the savepoint image, not from the attempted protected current
source.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext160Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-update-delete-returning-savepoint-current-source-next160.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext160Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-update-delete-returning-savepoint-current-source-next160.php --self-test
```

Focused test delta: `+62` assertions / PASS lines in
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext160Test.php`.
Expected dashboard movement: `phpPass` `70891 -> 70953`; mapped upstream
coverage remains `608 / 1589`.

Dependency closure: no new support component is needed. The slice reuses the
lane-local row-value `UPDATE`/`DELETE RETURNING` executor and savepoint
current-source modeling.

Non-overlap: avoids accepted next148 DISTINCT retry, next156 conflict-yielding,
next157 nested inner-savepoint rollback, trigger/FK RETURNING savepoint,
WAL/pager/VFS savepoint application, B-tree, JSON, PRAGMA, compound SELECT,
and encoding clusters. The new behavior is explicit `ROLLBACK TO` over a mixed
protected row-value UPDATE+DELETE RETURNING batch, with following statements
restarted from the savepoint image and protected `RETURNING` rows suppressed.
