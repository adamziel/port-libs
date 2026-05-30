# Trigger Recursive View RETURNING Current Source Next157

Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext157Plan`, a bounded current-source behavior slice for recursive view row materialization feeding an `INSTEAD OF` trigger with `RETURNING`.

The slice models copied Application `wp_options` rows where a recursive view expands option-parent chains, an `INSTEAD OF` trigger writes audit option rows, and `RETURNING` rows from the current view source must be drained before a next schema/view source is admitted. The next source is still planned and exposed as attempted-only evidence until `admit_next_source` is set.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext157Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext157Test.php
php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next157.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext157Test.php
php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next157.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +58` from the new focused test file. Mapped upstream coverage is unchanged; this is current-source PHP behavior over already mapped trigger/view/RETURNING and recursive-view surfaces.

Dependency closure: no new support component is needed. The slice reuses lane-local row-array trigger, recursive view materialization, current/next source, and RETURNING projection primitives.

Non-overlap: avoids accepted trigger UPSERT view next149, recursive deferred view next128, savepoint view RETURNING next134, trigger recursive savepoint/UPSERT slices, parser-level JSON table source/cursor behavior, SELECT SQL text/group/order/subquery clusters, VFS/WAL/pager savepoint application, B-tree, encoding, PRAGMA, and suite evidence handoffs. The new surface is specifically recursive view-source row expansion feeding `INSTEAD OF` trigger `RETURNING` current-source drain before next-source admission.
