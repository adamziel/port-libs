# trigger-upsert-returning-view-current-source-next140

Status: focused PHP behavior growth for current-source `INSTEAD OF` view-trigger UPSERT `RETURNING` through all table UNIQUE constraints.

Implemented behavior:

- `SQLiteTriggerReturningUpsertViewCurrentNextPlan` now forwards the full table unique-constraint list into the row-level UPSERT/trigger/FK executor instead of only checking the UPSERT conflict target.
- Empty statements validate malformed unique-constraint metadata before execution, matching parser-time schema admission behavior.
- Secondary UNIQUE conflicts on `option_id`, including conflicts introduced by AFTER triggers, roll back the view-trigger statement, restore parent/child rows, reset changes, and suppress previously yielded `RETURNING` rows.
- A Application smoke covers copied `wp_options` import rows routed through an `INSTEAD OF` view trigger where the `ON CONFLICT(option_name)` update collides with a distinct UNIQUE `option_id`.

Verification:

```sh
$ php -l lanes/libsqlite/src/SQLiteTriggerReturningUpsertViewCurrentNextPlan.php && php -l lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewUniqueCurrentSourceNext140Test.php && php -l lanes/libsqlite/examples/application-trigger-upsert-returning-view-unique-current-source-next140.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerReturningUpsertViewCurrentNextPlan.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewUniqueCurrentSourceNext140Test.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-upsert-returning-view-unique-current-source-next140.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewUniqueCurrentSourceNext140Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 43 assertions, 0 failures

$ php lanes/libsqlite/examples/application-trigger-upsert-returning-view-unique-current-source-next140.php --self-test
application-trigger-upsert-returning-view-unique-current-source-next140 self-test passed
```

Dashboard delta: `phpPass` +43 focused PASS assertions, `phpFail` unchanged at 0, mapped upstream coverage unchanged at 606 / 1589.

Dependency closure: no new support component is needed; this reuses the existing bounded schema-record, view-trigger resolution, UPSERT trigger/FK yield, and current/next row-stream helpers.

Non-overlap: avoids accepted trigger/upsert savepoint next73, trigger RETURNING savepoint next64/65/68, recursive trigger/upsert RETURNING next118/126, deferred/view RETURNING savepoint next119/120/123/129, accepted trigger RAISE IGNORE UPSERT RETURNING savepoint batch137, schema view/trigger reparse next125/131, VFS/WAL/B-tree/JSON/planner/encoding clusters, and current next115-next139 queued surfaces. The new boundary is secondary UNIQUE constraint enforcement while an `INSTEAD OF` view trigger routes UPSERT `RETURNING` rows over current statement source rows.
