# real-upstream-corpus-pager-wal-dynamic-20260601T003804Z-0

Status: focused real upstream pager/WAL corpus growth for `savepoint2.test`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint2.test`
- Upstream sections `savepoint2-2.1` through `savepoint2-21.7`.
- Ported behavior cluster: the 20-iteration WAL-mode savepoint signature loop that alternates optional `BEGIN`, `SAVEPOINT one`, `ROLLBACK TO one`, nested `SAVEPOINT two`, `ROLLBACK TO two`, `SAVEPOINT three` / `RELEASE three`, final `COMMIT`, integrity checks, and `wal_check_journal_mode`.

Patch summary:

- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::savepoint2WalSignatureRows()` with 1,000 dynamic rows across all 140 upstream loop sections.
- Added `SQLiteRealUpstreamCorpusPagerWalSavepoint2Dynamic20260601Test.php` with 1,003 focused PASS cases and 20,020 assertions.
- The tests exercise the existing `SQLiteSavepointStack` WAL rollback/release/commit behavior, including transaction-savepoint versus explicit-transaction cases and rollback frame boundaries for `one` and `two`.

Non-overlap:

- This covers `savepoint2.test` full WAL signature-preservation loop, not the already accepted `savepoint4/5/6/7` fault playback, `savepointfault.test`, WAL checkpoint/byte-truncation, VFS writer/sync/lock/rollback, rollback-journal commit/apply, B-tree, JSON, SELECT, Unicode GLOB, or the older one-section `savepoint2-4.1` generic dynamic batch.

Dependency closure:

- No new support component is needed. The slice reuses lane-local `SQLiteSavepointStack` and the existing pager/WAL corpus plan infrastructure.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalSavepoint2Dynamic20260601Test.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalSavepoint2Dynamic20260601Test.php` passed: `1 test files, 20020 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Root harness:

- Not run - isolated micro-slice.
