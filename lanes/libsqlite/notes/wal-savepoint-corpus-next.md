# WAL Savepoint Corpus Next

Status: focused PHP corpus growth for pager/WAL savepoint rollback behavior.

Evidence:

- Added `SQLitePagerWalSavepointCorpusTest.php` with 30 independent PASS cases covering savepoint WAL frame bookkeeping, byte-prefix truncation, page-image rollback planning, VFS application, durable sync accounting, invalid WAL byte guards, paired WAL input validation, and read-only writer rejection.
- Added `application-wal-savepoint-corpus-next.php` to smoke a failed copied `wp_options` plugin-settings import rollback through native PHP VFS file handles.
- Expected `phpPass` delta: `+30`, from `933` to `963`.
- `benchmarkDenominator.mapped` unchanged; this is lane-scoped PHP corpus growth over pager/WAL behavior, not a newly mapped upstream inventory unit.

Non-overlap:

- Avoids accepted savepoint counter preservation, first page-image capture, WAL byte truncation-only diagnostics, VFS savepoint rollback application, rollback-journal commit/apply, checkpoint transaction, VFS writer/sync/lock clusters, and the upstream scalar/JSON/SELECT/window corpus.
- This slice keeps the behavior bounded to corpus coverage for pager savepoint WAL rollback semantics and applies the existing native VFS writer path in a Application smoke.

Dependency closure:

- Reuses existing bounded native components: `SQLiteSavepointStack`, `SQLiteWal`, and `SQLiteVfsFileWriter`.
- No new support component is needed.

Next:

- Continue with non-overlapping pager/VFS transaction application, WAL durability, or full-suite countability blockers on current accepted HEAD.
