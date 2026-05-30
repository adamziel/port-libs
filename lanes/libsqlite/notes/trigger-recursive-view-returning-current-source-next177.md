# Trigger Recursive View RETURNING Current Source Next177

- Added `SQLiteTriggerRecursiveViewReturningCurrentSourceNext177Plan` for recursive `INSTEAD OF` view-trigger `RETURNING` streams that need a current-source resume token before attempted next-source rows can become visible.
- Focused behavior covers current-source drain order, held next-source rows, admitted next-source rows, reprepare-token mismatch holds, cursor resume tokens, page boundaries, recursive child rows, and malformed option guards.
- Application smoke: copied `wp_options` import through a recursive view trigger keeps current `RETURNING` rows visible while next-source rows are held behind the current-source cursor resume boundary.
- Non-overlap: avoids accepted next172 source pinning, next174 duplicate-key watermarking, accepted DML trigger RETURNING conflicts, recursive table-trigger savepoint rollback, deferred FK cascade triggers, and schema trigger/view invalidation batches.
- Dependency closure: no new support component is needed; this reuses lane-local recursive view trigger `RETURNING` current-source cursor modeling.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext177Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext177Test.php
php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next177.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext177Test.php
php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next177.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +74` from the new focused test file. `benchmarkDenominator.mapped` remains `604 / 1589`; this is current-source PHP behavior over already mapped trigger/view/RETURNING inventory, not a newly hydrated upstream row.
