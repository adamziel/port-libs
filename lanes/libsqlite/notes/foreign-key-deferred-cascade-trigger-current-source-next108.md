# foreign-key-deferred-cascade-trigger-current-source-next108

This slice adds `SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan`, a
bounded current-source planner for deferred parent deletes whose `ON DELETE
CASCADE` child deletes and child DELETE triggers are applied at deferred commit.
It covers current child/grandchild source changes between statement delete and
commit, BEFORE/AFTER child trigger ordering, grandchild cascade interactions,
NO ACTION deferred violations, and RESTRICT immediate blocking.

Application smoke:

- `php lanes/libsqlite/examples/application-foreign-key-deferred-cascade-trigger-current-source-next108.php --self-test`
- Result: `application-foreign-key-deferred-cascade-trigger-current-source-next108 self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyDeferredCascadeTriggerCurrentSourceNext108Test.php`
- Result: `1 test files, 60 assertions, 0 failures`
- PASS delta: `+60` focused PASS lines; `lane-status.json` `phpPass` moves
  from `41873` to `41933`.
- `benchmarkDenominator.mapped`: unchanged; this composes already mapped
  foreign-key deferred cascade, trigger, and current-source behavior rather
  than adding a new hydrated upstream inventory unit.

Non-overlap:

This avoids accepted standalone deferred FK cascade/action corpora, FK ON
UPDATE action corpora, standalone FK cascade trigger current-next behavior,
trigger deferred FK NO ACTION checks, trigger/FK RETURNING/UPSERT savepoints,
PRAGMA FK integrity pointer-map checks, schema DDL reparse, WAL/VFS/B-tree/JSON
current-source clusters, and next106 queued savepoint-trigger rollback and DML
trigger/RETURNING conflict surfaces. The new behavior is the deferred-commit
composition where cascaded child DELETE triggers observe current child and
grandchild sources after the parent DELETE statement has yielded.

Dependency closure:

No new support component is required. The slice reuses existing native PHP
row-array FK, trigger, and current-source modeling patterns; no ext/sqlite,
shell-out, live service, or shared dependency activation is needed.
