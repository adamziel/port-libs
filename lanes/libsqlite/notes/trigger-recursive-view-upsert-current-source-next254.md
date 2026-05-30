# trigger-recursive-view-upsert-current-source-next254

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger UPSERT current-source admission.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext254Plan`, layered after accepted next250 rowid provenance. Current recursive view UPSERT rows now require view-column mapping receipts tied to the current view source token and trigger source token before next-source rows publish. The fence keeps current rows visible while holding next-source rows when mapping receipts are missing, unexpected, stale, or when required view mapping columns are absent.

Application path: `application-trigger-recursive-view-upsert-current-source-next254.php` models copied `wp_options` imports through a recursive view trigger. It verifies that the current view mapping (`name` to `option_name`, `value` to `option_value`, and import columns) is acknowledged before plugin-next rows become visible.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext254Plan.php
# No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext254Plan.php

php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext254Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext254Test.php

php -l lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next254.php
# No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next254.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext254Test.php
# 1 test files, 76 assertions, 0 failures

php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next254.php --self-test
# application-trigger-recursive-view-upsert-current-source-next254 self-test passed
```

Expected dashboard movement: `phpPass +76` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped trigger/view/UPSERT inventory.

Dependency closure: no new support component needed; this reuses lane-local recursive view trigger, UPSERT, current-source sequence, and next250 rowid-provenance machinery.

Non-overlap: avoids accepted next250 rowid-provenance receipts, next247 statement sequence receipts, next244 commit watermarks, trigger RETURNING cursor/ticket/generation surfaces, row-value RETURNING, WAL/VFS, JSON table, planner, encoding, B-tree, PRAGMA, and suite-evidence clusters. The new behavior is current view-column mapping/source-token admission before next-source recursive view UPSERT rows publish.
