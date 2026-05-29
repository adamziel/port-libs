# WAL Hot-Journal Savepoint Checkpoint Current Source Next227

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded
publish-receipt validator for the WAL hot-journal savepoint checkpoint path.
The prior next219 slice finalizes savepoint scopes before checkpoint source
publication. This slice verifies that every finalized WordPress import
savepoint has a matching publish receipt with:

- the same current-source token and epoch;
- matching checkpoint frame, checkpoint cookie, and schema cookie;
- a hot-journal delete receipt;
- page digest coverage for the finalized scope page set;
- page digest equality against the finalized next219 scope digest map;
- the single expected next-source epoch.

Publication remains blocked for missing, extra, duplicate, stale-token,
stale-cookie, missing-journal, unfinalized-scope, and malformed page-digest
receipts.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext227Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next227.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext227Test.php`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next227.php`
  - `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next227 self-test passed`

## Non-Overlap

This next227 slice validates publish receipts after next219 scope finalization.
It does not repeat next211 reader acknowledgements, next219 finalization, WAL
byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply,
or checkpoint transaction planning.

## Dependency Closure

No new support component is needed. The slice reuses the existing next219
finalized savepoint scope output plus per-scope hot-journal delete receipts
and page digest seals.
