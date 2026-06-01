# Real Upstream Corpus: VFS Persistence Dynamic 2026-06-01T1710Z

Micro-slice: `real-upstream-corpus-vfs-persistence-dynamic-20260601T1710Z-0`

Base accepted HEAD: `a7e4507f91add4e6fd74f6fd6165d39670d41514`

## Source Truth

Hydrated upstream SQLite files used from `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `pager1.test`: `pager1-32.1` chunk-size plus size-hint preallocation behavior.
- `walpersist.test`: `walpersist-1.6`, `walpersist-1.8`, and `walpersist-2.2` persistent WAL and journal-size-limit behavior.
- `zerodamage.test`: `zerodamage-1.1` and `zerodamage-1.2` powersafe-overwrite toggles.
- `jrnlmode.test`: `jrnlmode-5.4` and `jrnlmode-5.5` journal-size-limit persistence.

## Ported Behavior

Added `SQLiteRealUpstreamCorpusVfsPersistenceDynamic20260601T1710ZTest.php` with 1000 dynamic disk-backed sidecar cases plus 4 source/guard/dependency tests.

The dynamic cases exercise `SQLiteVfsFileControlPersistence` over reopened handles and assert:

- durable controls persist to a JSON sidecar: `persist_wal`, `chunk_size`, `mmap_size`, `powersafe_overwrite`, `size_limit`, `reserve_bytes`, and default `data_version`;
- reopened handles rehydrate the persisted sidecar before applying later file controls;
- transient controls such as `name_hint`, `lock_timeout`, and `write_hint` do not persist;
- `size_hint` succeeds or is ignored according to the persisted `size_limit`;
- lock acquisition/release and VFS file-control dependencies remain present.

Non-overlap: this uses the disk-backed `SQLiteVfsFileControlPersistence` sidecar path, not the accepted in-memory `SQLiteVfsFileControlPersistencePlan` `filectrl.test` sequence batch or accepted VFS writer/lock/sync clusters.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPersistenceDynamic20260601T1710ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPersistenceDynamic20260601T1710ZTest.php`
  - Result: `1 test files, 42014 assertions, 0 failures`
  - PASS cases: `1004`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 7 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - Result: `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no output

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteVfsFileControlPersistence`, `SQLiteVfsFileControlState`, `SQLiteVfsCapabilityPlan`, and `SQLiteVfsLockState`.
