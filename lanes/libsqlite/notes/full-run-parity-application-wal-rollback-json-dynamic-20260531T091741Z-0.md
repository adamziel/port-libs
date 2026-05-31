# full-run-parity-application-wal-rollback-json-dynamic-20260531T091741Z-0

Scope: app-WAL rollback JSON dynamic parity follow-up on accepted base
`0098ded681a4eb1c42c3ee09d87f3167111f8b69`.

Behavior:

- Added `SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupScenarios()`.
- The new fixtures model a rollback-disabled failed JSON batch that keeps two
  successfully materialized WAL frames, then starts a later successful batch
  from that partial database/WAL prefix.
- The follow-up batch appends contiguous WAL frames, preserves the partial
  prefix bytes, carries committed prefix pages into the savepoint boundary,
  keeps JSONB/text mode parity on the continued catalog row, and records the
  final appended frame as the commit frame.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`, especially
  `wal3-1.*`, covers WAL rollback/savepoint rollback preserving external
  consistency after WAL-index frame removal.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test`,
  especially `savepoint-1.*` and `savepoint-2.*`, covers `SAVEPOINT`,
  `ROLLBACK TO`, and release behavior.

Focused movement:

- Before: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  reported `1 test files, 7019 assertions, 0 failures`.
- After: same command reports `1 test files, 7568 assertions, 0 failures`.
- Delta: `+549` focused assertions/PASS lines in the existing app-WAL rollback
  JSON dynamic parity file.
- `lane-status.json` selected `phpPass` moves from `2835919` to `2836468`;
  mapped coverage remains `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. The patch reuses existing native JSON
  mutation, savepoint, WAL checksum, WAL frame materialization, and generic
  application row primitives.

Non-overlap:

- This does not repeat accepted app-WAL basic rollback, preexisting WAL
  rollback, inserted-setting rollback, malformed WAL admission, successful
  materialized WAL, full-run retry/follow-up success, committed-prefix tail
  failure, or rollback-disabled materialized failure coverage.
- The new owned behavior is the next successful batch after a
  rollback-disabled partial failure has already materialized a committed WAL
  prefix.
