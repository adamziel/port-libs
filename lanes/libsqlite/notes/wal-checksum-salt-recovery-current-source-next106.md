# wal-checksum-salt-recovery-current-source-next106

This slice adds `SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan`, a bounded WAL current-source recovery planner for copied WordPress SQLite databases:

- recover the current WAL to its committed prefix before the next open uses it as the database source;
- recover the next restarted WAL generation with a changed salt;
- classify stale old-salt tail frames appended after the restarted WAL prefix;
- compare current-reader and next-reader page sources/images for copied `wp_options` pages.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalChecksumSaltRecoveryCurrentSourceNext106Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-checksum-salt-recovery-current-source-next106.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalChecksumSaltRecoveryCurrentSourceNext106Test.php`
- `php lanes/libsqlite/examples/wordpress-wal-checksum-salt-recovery-current-source-next106.php --self-test`

Focused result: `1 test files, 67 assertions, 0 failures`.

Expected dashboard movement: `phpPass` +67, from `40990` to `41057`, from the 67 independent PASS lines in `SQLiteWalChecksumSaltRecoveryCurrentSourceNext106Test.php`. `benchmarkDenominator.mapped` is unchanged because this slice does not claim a fresh upstream inventory unit.

Non-overlap: this does not repeat accepted WAL restart/truncate readers, savepoint byte truncation, VFS savepoint rollback apply, WAL recovery checkpoint savepoint next100, WAL savepoint restart reader next103, raw VFS checksum recovery apply, hot rollback-journal application, or WAL checkpoint transaction planning. The new surface is the current-source handoff between one recovered committed WAL prefix and the next restarted WAL generation with stale old-salt tail classification.

Dependency closure: no new support component is needed. The slice reuses existing native PHP WAL checksum, transaction recovery, checkpoint database-image, and reader visibility primitives.
