# PRAGMA integrity/freelist/foreign-key current-next49

This slice adds `SQLitePragmaIntegrityFreelistForeignKeyPreflight`, a bounded
Application import preflight that composes native `PRAGMA integrity_check`,
freelist snapshot evidence, and `PRAGMA foreign_key_check` rows into one
current/next gate.

Focused behavior:

- clean freelist plus clean FK rows returns `status=ready`;
- FK-only violations block the next import state while preserving clean
  integrity evidence;
- freelist pointer-map mismatches block the next import state while preserving
  FK counts;
- combined failures report both `integrity_check` and `foreign_key_check`
  blockers without hiding the limited integrity result;
- quick_check syntax and limit parsing remain delegated to the current PRAGMA
  implementation.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityFreelistForeignKeyCurrentNext49Test.php`
  - `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-integrity-freelist-foreignkey-current-next49.php --self-test`
  - `application-pragma-integrity-freelist-foreignkey-current-next49 self-test passed`
- PHP lint changed PHP files passed.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

- Avoids accepted PRAGMA rootpage diagnostics, PRAGMA index/freelist
  pointer-map current-next38 child checks, deferred foreign-key current-next30
  transaction planning, and the accepted VFS/WAL/B-tree clusters. This slice is
  only the composed current/next PRAGMA gate for Application import staging.

Dependency closure:

- No new support component is needed. The slice reuses `SQLitePragmaIntegrityCheck`,
  `SQLitePragmaForeignKeyIntegrity`, and `SQLitePragmaSnapshot`.
