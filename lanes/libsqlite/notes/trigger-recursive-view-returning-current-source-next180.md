# Trigger Recursive View RETURNING Current Source Next180

- Added `SQLiteTriggerRecursiveViewReturningCurrentSourceNext180Plan` for recursive `INSTEAD OF` view-trigger `RETURNING` streams where current-source rows must keep the current view/trigger source signature after a next source changes view columns or trigger source.
- Focused behavior covers current/next source signatures, source-token and drain-ack admission, reprepare-token holds, visible current rows, held next rows, source-frame metadata, and malformed source-token/view-shape guards.
- Application smoke: copied `wp_options` import through a recursive view trigger freezes current `RETURNING` rows while a plugin migration adds an `import_source` view column in the next schema source.
- Non-overlap: avoids accepted next172 source pinning, next174 duplicate-key watermarking, next175 savepoint release/rollback, next177 cursor resume-token admission, DML trigger conflict slices, deferred FK trigger slices, and schema reparse batches.
- Dependency closure: no new support component is needed; this reuses lane-local recursive view trigger `RETURNING` current-source cursor and source snapshot modeling.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext180Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext180Test.php
php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next180.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext180Test.php
php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next180.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +73` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped trigger/view/RETURNING inventory, not a newly hydrated upstream row.
