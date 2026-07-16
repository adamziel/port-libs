# foreign-key-deferred-cascade-update-trigger-current-source-next116

This slice extends `SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan`
with deferred parent `ON UPDATE CASCADE` behavior where child rows are updated
at deferred commit against the latest current-source child/grandchild rows.
It covers parent UPDATE statement state, deferred queue entries, child UPDATE
trigger ordering, grandchild `ON UPDATE CASCADE`, current child/grandchild
changes before COMMIT, NO ACTION deferred violations, and RESTRICT immediate
blocking.

Application smoke:

- `php lanes/libsqlite/examples/application-foreign-key-deferred-cascade-update-trigger-current-source-next116.php --self-test`
- Result: `application-foreign-key-deferred-cascade-update-trigger-current-source-next116 self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyDeferredCascadeUpdateTriggerCurrentSourceNext116Test.php`
- Result: `1 test files, 55 assertions, 0 failures`
- PASS delta: `+55` focused PASS lines; `lane-status.json` `phpPass` moves
  from `43574` to `43629`.
- `benchmarkDenominator.mapped`: unchanged; this composes already mapped
  foreign-key cascade, trigger, and current-source behavior rather than adding
  a new hydrated upstream inventory unit.

Non-overlap:

This avoids accepted deferred `ON DELETE CASCADE` trigger current-source
next108, FK ON UPDATE standalone corpus, recursive FK/trigger savepoint,
trigger RETURNING/UPSERT savepoint, schema generated-trigger reparse,
transaction savepoint trigger rollback, PRAGMA FK integrity, and accepted
WAL/VFS/B-tree/JSON current-source clusters. The new behavior is deferred
`ON UPDATE CASCADE` applied at COMMIT after a parent UPDATE statement has
yielded and current-source child/grandchild rows have changed.

Dependency closure:

No new support component is required. The slice reuses existing native PHP
row-array FK, trigger, and current-source modeling patterns; no ext/sqlite,
shell-out, live service, or shared dependency activation is needed.
