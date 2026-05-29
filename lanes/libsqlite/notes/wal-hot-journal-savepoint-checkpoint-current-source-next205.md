# WAL Hot Journal Savepoint Checkpoint Current Source Next205

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`.
It extends the accepted next195 reader retry gate with per-page image/current-source
validation after hot-journal recovery, savepoint release, and WAL checkpoint
publication. A reader with a matching source token is still forced to reopen when
its cached page digest, checkpoint frame, cache generation, schema cookie, WAL salt,
hot-journal generation, or savepoint generation no longer matches the published
checkpoint source.

WordPress path: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next205.php`
models copied `wp_options` import readers after hot rollback-journal recovery.
Current schema/options readers are reused, while a stale option-page cache and a
stale-token autoload-index reader reopen before plugin import retry code reuses
cached pages.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext205Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next205.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext205Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next205.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard delta: update `phpPass` by the focused PASS-line delta verified
for the next205 test file. `benchmarkDenominator.mapped` is unchanged; this is a
current-source PHP behavior slice over already mapped WAL/hot-journal/savepoint
checkpoint inventory, not a fresh upstream Tcl inventory unit.

Non-overlap: avoids accepted WAL byte truncation, VFS savepoint rollback apply,
rollback-journal commit/apply, checkpoint transaction planning, hot-journal
checkpoint byte publication, and next195 token-only reader retry admission. The
new surface is page-image digest/cache-generation validation before reusing
readers across the hot-journal checkpoint current-source boundary.

Dependency closure: no new support component is needed. The slice reuses
lane-local checkpoint current-source tokens, WAL frame bounds, page-image digests,
and reader cache metadata.
