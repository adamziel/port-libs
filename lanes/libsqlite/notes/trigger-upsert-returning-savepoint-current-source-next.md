# trigger-upsert-returning-savepoint-current-source-next129

Status: focused behavior growth for trigger/UPSERT/RETURNING savepoint current-source semantics.

This slice adds `SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan`, a bounded native PHP model for a current savepoint where an UPSERT emits RETURNING rows, a later trigger abort rolls back the current savepoint, and the next retry starts from the saved source rather than the attempted current source. The attempted RETURNING yield is retained as diagnostic evidence, while committed `current_returning_rows` are suppressed after rollback.

Application smoke: `application-trigger-upsert-returning-savepoint-current-source-next129.php` models a copied `wp_options` plugin import where a site URL retry must not inherit the discarded current attempt after a bad plugin trigger rolls back the savepoint.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerUpsertReturningSavepointCurrentSourceNext129Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-upsert-returning-savepoint-current-source-next129.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningSavepointCurrentSourceNext129Test.php`
- `php lanes/libsqlite/examples/application-trigger-upsert-returning-savepoint-current-source-next129.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted trigger/upsert savepoint next73, trigger RETURNING savepoint next64/65/68, trigger FK/upsert RETURNING next75, recursive trigger/upsert RETURNING next118/126, deferred/view RETURNING savepoint next119/120/123, savepoint-trigger rollback, VFS/WAL/B-tree/JSON/planner/encoding clusters, and the accepted batch127 trigger deferred RETURNING view behavior. The new boundary is the current-source retry after RETURNING rows have been yielded but then suppressed by savepoint rollback.

Dependency closure: no new support component is needed. This reuses lane-local row-array trigger, UPSERT, RETURNING, and savepoint modeling.
