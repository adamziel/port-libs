# real-upstream-corpus-pager-wal-dynamic-20260601T221036Z-0

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/crash4.test`
  - `sql_cmd_list`: creates table `a`, inserts ids 1 through 10, then updates row 3 after a close/reopen boundary.
  - `crash4_cksum_set`: records `allcksum` before the sequence and after each SQL statement.
  - `crash4-1.$cnt.1`: alternates crashes against `test.db` and `test.db-journal` while increasing `delay` by `int($cnt/50)+1`.
  - `crash4-1.$cnt.2`: `integrity_check` must remain ok after recovery.
  - `crash4-1.$cnt.3`: the recovered `allcksum` must be a member of the precomputed checksum set.

## Patch

Added `SQLitePagerCrashRecoveryDynamicPlan::crash4SequenceChecksumProfile()` and covered it with `SQLiteRealUpstreamCorpusPagerWalCrash4Dynamic20260601T221036ZTest.php`.

Focused TestRunner growth:

- 1,000 dynamic crash4 checksum-membership recovery cases over upstream-shaped `cnt 1..1000`.
- 4 source citation, malformed-input, count, and non-overlap/dependency PASS cases.
- Total: 1,004 focused PASS cases, 35,019 focused assertions in the new test file.

## Non-Overlap

This slice covers direct `crash4.test` power-loss recovery where recovery is accepted only if the resulting database checksum matches a precomputed statement boundary. It avoids the already accepted/covered crash5 movepage malloc, crash6 page-size rollback, crash7 VACUUM recovery, crash8 hot-journal, WAL checkpoint, rollback-commit, VFS sync/write/lock, super-journal, master-journal, and walcrash clusters.

## Dependency Closure

No new support component is required. The patch reuses the existing bounded pager crash recovery profile class, rollback-journal recovery modeling, TestRunner infrastructure, and hydrated upstream SQLite corpus as source truth.

## Verification

- `php -l lanes/libsqlite/src/SQLitePagerCrashRecoveryDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerCrashRecoveryDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCrash4Dynamic20260601T221036ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCrash4Dynamic20260601T221036ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCrash4Dynamic20260601T221036ZTest.php`
  - `1 test files, 35019 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCrashDynamic20260531T161009ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCrash4Dynamic20260601T221036ZTest.php`
  - `2 test files, 64425 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 8 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
