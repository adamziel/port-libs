# real-upstream-corpus-vfs-io-dynamic-20260601T130342Z-0

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/lock6.test`
- Ported scenarios: `lock6-1.1` through `lock6-1.6`
- Behavior: proxy-locking VFS interprocess flow where a child host forces
  `:auto:` proxy locking and reads `sqlite_master`, a parent host sees
  `database is locked`, parent `:auto:` reports `:auto: (not held)`, a
  `notmine` proxy stays blocked, and a `mine` proxy succeeds after the child
  closes.

## Local Changes

- Added `SQLiteVfsIoDynamicPlan::lockProxyInterprocessProfile()` as a generic
  VFS I/O dynamic profile over `SQLitePragmaLockProxyFileState`.
- Added `SQLiteRealUpstreamCorpusVfsLockProxyInterprocessDynamicTest.php` with
  1,000 generated upstream-shaped lock6 cases plus source-citation, input
  guard, and non-overlap/dependency assertions.
- Added `application-vfs-lock-proxy-interprocess.php` generic application smoke
  with `--self-test`.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`:
  `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsLockProxyInterprocessDynamicTest.php`:
  `No syntax errors detected`
- `php -l lanes/libsqlite/examples/application-vfs-lock-proxy-interprocess.php`:
  `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsLockProxyInterprocessDynamicTest.php`:
  `1 test files, 55017 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`:
  `1 test files, 6 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-vfs-lock-proxy-interprocess.php --self-test`:
  `application-vfs-lock-proxy-interprocess self-test passed`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`:
  `lane-status json ok`
- `git diff --check -- lanes/libsqlite`: clean

## Status Delta

- Focused PASS-line growth: `+1003` real TestRunner cases.
- `lane-status.json` `phpPass`: `5893009 -> 5894012`.
- Mapped denominator unchanged; this is behavior/assertion growth from an
  already hydrated upstream file.

## Non-Overlap

This slice covers `lock6.test` child/parent interprocess force proxy locking
with `:auto:`, `notmine`, and `mine` proxy transitions. It avoids accepted
standalone `pragma.test` `lock_proxy_file` coverage, `lock7` schema-read,
`sharedlock`, VFS process locks, lock-byte ranges, lock-state application, file
writer/sync/rollback, WAL checkpoint, and B-tree clusters.

## Dependency Closure

No new support component is needed. The slice reuses the bounded
`SQLitePragmaLockProxyFileState` component inside `SQLiteVfsIoDynamicPlan` and
the hydrated upstream `lock6.test` source truth.
