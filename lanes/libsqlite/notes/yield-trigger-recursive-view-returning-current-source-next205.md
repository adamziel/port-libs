# Trigger Recursive View RETURNING Current Source Next205

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext205Plan`, a current-source sequence fence layered after the accepted next203 recursive view RETURNING generation handoff.

Behavior covered:

- Builds deterministic 32-hex current-source sequence acknowledgements from the visible current generation RETURNING rows.
- Holds next-source RETURNING rows until the current-source sequence is acknowledged with matching current and next source tokens.
- Preserves current-source RETURNING visibility while next-source rows are held.
- Records missing, unexpected, reordered, stale-current-token, stale-next-token, and inherited base-hold blockers.
- Tags current and next rows with source-sequence metadata for Application import diagnostics.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext205Test.php`
- Result: `1 test files, 93 assertions, 0 failures` with 93 PASS lines.

Application smoke:

- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next205.php`
- Result: `application-trigger-recursive-view-returning-current-source-next205 self-test passed`

Dependency closure:

- No new support component is needed. The patch reuses native recursive view RETURNING current-source/generation modeling and adds a bounded source-sequence fence.

Non-overlap:

- This extends next203 with a source-sequence fence after generation receipts. It avoids accepted next203 generation handoff, next196 child drain, next195 receipt fence, row-value RETURNING savepoints, trigger DML conflict handling, schema reparse, WAL/VFS, JSON table, planner, and B-tree clusters.
