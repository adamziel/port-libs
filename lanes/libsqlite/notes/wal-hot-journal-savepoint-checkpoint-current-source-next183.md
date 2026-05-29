# WAL hot-journal savepoint checkpoint current-source next183

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a
post-apply verifier for the already-published next180 file map. It admits a
fresh WAL reader current-source token only when:

- the next180 result is published and not rolled back;
- the caller-visible file digest still matches the next180 published digest;
- every next180 verification row still matches;
- the hot journal remains deleted after a verified delete;
- durable directory sync evidence is present; and
- supplied reader cache tokens match the newly computed current-source token.

Blocked cases report explicit reasons for stale reader tokens, resurrected hot
journals, file digest drift, missing directory sync, and failed/non-next180
apply results.

## WordPress smoke

`examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next183.php`
models a copied WordPress plugin import that restarts after hot-journal and
checkpoint resume. It verifies the post-apply file map and admits a fresh WAL
reader token only after durable directory sync evidence.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext183Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next183.php
No syntax errors detected

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext183Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next183.php
status: wal-hot-journal-savepoint-checkpoint-current-source-next183
hotJournalDeleted: true
directorySyncVerified: true
```

## Non-overlap

This slice verifies post-apply current-source admission and reader cache tokens.
It does not repeat next180 atomic publication, next177 operation planning,
hot-journal recovery, checkpoint transaction planning, WAL byte truncation, or
VFS writer/sync application.

## Dependency closure

No new support component is needed. The slice reuses native PHP WAL parsing,
hot-journal/savepoint checkpoint resume evidence, and next180 file-map
publication metadata.
