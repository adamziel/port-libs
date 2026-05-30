# WAL hot-journal savepoint checkpoint current-source next219

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded
current-source admission step that publishes a WAL hot-journal checkpoint only
after savepoint scopes are fully retired. The plan verifies that each scope is
released, depth is zero, rollback generation is not newer than the current
source, checkpoint/schema cookies match, hot-journal delete receipts exist, WAL
reset frame is not before the checkpoint frame, page digests are present, and
scope readers do not overlap stale readers still fenced for reopen.

## Focused evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext219Test.php`
- Result: `1 test files, 62 assertions, 0 failures`
- PASS-line delta: `+62`
- Application smoke:
  `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next219.php`

## Non-overlap

This next219 slice finalizes savepoint scopes before publishing checkpoint
next-source state. It does not repeat next211 reader acknowledgements, next208
reader-slot digest validation, WAL byte truncation, VFS savepoint rollback
apply, rollback-journal commit/apply, checkpoint transactions, or hot-journal
reader-cache/token-only admission.

## Dependency closure

No new support component is needed. The implementation reuses next211 reader
acknowledgement state plus local savepoint generation, checkpoint cookie, schema
cookie, journal delete receipt, and page digest metadata.
