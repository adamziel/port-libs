# trigger-recursive-view-returning-current-source-next217

Behavior slice: recursive INSTEAD OF view-trigger `RETURNING` current-source payload provenance before a next-source handoff.

The new `SQLiteTriggerRecursiveViewReturningCurrentSourceNext217Plan` builds on accepted next212 current-source yield receipts, then adds a separate provenance fence over the current `RETURNING` payloads. A next-source result stream is visible only after the current-source provenance receipts are acknowledged, ordered when required, and still match the expected current view/trigger source plus returned value/name/depth/ordinal fields.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext217Test.php`
- Result: `1 test files, 96 assertions, 0 failures`

Application smoke:

- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next217.php`
- Expected output: `application-trigger-recursive-view-returning-current-source-next217 self-test passed`

Non-overlap:

- Avoids accepted next210 sequence fencing, next211 source sealing, and next212 yield receipts.
- Avoids accepted row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK trigger slices, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.

Dependency closure:

- No new support component is needed. This reuses the native recursive view-trigger `RETURNING` current-source/yield machinery and adds only a bounded payload-provenance admission fence.
