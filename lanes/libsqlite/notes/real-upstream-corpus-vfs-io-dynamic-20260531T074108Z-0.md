# real-upstream-corpus-vfs-io-dynamic-20260531T074108Z-0

Source truth: hydrated upstream SQLite checkout under
`/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Upstream sections ported:

- `walvfs.test` 1.0 through 1.3: WAL mode `xSync` behavior for ordinary versus
  `SQLITE_IOCAP_SEQUENTIAL` VFS devices after a checkpoint.

Behavior covered:

- Sequential VFS devices defer the immediate WAL-header sync after a
  post-checkpoint insert in `synchronous=normal`.
- Ordinary VFS devices issue the WAL sync for that same post-checkpoint insert.
- `synchronous=full` keeps frame-content syncs while still distinguishing the
  extra ordinary-device header sync.
- Dynamic cases vary page size, seed row count, post-checkpoint insert count,
  sync mode, and device capability.

Focused PHP behavior file:

- `SQLiteVfsIoWalSequentialDynamicRealCorpusTest.php`
- 320 dynamic real-upstream cases.
- 6,400 focused behavior assertions.

Non-overlap:

- This does not repeat accepted VFS lock byte ranges, VFS lock-state/process
  locks, VFS file writer/sync/rollback apply, `io.test` page-size/cache-spill
  traffic, `walvfs.test` lock recovery/readmark/readonly-SHM sections, or pager
  WAL snapshot/checkpoint dynamic batches.
- The owned behavior is the `walvfs.test` 1.x sequential-device WAL sync-count
  distinction.

Dependency closure:

- No new support component is needed. The slice extends the existing
  `SQLiteVfsIoDynamicPlan` real-corpus planner.
