# real-upstream-corpus-pager-wal-dynamic-20260531T092820Z-0

Status: ready handoff for a real upstream pager/WAL dynamic corpus slice.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_walauto.test`
- Ported sections: `e_walauto-1.$tn.2` default 1000-frame auto-checkpoint threshold, `1.$tn.4` and `1.$tn.6` configured thresholds, `1.$tn.7` and `1.$tn.9` zero/negative disabled thresholds, `1.$tn.10` auto-checkpoint replacing a prior WAL hook, `1.$tn.11` WAL hook replacing auto-checkpoint, and `1.$tn.12` PASSIVE auto-checkpoint with no busy-handler invocation.

Implemented behavior:

- Added `SQLitePagerWalDynamicPlan::walAutoCheckpointPlan()` for generic WAL auto-checkpoint state.
- Added `SQLiteRealUpstreamCorpusPagerWalAutoCheckpointDynamic20260531T092820ZTest.php` with 1000 dynamic behavior cases plus upstream-source and ownership/dependency tests.
- The dynamic cases validate the plan against native `SQLiteWal::checkpointModeResult()` and `SQLiteWal::durableCheckpointResult()` using generated WAL bytes with validated checksums.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerWalDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerWalDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalAutoCheckpointDynamic20260531T092820ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalAutoCheckpointDynamic20260531T092820ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalAutoCheckpointDynamic20260531T092820ZTest.php`
  - `1 test files, 19917 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T065753ZTest.php`
  - `1 test files, 15416 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - no output, exit 0

Expected movement:

- `+1002` focused TestRunner PASS cases from the new auto-checkpoint corpus file.
- `lane-status.json` moves `phpPass` from `2838123` to `2839125`.
- Mapped coverage remains `1589 / 1589`; this is PASS-line growth over already mapped upstream corpus files.

Non-overlap:

- This owns the official `e_walauto.test` auto-checkpoint API state and PASSIVE checkpoint behavior.
- It avoids accepted `walhook.test` callback rows, `walsetlk*` timeout/locking rows, `walro*` readonly rows, `walbak` backup rows, `walfault` rows, checkpoint transaction wrappers, rollback-journal apply/commit, VFS writer/sync/lock, and WAL byte truncation.

Dependency closure:

- No new support component is needed. This reuses native `SQLiteWal` checkpoint parsing and the generic pager/WAL dynamic plan surface.
