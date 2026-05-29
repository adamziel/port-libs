# WAL Hot-Journal Savepoint Checkpoint Current Source Next185

## Behavior

Adds checkpoint-generation admission after hot-journal recovery, savepoint rollback,
and current-source checkpoint publication. A prepared statement or reader that has
the right source token, epoch, schema cookie, and root-page admission from the
next182 path is still forced to reprepare/reopen when its observed WAL checkpoint
sequence or WAL salt belongs to an older or next generation.

## WordPress path

The smoke models a copied WordPress import that recovers a hot rollback journal,
rolls back a plugin savepoint, and then decides whether cached `wp_options` and
`wp_usermeta` prepared readers may remain open. Only readers that observed the
current WAL checkpoint sequence and salt are retained.

## Non-overlap

This does not repeat WAL byte truncation, VFS file application, hot rollback
journal application, checkpoint transaction planning, next167 publication
fingerprints, or next182 token/schema/root-page prepared statement admission.
It adds a narrower generation guard over already parsed WAL header checkpoint
sequence and salt values.

## Dependency Closure

No new support component is needed. The slice reuses lane-local native WAL header
parsing and current-source prepared statement metadata.

## Verification

Focused verification run in this lane:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext185Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next185.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext185Test.php`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next185.php`
- `git diff --check -- lanes/libsqlite`
