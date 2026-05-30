# Trigger Recursive View RETURNING Current Source Next183

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext183Plan`, a reset-barrier layer over the accepted recursive view trigger RETURNING source-snapshot chain.

Behavior covered:

- Current-source recursive `INSTEAD OF` view-trigger RETURNING rows can be yielded, then invalidated by a rollback/reset token before they become durable.
- Attempted next-source rows stay blocked when the current source rolls back, even if next-source admission succeeded before the reset barrier.
- A committed current source admits both current and next RETURNING streams.
- Rollback-token mismatch keeps the current rows visible and blocks next rows with explicit reasons.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext183Test.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next183.php`

Dependency closure: no new support component needed; this reuses recursive view-trigger RETURNING source snapshots and adds lane-local reset-barrier visibility modeling.

Non-overlap: this adds rollback/reset visibility after accepted next180 source snapshots. It avoids accepted next177 resume-token and next180 source-signature admission behavior.
