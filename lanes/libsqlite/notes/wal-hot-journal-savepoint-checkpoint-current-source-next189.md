# WAL hot-journal savepoint checkpoint current-source next189

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, which
builds on accepted next186 retained-WAL source admission. Once the retained WAL
header/token is valid, next189 admits a reader snapshot only when the requested
reader end frame is no later than the last committed retained WAL frame. Pages
with a retained frame are sourced from the WAL; pages without a retained frame
must have a checkpoint-database fallback image.

Blocked cases name stale next186 source tokens, missing/unreadable retained WAL
payloads, reader end frames that run beyond the retained commit frame, and
missing checkpoint database fallback pages.

## WordPress smoke

`examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next189.php`
models a copied WordPress plugin import after hot-journal/checkpoint recovery.
The reader keeps schema/options pages from retained WAL frames and reads
`active_plugins` from the checkpoint database because the savepoint draft frame
is not part of the committed retained snapshot.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext189Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next189.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext189Test.php

php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next189.php

git diff --check -- lanes/libsqlite
```

## Non-overlap

This slice selects reader snapshot page sources bounded by the retained commit
frame after next186 source admission. It does not repeat next186 WAL header
token validation, checkpoint transaction planning, VFS writer/sync application,
savepoint byte truncation, or rollback-journal apply.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local WAL parser,
checksum validation, and accepted hot-journal/savepoint checkpoint
current-source admission helpers.
