# WAL hot-journal savepoint checkpoint current-source next211

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a
checkpoint admission fence layered after next205 reader page-image validation.
The next checkpoint source is admitted only when retained readers acknowledge
the exact current-source token, epoch, checkpoint frame, checkpoint cookie,
schema cookie, and page digest, while stale or dirty readers are explicitly
fenced for reopen.

Blocked cases report missing reader acknowledgements, missing reopen fences,
unexpected acknowledgements from stale readers, stale token/cookie/digest
acks, orphan acknowledgement rows, and non-ready next205 reader plans.

## WordPress Smoke

`examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next211.php`
models a copied WordPress plugin import that resumes after hot-journal recovery
and checkpoint publication. Fresh `wp_options` readers can keep their cache,
while an old plugin reader and dirty index reader are fenced for reopen before
the next source epoch is published.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext211Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next211.php
No syntax errors detected

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext211Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 69 assertions, 0 failures
69 PASS lines

php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next211.php
status: wal-hot-journal-savepoint-checkpoint-current-source-next211
checkpointAdmitted: true
admittedReaders: wp-schema-reader, wp-options-reader
reopenReaders: wp-old-plugin-reader, wp-dirty-index-reader
```

## Non-overlap

This slice consumes next205 reader page-image validation and adds a separate
acknowledgement/reopen-fence admission step. It does not repeat next205
digest validation, WAL byte truncation, VFS savepoint rollback apply,
rollback-journal commit/apply, checkpoint transaction planning, or
writer-handle fencing.

## Dependency Closure

No new support component is needed. The slice reuses native PHP checkpoint
metadata, reader page digests, current-source tokens, and page-cache reopen
fence metadata already present in the lane.
