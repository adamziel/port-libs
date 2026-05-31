# real-upstream-corpus-pager-wal-dynamic-20260531T061959Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T061959Z`
Base accepted HEAD: `68a3731675769814ce7d56857d9182ac7f8b3613`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`
- Covered upstream sections:
  - `wal3-1.0` large WAL seed creates 4056 committed frames before rollback churn.
  - `wal3-1.$i.1` rollback removes WAL-index hash-table entries without integrity loss.
  - `wal3-1.$i.2` external reader keeps the 4018-row committed snapshot.
  - `wal3-1.$i.5` copied database/WAL pair recovers after rollback churn.
  - `wal3-2.multiproc.4` checkpoint is reader-blocked before older snapshots release.
  - `wal3-2.singleproc.5` checkpoint backfills after older reader commits.
  - `wal3-3.*` byte-is-zero checks distinguish backfilled pages from pinned pages.
  - `wal3-4.*` WAL restart preserves readable snapshots across checkpoint attempts.

## Behavior

Added `SQLiteRealUpstreamPagerWal3RollbackHashDynamic20260531T061959ZTest.php`,
a real upstream-derived dynamic WAL corpus shard. It builds bounded WAL byte
streams with committed frames followed by valid rollback-tail frames, then
verifies committed-prefix recovery, discarded rollback-tail accounting,
reader snapshot maps, checkpoint reader visibility, durable checkpoint output,
and byte-exact committed WAL preservation through existing native PHP WAL
helpers.

The test keeps upstream `wal3.test` row-count/frame-count constants as explicit
source assertions while using smaller synthetic page images so the focused
runner stays practical in this isolated lane.

## Verification

- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWal3RollbackHashDynamic20260531T061959ZTest.php`
  - `1 test files, 34009 assertions, 0 failures`
  - Focused PASS growth: `+1001` TestRunner cases from real upstream `wal3.test`.

## Non-overlap

This targets upstream `wal3.test` rollback hash-table removal and multi-client
checkpoint snapshot behavior. It does not repeat accepted `wal2.test` 15.x
checkpoint fullfsync coverage, `walcrash2/3/4` crash recovery coverage,
`walrestart`, `walpersist`, `walshared`, `wal5`, `pager2`, WAL byte
truncation, VFS writer/sync/lock/rollback clusters, rollback-journal
commit/super-journal behavior, or pager master-journal reader-cache surfaces.

Mapped denominator coverage remains complete at `1589 / 1589`; this is
countable PHP PASS-line growth over already mapped real upstream WAL inventory.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local
`SQLiteWal` parsing, committed-prefix recovery, reader snapshot, checkpoint,
and durable sidecar byte planning helpers.
