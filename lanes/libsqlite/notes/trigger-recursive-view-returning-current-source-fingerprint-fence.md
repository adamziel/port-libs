# trigger-recursive-view-returning-current-source-fingerprint fence

## Behavior

Adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceFingerprintFence()`, extending the accepted next188 current-source row-ordinal watermark with a payload/source fingerprint fence. Recursive view-trigger `RETURNING` rows from the next source are only publishable after the current source's exact row fingerprints are acknowledged; missing, unexpected, reordered, or stale-salt fingerprints keep next-source rows held.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceFingerprintFenceTest.php`
  - `1 test files, 74 assertions, 0 failures`
  - 74 PASS lines

## Application Smoke

- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-fingerprint-fence.php`
  - `application-trigger-recursive-view-returning-current-source-fingerprint-fence self-test passed`

## Non-Overlap

This slice adds payload/source fingerprint admission after next188 row-ordinal watermarks. It does not repeat next188 ordinal fencing, next185 nested-depth drain, next184 checkpoint acknowledgements, row-value RETURNING savepoint work, schema reparse trigger/view work, deferred FK trigger behavior, JSON table behavior, WAL/VFS pager durability, or B-tree current-source slices.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP recursive view trigger RETURNING rows, current-source row watermarks, and payload fingerprint metadata.
